<?php
define('SECURE_ACCESS', true);
require_once 'includes/config.php';
require_once 'includes/auth.php';
requireLogin();

$current_user = getCurrentUser();
$current_role = $current_user['role'];
$current_user_id = $_SESSION['user_id'];

$is_branch = ($current_role === 'branch');
$is_observer = ($current_role === 'observer');
$is_admin = ($current_role === 'admin');
$is_readonly = ($is_observer || $is_admin);

$tab = $_GET['tab'] ?? 'balance';
if (!in_array($tab, ['balance', 'income'])) $tab = 'balance';

// ============ Branch ID & Access ============
if ($is_branch) {
    $branch_id = $current_user_id;
    $branch_name = $current_user['branch_name'] ?? 'شعبه';
} elseif ($is_observer || $is_admin) {
    $branch_id = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : 0;
    if ($branch_id == 0) die('شناسه شعبه مشخص نشده است.');
    
    $stmt = mysqli_prepare($conn, "SELECT id FROM observer_assignments WHERE observer_id = ? AND branch_id = ?");
    if ($is_observer) {
        mysqli_stmt_bind_param($stmt, "ii", $current_user_id, $branch_id);
        mysqli_stmt_execute($stmt);
        if (mysqli_num_rows(mysqli_stmt_get_result($stmt)) == 0) die('دسترسی به این شعبه مجاز نیست.');
    }
    $stmt = mysqli_prepare($conn, "SELECT branch_name FROM users WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $branch_id);
    mysqli_stmt_execute($stmt);
    $branch = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    $branch_name = $branch['branch_name'] ?? 'نامشخص';
} else { die('دسترسی غیرمجاز'); }

// ============ تاریخ ============
$raw_date = $_GET['date'] ?? jdate('Y-m-d');
$selected_date = str_replace('/', '-', $raw_date); 
$selected_date_ui = str_replace('-', '/', $selected_date);
$sel_year = (int)substr($selected_date, 0, 4);
$sel_month = (int)substr($selected_date, 5, 2);

// ============ AJAX Polling ============
if (isset($_GET['poll']) && $_GET['poll'] == '1' && $is_readonly) {
    header('Content-Type: application/json; charset=utf-8');
    $last_time = isset($_GET['last']) ? (int)$_GET['last'] : 0;
    
    $poll_data = ['changed' => false, 'income_changed' => false, 'now' => time(), 'last_updated' => $last_time, 'new_messages' => []];
    
    // چک تراز
    $stmt = mysqli_prepare($conn, "SELECT UNIX_TIMESTAMP(updated_at) as updated FROM daily_reports WHERE user_id = ? ORDER BY updated_at DESC LIMIT 1");
    mysqli_stmt_bind_param($stmt, "i", $branch_id); mysqli_stmt_execute($stmt);
    $b_row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    $b_updated = $b_row['updated'] ?? 0;
    if ($b_updated > $last_time) $poll_data['changed'] = true;
    
    // چک درآمد
    $i_updated = 0;
    $q1 = mysqli_prepare($conn, "SELECT UNIX_TIMESTAMP(updated_at) as u FROM income_daily_records WHERE branch_id = ? AND record_date = ? ORDER BY updated_at DESC LIMIT 1");
    if($q1){ mysqli_stmt_bind_param($q1,"is",$branch_id,$selected_date); mysqli_stmt_execute($q1); $r1=mysqli_fetch_assoc(mysqli_stmt_get_result($q1)); if($r1) $i_updated=max($i_updated,$r1['u']); mysqli_stmt_close($q1); }
    $q2 = mysqli_prepare($conn, "SELECT UNIX_TIMESTAMP(updated_at) as u FROM income_monthly_records WHERE branch_id = ? AND record_year = ? AND record_month = ? ORDER BY updated_at DESC LIMIT 1");
    if($q2){ mysqli_stmt_bind_param($q2,"iii",$branch_id,$sel_year,$sel_month); mysqli_stmt_execute($q2); $r2=mysqli_fetch_assoc(mysqli_stmt_get_result($q2)); if($r2) $i_updated=max($i_updated,$r2['u']); mysqli_stmt_close($q2); }
    if ($i_updated > $last_time) $poll_data['income_changed'] = true;
    $poll_data['last_updated'] = max($b_updated, $i_updated, $last_time);
    
    // چک پیام
    $report_id_poll = 0;
    $stmt = mysqli_prepare($conn, "SELECT id FROM daily_reports WHERE user_id = ? AND report_date = ?");
    mysqli_stmt_bind_param($stmt, "is", $branch_id, $selected_date);
    mysqli_stmt_execute($stmt);
    $rep_poll = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    if ($rep_poll) {
        $report_id_poll = $rep_poll['id'];
        $last_msg_id = (int)($_GET['last_msg_id'] ?? 0);
        $stmt = mysqli_prepare($conn, "SELECT c.*, u.branch_name FROM comments c JOIN users u ON c.user_id = u.id WHERE c.report_id = ? AND c.id > ? ORDER BY c.id ASC");
        mysqli_stmt_bind_param($stmt, "ii", $report_id_poll, $last_msg_id);
        mysqli_stmt_execute($stmt);
        $msg_result = mysqli_stmt_get_result($stmt);
        while ($msg = mysqli_fetch_assoc($msg_result)) $poll_data['new_messages'][] = $msg;
    }
    echo json_encode($poll_data); exit;
}

// ============ Load Balance Data ============
$report_data = []; $report_id = 0; $last_updated_ts = 0;
$stmt = mysqli_prepare($conn, "SELECT id, report_data, UNIX_TIMESTAMP(updated_at) as updated_ts FROM daily_reports WHERE user_id = ? AND report_date = ?");
mysqli_stmt_bind_param($stmt, "is", $branch_id, $selected_date); mysqli_stmt_execute($stmt);
$rep = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if ($rep) { $report_id = $rep['id']; $report_data = json_decode($rep['report_data'], true) ?? []; $last_updated_ts = (int)$rep['updated_ts']; }

$debtors = $report_data['debtors'] ?? []; $creditors = $report_data['creditors'] ?? [];
$pettys = $report_data['pettys'] ?? []; $bankers = $report_data['bankers'] ?? [];
$matrixValues = $report_data['matrixValues'] ?? []; 
$controlRows = $report_data['controlRows'] ?? []; 
$controlDescs = $report_data['controlDescs'] ?? [];
$totalDebtors = array_sum(array_column($debtors, 'amt')); $totalCreditors = array_sum(array_column($creditors, 'amt'));
$totalPetty = array_sum(array_column($pettys, 'amt')); $totalBankers = array_sum(array_column($bankers, 'amt'));

// ⭐ دریافت سقف تنخواه از report_data
$petty_ceiling = $report_data['ceiling'] ?? 0;

// داینامیک
$dyn_items_list = []; $dyn_values = [];
$di_res = mysqli_query($conn, "SELECT * FROM dynamic_items WHERE active=1 ORDER BY sort_order");
while($r=mysqli_fetch_assoc($di_res)) $dyn_items_list[] = $r;
if($report_id > 0){
    $stmt = mysqli_prepare($conn, "SELECT item_id, amount_gram FROM dynamic_records WHERE report_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $report_id); mysqli_stmt_execute($stmt);
    $dr = mysqli_stmt_get_result($stmt);
    while($row=mysqli_fetch_assoc($dr)) $dyn_values[$row['item_id']] = $row['amount_gram'];
}

// ============ Load Income Data ============
$income_daily_items = []; $income_daily_data = []; $daily_rate = 0;
$di_res2 = mysqli_query($conn, "SELECT * FROM income_daily_items WHERE active=1 ORDER BY category, sort_order");
while($r=mysqli_fetch_assoc($di_res2)) $income_daily_items[] = $r;

$stmt = mysqli_prepare($conn, "SELECT item_id, amount_rial, amount_gram, gold_rate FROM income_daily_records WHERE branch_id=? AND record_date=?");
if($stmt){
    mysqli_stmt_bind_param($stmt,"is",$branch_id,$selected_date); mysqli_stmt_execute($stmt);
    $dr_res=mysqli_stmt_get_result($stmt);
    while($row=mysqli_fetch_assoc($dr_res)){
        $income_daily_data[$row['item_id']] = $row;
        if($row['gold_rate'] > 0) $daily_rate = $row['gold_rate'];
    }
}

$income_monthly_items = []; $income_monthly_data = [];
$mi_res = mysqli_query($conn, "SELECT * FROM income_monthly_items WHERE active=1 ORDER BY category, sort_order");
while($r=mysqli_fetch_assoc($mi_res)) $income_monthly_items[] = $r;

$stmt = mysqli_prepare($conn, "SELECT item_id, amount_gram FROM income_monthly_records WHERE branch_id=? AND record_year=? AND record_month=?");
if($stmt){
    mysqli_stmt_bind_param($stmt,"iii",$branch_id,$sel_year,$sel_month); mysqli_stmt_execute($stmt);
    $mr_res=mysqli_stmt_get_result($stmt);
    while($row=mysqli_fetch_assoc($mr_res)) $income_monthly_data[$row['item_id']] = $row;
}

// ============ Messages & Online ============
$messages = []; $last_msg_id = 0;
if ($report_id > 0) {
    $stmt = mysqli_prepare($conn, "SELECT c.*, u.username, u.branch_name FROM comments c JOIN users u ON c.user_id = u.id WHERE c.report_id = ? ORDER BY c.created_at ASC");
    mysqli_stmt_bind_param($stmt, "i", $report_id); mysqli_stmt_execute($stmt);
    $mres = mysqli_stmt_get_result($stmt);
    while ($m = mysqli_fetch_assoc($mres)) { $messages[] = $m; $last_msg_id = $m['id']; }
    $mark_read_stmt = mysqli_prepare($conn, "UPDATE comments SET is_read_by_branch = 1 WHERE report_id = ? AND sender_role = 'observer'");
    if($mark_read_stmt){ mysqli_stmt_bind_param($mark_read_stmt, "i", $report_id); mysqli_stmt_execute($mark_read_stmt); }
}
$online_status = 'offline'; $last_activity_text = '';
if (!$is_branch) {
    $stmt = mysqli_prepare($conn, "SELECT last_activity FROM users WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $branch_id); mysqli_stmt_execute($stmt);
    $online_row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    if ($online_row && $online_row['last_activity']) {
        $diff = floor((time() - strtotime($online_row['last_activity'])) / 60);
        $online_status = ($diff < 5) ? 'online' : 'offline';
        $last_activity_text = date('H:i', strtotime($online_row['last_activity']));
    }
}

function fmt_num($v) { 
    $v = (float)$v;
    if ($v == round($v)) {
        return number_format($v, 0);
    } else {
        $formatted = number_format($v, 3);
        $formatted = rtrim($formatted, '0');
        $formatted = rtrim($formatted, '.');
        return $formatted;
    }
}

function fmt_gram($v) { 
    $v = (float)$v;
    if ($v <= 0) return '-';
    if ($v == round($v)) {
        return number_format($v, 0);
    } else {
        $formatted = number_format($v, 3);
        $formatted = rtrim($formatted, '0');
        $formatted = rtrim($formatted, '.');
        return $formatted;
    }
}

function safe_float($val) {
    if ($val === null || $val === '' || !is_numeric($val)) return 0.0;
    return (float) $val;
}


?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>پنل مشاهده | <?php echo htmlspecialchars($branch_name); ?></title>
<link href="assets/fonts/fonts.css" rel="stylesheet">
<style>
/* ======================================================
   طراحی رسمی و حرفه‌ای – بدون ایموجی، با آیکون‌های SVG
   ====================================================== */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

:root {
    --bg-page: #f2f5f9;
    --bg-surface: #ffffff;
    --bg-hover: #f0f3f8;
    --border: #dce2ec;
    --border-strong: #c8d0de;
    --text-primary: #1a2538;
    --text-secondary: #4a5a72;
    --text-muted: #8895aa;
    --shadow-sm: 0 1px 3px rgba(0,0,0,0.04);
    --shadow-md: 0 4px 14px rgba(0,0,0,0.06);
    --shadow-lg: 0 10px 32px rgba(0,0,0,0.07);
    --radius: 14px;
    --radius-sm: 8px;
    --primary: #1a4972;
    --primary-light: #e7eef7;
    --primary-dark: #0d2a44;
    --green: #0e7a52;
    --green-light: #e6f4ee;
    --red: #b11f3d;
    --red-light: #fce8ed;
    --amber: #a85c0a;
    --amber-light: #fcf0e3;
    --purple: #5e3d8a;
    --purple-light: #efeaf5;
    --blue: #1a6d94;
    --blue-light: #e4eef7;
    --font: 'Vazirmatn', system-ui, -apple-system, sans-serif;
    --transition: all 0.2s ease;
}

body.dark {
    --bg-page: #0e1629;
    --bg-surface: #1a2538;
    --bg-hover: #26324a;
    --border: #2d3a52;
    --border-strong: #3d4d69;
    --text-primary: #e8edf5;
    --text-secondary: #b0c0d8;
    --text-muted: #7a8aa5;
    --shadow-sm: 0 1px 3px rgba(0,0,0,0.3);
    --shadow-md: 0 4px 14px rgba(0,0,0,0.4);
    --shadow-lg: 0 10px 32px rgba(0,0,0,0.5);
    --primary: #5d8dc4;
    --primary-light: #1e2f44;
    --green: #3acf99;
    --green-light: #173528;
    --red: #f0748a;
    --red-light: #3a1f28;
    --amber: #f5b84a;
    --amber-light: #3a2f1a;
    --purple: #a78bd9;
    --purple-light: #2a2140;
    --blue: #5da8d9;
    --blue-light: #1a2a40;
}

body {
    font-family: var(--font);
    background-color: var(--bg-page);
    color: var(--text-primary);
    line-height: 1.6;
    min-height: 100vh;
    padding: 20px 16px;
    transition: background-color 0.3s, color 0.3s;
}

.container {
    max-width: 1140px;
    margin: 0 auto;
    display: flex;
    flex-direction: column;
    gap: 20px;
}

/* ----- کارت‌ها ----- */
.card {
    background: var(--bg-surface);
    border-radius: var(--radius);
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--border);
    transition: var(--transition);
}
.card:hover {
    box-shadow: var(--shadow-md);
}
.card-header {
    padding: 16px 20px;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
    background: var(--bg-hover);
    border-radius: var(--radius) var(--radius) 0 0;
}
.card-body {
    padding: 18px 20px;
}
.card-title {
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 10px;
}
.card-title .icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    flex-shrink: 0;
}

/* ----- آیکون‌های SVG داخلی ----- */
.icon-svg {
    display: inline-block;
    width: 20px;
    height: 20px;
    stroke: currentColor;
    stroke-width: 2;
    fill: none;
    stroke-linecap: round;
    stroke-linejoin: round;
}
.icon-svg.filled {
    fill: currentColor;
    stroke: none;
}

/* ----- هدر اصلی ----- */
.header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 14px;
    padding: 14px 20px;
}
.header-left {
    display: flex;
    align-items: center;
    gap: 14px;
}
.branch-logo {
    width: 42px;
    height: 42px;
    border-radius: var(--radius-sm);
    object-fit: contain;
    background: var(--bg-hover);
    padding: 5px;
    border: 1px solid var(--border);
}
.branch-info h1 {
    font-size: 1.3rem;
    font-weight: 800;
    letter-spacing: -0.3px;
}
.branch-info .sub {
    font-size: 0.78rem;
    color: var(--text-secondary);
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 2px;
}
.online-dot {
    display: inline-block;
    width: 9px;
    height: 9px;
    border-radius: 50%;
    background: var(--green);
}
.online-dot.offline {
    background: var(--red);
}
.badge-role {
    font-size: 0.68rem;
    font-weight: 700;
    padding: 4px 14px;
    border-radius: 20px;
    background: var(--primary-light);
    color: var(--primary);
    border: none;
}
.header-actions {
    display: flex;
    align-items: center;
    gap: 6px;
}
.header-actions button,
.header-actions a {
    width: 38px;
    height: 38px;
    border-radius: var(--radius-sm);
    border: 1px solid var(--border);
    background: var(--bg-surface);
    color: var(--text-secondary);
    font-size: 1rem;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: var(--transition);
    text-decoration: none;
}
.header-actions button:hover,
.header-actions a:hover {
    background: var(--bg-hover);
    border-color: var(--border-strong);
    color: var(--text-primary);
}

