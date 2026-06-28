<?php
define('SECURE_ACCESS', true);
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isset($_GET['date'])) { die('تاریخ گزارش مشخص نشده است.'); }
$selected_date = $_GET['date']; // تاریخ دقیقاً همان چیزی که از صفحه ارسال شده
$user_id = $_SESSION['user_id'] ?? 0;
$db_date = str_replace('/', '-', $selected_date);

// دریافت گزارش اصلی
$stmt = mysqli_prepare($conn, "SELECT id, report_data FROM daily_reports WHERE user_id = ? AND report_date = ?");
mysqli_stmt_bind_param($stmt, "is", $user_id, $db_date);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$saved = mysqli_fetch_assoc($result);
$data = $saved ? json_decode($saved['report_data'], true) : null;
$report_id = $saved ? $saved['id'] : 0;

// استخراج داده‌ها
$debtors   = $data['debtors']   ?? [];
$creditors = $data['creditors'] ?? [];
$pettys    = $data['pettys']    ?? [];
$bankers   = $data['bankers']   ?? [];
$ceiling   = $data['ceiling']   ?? 1000;
$matrixValues = $data['matrixValues'] ?? [];
$controlRows  = $data['controlRows']  ?? [];
$controlDescs = $data['controlDescs'] ?? [];

// ⭐ دریافت اقلام داینامیک از دیتابیس
$dynNames = [];
$dynItemsRes = mysqli_query($conn, "SELECT id, name FROM dynamic_items WHERE active=1 ORDER BY sort_order");
while ($dn = mysqli_fetch_assoc($dynItemsRes)) $dynNames[$dn['id']] = $dn['name'];

