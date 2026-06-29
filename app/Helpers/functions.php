<?php
/**
 * Helper Functions - توابع کمکی
 * 
 * شامل توابع تاریخ شمسی، اعداد، امنیت و ...
 */

if (!function_exists('toJalali')) {
    /**
     * تبدیل تاریخ میلادی به شمسی
     */
    function toJalali($gy, $gm, $gd): array {
        $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        $jy = ($gy <= 1600) ? 0 : 979;
        $gy -= ($gy <= 1600) ? 621 : 1600;
        $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
        $days = (365 * $gy) + ((int)(($gy2 + 3) / 4)) - ((int)(($gy2 + 99) / 100)) + ((int)(($gy2 + 399) / 400)) - 80 + $gd + $g_d_m[$gm - 1];
        $jy += 33 * ((int)($days / 12053));
        $days %= 12053;
        $jy += 4 * ((int)($days / 1461));
        $days %= 1461;
        
        if ($days > 365) {
            $jy += ((int)(($days - 1) / 365));
            $days = ($days - 1) % 365;
        }
        
        $jm = ($days < 186) ? 1 + (int)($days / 31) : 7 + (int)(($days - 186) / 30);
        $jd = 1 + (($days < 186) ? ($days % 31) : (($days - 186) % 30));
        
        return [$jy, $jm, $jd];
    }
}

if (!function_exists('toGregorian')) {
    /**
     * تبدیل تاریخ شمسی به میلادی
     */
    function toGregorian($jy, $jm, $jd): array {
        $jy += 979;
        $jm -= 1;
        $jd -= 1;
        $j_day_no = 365 * $jy + ((int)($jy / 33)) * 8 + ((int)(($jy % 33 + 3) / 4));
        
        for ($i = 0; $i < $jm; ++$i) {
            $j_day_no += ($i < 6) ? 31 : 30;
        }
        
        $j_day_no += $jd;
        $g_day_no = $j_day_no + 79;
        $gy = 1600 + 400 * ((int)($g_day_no / 146097));
        $g_day_no = $g_day_no % 146097;
        $leap = true;
        
        if ($g_day_no >= 36525) {
            $g_day_no--;
            $gy += 100 * ((int)($g_day_no / 36524));
            $g_day_no = $g_day_no % 36524;
            
            if ($g_day_no >= 365) {
                $g_day_no++;
            } else {
                $leap = false;
            }
        }
        
        $gy += 4 * ((int)($g_day_no / 1461));
        $g_day_no %= 1461;
        
        if ($g_day_no >= 366) {
            $leap = false;
            $g_day_no--;
            $gy += ((int)($g_day_no / 365));
            $g_day_no = $g_day_no % 365;
        }
        
        $gd = $g_day_no + 1;
        $sal_a = [0, 31, (($leap) ? 29 : 28), 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        
        for ($i = 0; $gd > $sal_a[$i]; $i++) {
            $gd -= $sal_a[$i];
        }
        
        $gm = $i;
        return [$gy, $gm, $gd];
    }
}

if (!function_exists('jDate')) {
    /**
     * نمایش تاریخ شمسی با فرمت مشخص
     * @param string $format فرمت خروجی (Y-m-d, Y/m/d, j F Y و ...)
     * @param string $date تاریخ ورودی (اختیاری - پیش‌فرض امروز)
     */
    function jDate(string $format = 'Y-m-d', string $date = ''): string {
        if (empty($date)) {
            $timestamp = time();
        } else {
            $timestamp = strtotime($date);
        }
        
        $gy = date('Y', $timestamp);
        $gm = date('m', $timestamp);
        $gd = date('d', $timestamp);
        
        list($jy, $jm, $jd) = toJalali($gy, $gm, $gd);
        
        // تبدیل اعداد به فارسی
        $jy = toPersianNum($jy);
        $jm = toPersianNum($jm);
        $jd = toPersianNum($jd);
        
        $months = [
            '', 'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور',
            'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'
        ];
        
        $days = [
            'شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه'
        ];
        
        $dayName = $days[date('w', $timestamp)];
        $monthName = $months[(int)$jm];
        
        $replacements = [
            'Y' => $jy,
            'm' => str_pad($jm, 2, '۰', STR_PAD_LEFT),
            'n' => $jm,
            'd' => str_pad($jd, 2, '۰', STR_PAD_LEFT),
            'j' => $jd,
            'F' => $monthName,
            'M' => mb_substr($monthName, 0, 3, 'UTF-8'),
            'l' => $dayName,
            'D' => mb_substr($dayName, 0, 3, 'UTF-8'),
        ];
        
        return strtr($format, $replacements);
    }
}

if (!function_exists('toPersianNum')) {
    /**
     * تبدیل اعداد انگلیسی به فارسی
     */
    function toPersianNum($num): string {
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $english = range(0, 9);
        return str_replace($english, $persian, (string)$num);
    }
}

if (!function_exists('toEnglishNum')) {
    /**
     * تبدیل اعداد فارسی به انگلیسی
     */
    function toEnglishNum($num): string {
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $english = range(0, 9);
        return str_replace($persian, $english, (string)$num);
    }
}

if (!function_exists('formatNumber')) {
    /**
     * فرمت عدد با جداکننده هزارگان
     */
    function formatNumber($num, $decimal = 0): string {
        return number_format((float)$num, $decimal, '.', ',');
    }
}

if (!function_exists('formatCurrency')) {
    /**
     * فرمت پول با واحد تومان/ریال
     */
    function formatCurrency($amount, string $unit = 'تومان'): string {
        return formatNumber($amount) . ' ' . $unit;
    }
}

if (!function_exists('sanitize')) {
    /**
     * تمیز کردن ورودی‌ها
     */
    function sanitize($data) {
        if (is_array($data)) {
            return array_map('sanitize', $data);
        }
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('generateToken')) {
    /**
     * تولید توکن امن
     */
    function generateToken(int $length = 32): string {
        return bin2hex(random_bytes($length / 2));
    }
}

if (!function_exists('hashPassword')) {
    /**
     * هش کردن رمز عبور
     */
    function hashPassword(string $password): string {
        return password_hash($password, PASSWORD_DEFAULT);
    }
}

if (!function_exists('verifyPassword')) {
    /**
     * بررسی رمز عبور
     */
    function verifyPassword(string $password, string $hash): bool {
        return password_verify($password, $hash);
    }
}

if (!function_exists('uploadFile')) {
    /**
     * آپلود فایل
     */
    function uploadFile(array $file, string $destination, array $allowedTypes = []): array {
        $result = ['success' => false, 'message' => '', 'path' => ''];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $result['message'] = 'خطا در آپلود فایل';
            return $result;
        }
        
        // بررسی نوع فایل
        if (!empty($allowedTypes)) {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedTypes)) {
                $result['message'] = 'نوع فایل مجاز نیست';
                return $result;
            }
        }
        
        // ایجاد نام یکتا
        $newName = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file['name']);
        $fullPath = rtrim($destination, '/') . '/' . $newName;
        
        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }
        
        if (move_uploaded_file($file['tmp_name'], $fullPath)) {
            $result['success'] = true;
            $result['path'] = $fullPath;
        } else {
            $result['message'] = 'خطا در ذخیره فایل';
        }
        
        return $result;
    }
}