/* ----- نوار مشاهده‌گر ----- */
.readonly-bar {
    background: var(--amber-light);
    color: var(--amber);
    padding: 10px 18px;
    border-radius: var(--radius-sm);
    font-weight: 700;
    font-size: 0.82rem;
    display: flex;
    align-items: center;
    gap: 10px;
    border: 1px solid rgba(168, 92, 10, 0.12);
}

/* ----- تب‌ها ----- */
.tab-nav {
    display: flex;
    gap: 4px;
    background: var(--bg-surface);
    padding: 5px;
    border-radius: var(--radius);
    border: 1px solid var(--border);
    box-shadow: var(--shadow-sm);
}
.tab-btn {
    flex: 1;
    padding: 9px 14px;
    border: none;
    background: transparent;
    color: var(--text-secondary);
    font-weight: 700;
    font-family: inherit;
    font-size: 0.85rem;
    border-radius: var(--radius-sm);
    cursor: pointer;
    transition: var(--transition);
}
.tab-btn:hover {
    background: var(--bg-hover);
    color: var(--text-primary);
}
.tab-btn.active {
    background: var(--primary);
    color: #fff;
    box-shadow: 0 3px 10px rgba(26, 73, 114, 0.2);
}
body.dark .tab-btn.active {
    box-shadow: 0 3px 10px rgba(93, 141, 196, 0.2);
}
.tab-content {
    display: none;
    animation: fadeUp 0.3s ease;
}
.tab-content.active {
    display: block;
}
@keyframes fadeUp {
    from { opacity: 0; transform: translateY(6px); }
    to { opacity: 1; transform: translateY(0); }
}

