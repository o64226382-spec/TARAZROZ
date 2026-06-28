<?php
define('SECURE_ACCESS', true);
require_once 'includes/config.php';
require_once 'includes/auth.php';
requireLogin();

$user_id = intval($_SESSION['user_id']);
$perm_query = "SELECT permissions FROM users WHERE id = $user_id";
$perm_result = mysqli_query($conn, $perm_query);
$permissions = '';
if ($perm_result && mysqli_num_rows($perm_result) > 0) {
    $permissions = mysqli_fetch_assoc($perm_result)['permissions'];
}

if (strpos($permissions, 'contract_thana') === false && $_SESSION['role'] !== 'admin') {
    die('<div style="text-align:center;padding:60px;font-family:Vazirmatn;background:#0a0f1a;color:#e8ecf1;">⛔ شما دسترسی به این بخش را ندارید.<br><a href="index.php" style="color:#4b8cf7;">بازگشت</a></div>');
}

header('Location: contract_thana_app.php');
exit();
?>