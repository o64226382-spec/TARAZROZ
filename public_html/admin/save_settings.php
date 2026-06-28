<?php
define('SECURE_ACCESS', true);
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireAdmin();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'متد غیرمجاز']);
    exit();
}

// ⭐ CSRF Protection
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'خطای امنیتی. لطفاً صفحه را رفرش کنید.']);
    exit();
}

$action = $_POST['action'] ?? '';

switch ($action) {
    
    // ========== افزودن کاربر ==========
    case 'add':
        $username = trim($_POST['username'] ?? '');
        $password_input = $_POST['password'] ?? '';
        $branch_name = trim($_POST['branch_name'] ?? '');
        $role = $_POST['role'] ?? 'branch';
        
        if (empty($username) || empty($password_input) || empty($branch_name)) {
            echo json_encode(['success' => false, 'message' => 'لطفاً تمام فیلدها را پر کنید']);
            exit();
        }
        
        // ⭐ چک تکراری نبودن username با Prepared Statement
        $check_stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ?");
        mysqli_stmt_bind_param($check_stmt, "s", $username);
        mysqli_stmt_execute($check_stmt);
        
        if (mysqli_num_rows(mysqli_stmt_get_result($check_stmt)) > 0) {
            echo json_encode(['success' => false, 'message' => 'این نام کاربری قبلاً ثبت شده است']);
            exit();
        }
        
        // ⭐ هش پسورد با Bcrypt
        $hashed_password = password_hash($password_input, PASSWORD_BCRYPT);
        
        // ⭐ Insert امن
        $stmt = mysqli_prepare($conn, "INSERT INTO users (username, password, branch_name, role) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "ssss", $username, $hashed_password, $branch_name, $role);
        $success = mysqli_stmt_execute($stmt);
        
        echo json_encode(['success' => $success, 'message' => $success ? '✅ کاربر با موفقیت افزوده شد' : 'خطا در افزودن کاربر']);
        break;
    
    // ========== حذف کاربر ==========
    case 'delete':
        $id = intval($_POST['id'] ?? 0);
        
        if ($id == 1) {
            echo json_encode(['success' => false, 'message' => 'ادمین اصلی قابل حذف نیست']);
            exit();
        }
        
        // ⭐ Delete امن
        $stmt = mysqli_prepare($conn, "DELETE FROM users WHERE id = ? AND username != 'admin'");
        mysqli_stmt_bind_param($stmt, "i", $id);
        $success = mysqli_stmt_execute($stmt);
        
        echo json_encode(['success' => $success, 'message' => $success ? '✅ کاربر حذف شد' : 'خطا در حذف کاربر']);
        break;
    
    // ========== ویرایش کاربر ==========
    case 'edit':
        $id = intval($_POST['id'] ?? 0);
        $branch_name = trim($_POST['branch_name'] ?? '');
        
        if (empty($branch_name)) {
            echo json_encode(['success' => false, 'message' => 'نام را وارد کنید']);
            exit();
        }
        
        // ⭐ Update امن
        $stmt = mysqli_prepare($conn, "UPDATE users SET branch_name = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "si", $branch_name, $id);
        $success = mysqli_stmt_execute($stmt);
        
        echo json_encode(['success' => $success, 'message' => $success ? '✅ ویرایش شد' : 'خطا در ویرایش']);
        break;
    
    // ========== ریست رمز عبور ==========
    case 'reset_password':
        $id = intval($_POST['id'] ?? 0);
        $new_password = $_POST['new_password'] ?? '';
        
        if (empty($new_password) || strlen($new_password) < 6) {
            echo json_encode(['success' => false, 'message' => 'رمز عبور باید حداقل ۶ کاراکتر باشد']);
            exit();
        }
        
        // ⭐ هش پسورد جدید با Bcrypt
        $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
        
        // ⭐ Update امن
        $stmt = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "si", $hashed_password, $id);
        $success = mysqli_stmt_execute($stmt);
        
        echo json_encode(['success' => $success, 'message' => $success ? '✅ رمز عبور تغییر کرد' : 'خطا در تغییر رمز']);
        break;
    
    // ========== تخصیص ناظر به شعبه ==========
    case 'assign_observer':
        $observer_id = intval($_POST['observer_id'] ?? 0);
        $branch_id = intval($_POST['branch_id'] ?? 0);
        
        if ($observer_id <= 0 || $branch_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ناظر و شعبه را انتخاب کنید']);
            exit();
        }
        
        // ⭐ چک وجود ناظر
        $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE id = ? AND role = 'observer'");
        mysqli_stmt_bind_param($stmt, "i", $observer_id);
        mysqli_stmt_execute($stmt);
        
        if (mysqli_num_rows(mysqli_stmt_get_result($stmt)) == 0) {
            echo json_encode(['success' => false, 'message' => 'ناظر یافت نشد']);
            exit();
        }
        
        // ⭐ چک وجود شعبه
        $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE id = ? AND role = 'branch'");
        mysqli_stmt_bind_param($stmt, "i", $branch_id);
        mysqli_stmt_execute($stmt);
        
        if (mysqli_num_rows(mysqli_stmt_get_result($stmt)) == 0) {
            echo json_encode(['success' => false, 'message' => 'شعبه یافت نشد']);
            exit();
        }
        
        // ⭐ Insert امن
        $stmt = mysqli_prepare($conn, "INSERT IGNORE INTO observer_assignments (observer_id, branch_id) VALUES (?, ?)");
        mysqli_stmt_bind_param($stmt, "ii", $observer_id, $branch_id);
        $success = mysqli_stmt_execute($stmt);
        
        echo json_encode(['success' => $success, 'message' => $success ? '✅ ناظر با موفقیت تخصیص داده شد' : 'خطا در تخصیص ناظر']);
        break;
    
    // ========== حذف تخصیص ==========
    case 'delete_assignment':
        $id = intval($_POST['id'] ?? 0);
        
        // ⭐ Delete امن
        $stmt = mysqli_prepare($conn, "DELETE FROM observer_assignments WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        $success = mysqli_stmt_execute($stmt);
        
        echo json_encode(['success' => $success, 'message' => $success ? '✅ تخصیص حذف شد' : 'خطا در حذف']);
        break;
    
    // ========== لیست کاربران (فقط برای API) ==========
    case 'list':
        $stmt = mysqli_prepare($conn, "SELECT id, username, branch_name, role, created_at FROM users WHERE username != 'admin' ORDER BY branch_name");
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $users = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $users[] = $row;
        }
        
        echo json_encode(['success' => true, 'users' => $users]);
        break;
    
    default:
        echo json_encode(['success' => false, 'message' => 'عملیات نامشخص']);
}

exit();