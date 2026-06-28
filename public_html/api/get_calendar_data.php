<?php
ini_set('display_errors', 0);
error_reporting(0);

define('SECURE_ACCESS', true);
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/jdf.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'login']);
    exit;
}

$user = getCurrentUser();
$role = $user['role'];
$uid = $_SESSION['user_id'];

$y = isset($_GET['year']) ? (int)$_GET['year'] : (int)jdate('Y');
$m = isset($_GET['month']) ? (int)$_GET['month'] : (int)jdate('m');
$bid = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : 0;

if ($role === 'branch') $bid = $uid;

if ($role === 'observer' && $bid === 0) {
    echo json_encode(['error' => 'shobeh']);
    exit;
}

// تعداد روزهای ماه
if ($m <= 6) $dim = 31;
elseif ($m <= 11) $dim = 30;
else $dim = 29;

// محاسبه روز اول ماه
$tmp = jdate::to_gregorian_date($y, $m, 1);
$fdow = (date('w', strtotime($tmp)) + 1) % 7;

// بازه تاریخ
$sd = sprintf("%04d-%02d-%02d", $y, $m, 1);
$ed = sprintf("%04d-%02d-%02d", $y, $m, $dim);

// تراز روزانه
$bal = [];
$q = "SELECT report_date, report_data FROM daily_reports WHERE user_id = ? AND report_date BETWEEN ? AND ?";
$st = mysqli_prepare($conn, $q);
mysqli_stmt_bind_param($st, "iss", $bid, $sd, $ed);
mysqli_stmt_execute($st);
$res = mysqli_stmt_get_result($st);
while ($row = mysqli_fetch_assoc($res)) {
    $jd = json_decode($row['report_data'], true);
    $td = 0;
    $tc = 0;
    if (isset($jd['debtors'])) {
        foreach ($jd['debtors'] as $x) $td += (float)($x['amt'] ?? 0);
    }
    if (isset($jd['creditors'])) {
        foreach ($jd['creditors'] as $x) $tc += (float)($x['amt'] ?? 0);
    }
    $bal[$row['report_date']] = ['has_report' => true, 'total_balance' => $td + $tc];
}
mysqli_stmt_close($st);

// ساخت روزها
$days = [];
for ($d = 1; $d <= $dim; $d++) {
    $dk = sprintf("%04d-%02d-%02d", $y, $m, $d);
    $b = isset($bal[$dk]) ? $bal[$dk] : ['has_report' => false, 'total_balance' => 0];
    $days[$dk] = array_merge(['has_income' => false, 'amount' => 0], $b);
}

$mn = ['', 'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];

echo json_encode([
    'success' => true,
    'year' => $y,
    'month' => $m,
    'month_name' => $mn[$m],
    'days_in_month' => $dim,
    'first_day_of_week' => $fdow,
    'today_shamsi' => jdate('Y-m-d'),
    'monthly_income' => null,
    'days' => $days
], JSON_UNESCAPED_UNICODE);