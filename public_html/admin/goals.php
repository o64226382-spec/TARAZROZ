<?php
define('SECURE_ACCESS', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../includes/jdf.php';

// ========== تابع کمکی برای محاسبه پایان دوره شمسی ==========
function addMonthsToShamsiDate($shamsiDate, $months) {
    $parts = explode('-', $shamsiDate);
    $y = (int)$parts[0]; $m = (int)$parts[1]; $d = (int)$parts[2];
    // تبدیل به timestamp میلادی، اضافه کردن ماه، و بازگشت به شمسی
    $ts = jmktime(0, 0, 0, $m, $d, $y);
    $newTs = strtotime("+$months months -1 day", $ts);
    return jdate('Y-m-d', $newTs);
}

// ========== ایجاد/بروزرسانی جداول ==========
$tableCheck = mysqli_query($conn, "SHOW TABLES LIKE 'goal_types'");
if (mysqli_num_rows($tableCheck) == 0) {
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS goal_types (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        unit ENUM('gram', 'million_rial', 'count') DEFAULT 'gram',
        icon VARCHAR(10) DEFAULT '🎯',
        sort_order INT DEFAULT 0,
        is_active TINYINT DEFAULT 1
    )");
    mysqli_query($conn, "INSERT INTO goal_types (name, unit, icon, sort_order) VALUES
    ('وام طلایی ثنا', 'gram', '💰', 1), ('فروش قسطی طلا', 'gram', '📦', 2),
    ('وام رسالت', 'million_rial', '🏦', 3), ('وام نیک کارت', 'million_rial', '💳', 4),
    ('حساب آتیه طلا', 'gram', '⭐', 5), ('معاملات ماهانه', 'gram', '🔄', 6),
    ('وام آتیه ریالی', 'million_rial', '💰', 7)");
}

$tableCheck2 = mysqli_query($conn, "SHOW TABLES LIKE 'branch_goals'");
if (mysqli_num_rows($tableCheck2) == 0) {
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS branch_goals (
        id INT AUTO_INCREMENT PRIMARY KEY,
        branch_id INT NOT NULL,
        goal_type_id INT NOT NULL,
        target_value DECIMAL(15,3) NOT NULL,
        start_date VARCHAR(10) NOT NULL,
        end_date VARCHAR(10) NOT NULL,
        period_months TINYINT DEFAULT 1,
        created_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_goal_period (branch_id, goal_type_id, start_date)
    )");
}

$tableCheck3 = mysqli_query($conn, "SHOW TABLES LIKE 'goal_daily_progress'");
if (mysqli_num_rows($tableCheck3) == 0) {
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS goal_daily_progress (
        id INT AUTO_INCREMENT PRIMARY KEY,
        branch_id INT NOT NULL,
        goal_type_id INT NOT NULL,
        achieved_value DECIMAL(15,3) NOT NULL,
        progress_date VARCHAR(10) NOT NULL,
        created_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_daily (branch_id, goal_type_id, progress_date)
    )");
}

// ========== دریافت داده‌ها ==========
$goalTypes = [];
$q = mysqli_query($conn, "SELECT * FROM goal_types WHERE is_active = 1 ORDER BY sort_order");
while ($row = mysqli_fetch_assoc($q)) $goalTypes[] = $row;

$branches = [];
$q = mysqli_query($conn, "SELECT id, branch_name FROM users WHERE role = 'branch' ORDER BY branch_name");
while ($row = mysqli_fetch_assoc($q)) $branches[] = $row;

$selectedBranch = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : ($branches[0]['id'] ?? 0);
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : jdate('Y-m-01');
$period_months = isset($_GET['period_months']) ? (int)$_GET['period_months'] : 1;
$action = isset($_POST['action']) ? $_POST['action'] : '';

// محاسبه تاریخ پایان دوره انتخابی
$end_date = addMonthsToShamsiDate($start_date, $period_months);

// پردازش ذخیره‌سازی
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save') {
    $branchId = (int)$_POST['branch_id'];
    $start_date = $_POST['start_date'];
    $period_months = (int)$_POST['period_months'];
    $userId = $_SESSION['user_id'];
    $end_date = addMonthsToShamsiDate($start_date, $period_months);

    // بررسی تداخل
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) as cnt FROM branch_goals WHERE branch_id = ? AND start_date = ?");
    mysqli_stmt_bind_param($stmt, "is", $branchId, $start_date);
    mysqli_stmt_execute($stmt);
    if (mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['cnt'] > 0) {
        header("Location: goals.php?branch_id=$branchId&start_date=$start_date&error=overlap");
        exit;
    }

    mysqli_begin_transaction($conn);
    $stmtDel = mysqli_prepare($conn, "DELETE FROM branch_goals WHERE branch_id = ? AND start_date = ?");
    mysqli_stmt_bind_param($stmtDel, "is", $branchId, $start_date);
    mysqli_stmt_execute($stmtDel);

    $stmtIns = mysqli_prepare($conn, "INSERT INTO branch_goals (branch_id, goal_type_id, target_value, start_date, end_date, period_months, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
    foreach ($_POST['goals'] as $goalTypeId => $targetValue) {
        $targetValue = (float)$targetValue;
        if ($targetValue > 0) {
            mysqli_stmt_bind_param($stmtIns, "iiddsi", $branchId, (int)$goalTypeId, $targetValue, $start_date, $end_date, $period_months, $userId);
            mysqli_stmt_execute($stmtIns);
        }
    }
    mysqli_commit($conn);
    header("Location: goals.php?branch_id=$branchId&start_date=$start_date&saved=1");
    exit;
}

// دریافت اهداف بازه انتخاب شده
$currentGoals = [];
$currentPeriodInfo = ['start_date' => $start_date, 'end_date' => $end_date, 'period_months' => $period_months];
if ($selectedBranch > 0) {
    $q2 = mysqli_query($conn, "SELECT start_date, end_date, period_months FROM branch_goals WHERE branch_id = $selectedBranch AND start_date = '$start_date' LIMIT 1");
    if ($row = mysqli_fetch_assoc($q2)) {
        $currentPeriodInfo = $row;
        $start_date = $row['start_date'];
        $end_date = $row['end_date'];
        $period_months = $row['period_months'];
        
        $q3 = mysqli_query($conn, "SELECT goal_type_id, target_value FROM branch_goals WHERE branch_id = $selectedBranch AND start_date = '$start_date'");
        while ($r = mysqli_fetch_assoc($q3)) $currentGoals[$r['goal_type_id']] = $r['target_value'];
    }
}

// دریافت پیشرفت برای نمایش
$progressData = [];
if ($selectedBranch > 0) {
    foreach ($goalTypes as $goal) {
        $target = $currentGoals[$goal['id']] ?? 0;
        $q4 = mysqli_query($conn, "SELECT COALESCE(SUM(achieved_value), 0) as total FROM goal_daily_progress WHERE branch_id = $selectedBranch AND goal_type_id = {$goal['id']} AND progress_date BETWEEN '$start_date' AND '$end_date'");
        $r4 = mysqli_fetch_assoc($q4);
        $achieved = round($r4['total'] ?? 0, 3);
        $progressData[$goal['id']] = [
            'achieved' => $achieved,
            'percentage' => $target > 0 ? round(($achieved / $target) * 100, 1) : 0
        ];
    }
}

$selectedBranchName = '';
foreach ($branches as $b) { if ($b['id'] == $selectedBranch) { $selectedBranchName = $b['branch_name']; break; } }
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
<meta charset="UTF-8">
<title>مدیریت اهداف شعب</title>
<link href="../assets/fonts/fonts.css" rel="stylesheet">
<link href="../assets/css/persian-datepicker.css" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="../assets/js/persian-datepicker.js"></script>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Vazirmatn', sans-serif; background: #0a0f1a; color: #e8ecf1; padding: 20px; }
.container { max-width: 1200px; margin: 0 auto; }
.card { background: rgba(255,255,255,0.05); border-radius: 16px; padding: 24px; border: 1px solid rgba(255,255,255,0.1); margin-bottom: 20px; }
h1 { font-size: 1.5rem; background: linear-gradient(135deg, #d4af37, #fcd34d); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin-bottom: 20px; }
.filters { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; margin-bottom: 24px; }
.filters select, .filters input, .filters button { padding: 10px 18px; border-radius: 8px; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: #fff; font-family: 'Vazirmatn'; font-size: 0.9rem; cursor: pointer; }
.filters button { background: linear-gradient(135deg, #d4af37, #fcd34d); color: #1a1a1a; border: none; font-weight: 700; }
.form-group { margin-bottom: 20px; display: flex; align-items: center; gap: 15px; flex-wrap: wrap; }
.form-group label { width: 220px; font-weight: 600; display: flex; align-items: center; gap: 10px; font-size: 0.95rem; }
.form-group input { flex: 1; padding: 12px; border-radius: 8px; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.2); color: #fff; font-family: 'Vazirmatn'; font-size: 0.95rem; }
.form-group input:focus { outline: none; border-color: #d4af37; }
.form-group .progress-info { width: 180px; font-size: 0.8rem; color: #94a3b8; text-align: left; }
.btn-save { background: linear-gradient(135deg, #10b981, #059669); color: #fff; border: none; padding: 14px 24px; border-radius: 10px; font-weight: 700; cursor: pointer; width: 100%; font-family: 'Vazirmatn'; font-size: 1rem; margin-top: 20px; }
.success-msg { background: rgba(16,185,129,0.2); border: 1px solid #10b981; padding: 15px; border-radius: 10px; margin-bottom: 20px; text-align: center; color: #10b981; font-size: 0.9rem; }
.error-msg { background: rgba(239,68,68,0.2); border: 1px solid #ef4444; padding: 15px; border-radius: 10px; margin-bottom: 20px; text-align: center; color: #ef4444; font-size: 0.9rem; }
.unit-badge { font-size: 0.75rem; color: #94a3b8; }
hr { border-color: rgba(255,255,255,0.1); margin: 20px 0; }
.branch-info { background: rgba(212,175,55,0.1); padding: 15px; border-radius: 10px; margin-bottom: 20px; text-align: center; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; font-size: 0.9rem; }
.progress-bar-bg { width: 100px; height: 6px; background: rgba(255,255,255,0.1); border-radius: 3px; overflow: hidden; display: inline-block; margin-left: 8px; }
.progress-bar-fill { height: 100%; background: linear-gradient(90deg, #d4af37, #fcd34d); border-radius: 3px; }
@media (max-width: 768px) { .form-group label { width: 100%; } .form-group .progress-info { width: 100%; text-align: right; margin-top: 5px; } }
</style>
</head>
<body>
<div class="container">
<div class="card">
<h1>🎯 مدیریت اهداف شعب</h1>
<?php if (isset($_GET['saved'])): ?>
<div class="success-msg">✅ اهداف با موفقیت ذخیره شد</div>
<?php elseif (isset($_GET['error']) && $_GET['error'] == 'overlap'): ?>
<div class="error-msg">⚠️ خطا: بازه زمانی انتخابی قبلاً تعریف شده است.</div>
<?php endif; ?>
<form method="GET" action="" class="filters">
<select name="branch_id">
<?php foreach ($branches as $branch): ?>
<option value="<?php echo $branch['id']; ?>" <?php echo $branch['id'] == $selectedBranch ? 'selected' : ''; ?>>
<?php echo htmlspecialchars($branch['branch_name']); ?>
</option>
<?php endforeach; ?>
</select>
<input type="text" id="goal_start_date" name="start_date" value="<?php echo $start_date; ?>" placeholder="تاریخ شروع دوره" readonly style="width:150px;">
<select name="period_months" id="period_months">
<option value="1" <?php echo $period_months == 1 ? 'selected' : ''; ?>>۱ ماهه</option>
<option value="3" <?php echo $period_months == 3 ? 'selected' : ''; ?>>۳ ماهه</option>
<option value="4" <?php echo $period_months == 4 ? 'selected' : ''; ?>>۴ ماهه</option>
<option value="6" <?php echo $period_months == 6 ? 'selected' : ''; ?>>۶ ماهه</option>
<option value="12" <?php echo $period_months == 12 ? 'selected' : ''; ?>>۱۲ ماهه</option>
</select>
<button type="submit">🔍 انتخاب دوره</button>
</form>

<?php if ($selectedBranch > 0): ?>
<div class="branch-info">
<span>📍 <strong><?php echo htmlspecialchars($selectedBranchName); ?></strong></span>
<span>📅 بازه: <?php echo $start_date; ?> تا <?php echo $end_date; ?></span>
<span>🎯 وضعیت: <?php 
$completedCount = 0;
foreach ($progressData as $p) { if ($p['percentage'] >= 100) $completedCount++; }
echo $completedCount . ' از ' . count($goalTypes) . ' هدف تکمیل شده';
?></span>
</div>
<form method="POST" action="">
<input type="hidden" name="action" value="save">
<input type="hidden" name="branch_id" value="<?php echo $selectedBranch; ?>">
<input type="hidden" name="start_date" value="<?php echo $start_date; ?>">
<input type="hidden" name="period_months" value="<?php echo $period_months; ?>">
<?php foreach ($goalTypes as $goal):
$currentValue = $currentGoals[$goal['id']] ?? '';
$unitText = $goal['unit'] == 'gram' ? 'گرم' : ($goal['unit'] == 'million_rial' ? 'میلیون ریال' : 'فقره');
$progress = $progressData[$goal['id']] ?? ['achieved' => 0, 'percentage' => 0];
$icon = !empty($goal['icon']) ? $goal['icon'] : '🎯';
?>
<div class="form-group">
<label>
<span style="font-size:1.3rem;"><?php echo $icon; ?></span>
<?php echo htmlspecialchars($goal['name']); ?>
<span class="unit-badge">(<?php echo $unitText; ?>)</span>
</label>
<input type="number" step="0.001" name="goals[<?php echo $goal['id']; ?>]" value="<?php echo htmlspecialchars($currentValue); ?>" placeholder="0">
<?php if ($progress['achieved'] > 0): ?>
<div class="progress-info">
<div class="progress-bar-bg"><div class="progress-bar-fill" style="width: <?php echo min($progress['percentage'], 100); ?>%;"></div></div>
پیشرفت: <?php echo number_format($progress['achieved']); ?> / <?php echo number_format($currentValue ?: 0); ?> (<?php echo $progress['percentage']; ?>%)
</div>
<?php endif; ?>
</div>
<?php endforeach; ?>
<hr>
<button type="submit" class="btn-save">💾 ذخیره اهداف دوره</button>
</form>
<?php else: ?>
<div style="text-align:center; padding:60px; color:#94a3b8; font-size:1rem;">🏢 لطفاً یک شعبه را انتخاب کنید</div>
<?php endif; ?>
</div>
</div>

<script>
$(document).ready(function() {
    $('#goal_start_date').persianDatepicker({
        format: 'YYYY-MM-DD',
        autoClose: true,
        initialValue: false,
        onSelect: function(date) { $('#goal_start_date').val(date); }
    });
});
</script>
</body>
</html>