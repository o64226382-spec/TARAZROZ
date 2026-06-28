<?php
define('SECURE_ACCESS', true);
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireAdmin();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="full_report_' . date('Y-m-d_His') . '.csv"');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
fputcsv($output, ['تاریخ', 'شعبه', 'نوع جزء', 'نام/شرح', 'مبلغ', 'مانده بدهکار', 'مانده بستانکار', 'بلاتکلیف بدهکار', 'بلاتکلیف بستانکار', 'سقف تنخواه', 'وضعیت تنخواه']);

$query = "SELECT d.report_date, u.branch_name, d.report_data FROM daily_reports d JOIN users u ON d.user_id = u.id WHERE u.username != 'admin' ORDER BY d.report_date DESC";
$result = mysqli_query($conn, $query);

while ($row = mysqli_fetch_assoc($result)) {
    $data = json_decode($row['report_data'], true);
    if (!$data) continue;
    
    foreach ($data['debtors'] ?? [] as $d) {
        if (!empty($d['name']) || !empty($d['amt'])) fputcsv($output, [$row['report_date'], $row['branch_name'], 'بدهکار', $d['name'] ?? '', $d['amt'] ?? '', '', '', '', '', '', '']);
    }
    foreach ($data['creditors'] ?? [] as $c) {
        if (!empty($c['name']) || !empty($c['amt'])) fputcsv($output, [$row['report_date'], $row['branch_name'], 'بستانکار', $c['name'] ?? '', $c['amt'] ?? '', '', '', '', '', '', '']);
    }
    foreach ($data['pettys'] ?? [] as $p) {
        if (!empty($p['desc']) || !empty($p['amt'])) fputcsv($output, [$row['report_date'], $row['branch_name'], 'تنخواه', $p['desc'] ?? '', $p['amt'] ?? '', '', '', '', '', $data['ceiling'] ?? '', '']);
    }
    foreach ($data['bankers'] ?? [] as $b) {
        if (!empty($b['name']) || !empty($b['amt'])) fputcsv($output, [$row['report_date'], $row['branch_name'], 'بنکدار', $b['name'] ?? '', $b['amt'] ?? '', '', '', '', '', '', '']);
    }
}
fclose($output);
exit();
?>