/* ----- تاریخ ----- */
.date-badge {
    background: var(--bg-surface);
    padding: 10px 18px;
    border-radius: var(--radius-sm);
    border: 1px solid var(--border);
    font-weight: 700;
    font-size: 0.9rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    box-shadow: var(--shadow-sm);
}
.date-badge .rate {
    font-weight: 400;
    font-size: 0.82rem;
    color: var(--text-secondary);
}

/* ----- آمار سریع ----- */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 10px;
    margin-bottom: 6px;
}
.stat-item {
    background: var(--bg-surface);
    border-radius: var(--radius-sm);
    padding: 14px 10px;
    text-align: center;
    border: 1px solid var(--border);
    box-shadow: var(--shadow-sm);
    transition: var(--transition);
}
.stat-item:hover {
    box-shadow: var(--shadow-md);
}
.stat-item .label {
    font-size: 0.68rem;
    font-weight: 600;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.3px;
}
.stat-item .value {
    font-size: 1.5rem;
    font-weight: 800;
    letter-spacing: -0.5px;
    margin-top: 2px;
}
.stat-item.debtors .value { color: var(--green); }
.stat-item.creditors .value { color: var(--red); }
.stat-item.diff .value { color: var(--primary); }
.stat-item.petty .value { color: var(--purple); }

/* ----- جداول ----- */
.table-wrap {
    overflow-x: auto;
    margin-top: 2px;
}
table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.82rem;
}
table th {
    background: var(--bg-hover);
    color: var(--text-secondary);
    font-weight: 700;
    font-size: 0.68rem;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    padding: 10px 8px;
    text-align: center;
    border-bottom: 2px solid var(--border-strong);
}
table td {
    padding: 10px 8px;
    text-align: center;
    border-bottom: 1px solid var(--border);
    color: var(--text-primary);
}
table tr:last-child td {
    border-bottom: none;
}
table tr:hover td {
    background: var(--bg-hover);
}
table .sum-row td {
    background: var(--bg-hover);
    font-weight: 700;
    border-top: 2px solid var(--border-strong);
    border-bottom: none;
}
table .sum-row:hover td {
    background: var(--bg-hover);
}
.t-debtor { color: var(--green); font-weight: 600; }
.t-creditor { color: var(--red); font-weight: 600; }
.t-positive { color: var(--green); }
.t-negative { color: var(--red); }

/* ----- آیتم‌های درآمد ----- */
.income-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid var(--border);
}
.income-item:last-child {
    border-bottom: none;
}
.income-item .name {
    font-weight: 600;
    color: var(--text-primary);
}
.income-item .values {
    text-align: left;
}
.income-item .rial {
    font-weight: 700;
    color: var(--green);
}
.income-item .gram {
    font-size: 0.73rem;
    color: var(--text-muted);
}
.income-cat {
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.3px;
    padding: 10px 0 4px;
    border-bottom: 1px dashed var(--border);
}
.income-total {
    margin-top: 6px;
    padding-top: 10px;
    border-top: 2px solid var(--border-strong);
    font-weight: 700;
}
.income-total .rial {
    color: var(--primary);
    font-size: 0.95rem;
}

