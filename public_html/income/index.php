<?php
define('SECURE_ACCESS', true);
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/jdf.php';
requireLogin();

$current_user = getCurrentUser();
$role = $_SESSION['role'];
$user_id = intval($_SESSION['user_id']);
$is_admin = ($role === 'admin');
$is_observer = ($role === 'observer');
$is_readonly = $is_observer;

// ⭐ branch_id
if ($is_admin || $is_observer) {
    if ($is_observer) {
        $branches = [];
        $stmt = mysqli_prepare($conn, "SELECT u.id, u.branch_name FROM observer_assignments oa JOIN users u ON oa.branch_id = u.id WHERE oa.observer_id = ?");
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $br_res = mysqli_stmt_get_result($stmt);
        while ($b = mysqli_fetch_assoc($br_res)) $branches[] = $b;
        $branch_id = isset($_GET['branch_id']) ? intval($_GET['branch_id']) : ($branches[0]['id'] ?? 0);
    } else {
        $branches = [];
        $br_res = mysqli_query($conn, "SELECT id, branch_name FROM users WHERE role='branch' ORDER BY branch_name");
        while ($b = mysqli_fetch_assoc($br_res)) $branches[] = $b;
        $branch_id = isset($_GET['branch_id']) ? intval($_GET['branch_id']) : ($branches[0]['id'] ?? 0);
    }
} else {
    $branch_id = $user_id;
}

