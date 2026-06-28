<?php
/**
 * برگرداندن تعداد اعلان‌های کاربر - برای AJAX
 */
define('SECURE_ACCESS', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/reminder_functions.php';

if (!isLoggedIn() || !isBranch()) {
    die('0');
}

echo getNotificationCount($_SESSION['user_id']);