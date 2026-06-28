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

$action = $_POST['action'] ?? '';

switch ($action) {
    
    // ========== افزودن/ویرایش ابزار ==========
    case 'save_tool':
        $id = intval($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $url = trim($_POST['url'] ?? '');
        $icon = trim($_POST['icon'] ?? '🔧');
        $desc = trim($_POST['description'] ?? '');
        $active = intval($_POST['active'] ?? 1);
        
        if (empty($name) || empty($slug) || empty($url)) {
            echo json_encode(['success' => false, 'message' => 'فیلدهای ضروری را پر کنید']);
            exit();
        }
        
        if ($id > 0) {
            $stmt = mysqli_prepare($conn, "UPDATE tools SET name=?, slug=?, url=?, icon=?, description=?, active=? WHERE id=?");
            mysqli_stmt_bind_param($stmt, "sssssii", $name, $slug, $url, $icon, $desc, $active, $id);
        } else {
            $stmt = mysqli_prepare($conn, "INSERT INTO tools (name, slug, url, icon, description, active) VALUES (?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "sssssi", $name, $slug, $url, $icon, $desc, $active);
        }
        
        $success = mysqli_stmt_execute($stmt);
        echo json_encode(['success' => $success, 'message' => $success ? '✅ ذخیره شد' : mysqli_error($conn)]);
        break;
    
    // ========== حذف ابزار ==========
    case 'delete_tool':
        $id = intval($_POST['id'] ?? 0);
        
        $stmt = mysqli_prepare($conn, "SELECT slug FROM tools WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $tool = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        
        if ($tool) {
            $slug = $tool['slug'];
            
            $stmt = mysqli_prepare($conn, "SELECT id, permissions FROM users WHERE permissions LIKE ?");
            $search = '%' . $slug . '%';
            mysqli_stmt_bind_param($stmt, "s", $search);
            mysqli_stmt_execute($stmt);
            $users = mysqli_stmt_get_result($stmt);
            
            while ($u = mysqli_fetch_assoc($users)) {
                $newPerms = trim(str_replace([$slug, ',,'], ['', ','], $u['permissions']), ',');
                $update_stmt = mysqli_prepare($conn, "UPDATE users SET permissions = ? WHERE id = ?");
                mysqli_stmt_bind_param($update_stmt, "si", $newPerms, $u['id']);
                mysqli_stmt_execute($update_stmt);
            }
        }
        
        $stmt = mysqli_prepare($conn, "DELETE FROM tools WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        $success = mysqli_stmt_execute($stmt);
        
        echo json_encode(['success' => true, 'message' => '🗑️ حذف شد']);
        break;
    
    // ⭐ ========== تغییر دسترسی کاربر ==========
    case 'toggle_user_tool':
        $user_id = intval($_POST['user_id'] ?? 0);
        $slug = trim($_POST['slug'] ?? '');
        
        if ($user_id <= 0 || empty($slug)) {
            echo json_encode(['success' => false, 'message' => 'اطلاعات ناقص']);
            exit();
        }
        
        $stmt = mysqli_prepare($conn, "SELECT permissions FROM users WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $u = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        
        $perms = $u['permissions'] ?? '';
        
        if (strpos($perms, $slug) !== false) {
            $perms = trim(str_replace([$slug, ',,'], ['', ','], $perms), ',');
        } else {
            $perms = $perms ? "$perms,$slug" : $slug;
        }
        
        $stmt = mysqli_prepare($conn, "UPDATE users SET permissions = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "si", $perms, $user_id);
        $success = mysqli_stmt_execute($stmt);
        
        echo json_encode(['success' => $success, 'message' => $success ? '✅ تغییر کرد' : 'خطا']);
        break;
    
    default:
        echo json_encode(['success' => false, 'message' => 'عملیات نامشخص']);
}

exit();