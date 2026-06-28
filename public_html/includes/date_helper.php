<?php
require_once __DIR__ . '/jdf.php';

// ========== توابع تبدیل تاریخ ==========

/**
 * تبدیل تاریخ شمسی به میلادی
 * @param string $shamsi_date فرمت: 1405-02-02
 * @return string|null فرمت: 2026-05-22
 */
function shamsi_to_miladi($shamsi_date) {
    if (empty($shamsi_date)) return null;
    
    $parts = explode('-', $shamsi_date);
    if (count($parts) != 3) return null;
    
    $jy = (int)tr_num($parts[0], 'en');
    $jm = (int)tr_num($parts[1], 'en');
    $jd = (int)tr_num($parts[2], 'en');
    
    list($gy, $gm, $gd) = jalali_to_gregorian($jy, $jm, $jd);
    return sprintf("%04d-%02d-%02d", $gy, $gm, $gd);
}

/**
 * تبدیل تاریخ میلادی به شمسی با اعداد انگلیسی
 * @param string $miladi_date فرمت: 2026-05-22
 * @return string|null فرمت: 1405-02-02
 */
function miladi_to_shamsi($miladi_date) {
    if (empty($miladi_date)) return null;
    
    $parts = explode('-', $miladi_date);
    if (count($parts) != 3) return null;
    
    $gy = (int)$parts[0];
    $gm = (int)$parts[1];
    $gd = (int)$parts[2];
    
    list($jy, $jm, $jd) = gregorian_to_jalali($gy, $gm, $gd);
    return sprintf("%04d-%02d-%02d", $jy, $jm, $jd);
}

// ========== توابع دریافت تاریخ جاری ==========

/**
 * دریافت تاریخ امروز شمسی با فرمت 1405-02-02
 */
function today_shamsi() {
    $gy = (int)date('Y');
    $gm = (int)date('m');
    $gd = (int)date('d');
    list($jy, $jm, $jd) = gregorian_to_jalali($gy, $gm, $gd);
    return sprintf("%04d-%02d-%02d", $jy, $jm, $jd);
}

/**
 * دریافت تاریخ امروز میلادی با فرمت 2026-05-22
 */
function today_miladi() {
    return date('Y-m-d');
}

// ========== توابع نرمال‌سازی ==========

/**
 * نرمال‌سازی تاریخ شمسی (تبدیل اعداد فارسی به انگلیسی)
 * @param string $date تاریخ ورودی (ممکن است با اعداد فارسی باشد)
 * @return string تاریخ با اعداد انگلیسی
 */
function normalize_shamsi_date($date) {
    if (empty($date)) return '';
    return tr_num($date, 'en');
}

/**
 * اعتبارسنجی تاریخ شمسی
 * @param string $date فرمت: 1405-02-02
 * @return bool
 */
function validate_shamsi_date($date) {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return false;
    
    $parts = explode('-', $date);
    $year = (int)$parts[0];
    $month = (int)$parts[1];
    $day = (int)$parts[2];
    
    if ($year < 1300 || $year > 1500) return false;
    if ($month < 1 || $month > 12) return false;
    
    // محاسبه تعداد روزهای ماه
    if ($month <= 6) $maxDay = 31;
    elseif ($month <= 11) $maxDay = 30;
    else $maxDay = (jdate('L', mktime(0,0,0,1,1,$year)) == 1) ? 30 : 29;
    
    return ($day >= 1 && $day <= $maxDay);
}

// ========== توابع برای استفاده در کوئری‌ها ==========

/**
 * تبدیل بازه تاریخ شمسی به میلادی برای استفاده در کوئری
 * @param string $from تاریخ شروع شمسی (1405-02-01)
 * @param string $to تاریخ پایان شمسی (1405-02-31)
 * @return array ['from' => '2026-05-22', 'to' => '2026-06-21']
 */
function shamsi_range_to_miladi($from, $to) {
    return [
        'from' => shamsi_to_miladi($from),
        'to' => shamsi_to_miladi($to)
    ];
}

// ========== توابع نمایش ==========

/**
 * نمایش تاریخ شمسی به صورت خوانا
 * @param string $shamsi_date 1405-02-02
 * @return string 2 خرداد 1405
 */
function display_shamsi_date($shamsi_date) {
    if (empty($shamsi_date)) return '';
    
    $parts = explode('-', $shamsi_date);
    if (count($parts) != 3) return $shamsi_date;
    
    $months = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
    $year = (int)$parts[0];
    $month = (int)$parts[1];
    $day = (int)$parts[2];
    
    return $day . ' ' . $months[$month-1] . ' ' . $year;
}
?>