/* ----- چت ----- */
.chat-wrap {
    max-height: 280px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 2px 0;
}
.chat-wrap::-webkit-scrollbar {
    width: 5px;
}
.chat-wrap::-webkit-scrollbar-thumb {
    background: var(--border-strong);
    border-radius: 4px;
}
.msg {
    max-width: 82%;
    padding: 10px 14px;
    border-radius: var(--radius-sm);
    font-size: 0.82rem;
    line-height: 1.5;
    box-shadow: var(--shadow-sm);
}
.msg.sent {
    align-self: flex-end;
    background: var(--primary);
    color: #fff;
    border-bottom-right-radius: 3px;
}
.msg.recv {
    align-self: flex-start;
    background: var(--bg-hover);
    border: 1px solid var(--border);
    border-bottom-left-radius: 3px;
}
.msg strong {
    display: block;
    font-size: 0.72rem;
    margin-bottom: 3px;
    opacity: 0.8;
}
.msg.sent strong {
    color: rgba(255,255,255,0.8);
}
.msg .time {
    font-size: 0.62rem;
    margin-top: 5px;
    opacity: 0.6;
    text-align: left;
}
.chat-input {
    display: flex;
    gap: 8px;
    margin-top: 14px;
}
.chat-input textarea {
    flex: 1;
    padding: 10px 14px;
    border-radius: var(--radius-sm);
    border: 1px solid var(--border);
    background: var(--bg-page);
    color: var(--text-primary);
    font-family: inherit;
    font-size: 0.82rem;
    resize: none;
    outline: none;
    transition: var(--transition);
}
.chat-input textarea:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px var(--primary-light);
}
.chat-input button {
    padding: 0 22px;
    border: none;
    border-radius: var(--radius-sm);
    background: var(--primary);
    color: #fff;
    font-weight: 700;
    font-family: inherit;
    font-size: 0.85rem;
    cursor: pointer;
    transition: var(--transition);
}
.chat-input button:hover {
    filter: brightness(1.05);
    box-shadow: 0 3px 10px rgba(26, 73, 114, 0.2);
}

