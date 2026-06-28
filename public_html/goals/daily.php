<?php
ob_start();
define('SECURE_ACCESS', true);
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/jdf.php';
requireLogin();

$role = $_SESSION['role'];
$user_id = (int)$_SESSION['user_id'];
$is_admin = ($role === 'admin');
$is_observer = ($role === 'observer');
$can_edit = false;
$branch_id = $user_id;
$branches = [];

if ($is_admin) {
    $can_edit = true;
    $q = mysqli_query($conn, "SELECT id, branch_name FROM users WHERE role='branch' ORDER BY branch_name");
    while ($b = mysqli_fetch_assoc($q)) $branches[] = $b;
    $branch_id = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : ($branches[0]['id'] ?? 0);
} elseif ($is_observer) {
    $stmt = mysqli_prepare($conn, "SELECT u.id, u.branch_name FROM observer_assignments oa JOIN users u ON oa.branch_id = u.id WHERE oa.observer_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($b = mysqli_fetch_assoc($res)) $branches[] = $b;
    $branch_id = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : ($branches[0]['id'] ?? 0);
} else {
    $can_edit = true;
}

$selected_date = isset($_GET['date']) ? $_GET['date'] : jdate('Y-m-d');
$selected_date = str_replace('/', '-', $selected_date);
$selected_date = str_replace(
    ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'],
    ['0','1','2','3','4','5','6','7','8','9'],
    $selected_date
);

$branch_name = '';
$stmt = mysqli_prepare($conn, "SELECT branch_name FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $branch_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
if ($res && mysqli_num_rows($res) > 0) $branch_name = mysqli_fetch_assoc($res)['branch_name'];

// ذخیره داده‌ها
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    header('Content-Type: application/json; charset=utf-8');
    $date = $_POST['date'];
    $items = json_decode($_POST['items'] ?? '[]', true);
    
    $stmt = mysqli_prepare($conn, "DELETE FROM goal_daily_progress WHERE branch_id = ? AND progress_date = ?");
    mysqli_stmt_bind_param($stmt, "is", $branch_id, $date);
    mysqli_stmt_execute($stmt);
    
    $count = 0;
    $stmt = mysqli_prepare($conn, "INSERT INTO goal_daily_progress (branch_id, goal_type_id, achieved_value, progress_date, created_by) VALUES (?, ?, ?, ?, ?)");
    foreach ($items as $item) {
        $gid = (int)$item['id'];
        $val = (float)$item['value'];
        if ($gid > 0 && $val != 0) {
            mysqli_stmt_bind_param($stmt, "iidsi", $branch_id, $gid, $val, $date, $user_id);
            if (mysqli_stmt_execute($stmt)) $count++;
        }
    }
    require_once __DIR__ . '/../includes/reminder_functions.php';
    clearReminderAfterSubmit($branch_id, $date, 'پیشرفت اهداف');
    
    echo json_encode(['success' => true, 'message' => "$count مورد ثبت شد"]);
    exit;
}

// دریافت آیتم‌های هدف
$items = [];
$q = mysqli_query($conn, "SELECT * FROM goal_types WHERE is_active = 1 ORDER BY sort_order");
while ($r = mysqli_fetch_assoc($q)) $items[] = $r;

// دریافت داده‌های ثبت‌شده
$today_data = [];
$stmt = mysqli_prepare($conn, "SELECT goal_type_id, achieved_value FROM goal_daily_progress WHERE branch_id = ? AND progress_date = ?");
mysqli_stmt_bind_param($stmt, "is", $branch_id, $selected_date);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) $today_data[$row['goal_type_id']] = $row['achieved_value'];

// یافتن بازه فعال
$activeGoals = [];
$periodLabel = 'هدفی برای این بازه تعریف نشده';
$active_start = $active_end = null;

$q = mysqli_query($conn, "SELECT goal_type_id, target_value, start_date, end_date FROM branch_goals WHERE branch_id = $branch_id AND start_date <= '$selected_date' AND end_date >= '$selected_date' LIMIT 1");
if ($row = mysqli_fetch_assoc($q)) {
    $active_start = $row['start_date'];
    $active_end = $row['end_date'];
    $periodLabel = "دوره: {$active_start} تا {$active_end}";
    $q2 = mysqli_query($conn, "SELECT goal_type_id, target_value FROM branch_goals WHERE branch_id = $branch_id AND start_date = '$active_start'");
    while ($r = mysqli_fetch_assoc($q2)) $activeGoals[$r['goal_type_id']] = $r['target_value'];
}

