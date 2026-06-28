<?php
// ⭐ تنظیمات
$bot_token = '368335055:d0QjqskBT2Vlh801aFNbsB6659GpErmaMZE';
$admin_user_id = '7596668';
$offset_file = __DIR__ . '/bot_offset.txt';
$site_url = 'https://ilh10.airodns.com/~tarazroz';

// ⭐ دیتابیس
$conn = mysqli_connect('localhost', 'tarazroz_tarazuser', 'NyLue-hRh2OP9c;8', 'tarazroz_tarazdb');
mysqli_set_charset($conn, 'utf8mb4');

// ⭐ دریافت پیام‌ها
$offset = file_exists($offset_file) ? (int)file_get_contents($offset_file) : 0;
$ch = curl_init("https://tapi.bale.ai/bot{$bot_token}/getUpdates?offset={$offset}");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch); curl_close($ch);
$data = json_decode($response, true);
if (!$data || !$data['ok'] || empty($data['result'])) exit;

// ⭐ ارسال پیام
function send($chat_id, $text) {
    global $bot_token;
    $ch = curl_init("https://tapi.bale.ai/bot{$bot_token}/sendMessage");
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query(['chat_id' => $chat_id, 'text' => $text]), CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
    curl_exec($ch); curl_close($ch);
}

// ⭐ دریافت آیتم‌های داینامیک
function get_dynamic_items($report_id) {
    global $conn;
    $items = [];
    $res = mysqli_query($conn, "SELECT di.name, dr.amount_gram FROM dynamic_records dr JOIN dynamic_items di ON dr.item_id = di.id WHERE dr.report_id = $report_id");
    while ($r = mysqli_fetch_assoc($res)) $items[] = $r;
    return $items;
}

// ⭐ گزارش خلاصه روز
function summary_day($date) {
    global $conn;
    $msg = "📊 گزارش روزانه - " . str_replace('-', '/', $date) . "\n\n";
    $t_d = $t_c = $t_p = $t_i = $t_dyn = $cnt = 0;
    $res = mysqli_query($conn, "SELECT dr.id, dr.user_id, u.branch_name, dr.report_data FROM daily_reports dr JOIN users u ON dr.user_id = u.id WHERE dr.report_date = '$date'");
    while ($r = mysqli_fetch_assoc($res)) {
        $d = json_decode($r['report_data'], true); if (!$d) continue;
        $debt = array_sum(array_column($d['debtors'] ?? [], 'amt'));
        $cred = array_sum(array_column($d['creditors'] ?? [], 'amt'));
        $pet = array_sum(array_column($d['pettys'] ?? [], 'amt'));
        $inc = 0; $ir = mysqli_query($conn, "SELECT COALESCE(SUM(amount_rial),0) FROM income_daily_records WHERE branch_id = {$r['user_id']} AND record_date = '$date'");
        if ($ir) $inc = mysqli_fetch_row($ir)[0];
        $dyn_items = get_dynamic_items($r['id']); $dyn_sum = array_sum(array_column($dyn_items, 'amount_gram'));
        $msg .= "🏢 {$r['branch_name']}\n  بدهکار: " . number_format($debt, 1) . " | بستانکار: " . number_format($cred, 1) . " | تنخواه: " . number_format($pet, 1) . " | درآمد: " . number_format($inc) . " ریال";
        if ($dyn_sum > 0) $msg .= " | 📥 داینامیک: " . number_format($dyn_sum, 3) . " گرم";
        $msg .= "\n\n";
        $t_d += $debt; $t_c += $cred; $t_p += $pet; $t_i += $inc; $t_dyn += $dyn_sum; $cnt++;
    }
    if ($cnt == 0) return "📭 گزارشی در این تاریخ یافت نشد.";
    $msg .= "━ ━ ━ ━ ━ ━ ━\n📌 جمع کل ({$cnt} شعبه):\n💰 بدهکاران: " . number_format($t_d, 1) . " م\n💳 بستانکاران: " . number_format($t_c, 1) . " م\n📦 تنخواه: " . number_format($t_p, 1) . " م\n💵 درآمد: " . number_format($t_i) . " ریال";
    if ($t_dyn > 0) $msg .= "\n📥 داینامیک: " . number_format($t_dyn, 3) . " گرم";
    return $msg;
}