/* ----- توست ----- */
.toast-container {
    position: fixed;
    bottom: 28px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 9999;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
}
.toast {
    background: var(--bg-surface);
    color: var(--text-primary);
    padding: 10px 26px;
    border-radius: var(--radius-sm);
    font-weight: 600;
    font-size: 0.82rem;
    box-shadow: var(--shadow-lg);
    border: 1px solid var(--border);
    animation: slideUp 0.3s ease;
}
.toast.success { border-right: 4px solid var(--green); }
.toast.error { border-right: 4px solid var(--red); }
@keyframes slideUp {
    from { opacity: 0; transform: translateY(16px) scale(0.97); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}

/* ======================================================
   آیکون‌های سفارشی با SVG (بدون ایموجی)
   ====================================================== */
.icon-close {
    width: 18px;
    height: 18px;
    stroke: currentColor;
    stroke-width: 2;
    stroke-linecap: round;
    stroke-linejoin: round;
    fill: none;
}
.icon-close line {
    x1: 4;
    y1: 4;
    x2: 14;
    y2: 14;
}
.icon-close line:nth-child(2) {
    x1: 14;
    y1: 4;
    x2: 4;
    y2: 14;
}

.icon-moon {
    width: 18px;
    height: 18px;
    stroke: currentColor;
    stroke-width: 2;
    stroke-linecap: round;
    stroke-linejoin: round;
    fill: none;
}
.icon-moon path {
    d: "M12 3a6 6 0 0 0 9 9 6 6 0 1 1-9-9z";
}

.icon-sun {
    width: 18px;
    height: 18px;
    stroke: currentColor;
    stroke-width: 2;
    stroke-linecap: round;
    stroke-linejoin: round;
    fill: none;
}
.icon-sun circle { cx:12; cy:12; r:5; }
.icon-sun line { stroke:currentColor; stroke-width:2; stroke-linecap:round; }
.icon-sun line:nth-child(2) { x1:12; y1:1; x2:12; y2:3; }
.icon-sun line:nth-child(3) { x1:12; y1:21; x2:12; y2:23; }
.icon-sun line:nth-child(4) { x1:4.22; y1:4.22; x2:5.64; y2:5.64; }
.icon-sun line:nth-child(5) { x1:18.36; y1:18.36; x2:19.78; y2:19.78; }
.icon-sun line:nth-child(6) { x1:1; y1:12; x2:3; y2:12; }
.icon-sun line:nth-child(7) { x1:21; y1:12; x2:23; y2:12; }
.icon-sun line:nth-child(8) { x1:4.22; y1:19.78; x2:5.64; y2:18.36; }
.icon-sun line:nth-child(9) { x1:18.36; y1:5.64; x2:19.78; y2:4.22; }

.icon-eye {
    width: 18px;
    height: 18px;
    stroke: currentColor;
    stroke-width: 2;
    stroke-linecap: round;
    stroke-linejoin: round;
    fill: none;
}
.icon-eye path { d:"M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"; }
.icon-eye circle { cx:12; cy:12; r:3; }

.icon-list {
    width: 18px;
    height: 18px;
    stroke: currentColor;
    stroke-width: 2;
    stroke-linecap: round;
    stroke-linejoin: round;
    fill: none;
}
.icon-list line { stroke:currentColor; stroke-width:2; stroke-linecap:round; }
.icon-list line:nth-child(1) { x1:8; y1:6; x2:21; y2:6; }
.icon-list line:nth-child(2) { x1:8; y1:12; x2:21; y2:12; }
.icon-list line:nth-child(3) { x1:8; y1:18; x2:21; y2:18; }
.icon-list circle { cx:4; cy:6; r:1.5; }
.icon-list circle:nth-child(5) { cx:4; cy:12; r:1.5; }
.icon-list circle:nth-child(6) { cx:4; cy:18; r:1.5; }

.icon-money {
    width: 18px;
    height: 18px;
    stroke: currentColor;
    stroke-width: 2;
    stroke-linecap: round;
    stroke-linejoin: round;
    fill: none;
}
.icon-money circle { cx:12; cy:12; r:10; }
.icon-money line { x1:12; y1:8; x2:12; y2:16; }
.icon-money line:nth-child(3) { x1:8; y1:12; x2:16; y2:12; }

.icon-grid {
    width: 18px;
    height: 18px;
    stroke: currentColor;
    stroke-width: 2;
    stroke-linecap: round;
    stroke-linejoin: round;
    fill: none;
}
.icon-grid rect { x:3; y:3; width:7; height:7; rx:1; }
.icon-grid rect:nth-child(2) { x:14; y:3; width:7; height:7; rx:1; }
.icon-grid rect:nth-child(3) { x:3; y:14; width:7; height:7; rx:1; }
.icon-grid rect:nth-child(4) { x:14; y:14; width:7; height:7; rx:1; }

.icon-chart {
    width: 18px;
    height: 18px;
    stroke: currentColor;
    stroke-width: 2;
    stroke-linecap: round;
    stroke-linejoin: round;
    fill: none;
}
.icon-chart line { x1:18; y1:20; x2:2; y2:20; }
.icon-chart polyline { points:"2 18 7 12 12 15 17 6 22 9"; }

.icon-chat {
    width: 18px;
    height: 18px;
    stroke: currentColor;
    stroke-width: 2;
    stroke-linecap: round;
    stroke-linejoin: round;
    fill: none;
}
.icon-chat path { d:"M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"; }

.icon-calendar {
    width: 18px;
    height: 18px;
    stroke: currentColor;
    stroke-width: 2;
    stroke-linecap: round;
    stroke-linejoin: round;
    fill: none;
}
.icon-calendar rect { x:3; y:4; width:18; height:18; rx:2; ry:2; }
.icon-calendar line { x1:16; y1:2; x2:16; y2:6; }
.icon-calendar line:nth-child(3) { x1:8; y1:2; x2:8; y2:6; }
.icon-calendar line:nth-child(4) { x1:3; y1:10; x2:21; y2:10; }

.icon-arrow-left {
    width: 18px;
    height: 18px;
    stroke: currentColor;
    stroke-width: 2;
    stroke-linecap: round;
    stroke-linejoin: round;
    fill: none;
}
.icon-arrow-left line { x1:19; y1:12; x2:5; y2:12; }
.icon-arrow-left polyline { points:"12 19 5 12 12 5"; }

.icon-arrow-right {
    width: 18px;
    height: 18px;
    stroke: currentColor;
    stroke-width: 2;
    stroke-linecap: round;
    stroke-linejoin: round;
    fill: none;
}
.icon-arrow-right line { x1:5; y1:12; x2:19; y2:12; }
.icon-arrow-right polyline { points:"12 5 19 12 12 19"; }

.icon-user {
    width: 18px;
    height: 18px;
    stroke: currentColor;
    stroke-width: 2;
    stroke-linecap: round;
    stroke-linejoin: round;
    fill: none;
}
.icon-user path { d:"M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"; }
.icon-user circle { cx:12; cy:7; r:4; }

.icon-users {
    width: 18px;
    height: 18px;
    stroke: currentColor;
    stroke-width: 2;
    stroke-linecap: round;
    stroke-linejoin: round;
    fill: none;
}
.icon-users path { d:"M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"; }
.icon-users circle { cx:9; cy:7; r:4; }
.icon-users path:nth-child(3) { d:"M23 21v-2a4 4 0 0 0-3-3.87"; }
.icon-users path:nth-child(4) { d:"M16 3.13a4 4 0 0 1 0 7.75"; }

.icon-wallet {
    width: 18px;
    height: 18px;
    stroke: currentColor;
    stroke-width: 2;
    stroke-linecap: round;
    stroke-linejoin: round;
    fill: none;
}
.icon-wallet path { d:"M21 12V7H5a2 2 0 0 1-2-2 2 2 0 0 1 2-2h14v5"; }
.icon-wallet path:nth-child(2) { d:"M3 19V5a2 2 0 0 0 2 2h16v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"; }
.icon-wallet circle { cx:18; cy:15; r:1.5; }

/* ----- واکنش‌گرایی ----- */
@media (max-width: 700px) {
    body { padding: 14px 10px; }
    .container { gap: 14px; }
    .header { padding: 12px 14px; }
    .branch-info h1 { font-size: 1rem; }
    .tab-btn { font-size: 0.78rem; padding: 7px 10px; }
    .card-header { padding: 12px 14px; }
    .card-body { padding: 12px 14px; }
    .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 8px; }
    .stat-item .value { font-size: 1.3rem; }
    .stat-item { padding: 10px 6px; }
    .msg { max-width: 95%; }
    .header-actions button,
    .header-actions a { width: 34px; height: 34px; font-size: 0.9rem; }
    .date-badge { font-size: 0.82rem; padding: 8px 14px; }
    table { font-size: 1.3rem; }
    table th, table td { padding: 7px 5px; }
}
@media (max-width: 480px) {
    .stats-grid { grid-template-columns: 1fr 1fr; gap: 6px; }
    .stat-item .value { font-size: 1.05rem; }
}
</style>
</head>
<body>
<div class="container">
    <div class="toast-container" id="toastContainer"></div>

    <!-- HEADER -->
    <div class="header card">
        <div class="header-left">
            <img src="assets/images/logo.png" alt="لوگو" class="branch-logo">
            <div class="branch-info">
                <h1><?php echo htmlspecialchars($branch_name); ?></h1>
                <?php if (!$is_branch): ?>
                <div class="sub" id="onlineStatusArea">
                    <span class="online-dot <?php echo $online_status === 'online' ? '' : 'offline'; ?>"></span>
                    <?php echo $online_status === 'online' ? 'آنلاین' : 'آفلاین'; ?>
                    <?php if ($last_activity_text): ?> <span>(<?php echo $last_activity_text; ?>)</span> <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
            <span class="badge-role"><?php echo $is_branch ? 'کاربر' : ($is_observer ? 'ناظر' : 'مدیر'); ?></span>
            <div class="header-actions">
                <button onclick="toggleDarkMode()" title="تغییر حالت روز/شب" id="themeToggle">
                    <svg class="icon-moon" viewBox="0 0 24 24"><path d="M12 3a6 6 0 0 0 9 9 6 6 0 1 1-9-9z"/></svg>
                </button>
                <a href="index.php" title="خروج">
                    <svg class="icon-close" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </a>
            </div>
        </div>
    </div>

    <?php if ($is_readonly): ?>
    <div class="readonly-bar">
        <svg class="icon-eye" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        حالت مشاهده‌گر (Live)
    </div>
    <?php endif; ?>

    <!-- TABS -->
    <div class="tab-nav">
        <button class="tab-btn <?php echo $tab === 'balance' ? 'active' : ''; ?>" onclick="switchTab('balance')">
            <svg class="icon-list" viewBox="0 0 24 24" style="width:16px;height:16px;vertical-align:middle;display:inline-block;margin-left:4px;">
                <line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/>
                <circle cx="4" cy="6" r="1.5"/><circle cx="4" cy="12" r="1.5"/><circle cx="4" cy="18" r="1.5"/>
            </svg>
            تراز روزانه
        </button>
        <button class="tab-btn <?php echo $tab === 'income' ? 'active' : ''; ?>" onclick="switchTab('income')">
            <svg class="icon-chart" viewBox="0 0 24 24" style="width:16px;height:16px;vertical-align:middle;display:inline-block;margin-left:4px;">
                <line x1="18" y1="20" x2="2" y2="20"/><polyline points="2 18 7 12 12 15 17 6 22 9"/>
            </svg>
            درآمد
        </button>
    </div>

    <!-- ============ TAB: BALANCE ============ -->
    <div id="tab-balance" class="tab-content <?php echo $tab === 'balance' ? 'active' : ''; ?>">
        <div class="date-badge">
            <span>
                <svg class="icon-calendar" viewBox="0 0 24 24" style="width:16px;height:16px;vertical-align:middle;display:inline-block;margin-left:6px;">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                </svg>
                <?php echo htmlspecialchars($selected_date_ui); ?>
            </span>
            <span class="rate">نرخ طلا: <span id="poll-rate-display"><?php echo $daily_rate ? number_format($daily_rate) : '-'; ?></span></span>
        </div>

        <?php if (!empty($report_data)): ?>