$dynRecords = [];
if ($report_id > 0) {
    $stmt = mysqli_prepare($conn, "SELECT item_id, amount_gram FROM dynamic_records WHERE report_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $report_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($dr = mysqli_fetch_assoc($res)) $dynRecords[$dr['item_id']] = (float)$dr['amount_gram'];
}

// محاسبات
function sumArr($arr, $k='amt') { return array_reduce($arr, fn($s,$x) => $s + (float)($x[$k]??0), 0); }
$sD = sumArr($debtors); $sC = sumArr($creditors); $sP = sumArr($pettys); $sB = sumArr($bankers);
$sDyn = array_sum($dynRecords);
$diff1 = $sD - $sC; $diff2 = $sP - $ceiling;

// مانده‌های ماتریس
$cBals = []; $dBals = [];
foreach ($creditors as $c) {
    $sum = 0; foreach ($debtors as $d) $sum += (float)($matrixValues[$c['id'].'_'.$d['id']] ?? 0);
    $cBals[$c['id']] = ((float)($c['amt']??0)) - $sum;
}
foreach ($debtors as $d) {
    $sum = 0; foreach ($creditors as $c) $sum += (float)($matrixValues[$c['id'].'_'.$d['id']] ?? 0);
    $dBals[$d['id']] = ((float)($d['amt']??0)) - $sum;
}
$unsettledC = array_sum($cBals); $unsettledD = array_sum($dBals);

function fmt($n) { return number_format((float)$n, 1, '.', ','); }
function esc($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

// ⭐ ساخت مجدد روابط برای جدول کنترل (مشابه لاجیک JS)
$activeRelations = [];
foreach ($creditors as $c) {
    foreach ($debtors as $d) {
        $key = $c['id'].'_'.$d['id'];
        $val = (float)($matrixValues[$key] ?? 0);
        if ($val > 0) {
            $activeRelations[] = [
                'id' => 'rel_'.$d['id'].'_'.$c['id'],
                'title' => ($d['name']?:'بدهکار').' به '.($c['name']?:'بستانکار'),
                'target' => $val
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<title>گزارش رسمی - <?php echo $selected_date; ?></title>
<link href="../assets/fonts/fonts.css" rel="stylesheet">
<style>
@media print {
    @page { size: A4; margin: 6mm; }
    body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}
* { box-sizing: border-box; }
body {
    font-family: 'Vazirmatn', sans-serif !important;
    direction: rtl; background: #fff; color: #000;
    font-size: 9pt; line-height: 1.25; margin: 0; padding: 8px;
}
.report-header {
    text-align: center; border-bottom: 2px solid #000;
    padding: 6px 0 10px; margin-bottom: 10px;
}
.report-title { font-size: 14pt; font-weight: 900; margin: 0 0 4px; }
.report-meta { font-size: 9pt; color: #333; font-weight: 500; }

.main-layout { display: flex; gap: 12px; margin-bottom: 8px; }
.lists-col { width: 35%; display: flex; flex-direction: column; gap: 6px; }
.matrix-col { width: 65%; }

table { width: 100%; border-collapse: collapse; margin-bottom: 5px; font-size: 8.5pt; page-break-inside: avoid; }
th, td { border: 1px solid #000; padding: 3px 4px; text-align: center; vertical-align: middle; }
th { background: #eee !important; font-weight: 700; }
.text-right { text-align: right !important; }
.total-row { font-weight: 800; background: #f9f9f9 !important; }

.green { color: #047857; } .red { color: #b91c1c; }
.purple { color: #6d28d9; } .amber { color: #b45309; }

.list-header { background: #eee !important; font-weight: 800; padding: 4px !important;
    text-align: center; border: 1px solid #000 !important; font-size: 9pt; }

.matrix-table { font-size: 7.5pt; }
.matrix-table th { font-size: 7.5pt; padding: 3px 4px; }
.matrix-table td { padding: 3px 4px; }
.mx-cname { background: #ffe6e6 !important; color: #b91c1c; font-weight: 700; }
.mx-dname { background: #e6ffe6 !important; color: #047857; font-weight: 700; }
.mx-cbal { background: #fde8e7 !important; color: #b91c1c; font-weight: 800; }
.mx-dbal { background: #d4edda !important; color: #047857; font-weight: 800; }

.summary-box { display: flex; gap: 8px; margin: 8px 0; flex-wrap: wrap; }
.summary-card { flex: 1; min-width: 130px; border: 1px solid #000; padding: 5px 6px; text-align: center; }
.summary-card .label { font-size: 8pt; color: #444; margin-bottom: 2px; }
.summary-card .value { font-size: 11pt; font-weight: 900; }

/* ⭐ انتقال ریز تراکنش‌ها به صفحه دوم */
.page-2-section { margin-top: 20px; border-top: 1px dashed #999; padding-top: 10px; }
@media print {
    .page-2-section { margin-top: 15px; }
    .main-layout, .summary-box { page-break-after: avoid !important; }
}
.control-table { font-size: 7.5pt; }
.ctrl-diff-ok { color: #047857; font-weight: 800; }
.ctrl-diff-neg { color: #b91c1c; font-weight: 800; }

.print-footer {
    margin-top: 10px; text-align: center; font-size: 7pt;
    color: #666; border-top: 1px solid #ccc; padding-top: 4px;
}
</style>
</head>
<body>

<div class="report-header">
    <div class="report-title">گزارش رسمی تراز روزانه</div>
    <div class="report-meta">
        شعبه: <?php echo esc($_SESSION['branch_name'] ?? '-'); ?> |
        تاریخ: <?php echo $selected_date; ?> |
        کاربر: <?php echo esc($_SESSION['username'] ?? '-'); ?>
    </div>
</div>

<!-- صفحه ۱: لیست‌ها + ماتریس + خلاصه -->
<div class="main-layout">
    <div class="lists-col">
        <table>
            <tr><th colspan="3" class="list-header green">بدهکاران</th></tr>
            <tr><th style="width:15%">#</th><th>نام</th><th style="width:30%">مبلغ</th></tr>
            <?php foreach ($debtors as $i => $d): ?>
            <tr><td><?php echo $i+1; ?></td><td class="text-right"><?php echo esc($d['name']); ?></td><td><?php echo fmt($d['amt']); ?></td></tr>
            <?php endforeach; ?>
            <tr class="total-row green"><td colspan="2">جمع</td><td><?php echo fmt($sD); ?></td></tr>
        </table>

        <table>
            <tr><th colspan="3" class="list-header red">بستانکاران</th></tr>
            <tr><th style="width:15%">#</th><th>نام</th><th style="width:30%">مبلغ</th></tr>
            <?php foreach ($creditors as $i => $c): ?>
            <tr><td><?php echo $i+1; ?></td><td class="text-right"><?php echo esc($c['name']); ?></td><td><?php echo fmt($c['amt']); ?></td></tr>
            <?php endforeach; ?>
            <tr class="total-row red"><td colspan="2">جمع</td><td><?php echo fmt($sC); ?></td></tr>
        </table>

        <table>
            <tr><th colspan="3" class="list-header purple">تنخواه (سقف: <?php echo fmt($ceiling); ?>)</th></tr>
            <tr><th style="width:15%">#</th><th>شرح</th><th style="width:30%">مبلغ</th></tr>
            <?php foreach ($pettys as $i => $p): ?>
            <tr><td><?php echo $i+1; ?></td><td class="text-right"><?php echo esc($p['desc']); ?></td><td><?php echo fmt($p['amt']); ?></td></tr>
            <?php endforeach; ?>
            <tr class="total-row purple"><td colspan="2">جمع</td><td><?php echo fmt($sP); ?></td></tr>
        </table>

        <table>
            <tr><th colspan="3" class="list-header amber">بنکداران</th></tr>
            <tr><th style="width:15%">#</th><th>نام</th><th style="width:30%">وزن</th></tr>
            <?php foreach ($bankers as $i => $b): ?>
            <tr><td><?php echo $i+1; ?></td><td class="text-right"><?php echo esc($b['name']); ?></td><td><?php echo fmt($b['amt']); ?></td></tr>
            <?php endforeach; ?>
            <tr class="total-row amber"><td colspan="2">جمع</td><td><?php echo fmt($sB); ?></td></tr>
        </table>

        <!-- ⭐ اقلام داینامیک -->
        <?php if (!empty($dynNames)): ?>
        <table>
            <tr><th colspan="3" class="list-header amber">اقلام داینامیک</th></tr>
            <tr><th style="width:15%">#</th><th>نام</th><th style="width:30%">وزن</th></tr>
            <?php $idx=1; foreach($dynNames as $id => $name): 
                $gram = $dynRecords[$id] ?? 0;
                if ($gram > 0): ?>
                <tr><td><?php echo $idx++; ?></td><td class="text-right"><?php echo esc($name); ?></td><td><?php echo number_format($gram, 3); ?></td></tr>
            <?php endif; endforeach; ?>
            <tr class="total-row amber"><td colspan="2">جمع</td><td><?php echo number_format($sDyn, 3); ?></td></tr>
        </table>
        <?php endif; ?>
    </div>

    <div class="matrix-col">
        <?php if (!empty($debtors) && !empty($creditors)): ?>
        <table class="matrix-table">
            <thead>
                <tr>
                    <th rowspan="2" style="min-width:80px;">بستانکار →<br>بدهکار ↓</th>
                    <?php foreach ($debtors as $d): ?>
                        <th class="mx-dname">↓ <?php echo esc($d['name']); ?></th>
                    <?php endforeach; ?>
                    <th rowspan="2" style="width:60px;">مانده</th>
                </tr>
                <tr>
                    <?php foreach ($debtors as $d): ?>
                        <th class="mx-dname" style="font-size:7pt;"><?php echo fmt($d['amt']); ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($creditors as $c): ?>
                <tr>
                    <td class="mx-cname text-right" style="font-weight:700;">
                        ↑ <?php echo esc($c['name']); ?><br>
                        <small style="font-size:6.5pt;"><?php echo fmt($c['amt']); ?></small>
                    </td>
                    <?php foreach ($debtors as $d): 
                        $key = $c['id'].'_'.$d['id'];
                        $val = $matrixValues[$key] ?? '';
                    ?>
                        <td><?php echo $val !== '' ? fmt($val) : ''; ?></td>
                    <?php endforeach; ?>
                    <td class="mx-cbal" style="font-weight:800;">
                        <?php $bal = $cBals[$c['id']] ?? 0; echo ($bal >= 0 ? '' : '-') . fmt(abs($bal)); ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th class="text-right">مانده بدهکار</th>
                    <?php foreach ($debtors as $d): 
                        $bal = $dBals[$d['id']] ?? 0;
                    ?>
                        <td class="mx-dbal" style="font-weight:800;">
                            <?php echo ($bal >= 0 ? '' : '-') . fmt(abs($bal)); ?>
                        </td>
                    <?php endforeach; ?>
                    <th></th>
                </tr>
            </tfoot>
        </table>
        <?php endif; ?>
    </div>
</div>

<div class="summary-box">
    <div class="summary-card">
        <div class="label">اختلاف کل</div>
        <div class="value <?php echo $diff1 >= 0 ? 'green' : 'red'; ?>">
            <?php echo ($diff1 >= 0 ? '+' : '-') . fmt(abs($diff1)); ?> م
        </div>
    </div>
    <div class="summary-card">
        <div class="label">وضعیت تنخواه</div>
        <div class="value <?php echo $diff2 >= 0 ? 'green' : 'red'; ?>">
            <?php echo ($diff2 >= 0 ? '+' : '-') . fmt(abs($diff2)); ?> م
        </div>
    </div>
    <div class="summary-card">
        <div class="label">بلاتکلیف بستانکار</div>
        <div class="value red">-<?php echo fmt(abs($unsettledC)); ?> م</div>
    </div>
    <div class="summary-card">
        <div class="label">بلاتکلیف بدهکار</div>
        <div class="value green">+<?php echo fmt(abs($unsettledD)); ?> م</div>
    </div>
</div>

<!-- ⭐ صفحه ۲: ریز تراکنش‌ها -->
<div class="page-2-section">
    <div class="list-header" style="margin-bottom:8px;">ریز تراکنش‌ها و کنترل تسویه</div>
    <?php if (!empty($activeRelations) && !empty($controlRows[0])): ?>
    <table class="control-table">
        <thead>
            <tr>
                <?php foreach ($activeRelations as $rel): ?>
                    <th class="text-right"><?php echo esc($rel['title']); ?><br><small><?php echo fmt($rel['target']); ?></small></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($controlRows as $row): ?>
            <tr>
                <?php foreach ($row as $cell): ?>
                    <td><?php echo fmt($cell); ?></td>
                <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <?php foreach ($activeRelations as $idx => $rel): 
                    $target = $rel['target'];
                    $sum = 0;
                    foreach ($controlRows as $cr) $sum += (float)($cr[$idx] ?? 0);
                    $diff = $sum - $target;
                    $cls = $diff == 0 ? 'ctrl-diff-ok' : 'ctrl-diff-neg';
                ?>
                    <td class="<?php echo $cls; ?>">
                        اختلاف: <?php echo ($diff >= 0 ? '+' : '-') . fmt(abs($diff)); ?>
                    </td>
                <?php endforeach; ?>
            </tr>
        </tfoot>
    </table>
    <?php else: ?>
        <p style="text-align:center; color:#666; padding:15px;">تراکنشی ثبت نشده است.</p>
    <?php endif; ?>
</div>

<div class="print-footer">
    تولید شده توسط سیستم تراز روزانه | <?php echo date('Y/m/d H:i'); ?>
</div>

<script>
window.addEventListener('load', function() {
    setTimeout(() => {
        if (window.location.search.includes('print=1')) window.print();
    }, 300);
});
</script>
</body>
</html>