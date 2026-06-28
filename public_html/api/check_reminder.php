<?php
define('SECURE_ACCESS', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/jdf.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['remind' => false]);
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// فقط برای branch
if ($role !== 'branch') {
    echo json_encode(['remind' => false]);
    exit;
}

$today = jdate('Y/m/d');
$now_hour = (int)date('H');

// ⭐ فقط بعد از ۸ شب (20:00) یادآوری کن
if ($now_hour < 20) {
    echo json_encode(['remind' => false]);
    exit;
}

// چک کن امروز remind شده یا نه
$q = mysqli_query($conn, "
    SELECT id FROM daily_reminders 
    WHERE user_id = $user_id AND reminder_date = '$today'
");

if (mysqli_num_rows($q) > 0) {
    echo json_encode(['remind' => false]);
    exit;
}

// چک کن امروز گزارش ثبت کرده یا نه
$q2 = mysqli_query($conn, "
    SELECT id FROM daily_reports 
    WHERE user_id = $user_id AND report_date = '$today'
");

if (mysqli_num_rows($q2) > 0) {
    echo json_encode(['remind' => false]);
    exit;
}

// یادآوری رو ثبت کن
mysqli_query($conn, "
    INSERT INTO daily_reminders (user_id, reminder_date) 
    VALUES ($user_id, '$today')
");

echo json_encode([
    'remind' => true,
    'message' => '⏰ وقت ثبت گزارش امروز رسیده!'
]);