// محاسبه پیشرفت تجمعی
$periodProgress = [];
foreach ($items as $item) {
    $pid = (int)$item['id'];
    if (isset($activeGoals[$pid]) && $active_start && $active_end) {
        $total_q = mysqli_query($conn, "SELECT SUM(achieved_value) as total FROM goal_daily_progress WHERE branch_id = $branch_id AND goal_type_id = $pid AND progress_date BETWEEN '$active_start' AND '$active_end'");
        $t_row = mysqli_fetch_assoc($total_q);
        $periodProgress[$pid] = $t_row['total'] ?? 0;
    }
}

$theme = $_COOKIE['theme'] ?? 'dark';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>ثبت پیشرفت | <?= htmlspecialchars($branch_name) ?></title>
    <link href="../assets/fonts/fonts.css" rel="stylesheet">
    <link rel="icon" href="../assets/images/logo.png">
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
            --green: #10b981;
            --green-bg: rgba(16,185,129,0.1);
            --red: #f87171;
            --red-bg: rgba(248,113,113,0.1);
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
            --green: #059669;
            --green-bg: rgba(5,150,105,0.08);
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
        .app-title svg { width: 22px; height: 22px; flex-shrink: 0; }
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
        .theme-btn:hover { background: var(--accent-bg); border-color: var(--accent); }
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
        .logout-btn:hover { background: var(--accent-bg); color: var(--accent); }
        
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
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .panel-title svg { width: 18px; height: 18px; }
        
        .period-badge {
            font-size: 0.7rem;
            color: var(--text-2);
            margin-right: auto;
            font-weight: 400;
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
        .date-badge-display {
            padding: 8px 16px;
            background: var(--accent-bg);
            border: 1px solid var(--accent);
            border-radius: 8px;
            color: var(--accent);
            font-family: 'Vazirmatn';
            font-size: 0.85rem;
            font-weight: 600;
        }
        .back-link {
            color: var(--accent);
            text-decoration: none;
            background: var(--accent-bg);
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 600;
            border: 1px solid var(--accent);
            transition: all 0.2s;
            margin-right: auto;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .back-link:hover { background: var(--accent); color: #1a1a1a; }
        .back-link svg { width: 14px; height: 14px; }
        
        .icard {
            border-radius: var(--radius-sm);
            border: 1px solid var(--border);
            overflow: hidden;
            background: var(--bg-input);
            margin-bottom: 16px;
        }
        .icard-head {
            padding: 10px 14px;
            background: var(--purple-bg);
            color: var(--purple);
            font-weight: 700;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 6px;
            justify-content: space-between;
            flex-wrap: wrap;
        }
        .icard-head svg { width: 16px; height: 16px; }
        .icard-body { padding: 12px; }
        
        .target-tag {
            font-size: 0.65rem;
            background: var(--accent-bg);
            color: var(--accent);
            padding: 3px 8px;
            border-radius: 12px;
        }
        
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
        .item-row .progress-info {
            flex: 0.9;
            font-size: 0.65rem;
            color: var(--green);
            text-align: center;
            direction: ltr;
        }
        
        .btn-save {
            background: linear-gradient(135deg, var(--accent), #f59e0b);
            color: #1a1a1a;
            border: none;
            padding: 14px 20px;
            border-radius: var(--radius-sm);
            font-weight: 800;
            cursor: pointer;
            width: 100%;
            margin-top: 16px;
            font-family: 'Vazirmatn';
            font-size: 0.95rem;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-save:hover { opacity: 0.9; transform: translateY(-2px); box-shadow: 0 4px 15px rgba(212,175,55,0.3); }
        .btn-save svg { width: 18px; height: 18px; }
        
        .no-access {
            text-align: center;
            padding: 20px;
            background: var(--bg-input);
            border-radius: var(--radius-sm);
            color: var(--text-2);
            font-weight: 600;
            border: 1px solid var(--border);
            margin-top: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
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
        
        /* تب‌های سوییچ */
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
        .tab-switch-item:hover { color: var(--text-1); background: rgba(255,255,255,0.03); }
        .tab-switch-item.active { background: var(--accent); color: #fff; font-weight: 700; }
    </style>
</head>
<body class="<?= $theme ?>">
<div class="wrapper">
    <div class="app-header">
        <div class="app-title">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
            </svg>
            <span>ثبت پیشرفت | <?= htmlspecialchars($branch_name) ?></span>
            <?php if (!$can_edit): ?><span class="badge">مشاهده</span><?php endif; ?>
        </div>
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <?php if ($is_admin || $is_observer): ?>
            <select onchange="location.href='daily.php?branch_id='+this.value+'&date=<?= $selected_date ?>'">
                <?php foreach ($branches as $b): ?>
                <option value="<?= $b['id'] ?>" <?= $b['id'] == $branch_id ? 'selected' : '' ?>>
                    <?= htmlspecialchars($b['branch_name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
            <button class="theme-btn" onclick="toggleTheme()" id="themeToggle">
                <?= $theme == 'light' ? 
                    '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>' : 
                    '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>'; 
                ?>
            </button>
            <a href="../index.php" class="logout-btn">
                <svg style="width:14px;height:14px;vertical-align:middle;margin-left:4px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
                    <polyline points="10 17 15 12 10 7"></polyline>
                    <line x1="15" y1="12" x2="3" y2="12"></line>
                </svg>
                بازگشت
            </a>
        </div>
    </div>
    
    <!-- تب‌ها -->
    <div class="tab-switch">
        <a href="../user/index.php?date=<?= $selected_date ?>" class="tab-switch-item">تراز روزانه</a>
        <a href="../income/index.php?date=<?= $selected_date ?>" class="tab-switch-item">درآمد روزانه</a>
        <a href="../income/monthly.php?date=<?= $selected_date ?><?= isset($_GET['branch_id']) ? '&branch_id='.$_GET['branch_id'] : '' ?>" class="tab-switch-item">درآمد ماهانه</a>
        <a href="../goals/daily.php?date=<?= $selected_date ?>" class="tab-switch-item active">ثبت پیشرفت</a>
    </div>

    <div class="toast-container" id="toastContainer"></div>
    
    <div class="panel">
        <div class="panel-body">
            <div class="panel-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                </svg>
                پیشرفت اهداف
                <span class="period-badge"><?= $periodLabel ?></span>
            </div>

            <div class="date-bar">
                <svg style="width:16px;height:16px;flex-shrink:0;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
                <span class="date-badge-display"><?= $selected_date ?></span>
                
            </div>

            <?php $hasItems = false; foreach ($items as $item): 
                $cv = $today_data[$item['id']] ?? 0;
                $target = $activeGoals[$item['id']] ?? 0;
                if ($target <= 0) continue;
                $hasItems = true;
                $unitText = $item['unit'] == 'gram' ? 'گرم' : 'ریال';
                $total = $periodProgress[$item['id']] ?? 0;
                $isGram = ($item['unit'] == 'gram');
                
                if ($isGram) {
                    $displayValue = ($cv != 0) ? number_format($cv, 3, '.', '') : '';
                    $displayTarget = number_format($target, 3, '.', '');
                    $displayTotal = number_format($total, 3, '.', '');
                } else {
                    $displayValue = ($cv != 0) ? number_format($cv) : '';
                    $displayTarget = number_format($target);
                    $displayTotal = number_format($total);
                }
            ?>
            <div class="icard">
                <div class="icard-head">
                    <span style="display:flex;align-items:center;gap:6px;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                        </svg>
                        <?= htmlspecialchars($item['name']) ?>
                    </span>
                    <span class="target-tag">هدف: <?= $displayTarget ?> <?= $unitText ?></span>
                </div>
                <div class="icard-body">
                    <div class="item-row">
                        <span class="item-name">مقدار امروز</span>
                        <input type="text" 
                               class="goal-input <?= $isGram ? 'gram-input' : '' ?>" 
                               data-id="<?= $item['id'] ?>" 
                               data-unit="<?= $item['unit'] ?>" 
                               value="<?= $displayValue ?>" 
                               placeholder="<?= $isGram ? 'مثال: 4.333' : 'مقدار پیشرفت' ?>"
                               <?= $can_edit ? '' : 'readonly disabled' ?>>
                        <span class="progress-info">
                            تجمعی: <?= $displayTotal ?> <?= $unitText ?>
                        </span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            
            <?php if (!$hasItems): ?>
            <div class="no-access">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
                هدفی برای این بازه تعریف نشده است
            </div>
            <?php endif; ?>

            <?php if ($can_edit && $hasItems): ?>
            <button class="btn-save" onclick="saveData()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                    <polyline points="17 21 17 13 7 13 7 21"></polyline>
                    <polyline points="7 3 7 8 15 8"></polyline>
                </svg>
                ذخیره پیشرفت
            </button>
            <?php elseif (!$can_edit && $hasItems): ?>
            <div class="no-access">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                </svg>
                فقط مشاهده - دسترسی ثبت ندارید
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function toggleTheme() {
    var b = document.body, btn = document.getElementById('themeToggle');
    if (b.classList.contains('light')) {
        b.classList.remove('light');
        btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>';
        document.cookie = "theme=dark;path=/;max-age=" + (365*24*60*60);
    } else {
        b.classList.add('light');
        btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>';
        document.cookie = "theme=light;path=/;max-age=" + (365*24*60*60);
    }
}

function showToast(msg, type) {
    type = type || 'success';
    var c = document.getElementById('toastContainer');
    var t = document.createElement('div');
    t.className = 'toast ' + type;
    t.textContent = msg;
    c.appendChild(t);
    setTimeout(function() { t.remove(); }, 2000);
}

function parseNum(s) {
    s = String(s || '');
    s = s.replace(/[۰-۹]/g, function(d) { return '۰۱۲۳۴۵۶۷۸۹'.indexOf(d); });
    s = s.replace(/,/g, '');
    s = s.replace(/[^\d.-]/g, '');
    return parseFloat(s) || 0;
}

function formatGramInput(el) {
    var raw = el.value.replace(/[^\d.-]/g, '');
    var isNegative = false;
    if (raw.startsWith('-')) { isNegative = true; raw = raw.substring(1); }
    var parts = raw.split('.');
    if (parts.length > 2) raw = parts[0] + '.' + parts.slice(1).join('');
    if (parts.length === 2 && parts[1].length > 3) raw = parts[0] + '.' + parts[1].substring(0, 3);
    el.value = isNegative ? '-' + raw : raw;
}

function saveData() {
    if (!<?= json_encode($can_edit) ?>) { showToast('دسترسی ثبت ندارید', 'error'); return; }
    
    var date = '<?= $selected_date ?>';
    var items = [];
    
    document.querySelectorAll('.goal-input').forEach(function(inp) {
        var v = parseNum(inp.value);
        if (v != 0) items.push({ id: parseInt(inp.dataset.id), value: v });
    });
    
    if (items.length === 0) { showToast('مقداری وارد نشده', 'error'); return; }
    
    fetch('daily.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'save=1&date=' + encodeURIComponent(date) + '&items=' + encodeURIComponent(JSON.stringify(items))
    })
    .then(function(r) { return r.json(); })
    .then(function(d) {
        if (d.success) {
            showToast(d.message, 'success');
            setTimeout(function() { location.reload(); }, 1000);
        } else {
            showToast('خطا: ' + (d.error || d.message), 'error');
        }
    })
    .catch(function() { showToast('خطای ارتباط با سرور', 'error'); });
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.goal-input').forEach(function(inp) {
        var isGram = (inp.dataset.unit === 'gram');
        if (!inp.disabled) {
            if (isGram) {
                inp.addEventListener('input', function() { formatGramInput(this); });
            } else {
                inp.addEventListener('input', function() {
                    var c = this.selectionStart;
                    var b = this.value.substring(0, c);
                    var rv = this.value.replace(/,/g, '').replace(/\D/g, '');
                    this.value = rv.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                    var newPos = rv.substring(0, b.replace(/,/g, '').length).replace(/\B(?=(\d{3})+(?!\d))/g, ',').length;
                    this.setSelectionRange(newPos, newPos);
                });
                inp.addEventListener('blur', function() {
                    if (this.value) this.value = this.value.replace(/,/g, '').replace(/\D/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                });
            }
        }
    });
});
</script>
</body>
</html>