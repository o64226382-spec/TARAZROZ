<?php
define('SECURE_ACCESS', true);
require_once '../includes/config.php';
require_once '../includes/auth.php';

requireAdmin();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}


if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function isOnline($last_activity) {
    if (!$last_activity) return false;
    $last = strtotime($last_activity);
    $now = time();
    return floor(($now - $last) / 60) < 5;
}

// ==================== دریافت کاربران ====================
$users = [];
$result = mysqli_query($conn, "SELECT id, username, branch_name, role, created_at, last_activity FROM users WHERE role != 'admin' ORDER BY role, branch_name");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $users[] = $row;
    }
}

// ==================== دریافت ماه‌های دارای گزارش ====================
$months = [];
$mres = mysqli_query($conn, "SELECT DISTINCT DATE_FORMAT(report_date, '%Y/%m') as m FROM daily_reports ORDER BY m DESC");
if ($mres) {
    while ($row = mysqli_fetch_assoc($mres)) {
        $months[] = $row['m'];
    }
}

$selected_month = $_GET['month'] ?? ($months[0] ?? '');
$selected_branch = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : 0;

// ==================== دریافت گزارش‌های ماه ====================
$branch_report = null;
$branch_name = '';
$branch_stats = ['debtors' => 0, 'creditors' => 0, 'petty' => 0, 'difference' => 0];

if ($selected_branch > 0 && $selected_month) {
    $branch_res = mysqli_query($conn, "SELECT branch_name FROM users WHERE id = $selected_branch AND role = 'branch'");
    if ($branch_res && mysqli_num_rows($branch_res) > 0) {
        $branch = mysqli_fetch_assoc($branch_res);
        $branch_name = $branch['branch_name'];
        
        $reports_query = "SELECT report_date, report_data FROM daily_reports 
                          WHERE user_id = $selected_branch 
                          AND DATE_FORMAT(report_date, '%Y/%m') = '$selected_month'
                          ORDER BY report_date ASC";
        $rep_res = mysqli_query($conn, $reports_query);
        $branch_report = [];
        if ($rep_res) {
            while ($rep = mysqli_fetch_assoc($rep_res)) {
                $data = json_decode($rep['report_data'], true);
                $branch_report[$rep['report_date']] = $data;
                $branch_stats['debtors'] += array_sum(array_column($data['debtors'] ?? [], 'amt'));
                $branch_stats['creditors'] += array_sum(array_column($data['creditors'] ?? [], 'amt'));
                $branch_stats['petty'] += array_sum(array_column($data['pettys'] ?? [], 'amt'));
            }
            $branch_stats['difference'] = $branch_stats['debtors'] - $branch_stats['creditors'];
        }
    }
}

// ==================== آمار کل همه شعب ====================
$all_branches_stats = [];
$total_all_debtors = 0;
$total_all_creditors = 0;
$total_all_petty = 0;

$branches_res = mysqli_query($conn, "SELECT id, branch_name FROM users WHERE role = 'branch' ORDER BY branch_name");
if ($branches_res) {
    while ($branch = mysqli_fetch_assoc($branches_res)) {
        $stats = ['debtors' => 0, 'creditors' => 0, 'petty' => 0, 'difference' => 0, 'name' => $branch['branch_name'], 'id' => $branch['id']];
        $reports_res = mysqli_query($conn, "SELECT report_date, report_data FROM daily_reports WHERE user_id = {$branch['id']}");
        if ($reports_res) {
            while ($rep = mysqli_fetch_assoc($reports_res)) {
                $data = json_decode($rep['report_data'], true);
                $stats['debtors'] += array_sum(array_column($data['debtors'] ?? [], 'amt'));
                $stats['creditors'] += array_sum(array_column($data['creditors'] ?? [], 'amt'));
                $stats['petty'] += array_sum(array_column($data['pettys'] ?? [], 'amt'));
            }
        }
        $stats['difference'] = $stats['debtors'] - $stats['creditors'];
        $all_branches_stats[] = $stats;
        $total_all_debtors += $stats['debtors'];
        $total_all_creditors += $stats['creditors'];
        $total_all_petty += $stats['petty'];
    }
}
$total_all_difference = $total_all_debtors - $total_all_creditors;