<!-- دو باکس هم اندازه در یک ردیف -->
<div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:6px;">
    
    <!-- باکس اول: بدهکاران + بستانکاران + اختلاف (یکجا) -->
    <div class="stat-item" style="padding:12px 10px; border:1px solid var(--border); border-radius:var(--radius-sm); background:var(--bg-surface); box-shadow:var(--shadow-sm);">
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:4px;">
            <!-- بدهکاران -->
            <div style="text-align:center; border-left:1px solid var(--border); padding:4px 8px;">
                <div class="label" style="font-size:0.65rem; font-weight:600; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.3px;">بدهکاران</div>
                <div class="value" id="valDebtors" style="font-size:1.3rem; font-weight:800; color:var(--green); letter-spacing:-0.5px;"><?php echo fmt_num($totalDebtors); ?></div>
            </div>
            <!-- بستانکاران -->
            <div style="text-align:center; padding:4px 8px;">
                <div class="label" style="font-size:0.65rem; font-weight:600; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.3px;">بستانکاران</div>
                <div class="value" id="valCreditors" style="font-size:1.3rem; font-weight:800; color:var(--red); letter-spacing:-0.5px;"><?php echo fmt_num($totalCreditors); ?></div>
            </div>
        </div>
        <!-- اختلاف (در زیر) -->
        <?php $diff = $totalDebtors - $totalCreditors; ?>
        <div style="text-align:center; border-top:1px dashed var(--border); padding-top:6px; margin-top:4px;">
            <span style="font-size:0.65rem; font-weight:600; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.3px;">اختلاف</span>
            <div style="font-size:1.3rem; font-weight:800; color:<?php echo $diff > 0 ? 'var(--green)' : ($diff < 0 ? 'var(--red)' : 'var(--text-muted)'); ?>;">
                <?php echo fmt_num($diff); ?>
            </div>
        </div>
    </div>

    <!-- باکس دوم: تنخواه -->
    <div class="stat-item petty" style="padding:12px 10px; border:1px solid var(--border); border-radius:var(--radius-sm); background:var(--bg-surface); box-shadow:var(--shadow-sm);">
        <div class="label" style="font-size:0.65rem; font-weight:600; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.3px; text-align:center;">تنخواه</div>
        
        <?php if ($petty_ceiling > 0): 
            $difference = $totalPetty - $petty_ceiling;
        ?>
        <div style="margin-top:4px;">
            <div style="display:flex;justify-content:space-between;font-size:0.8rem;padding:2px 0;">
                <span style="color:var(--text-muted);">سقف تنخواه</span>
                <span style="font-weight:600;"><?php echo fmt_num($petty_ceiling); ?></span>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:0.8rem;padding:2px 0;border-bottom:1px dashed var(--border);padding-bottom:4px;margin-bottom:4px;">
                <span style="color:var(--text-muted);">جمع تنخواه</span>
                <span style="font-weight:600;"><?php echo fmt_num($totalPetty); ?></span>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:1.3rem;padding-top:4px;">
                <span style="font-weight:700;color:<?php echo $difference > 0 ? 'var(--green)' : ($difference < 0 ? 'var(--red)' : 'var(--text-muted)'); ?>;">
                    <?php 
                    if ($difference > 0) echo 'فزونی (مازاد)';
                    elseif ($difference < 0) echo 'کسری (کمبود)';
                    else echo 'متوازن';
                    ?>
                </span>
                <span style="font-weight:700;color:<?php echo $difference > 0 ? 'var(--green)' : ($difference < 0 ? 'var(--red)' : 'var(--text-muted)'); ?>;">
                    <?php echo $difference != 0 ? fmt_num(abs($difference)) : '۰'; ?>
                </span>
            </div>
        </div>
        <?php else: ?>
        <div style="font-size:0.65rem;color:var(--text-muted);text-align:center;margin-top:6px;border-top:1px dashed var(--border);padding-top:6px;">
            سقف تنخواه تعیین نشده
        </div>
        <?php endif; ?>
    </div>
</div>
<?php else: ?>
<div class="card" style="padding:36px; text-align:center; color:var(--text-secondary);">
    <div style="font-size:2.2rem; display:block; margin-bottom:10px; opacity:0.4;">
        <svg class="icon-list" viewBox="0 0 24 24" style="width:48px;height:48px;opacity:0.3;display:block;margin:0 auto;">
            <line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/>
            <circle cx="4" cy="6" r="1.5"/><circle cx="4" cy="12" r="1.5"/><circle cx="4" cy="18" r="1.5"/>
        </svg>
    </div>
    گزارشی برای این تاریخ ثبت نشده است.
</div>
<?php endif; ?>

        <!-- جداول اصلی -->
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
            <?php if(!empty($debtors)): ?>
            <div class="card">
                <div class="card-header">
                    <span class="card-title">
                        <span class="icon">
                            <svg class="icon-users" viewBox="0 0 24 24" style="width:20px;height:20px;">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                            </svg>
                        </span>
                        بدهکاران
                    </span>
                </div>
                <div class="card-body table-wrap">
                    <table><thead><tr><th>نام</th><th>مبلغ</th></tr></thead><tbody>
                    <?php foreach($debtors as $d): ?>
                    <tr><td><?php echo htmlspecialchars($d['name']); ?></td><td class="t-debtor"><?php echo fmt_num($d['amt']); ?></td></tr>
                    <?php endforeach; ?>
                    <tr class="sum-row"><td>جمع</td><td class="t-debtor"><?php echo fmt_num($totalDebtors); ?></td></tr>
                    </tbody></table>
                </div>
            </div>
            <?php endif; ?>
            <?php if(!empty($creditors)): ?>
            <div class="card">
                <div class="card-header">
                    <span class="card-title">
                        <span class="icon">
                            <svg class="icon-users" viewBox="0 0 24 24" style="width:20px;height:20px;">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                            </svg>
                        </span>
                        بستانکاران
                    </span>
                </div>
                <div class="card-body table-wrap">
                    <table><thead><tr><th>نام</th><th>مبلغ</th></tr></thead><tbody>
                    <?php foreach($creditors as $c): ?>
                    <tr><td><?php echo htmlspecialchars($c['name']); ?></td><td class="t-creditor"><?php echo fmt_num($c['amt']); ?></td></tr>
                    <?php endforeach; ?>
                    <tr class="sum-row"><td>جمع</td><td class="t-creditor"><?php echo fmt_num($totalCreditors); ?></td></tr>
                    </tbody></table>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- تنخواه و بنکداران -->
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-top:16px;">
            <?php if(!empty($pettys)): ?>
            <div class="card">
                <div class="card-header">
                    <span class="card-title">
                        <span class="icon">
                            <svg class="icon-wallet" viewBox="0 0 24 24" style="width:20px;height:20px;">
                                <path d="M21 12V7H5a2 2 0 0 1-2-2 2 2 0 0 1 2-2h14v5"/><path d="M3 19V5a2 2 0 0 0 2 2h16v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><circle cx="18" cy="15" r="1.5"/>
                            </svg>
                        </span>
                        تنخواه
                    </span>
                </div>
                <div class="card-body table-wrap">
                    <table><thead><tr><th>شرح</th><th>مبلغ</th></tr></thead><tbody>
                    <?php foreach($pettys as $p): ?>
                    <tr><td><?php echo htmlspecialchars($p['desc']); ?></td><td><?php echo fmt_num($p['amt']); ?></td></tr>
                    <?php endforeach; ?>
                    <tr class="sum-row"><td>جمع</td><td><?php echo fmt_num($totalPetty); ?></td></tr>
                    </tbody></table>
                </div>
            </div>
            <?php endif; ?>
            <?php if(!empty($bankers)): ?>
            <div class="card">
                <div class="card-header">
                    <span class="card-title">
                        <span class="icon">
                            <svg class="icon-user" viewBox="0 0 24 24" style="width:20px;height:20px;">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                            </svg>
                        </span>
                        بنکداران
                    </span>
                </div>
                <div class="card-body table-wrap">
                    <table><thead><tr><th>نام</th><th>وزن</th></tr></thead><tbody>
                    <?php foreach($bankers as $b): if($b['name']): ?>
                    <tr><td><?php echo htmlspecialchars($b['name']); ?></td><td><?php echo fmt_num($b['amt']); ?></td></tr>
                    <?php endif; endforeach; ?>
                    <tr class="sum-row"><td>جمع</td><td><?php echo fmt_num($totalBankers); ?></td></tr>
                    </tbody></table>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- آیتم‌های داینامیک -->
        <?php if (!empty($dyn_items_list)): 
            $has_dyn = false;
            foreach ($dyn_items_list as $di) { if (isset($dyn_values[$di['id']]) && $dyn_values[$di['id']] > 0) $has_dyn = true; }
            if ($has_dyn):
        ?>
        <div class="card" style="margin-top:16px;">
            <div class="card-header">
                <span class="card-title">
                    <span class="icon">
                        <svg class="icon-grid" viewBox="0 0 24 24" style="width:20px;height:20px;">
                            <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
                            <rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>
                        </svg>
                    </span>
                    آیتم‌های افزوده (طلا)
                </span>
            </div>
            <div class="card-body table-wrap">
                <table><thead><tr><th>عنوان</th><th>وزن (گرم)</th></tr></thead><tbody>
                <?php foreach ($dyn_items_list as $di): 
                    $val = $dyn_values[$di['id']] ?? 0;
                    if ($val > 0): 
                ?>
                <tr><td><?php echo htmlspecialchars($di['name']); ?></td><td><?php echo fmt_gram($val); ?></td></tr>
                <?php endif; endforeach; ?>
                </tbody></table>
            </div>
        </div>
        <?php endif; endif; ?>

        <!-- ماتریس -->