// ⭐ گزارش کامل روز
function full_day($date) {
    global $conn, $site_url;
    $msg = "📋 گزارش کامل - " . str_replace('-', '/', $date) . "\n\n";
    $t_d = $t_c = $t_p = $t_i = $t_dyn = 0;
    $res = mysqli_query($conn, "SELECT report_data FROM daily_reports WHERE report_date = '$date'");
    while ($r = mysqli_fetch_assoc($res)) { $d = json_decode($r['report_data'], true); if (!$d) continue; $t_d += array_sum(array_column($d['debtors'] ?? [], 'amt')); $t_c += array_sum(array_column($d['creditors'] ?? [], 'amt')); $t_p += array_sum(array_column($d['pettys'] ?? [], 'amt')); }
    $ir = mysqli_query($conn, "SELECT COALESCE(SUM(amount_rial),0) FROM income_daily_records WHERE record_date = '$date'"); if ($ir) $t_i = mysqli_fetch_row($ir)[0];
    $dr = mysqli_query($conn, "SELECT COALESCE(SUM(amount_gram),0) FROM dynamic_records WHERE report_id IN (SELECT id FROM daily_reports WHERE report_date = '$date')"); if ($dr) $t_dyn = mysqli_fetch_row($dr)[0];
    if ($t_d == 0 && $t_c == 0 && $t_p == 0 && $t_i == 0 && $t_dyn == 0) return "📭 گزارشی در این تاریخ یافت نشد.";
    $msg .= "📌 جمع کل:\n💰 بدهکاران: " . number_format($t_d, 1) . " م\n💳 بستانکاران: " . number_format($t_c, 1) . " م\n📦 تنخواه: " . number_format($t_p, 1) . " م\n💵 درآمد: " . number_format($t_i) . " ریال";
    if ($t_dyn > 0) $msg .= "\n📥 داینامیک: " . number_format($t_dyn, 3) . " گرم";
    $msg .= "\n\n";
    $res2 = mysqli_query($conn, "SELECT dr.id, dr.user_id, u.branch_name, dr.report_data FROM daily_reports dr JOIN users u ON dr.user_id = u.id WHERE dr.report_date = '$date'");
    while ($r = mysqli_fetch_assoc($res2)) {
        $d = json_decode($r['report_data'], true); if (!$d) continue;
        $msg .= "🏢 {$r['branch_name']}\n";
        if (!empty($d['debtors'])) { $sum = array_sum(array_column($d['debtors'], 'amt')); $msg .= "🔴 بدهکاران: " . number_format($sum, 1) . " م\n"; foreach ($d['debtors'] as $x) if (!empty($x['name'])||!empty($x['amt'])) $msg .= "  • {$x['name']}: " . number_format($x['amt'], 1) . " م\n"; }
        if (!empty($d['creditors'])) { $sum = array_sum(array_column($d['creditors'], 'amt')); $msg .= "🟢 بستانکاران: " . number_format($sum, 1) . " م\n"; foreach ($d['creditors'] as $x) if (!empty($x['name'])||!empty($x['amt'])) $msg .= "  • {$x['name']}: " . number_format($x['amt'], 1) . " م\n"; }
        if (!empty($d['pettys'])) { $sum = array_sum(array_column($d['pettys'], 'amt')); $msg .= "🟣 تنخواه: " . number_format($sum, 1) . " م\n"; foreach ($d['pettys'] as $x) if (!empty($x['desc'])||!empty($x['amt'])) $msg .= "  • {$x['desc']}: " . number_format($x['amt'], 1) . " م\n"; }
        if (!empty($d['bankers'])) { $sum = array_sum(array_column($d['bankers'], 'amt')); $msg .= "🟠 بنکداران: " . number_format($sum, 1) . " گرم\n"; foreach ($d['bankers'] as $x) if (!empty($x['name'])||!empty($x['amt'])) $msg .= "  • {$x['name']}: " . number_format($x['amt'], 1) . " گرم\n"; }
        $dyn_items = get_dynamic_items($r['id']); if (!empty($dyn_items)) { $sum_dyn = array_sum(array_column($dyn_items, 'amount_gram')); $msg .= "📥 آیتم‌های داینامیک: " . number_format($sum_dyn, 3) . " گرم\n"; foreach ($dyn_items as $x) $msg .= "  • {$x['name']}: " . number_format($x['amount_gram'], 3) . " گرم\n"; }
        $bir = mysqli_query($conn, "SELECT di.name, dr_inc.amount_rial, dr_inc.amount_gram FROM income_daily_records dr_inc JOIN income_daily_items di ON dr_inc.item_id = di.id WHERE dr_inc.branch_id = {$r['user_id']} AND dr_inc.record_date = '$date'");
        if (mysqli_num_rows($bir) > 0) { $sum_inc = 0; $items = []; while ($inc = mysqli_fetch_assoc($bir)) { $sum_inc += $inc['amount_rial']; $items[] = "  • {$inc['name']}: " . number_format($inc['amount_rial']) . " ریال (" . number_format($inc['amount_gram'], 3) . " گرم)"; } $msg .= "💵 درآمد: " . number_format($sum_inc) . " ریال\n" . implode("\n", $items) . "\n"; }
        $msg .= "\n";
    }
    // ⭐ لینک PDF
    $pdf_url = $site_url . "/pdf_report.php?date=" . $date;
    $msg .= "📄 نسخه چاپی: {$pdf_url}";
    return $msg;
}

