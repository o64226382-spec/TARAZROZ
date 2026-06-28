<?php
define('SECURE_ACCESS', true);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/jdf.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'لطفا وارد شوید']);
    exit;
}

$user = getCurrentUser();
$userId = $_SESSION['user_id'];
$goalId = isset($_POST['goal_id']) ? (int)$_POST['goal_id'] : 0;
$value = isset($_POST['value']) ? (float)$_POST['value'] : 0;
$date = isset($_POST['date']) ? $_POST['date'] : date('Y-m-d');

// تعیین شعبه
if ($user['role'] === 'branch') {
    $branchId = $userId;
} elseif ($user['role'] === 'observer' && isset($_POST['branch_id'])) {
    $branchId = (int)$_POST['branch_id'];
    $check = mysqli_query($conn, "SELECT 1 FROM observer_assignments WHERE observer_id = $userId AND branch_id = $branchId");
    if (mysqli_num_rows($check) == 0) {
        echo json_encode(['success' => false, 'error' => 'شما مجوز ثبت برای این شعبه را ندارید']);
        exit;
    }
} else {
    echo json_encode(['success' => false, 'error' => 'شما مجوز ثبت ندارید']);
    exit;
}

if ($goalId == 0 || $value <= 0) {
    echo json_encode(['success' => false, 'error' => 'مقدار نامعتبر']);
    exit;
}

// ثبت یا به‌روزرسانی
$query = "INSERT INTO goal_daily_progress (branch_id, goal_type_id, achieved_value, progress_date, created_by) 
          VALUES ($branchId, $goalId, $value, '$date', $userId)
          ON DUPLICATE KEY UPDATE achieved_value = achieved_value + $value";

if (mysqli_query($conn, $query)) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => mysqli_error($conn)]);
}
?>