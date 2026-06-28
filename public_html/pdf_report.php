<?php
define('SECURE_ACCESS', true);
require_once 'includes/config.php';

$date = $_GET['date'] ?? date('Y-m-d');
$formatted_date = str_replace('-', '/', $date);
// تبدیل تاریخ میلادی به شمسی برای نمایش رسمی
$jalali_date = function_exists('jdate') ? jdate('Y/m/d', strtotime($date)) : $formatted_date;

// ⭐ دیتابیس
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if (!$conn) die("خطا در اتصال به دیتابیس");
mysqli_set_charset($conn, 'utf8mb4');

// ⭐ داده‌ها
$branches = [];
$res = mysqli_query($conn, "SELECT dr.id, dr.user_id, u.branch_name, dr.report_data FROM daily_reports dr JOIN users u ON dr.user_id = u.id WHERE dr.report_date = '$date'");
while ($r = mysqli_fetch_assoc($res)) {
    $d = json_decode($r['report_data'], true);
    if (!$d) continue;
    
    $debt = array_sum(array_column($d['debtors'] ?? [], 'amt'));
    $cred = array_sum(array_column($d['creditors'] ?? [], 'amt'));
    $pet = array_sum(array_column($d['pettys'] ?? [], 'amt'));
    $bank = array_sum(array_column($d['bankers'] ?? [], 'amt'));
    
    // آیتم‌های داینامیک
    $dyn_items = [];
    $dr = mysqli_query($conn, "SELECT di.name, dr2.amount_gram FROM dynamic_records dr2 JOIN dynamic_items di ON dr2.item_id = di.id WHERE dr2.report_id = {$r['id']}");
    while ($dy = mysqli_fetch_assoc($dr)) $dyn_items[] = $dy;
    $dyn_sum = array_sum(array_column($dyn_items, 'amount_gram'));
    
    // درآمد
    $income_items = [];
    $income_sum = 0;
    $income_gram_sum = 0; // متغیر اضافه شده برای محاسبه جمع گرم درآمد
    $ir = mysqli_query($conn, "SELECT di.name, dr_inc.amount_rial, dr_inc.amount_gram FROM income_daily_records dr_inc JOIN income_daily_items di ON dr_inc.item_id = di.id WHERE dr_inc.branch_id = {$r['user_id']} AND dr_inc.record_date = '$date'");
    while ($inc = mysqli_fetch_assoc($ir)) {
        $income_items[] = $inc;
        $income_sum += $inc['amount_rial'];
        $income_gram_sum += $inc['amount_gram']; // جمع زدن مقادیر معادل گرم
    }
    
    $branches[] = [
        'name' => $r['branch_name'],
        'debtors' => $d['debtors'] ?? [],
        'creditors' => $d['creditors'] ?? [],
        'pettys' => $d['pettys'] ?? [],
        'bankers' => $d['bankers'] ?? [],
        'dyn_items' => $dyn_items,
        'income_items' => $income_items,
        'debt_sum' => $debt,
        'cred_sum' => $cred,
        'pet_sum' => $pet,
        'bank_sum' => $bank,
        'dyn_sum' => $dyn_sum,
        'income_sum' => $income_sum,
        'income_gram_sum' => $income_gram_sum, // پاس دادن جمع گرم درآمد به آرایه خروجی
    ];
}

// ⭐ جمع کل
$t_d = array_sum(array_column($branches, 'debt_sum'));
$t_c = array_sum(array_column($branches, 'cred_sum'));
$t_p = array_sum(array_column($branches, 'pet_sum'));
$t_b = array_sum(array_column($branches, 'bank_sum'));
$t_dyn = array_sum(array_column($branches, 'dyn_sum'));
$t_inc = array_sum(array_column($branches, 'income_sum'));

