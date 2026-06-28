<?php
session_start();
require_once 'includes/config.php';

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    mysqli_query($conn, "UPDATE users SET last_activity = NULL WHERE id = $user_id");
}
session_destroy();
header('Location: index.php');
exit();
?>