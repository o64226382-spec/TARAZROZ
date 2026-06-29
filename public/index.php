<?php
/**
 * Front Controller - نقطه ورود اصلی برنامه
 * 
 * تمام درخواست‌ها از این فایل عبور می‌کنند
 */

// تعریف ثابت‌های پایه
define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));

// بارگذاری autoload Composer
if (file_exists(BASE_PATH . '/vendor/autoload.php')) {
    require_once BASE_PATH . '/vendor/autoload.php';
}

// بارگذاری فایل‌های core به صورت دستی (در صورت عدم وجود Composer)
if (!class_exists('App\Core\Database')) {
    require_once BASE_PATH . '/app/Core/Database.php';
    require_once BASE_PATH . '/app/Core/Router.php';
    require_once BASE_PATH . '/app/Core/Controller.php';
    require_once BASE_PATH . '/app/Core/Model.php';
    require_once BASE_PATH . '/app/Core/Auth.php';
    require_once BASE_PATH . '/app/Core/Request.php';
    require_once BASE_PATH . '/app/Core/Session.php';
}

// بارگذاری توابع کمکی
require_once BASE_PATH . '/app/Helpers/functions.php';

// بارگذاری تنظیمات محیطی (.env)
if (file_exists(BASE_PATH . '/.env')) {
    $envLines = file(BASE_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($envLines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, '"\'');
            if (!defined($key)) {
                define($key, $value);
            }
        }
    }
}

// تعریف ثابت‌های پیش‌فرض اگر در .env نباشند
if (!defined('DB_HOST')) define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
if (!defined('DB_NAME')) define('DB_NAME', getenv('DB_NAME') ?: 'tarazroz_db');
if (!defined('DB_USER')) define('DB_USER', getenv('DB_USER') ?: 'root');
if (!defined('DB_PASS')) define('DB_PASS', getenv('DB_PASS') ?: '123456');
if (!defined('APP_DEBUG')) define('APP_DEBUG', true);
if (!defined('APP_TIMEZONE')) define('APP_TIMEZONE', 'Asia/Tehran');

// تنظیم timezone
date_default_timezone_set(APP_TIMEZONE);

// گزارش خطاها بر اساس debug mode
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// شروع نشست
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// بارگذاری مسیرها
require_once BASE_PATH . '/config/routes.php';

// اجرای روتر
try {
    $router = App\Core\Router::getInstance();
    $router->dispatch();
} catch (\Exception $e) {
    if (APP_DEBUG) {
        echo '<h1>خطا در سیستم</h1>';
        echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
        echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
    } else {
        http_response_code(500);
        echo '<h1>خطای داخلی سرور</h1>';
        echo '<p>لطفاً با مدیر سیستم تماس بگیرید</p>';
    }
}
