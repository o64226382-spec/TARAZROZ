<?php
define('SECURE_ACCESS', true);
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

$role = $_SESSION['role'];
if ($role !== 'observer') {
    echo json_encode(['feed' => []]);
    exit;
}

$observer_id = intval($_SESSION['user_id']);

// شعب تحت نظارت
$branches = [];
$br = mysqli_query($conn, "SELECT branch_id FROM observer_assignments WHERE observer_id = $observer_id");
while ($b = mysqli_fetch_assoc($br)) {
    $branches[] = intval($b['branch_id']);
}

if (empty($branches)) {
    echo json_encode(['feed' => []]);
    exit;
}

$branch_ids = implode(',', $branches);

// آخرین گزارش‌های هر شعبه
$feed = [];
$q = "SELECT dr.user_id, dr.report_date, dr.report_data, u.branch_name 
      FROM daily_reports dr 
      JOIN users u ON dr.user_id = u.id 
      WHERE dr.user_id IN ($branch_ids) 
      ORDER BY dr.updated_at DESC 
      LIMIT 30";
$res = mysqli_query($conn, $q);

while ($row = mysqli_fetch_assoc($res)) {
    $data = json_decode($row['report_data'], true);
    if (!$data) continue;
    
    $debtors = $data['debtors'] ?? [];
    $creditors = $data['creditors'] ?? [];
    $matrixVals = $data['matrixValues'] ?? [];
    $controlRows = $data['controlRows'] ?? [];
    $controlDescs = $data['controlDescs'] ?? [];
    
    // ساخت activeRelations
    $activeRelations = [];
    foreach ($creditors as $c) {
        foreach ($debtors as $d) {
            $key = $c['id'] . '_' . $d['id'];
            $val = isset($matrixVals[$key]) ? (float)$matrixVals[$key] : 0;
            if ($val > 0) {
                $activeRelations[] = [
                    'from' => $d['name'],
                    'to' => $c['name'],
                    'value' => $val
                ];
            }
        }
    }
    
    // استخراج ریزتراکنش‌ها
    foreach ($controlRows as $rowIdx => $ctrlRow) {
        if (!is_array($ctrlRow)) continue;
        foreach ($ctrlRow as $colIdx => $val) {
            $numVal = (float)$val;
            if ($numVal <= 0) continue;
            $desc = isset($controlDescs[$rowIdx][$colIdx]) ? trim($controlDescs[$rowIdx][$colIdx]) : '';
            if (isset($activeRelations[$colIdx])) {
                $feed[] = [
                    'branch_id' => $row['user_id'],
                    'branch_name' => $row['branch_name'],
                    'date' => $row['report_date'],
                    'from' => $activeRelations[$colIdx]['from'],
                    'to' => $activeRelations[$colIdx]['to'],
                    'amount' => $numVal,
                    'desc' => $desc ?: ''
                ];
            }
        }
    }
}

// مرتب‌سازی بر اساس تاریخ نزولی
usort($feed, function($a, $b) {
    return strcmp($b['date'], $a['date']);
});

echo json_encode(['feed' => array_slice($feed, 0, 20)]);
exit();
?>