<?php
$hasRealMatrixData = false;
if (!empty($debtors) && !empty($creditors) && !empty($matrixValues)) {
    foreach($matrixValues as $v) {
        if (is_numeric($v) && $v != 0) {
            $hasRealMatrixData = true;
            break;
        }
    }
}
if ($hasRealMatrixData): 
    $debtorColSums = array_fill(0, count($debtors), 0);
?>
<div class="card" style="margin-top:16px;">
    <div class="card-header">
        <span class="card-title">
            <span class="icon">
                <svg class="icon-grid" viewBox="0 0 24 24" style="width:20px;height:20px;">
                    <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
                    <rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>
                </svg>
            </span>
            ماتریس جابجایی
        </span>
    </div>
    <div class="card-body table-wrap">
        <table style="min-width:600px; font-size:0.85rem;">
            <thead>
                <tr>
                    <th style="min-width:120px;">بستانکار \ بدهکار</th>
                    <?php foreach($debtors as $d): ?>
                        <th style="min-width:80px;">↓ <?php echo htmlspecialchars($d['name']); ?></th>
                    <?php endforeach; ?>
                    <th style="min-width:90px;">مانده بستانکار</th>
                </tr>
                <tr>
                    <th style="font-weight:400; color:var(--text-muted); font-size:0.7rem;">مبلغ بدهکار</th>
                    <?php foreach($debtors as $d): ?>
                        <th style="font-weight:400; color:var(--text-muted); font-size:0.7rem;"><?php echo fmt_num($d['amt']); ?></th>
                    <?php endforeach; ?>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($creditors as $c): 
                $rowSum = 0; 
            ?>
            <tr>
                <td style="font-weight:600; text-align:right; padding-left:8px;">
                    ↑ <?php echo htmlspecialchars($c['name']); ?>
                    <br><small style="color:var(--text-muted);"><?php echo fmt_num($c['amt']); ?></small>
                </td>
                <?php foreach($debtors as $d_idx => $d): 
                    $key = $c['id'].'_'.$d['id']; 
                    $val = safe_float($matrixValues[$key] ?? 0); 
                    $rowSum += $val; 
                    $debtorColSums[$d_idx] += $val; 
                ?>
                <td style="text-align:center;">
                    <?php echo ($val != 0) ? fmt_num($val) : '-'; ?>
                </td>
                <?php endforeach; ?>
                <td style="font-weight:700; color:<?php echo ($c['amt'] - $rowSum) != 0 ? 'var(--red)' : 'var(--text-muted)'; ?>; text-align:center;">
                    <?php echo fmt_num($c['amt'] - $rowSum); ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="sum-row">
                    <td style="font-weight:700; text-align:right;">↓ مانده بدهکار</td>
                    <?php foreach($debtors as $d_idx => $d): 
                        $d_amt = safe_float($d['amt']);
                        $debtorColSum = safe_float($debtorColSums[$d_idx]);
                        $diff = $d_amt - $debtorColSum;
                    ?>
                    <td style="text-align:center; font-weight:700; color:<?php echo $diff != 0 ? 'var(--green)' : 'var(--text-muted)'; ?>;">
                        <?php echo fmt_num($diff); ?>
                    </td>
                    <?php endforeach; ?>
                    <td style="text-align:center;">!</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
<?php endif; ?>

                <!-- چت -->
        <div class="card" style="margin-top:16px;">
            <div class="card-header">
                <span class="card-title">
                    <span class="icon">
                        <svg class="icon-chat" viewBox="0 0 24 24" style="width:20px;height:20px;">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                        </svg>
                    </span>
                    یادداشت‌ها
                </span>
            </div>
            <div class="card-body">
                <div class="chat-wrap" id="chatMessages">
                    <?php foreach($messages as $msg): $is_mine = ($msg['sender_role'] === $current_role); ?>
                    <div class="msg <?php echo $is_mine ? 'sent' : 'recv'; ?>">
                        <strong><?php echo $is_mine ? 'شما' : htmlspecialchars($msg['branch_name'] ?? 'کاربر'); ?></strong>
                        <div><?php echo nl2br(htmlspecialchars($msg['message'])); ?></div>
                        <div class="time"><?php echo htmlspecialchars($msg['created_at']); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php if ($report_id > 0): ?>
                <div class="chat-input">
                    <textarea id="newMessage" placeholder="پیام..." rows="1"></textarea>
                    <button onclick="sendMessage()">ارسال</button>
                </div>
                <?php endif; ?>
            </div>
        </div>

    </div> <!-- END tab-balance -->

    <!-- ============ TAB: INCOME ============ -->
    <div id="tab-income" class="tab-content <?php echo $tab === 'income' ? 'active' : ''; ?>">
        <div class="date-badge">
            <span>
                <svg class="icon-calendar" viewBox="0 0 24 24" style="width:16px;height:16px;vertical-align:middle;display:inline-block;margin-left:6px;">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                </svg>
                <?php echo htmlspecialchars($selected_date_ui); ?>
            </span>
            <span class="rate">نرخ: <span id="poll-rate-display-income"><?php echo $daily_rate ? number_format($daily_rate) : '-'; ?></span></span>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
            <!-- روزانه -->
            <div class="card">
                <div class="card-header">
                    <span class="card-title">
                        <span class="icon">
                            <svg class="icon-money" viewBox="0 0 24 24" style="width:20px;height:20px;">
                                <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/>
                            </svg>
                        </span>
                        درآمد روزانه
                    </span>
                </div>
                <div class="card-body">
                    <?php
                    $currentCat = ''; $daily_total_rial = 0; $daily_total_gram = 0;
                    foreach($income_daily_items as $item):
                        if($item['category'] && $item['category'] !== $currentCat):
                            $currentCat = $item['category'];
                            echo '<div class="income-cat">'.htmlspecialchars($currentCat).'</div>';
                        endif;
                        $rec = $income_daily_data[$item['id']] ?? null;
                        $rial = $rec ? (float)$rec['amount_rial'] : 0;
                        $gram = $rec ? (float)$rec['amount_gram'] : ($daily_rate > 0 && $rial != 0 ? $rial / $daily_rate : 0);
                        $daily_total_rial += $rial; $daily_total_gram += $gram;
                    ?>
                    <div class="income-item">
                        <span class="name"><?php echo htmlspecialchars($item['name']); ?></span>
                        <div class="values"><div class="rial"><?php echo $rial != 0 ? fmt_num($rial) : '-'; ?></div><div class="gram"><?php echo fmt_gram($gram); ?> گرم</div></div>
                    </div>
                    <?php endforeach; ?>
                    <div class="income-item income-total">
                        <span class="name">جمع کل روزانه</span>
                        <div class="values"><div class="rial" style="color:var(--primary);"><?php echo $daily_total_rial > 0 ? fmt_num($daily_total_rial) : '-'; ?></div><div class="gram"><?php echo fmt_gram($daily_total_gram); ?> گرم</div></div>
                    </div>
                </div>
            </div>

            <!-- ماهانه -->
            <div class="card">
                <div class="card-header">
                    <span class="card-title">
                        <span class="icon">
                            <svg class="icon-chart" viewBox="0 0 24 24" style="width:20px;height:20px;">
                                <line x1="18" y1="20" x2="2" y2="20"/><polyline points="2 18 7 12 12 15 17 6 22 9"/>
                            </svg>
                        </span>
                        درآمد ماهانه
                    </span>
                </div>
                <div class="card-body">
                    <?php
                    $mCat = ''; $monthly_total_gram = 0;
                    foreach($income_monthly_items as $mItem):
                        if($mItem['category'] && $mItem['category'] !== $mCat):
                            $mCat = $mItem['category'];
                            echo '<div class="income-cat">'.htmlspecialchars($mCat).'</div>';
                        endif;
                        $mRec = $income_monthly_data[$mItem['id']] ?? null;
                        $mGram = $mRec ? (float)$mRec['amount_gram'] : 0;
                        $monthly_total_gram += $mGram;
                    ?>
                    <div class="income-item">
                        <span class="name"><?php echo htmlspecialchars($mItem['name']); ?></span>
                        <div class="values"><div class="rial"><?php echo fmt_gram($mGram); ?> گرم</div></div>
                    </div>
                    <?php endforeach; ?>
                    <div class="income-item income-total">
                        <span class="name">جمع کل ماهانه</span>
                        <div class="values"><div class="rial" style="color:var(--primary);"><?php echo fmt_gram($monthly_total_gram); ?> گرم</div></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const isObserver = <?php echo $is_observer ? 'true' : 'false'; ?>;
