<?php
define('SECURE_ACCESS', true);
require_once __DIR__ . '/../includes/config.php';

$res = mysqli_query($conn, "SELECT * FROM notification_channels WHERE purpose = 'report' AND active = 1 LIMIT 1");
$ch = mysqli_fetch_assoc($res);

if (!$ch) exit('No report channel found');

$bale_token = $ch['token'];
$chat_id = $ch['chat_id'];
$today = date('Y-m-d');

$debtors = $creditors = $pettys = 0;
$res2 = mysqli_query($conn, "SELECT report_data FROM daily_reports WHERE report_date = '$today'");
while ($r = mysqli_fetch_assoc($res2)) {
    $d = json_decode($r['report_data'], true);
    $debtors += array_sum(array_column($d['debtors'] ?? [], 'amt'));
    $creditors += array_sum(array_column($d['creditors'] ?? [], 'amt'));
    $pettys += array_sum(array_column($d['pettys'] ?? [], 'amt'));
}

$income = 0;
$res3 = mysqli_query($conn, "SELECT COALESCE(SUM(amount_rial), 0) FROM income_daily_records WHERE record_date = '$today'");
if ($res3) $income = mysqli_fetch_row($res3)[0] ?? 0;

$online = 0;
$res4 = mysqli_query($conn, "SELECT COUNT(*) FROM users WHERE last_activity > DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
if ($res4) $online = mysqli_fetch_row($res4)[0] ?? 0;

$msg = "📊 گزارش روزانه - " . date('Y/m/d') . "\n\n";
$msg .= "💰 بدهکاران: " . number_format($debtors) . " میلیون\n";
$msg .= "💳 بستانکاران: " . number_format($creditors) . " میلیون\n";
$msg .= "📦 تنخواه: " . number_format($pettys) . " میلیون\n";
$msg .= "💵 درآمد: " . number_format($income) . " ریال\n";
$msg .= "👥 آنلاین: {$online} نفر\n";
$msg .= "\n⏰ " . date('H:i:s');

$curl = curl_init("https://tapi.bale.ai/bot{$bale_token}/sendMessage");
curl_setopt_array($curl, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query(['chat_id' => $chat_id, 'text' => $msg]),
    CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10
]);
curl_exec($curl);
curl_close($curl);

echo "OK: Report sent at " . date('H:i:s');