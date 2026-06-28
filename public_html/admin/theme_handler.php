<?php
define('SECURE_ACCESS', true);
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireAdmin();

// ⭐ CSRF Protection
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: theme.php');
    exit();
}

if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    die('خطای امنیتی. لطفاً صفحه را رفرش کنید.');
}

// ⭐ لیست همه رنگ‌های قابل تنظیم
$color_keys = [
    'primary_color',
    'bg_color',
    'surface_color',
    'border_color',
    'text_color',
    'text_secondary',
    'accent_color',
    'green_color',
    'red_color',
    'purple_color',
    'amber_color',
    'btn_bg',
    'btn_text',
    'header_bg',
    'icon_color',
    'input_bg',
    'input_border',
    'shadow_color',
];

// ⭐ Prepared Statement برای ذخیره
$stmt = mysqli_prepare($conn, "INSERT INTO theme_settings (setting_name, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");

foreach ($color_keys as $key) {
    if (isset($_POST[$key])) {
        $value = trim($_POST[$key]);
        
        // اعتبارسنجی فرمت رنگ hex
        if (preg_match('/^#[a-fA-F0-9]{6}$/', $value)) {
            mysqli_stmt_bind_param($stmt, "ss", $key, $value);
            mysqli_stmt_execute($stmt);
        }
    }
}

// ⭐ رفرش با پیام موفقیت
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
header('Location: theme.php?saved=1');
exit();