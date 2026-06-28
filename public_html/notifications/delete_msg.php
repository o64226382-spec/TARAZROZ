<?php
define('SECURE_ACCESS', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

if (!isLoggedIn() || !isObserver()) die('0');

$id = (int)($_GET['id'] ?? 0);
$observer_id = $_SESSION['user_id'];

mysqli_query($conn, "UPDATE observer_messages SET deleted_by = $observer_id, deleted_at = NOW() WHERE id = $id AND observer_id = $observer_id");
echo '1';