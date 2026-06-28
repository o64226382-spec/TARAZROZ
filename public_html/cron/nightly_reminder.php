<?php
/**
 * ساخت هشدار برای کاربر branch - صدا زده میشه از index.php
 */
if (!defined('SECURE_ACCESS')) {
    define('SECURE_ACCESS', true);
}
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/jdf.php';
require_once __DIR__ . '/../includes/reminder_functions.php';

// فقط کاربر جاری
if (!isset($current_user_id)) return;

// بررسی فعال بودن سیستم
if (getSetting('reminder_active', '1') != '1') return;

// بررسی جمعه
if (isFriday()) return;

$today = toEnJDate('Y-m-d');

// چک کردن اینکه آیا امروز قبلاً reminder ساخته شده
$check = mysqli_query($conn, "SELECT id FROM reminders WHERE user_id = $current_user_id AND date_shamsi = '$today' LIMIT 1");
if (mysqli_num_rows($check) > 0) return;

$missing = [];

// ۱. daily_reports
$report = mysqli_query($conn, "SELECT report_data FROM daily_reports WHERE user_id = $current_user_id AND report_date = '$today' LIMIT 1");
if (mysqli_num_rows($report) == 0) {
    $missing[] = 'تراز روزانه';
    $missing[] = 'آیتم داینامیک';
} else {
    $report_data = json_decode(mysqli_fetch_assoc($report)['report_data'], true);
    if (empty($report_data['dynamic_items'])) $missing[] = 'آیتم داینامیک';
}

// ۲. درآمد
$income = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM income_daily_records WHERE branch_id = $current_user_id AND record_date = '$today'");
if (mysqli_fetch_assoc($income)['cnt'] == 0) $missing[] = 'درآمد روزانه';

// ۳. اهداف
$goals = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM goal_daily_progress WHERE branch_id = $current_user_id AND progress_date = '$today'");
if (mysqli_fetch_assoc($goals)['cnt'] == 0) $missing[] = 'پیشرفت اهداف';

if (!empty($missing)) {
    $json = mysqli_real_escape_string($conn, json_encode($missing, JSON_UNESCAPED_UNICODE));
    mysqli_query($conn, "INSERT INTO reminders (user_id, date_shamsi, missing_items, is_active) VALUES ($current_user_id, '$today', '$json', 1)");
}