// ⭐ گزارش ماهانه
function summary_month($year, $month) {
    global $conn; $prefix = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT);
    $msg = "📊 گزارش ماهانه - {$prefix}\n\n"; $t_d = $t_c = $t_p = $t_i = 0;
    $res = mysqli_query($conn, "SELECT report_data FROM daily_reports WHERE report_date LIKE '$prefix%'");
    while ($row = mysqli_fetch_assoc($res)) { $d = json_decode($row['report_data'], true); if (!$d) continue; $t_d += array_sum(array_column($d['debtors'] ?? [], 'amt')); $t_c += array_sum(array_column($d['creditors'] ?? [], 'amt')); $t_p += array_sum(array_column($d['pettys'] ?? [], 'amt')); }
    $res2 = mysqli_query($conn, "SELECT COALESCE(SUM(amount_rial),0) FROM income_daily_records WHERE record_date LIKE '$prefix%'"); if ($res2) $t_i = mysqli_fetch_row($res2)[0];
    if ($t_d == 0 && $t_c == 0 && $t_p == 0 && $t_i == 0) return "📭 گزارشی در این ماه یافت نشد.";
    $msg .= "📌 جمع کل:\n💰 بدهکاران: " . number_format($t_d, 1) . " م\n💳 بستانکاران: " . number_format($t_c, 1) . " م\n📦 تنخواه: " . number_format($t_p, 1) . " م\n💵 درآمد: " . number_format($t_i) . " ریال";
    return $msg;
}

// ⭐ گزارش سالانه
function summary_year($year) {
    global $conn; $msg = "📊 گزارش سالانه - {$year}\n\n"; $t_d = $t_c = $t_p = $t_i = 0;
    $res = mysqli_query($conn, "SELECT report_data FROM daily_reports WHERE report_date LIKE '$year-%'");
    while ($row = mysqli_fetch_assoc($res)) { $d = json_decode($row['report_data'], true); if (!$d) continue; $t_d += array_sum(array_column($d['debtors'] ?? [], 'amt')); $t_c += array_sum(array_column($d['creditors'] ?? [], 'amt')); $t_p += array_sum(array_column($d['pettys'] ?? [], 'amt')); }
    $res2 = mysqli_query($conn, "SELECT COALESCE(SUM(amount_rial),0) FROM income_daily_records WHERE record_date LIKE '$year-%'"); if ($res2) $t_i = mysqli_fetch_row($res2)[0];
    $msg .= "💰 بدهکاران: " . number_format($t_d, 1) . " م\n💳 بستانکاران: " . number_format($t_c, 1) . " م\n📦 تنخواه: " . number_format($t_p, 1) . " م\n💵 درآمد: " . number_format($t_i) . " ریال";
    return $msg;
}

// ⭐ پردازش پیام‌ها
foreach ($data['result'] as $update) {
    $update_id = $update['update_id']; $message = $update['message'] ?? null;
    if (!$message || !isset($message['text'])) { $offset = $update_id + 1; continue; }
    $chat_id = $message['chat']['id']; $user_id = $message['from']['id']; $text = trim($message['text'] ?? '');
    if ($user_id != $admin_user_id) { $offset = $update_id + 1; continue; }
    
    if (preg_match('/^(\d{4}\/\d{2}\/\d{2})\!$/', $text, $m)) { $date = str_replace('/', '-', $m[1]); send($chat_id, full_day($date)); }
    elseif (preg_match('/^\d{4}\/\d{2}\/\d{2}$/', $text)) { $date = str_replace('/', '-', $text); send($chat_id, summary_day($date)); }
    elseif (preg_match('/^\d{4}\/\d{2}$/', $text)) { list($y, $m) = explode('/', $text); send($chat_id, summary_month($y, $m)); }
    elseif (preg_match('/^\d{4}$/', $text)) { send($chat_id, summary_year($text)); }
    elseif ($text == '/start') { send($chat_id, "👋 ربات حسابداری\n\n📅 1405=سال 1405/02=ماه 1405/02/20=روز 1405/02/20!=کامل\n/today /yesterday"); }
    elseif ($text == '/today') { send($chat_id, summary_day(date('Y-m-d'))); }
    elseif ($text == '/yesterday') { send($chat_id, summary_day(date('Y-m-d', strtotime('-1 day')))); }
    else { send($chat_id, "❓ فرمت نامشخص. تاریخ رو بفرست."); }
    $offset = $update_id + 1;
}
file_put_contents($offset_file, $offset);