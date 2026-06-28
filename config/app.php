<?php
/**
 * تنظیمات اصلی برنامه
 */

// مسیرهای اصلی
define('BASE_PATH', dirname(__DIR__));
define('PUBLIC_PATH', BASE_PATH . '/public');
define('APP_PATH', BASE_PATH . '/app');
define('CONFIG_PATH', BASE_PATH . '/config');
define('STORAGE_PATH', BASE_PATH . '/storage');
define('UPLOAD_PATH', PUBLIC_PATH . '/uploads');

// URLهای اصلی
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
define('BASE_URL', $protocol . '://' . $_SERVER['HTTP_HOST']);
define('ASSETS_URL', BASE_URL . '/assets');
define('UPLOADS_URL', BASE_URL . '/uploads');

// تنظیمات اپلیکیشن
define('APP_NAME', 'تراز روزانه - سیستم مدیریت طلافروشی');
define('APP_VERSION', '2.0.0');
define('APP_DEBUG', false); // در production باید false باشد
define('APP_TIMEZONE', 'Asia/Tehran');

// تنظیمات Session
define('SESSION_LIFETIME', 1440); // دقیقه
define('SESSION_SECURE', false); // در HTTPS فعال شود

// تنظیمات امنیتی
define('CSRF_TOKEN_NAME', 'csrf_token');
define('HASH_ALGO', PASSWORD_BCRYPT);
define('HASH_COST', 12);

// تنظیمات فایل
define('MAX_UPLOAD_SIZE', 64 * 1024 * 1024); // 64MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'zip', 'woff2', 'ttf']);

// شعب
define('BRANCHES', [
    'taleghani' => 'طالقانی',
    'abureihan' => 'ابوریحان',
    'abresan' => 'آبرسان'
]);

// نقش‌های پیش‌فرض
define('DEFAULT_ROLES', ['admin', 'branch', 'observer', 'receipt']);

// تنظیمات تاریخ و زمان
date_default_timezone_set(APP_TIMEZONE);

// روزهای تعطیل رسمی
$GLOBALS['holidays'] = [
    '01/01', '01/02', '01/03', '01/04', '01/12', '01/13',
    '03/14', '03/15', '11/22', '12/29'
];
