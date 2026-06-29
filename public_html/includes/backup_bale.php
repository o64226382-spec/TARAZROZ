<?php
/**
 * نسخه اصلاح‌شده بکاپ کامل هفتگی – تراز روزانه
 * تنها از طریق CLI (کرون) اجرا شود
 */

// فعال‌سازی گزارش کامل خطاها در محیط تست؛ در تولید می‌توانید خط ۹ را حذف کنید
error_reporting(E_ALL);
ini_set('display_errors', 0);      // خروجی مرورگر را خطا پر نکند
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/backup_errors.log');
date_default_timezone_set('Asia/Tehran');

// امنیت دسترسی: فقط خط فرمان
if (php_sapi_name() !== 'cli') {
    die("Access denied: CLI only.");
}

// ---------------------- تنظیمات ----------------------
define('SECURE_ACCESS', true);

$bale_token = '2047185171:48SKKO5dzswnDnBcH-KDpMYs2zWfwNXVinY';
$chat_id     = '5838291218';

$db_host = getenv('DB_HOST') ?: 'localhost';
$db_user = getenv('DB_USER') ?: 'root';
$db_pass = getenv('DB_PASS') ?: '123456';
$db_name = getenv('DB_NAME') ?: 'tarazroz_db';

$site_root = '/home/tarazroz/public_html';
$tmp_dir   = '/home/tarazroz/tmp_full_backup/';
$log_file  = $tmp_dir . 'backup.log';

// ---------------------- توابع کمکی ----------------------
function send_telegram_message($token, $chat_id, $text) {
    $url = "https://tapi.bale.ai/bot{$token}/sendMessage?chat_id={$chat_id}&text=" . urlencode($text);
    // سعی با curl، در صورت نبود با file_get_contents
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_exec($ch);
        curl_close($ch);
    } else {
        @file_get_contents($url);
    }
}

function log_step($msg, $log_path) {
    @file_put_contents($log_path, date('[Y-m-d H:i:s] ') . $msg . "\n", FILE_APPEND);
}

// ---------------------- شروع فرایند ----------------------
log_step("Backup started", $log_file);

// پاکسازی پوشه موقت و ساخت دوباره
exec("rm -rf {$tmp_dir}");
if (!mkdir($tmp_dir, 0755, true) && !is_dir($tmp_dir)) {
    $err = "Failed to create temp dir: {$tmp_dir}";
    log_step($err, $log_file);
    send_telegram_message($bale_token, $chat_id, "❌ {$err}");
    exit(1);
}

// بررسی وجود exec()
if (!function_exists('exec')) {
    $err = "exec() function is disabled. Cannot proceed.";
    log_step($err, $log_file);
    send_telegram_message($bale_token, $chat_id, "❌ {$err}");
    exit(1);
}

// پیدا کردن مسیر کامل mysqldump
$mysqldump = trim(exec('which mysqldump'));
if (empty($mysqldump)) {
    // مسیرهای محتمل
    $common = ['/usr/bin/mysqldump', '/usr/local/bin/mysqldump'];
    foreach ($common as $p) {
        if (is_executable($p)) {
            $mysqldump = $p;
            break;
        }
    }
    if (empty($mysqldump)) {
        $err = "mysqldump not found.";
        log_step($err, $log_file);
        send_telegram_message($bale_token, $chat_id, "❌ {$err}");
        exit(1);
    }
}

