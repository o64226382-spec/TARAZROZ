<?php
define('SECURE_ACCESS', true);
require_once 'includes/config.php';
require_once 'includes/auth.php';
requireAdmin();

require_once 'includes/jdf.php';

// ========== ایجاد جداول در صورت نیاز ==========
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS goal_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    unit ENUM('gram', 'million_rial') DEFAULT 'gram',
    icon VARCHAR(10) DEFAULT '🎯',
    sort_order INT DEFAULT 0,
    is_active TINYINT DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// درج داده‌های اولیه اگر جدول خالی است
$check = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM goal_types");
$row = mysqli_fetch_assoc($check);
if ($row['cnt'] == 0) {
    mysqli_query($conn, "INSERT INTO goal_types (name, unit, icon, sort_order) VALUES
        ('وام طلایی ثنا', 'gram', '💰', 1),
        ('فروش قسطی طلا', 'gram', '📦', 2),
        ('وام رسالت', 'million_rial', '🏦', 3),
        ('وام نیک کارت', 'million_rial', '💳', 4),
        ('حساب آتیه طلا', 'gram', '⭐', 5),
        ('معاملات ماهانه', 'gram', '🔄', 6),
        ('وام آتیه ریالی', 'million_rial', '💰', 7)");
}

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS branch_goals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    branch_id INT NOT NULL,
    goal_type_id INT NOT NULL,
    target_value DECIMAL(15,3) NOT NULL,
    year INT NOT NULL,
    month INT NOT NULL,
    created_by INT NOT NULL,
    UNIQUE KEY unique_goal (branch_id, goal_type_id, year, month)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS goal_daily_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    branch_id INT NOT NULL,
    goal_type_id INT NOT NULL,
    achieved_value DECIMAL(15,3) NOT NULL,
    progress_date DATE NOT NULL,
    created_by INT NOT NULL,
    UNIQUE KEY unique_daily (branch_id, goal_type_id, progress_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ========== دریافت داده‌ها ==========
$goalTypes = [];
$q = mysqli_query($conn, "SELECT * FROM goal_types WHERE is_active = 1 ORDER BY sort_order");
while ($row = mysqli_fetch_assoc($q)) {
    $goalTypes[] = $row;
}

$branches = [];
$q = mysqli_query($conn, "SELECT id, branch_name FROM users WHERE role = 'branch' ORDER BY branch_name");
while ($row = mysqli_fetch_assoc($q)) {
    $branches[] = $row;
}

$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)jdate('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)jdate('m');
$selectedBranch = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : (isset($branches[0]['id']) ? $branches[0]['id'] : 0);

// ذخیره اهداف
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedBranch = (int)$_POST['branch_id'];
    $year = (int)$_POST['year'];
    $month = (int)$_POST['month'];
    
    foreach ($_POST['goals'] as $goalTypeId => $targetValue) {
        $targetValue = (float)$targetValue;
        if ($targetValue > 0) {
            $check = mysqli_query($conn, "SELECT id FROM branch_goals WHERE branch_id = $selectedBranch AND goal_type_id = $goalTypeId AND year = $year AND month = $month");
            if (mysqli_num_rows($check) > 0) {
                mysqli_query($conn, "UPDATE branch_goals SET target_value = $targetValue WHERE branch_id = $selectedBranch AND goal_type_id = $goalTypeId AND year = $year AND month = $month");
            } else {
                mysqli_query($conn, "INSERT INTO branch_goals (branch_id, goal_type_id, target_value, year, month, created_by) VALUES ($selectedBranch, $goalTypeId, $targetValue, $year, $month, {$_SESSION['user_id']})");
            }
        } else {
            mysqli_query($conn, "DELETE FROM branch_goals WHERE branch_id = $selectedBranch AND goal_type_id = $goalTypeId AND year = $year AND month = $month");
        }
    }
    header("Location: admin_goals.php?year=$year&month=$month&branch_id=$selectedBranch&saved=1");
    exit;
}

// دریافت اهداف جاری
$currentGoals = [];
if ($selectedBranch > 0) {
    $q = mysqli_query($conn, "SELECT goal_type_id, target_value FROM branch_goals WHERE branch_id = $selectedBranch AND year = $year AND month = $month");
    while ($row = mysqli_fetch_assoc($q)) {
        $currentGoals[$row['goal_type_id']] = $row['target_value'];
    }
}

$months = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
$branchName = '';
foreach ($branches as $b) {
    if ($b['id'] == $selectedBranch) $branchName = $b['branch_name'];
}
?>

<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <title>مدیریت اهداف شعب</title>
    <link href="assets/fonts/fonts.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Vazirmatn', sans-serif; background: #0a0f1a; color: #e8ecf1; padding: 20px; }
        .container { max-width: 1000px; margin: 0 auto; }
        .card { background: rgba(255,255,255,0.05); border-radius: 16px; padding: 24px; border: 1px solid rgba(255,255,255,0.1); }
        h1 { font-size: 1.5rem; background: linear-gradient(135deg, #d4af37, #fcd34d); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin-bottom: 20px; }
        .filters { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 24px; }
        .filters select, .filters button { padding: 10px 16px; border-radius: 8px; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: #fff; cursor: pointer; font-family: 'Vazirmatn'; }
        .filters button { background: linear-gradient(135deg, #d4af37, #fcd34d); color: #1a1a1a; border: none; font-weight: 700; }
        .form-group { margin-bottom: 16px; display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
        .form-group label { width: 200px; font-weight: 600; display: flex; align-items: center; gap: 8px; }
        .form-group input { flex: 1; padding: 10px; border-radius: 8px; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.2); color: #fff; font-family: 'Vazirmatn'; }
        .btn-save { background: linear-gradient(135deg, #10b981, #059669); color: #fff; border: none; padding: 14px; border-radius: 10px; font-weight: 700; cursor: pointer; width: 100%; margin-top: 20px; font-family: 'Vazirmatn'; font-size: 1rem; }
        .success-msg { background: rgba(16,185,129,0.2); border: 1px solid #10b981; padding: 12px; border-radius: 10px; margin-bottom: 20px; text-align: center; }
        .branch-info { background: rgba(212,175,55,0.1); padding: 12px; border-radius: 10px; margin-bottom: 20px; text-align: center; }
        .unit-badge { font-size: 0.7rem; color: #94a3b8; }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <h1>🎯 مدیریت اهداف شعب</h1>
        
        <?php if (isset($_GET['saved'])): ?>
        <div class="success-msg">✅ اهداف با موفقیت ذخیره شد</div>
        <?php endif; ?>
        
        <form method="GET" class="filters">
            <select name="year">
                <?php for($y = jdate('Y')-1; $y <= jdate('Y')+1; $y++): ?>
                    <option value="<?php echo $y; ?>" <?php echo $y == $year ? 'selected' : ''; ?>><?php echo $y; ?></option>
                <?php endfor; ?>
            </select>
            <select name="month">
                <?php for($m = 1; $m <= 12; $m++): ?>
                    <option value="<?php echo $m; ?>" <?php echo $m == $month ? 'selected' : ''; ?>><?php echo $months[$m-1]; ?></option>
                <?php endfor; ?>
            </select>
            <select name="branch_id">
                <?php foreach ($branches as $branch): ?>
                    <option value="<?php echo $branch['id']; ?>" <?php echo $branch['id'] == $selectedBranch ? 'selected' : ''; ?>><?php echo htmlspecialchars($branch['branch_name']); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit">🔍 انتخاب</button>
        </form>
        
        <div class="branch-info">📍 <?php echo htmlspecialchars($branchName); ?> - <?php echo $months[$month-1] . ' ' . $year; ?></div>
        
        <form method="POST">
            <input type="hidden" name="branch_id" value="<?php echo $selectedBranch; ?>">
            <input type="hidden" name="year" value="<?php echo $year; ?>">
            <input type="hidden" name="month" value="<?php echo $month; ?>">
            
            <?php foreach ($goalTypes as $goal): 
                $currentValue = isset($currentGoals[$goal['id']]) ? $currentGoals[$goal['id']] : '';
                $unitText = $goal['unit'] == 'gram' ? 'گرم' : 'میلیون ریال';
            ?>
            <div class="form-group">
                <label><span style="font-size:1.2rem;"><?php echo $goal['icon']; ?></span> <?php echo htmlspecialchars($goal['name']); ?> <span class="unit-badge">(<?php echo $unitText; ?>)</span></label>
                <input type="number" step="0.001" name="goals[<?php echo $goal['id']; ?>]" value="<?php echo htmlspecialchars($currentValue); ?>" placeholder="0">
            </div>
            <?php endforeach; ?>
            
            <button type="submit" class="btn-save">💾 ذخیره اهداف</button>
        </form>
    </div>
</div>
</body>
</html>