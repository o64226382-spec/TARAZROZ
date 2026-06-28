<?php
define('SECURE_ACCESS', true);
require_once '../includes/config.php'; // اگر این فایل در پوشه user است، این مسیر درست است
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز']); exit;
}

$user_id = $_SESSION['user_id'];
$date    = str_replace('/', '-', $_GET['date'] ?? date('Y/m/d'));
$person  = $_GET['person'] ?? '';
if (!$person) { echo json_encode(['success' => false, 'message' => 'اطلاعات ناقص است']); exit; }

list($p_name, $p_type) = explode('|', $person);
$is_debtor = ($p_type === 'debtor');

$stmt = mysqli_prepare($conn, "SELECT report_data FROM daily_reports WHERE user_id = ? AND report_date = ?");
mysqli_stmt_bind_param($stmt, "is", $user_id, $date);
mysqli_stmt_execute($stmt);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$row) { echo json_encode(['success' => false, 'message' => 'گزارشی یافت نشد']); exit; }
$data = json_decode($row['report_data'], true);

$total = 0; $pid = 0;
$list = $is_debtor ? ($data['debtors'] ?? []) : ($data['creditors'] ?? []);
foreach ($list as $item) {
    if (trim($item['name']) === trim($p_name)) { $total = (float)$item['amt']; $pid = $item['id']; break; }
}
if ($total <= 0) { echo json_encode(['success' => false, 'message' => 'مبلغی ثبت نشده']); exit; }

// محاسبه پرداختی‌ها از ماتریس و کنترل
$matrixVals = $data['matrixValues'] ?? [];
$controlRows = $data['controlRows'] ?? [];
$controlDescs = $data['controlDescs'] ?? [];
$dList = $data['debtors'] ?? []; $cList = $data['creditors'] ?? [];

$activeRels = [];
foreach ($cList as $c) foreach ($dList as $d) {
    $k = $c['id'].'_'.$d['id'];
    if ((float)($matrixVals[$k] ?? 0) > 0) $activeRels[] = ['cid'=>$c['id'], 'did'=>$d['id']];
}

$targetCols = [];
foreach ($activeRels as $i => $r) {
    if (($is_debtor && $r['did']==$pid) || (!$is_debtor && $r['cid']==$pid)) $targetCols[] = $i;
}

$txs = []; $paid = 0;
foreach ($controlRows as $ri => $row) foreach ($targetCols as $ci) {
    if (isset($row[$ci]) && (float)$row[$ci] > 0) {
        $desc = trim($controlDescs[$ri][$ci] ?? '');
        if ($desc=='' || strcasecmp($desc, $p_name)==0) $desc = '—';
        $txs[] = ['amount' => (float)$row[$ci], 'desc' => $desc];
        $paid += (float)$row[$ci];
    }
}

echo json_encode([
    'success' => true, 'person' => $p_name, 'type' => ($is_debtor?'بدهکار':'بستانکار'),
    'total' => $total, 'paid' => $paid, 'rem' => $total-$paid, 'txs' => $txs, 'date' => $date
]);
exit;
?>