// شماره گزارش (برای اسناد رسمی)
$report_id = strtoupper('TRZ-' . date('Ymd') . '-' . substr(md5($date . uniqid()), 0, 6));
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>گزارش رسمی تراز روزانه - <?php echo $formatted_date; ?></title>
    <style>
        @font-face {
            font-family: 'Modam';
            src: url('assets/fonts/Modam-Medium.woff2') format('woff2');
            font-display: swap;
        }
        @font-face {
            font-family: 'Vazirmatn';
            src: url('assets/fonts/Vazirmatn-Regular.woff2') format('woff2');
            font-display: swap;
        }
        
        :root {
            --primary-dark: #1a1a1a;
            --primary-gold: #b89e2c;
            --border-color: #333;
            --header-bg: #f8f8f8;
            --row-alt: #fafafa;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Modam', 'Vazirmatn', Tahoma, sans-serif;
            background: #fff;
            color: var(--primary-dark);
            line-height: 1.5;
            font-size: 10pt;
            padding: 0;
        }
        
        .report {
            max-width: 210mm;
            margin: 0 auto;
            background: #fff;
            padding: 10mm 15mm;
        }
        
        /* سربرگ رسمی */
        .official-header {
            border: 2px solid var(--border-color);
            border-radius: 4px;
            padding: 12px 15px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            background: var(--header-bg);
        }
        .header-right { text-align: right; }
        .header-right h1 {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 4px;
            color: var(--primary-dark);
        }
        .header-right .subtitle { font-size: 0.9rem; color: #555; margin-bottom: 8px; }
        .header-meta { font-size: 0.85rem; line-height: 1.8; }
        
        .header-left { text-align: center; min-width: 150px; }
        .company-logo {
            width: 80px;
            height: auto;
            max-height: 80px;
            object-fit: contain;
            margin: 0 auto 8px;
            display: block;
        }
        .report-id {
            font-family: monospace;
            font-size: 0.8rem;
            background: #eee;
            padding: 3px 8px;
            border-radius: 3px;
            display: inline-block;
            margin-top: 5px;
        }
        
        /* نوار خلاصه وضعیت */
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 5px;
            margin: 15px 0;
            border: 1px solid var(--border-color);
            padding: 10px;
            background: #fff;
        }
        .summary-item {
            text-align: center;
            padding: 5px;
            border-left: 1px solid #ddd;
        }
        .summary-item:first-child { border-left: none; }
        .summary-item .label { font-size: 0.75rem; color: #444; margin-bottom: 3px; font-weight: 700; }
        .summary-item .value { font-size: 1rem; font-weight: 700; color: #000; }
        .summary-item .unit { font-size: 0.7rem; color: #666; margin-right: 2px; }
        
        /* بخش شعبه */
        .branch-section {
            border: 1px solid var(--border-color);
            border-radius: 4px;
            margin-bottom: 15px;
            page-break-inside: avoid;
        }
        .branch-header {
            background: var(--header-bg);
            padding: 8px 12px;
            border-bottom: 1px solid var(--border-color);
            font-weight: 700;
            font-size: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        /* استایل گرید دو ستونه برای جداول */
        .tables-grid {
            display: grid;
            grid-template-columns: 1fr 1fr; /* دو ستون مساوی */
            gap: 15px;
            padding: 10px;
        }
        .tables-grid > table {
            margin: 0; /* حذف مارجین اضافی */
            height: fit-content;
        }
        /* درآمد ریالی معمولا پهن‌تر است، می‌تواند دو ستون را پر کند (اختیاری) */
        .type-income { grid-column: 1 / -1; }

        /* جداول */
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }
        th, td {
            border: 1px solid var(--border-color);
            padding: 5px 8px;
            text-align: center;
            vertical-align: middle;
        }
        th { background: var(--header-bg); font-weight: 700; }
        
        .sum-row {
            font-weight: 700;
            background: var(--row-alt);
            border-top: 2px solid var(--border-color);
        }
        
        .type-debt { border-right: 3px solid #000; }
        .type-cred { border-right: 3px solid #333; }
        .type-petty { border-right: 3px solid #555; }
        .type-bank { border-right: 3px solid #777; }
        .type-dyn { border-right: 3px solid #999; }
        .type-income { border-right: 3px solid #bbb; }
        
        /* فوتر و امضا */
        .signature-section {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 2px solid var(--border-color);
            page-break-inside: avoid;
        }
        .signature-box { text-align: center; padding: 10px; }
        .signature-line {
            border-top: 1px solid #000;
            margin: 15px 15px 5px;
            padding-top: 3px;
            font-size: 0.85rem;
            font-weight: 700;
        }
        .stamp-area {
            min-height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px dashed #999;
            margin: 5px;
            color: #777;
            font-size: 0.8rem;
        }
        
        .footer-official {
            text-align: center;
            margin-top: 15px;
            padding-top: 10px;
            border-top: 1px solid #ccc;
            font-size: 0.75rem;
            color: #555;
            page-break-inside: avoid;
        }
        
        /* استایل‌های پرینت */
        @media print {
            @page {
                size: A4 portrait;
                margin: 10mm;
            }
            body { background: #fff !important; font-size: 9.5pt !important; }
            .report { max-width: 100%; margin: 0; padding: 0; }
            .no-print { display: none !important; }
            .tables-grid { gap: 10px; padding: 8px; }
            .official-header, .summary-grid, .branch-section, .signature-section {
                page-break-inside: avoid;
            }
        }
        
        .print-controls {
            text-align: center;
            padding: 10px;
            background: #f0f0f0;
            margin-bottom: 15px;
        }
        .btn {
            padding: 6px 12px; margin: 0 4px;
            background: var(--primary-gold); color: #fff;
            border: none; border-radius: 4px; cursor: pointer;
            text-decoration: none; font-family: inherit;
        }
        .btn-outline { background: transparent; border: 1px solid var(--border-color); color: #000; }
    </style>
</head>
<body>
    <div class="print-controls no-print">
        <button class="btn" onclick="window.print()">🖨️ چاپ گزارش</button>
        <button class="btn btn-outline" onclick="window.location.href='?date=<?php echo date('Y-m-d', strtotime($date . ' -1 day')); ?>'">روز قبل</button>
        <button class="btn btn-outline" onclick="window.location.href='?date=<?php echo date('Y-m-d', strtotime($date . ' +1 day')); ?>'">روز بعد</button>
        <button class="btn btn-outline" onclick="window.location.href='?date=<?php echo date('Y-m-d'); ?>'">امروز</button>
    </div>

    <div class="report">
        <!-- سربرگ رسمی -->
        <div class="official-header">
            <div class="header-right">
                <h1>گزارش رسمی تراز روزانه</h1>
                <div class="subtitle">سامانه مدیریت مالی شعب</div>
                <div class="header-meta">
                    <div><strong>تاریخ گزارش:</strong> <?php echo $jalali_date; ?></div>
                    <div><strong>زمان چاپ:</strong> <?php echo date('H:i:s'); ?></div>
                    <div><strong>تعداد شعب:</strong> <?php echo count($branches); ?></div>
                </div>
            </div>
            <div class="header-left">
                <!-- آدرس لوگوی خود را در قسمت src قرار دهید -->
                <img src="assets/images/logo.png" alt="لوگو" class="company-logo">
                <div style="font-size:0.75rem">
                    <strong>شناسه گزارش:</strong><br>
                    <span class="report-id"><?php echo $report_id; ?></span>
                </div>
            </div>
        </div>

        <!-- خلاصه وضعیت کل -->
        <div class="summary-grid">
            <div class="summary-item">
                <div class="label">بدهکاران</div>
                <div class="value"><?php echo number_format($t_d, 1); ?> <span class="unit">م.ت</span></div>
            </div>
            <div class="summary-item">
                <div class="label">بستانکاران</div>
                <div class="value"><?php echo number_format($t_c, 1); ?> <span class="unit">م.ت</span></div>
            </div>
            <div class="summary-item">
                <div class="label">تنخواه</div>
                <div class="value"><?php echo number_format($t_p, 1); ?> <span class="unit">م.ت</span></div>
            </div>
            <div class="summary-item">
                <div class="label">بنکداران</div>
                <div class="value"><?php echo number_format($t_b, 1); ?> <span class="unit">گرم</span></div>
            </div>
            <div class="summary-item">
                <div class="label">آیتم‌های داینامیک</div>
                <div class="value"><?php echo number_format($t_dyn, 3); ?> <span class="unit">گرم</span></div>
            </div>
            <div class="summary-item">
                <div class="label">درآمد ریالی</div>
                <div class="value"><?php echo number_format($t_inc); ?> <span class="unit">ریال</span></div>
            </div>
        </div>

        <!-- جزئیات شعب -->
        <?php foreach ($branches as $index => $b): ?>
        <div class="branch-section">
            <div class="branch-header">
                <span>شعبه: <?php echo htmlspecialchars($b['name']); ?></span>
                <span style="font-weight:400">کد: <?php echo $index + 1; ?></span>
            </div>
            
            <!-- چیدمان دو ستونه جداول -->
            <div class="tables-grid">
                <?php if (!empty($b['debtors'])): ?>
                <table class="type-debt">
                    <thead><tr><th style="width:65%">شرح بدهی</th><th>مبلغ (م.ت)</th></tr></thead>
                    <tbody>
                        <?php foreach ($b['debtors'] as $x): if (!empty($x['name']) || !empty($x['amt'])): ?>
                        <tr><td style="text-align:right"><?php echo htmlspecialchars($x['name']); ?></td><td><?php echo number_format($x['amt'], 1); ?></td></tr>
                        <?php endif; endforeach; ?>
                        <tr class="sum-row"><td>جمع</td><td><?php echo number_format($b['debt_sum'], 1); ?></td></tr>
                    </tbody>
                </table>
                <?php endif; ?>

                <?php if (!empty($b['creditors'])): ?>
                <table class="type-cred">
                    <thead><tr><th style="width:65%">شرح بستانکاری</th><th>مبلغ (م.ت)</th></tr></thead>
                    <tbody>
                        <?php foreach ($b['creditors'] as $x): if (!empty($x['name']) || !empty($x['amt'])): ?>
                        <tr><td style="text-align:right"><?php echo htmlspecialchars($x['name']); ?></td><td><?php echo number_format($x['amt'], 1); ?></td></tr>
                        <?php endif; endforeach; ?>
                        <tr class="sum-row"><td>جمع</td><td><?php echo number_format($b['cred_sum'], 1); ?></td></tr>
                    </tbody>
                </table>
                <?php endif; ?>

                <?php if (!empty($b['pettys'])): ?>
                <table class="type-petty">
                    <thead><tr><th style="width:65%">شرح هزینه تنخواه</th><th>مبلغ (م.ت)</th></tr></thead>
                    <tbody>
                        <?php foreach ($b['pettys'] as $x): if (!empty($x['desc']) || !empty($x['amt'])): ?>
                        <tr><td style="text-align:right"><?php echo htmlspecialchars($x['desc']); ?></td><td><?php echo number_format($x['amt'], 1); ?></td></tr>
                        <?php endif; endforeach; ?>
                        <tr class="sum-row"><td>جمع</td><td><?php echo number_format($b['pet_sum'], 1); ?></td></tr>
                    </tbody>
                </table>
                <?php endif; ?>

                <?php if (!empty($b['bankers'])): ?>
                <table class="type-bank">
                    <thead><tr><th style="width:65%">نام بنکدار</th><th>وزن (گرم)</th></tr></thead>
                    <tbody>
                        <?php foreach ($b['bankers'] as $x): if (!empty($x['name']) || !empty($x['amt'])): ?>
                        <tr><td style="text-align:right"><?php echo htmlspecialchars($x['name']); ?></td><td><?php echo number_format($x['amt'], 1); ?></td></tr>
                        <?php endif; endforeach; ?>
                        <tr class="sum-row"><td>جمع</td><td><?php echo number_format($b['bank_sum'], 1); ?></td></tr>
                    </tbody>
                </table>
                <?php endif; ?>

                <?php if (!empty($b['dyn_items'])): ?>
                <table class="type-dyn">
                    <thead><tr><th style="width:65%">عنوان آیتم</th><th>مقدار (گرم)</th></tr></thead>
                    <tbody>
                        <?php foreach ($b['dyn_items'] as $x): ?>
                        <tr><td style="text-align:right"><?php echo htmlspecialchars($x['name']); ?></td><td><?php echo number_format($x['amount_gram'], 3); ?></td></tr>
                        <?php endforeach; ?>
                        <tr class="sum-row"><td>جمع</td><td><?php echo number_format($b['dyn_sum'], 3); ?></td></tr>
                    </tbody>
                </table>
                <?php endif; ?>

                <?php if (!empty($b['income_items'])): ?>
                <table class="type-income">
                    <thead><tr><th style="width:40%">عنوان درآمد</th><th>مبلغ (ریال)</th><th>معادل (گرم)</th></tr></thead>
                    <tbody>
                        <?php foreach ($b['income_items'] as $x): ?>
                        <tr>
                            <td style="text-align:right"><?php echo htmlspecialchars($x['name']); ?></td>
                            <td><?php echo number_format($x['amount_rial']); ?></td>
                            <td><?php echo number_format($x['amount_gram'], 3); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <!-- تغییر در ردیف جمع کل و جایگزینی کاراکتر خط تیره با متغیر جمع گرم -->
                        <tr class="sum-row">
                            <td>جمع کل</td>
                            <td><?php echo number_format($b['income_sum']); ?></td>
                            <td><?php echo number_format($b['income_gram_sum'], 3); ?></td>
                        </tr>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- بخش امضا و مهر -->
        <div class="signature-section">
            <div class="signature-box">
                <div class="stamp-area">مهر شعبه</div>
                <div class="signature-line">امضاء مسئول شعبه</div>
            </div>
            <div class="signature-box">
                <div class="stamp-area">مهر مالی</div>
                <div class="signature-line">امضاء مدیر مالی</div>
            </div>
            <div class="signature-box">
                <div class="stamp-area">مهر رسمی</div>
                <div class="signature-line">امضاء مدیر عامل</div>
            </div>
        </div>

        <div class="footer-official">
            این گزارش به صورت الکترونیکی تولید شده و دارای اعتبار رسمی می‌باشد. [شناسه: <?php echo $report_id; ?>]
        </div>
    </div>
</body>
</html>
