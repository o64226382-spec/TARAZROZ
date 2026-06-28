<?php
define('SECURE_ACCESS', true);
require_once '../includes/config.php';
require_once '../includes/auth.php';

// ⭐ فقط کاربر branch می‌تونه حذف کنه (admin و observer نمی‌تونن)
if (!isLoggedIn() || isAdmin() || isObserver()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز']);
    exit();
}

$user_id = $_SESSION['user_id'];
$date = $_POST['date'] ?? '';

if (!$date) {
    echo json_encode(['success' => false, 'message' => 'تاریخ مشخص نشده']);
    exit();
}

// ⭐ اول چک می‌کنیم این گزارش واقعاً مال این کاربر هست
$check_stmt = mysqli_prepare($conn, "SELECT id FROM daily_reports WHERE user_id = ? AND report_date = ?");
mysqli_stmt_bind_param($check_stmt, "is", $user_id, $date);
mysqli_stmt_execute($check_stmt);
$check_result = mysqli_stmt_get_result($check_stmt);

if (mysqli_num_rows($check_result) == 0) {
    echo json_encode(['success' => false, 'message' => 'گزارشی برای این تاریخ یافت نشد']);
    exit();
}

// ⭐ حالا حذف امن با Prepared Statement
$del_stmt = mysqli_prepare($conn, "DELETE FROM daily_reports WHERE user_id = ? AND report_date = ?");
mysqli_stmt_bind_param($del_stmt, "is", $user_id, $date);
$success = mysqli_stmt_execute($del_stmt);

if ($success) {
    echo json_encode(['success' => true, 'message' => "گزارش روز $date با موفقیت حذف شد"]);
} else {
    echo json_encode(['success' => false, 'message' => 'خطا در حذف گزارش']);
}
exit();