// ⭐ دریافت نام شعبه
$branch_name = '';
$stmt = mysqli_prepare($conn, "SELECT branch_name FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $branch_id);
mysqli_stmt_execute($stmt);
$b_res = mysqli_stmt_get_result($stmt);
if ($b_res && mysqli_num_rows($b_res) > 0) $branch_name = mysqli_fetch_assoc($b_res)['branch_name'];

// ⭐ دریافت تاریخ شمسی (فرمت: 1405-03-05)
$selected_date = isset($_GET['date']) ? $_GET['date'] : jdate('Y-m-d');
$selected_date = str_replace('/', '-', $selected_date);
$selected_date = str_replace(['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'], ['0','1','2','3','4','5','6','7','8','9'], $selected_date);

// ========== ذخیره ==========
if (isset($_POST['save'])) {
    header('Content-Type: application/json; charset=utf-8');
    $date = $_POST['date'] ?? '';
    $rate = floatval($_POST['gold_rate'] ?? 0);
    $items = json_decode($_POST['items'] ?? '[]', true);
    $d = str_replace('/', '-', $date);
    
    $stmt = mysqli_prepare($conn, "DELETE FROM income_daily_records WHERE branch_id = ? AND record_date = ?");
    mysqli_stmt_bind_param($stmt, "is", $branch_id, $d);
    mysqli_stmt_execute($stmt);
    
    $count = 0;
    $stmt = mysqli_prepare($conn, "INSERT INTO income_daily_records (branch_id, item_id, record_date, gold_rate, amount_rial, amount_gram, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
    foreach ($items as $item) {
        $item_id = intval($item['id']); $rial = floatval($item['rial'] ?? 0); $gram = floatval($item['gram'] ?? 0);
        if ($item_id > 0 && ($rial != 0 || $gram != 0)) {
            mysqli_stmt_bind_param($stmt, "iisiddi", $branch_id, $item_id, $d, $rate, $rial, $gram, $user_id);
            if (mysqli_stmt_execute($stmt)) $count++;
        }
    }
    // ⭐ اینو اینجا اضافه کن
    require_once __DIR__ . '/../includes/reminder_functions.php';
    clearReminderAfterSubmit($branch_id, $d, 'درآمد روزانه');
    
    echo json_encode(['success' => true, 'message' => "$count مورد ذخیره شد"]);
    exit;
}


// ========== آیتم‌ها ==========
$items = [];
$d_res = mysqli_query($conn, "SELECT * FROM income_daily_items WHERE active=1 ORDER BY sort_order");
while ($r = mysqli_fetch_assoc($d_res)) $items[] = $r;

// ⭐ داده‌های ذخیره‌شده
$today_data = []; $today_rate = 0;
$d = $selected_date;
$stmt = mysqli_prepare($conn, "SELECT * FROM income_daily_records WHERE branch_id = ? AND record_date = ?");
mysqli_stmt_bind_param($stmt, "is", $branch_id, $d);
mysqli_stmt_execute($stmt);
$rec_res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($rec_res)) {
    $today_data[$row['item_id']] = $row;
    if ($row['gold_rate'] > 0) $today_rate = $row['gold_rate'];
}

$today_shamsi = jdate('Y-m-d');
$today_shamsi = str_replace(['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'], ['0','1','2','3','4','5','6','7','8','9'], $today_shamsi);
$theme = isset($_COOKIE['theme']) ? $_COOKIE['theme'] : 'dark';
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>درآمد روزانه | <?php echo $branch_name; ?></title>
    <link href="../assets/fonts/fonts.css" rel="stylesheet">
    <link href="../assets/css/persian-datepicker.css" rel="stylesheet">
    <link rel="icon" href="../assets/images/logo.png">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../assets/js/persian-datepicker.js"></script>
    <style>
        :root {
            --bg-main: #0a0f1a;
            --bg-card: rgba(255,255,255,0.05);
            --bg-input: rgba(0,0,0,0.3);
            --border: rgba(255,255,255,0.08);
            --text-1: #e8ecf1;
            --text-2: #94a3b8;
            --accent: #d4af37;
            --accent-bg: rgba(212,175,55,0.1);
            --purple: #a78bfa;
            --purple-bg: rgba(167,139,250,0.1);
            --red: #f87171;
            --red-bg: rgba(248,113,113,0.1);
            --green: #10b981;
            --radius: 16px;
            --radius-sm: 10px;
        }
        
        body.light {
            --bg-main: #f5f7fa;
            --bg-card: rgba(255,255,255,0.9);
            --bg-input: rgba(0,0,0,0.03);
            --border: rgba(0,0,0,0.08);
            --text-1: #1e293b;
            --text-2: #64748b;
            --accent: #b45309;
            --accent-bg: rgba(180,83,9,0.08);
            --purple: #7c3aed;
            --purple-bg: rgba(124,58,237,0.08);
            --red: #dc2626;
            --red-bg: rgba(220,38,38,0.08);
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { 
            font-family: 'Vazirmatn', sans-serif; 
            background: var(--bg-main); 
            color: var(--text-1); 
            padding: 16px 12px; 
            direction: rtl; 
            min-height: 100vh; 
            transition: all 0.3s ease;
        }
        .wrapper { max-width: 700px; margin: 0 auto; display: flex; flex-direction: column; gap: 16px; }
        
        .app-header {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            backdrop-filter: blur(12px);
        }
        .app-title {
            font-size: 1rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, #fcd34d, #d4af37);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .badge {
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            background: rgba(212,175,55,0.15);
            color: #d4af37;
        }
        .theme-btn {
            background: var(--bg-input);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 6px 12px;
            cursor: pointer;
            font-family: 'Vazirmatn';
            font-size: 0.75rem;
            color: var(--text-1);
            transition: all 0.2s;
        }
        .theme-btn:hover {
            background: var(--accent-bg);
            border-color: var(--accent);
        }
        .logout-btn {
            background: var(--bg-input);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 6px 12px;
            color: var(--text-2);
            font-weight: 700;
            text-decoration: none;
            font-size: 0.75rem;
            transition: all 0.2s;
        }
        .logout-btn:hover {
            background: var(--accent-bg);
            color: var(--accent);
        }
        
        .panel {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            overflow: hidden;
            backdrop-filter: blur(12px);
        }
        .panel-body { padding: 20px; }
        .panel-title {
            font-weight: 700;
            font-size: 0.9rem;
            margin-bottom: 16px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--accent-bg);
            color: var(--accent);
        }
        
        .date-bar {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            padding: 10px 14px;
            background: var(--bg-input);
            border-radius: var(--radius-sm);
            flex-wrap: wrap;
            border: 1px solid var(--border);
        }
        .date-bar label { font-size: 0.75rem; color: var(--text-2); white-space: nowrap; }
        .date-bar input {
            padding: 8px 12px;
            border-radius: 8px;
            background: rgba(0,0,0,0.2);
            border: 1px solid var(--border);
            color: var(--text-1);
            font-family: 'Vazirmatn';
            font-size: 0.85rem;
            text-align: center;
            width: 140px;
        }
        .date-bar button {
            background: var(--accent-bg);
            border: none;
            padding: 6px 14px;
            border-radius: 8px;
            cursor: pointer;
            color: var(--accent);
            font-family: 'Vazirmatn';
            font-size: 0.75rem;
            transition: all 0.2s;
        }
        .date-bar button:hover {
            background: rgba(212,175,55,0.2);
        }
        
        .icard {
            border-radius: var(--radius-sm);
            border: 1px solid var(--border);
            overflow: hidden;
            background: var(--bg-input);
            margin-bottom: 16px;
        }
        .icard-head {
            padding: 10px 14px;
            background: var(--accent-bg);
            color: var(--accent);
            font-weight: 700;
            font-size: 0.8rem;
        }
        .icard-body { padding: 12px; }
        
        .item-row {
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(255,255,255,0.03);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 8px 12px;
            margin-bottom: 8px;
            transition: all 0.2s;
        }
        .item-row:hover {
            border-color: var(--accent);
            background: rgba(212,175,55,0.05);
        }
        .item-row .item-name { flex: 1; font-size: 0.8rem; font-weight: 500; }
        .item-row input {
            flex: 1.2;
            border: none;
            outline: none;
            font-family: 'Vazirmatn', sans-serif;
            font-size: 0.8rem;
            background: transparent;
            text-align: center;
            color: var(--text-1);
            padding: 4px;
        }
        .item-row .gram-val {
            flex: 0.7;
            font-size: 0.7rem;
            color: var(--green);
            text-align: center;
        }
        
        /* ⭐ دکمه ماشین حساب جدید */
        .calc-btn {
            background: linear-gradient(135deg, var(--purple-bg), rgba(167,139,250,0.05));
            border: 1px solid var(--purple);
            border-radius: 8px;
            width: 32px;
            height: 32px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--purple);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            flex-shrink: 0;
            position: relative;
            overflow: hidden;
        }
        .calc-btn::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, var(--purple), #6366f1);
            opacity: 0;
            transition: opacity 0.3s;
        }
        .calc-btn:hover::before {
            opacity: 0.1;
        }
        .calc-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(167,139,250,0.3);
            border-color: #8b5cf6;
        }
        .calc-btn:active {
            transform: translateY(0);
            box-shadow: 0 2px 6px rgba(167,139,250,0.2);
        }
        .calc-btn svg {
            width: 16px;
            height: 16px;
            position: relative;
            z-index: 1;
            transition: transform 0.3s;
        }
        .calc-btn:hover svg {
            transform: scale(1.1);
        }
        
        /* ⭐ مودال ماشین حساب */
        .calc-modal-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.7);
            z-index: 10000;
            display: flex;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(4px);
        }
        .calc-modal {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 20px;
            width: 320px;
            max-width: 90vw;
            backdrop-filter: blur(20px);
        }
        .calc-display {
            background: var(--bg-input);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 14px;
            font-size: 1.3rem;
            font-family: 'Vazirmatn', monospace;
            text-align: left;
            direction: ltr;
            color: var(--text-1);
            margin-bottom: 16px;
            min-height: 50px;
            word-break: break-all;
            overflow-x: auto;
            white-space: nowrap;
        }
        .calc-buttons {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            direction: ltr;
        }
        .calc-buttons button {
            padding: 12px 4px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: var(--bg-input);
            color: var(--text-1);
            font-family: 'Vazirmatn';
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.15s;
        }
        .calc-buttons button:hover {
            background: var(--accent-bg);
            border-color: var(--accent);
        }
        .calc-buttons button:active {
            transform: scale(0.95);
        }
        .calc-buttons .btn-op {
            background: var(--purple-bg);
            border-color: var(--purple);
            color: var(--purple);
        }
        .calc-buttons .btn-op:hover {
            background: rgba(167,139,250,0.25);
        }
        .calc-buttons .btn-eq {
            background: var(--accent-bg);
            border-color: var(--accent);
            color: var(--accent);
            grid-row: span 2;
        }
        .calc-buttons .btn-eq:hover {
            background: rgba(212,175,55,0.25);
        }
        .calc-buttons .btn-clr {
            background: var(--red-bg);
            border-color: var(--red);
            color: var(--red);
        }
        .calc-buttons .btn-clr:hover {
            background: rgba(248,113,113,0.25);
        }
        .calc-buttons .btn-back {
            background: var(--bg-input);
            border-color: var(--border);
            color: var(--text-2);
        }
        .calc-modal-footer {
            display: flex;
            gap: 8px;
            margin-top: 12px;
        }
        .calc-modal-footer button {
            flex: 1;
            padding: 10px;
            border-radius: 8px;
            border: 1px solid var(--border);
            font-family: 'Vazirmatn';
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.2s;
        }
        .btn-apply {
            background: var(--accent-bg);
            color: var(--accent);
            border-color: var(--accent) !important;
        }
        .btn-apply:hover {
            background: rgba(212,175,55,0.25);
        }
        .btn-cancel {
            background: var(--bg-input);
            color: var(--text-2);
        }
        .btn-cancel:hover {
            background: rgba(255,255,255,0.05);
        }
        
        /* ⭐ استایل کارت‌های جمع کل */
        .totals-row {
            display: flex;
            gap: 12px;
            margin-bottom: 16px;
        }
        .total-card {
            flex: 1;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 14px;
            text-align: center;
            backdrop-filter: blur(12px);
            transition: all 0.3s ease;
        }
        .total-card:hover {
            border-color: var(--accent);
            box-shadow: 0 4px 15px rgba(212,175,55,0.1);
        }
        .total-label {
            font-size: 0.7rem;
            color: var(--text-2);
            margin-bottom: 8px;
            font-weight: 500;
            letter-spacing: 0.5px;
        }
        .total-value {
            font-size: 1.1rem;
            font-weight: 800;
            direction: ltr;
        }
        .total-value.rial {
            background: linear-gradient(135deg, var(--accent), #f59e0b);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .total-value.gram {
            background: linear-gradient(135deg, var(--green), #34d399);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .toast-container { position: fixed; bottom: 80px; right: 20px; z-index: 9999; display: flex; flex-direction: column; gap: 8px; }
        .toast { padding: 12px 20px; border-radius: 10px; color: white; font-size: 0.85rem; box-shadow: 0 4px 12px rgba(0,0,0,0.15); animation: slideIn 0.3s ease; }
        .toast.success { background: #10b981; }
        .toast.error { background: #ef4444; }
        @keyframes slideIn { from { opacity: 0; transform: translateX(50px); } to { opacity: 1; transform: translateX(0); } }
        
        select {
            padding: 6px 10px;
            border-radius: 8px;
            background: var(--bg-input);
            border: 1px solid var(--border);
            color: var(--text-1);
            font-family: 'Vazirmatn';
            font-size: 0.75rem;
        }
        
        input::placeholder { color: var(--text-2); opacity: 0.5; }
        
        /* ⭐ تب‌های سوییچ */
        .tab-switch {
            display: flex;
            gap: 4px;
            padding: 6px;
            background: var(--bg-input);
            border: 1px solid var(--border);
            border-radius: 12px;
            margin-bottom: 16px;
            overflow-x: auto;
        }
        .tab-switch-item {
            flex: 1;
            min-width: 90px;
            padding: 10px 16px;
            text-align: center;
            text-decoration: none;
            font-family: 'Vazirmatn', sans-serif;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-2);
            border-radius: 8px;
            transition: all 0.25s;
            white-space: nowrap;
        }
        .tab-switch-item:hover {
            color: var(--text-1);
            background: rgba(255,255,255,0.03);
        }
        .tab-switch-item.active {
            background: var(--accent);
            color: #fff;
            font-weight: 700;
        }
    </style>
</head>
<body class="<?php echo $theme; ?>">
<div class="wrapper">
    <div class="app-header">
        <div class="app-title">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="1" x2="12" y2="23"></line>
                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
            </svg>
            <span>درآمد روزانه | <?php echo $branch_name; ?></span>
            <?php if ($is_readonly): ?><span class="badge">مشاهده</span><?php endif; ?>
        </div>
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <?php if ($is_admin || $is_observer): ?>
            <select onchange="changeBranch(this.value)">
                <?php foreach ($branches as $b): ?>
                    <option value="<?php echo $b['id']; ?>" <?php echo $b['id'] == $branch_id ? 'selected' : ''; ?>><?php echo htmlspecialchars($b['branch_name']); ?></option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
            <button class="theme-btn" onclick="toggleTheme()" id="themeToggle">
                <?php echo $theme == 'light' ? 
                    '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>' : 
                    '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>'; 
                ?>
            </button>
            <a href="../index.php#income" class="logout-btn">
                <svg style="width:14px;height:14px;vertical-align:middle;margin-left:4px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
                    <polyline points="10 17 15 12 10 7"></polyline>
                    <line x1="15" y1="12" x2="3" y2="12"></line>
                </svg>
                بازگشت
            </a>
        </div>
    </div>
    
    <!-- ⭐ تب‌ها -->
<div class="tab-switch">
    <a href="../user/index.php?date=<?php echo $selected_date; ?>" 
       class="tab-switch-item <?php echo basename(dirname($_SERVER['SCRIPT_NAME'])) == 'user' ? 'active' : ''; ?>">
        تراز روزانه
    </a>
    <a href="../income/index.php?date=<?php echo $selected_date; ?>" 
       class="tab-switch-item <?php echo basename(dirname($_SERVER['SCRIPT_NAME'])) == 'income' ? 'active' : ''; ?>">
        درآمد روزانه
    </a>
    <!-- ⭐ تب جدید درآمد ماهانه -->
    <a href="../income/monthly.php?date=<?php echo $selected_date; ?><?php echo $branch_id != $user_id ? '&branch_id='.$branch_id : ''; ?>" 
   class="tab-switch-item">
    درآمد ماهانه
</a>
    <a href="../goals/daily.php?date=<?php echo $selected_date; ?>" 
       class="tab-switch-item <?php echo strpos($_SERVER['SCRIPT_NAME'], 'goals/daily.php') !== false ? 'active' : ''; ?>">
        ثبت پیشرفت
    </a>
</div>

    <div class="toast-container" id="toastContainer"></div>
    
    <!-- ⭐ ماشین حساب مودال -->
    <div class="calc-modal-overlay" id="calcModal" style="display: none;">
        <div class="calc-modal">
            <div class="calc-display" id="calcDisplay">0</div>
            <div class="calc-buttons">
                <button class="btn-clr" onclick="calcClear()">C</button>
                <button class="btn-back" onclick="calcBack()">⌫</button>
                <button class="btn-op" onclick="calcInput('/')">÷</button>
                <button class="btn-op" onclick="calcInput('*')">×</button>
                
                <button onclick="calcInput('7')">7</button>
                <button onclick="calcInput('8')">8</button>
                <button onclick="calcInput('9')">9</button>
                <button class="btn-op" onclick="calcInput('-')">−</button>
                
                <button onclick="calcInput('4')">4</button>
                <button onclick="calcInput('5')">5</button>
                <button onclick="calcInput('6')">6</button>
                <button class="btn-op" onclick="calcInput('+')">+</button>
                
                <button onclick="calcInput('1')">1</button>
                <button onclick="calcInput('2')">2</button>
                <button onclick="calcInput('3')">3</button>
                <button class="btn-eq" onclick="calcEqual()">=</button>
                
                <button onclick="calcInput('0')" style="grid-column: span 2;">0</button>
                <button onclick="calcInput('.')">.</button>
            </div>
            <div class="calc-modal-footer">
                <button class="btn-cancel" onclick="closeCalc()">انصراف</button>
                <button class="btn-apply" onclick="applyCalc()">✓ تأیید</button>
            </div>
        </div>
    </div>
    
    <!-- ⭐ جمع کل ریالی و گرمی -->
    <div class="totals-row">
        <div class="total-card">
    <div class="total-label">
        <svg style="width:14px;height:14px;vertical-align:middle;margin-left:4px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="12" y1="1" x2="12" y2="23"></line>
            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
        </svg>
        جمع کل ریالی
    </div>
    <div class="total-value rial" id="totalRial">۰ ریال</div>
</div>
        <div class="total-card">
    <div class="total-label">
        <svg style="width:14px;height:14px;vertical-align:middle;margin-left:4px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="6" x2="12" y2="12"></line>
            <line x1="12" y1="12" x2="16" y2="14"></line>
        </svg>
        جمع کل طلا (گرم)
    </div>
    <div class="total-value gram" id="totalGram">۰.۰۰۰۰ گرم</div>
</div>
    </div>
    
    <div class="panel">
        <div class="panel-body">
            <div class="panel-title">
    <svg style="width:18px;height:18px;vertical-align:middle;margin-left:6px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
        <line x1="16" y1="2" x2="16" y2="6"></line>
        <line x1="8" y1="2" x2="8" y2="6"></line>
        <line x1="3" y1="10" x2="21" y2="10"></line>
    </svg>
    درآمد روزانه
</div>
            <input type="hidden" id="dailyDate" value="<?php echo $selected_date; ?>">
            <input type="hidden" id="serverDate" value="<?php echo $today_shamsi; ?>">
            
            <div class="date-bar">
                <label>
    <svg style="width:14px;height:14px;vertical-align:middle;margin-left:4px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
        <line x1="16" y1="2" x2="16" y2="6"></line>
        <line x1="8" y1="2" x2="8" y2="6"></line>
        <line x1="3" y1="10" x2="21" y2="10"></line>
    </svg>
    تاریخ:
</label>
                <input type="text" id="datepicker" class="date-input" value="<?php echo $selected_date; ?>" readonly>
            </div>
            
            <?php if (!$is_readonly): ?>
            <div class="icard">
                <div class="icard-head">
    <svg style="width:16px;height:16px;vertical-align:middle;margin-left:4px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline>
        <polyline points="17 6 23 6 23 12"></polyline>
    </svg>
    نرخ هر گرم طلا (ریال)
</div>
                <div class="icard-body">
                    <input type="text" id="goldRate" value="<?php echo $today_rate ? number_format($today_rate) : ''; ?>" placeholder="۱۸۵,۰۰۰,۰۰۰" oninput="formatNum(this); updateGrams(); debounceSave();" style="width:100%;padding:10px;border-radius:8px;background:var(--bg-input);border:1px solid var(--border);color:var(--text-1);font-family:'Vazirmatn';font-size:0.85rem;">
                </div>
            </div>
            <?php endif; ?>
            
            <?php $currentCat = ''; $noCat = false; foreach ($items as $item): 
                $val = $today_data[$item['id']] ?? null; 
                $rial = $val ? $val['amount_rial'] : ''; 
                $gram = $val ? $val['amount_gram'] : 0;
                
                if ($item['category'] && $item['category'] !== $currentCat): 
                    if ($currentCat) echo '</div></div></div>'; 
                    $currentCat = $item['category']; 
                    echo '<div class="icard"><div class="icard-head">
    <svg style="width:16px;height:16px;vertical-align:middle;margin-left:4px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
    </svg>' . $currentCat . '</div><div class="icard-body">';
                elseif (!$item['category'] && $currentCat): 
                    echo '</div></div></div>'; 
                    $currentCat = ''; 
                endif;
                if (!$item['category'] && !$currentCat && !$noCat): 
                    echo '<div class="icard"><div class="icard-head">
    <svg style="width:16px;height:16px;vertical-align:middle;margin-left:4px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="8" y1="6" x2="21" y2="6"></line>
        <line x1="8" y1="12" x2="21" y2="12"></line>
        <line x1="8" y1="18" x2="21" y2="18"></line>
        <line x1="3" y1="6" x2="3.01" y2="6"></line>
        <line x1="3" y1="12" x2="3.01" y2="12"></line>
        <line x1="3" y1="18" x2="3.01" y2="18"></line>
    </svg>
    آیتم‌ها</div><div class="icard-body">';
                    $noCat = true; 
                endif; 
            ?>
                <div class="item-row">
                    <span class="item-name"><?php echo $item['name']; ?></span>
                    <input type="text" class="daily-input" data-id="<?php echo $item['id']; ?>" value="<?php echo $rial ? number_format($rial) : ''; ?>" placeholder="ریال" oninput="formatNum(this); updateGrams(); debounceSave();" <?php echo $is_readonly ? 'disabled' : ''; ?>>
                    <?php if (!$is_readonly): ?>
                    <button class="calc-btn" onclick="openCalc(this)" title="ماشین حساب">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="4" y="2" width="16" height="20" rx="2"/>
                            <line x1="8" y1="6" x2="16" y2="6"/>
                            <line x1="8" y1="10" x2="16" y2="10"/>
                            <line x1="8" y1="14" x2="12" y2="14"/>
                            <line x1="8" y1="18" x2="16" y2="18"/>
                        </svg>
                    </button>
                    <?php endif; ?>
                    <span class="gram-val" id="dg-<?php echo $item['id']; ?>"><?php echo $gram ? number_format($gram, 4) . ' گرم' : ''; ?></span>
                </div>
            <?php endforeach; 
            if ($currentCat || $noCat) echo '</div></div></div>'; ?>
        </div>
    </div>
</div>

<script>
// ========== Theme ==========
function toggleTheme() {
    var body = document.body;
    var btn = document.getElementById('themeToggle');
    if (body.classList.contains('light')) {
        body.classList.remove('light');
        btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>';
        document.cookie = "theme=dark; path=/; max-age=" + (365 * 24 * 60 * 60);
    } else {
        body.classList.add('light');
        btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>';
        document.cookie = "theme=light; path=/; max-age=" + (365 * 24 * 60 * 60);
    }
}

// ========== Toast ==========
function showToast(msg, type) { 
    type = type || 'success'; 
    var c = document.getElementById('toastContainer'); 
    var t = document.createElement('div'); 
    t.className = 'toast ' + type; 
    t.textContent = msg; 
    c.appendChild(t); 
    setTimeout(function() { t.remove(); }, 2000); 
}

// ========== Number Utils ==========
function parseNum(s) { return parseFloat(String(s||'').replace(/,/g, '')) || 0; }
function formatNum(el) { 
    let raw = el.value.replace(/,/g, ''); 
    let num = parseFloat(raw); 
    if (!isNaN(num)) el.value = num.toLocaleString('en-US'); 
}

// ========== Navigation ==========
function changeBranch(id) { 
    var date = document.getElementById('dailyDate').value;
    window.location.href = 'index.php?branch_id=' + id + '&date=' + date; 
}

function changeDate() {
    var date = document.getElementById('datepicker').value;
    if (date) {
        window.location.href = 'index.php?date=' + date + '<?php echo $branch_id != $user_id ? '&branch_id='.$branch_id : ''; ?>';
    }
}

// ========== ⭐ اصلاح شده: Grams Update + Totals ==========
function updateGrams() { 
    var rateEl = document.getElementById('goldRate');
    var rate = rateEl ? parseNum(rateEl.value) : 0;
    
    var totalRial = 0;
    var totalGram = 0;
    
    document.querySelectorAll('.daily-input').forEach(function(inp) { 
        var rial = parseNum(inp.value);
        
        // همیشه ریال رو جمع کن
        totalRial += rial;
        
        var span = document.getElementById('dg-' + inp.dataset.id); 
        if (span) { 
            var gram = 0;
            
            if (rate > 0) {
                // اگر نرخ داریم، گرم رو محاسبه کن
                gram = rial / rate;
                span.textContent = gram > 0 ? gram.toFixed(4) + ' گرم' : '';
            } else {
                // اگر نرخ نداریم (مثلاً observer یا تاریخ قبلی)، گرم رو از span بخون
                var gramText = span.textContent.replace(' گرم', '').trim();
                gram = parseFloat(gramText) || 0;
            }
            
            totalGram += gram;
        } 
    });
    
    // ⭐ نمایش جمع‌ها (فقط فرانت‌اند - هیچ داده‌ای ذخیره نمیشه)
    var rialEl = document.getElementById('totalRial');
    var gramEl = document.getElementById('totalGram');
    
    if (rialEl) {
        rialEl.textContent = totalRial > 0 ? totalRial.toLocaleString('en-US') + ' ریال' : '۰ ریال';
    }
    if (gramEl) {
        gramEl.textContent = totalGram > 0 ? totalGram.toFixed(4) + ' گرم' : '۰.۰۰۰۰ گرم';
    }
}

// ========== Save ==========
var saveTimer; 
function debounceSave() { clearTimeout(saveTimer); saveTimer = setTimeout(saveData, 2000); }

async function saveData() {
    var date = document.getElementById('dailyDate').value, 
        rate = parseNum(document.getElementById('goldRate')?.value || 0), 
        items = [];
    
    document.querySelectorAll('.daily-input').forEach(function(inp) { 
        var rial = parseNum(inp.value); 
        if (rial != 0) items.push({ id: parseInt(inp.dataset.id), rial: rial, gram: rate > 0 ? rial / rate : 0 }); 
    });
    
    if (!date || items.length === 0) return;
    
    try { 
        var resp = await fetch('index.php', { 
            method:'POST', 
            headers:{'Content-Type':'application/x-www-form-urlencoded'}, 
            body:'save=1&date='+encodeURIComponent(date)+'&gold_rate='+rate+'&items='+encodeURIComponent(JSON.stringify(items))
        }); 
        var data = await resp.json(); 
        if (data.success) showToast('✓ ذخیره شد', 'success'); 
    } catch(e) { console.error(e); }
}

// ========== ⭐ Calculator ==========
var calcTargetInput = null;
var calcExpression = '0';
var calcResultShown = false;

function formatNumberWithCommas(numStr) {
    if (!numStr || numStr === '0' || numStr === 'خطا') return numStr;
    
    var isNegative = false;
    if (numStr.startsWith('-')) {
        isNegative = true;
        numStr = numStr.substring(1);
    }
    
    var parts = numStr.split('.');
    var integerPart = parts[0];
    var decimalPart = parts.length > 1 ? '.' + parts[1] : '';
    
    integerPart = integerPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    
    return (isNegative ? '-' : '') + integerPart + decimalPart;
}

function updateCalcDisplay() {
    var display = calcExpression;
    
    if (calcResultShown) {
        display = formatNumberWithCommas(calcExpression);
    } else {
        var parts = display.split(/([+\-*/×÷−])/);
        var formattedParts = parts.map(function(part) {
            if (['+', '-', '*', '/', '×', '÷', '−'].includes(part)) {
                return part;
            }
            return formatNumberWithCommas(part);
        });
        display = formattedParts.join('');
    }
    
    document.getElementById('calcDisplay').textContent = display;
}

function openCalc(btn) {
    var row = btn.closest('.item-row');
    calcTargetInput = row.querySelector('.daily-input');
    
    var currentVal = parseNum(calcTargetInput.value);
    calcExpression = currentVal > 0 ? currentVal.toString() : '0';
    calcResultShown = false;
    updateCalcDisplay();
    
    document.getElementById('calcModal').style.display = 'flex';
    document.addEventListener('keydown', handleCalcKeyboard);
}

function closeCalc() {
    document.getElementById('calcModal').style.display = 'none';
    calcTargetInput = null;
    calcExpression = '0';
    calcResultShown = false;
    document.removeEventListener('keydown', handleCalcKeyboard);
}

function calcInput(val) {
    if (calcResultShown) {
        if ('0123456789.'.includes(val)) {
            calcExpression = val;
        } else {
            calcExpression += val;
        }
        calcResultShown = false;
    } else {
        if (calcExpression === '0' && '0123456789'.includes(val)) {
            calcExpression = val;
        } else if (calcExpression === '0' && val === '.') {
            calcExpression = '0.';
        } else {
            calcExpression += val;
        }
    }
    updateCalcDisplay();
}

function calcClear() {
    calcExpression = '0';
    calcResultShown = false;
    updateCalcDisplay();
}

function calcBack() {
    if (calcResultShown) {
        calcClear();
        return;
    }
    if (calcExpression.length > 1) {
        calcExpression = calcExpression.slice(0, -1);
    } else {
        calcExpression = '0';
    }
    updateCalcDisplay();
}

function calcEqual() {
    try {
        var expr = calcExpression
            .replace(/,/g, '')
            .replace(/×/g, '*')
            .replace(/÷/g, '/')
            .replace(/−/g, '-');
        
        if (/[^0-9+\-*/().]/.test(expr.replace(/\s/g, ''))) {
            throw new Error('Invalid');
        }
        
        var result = eval(expr);
        if (!isFinite(result)) {
            calcExpression = 'خطا';
        } else {
            result = Math.round(result * 100) / 100;
            calcExpression = result.toString();
        }
    } catch(e) {
        calcExpression = 'خطا';
    }
    calcResultShown = true;
    updateCalcDisplay();
}

function applyCalc() {
    if (!calcTargetInput) return;
    
    var cleanExpression = calcExpression.replace(/,/g, '');
    var finalVal = parseFloat(cleanExpression);
    
    if (isNaN(finalVal) || !isFinite(finalVal)) {
        showToast('⚠️ مقدار نامعتبر', 'error');
        return;
    }
    
    calcTargetInput.value = finalVal.toLocaleString('en-US');
    updateGrams();
    debounceSave();
    
    closeCalc();
    showToast('✓ عدد در فیلد قرار گرفت', 'success');
}

function handleCalcKeyboard(e) {
    var modal = document.getElementById('calcModal');
    if (modal.style.display !== 'flex') return;
    
    var key = e.key;
    
    if (['0','1','2','3','4','5','6','7','8','9','+','-','*','/','.','Enter','Backspace','Delete','Escape','='].includes(key)) {
        e.preventDefault();
    }
    
    if (/^[0-9.]$/.test(key)) {
        calcInput(key);
    }
    else if (key === '+') calcInput('+');
    else if (key === '-') calcInput('-');
    else if (key === '*') calcInput('*');
    else if (key === '/') calcInput('/');
    else if (key === 'Enter' || key === '=') {
        calcEqual();
    }
    else if (key === 'Backspace') {
        calcBack();
    }
    else if (key === 'Delete' || key === 'c' || key === 'C') {
        calcClear();
    }
    else if (key === 'Escape') {
        closeCalc();
    }
}

document.getElementById('calcModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeCalc();
    }
});

// ========== ⭐ DatePicker + Initial Load ==========
$(document).ready(function() {
    $('#datepicker').persianDatepicker({
        format: 'YYYY-MM-DD',
        autoClose: true,
        onSelect: function(date) {
            window.location.href = 'index.php?date=' + date + '<?php echo $branch_id != $user_id ? '&branch_id='.$branch_id : ''; ?>';
        }
    });
    
    // ⭐ محاسبه جمع کل با تأخیر برای اطمینان از لود کامل داده‌ها
    setTimeout(updateGrams, 150);
});
</script>
</body>
</html>