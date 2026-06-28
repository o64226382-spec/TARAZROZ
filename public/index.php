<?php
/**
 * فایل اصلی ورودی برنامه (Entry Point)
 * تمام درخواست‌ها از این فایل عبور می‌کنند
 */

// تعریف ثابت امنیتی
define('SECURE_ACCESS', true);

// شروع session با تنظیمات امنیتی
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);
    session_start();
}

// بارگذاری Composer Autoload
require_once __DIR__ . '/../vendor/autoload.php';

// بارگذاری فایل‌های کانفیگ
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

// بارگذاری توابع کمکی
require_once __DIR__ . '/../app/Helpers/functions.php';
require_once __DIR__ . '/../app/Helpers/date_helper.php';
require_once __DIR__ . '/../app/Helpers/security_helper.php';
require_once __DIR__ . '/../app/Helpers/number_helper.php';

// بارگذاری هسته اصلی
use App\Core\Router;
use App\Core\Database;
use App\Core\Auth;

// اتصال به دیتابیس
Database::connect();

// ایجاد نمونه Router و پردازش درخواست
$router = new Router();
$router->dispatch();
