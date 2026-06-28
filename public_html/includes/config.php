<?php
if (!defined('SECURE_ACCESS')) {
    define('SECURE_ACCESS', true);
}
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('DB_HOST', 'localhost');
define('DB_NAME', 'tarazroz_tarazdb');
define('DB_USER', 'tarazroz_tarazuser');
define('DB_PASS', 'NyLue-hRh2OP9c;8');

$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if (mysqli_connect_errno()) {
    die('خطا در اتصال به پایگاه داده: ' . mysqli_connect_error());
}
mysqli_set_charset($conn, 'utf8mb4');
date_default_timezone_set('Asia/Tehran');

$holidays = ['01/01','01/02','01/03','01/04','01/12','01/13','03/14','03/15','11/22','12/29'];

define('BASE_URL', 'http://' . $_SERVER['HTTP_HOST']);
define('ADMIN_URL', BASE_URL . '/admin');
define('USER_URL', BASE_URL . '/user');
/**
 * تبدیل تاریخ شمسی به فرمت استاندارد دیتابیس: YYYY-MM-DD با اعداد انگلیسی
 * مثال خروجی: 1405-03-09
 */
function toEnJDate($format = 'Y-m-d', $timestamp = '') {
    $jalali = jdate($format, $timestamp);
    return str_replace(
        ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'],
        ['0','1','2','3','4','5','6','7','8','9'],
        $jalali
    );
}
?>