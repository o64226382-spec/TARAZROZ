<?php
define('SECURE_ACCESS', true);

$bale_token = '2047185171:48SKKO5dzswnDnBcH-KDpMYs2zWfwNXVinY';
$chat_id = '5838291218';

$db_host = getenv('DB_HOST') ?: 'localhost';
$db_user = getenv('DB_USER') ?: 'root';
$db_pass = getenv('DB_PASS') ?: '123456';
$db_name = getenv('DB_NAME') ?: 'tarazroz_db';
$site_root = '/home/tarazroz/public_html';
$tmp_dir = '/home/tarazroz/tmp_full_backup/';

exec("rm -rf {$tmp_dir}");
mkdir($tmp_dir, 0755, true);

$date = date('Y-m-d_His');
$db_file = $tmp_dir . "database_{$date}.sql";
$full_zip = $tmp_dir . "full_backup_{$date}.tar.gz";

// ۱. بکاپ دیتابیس
$command = "mysqldump -u {$db_user} -p'{$db_pass}' {$db_name} --single-transaction --routines --triggers > {$db_file} 2>&1";
exec($command, $output, $return_var);

if ($return_var !== 0) {
    file_get_contents("https://tapi.bale.ai/bot{$bale_token}/sendMessage?chat_id={$chat_id}&text=" . urlencode("❌ خطای بکاپ کامل: " . date('Y-m-d H:i:s')));
    exec("rm -rf {$tmp_dir}");
    exit;
}

// ۲. کپی فایل‌های سایت
exec("cp -r {$site_root} {$tmp_dir}site_files");

// ۳. کپی دیتابیس به داخل پوشه
exec("cp {$db_file} {$tmp_dir}site_files/database_backup.sql");

// ۴. README
$readme = "📦 بکاپ کامل تراز روزانه\n📅 " . date('Y/m/d H:i:s') . "\n\n🚀 روش بازیابی:\n۱. Extract کن\n۲. فایل‌های site_files رو توی public_html آپلود کن\n۳. database_backup.sql رو import کن\n۴. includes/config.php رو با اطلاعات هاست جدید ویرایش کن\n۵. تمام!";
file_put_contents($tmp_dir . "site_files/README.txt", $readme);

// ۵. فشرده‌سازی
exec("cd {$tmp_dir}site_files && tar -czf {$full_zip} .");
exec("rm -rf {$tmp_dir}site_files {$db_file}");

// ۶. ارسال به بله
$file_size = round(filesize($full_zip) / 1024 / 1024, 1);
$caption = "📦 بکاپ کامل هفتگی\n📅 " . date('Y/m/d H:i:s') . "\n📏 {$file_size} MB\n\n🚀 قابل بازیابی فوری";

if (filesize($full_zip) < 50 * 1024 * 1024) {
    $ch = curl_init("https://tapi.bale.ai/bot{$bale_token}/sendDocument");
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => ['chat_id' => $chat_id, 'document' => new CURLFile($full_zip), 'caption' => $caption],
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 300
    ]);
    curl_exec($ch);
    curl_close($ch);
    file_get_contents("https://tapi.bale.ai/bot{$bale_token}/sendMessage?chat_id={$chat_id}&text=" . urlencode("✅ بکاپ کامل ارسال شد ({$file_size} MB)"));
} else {
    $ch = curl_init("https://tapi.bale.ai/bot{$bale_token}/sendDocument");
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => ['chat_id' => $chat_id, 'document' => new CURLFile($full_zip), 'caption' => $caption],
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 300
    ]);
    curl_exec($ch);
    curl_close($ch);
}

exec("rm -rf {$tmp_dir}");
echo "OK: Full backup " . date('Y-m-d H:i:s');