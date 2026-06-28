<?php
define('SECURE_ACCESS', true);
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

$user_id = $_SESSION['user_id'];
$selected_date = $_GET['date'] ?? '';
$person_input = $_GET['person'] ?? '';

if (!$selected_date || !$person_input) {
    echo json_encode(['error' => 'اطلاعات ناقص']);
    exit;
}

list($person_name, $person_type) = explode('|', $person_input);
$is_debtor = ($person_type === 'debtor');

$stmt = mysqli_prepare($conn, "SELECT report_data FROM daily_reports WHERE user_id = ? AND report_date = ?");
mysqli_stmt_bind_param($stmt, "is", $user_id, $selected_date);
mysqli_stmt_execute($stmt);
$report = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$report) {
    echo json_encode(['error' => 'گزارشی یافت نشد']);
    exit;
}

$data = json_decode($report['report_data'], true);
$totalAmount = 0;
$personId = 0;

if ($is_debtor) {
    foreach (($data['debtors'] ?? []) as $d) {
        if ($d['name'] === $person_name) { $totalAmount = (float)($d['amt'] ?? 0); $personId = $d['id']; break; }
    }
} else {
    foreach (($data['creditors'] ?? []) as $c) {
        if ($c['name'] === $person_name) { $totalAmount = (float)($c['amt'] ?? 0); $personId = $c['id']; break; }
    }
}

$controlRows = $data['controlRows'] ?? [];
$controlDescs = $data['controlDescs'] ?? [];
$matrixVals = $data['matrixValues'] ?? [];
$debtorsList = $data['debtors'] ?? [];
$creditorsList = $data['creditors'] ?? [];

$activeRelations = [];
foreach ($creditorsList as $c) {
    foreach ($debtorsList as $d) {
        $key = $c['id'] . '_' . $d['id'];
        $val = (float)($matrixVals[$key] ?? 0);
        if ($val > 0) $activeRelations[] = ['creditor_id' => $c['id'], 'debtor_id' => $d['id']];
    }
}

$targetCols = [];
foreach ($activeRelations as $colIdx => $rel) {
    if ($is_debtor && $rel['debtor_id'] === $personId) $targetCols[] = $colIdx;
    if (!$is_debtor && $rel['creditor_id'] === $personId) $targetCols[] = $colIdx;
}

$transactions = [];
$paidTotal = 0;
foreach ($controlRows as $rowIdx => $row) {
    foreach ($targetCols as $colIdx) {
        if (!isset($row[$colIdx])) continue;
        $amount = (float)$row[$colIdx];
        if ($amount <= 0) continue;
        $desc = trim($controlDescs[$rowIdx][$colIdx] ?? '');
        if (empty($desc) || $desc === $person_name) $desc = '—';
        $transactions[] = ['amount' => $amount, 'desc' => $desc];
        $paidTotal += $amount;
    }
}
// ⭐ تبدیل به ریال (ضرب در ۱۰۰ میلیون)
$totalAmount = $totalAmount * 10000000;
$paidTotal = $paidTotal * 10000000;
foreach ($transactions as &$t) {
    $t['amount'] = $t['amount'] * 10000000;
}
unset($t);

$remaining = $totalAmount - $paidTotal;

// ⭐ ساخت رسید متنی
$person_type_fa = $is_debtor ? 'بدهکار' : 'طلبکار';
$amount_label = $is_debtor ? 'کل بدهی' : 'کل طلب';
$date_formatted = gregorian_to_jalali_format($selected_date);

$receipt = '';
$receipt .= 'تاریخ گزارش: ' . $date_formatted . "\n";
$receipt .= 'نام شخص: ' . $person_name . "\n";
$receipt .= 'نوع حساب: ' . $person_type_fa . "\n";
$receipt .= str_repeat('-', 48) . "\n\n";
$receipt .= $amount_label . ': ' . number_format($totalAmount) . ' ریال' . "\n";
$receipt .= 'مبلغ پرداخت شده: ' . number_format($paidTotal) . ' ریال' . "\n";
$receipt .= 'مانده قابل پرداخت: ' . number_format($remaining) . ' ریال' . "\n";
$receipt .= str_repeat('-', 48) . "\n\n";

if (count($transactions) > 0) {
    $receipt .= 'جزئیات تراکنش‌ها:' . "\n";
    $receipt .= str_repeat('-', 48) . "\n";
    $receipt .= str_pad('ردیف', 6) . str_pad('مبلغ (ریال)', 20) . str_pad('شرح', 22) . "\n";
    $receipt .= str_repeat('-', 48) . "\n";
    
    foreach ($transactions as $i => $t) {
        $row_num = $i + 1;
        $amount_str = number_format($t['amount']);
        $desc_str = mb_substr($t['desc'], 0, 20);
        $receipt .= str_pad($row_num, 6) . str_pad($amount_str, 20) . str_pad($desc_str, 22) . "\n";
    }
    $receipt .= str_repeat('-', 48);
}

// ⭐ تابع تبدیل تاریخ
function gregorian_to_jalali_format($date) {
    $parts = explode('-', $date);
    if (count($parts) !== 3) return $date;
    
    $gYear = (int)$parts[0];
    $gMonth = (int)$parts[1];
    $gDay = (int)$parts[2];
    
    $gDaysInMonth = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    $jDaysInMonth = [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29];
    
    $gy = $gYear - 1600;
    $gm = $gMonth - 1;
    $gd = $gDay - 1;
    
    $gDayNo = 365 * $gy + (int)(($gy + 3) / 4) - (int)(($gy + 99) / 100) + (int)(($gy + 399) / 400);
    
    for ($i = 0; $i < $gm; ++$i) {
        $gDayNo += $gDaysInMonth[$i];
    }
    
    if ($gm > 1 && (($gy % 4 == 0 && $gy % 100 != 0) || ($gy % 400 == 0))) {
        $gDayNo++;
    }
    
    $gDayNo += $gd;
    
    $jDayNo = $gDayNo - 79;
    $jNp = (int)($jDayNo / 12053);
    $jDayNo %= 12053;
    $jy = 979 + 33 * $jNp + 4 * (int)($jDayNo / 1461);
    $jDayNo %= 1461;
    
    if ($jDayNo >= 366) {
        $jy += (int)(($jDayNo - 1) / 365);
        $jDayNo = ($jDayNo - 1) % 365;
    }
    
    for ($i = 0; $i < 11 && $jDayNo >= $jDaysInMonth[$i]; ++$i) {
        $jDayNo -= $jDaysInMonth[$i];
    }
    
    $jMonth = $i + 1;
    $jDay = $jDayNo + 1;
    
    return sprintf('%04d/%02d/%02d', $jy, $jMonth, $jDay);
}

echo json_encode([
    'person_name' => $person_name,
    'is_debtor' => $is_debtor,
    'selected_date' => $selected_date,
    'totalAmount' => $totalAmount,
    'transactions' => $transactions,
    'paidTotal' => $paidTotal,
    'remaining' => $remaining,
    'receipt_text' => $receipt  // ⭐ اضافه شد
]);