// فایل تنظیمات موقت برای دیتابیس (پسورد حاوی ; را بی‌خطر می‌کند)
$mysql_cnf = tempnam($tmp_dir, 'mysql_');
file_put_contents($mysql_cnf, "[client]
user={$db_user}
password=\"{$db_pass}\"
host={$db_host}
");
chmod($mysql_cnf, 0600);

$date    = date('Y-m-d_His');
$db_file = $tmp_dir . "database_{$date}.sql";
$full_zip = $tmp_dir . "full_backup_{$date}.tar.gz";

// ۱. دامپ SQL
$dump_cmd = "{$mysqldump} --defaults-extra-file={$mysql_cnf} {$db_name} --single-transaction --routines --triggers > {$db_file} 2>&1";
exec($dump_cmd, $output, $return_var);

// حذف فایل کانفیگ موقت
unlink($mysql_cnf);

if ($return_var !== 0) {
    $err = "Database dump failed. Exit code: {$return_var}";
    $out = implode("\n", $output);
    log_step("{$err}\n{$out}", $log_file);
    send_telegram_message($bale_token, $chat_id, "❌ خطای بکاپ کامل (dump): " . date('Y-m-d H:i:s'));
    exec("rm -rf {$tmp_dir}");
    exit(1);
}
log_step("Database dumped successfully", $log_file);

// ۲. کپی فایل‌های سایت
$site_tmp = $tmp_dir . 'site_files';
exec("cp -r {$site_root} {$site_tmp}", $cp_output, $cp_return);
if ($cp_return !== 0) {
    $err = "Copy site files failed. Exit code: {$cp_return}";
    log_step($err, $log_file);
    send_telegram_message($bale_token, $chat_id, "❌ خطای بکاپ کامل (copy files)");
    exec("rm -rf {$tmp_dir}");
    exit(1);
}

// ۳. کپی دامپ به داخل پوشه سایت
copy($db_file, $site_tmp . '/database_backup.sql');

// ۴. ساخت فایل README
$readme = "📦 بکاپ کامل تراز روزانه\n📅 " . date('Y/m/d H:i:s') . "\n\n🚀 روش بازیابی:\n۱. Extract کن\n۲. فایل‌های site_files رو توی public_html آپلود کن\n۳. database_backup.sql رو import کن\n۴. includes/config.php رو با اطلاعات هاست جدید ویرایش کن\n۵. تمام!";
file_put_contents($site_tmp . '/README.txt', $readme);

// ۵. فشرده‌سازی
$tar_cmd = "cd {$site_tmp} && tar -czf {$full_zip} .";
exec($tar_cmd, $tar_output, $tar_return);
if ($tar_return !== 0) {
    $err = "tar compression failed. Exit code: {$tar_return}";
    log_step($err, $log_file);
    send_telegram_message($bale_token, $chat_id, "❌ خطای بکاپ کامل (tar)");
    exec("rm -rf {$tmp_dir}");
    exit(1);
}
// پاکسازی فایل‌های اضافی
exec("rm -rf {$site_tmp} {$db_file}");
log_step("Archive created: {$full_zip}", $log_file);

// ۶. ارسال فایل به بله
if (!file_exists($full_zip)) {
    log_step("Archive file missing before sending.", $log_file);
    exec("rm -rf {$tmp_dir}");
    exit(1);
}

$file_size_bytes = filesize($full_zip);
$file_size_mb = round($file_size_bytes / 1024 / 1024, 1);
$caption = "📦 بکاپ کامل هفتگی\n📅 " . date('Y/m/d H:i:s') . "\n📏 {$file_size_mb} MB\n\n🚀 قابل بازیابی فوری";

$send_ok = false;
if (function_exists('curl_init')) {
    $ch = curl_init("https://tapi.bale.ai/bot{$bale_token}/sendDocument");
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => [
            'chat_id' => $chat_id,
            'document' => new CURLFile($full_zip),
            'caption' => $caption
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 300
    ]);
    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if (curl_errno($ch)) {
        log_step("cURL error: " . curl_error($ch), $log_file);
    } else {
        log_step("File sent, HTTP code: {$http_code}", $log_file);
        $send_ok = ($http_code == 200);
    }
    curl_close($ch);
} else {
    // ارسال ساده با file_get_contents (محدودیت‌های حجمی بیشتری دارد)
    $result = @file_get_contents("https://tapi.bale.ai/bot{$bale_token}/sendDocument?chat_id={$chat_id}&caption=" . urlencode($caption) . "&document=" . urlencode(base64_encode(file_get_contents($full_zip))));
    // این روش معمولاً برای فایل‌های بزرگ جواب نمی‌دهد
    log_step("Sent via file_get_contents (may fail for large files)", $log_file);
}

if ($send_ok) {
    send_telegram_message($bale_token, $chat_id, "✅ بکاپ کامل ارسال شد ({$file_size_mb} MB)");
} else {
    send_telegram_message($bale_token, $chat_id, "⚠️ بکاپ کامل ساخته شد اما ارسال پیام ممکن است ناموفق باشد. بررسی کنید.");
}

// ۷. پاکسازی نهایی
exec("rm -rf {$tmp_dir}");
log_step("Backup finished and temp cleaned", $log_file);
echo "OK: Full backup " . date('Y-m-d H:i:s');