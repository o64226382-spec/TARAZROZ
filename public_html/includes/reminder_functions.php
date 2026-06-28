<?php
/**
 * توابع سیستم یادآوری و پیام‌رسانی
 * نیازمند: config.php, jdf.php
 */

if (!defined('SECURE_ACCESS')) {
    die('دسترسی مستقیم مجاز نیست');
}

/**
 * دریافت تعداد کل اعلان‌های فعال کاربر (هشدار + پیام خوانده نشده)
 */
function getNotificationCount($user_id) {
    global $conn;
    $count = 0;
    
    // تعداد هشدارهای فعال
    $q = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM reminders WHERE user_id = $user_id AND is_active = 1");
    if ($r = mysqli_fetch_assoc($q)) $count += (int)$r['cnt'];
    
    // تعداد پیام‌های خوانده نشده از ناظر
    $q = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM observer_messages WHERE target_user_id = $user_id AND is_read = 0 AND deleted_by IS NULL");
    if ($r = mysqli_fetch_assoc($q)) $count += (int)$r['cnt'];
    
    return $count;
}

/**
 * دریافت لیست همه اعلان‌های کاربر (برای پاپ‌آپ)
 */
function getAllNotifications($user_id) {
    global $conn;
    $notifications = [];
    
    // ۱. هشدارهای خودکار فعال
    $q = mysqli_query($conn, "
        SELECT id, date_shamsi, missing_items, created_at, 'reminder' as type 
        FROM reminders 
        WHERE user_id = $user_id AND is_active = 1 
        ORDER BY date_shamsi DESC
    ");
    while ($row = mysqli_fetch_assoc($q)) {
        $row['missing_items'] = json_decode($row['missing_items'], true) ?: [];
        $notifications[] = $row;
    }
    
    // ۲. پیام‌های ناظر (خوانده نشده + خوانده شده اخیر)
    $q = mysqli_query($conn, "
        SELECT om.id, om.title, om.message, om.is_read, om.created_at, u.branch_name as observer_name,
               'message' as type
        FROM observer_messages om
        LEFT JOIN users u ON om.observer_id = u.id
        WHERE om.target_user_id = $user_id AND om.deleted_by IS NULL
        ORDER BY om.created_at DESC
        LIMIT 30
    ");
    while ($row = mysqli_fetch_assoc($q)) {
        $notifications[] = $row;
    }
    
    return $notifications;
}

/**
 * علامت‌گذاری پیام ناظر به عنوان خوانده شده
 */
function markMessageRead($message_id, $user_id) {
    global $conn;
    mysqli_query($conn, "UPDATE observer_messages SET is_read = 1 WHERE id = $message_id AND target_user_id = $user_id");
}

/**
 * حذف یک آیتم از reminder بعد از ثبت کاربر
 * @param int $user_id
 * @param string $date_shamsi تاریخ به فرمت YYYY-MM-DD
 * @param string $item_name نام آیتم ثبت شده
 * @return bool
 */
function clearReminderAfterSubmit($user_id, $date_shamsi, $item_name) {
    global $conn;
    
    // پیدا کردن reminder فعال برای این کاربر و تاریخ
    $q = mysqli_query($conn, "
        SELECT id, missing_items FROM reminders 
        WHERE user_id = $user_id AND date_shamsi = '$date_shamsi' AND is_active = 1
        LIMIT 1
    ");
    
    if (mysqli_num_rows($q) === 0) return false;
    
    $row = mysqli_fetch_assoc($q);
    $missing = json_decode($row['missing_items'], true) ?: [];
    
    // حذف آیتم از آرایه
    $missing = array_values(array_diff($missing, [$item_name]));
    
    if (empty($missing)) {
        // همه آیتم‌ها ثبت شدن → غیرفعال کردن reminder
        mysqli_query($conn, "UPDATE reminders SET missing_items = '[]', is_active = 0 WHERE id = " . $row['id']);
    } else {
        // هنوز آیتم‌های ثبت نشده وجود داره
        $json = json_encode($missing, JSON_UNESCAPED_UNICODE);
        mysqli_query($conn, "UPDATE reminders SET missing_items = '$json' WHERE id = " . $row['id']);
    }
    
    return true;
}

/**
 * دریافت تنظیمات سیستم
 */
function getSetting($key, $default = '') {
    global $conn;
    $q = mysqli_query($conn, "SELECT setting_value FROM settings WHERE setting_key = '$key' LIMIT 1");
    if ($r = mysqli_fetch_assoc($q)) return $r['setting_value'];
    return $default;
}

/**
 * بررسی اینکه امروز جمعه است؟
 */
function isFriday() {
    $dayOfWeek = (int)str_replace(['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'], 
                                   ['0','1','2','3','4','5','6','7','8','9'], 
                                   jdate('w'));
    return $dayOfWeek == 6; // 6 = جمعه
}