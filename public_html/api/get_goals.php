<?php
define('SECURE_ACCESS', true);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/jdf.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'auth_required', 'goals' => []]);
    exit;
}

$user = getCurrentUser();
$role = $user['role'];
$userId = $_SESSION['user_id'];

// دریافت سال و ماه از پارامترها
$year = isset($_GET['y']) ? (int)$_GET['y'] : (int)jdate('Y');
$month = isset($_GET['m']) ? (int)$_GET['m'] : (int)jdate('m');

// تعیین شعبه مورد نظر
$branchId = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : 0;
$branches = [];

if ($role === 'branch') {
    $branchId = $userId;
    $branches = [['id' => $userId, 'name' => $user['branch_name'] ?? 'شعبه']];
} 
elseif ($role === 'observer') {
    $q = mysqli_query($conn, "SELECT u.id, u.branch_name FROM observer_assignments oa JOIN users u ON oa.branch_id = u.id WHERE oa.observer_id = $userId");
    while ($b = mysqli_fetch_assoc($q)) {
        $branches[] = ['id' => $b['id'], 'name' => $b['branch_name']];
    }
    if ($branchId == 0 && !empty($branches)) $branchId = $branches[0]['id'];
} 
elseif ($role === 'admin') {
    $q = mysqli_query($conn, "SELECT id, branch_name FROM users WHERE role = 'branch' ORDER BY branch_name");
    while ($b = mysqli_fetch_assoc($q)) {
        $branches[] = ['id' => $b['id'], 'name' => $b['branch_name']];
    }
    if ($branchId == 0 && !empty($branches)) $branchId = $branches[0]['id'];
}

if (!$branchId) {
    echo json_encode(['error' => 'هیچ شعبه‌ای یافت نشد', 'goals' => []]);
    exit;
}

// ========== دریافت انواع اهداف ==========
$goalTypes = [];
$q = mysqli_query($conn, "SELECT id, name, unit, icon FROM goal_types WHERE is_active = 1 ORDER BY sort_order");
while ($row = mysqli_fetch_assoc($q)) {
    $goalTypes[$row['id']] = $row;
}

// اگر جدول goal_types خالی است، از آرایه پیش‌فرض استفاده کن
if (empty($goalTypes)) {
    $goalTypes = [
        1 => ['id' => 1, 'name' => 'وام طلایی ثنا', 'unit' => 'gram', 'icon' => '💰'],
        2 => ['id' => 2, 'name' => 'فروش قسطی طلا', 'unit' => 'gram', 'icon' => '📦'],
        3 => ['id' => 3, 'name' => 'وام رسالت', 'unit' => 'million_rial', 'icon' => '🏦'],
        4 => ['id' => 4, 'name' => 'وام نیک کارت', 'unit' => 'million_rial', 'icon' => '💳'],
        5 => ['id' => 5, 'name' => 'حساب آتیه طلا', 'unit' => 'gram', 'icon' => '⭐'],
        6 => ['id' => 6, 'name' => 'معاملات ماهانه', 'unit' => 'gram', 'icon' => '🔄'],
        7 => ['id' => 7, 'name' => 'وام آتیه ریالی', 'unit' => 'million_rial', 'icon' => '💰']
    ];
}

// ========== دریافت اهداف شعبه ==========
$goals = [];
$daysPassed = (int)jdate('d');

// محاسبه تاریخ میلادی برای ماه جاری
$startDate = sprintf("%04d-%02d-01", $year, $month);
$endDate = date('Y-m-t', strtotime($startDate));

foreach ($goalTypes as $goalTypeId => $goal) {
    // دریافت مقدار هدف
    $targetQuery = "SELECT target_value FROM branch_goals WHERE branch_id = $branchId AND goal_type_id = $goalTypeId AND year = $year AND month = $month";
    $targetResult = mysqli_query($conn, $targetQuery);
    $targetRow = mysqli_fetch_assoc($targetResult);
    $target = (float)($targetRow['target_value'] ?? 0);
    
    // محاسبه پیشرفت (فقط برای ماه جاری)
    $achieved = 0;
    if ($target > 0) {
        $progressQuery = "SELECT COALESCE(SUM(achieved_value), 0) as total 
                          FROM goal_daily_progress 
                          WHERE branch_id = $branchId 
                          AND goal_type_id = $goalTypeId 
                          AND YEAR(progress_date) = YEAR('$startDate')
                          AND MONTH(progress_date) = MONTH('$startDate')";
        $progressResult = mysqli_query($conn, $progressQuery);
        $progressRow = mysqli_fetch_assoc($progressResult);
        $achieved = round($progressRow['total'] ?? 0, 3);
    }
    
    $remaining = max(0, $target - $achieved);
    $percentage = $target > 0 ? round(($achieved / $target) * 100, 1) : 0;
    
    $goals[] = [
        'id' => $goalTypeId,
        'name' => $goal['name'],
        'unit' => $goal['unit'],
        'icon' => $goal['icon'] ?? '🎯',
        'target_value' => $target,
        'achieved' => $achieved,
        'remaining' => $remaining,
        'percentage' => $percentage,
        'daily_avg' => $daysPassed > 0 ? round($achieved / $daysPassed, 3) : 0
    ];
}

// دریافت نام شعبه
$branchName = '';
$q = mysqli_query($conn, "SELECT branch_name FROM users WHERE id = $branchId");
if ($row = mysqli_fetch_assoc($q)) $branchName = $row['branch_name'];

echo json_encode([
    'success' => true,
    'goals' => $goals,
    'branch_name' => $branchName,
    'branch_id' => $branchId,
    'branches' => $branches,
    'current_branch' => $branchId,
    'year' => $year,
    'month' => $month
], JSON_UNESCAPED_UNICODE);
?>