<?php
define('SECURE_ACCESS', true);
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireLogin();

$slip_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$query = mysqli_query($conn, "
    SELECT sa.*, sp.first_name, sp.last_name, sp.national_code, sp.personnel_code, 
           sp.position, sp.department, sp.insurance_number, sp.base_salary
    FROM salary_archive sa 
    JOIN salary_personnel sp ON sa.personnel_id = sp.id 
    WHERE sa.id = $slip_id
");

if (!$query || mysqli_num_rows($query) == 0) {
    die('فیش یافت نشد');
}

$slip = mysqli_fetch_assoc($query);
$months = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];

function fmt($n) { return number_format($n); }
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>فیش حقوقی - <?php echo $slip['first_name'] . ' ' . $slip['last_name']; ?></title>
    <link href="../assets/fonts/fonts.css" rel="stylesheet">
    <style>
        @page { size: A4; margin: 10mm; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Vazirmatn', sans-serif;
            direction: rtl;
            background: #fff;
            color: #1a1a1a;
            font-size: 10pt;
            line-height: 2;
            max-width: 190mm;
            margin: 0 auto;
            padding: 10mm;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #333;
            padding-bottom: 4mm;
            margin-bottom: 6mm;
        }
        .header .right { text-align: right; }
        .header .left { text-align: left; font-size: 8pt; color: #555; }
        .logo { font-weight: bold; font-size: 14pt; letter-spacing: 2px; }
        .title {
            text-align: center;
            font-size: 12pt;
            font-weight: bold;
            margin: 6mm 0;
            padding: 3mm;
            border: 1px solid #333;
            background: #f9f9f9;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 4mm 0;
        }
        th {
            background: #f0f0f0;
            padding: 6px 8px;
            border: 1px solid #ccc;
            text-align: right;
            font-size: 9pt;
        }
        td {
            padding: 6px 8px;
            border: 1px solid #ccc;
            font-size: 9pt;
        }
        .num { text-align: left; }
        .total-row td { font-weight: bold; background: #f9f9f9; }
        .net-row td { font-weight: bold; font-size: 11pt; background: #e8f5e9; }
        .footer {
            margin-top: 8mm;
            display: flex;
            justify-content: space-between;
            border-top: 1px solid #ccc;
            padding-top: 5mm;
            font-size: 8pt;
        }
        .sig { text-align: center; }
        .sig .line { 
            display: block; width: 100px; height: 1px; 
            background: #000; margin: 10mm auto 2mm; 
        }
        @media print {
            body { padding: 0; }
        }
    </style>
</head>
<body onload="window.print();">

<div class="header">
    <div class="right">
        <div class="logo">فیش حقوقی</div>
        <div style="font-size:8pt;"><?php echo $months[$slip['month']-1] . ' ' . $slip['year']; ?></div>
    </div>
    <div class="left">
        <div>تاریخ صدور: <?php echo date('Y/m/d'); ?></div>
    </div>
</div>

<div class="title">فیش حقوقی ماهانه</div>

<table>
    <tr><th colspan="2">اطلاعات پرسنلی</th></tr>
    <tr><td width="30%">نام و نام خانوادگی</td><td><?php echo $slip['first_name'] . ' ' . $slip['last_name']; ?></td></tr>
    <tr><td>کد پرسنلی</td><td><?php echo $slip['personnel_code'] ?: '---'; ?></td></tr>
    <tr><td>کد ملی</td><td><?php echo $slip['national_code'] ?: '---'; ?></td></tr>
    <tr><td>سمت</td><td><?php echo $slip['position'] ?: '---'; ?></td></tr>
    <tr><td>واحد</td><td><?php echo $slip['department'] ?: '---'; ?></td></tr>
    <tr><td>ماه کاری</td><td><?php echo $months[$slip['month']-1] . ' ' . $slip['year']; ?></td></tr>
    <tr><td>روزهای کارکرد</td><td><?php echo $slip['working_days']; ?> روز</td></tr>
</table>

<table>
    <tr><th>شرح</th><th class="num">مبلغ (ریال)</th></tr>
    <tr><td>حقوق پایه</td><td class="num"><?php echo fmt($slip['base_salary']); ?></td></tr>
    <tr><td>حق مسکن</td><td class="num"><?php echo fmt($slip['housing_allowance']); ?></td></tr>
    <tr><td>بن کارگری</td><td class="num"><?php echo fmt($slip['food_allowance']); ?></td></tr>
    <tr><td>حق اولاد</td><td class="num"><?php echo fmt($slip['child_allowance']); ?></td></tr>
    <tr><td>اضافه‌کاری</td><td class="num"><?php echo fmt($slip['overtime_amount']); ?></td></tr>
    <tr><td>پاداش</td><td class="num"><?php echo fmt($slip['bonus']); ?></td></tr>
    <tr class="total-row"><td>جمع حقوق و مزایا</td><td class="num"><?php echo fmt($slip['total_gross']); ?></td></tr>
</table>

<table>
    <tr><th>شرح</th><th class="num">مبلغ (ریال)</th></tr>
    <tr><td>بیمه تأمین اجتماعی (۷٪)</td><td class="num"><?php echo fmt($slip['insurance_deduction']); ?></td></tr>
    <tr><td>مالیات</td><td class="num"><?php echo fmt($slip['tax_deduction']); ?></td></tr>
    <tr><td>اقساط وام</td><td class="num"><?php echo fmt($slip['deduction_loan']); ?></td></tr>
    <tr><td>سایر کسورات</td><td class="num"><?php echo fmt($slip['deduction_other']); ?></td></tr>
    <tr class="total-row"><td>جمع کسورات</td><td class="num"><?php echo fmt($slip['total_deductions']); ?></td></tr>
</table>

<table>
    <tr class="net-row"><td>خالص پرداختی</td><td class="num"><?php echo fmt($slip['net_salary']); ?> ریال</td></tr>
</table>

<?php if ($slip['notes']): ?>
<p style="font-size:8pt;color:#555;">توضیحات: <?php echo $slip['notes']; ?></p>
<?php endif; ?>

<div class="footer">
    <div class="sig">
        <div>امضاء کارمند</div>
        <div class="line"></div>
        <div><?php echo $slip['first_name'] . ' ' . $slip['last_name']; ?></div>
    </div>
    <div class="sig">
        <div>مدیر مالی</div>
        <div class="line"></div>
    </div>
    <div class="sig">
        <div>مدیرعامل</div>
        <div class="line"></div>
    </div>
</div>

</body>
</html>