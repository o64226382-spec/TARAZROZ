<?php
// api/observer_day_summary.php
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
$jalali_date = $_GET['date'] ?? '';

if (!preg_match('/^\d{4}\/\d{2}\/\d{2}$/', $jalali_date)) {
    echo json_encode(['error' => 'فرمت تاریخ نامعتبر']);
    exit;
}

$db_date = str_replace('/', '-', $jalali_date);

$stmt = mysqli_prepare($conn, "SELECT u.id, u.branch_name FROM users u JOIN observer_assignments oa ON u.id = oa.branch_id WHERE oa.observer_id = ?");
mysqli_stmt_bind_param($stmt, "i", $observer_id);
mysqli_stmt_execute($stmt);
$branches_raw = mysqli_stmt_get_result($stmt);
$branches = [];
$branch_ids = [];
while ($b = mysqli_fetch_assoc($branches_raw)) { $branches[$b['id']] = ['name' => $b['branch_name'], 'report' => null, 'income' => null]; $branch_ids[] = $b['id']; }
mysqli_stmt_close($stmt);

if (empty($branch_ids)) { echo json_encode(['date' => $jalali_date, 'branches' => []]); exit; }

$branch_placeholders = implode(',', array_fill(0, count($branch_ids), '?'));
$params = array_merge([$db_date], $branch_ids);
$types = 's' . str_repeat('i', count($branch_ids));

$stmt = mysqli_prepare($conn, "SELECT dr.id, dr.user_id, dr.report_data, UNIX_TIMESTAMP(dr.updated_at) as ts FROM daily_reports dr WHERE dr.report_date = ? AND dr.user_id IN ($branch_placeholders)");
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$report_ids = [];
while ($row = mysqli_fetch_assoc($res)) {
    $data = json_decode($row['report_data'], true);
    if ($data) {
        $branches[$row['user_id']]['report'] = [
            'report_id' => $row['id'],
            'total_debtors' => array_sum(array_column($data['debtors'] ?? [], 'amt')),
            'total_creditors' => array_sum(array_column($data['creditors'] ?? [], 'amt')),
            'total_petty' => array_sum(array_column($data['pettys'] ?? [], 'amt')),
            'total_bankers' => array_sum(array_column($data['bankers'] ?? [], 'amt')),
            'dyn_items' => [],
            'last_update' => date('H:i', $row['ts'] ?? time())
        ];
        $report_ids[] = $row['id'];
    }
}
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare($conn, "SELECT branch_id, SUM(amount_rial) as sum_rial, SUM(amount_gram) as sum_gram FROM income_daily_records WHERE record_date = ? AND branch_id IN ($branch_placeholders) GROUP BY branch_id");
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) {
    if (isset($branches[$row['branch_id']])) {
        $branches[$row['branch_id']]['income'] = ['total_rial' => floatval($row['sum_rial']), 'total_gram' => floatval($row['sum_gram'])];
    }
}
mysqli_stmt_close($stmt);

if (!empty($report_ids)) {
    foreach ($branches as $bid => &$bdata) {
        if ($bdata['report'] && $bdata['report']['report_id']) {
            $stmt = mysqli_prepare($conn, "SELECT di.name, dr.amount_gram FROM dynamic_records dr JOIN dynamic_items di ON dr.item_id = di.id WHERE dr.report_id = ?");
            mysqli_stmt_bind_param($stmt, "i", $bdata['report']['report_id']);
            mysqli_stmt_execute($stmt);
            $dyn_res = mysqli_stmt_get_result($stmt);
            $dyn_items = [];
            while ($d = mysqli_fetch_assoc($dyn_res)) $dyn_items[] = $d;
            $bdata['report']['dyn_items'] = $dyn_items;
            mysqli_stmt_close($stmt);
        }
    }
    unset($bdata);
}

echo json_encode(['date' => $jalali_date, 'branches' => $branches]);