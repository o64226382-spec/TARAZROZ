<?php
/**
 * علامت‌گذاری پیام به عنوان خوانده شده - AJAX
 */
define('SECURE_ACCESS', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/reminder_functions.php';

if (!isLoggedIn()) {
    die('unauthorized');
}

$message_id = (int)($_POST['message_id'] ?? 0);
markMessageRead($message_id, $_SESSION['user_id']);
echo 'ok';