<?php
define('SECURE_ACCESS', true);
require_once '../includes/config.php';
require_once '../includes/auth.php';

requireLogin();
redirectIfAdmin();

header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
$date = $input['date'] ?? '';
$report_data = $input['data'] ?? '';

if (empty($date) || empty($report_data)) {
    echo json_encode(['success' => false, 'message' => 'داده‌های ارسالی ناقص است']);
    exit();
}

$user_id = $_SESSION['user_id'];

$stmt = mysqli_prepare($conn, "SELECT id FROM daily_reports WHERE user_id = ? AND report_date = ?");
mysqli_stmt_bind_param($stmt, "is", $user_id, $date);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) > 0) {
    $stmt = mysqli_prepare($conn, "UPDATE daily_reports SET report_data = ? WHERE user_id = ? AND report_date = ?");
    mysqli_stmt_bind_param($stmt, "sis", $report_data, $user_id, $date);
} else {
    $stmt = mysqli_prepare($conn, "INSERT INTO daily_reports (user_id, report_date, report_data) VALUES (?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "iss", $user_id, $date, $report_data);
}

$success = mysqli_stmt_execute($stmt);

// ⭐ ذخیره آیتم‌های داینامیک (با گرم و میز)
if ($success && isset($input['dyn_items'])) {
    $report_stmt = mysqli_prepare($conn, "SELECT id FROM daily_reports WHERE user_id = ? AND report_date = ?");
    mysqli_stmt_bind_param($report_stmt, "is", $user_id, $date);
    mysqli_stmt_execute($report_stmt);
    $report_row = mysqli_fetch_assoc(mysqli_stmt_get_result($report_stmt));
    $report_id = $report_row['id'] ?? 0;
    
    if ($report_id > 0) {
        $dyn_items = json_decode($input['dyn_items'], true);
        if (is_array($dyn_items) && count($dyn_items) > 0) {
            // حذف مقادیر قبلی
            $del_stmt = mysqli_prepare($conn, "DELETE FROM dynamic_records WHERE report_id = ?");
            mysqli_stmt_bind_param($del_stmt, "i", $report_id);
            mysqli_stmt_execute($del_stmt);
            
            // ⭐ ثبت مقادیر جدید با گرم و میز
            $ins_stmt = mysqli_prepare($conn, "INSERT INTO dynamic_records (report_id, item_id, amount_gram, amount_miz) VALUES (?, ?, ?, ?)");
            foreach ($dyn_items as $di) {
                $item_id = intval($di['id'] ?? 0);
                $gram = floatval($di['gram'] ?? 0);
                $miz = floatval($di['miz'] ?? 0);  // ⭐ فیلد میز
                if ($item_id > 0 && ($gram > 0 || $miz > 0)) {  // ⭐ اگر حداقل یکی مقدار داشته باشه
                    mysqli_stmt_bind_param($ins_stmt, "iidd", $report_id, $item_id, $gram, $miz);
                    mysqli_stmt_execute($ins_stmt);
                }
            }
        }
    }
}

// ⭐ پاک کردن هشدار تراز و داینامیک بعد از ذخیره موفق
if ($success) {
    require_once __DIR__ . '/../includes/reminder_functions.php';
    clearReminderAfterSubmit($user_id, $date, 'تراز روزانه');
    
    // فقط اگه داینامیک مقدار داره پاک کن
    $has_dynamic = false;
    if (isset($input['dyn_items'])) {
        $dyn_check = json_decode($input['dyn_items'], true);
        if (is_array($dyn_check)) {
            foreach ($dyn_check as $di) {
                if (($di['gram'] ?? 0) > 0 || ($di['miz'] ?? 0) > 0) {
                    $has_dynamic = true;
                    break;
                }
            }
        }
    }
    if ($has_dynamic) {
        clearReminderAfterSubmit($user_id, $date, 'آیتم داینامیک');
    }
}

if ($success) {
    echo json_encode(['success' => true, 'message' => 'گزارش با موفقیت ذخیره شد']);
} else {
    echo json_encode(['success' => false, 'message' => 'خطا در ذخیره گزارش']);
}
if (($rubika_config['enable_hooks'] ?? '0') == '1') {
    $cmd = "/usr/bin/php " . __DIR__ . "/../includes/send_rubika_hook.php {$report_id} > /dev/null 2>&1 &";
    exec($cmd);
}
exit();