// ==================== ناظرها و شعب ====================
$observers = [];
$obs_res = mysqli_query($conn, "SELECT id, username, branch_name FROM users WHERE role = 'observer'");
if ($obs_res) { while ($o = mysqli_fetch_assoc($obs_res)) $observers[] = $o; }

$branches_list = [];
$br_res = mysqli_query($conn, "SELECT id, branch_name FROM users WHERE role = 'branch'");
if ($br_res) { while ($b = mysqli_fetch_assoc($br_res)) $branches_list[] = $b; }

$assignments = [];
$ares = mysqli_query($conn, "SELECT oa.id, o.username as observer, b.branch_name as branch 
    FROM observer_assignments oa 
    JOIN users o ON oa.observer_id = o.id 
    JOIN users b ON oa.branch_id = b.id");
if ($ares) { while ($row = mysqli_fetch_assoc($ares)) $assignments[] = $row; }

function fmt_num($amount) {
    return number_format((float)$amount, 1);
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>پنل مدیریت | تراز روزانه</title>
    
    <link href="../assets/fonts/fonts.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="192x192" href="../assets/images/logo.png">
    <link rel="apple-touch-icon" href="../assets/images/logo.png">
    <link href="assets/css/dynamic-theme.php" rel="stylesheet">
    <link href="../assets/css/light-theme.css" rel="stylesheet">
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <style>
        :root {
            --bg: #0a0f1a;
            --surface: rgba(255,255,255,0.025);
            --border: rgba(255,255,255,0.05);
            --text: #e8ecf1;
            --text-secondary: #8899aa;
            --accent: #4b8cf7;
            --green: #10b981;
            --red: #ef4444;
            --purple: #8b5cf6;
            --radius: 14px;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Vazirmatn', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            padding: 12px;
            background-image: 
                radial-gradient(ellipse at 20% 40%, rgba(75,140,247,0.04) 0%, transparent 60%),
                radial-gradient(ellipse at 80% 60%, rgba(139,92,246,0.03) 0%, transparent 60%);
        }
        .container { max-width: 1300px; margin: 0 auto; display: flex; flex-direction: column; gap: 12px; }

        .header {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: var(--radius); padding: 14px 18px;
            display: flex; align-items: center; justify-content: space-between;
            gap: 12px; flex-wrap: wrap; backdrop-filter: blur(12px);
        }
        .header h2 { font-size: 1.1rem; font-weight: 700; }
        .logout-btn { background: rgba(255,255,255,0.04); border: 1px solid var(--border); color: var(--text); padding: 6px 14px; border-radius: 8px; text-decoration: none; font-size: 0.8rem; }
        .logout-btn:hover { background: rgba(255,255,255,0.06); }

        .bottom-nav {
            position: fixed; bottom: 12px; left: 50%; transform: translateX(-50%);
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 30px; display: flex; gap: 4px; padding: 6px;
            backdrop-filter: blur(16px); z-index: 1000;
        }
        .nav-item {
            padding: 8px 16px; border-radius: 24px; font-size: 0.75rem;
            font-weight: 600; cursor: pointer; color: var(--text-secondary);
            transition: all 0.2s; white-space: nowrap;
        }
        .nav-item.active { background: var(--accent); color: white; }

        .view-container { display: none; }
        .view-container.active { display: block; }

        .card {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: var(--radius); padding: 16px; margin-bottom: 12px;
            backdrop-filter: blur(8px);
        }
        .card-title {
            font-size: 0.9rem; font-weight: 700; margin-bottom: 12px;
            padding-bottom: 8px; border-bottom: 1px solid var(--border);
            color: var(--accent);
        }

        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 8px; margin-bottom: 12px; }
        .stat-card { background: rgba(255,255,255,0.02); border: 1px solid var(--border); border-radius: 10px; padding: 12px; text-align: center; }
        .stat-card h4 { font-size: 0.7rem; color: var(--text-secondary); margin-bottom: 4px; }
        .stat-card .value { font-size: 1rem; font-weight: 700; }
        .stat-card.debtors .value { color: var(--red); }
        .stat-card.creditors .value { color: var(--green); }

        table { width: 100%; border-collapse: collapse; font-size: 0.73rem; }
        th, td { border: 1px solid rgba(255,255,255,0.04); padding: 8px 6px; text-align: center; }
        th { background: rgba(255,255,255,0.02); font-weight: 600; font-size: 0.7rem; color: var(--text-secondary); }
        .sticky-header th { position: sticky; top: 0; background: #111827; z-index: 2; }

        .online { color: var(--green); background: rgba(16,185,129,0.1); display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 0.65rem; }
        .offline { color: var(--red); background: rgba(239,68,68,0.1); display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 0.65rem; }

        .btn { padding: 6px 10px; border-radius: 6px; border: none; cursor: pointer; font-family: 'Vazirmatn'; font-size: 0.7rem; margin: 2px; color: white; }
        .btn-primary { background: var(--accent); }
        .btn-danger { background: var(--red); }
        .btn-warning { background: #f59e0b; }
        .btn-success { background: var(--green); }

        .toast-container { position: fixed; bottom: 80px; right: 20px; z-index: 9999; display: flex; flex-direction: column; gap: 6px; }
        .toast { padding: 10px 16px; border-radius: 10px; color: white; font-size: 0.78rem; animation: slideUp 0.3s ease; }
        .toast.success { background: #10b981; }
        .toast.error { background: #ef4444; }
        @keyframes slideUp { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }

        @media (max-width: 600px) {
            .bottom-nav { width: 95%; overflow-x: auto; justify-content: flex-start; }
            .nav-item { font-size: 0.65rem; padding: 6px 10px; }
            th, td { font-size: 0.6rem; padding: 5px 3px; }
        }
    </style>
    <link href="../assets/css/light-theme.css" rel="stylesheet">
</head>
<body>
<div class="container">
    <div class="toast-container" id="toastContainer"></div>

    <div class="header">
        <h2>⚙️ پنل مدیریت کل</h2>
        <a href="../logout.php" class="logout-btn">🚪 خروج</a>
    </div>

    <div class="bottom-nav">
        <div class="nav-item active" data-view="users">👥 کاربران</div>
        <div class="nav-item" data-view="stats">📊 آمار شعبه</div>
        <a href="dynamic_items.php" class="nav-item">📦 آیتم‌های داینامیک</a>
        <a href="full_report.php" class="nav-item">📦 امار اصلی</a>
        <a href="rubika.php" class="nav-item">🔔 روبیکا</a>
        <a href="admin/work_hours.php">⚙️ تنظیمات ساعات کاری</a>
        <a href="notification_channels.php" class="nav-item">📡 کانال‌ها</a>
        <div class="nav-item" data-view="all-stats">🏢 کل شعب</div>
        <a href="test_bale.php" class="nav-item">🧪 تست بله</a>
        <div class="nav-item" data-view="settings">⚙️ تنظیمات</div>
        <a href="tools.php" class="nav-item">🔧 ابزارها</a>
        <a href="salary_admin.php" class="nav-item">💰 فیش حقوقی</a>
        <a href="reminders.php">⚙️ تنظیمات هشدار</a>
        <a href="ai_chat.php" class="nav-item">🤖 دستیار</a>
        <a href="income_items.php" class="nav-item">📋 آیتم‌های درآمد</a>
        <a href="theme.php" class="nav-item">🎨 تم</a>
        <a href="asset_items.php" class="nav-item">📦 آیتم‌های دارایی</a>
        <a href="income_stats.php" class="nav-item">📊 آمار درآمد</a>
        <a href="dynamic_stats.php" class="nav-item">📊 آمار  داینامیک</a>
        <a href="goal_items.php" class="nav-item">📊 آمار  داینامیک</a>
        <li class="nav-item">
    <a href="goals/index.php" class="nav-link">
        <span>🎯</span> <span>مدیریت اهداف شعب</span>
    </a>
</li>
        <button class="nav-item" id="adminThemeToggle" onclick="toggleAdminTheme()">🌙</button>
    </div>

    <!-- تب ۱: کاربران -->
    <div id="view-users" class="view-container active">
        <div class="card">
            <div class="card-title">👥 لیست کاربران</div>
            <div style="overflow-x: auto;">
                <table class="sticky-header">
                    <thead><tr><th>#</th><th>نام کاربری</th><th>نام شعبه/ناظر</th><th>نقش</th><th>وضعیت</th><th>آخرین فعالیت</th><th>تاریخ عضویت</th><th>عملیات</th></tr></thead>
                    <tbody>
                        <?php if(empty($users)): ?>
                            <tr><td colspan="8">هیچ کاربری یافت نشد.</td></tr>
                        <?php else: ?>
                            <?php foreach($users as $i=>$u): ?>
                            <tr>
                                <td><?php echo $i+1; ?></td>
                                <td><?php echo htmlspecialchars($u['username']); ?></td>
                                <td><?php echo htmlspecialchars($u['branch_name']); ?></td>
                                <td><?php echo $u['role'] == 'branch' ? '🏢 شعبه' : '👁️ ناظر'; ?></td>
                                <td><span class="<?php echo isOnline($u['last_activity']) ? 'online' : 'offline'; ?>"><?php echo isOnline($u['last_activity']) ? '🟢 آنلاین' : '🔴 آفلاین'; ?></span></td>
                                <td><?php echo $u['last_activity'] ? date('H:i - Y/m/d', strtotime($u['last_activity'])) : 'نامشخص'; ?></td>
                                <td><?php echo date('Y/m/d', strtotime($u['created_at'])); ?></td>
                                <td>
                                    <button class="btn btn-primary" onclick="editUser(<?php echo $u['id']; ?>, '<?php echo addslashes($u['branch_name']); ?>', '<?php echo $u['role']; ?>')">✏️</button>
                                    <button class="btn btn-warning" onclick="resetPass(<?php echo $u['id']; ?>)">🔑</button>
                                    <?php if($u['role'] != 'admin'): ?>
                                    <button class="btn btn-danger" onclick="deleteUser(<?php echo $u['id']; ?>)">🗑️</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- تب ۲: آمار شعبه -->
    <div id="view-stats" class="view-container">
        <div class="card">
            <div class="card-title">📊 آمار یک شعبه</div>
            <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 12px;">
                <select id="branchStatSelect" style="padding:8px;border-radius:8px;border:1px solid var(--border);background:rgba(255,255,255,0.03);color:var(--text);flex:1;">
                    <option value="0">-- انتخاب شعبه --</option>
                    <?php foreach($branches_list as $b): ?>
                        <option value="<?php echo $b['id']; ?>" <?php echo $selected_branch == $b['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($b['branch_name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <select id="monthStatSelect" style="padding:8px;border-radius:8px;border:1px solid var(--border);background:rgba(255,255,255,0.03);color:var(--text);">
                    <?php foreach($months as $m): ?>
                        <option value="<?php echo $m; ?>" <?php echo $selected_month == $m ? 'selected' : ''; ?>><?php echo $m; ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-primary" onclick="viewBranchStats()">مشاهده</button>
            </div>
            
            <?php if($selected_branch > 0 && $branch_report): ?>
                <div class="stats-grid">
                    <div class="stat-card debtors"><h4>مجموع بدهکاران</h4><div class="value"><?php echo fmt_num($branch_stats['debtors']); ?> میلیون</div></div>
                    <div class="stat-card creditors"><h4>مجموع بستانکاران</h4><div class="value"><?php echo fmt_num($branch_stats['creditors']); ?> میلیون</div></div>
                    <div class="stat-card"><h4>اختلاف کل</h4><div class="value" style="color:var(--accent);"><?php echo fmt_num($branch_stats['difference']); ?> میلیون</div></div>
                    <div class="stat-card"><h4>تنخواه</h4><div class="value" style="color:var(--purple);"><?php echo fmt_num($branch_stats['petty']); ?> میلیون</div></div>
                </div>
                
                <h4 style="margin:12px 0;font-size:0.85rem;">گزارش‌های <?php echo $selected_month; ?> - <?php echo htmlspecialchars($branch_name); ?></h4>
                <?php foreach($branch_report as $date => $data): ?>
                <div style="background:rgba(255,255,255,0.02);border:1px solid var(--border);border-radius:10px;padding:12px;margin-bottom:10px;">
                    <h4 style="margin-bottom:8px;">📄 <?php echo str_replace('-', '/', $date); ?></h4>
                    <?php if(!empty($data['debtors'])): ?>
                    <p style="font-size:0.75rem;color:var(--red);font-weight:600;">بدهکاران</p>
                    <table><thead><tr><th>#</th><th>نام</th><th>مبلغ (میلیون)</th></tr></thead>
                        <tbody><?php $i=1; foreach($data['debtors'] as $d): ?><tr><td><?php echo $i++; ?></td><td><?php echo htmlspecialchars($d['name']); ?></td><td style="color:var(--red);"><?php echo fmt_num($d['amt']); ?></td></tr><?php endforeach; ?></tbody>
                    </table>
                    <?php endif; ?>
                    <?php if(!empty($data['creditors'])): ?>
                    <p style="font-size:0.75rem;color:var(--green);font-weight:600;margin-top:8px;">بستانکاران</p>
                    <table><thead><tr><th>#</th><th>نام</th><th>مبلغ (میلیون)</th></tr></thead>
                        <tbody><?php $i=1; foreach($data['creditors'] as $c): ?><tr><td><?php echo $i++; ?></td><td><?php echo htmlspecialchars($c['name']); ?></td><td style="color:var(--green);"><?php echo fmt_num($c['amt']); ?></td></tr><?php endforeach; ?></tbody>
                    </table>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php elseif($selected_branch > 0): ?>
                <p style="color:var(--text-secondary);">گزارشی برای این شعبه در ماه <?php echo $selected_month; ?> یافت نشد.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- تب ۳: آمار کل شعب -->
    <div id="view-all-stats" class="view-container">
        <div class="card">
            <div class="card-title">🏢 آمار کل همه شعب</div>
            <div class="stats-grid">
                <div class="stat-card debtors"><h4>مجموع بدهکاران</h4><div class="value"><?php echo fmt_num($total_all_debtors); ?> میلیون</div></div>
                <div class="stat-card creditors"><h4>مجموع بستانکاران</h4><div class="value"><?php echo fmt_num($total_all_creditors); ?> میلیون</div></div>
                <div class="stat-card"><h4>اختلاف کل</h4><div class="value" style="color:var(--accent);"><?php echo fmt_num($total_all_difference); ?> میلیون</div></div>
                <div class="stat-card"><h4>تنخواه</h4><div class="value" style="color:var(--purple);"><?php echo fmt_num($total_all_petty); ?> میلیون</div></div>
            </div>
            
            <div style="overflow-x:auto;margin-top:12px;">
                <table class="sticky-header">
                    <thead><tr><th>#</th><th>نام شعبه</th><th>بدهکاران</th><th>بستانکاران</th><th>اختلاف</th><th>تنخواه</th><th>مشاهده</th></tr></thead>
                    <tbody>
                        <?php foreach($all_branches_stats as $i=>$stat): ?>
                        <tr>
                            <td><?php echo $i+1; ?></td>
                            <td><?php echo htmlspecialchars($stat['name']); ?></td>
                            <td style="color:var(--red);"><?php echo fmt_num($stat['debtors']); ?></td>
                            <td style="color:var(--green);"><?php echo fmt_num($stat['creditors']); ?></td>
                            <td style="color:var(--accent);"><?php echo fmt_num($stat['difference']); ?></td>
                            <td><?php echo fmt_num($stat['petty']); ?></td>
                            <td><a href="../view.php?branch_id=<?php echo $stat['id']; ?>" style="color:var(--accent);text-decoration:none;font-weight:600;">مشاهده ←</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- تب ۴: تنظیمات -->
    <div id="view-settings" class="view-container">
        <div class="card">
            <div class="card-title">🔗 تخصیص ناظر به شعبه</div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px;">
                <select id="obsSelect" style="flex:1;padding:8px;border-radius:6px;border:1px solid var(--border);background:rgba(255,255,255,0.03);color:var(--text);">
                    <option value="">-- ناظر --</option>
                    <?php foreach($observers as $o): ?><option value="<?php echo $o['id']; ?>"><?php echo htmlspecialchars($o['branch_name']); ?></option><?php endforeach; ?>
                </select>
                <select id="branchSelect" style="flex:1;padding:8px;border-radius:6px;border:1px solid var(--border);background:rgba(255,255,255,0.03);color:var(--text);">
                    <option value="">-- شعبه --</option>
                    <?php foreach($branches_list as $b): ?><option value="<?php echo $b['id']; ?>"><?php echo htmlspecialchars($b['branch_name']); ?></option><?php endforeach; ?>
                </select>
                <button class="btn btn-primary" onclick="assign()">تخصیص</button>
            </div>
            <?php foreach($assignments as $a): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:8px;background:rgba(255,255,255,0.02);margin-bottom:6px;border-radius:8px;">
                <span><strong><?php echo htmlspecialchars($a['observer']); ?></strong> → <?php echo htmlspecialchars($a['branch']); ?></span>
                <button class="btn btn-danger" onclick="removeAssign(<?php echo $a['id']; ?>)">حذف</button>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div class="card">
            <div class="card-title">➕ افزودن کاربر جدید</div>
            <button class="btn btn-success" onclick="openUserModal()">➕ افزودن کاربر جدید</button>
        </div>
    </div>
</div>

<!-- مودال -->
<div id="userModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.7);z-index:1001;justify-content:center;align-items:center;">
    <div style="background:#1a1f2e;padding:24px;border-radius:16px;width:90%;max-width:420px;border:1px solid var(--border);">
        <h3 id="modalTitle" style="margin-bottom:14px;">افزودن کاربر جدید</h3>
        <input type="hidden" id="userId">
        <label style="font-size:0.75rem;color:var(--text-secondary);">نام کاربری:</label>
        <input type="text" id="modalUsername" style="width:100%;padding:8px;margin-bottom:10px;border-radius:6px;border:1px solid var(--border);background:rgba(255,255,255,0.03);color:var(--text);">
        <label style="font-size:0.75rem;color:var(--text-secondary);">رمز عبور:</label>
        <input type="password" id="modalPassword" style="width:100%;padding:8px;margin-bottom:10px;border-radius:6px;border:1px solid var(--border);background:rgba(255,255,255,0.03);color:var(--text);">
        <label style="font-size:0.75rem;color:var(--text-secondary);">نام:</label>
        <input type="text" id="modalBranchName" style="width:100%;padding:8px;margin-bottom:10px;border-radius:6px;border:1px solid var(--border);background:rgba(255,255,255,0.03);color:var(--text);">
        <label style="font-size:0.75rem;color:var(--text-secondary);">نقش:</label>
        <select id="modalRole" style="width:100%;padding:8px;margin-bottom:16px;border-radius:6px;border:1px solid var(--border);background:rgba(255,255,255,0.03);color:var(--text);">
            <option value="branch">کاربر شعبه</option>
            <option value="observer">ناظر</option>
        </select>
        <div style="display:flex;gap:8px;">
            <button class="btn btn-success" onclick="saveUser()" style="flex:1;">ذخیره</button>
            <button class="btn btn-danger" onclick="closeModal()" style="flex:1;">انصراف</button>
        </div>
    </div>
</div>
<script>
const csrfToken = "<?php echo $_SESSION['csrf_token']; ?>";
</script>

<script>
function showToast(msg, type) {
    type = type || 'success';
    var c = document.getElementById('toastContainer');
    var t = document.createElement('div');
    t.className = 'toast ' + type;
    t.textContent = msg;
    c.appendChild(t);
    setTimeout(function(){ t.remove(); }, 3000);
}

// ناوبری
document.querySelectorAll('.nav-item').forEach(function(item) {
    item.addEventListener('click', function() {
        var view = this.dataset.view;
        document.querySelectorAll('.nav-item').forEach(function(i) { i.classList.remove('active'); });
        this.classList.add('active');
        document.querySelectorAll('.view-container').forEach(function(v) { v.classList.remove('active'); });
        document.getElementById('view-' + view).classList.add('active');
        location.hash = view;
    });
});

// فعال‌سازی تب از hash
if (location.hash) {
    var hash = location.hash.replace('#', '');
    var tab = document.querySelector('.nav-item[data-view="' + hash + '"]');
    if (tab) tab.click();
} else {
    document.getElementById('view-users').classList.add('active');
}

function viewBranchStats() {
    var branch = document.getElementById('branchStatSelect').value;
    var month = document.getElementById('monthStatSelect').value;
    if (branch && branch != 0) {
        window.location.href = 'index.php?branch_id=' + branch + '&month=' + month + '#stats';
    }
}

function openUserModal() {
    document.getElementById('userModal').style.display = 'flex';
    document.getElementById('modalTitle').innerText = 'افزودن کاربر جدید';
    document.getElementById('userId').value = '';
    document.getElementById('modalUsername').value = '';
    document.getElementById('modalUsername').disabled = false;
    document.getElementById('modalPassword').value = '';
    document.getElementById('modalBranchName').value = '';
    document.getElementById('modalRole').value = 'branch';
}

function closeModal() { document.getElementById('userModal').style.display = 'none'; }

function editUser(id, name, role) {
    document.getElementById('userModal').style.display = 'flex';
    document.getElementById('modalTitle').innerText = 'ویرایش کاربر';
    document.getElementById('userId').value = id;
    document.getElementById('modalUsername').value = '';
    document.getElementById('modalUsername').disabled = true;
    document.getElementById('modalPassword').value = '';
    document.getElementById('modalBranchName').value = name;
    document.getElementById('modalRole').value = role;
}

function saveUser() {
    var id = document.getElementById('userId').value;
    var username = document.getElementById('modalUsername').value;
    var pass = document.getElementById('modalPassword').value;
    var branch = document.getElementById('modalBranchName').value;
    var role = document.getElementById('modalRole').value;
    var action = id ? 'edit' : 'add';
    
    if (!id && (!username || !pass)) { showToast('نام کاربری و رمز عبور الزامی است', 'error'); return; }
    if (!branch) { showToast('نام الزامی است', 'error'); return; }
    
    var body = 'csrf_token=' + encodeURIComponent(csrfToken) +
           '&action=' + action +
           '&username=' + encodeURIComponent(username) +
           '&password=' + encodeURIComponent(pass) +
           '&branch_name=' + encodeURIComponent(branch) +
           '&role=' + role;

if (id) body += '&id=' + id;

    
    fetch('save_settings.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:body })
    .then(function(r){ return r.json(); })
    .then(function(d){ showToast(d.message, d.success ? 'success' : 'error'); closeModal(); if(d.success) location.reload(); });
}

function deleteUser(id) {
    if (confirm('آیا مطمئن هستید؟')) {
        fetch('save_settings.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'csrf_token=' + encodeURIComponent(csrfToken) + '&action=delete&id=' + id})
        .then(function(r){ return r.json(); })
        .then(function(d){ showToast(d.message, d.success ? 'success' : 'error'); if(d.success) location.reload(); });
    }
}

function resetPass(id) {
    var pass = prompt('رمز عبور جدید را وارد کنید:');
    if (pass) {
        fetch('save_settings.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'csrf_token=' + encodeURIComponent(csrfToken) +
     '&action=reset_password&id=' + id +
     '&new_password=' + encodeURIComponent(pass) })
        .then(function(r){ return r.json(); })
        .then(function(d){ showToast(d.message); });
    }
}

function assign() {
    var o = document.getElementById('obsSelect').value;
    var b = document.getElementById('branchSelect').value;
    if (!o || !b) { showToast('لطفاً ناظر و شعبه را انتخاب کنید', 'error'); return; }
    fetch('save_settings.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'csrf_token=' + encodeURIComponent(csrfToken) +
     '&action=assign_observer&observer_id=' + o +
     '&branch_id=' + b })
    .then(function(r){ return r.json(); })
    .then(function(d){ showToast(d.message, d.success ? 'success' : 'error'); if(d.success) location.reload(); });
}

function removeAssign(id) {
    if (confirm('آیا مطمئن هستید؟')) {
        fetch('save_settings.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'csrf_token=' + encodeURIComponent(csrfToken) +
     '&action=delete_assignment&id=' + id })
        .then(function(r){ return r.json(); })
        .then(function(d){ showToast(d.message); if(d.success) location.reload(); });
    }
}
function toggleTheme() {
    document.body.classList.toggle('light');
    let btn = document.getElementById('themeToggle');
    if (document.body.classList.contains('light')) {
        btn.textContent = '☀️';
        localStorage.setItem('theme', 'light');
    } else {
        btn.textContent = '🌙';
        localStorage.setItem('theme', 'dark');
    }
}

// لود اولیه
(function() {
    if (localStorage.getItem('theme') === 'light') {
        document.body.classList.add('light');
        let btn = document.getElementById('themeToggle');
        if (btn) btn.textContent = '☀️';
    }
})();

</script>
</body>
</html>