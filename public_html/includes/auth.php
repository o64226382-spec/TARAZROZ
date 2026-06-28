<?php
if (!defined('SECURE_ACCESS')) {
    define('SECURE_ACCESS', true);
}
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}
function isObserver() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'observer';
}
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/login.php');
        exit();
    }
}
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: ' . USER_URL . '/index.php');
        exit();
    }
}
function redirectIfAdmin() {
    if (isLoggedIn() && isAdmin()) {
        header('Location: ' . ADMIN_URL . '/index.php');
        exit();
    }
}
function getCurrentUser() {
    if (!isLoggedIn()) return null;
    global $conn;
    $user_id = $_SESSION['user_id'];
    $query = "SELECT * FROM users WHERE id = $user_id";
    $result = mysqli_query($conn, $query);
    return mysqli_fetch_assoc($result);
}
function isBranch() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'branch';
}
?>