const isAdmin = <?php echo $is_admin ? 'true' : 'false'; ?>;
const branchId = <?php echo $branch_id; ?>;
const currentDate = '<?php echo htmlspecialchars($selected_date, ENT_QUOTES); ?>';
const currentRole = '<?php echo htmlspecialchars($current_role, ENT_QUOTES); ?>';
let lastUpdateTs = <?php echo $last_updated_ts; ?>;
let lastMsgId = <?php echo $last_msg_id; ?>;

// ========== حالت روز/شب ==========
function toggleDarkMode() {
    document.body.classList.toggle('dark');
    const btn = document.getElementById('themeToggle');
    const isDark = document.body.classList.contains('dark');
    btn.innerHTML = isDark 
        ? '<svg class="icon-sun" viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>'
        : '<svg class="icon-moon" viewBox="0 0 24 24"><path d="M12 3a6 6 0 0 0 9 9 6 6 0 1 1-9-9z"/></svg>';
    localStorage.setItem('theme', isDark ? 'dark' : 'light');
}
(function(){
    if(localStorage.getItem('theme') === 'dark') {
        document.body.classList.add('dark');
        document.getElementById('themeToggle').innerHTML = 
            '<svg class="icon-sun" viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>';
    }
})();

// ========== تب‌ها ==========
function switchTab(t) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    document.querySelector(`.tab-btn[onclick="switchTab('${t}')"]`).classList.add('active');
    document.getElementById('tab-' + t).classList.add('active');
    history.replaceState(null, '', `?date=${encodeURIComponent('<?php echo $selected_date_ui; ?>')}&branch_id=${branchId}&tab=${t}`);
}

// ========== توست ==========
function showToast(msg, type) {
    const c = document.getElementById('toastContainer');
    const t = document.createElement('div');
    t.className = 'toast' + (type ? ' ' + type : '');
    t.textContent = msg;
    c.appendChild(t);
    setTimeout(() => t.remove(), 3000);
}

// ========== ارسال پیام ==========
function sendMessage() {
    const msg = document.getElementById('newMessage').value.trim();
    if (!msg) return;
    document.getElementById('newMessage').value = '';
    const btn = document.querySelector('.chat-input button');
    btn.disabled = true;
    btn.textContent = '...';
    const chatDiv = document.getElementById('chatMessages');
    const tmp = document.createElement('div');
    tmp.className = 'msg sent';
    tmp.innerHTML = '<strong>شما</strong><div>' + msg.replace(/\n/g, '<br>') + '</div><div class="time">ارسال...</div>';
    chatDiv.appendChild(tmp);
    chatDiv.scrollTop = chatDiv.scrollHeight;
    const ep = (currentRole === 'branch') ? 'user/send_reply.php' : 'user/send_message.php';
    fetch(ep, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ date: currentDate, message: msg, branch_id: branchId })
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            tmp.querySelector('.time').textContent = 'الان';
        } else {
            tmp.style.opacity = '0.5';
            showToast('خطا در ارسال پیام', 'error');
        }
    })
    .catch(() => {
        tmp.style.opacity = '0.5';
        showToast('خطا در ارسال پیام', 'error');
    })
    .finally(() => {
        btn.disabled = false;
        btn.textContent = 'ارسال';
    });
}

// ========== نظرسنجی خودکار (ناظر/مدیر) ==========
if (isObserver || isAdmin) {
    let pollTimer, pollInterval = 10000;
    function doPoll() {
        fetch('view.php?poll=1&branch_id=' + branchId + '&last=' + lastUpdateTs + '&last_msg_id=' + lastMsgId + '&date=' + encodeURIComponent(currentDate) + '&tab=<?php echo $tab; ?>')
        .then(r => r.json())
        .then(data => {
            const balanceActive = document.getElementById('tab-balance').classList.contains('active');
            const incomeActive = document.getElementById('tab-income').classList.contains('active');
            if (data.changed && balanceActive) location.reload();
            if (data.income_changed && incomeActive) location.reload();
            if (data.new_messages && data.new_messages.length) {
                const cd = document.getElementById('chatMessages');
                data.new_messages.forEach(msg => {
                    lastMsgId = Math.max(lastMsgId, msg.id);
                    const d = document.createElement('div');
                    d.className = 'msg recv';
                    d.innerHTML = '<strong>' + (msg.branch_name || '') + '</strong><div>' + (msg.message || '').replace(/\n/g, '<br>') + '</div><div class="time">' + msg.created_at + '</div>';
                    cd.appendChild(d);
                });
                cd.scrollTop = cd.scrollHeight;
            }
            if (data.last_updated > lastUpdateTs) lastUpdateTs = data.last_updated;
        })
        .catch(() => {});
    }
    setTimeout(() => {
        doPoll();
        pollTimer = setInterval(doPoll, pollInterval);
    }, 4000);
    document.addEventListener('visibilitychange', () => {
        clearInterval(pollTimer);
        pollInterval = document.hidden ? 30000 : 10000;
        pollTimer = setInterval(doPoll, pollInterval);
    });
}
</script>
</body>
</html>