if (!function_exists('deleteFile')) {
    /**
     * حذف فایل
     */
    function deleteFile(string $path): bool {
        if (file_exists($path)) {
            return unlink($path);
        }
        return false;
    }
}

if (!function_exists('logActivity')) {
    /**
     * ثبت لاگ فعالیت
     */
    function logActivity(string $action, string $description = '', $userId = null): void {
        $auth = \App\Core\Auth::getInstance();
        $userId = $userId ?? ($auth->check() ? $auth->id() : null);
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        try {
            \App\Core\Database::insert('activity_logs', [
                'user_id' => $userId,
                'action' => $action,
                'description' => $description,
                'ip_address' => $ip,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            // اگر جدول وجود نداشت، در فایل لاگ بنویس
            error_log("[ACTIVITY] {$action}: {$description}");
        }
    }
}

if (!function_exists('asset')) {
    /**
     * تولید URL برای فایل‌های استاتیک
     */
    function asset(string $path): string {
        return BASE_URL . '/public/' . ltrim($path, '/');
    }
}

if (!function_exists('url')) {
    /**
     * تولید URL
     */
    function url(string $path = ''): string {
        return BASE_URL . '/' . ltrim($path, '/');
    }
}

if (!function_exists('dd')) {
    /**
     * Debug and Die
     */
    function dd(...$vars): void {
        echo '<pre>';
        foreach ($vars as $var) {
            var_dump($var);
            echo "\n";
        }
        echo '</pre>';
        exit;
    }
}

if (!function_exists('dump')) {
    /**
     * Debug dump
     */
    function dump(...$vars): void {
        echo '<pre>';
        foreach ($vars as $var) {
            var_dump($var);
            echo "\n";
        }
        echo '</pre>';
    }
}
