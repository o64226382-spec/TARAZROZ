<?php
/**
 * نمایش شمارش معکوس ساعات کاری
 * این فایل با AJAX فراخوانی می‌شود
 */
define('SECURE_ACCESS', true);
require_once 'includes/config.php';
require_once 'includes/jdf.php';

// تنظیم هدر JSON
header('Content-Type: application/json; charset=utf-8');

// بارگذاری تنظیمات
$schedule_path = __DIR__ . '/includes/work_schedule.json';
$schedule = file_exists($schedule_path) 
    ? json_decode(file_get_contents($schedule_path), true) 
    : null;

// اگر تایمر غیرفعال باشه
if (!$schedule || !($schedule['show_timer'] ?? 0)) {
    echo json_encode(['show' => false]);
    exit;
}

// دریافت تاریخ و زمان فعلی
$now = time();
$current_hour_minute = date('H:i', $now);

// تبدیل اعداد فارسی به انگلیسی برای روز هفته
$jalali_day_of_week = (int) str_replace(
    ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'],
    ['0','1','2','3','4','5','6','7','8','9'],
    jdate('w') // 0=شنبه تا 6=جمعه در jdf
);

$work_days = $schedule['work_days'] ?? [];

// اگر امروز تعطیله
if (!in_array($jalali_day_of_week, $work_days)) {
    echo json_encode([
        'show' => true,
        'status' => 'holiday',
        'message' => $schedule['message_holiday'] ?? 'امروز تعطیل است',
        'timer' => null
    ]);
    exit;
}

// بررسی اینکه امروز پنجشنبه است (day = 5)
$is_thursday = ($jalali_day_of_week === 5);

// زمان‌های کاری
$morning_start = strtotime(date('Y-m-d') . ' ' . $schedule['morning_start']);
$morning_end = strtotime(date('Y-m-d') . ' ' . $schedule['morning_end']);
$afternoon_start = strtotime(date('Y-m-d') . ' ' . $schedule['afternoon_start']);

// پایان کار: اگر پنجشنبه است از thursday_end استفاده کن
$afternoon_end_time = $is_thursday ? ($schedule['thursday_end'] ?? $schedule['afternoon_end']) : $schedule['afternoon_end'];
$afternoon_end = strtotime(date('Y-m-d') . ' ' . $afternoon_end_time);

// تعیین وضعیت فعلی
$response = ['show' => true];

if ($now < $morning_start) {
    // قبل از شروع کار صبح
    $response['status'] = 'before_work';
    $response['message'] = $schedule['message_before_work'] ?? 'هنوز شروع نشده';
    $response['timer'] = [
        'target' => $morning_start * 1000, // میلی‌ثانیه برای JS
        'label' => 'تا شروع کار',
        'type' => 'countdown'
    ];
} elseif ($now >= $morning_start && $now <= $morning_end) {
    // در شیفت صبح
    $response['status'] = 'working_morning';
    $response['message'] = $schedule['message_during_work'] ?? 'در حال خدمت‌رسانی';
    $response['timer'] = [
        'target' => $morning_end * 1000,
        'label' => 'تا پایان شیفت صبح',
        'type' => 'countdown'
    ];
} elseif ($now > $morning_end && $now < $afternoon_start) {
    // زمان استراحت
    $response['status'] = 'break';
    $response['message'] = $schedule['message_break'] ?? 'وقت استراحت';
    $response['timer'] = [
        'target' => $afternoon_start * 1000,
        'label' => 'تا شروع شیفت عصر',
        'type' => 'countdown'
    ];
} elseif ($now >= $afternoon_start && $now < $afternoon_end) {
    // در شیفت عصر
    $response['status'] = 'working_afternoon';
    $response['message'] = $schedule['message_during_work'] ?? 'در حال خدمت‌رسانی';
    $response['timer'] = [
        'target' => $afternoon_end * 1000,
        'label' => 'تا پایان کار' . ($is_thursday ? ' (پنجشنبه)' : ''),
        'type' => 'countdown'
    ];
} else {
    // بعد از پایان کار
    $response['status'] = 'after_work';
    $response['message'] = $is_thursday 
        ? ($schedule['message_thursday_early'] ?? 'پایان زودهنگام پنجشنبه')
        : ($schedule['message_after_work'] ?? 'خسته نباشید همکاران 🌙');
    $response['timer'] = null;
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);