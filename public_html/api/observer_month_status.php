<?php
// api/observer_month_status.php
define('SECURE_ACCESS', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/jdf.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'observer') {
    echo json_encode(['error' => 'دسترسی غیرمجاز']);
    exit;
}

$observer_id = $_SESSION['user_id'];
$year = intval($_GET['year'] ?? 0);
$month = intval($_GET['month'] ?? 0);

if ($year < 1000 || $month < 1 || $month > 12) {
    echo json_encode(['error' => 'سال/ماه نامعتبر']);
    exit;
}

$stmt = mysqli_prepare($conn, "SELECT u.id, u.branch_name FROM users u JOIN observer_assignments oa ON u.id = oa.branch_id WHERE oa.observer_id = ? AND u.role = 'branch'");
mysqli_stmt_bind_param($stmt, "i", $observer_id);
mysqli_stmt_execute($stmt);
$branches = [];
$branch_ids = [];
$res_b = mysqli_stmt_get_result($stmt);
while ($b = mysqli_fetch_assoc($res_b)) { $branches[] = $b; $branch_ids[] = $b['id']; }
mysqli_stmt_close($stmt);

if (empty($branch_ids)) { echo json_encode(['statuses' => [], 'branches' => []]); exit; }

$days_in_month = jdate::days_of_month($year, $month);
$jalali_weekdays = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنج‌شنبه', 'جمعه'];
$statuses = [];

$jalali_dates = [];
for ($day = 1; $day <= $days_in_month; $day++) {
    $jalali_dates[] = sprintf("%04d-%02d-%02d", $year, $month, $day);
}

$branch_placeholders = implode(',', array_fill(0, count($branch_ids), '?'));
$date_placeholders = implode(',', array_fill(0, count($jalali_dates), '?'));
$bind_types = str_repeat('i', count($branch_ids)) . str_repeat('s', count($jalali_dates));
$all_params = array_merge($branch_ids, $jalali_dates);

$report_map = [];
$stmt = mysqli_prepare($conn, "SELECT report_date FROM daily_reports WHERE user_id IN ($branch_placeholders) AND report_date IN ($date_placeholders)");
mysqli_stmt_bind_param($stmt, $bind_types, ...$all_params);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($r = mysqli_fetch_assoc($res)) $report_map[$r['report_date']] = true;
mysqli_stmt_close($stmt);

$income_map = [];
$stmt = mysqli_prepare($conn, "SELECT record_date FROM income_daily_records WHERE branch_id IN ($branch_placeholders) AND record_date IN ($date_placeholders)");
mysqli_stmt_bind_param($stmt, $bind_types, ...$all_params);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($r = mysqli_fetch_assoc($res)) $income_map[$r['record_date']] = true;
mysqli_stmt_close($stmt);

$count_map = [];
$stmt = mysqli_prepare($conn, "SELECT report_date, COUNT(DISTINCT user_id) as cnt FROM daily_reports WHERE user_id IN ($branch_placeholders) AND report_date IN ($date_placeholders) GROUP BY report_date");
mysqli_stmt_bind_param($stmt, $bind_types, ...$all_params);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($r = mysqli_fetch_assoc($res)) $count_map[$r['report_date']] = (int)$r['cnt'];
mysqli_stmt_close($stmt);

for ($day = 1; $day <= $days_in_month; $day++) {
    $jdate = sprintf("%04d-%02d-%02d", $year, $month, $day);
    $jdate_slash = sprintf("%04d/%02d/%02d", $year, $month, $day);
    
    list($gy, $gm, $gd) = jalali_to_gregorian($year, $month, $day);
    $ts = mktime(0, 0, 0, $gm, $gd, $gy);
    $persian_w = ((int)date('w', $ts) + 1) % 7;
    
    $statuses[] = [
        'jalali_date' => $jdate_slash,
        'weekday' => $persian_w,
        'weekday_name' => $jalali_weekdays[$persian_w],
        'has_report' => isset($report_map[$jdate]),
        'has_income' => isset($income_map[$jdate]),
        'branches_count' => $count_map[$jdate] ?? 0
    ];
}

echo json_encode(['year' => $year, 'month' => $month, 'month_name' => jdate::name_of_month($month), 'days_in_month' => $days_in_month, 'branches' => $branches, 'statuses' => $statuses]);