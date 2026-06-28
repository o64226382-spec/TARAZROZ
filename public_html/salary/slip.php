<?php
define('SECURE_ACCESS', true);
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireLogin();
require_once '../includes/jdf.php';

// ========== خواندن تنظیمات از دیتابیس ==========
$settings_query = mysqli_query($conn, "SELECT setting_key, setting_value FROM salary_settings");
$settings = [];
while ($row = mysqli_fetch_assoc($settings_query)) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// مقادیر با fallback به پیش‌فرض
$HOUSING = isset($settings['housing']) ? intval($settings['housing']) : 9000000;
$FOOD = isset($settings['food']) ? intval($settings['food']) : 11000000;
$CHILD_PER = isset($settings['child_per']) ? intval($settings['child_per']) : 2100000;
$INSURANCE_PERCENT = isset($settings['insurance_percent']) ? floatval($settings['insurance_percent']) : 7;
$TAX_EXEMPTION = isset($settings['tax_exemption']) ? intval($settings['tax_exemption']) : 120000000;
$TAX_PERCENT = isset($settings['tax_percent']) ? floatval($settings['tax_percent']) : 10;
$OVERTIME_MULTIPLIER = isset($settings['overtime_multiplier']) ? floatval($settings['overtime_multiplier']) : 1.4;
$months = ['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];

date_default_timezone_set('Asia/Tehran');

// محاسبه سال شمسی
$gy = intval(date('Y'));
$gm = intval(date('m'));
$gd = intval(date('d'));

if ($gm > 3 || ($gm == 3 && $gd >= 21)) {
    $current_year = $gy - 621;
} else {
    $current_year = $gy - 622;
}

if ($current_year < 1400 || $current_year > 1500) {
    $current_year = intval(jdate('Y', time(), '', 'Asia/Tehran', 'fa'));
}

$personnel_list = mysqli_query($conn, "SELECT id, first_name, last_name FROM salary_personnel WHERE status='active' ORDER BY last_name, first_name");
$msg = ''; $error = '';
$preview = null;

$preset_pid = isset($_GET['personnel_id']) ? intval($_GET['personnel_id']) : 0;
$preset_month = isset($_GET['month']) ? intval($_GET['month']) : 0;
$preset_year = isset($_GET['year']) ? intval($_GET['year']) : $current_year;

// افزودن پرسنل
if (isset($_POST['add_personnel'])) {
    $fname = mysqli_real_escape_string($conn, $_POST['first_name']);
    $lname = mysqli_real_escape_string($conn, $_POST['last_name']);
    $pos = mysqli_real_escape_string($conn, $_POST['position'] ?? '');
    $dept = mysqli_real_escape_string($conn, $_POST['department'] ?? '');
    $ncode = mysqli_real_escape_string($conn, $_POST['national_code'] ?? '');
    $pcode = mysqli_real_escape_string($conn, $_POST['personnel_code'] ?? '');
    $bsalary = intval(str_replace(',', '', $_POST['base_salary'] ?? '0'));
    $children = intval($_POST['children_count'] ?? 0);
    $insnum = mysqli_real_escape_string($conn, $_POST['insurance_number'] ?? '');
    $hdate = mysqli_real_escape_string($conn, $_POST['hire_date'] ?? '');
    
    mysqli_query($conn, "INSERT INTO salary_personnel (first_name, last_name, national_code, personnel_code, position, department, base_salary, children_count, insurance_number, hire_date) 
        VALUES ('$fname','$lname','$ncode','$pcode','$pos','$dept',$bsalary,$children,'$insnum','$hdate')");
    
    header('Location: index.php?msg=added');
    exit;
}

// محاسبه و ذخیره فیش
if (isset($_POST['calculate']) || isset($_POST['save_slip'])) {
    $pid = intval($_POST['personnel_id']);
    $month = intval($_POST['month']);
    $year = intval($_POST['year']);
    $wdays = floatval($_POST['working_days'] ?? 30);
    
    $overtime_hours = floatval($_POST['overtime_hours'] ?? 0);
    $overtime_manual = intval(str_replace(',', '', $_POST['overtime_manual'] ?? '0'));
    $bonus = intval(str_replace(',', '', $_POST['bonus'] ?? '0'));
    $dloan = intval(str_replace(',', '', $_POST['deduction_loan'] ?? '0'));
    $dother = intval(str_replace(',', '', $_POST['deduction_other'] ?? '0'));
    $notes = mysqli_real_escape_string($conn, $_POST['notes'] ?? '');
    
    $pres = mysqli_query($conn, "SELECT * FROM salary_personnel WHERE id=$pid");
    $pdata = mysqli_fetch_assoc($pres);
    
    $base = intval($pdata['base_salary']);
    $children = intval($pdata['children_count']);
    
    // ===== محاسبات قانونی =====
    $daily_rate = $base / 30;
    $hourly_rate = $base / 176; // 176 ساعت = 44 ساعت در هفته × 4 هفته
    
    $overtime_calculated = intval(round($overtime_hours * $hourly_rate * $OVERTIME_MULTIPLIER));
    $total_overtime = $overtime_calculated + $overtime_manual;
    $child_total = $children * $CHILD_PER;
    
    // فقط حقوق پایه تناسب می‌خورد - مسکن و بن ثابت
    $work_ratio = $wdays / 30;
    $base_worked = intval(round($base * $work_ratio));
    $housing_worked = $HOUSING;
    $food_worked = $FOOD;
    
    $gross = $base_worked + $housing_worked + $food_worked + $child_total + $total_overtime + $bonus;
    
    // بیمه: فقط پایه + مسکن + بن (بدون حق اولاد)
    $insurance_base = $base + $HOUSING + $FOOD;
    $insurance = intval(round($insurance_base * $INSURANCE_PERCENT / 100));
    
    // مالیات (یک نرخ ساده از تنظیمات)
    $monthly_exemption = $TAX_EXEMPTION / 12;
    $taxable = $gross - $insurance - $monthly_exemption;
    $tax = ($taxable > 0) ? intval(round($taxable * $TAX_PERCENT / 100)) : 0;
    
    $deductions = $insurance + $tax + $dloan + $dother;
    $net = $gross - $deductions;
    
    $preview = [
        'personnel_name' => $pdata['first_name'] . ' ' . $pdata['last_name'],
        'personnel_code' => $pdata['personnel_code'],
        'national_code' => $pdata['national_code'],
        'position' => $pdata['position'],
        'department' => $pdata['department'],
        'insurance_number' => $pdata['insurance_number'],
        'month' => $month,
        'year' => $year,
        'month_name' => $months[$month-1],
        'working_days' => $wdays,
        'daily_rate' => $daily_rate,
        'hourly_rate' => $hourly_rate,
        'base_salary' => $base,
        'base_worked' => $base_worked,
        'housing' => $HOUSING,
        'housing_worked' => $housing_worked,
        'food' => $FOOD,
        'food_worked' => $food_worked,
        'child_count' => $children,
        'child_allowance' => $child_total,
        'overtime_hours' => $overtime_hours,
        'overtime_calculated' => $overtime_calculated,
        'overtime_manual' => $overtime_manual,
        'total_overtime' => $total_overtime,
        'bonus' => $bonus,
        'total_gross' => $gross,
        'insurance' => $insurance,
        'tax' => $tax,
        'deduction_loan' => $dloan,
        'deduction_other' => $dother,
        'total_deductions' => $deductions,
        'net_salary' => $net,
        'notes' => $notes,
    ];
    
    if (isset($_POST['save_slip'])) {
        $check = mysqli_query($conn, "SELECT id FROM salary_archive WHERE personnel_id=$pid AND month=$month AND year=$year");
        if (mysqli_num_rows($check) == 0) {
            mysqli_query($conn, "INSERT INTO salary_archive (personnel_id, month, year, working_days, overtime_amount, overtime_hours, bonus, deduction_loan, deduction_other, housing_allowance, food_allowance, child_allowance, insurance_deduction, tax_deduction, total_gross, total_deductions, net_salary, notes) 
                VALUES ($pid,$month,$year,$wdays,$total_overtime,$overtime_hours,$bonus,$dloan,$dother,$housing_worked,$food_worked,$child_total,$insurance,$tax,$gross,$deductions,$net,'$notes')");
            $slip_id = mysqli_insert_id($conn);
            $msg = 'فیش با موفقیت ذخیره شد';
            $preview['slip_id'] = $slip_id;
        } else {
            $error = 'فیش این ماه قبلاً ثبت شده است';
        }
    }
}

$show_add_person = isset($_GET['action']) && $_GET['action'] == 'add_person';

function fm($n) { return number_format($n); }
function money_text($num) {
    $num = intval($num);
    if ($num == 0) return 'صفر ریال';
    
    $ones = ['', 'یک', 'دو', 'سه', 'چهار', 'پنج', 'شش', 'هفت', 'هشت', 'نه'];
    $tens = ['', 'ده', 'بیست', 'سی', 'چهل', 'پنجاه', 'شصت', 'هفتاد', 'هشتاد', 'نود'];
    $teens = ['ده', 'یازده', 'دوازده', 'سیزده', 'چهارده', 'پانزده', 'شانزده', 'هفده', 'هجده', 'نوزده'];
    $hundreds = ['', 'صد', 'دویست', 'سیصد', 'چهارصد', 'پانصد', 'ششصد', 'هفتصد', 'هشتصد', 'نهصد'];
    $thousands = ['', 'هزار', 'میلیون', 'میلیارد'];
    
    $result = '';
    $group = 0;
    
    while ($num > 0) {
        $part = $num % 1000;
        if ($part > 0) {
            $part_text = '';
            if ($part >= 100) {
                $part_text .= $hundreds[intval($part / 100)] . ' و ';
                $part %= 100;
            }
            if ($part >= 20) {
                $part_text .= $tens[intval($part / 10)] . ' و ';
                $part %= 10;
            } elseif ($part >= 10) {
                $part_text .= $teens[$part - 10] . ' و ';
                $part = 0;
            }
            if ($part > 0) {
                $part_text .= $ones[$part] . ' و ';
            }
            $part_text = rtrim($part_text, ' و ');
            if ($group > 0) {
                $part_text .= ' ' . $thousands[$group];
            }
            $result = $part_text . ($result ? ' و ' . $result : '');
        }
        $num = intval($num / 1000);
        $group++;
    }
    
    return $result . ' ریال';
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $show_add_person ? 'افزودن پرسنل' : 'صدور فیش حقوقی'; ?></title>
    <link href="../assets/fonts/fonts.css" rel="stylesheet">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Vazirmatn', sans-serif;
            background: #f0f2f5;
            color: #1a1f2e;
            padding: 20px;
        }
        .container { max-width: 880px; margin: 0 auto; }
        
        /* ===== دکمه‌ها ===== */
        .btn {
            padding: 8px 18px;
            border-radius: 6px;
            border: 1px solid #d0d5dd;
            background: #fff;
            cursor: pointer;
            font-family: 'Vazirmatn';
            font-size: 0.75rem;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s;
        }
        .btn:hover { border-color: #1a4a7a; }
        .btn-primary { background: #1a4a7a; color: #fff; border: none; }
        .btn-primary:hover { background: #0d3a5a; }
        .btn-success { background: #1a7a3a; color: #fff; border: none; }
        .btn-success:hover { background: #0d5a2a; }
        
        /* ===== هدر ===== */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 18px;
            background: #fff;
            border-radius: 8px;
            margin-bottom: 14px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
        }
        .header h2 { font-size: 0.9rem; font-weight: 700; }
        
        /* ===== کارت‌ها ===== */
        .card {
            background: #fff;
            border-radius: 8px;
            padding: 18px;
            margin-bottom: 14px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
        }
        .card h3 {
            font-size: 0.8rem;
            margin-bottom: 14px;
            color: #1a4a7a;
            padding-bottom: 8px;
            border-bottom: 1px solid #e8edf4;
        }
        
        /* ===== پیام‌ها ===== */
        .toast {
            padding: 10px 16px;
            border-radius: 6px;
            margin-bottom: 12px;
            font-weight: 600;
            font-size: 0.78rem;
        }
        .toast-success { background: #1a7a3a; color: #fff; }
        .toast-error { background: #7a1a1a; color: #fff; }
        
        /* ===== فرم ===== */
        .form-group { margin-bottom: 10px; }
        .form-group label {
            display: block;
            font-size: 0.7rem;
            color: #555f6e;
            margin-bottom: 3px;
            font-weight: 600;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #d0d5dd;
            border-radius: 6px;
            background: #fafbfc;
            font-family: 'Vazirmatn';
            font-size: 0.78rem;
            transition: border 0.2s;
        }
        .form-group input:focus, .form-group select:focus {
            border-color: #1a4a7a;
            outline: none;
        }
        .form-group .hint {
            font-size: 0.6rem;
            color: #8899aa;
            margin-top: 3px;
        }
        .row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .row3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; }
        @media (max-width: 500px) { .row2, .row3 { grid-template-columns: 1fr; } }
        
        /* ===== فیش رسمی ===== */
        .slip {
            background: #fff;
            border: 1px solid #c8cdd5;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        }
        
        /* سربرگ */
        .slip-head {
            background: linear-gradient(135deg, #0d1a3a 0%, #1a3a6a 100%);
            color: #fff;
            padding: 16px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 3px solid #c9a84c;
        }
        .slip-head .logo-text {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .slip-head .logo-text img {
            max-height: 45px;
            max-width: 45px;
            filter: brightness(0) invert(1);
        }
        .slip-head .logo-text .comp {
            font-size: 0.9rem;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        .slip-head .logo-text .comp small {
            display: block;
            font-size: 0.55rem;
            font-weight: 400;
            opacity: 0.75;
            letter-spacing: 0;
        }
        .slip-head .title {
            text-align: left;
            border-right: 2px solid rgba(255,255,255,0.2);
            padding-right: 16px;
        }
        .slip-head .title h2 {
            font-size: 1.05rem;
            font-weight: 800;
            letter-spacing: 1px;
        }
        .slip-head .title .date {
            font-size: 0.65rem;
            opacity: 0.75;
            font-weight: 400;
        }
        
        /* بدنه */
        .slip-body {
            padding: 20px 24px;
        }
        
        /* اطلاعات پرسنلی */
        .info-row {
            display: flex;
            flex-wrap: wrap;
            gap: 4px 28px;
            background: #f7f9fc;
            padding: 10px 16px;
            border-radius: 6px;
            margin-bottom: 16px;
            border: 1px solid #e8edf4;
            font-size: 0.72rem;
        }
        .info-row .item {
            display: flex;
            gap: 4px;
        }
        .info-row .item .lbl {
            color: #7788aa;
            font-weight: 600;
        }
        .info-row .item .val {
            font-weight: 600;
            color: #0d1a3a;
        }
        
        /* جدول */
        .table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.72rem;
            margin-bottom: 14px;
        }
        .table th {
            background: #e8edf4;
            padding: 8px 12px;
            text-align: center;
            font-weight: 700;
            font-size: 0.65rem;
            color: #1a2a4a;
            border-bottom: 2px solid #c8cdd5;
        }
        .table td {
            padding: 7px 12px;
            border-bottom: 1px solid #eef1f5;
        }
        .table .desc { text-align: right; }
        .table .amt { 
            text-align: left; 
            direction: ltr; 
            font-weight: 500;
            font-feature-settings: "tnum";
        }
        .table .sec td {
            background: #f5f7fa;
            font-weight: 700;
            color: #1a2a4a;
            border-top: 1px solid #d0d5dd;
            border-bottom: 1px solid #d0d5dd;
        }
        .table .total td {
            font-weight: 700;
            border-top: 2px solid #1a2a4a;
            background: #f7f9fc;
        }
        .table .net td {
            font-weight: 800;
            font-size: 0.9rem;
            background: #e6f4ea;
            color: #0d5a2a;
            border: 2px solid #0d5a2a;
        }
        .table .deduct { color: #a02020; }
        .table .no-item {
            text-align: center;
            color: #8899aa;
            font-size: 0.65rem;
            padding: 6px;
        }
        
        /* مبلغ به حروف */
        .words {
            background: #f7f9fc;
            padding: 10px 16px;
            border-radius: 6px;
            font-size: 0.72rem;
            margin-bottom: 14px;
            border-right: 4px solid #1a4a7a;
        }
        .words strong { color: #0d1a3a; }
        
        /* توضیحات */
        .note-box {
            background: #fcf8e8;
            padding: 8px 16px;
            border-radius: 4px;
            border: 1px solid #ece0b0;
            font-size: 0.68rem;
            color: #5a4a1a;
            margin-bottom: 14px;
        }
        
        /* فوتر امضا */
        .slip-foot {
            border-top: 2px solid #0d1a3a;
            padding: 14px 24px 16px;
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 16px;
            background: #fafbfc;
        }
        .slip-foot .sig {
            text-align: center;
            font-size: 0.6rem;
        }
        .slip-foot .sig .lbl {
            color: #7788aa;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        .slip-foot .sig .line {
            border-bottom: 1.5px solid #1a2a4a;
            margin: 22px 0 3px;
        }
        .slip-foot .sig .name {
            font-weight: 600;
            font-size: 0.65rem;
            color: #0d1a3a;
        }
        .slip-foot .sig .stamp {
            font-size: 0.55rem;
            color: #1a4a7a;
            font-weight: 700;
            margin-top: 4px;
        }
        .slip-foot .sig .stamp img {
            max-height: 30px;
            max-width: 50px;
            opacity: 0.7;
        }
        
        /* ===== دکمه‌های عملیات ===== */
        .actions {
            display: flex;
            gap: 8px;
            margin-top: 14px;
        }
        .actions button, .actions a { 
            flex: 1; 
            padding: 10px; 
            text-align: center;
            font-size: 0.75rem;
        }
        
        /* ===== چاپ ===== */
        @media print {
            body { background: #fff; padding: 0; }
            .header, .card, .actions, .toast, .no-print { display: none !important; }
            .slip { border: 1px solid #000; border-radius: 0; box-shadow: none; }
            .slip-head { background: #0d1a3a !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .table th { background: #e8edf4 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .sec td { background: #f5f7fa !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .net td { background: #e6f4ea !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .total td { background: #f7f9fc !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .info-row { background: #f7f9fc !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .words { background: #f7f9fc !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .slip-foot { background: #fafbfc !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>

<div class="container">
    <!-- ===== هدر ===== -->
    <div class="header no-print">
        <h2><?php echo $show_add_person ? 'افزودن پرسنل' : 'صدور فیش حقوقی'; ?></h2>
        <a href="index.php" class="btn">بازگشت</a>
    </div>
    
    <!-- ===== پیام‌ها ===== -->
    <?php if ($msg): ?>
    <div class="toast toast-success no-print">✅ <?php echo $msg; ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="toast toast-error no-print">❌ <?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if ($show_add_person): ?>
    
    <!-- ===== فرم افزودن پرسنل ===== -->
    <div class="card no-print">
        <h3>➕ اطلاعات پرسنل جدید</h3>
        <form method="POST">
            <div class="row2">
                <div class="form-group"><label>نام *</label><input name="first_name" required></div>
                <div class="form-group"><label>نام خانوادگی *</label><input name="last_name" required></div>
            </div>
            <div class="row2">
                <div class="form-group"><label>کد ملی</label><input name="national_code" maxlength="10"></div>
                <div class="form-group"><label>کد پرسنلی</label><input name="personnel_code"></div>
            </div>
            <div class="row2">
                <div class="form-group"><label>سمت</label><input name="position"></div>
                <div class="form-group"><label>واحد</label><input name="department"></div>
            </div>
            <div class="row3">
                <div class="form-group"><label>حقوق پایه (ریال)</label><input name="base_salary" value="0"></div>
                <div class="form-group"><label>تعداد اولاد</label><input type="number" name="children_count" value="0" min="0"></div>
                <div class="form-group"><label>شماره بیمه</label><input name="insurance_number"></div>
            </div>
            <button class="btn btn-primary" name="add_personnel" style="width:100%;padding:10px;">ثبت پرسنل</button>
        </form>
    </div>
    
    <?php else: ?>
    
    <!-- ===== فرم صدور فیش ===== -->
    <div class="card no-print">
        <h3>📋 اطلاعات فیش</h3>
        <form method="POST">
            <div class="form-group">
                <label>پرسنل *</label>
                <select name="personnel_id" required>
                    <option value="">-- انتخاب کنید --</option>
                    <?php 
                    mysqli_data_seek($personnel_list, 0); 
                    while ($p = mysqli_fetch_assoc($personnel_list)): 
                        $selected_pid = isset($_POST['personnel_id']) ? $_POST['personnel_id'] : $preset_pid;
                    ?>
                    <option value="<?php echo $p['id']; ?>" <?php echo $selected_pid == $p['id'] ? 'selected' : ''; ?>>
                        <?php echo $p['first_name'].' '.$p['last_name']; ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="row2">
                <div class="form-group">
                    <label>ماه *</label>
                    <select name="month" required>
                        <option value="">-- ماه --</option>
                        <?php 
                        $selected_month = isset($_POST['month']) ? intval($_POST['month']) : $preset_month;
                        foreach($months as $i => $m): 
                        ?>
                        <option value="<?php echo $i+1; ?>" <?php echo $selected_month == ($i+1) ? 'selected' : ''; ?>>
                            <?php echo $m; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>سال *</label>
                    <select name="year" required>
                        <option value="">-- سال --</option>
                        <?php 
                        $selected_year = isset($_POST['year']) ? intval($_POST['year']) : $preset_year;
                        for($y = $current_year + 1; $y >= $current_year - 5; $y--): 
                        ?>
                        <option value="<?php echo $y; ?>" <?php echo $selected_year == $y ? 'selected' : ''; ?>>
                            <?php echo $y; ?>
                        </option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label>روز های ماه</label>
                <input type="number" name="working_days" value="<?php echo $_POST['working_days'] ?? '30'; ?>" step="0.5" min="0" max="31">
                <div class="hint">تعداد روزهایی که کارگر در این ماه کار کرده است</div>
            </div>
            
            <div style="background:#f7f9fc; border-radius:6px; padding:14px; margin-bottom:12px; border:1px solid #e8edf4;">
                <div class="form-group">
                    <label>ساعات اضافه‌کاری</label>
                    <input type="number" name="overtime_hours" value="<?php echo $_POST['overtime_hours'] ?? '0'; ?>" step="0.5" min="0">
                    <div class="hint"><?php echo $OVERTIME_MULTIPLIER; ?> × (حقوق پایه ÷ ۱۷۶)</div>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label>اضافه‌کاری دستی (ریال)</label>
                    <input type="text" name="overtime_manual" value="<?php echo $_POST['overtime_manual'] ?? '0'; ?>" oninput="formatNumberInput(this)">
                    <div class="hint">برای موارد خاص که مبلغ توافقی است</div>
                </div>
            </div>
            
            <div class="row2">
                <div class="form-group"><label>پاداش (ریال)</label><input type="text" name="bonus" value="<?php echo $_POST['bonus'] ?? '0'; ?>" oninput="formatNumberInput(this)"></div>
                <div class="form-group"><label>اقساط وام (ریال)</label><input type="text" name="deduction_loan" value="<?php echo $_POST['deduction_loan'] ?? '0'; ?>" oninput="formatNumberInput(this)"></div>
            </div>
            <div class="form-group"><label>سایر کسورات (ریال)</label><input type="text" name="deduction_other" value="<?php echo $_POST['deduction_other'] ?? '0'; ?>" oninput="formatNumberInput(this)"></div>
            <div class="form-group"><label>توضیحات</label><textarea name="notes" rows="2"><?php echo $_POST['notes'] ?? ''; ?></textarea></div>
            
            <button class="btn btn-primary" name="calculate" style="width:100%;padding:10px;">🔍 محاسبه و پیش‌نمایش</button>
        </form>
    </div>
    
    <!-- ============================================================ -->
    <!-- ===== فیش حقوقی ===== -->
    <!-- ============================================================ -->
    <?php if ($preview): ?>
    <div class="slip" id="printArea">
        
        <!-- ===== سربرگ ===== -->
<div class="slip-head">
    <div class="logo-text">
        <img src="logo.png" alt="لوگو" onerror="this.style.display='none'">
        <div class="comp">
            مجموعه ثناگلد
            <small>
                شعبه ۱: تبریز، چهار راه ابوریحان، پاساژ ایران، همکف، پلاک ۱۵ – تلفن: ۰۴۱۳۴۷۸۲۳۷۳
                <br>
                شعبه ۲: تبریز، چهار راه طالقانی، روبروی مسجد عربلر، پلاک ۸۴۸ – تلفن: ۰۴۱۳۵۴۰۶۴۸۶
                <br>
                شعبه ۳: تبریز، آبرسان، برج سفید، پلاک ۲۱ – تلفن: ۰۴۱۳۳۳۴۷۷۰۹
            </small>
        </div>
    </div>
    <div class="title">
        <h2>فیش حقوقی</h2>
        <div class="date"><?php echo $preview['month_name'] . ' ' . $preview['year']; ?></div>
    </div>
</div>
        
        <!-- ===== بدنه ===== -->
        <div class="slip-body">
            
            <!-- اطلاعات پرسنلی -->
            <div class="info-row">
                <span class="item"><span class="lbl">نام:</span><span class="val"><?php echo $preview['personnel_name']; ?></span></span>
                <span class="item"><span class="lbl">کد پرسنلی:</span><span class="val"><?php echo $preview['personnel_code'] ?: '---'; ?></span></span>
                <span class="item"><span class="lbl">سمت:</span><span class="val"><?php echo $preview['position'] ?: '---'; ?></span></span>
                <span class="item"><span class="lbl">واحد:</span><span class="val"><?php echo $preview['department'] ?: '---'; ?></span></span>
                <span class="item"><span class="lbl">روز های ماه:</span><span class="val"><?php echo $preview['working_days']; ?></span></span>
            </div>
            
            <!-- جدول مزایا و کسورات -->
            <table class="table">
                <thead>
                    <tr><th style="width:40px;">#</th><th>شرح</th><th style="width:170px;">مبلغ (ریال)</th></tr>
                </thead>
                <tbody>
                    
                    <!-- ===== بخش مزایا ===== -->
                    <tr class="sec"><td colspan="3">مزایا و پرداخت‌ها</td></tr>
                    
                    <?php $i = 1; ?>
                    
                    <!-- حقوق پایه (همیشه نمایش داده می‌شود) -->
                    <tr>
                        <td><?php echo $i++; ?></td>
                        <td class="desc">حقوق پایه (<?php echo fm($preview['base_salary']); ?> × <?php echo $preview['working_days']; ?>/۳۰)</td>
                        <td class="amt"><?php echo fm($preview['base_worked']); ?></td>
                    </tr>
                    
                    <!-- حق مسکن -->
                    <?php if ($preview['housing_worked'] > 0): ?>
                    <tr>
                        <td><?php echo $i++; ?></td>
                        <td class="desc">حق مسکن (ثابت)</td>
                        <td class="amt"><?php echo fm($preview['housing_worked']); ?></td>
                    </tr>
                    <?php endif; ?>
                    
                    <!-- بن کارگری -->
                    <?php if ($preview['food_worked'] > 0): ?>
                    <tr>
                        <td><?php echo $i++; ?></td>
                        <td class="desc">بن کارگری (ثابت)</td>
                        <td class="amt"><?php echo fm($preview['food_worked']); ?></td>
                    </tr>
                    <?php endif; ?>
                    
                    <!-- حق اولاد -->
                    <?php if ($preview['child_allowance'] > 0): ?>
                    <tr>
                        <td><?php echo $i++; ?></td>
                        <td class="desc">حق اولاد (<?php echo $preview['child_count']; ?> فرزند)</td>
                        <td class="amt"><?php echo fm($preview['child_allowance']); ?></td>
                    </tr>
                    <?php endif; ?>
                    
                    <!-- اضافه‌کاری ساعتی -->
                    <?php if ($preview['overtime_calculated'] > 0): ?>
                    <tr>
                        <td><?php echo $i++; ?></td>
                        <td class="desc">اضافه‌کاری (<?php echo $preview['overtime_hours']; ?> ساعت × <?php echo $OVERTIME_MULTIPLIER; ?>)</td>
                        <td class="amt"><?php echo fm($preview['overtime_calculated']); ?></td>
                    </tr>
                    <?php endif; ?>
                    
                    <!-- اضافه‌کاری دستی -->
                    <?php if ($preview['overtime_manual'] > 0): ?>
                    <tr>
                        <td><?php echo $i++; ?></td>
                        <td class="desc">اضافه‌کاری (توافقی)</td>
                        <td class="amt"><?php echo fm($preview['overtime_manual']); ?></td>
                    </tr>
                    <?php endif; ?>
                    
                    <!-- پاداش -->
                    <?php if ($preview['bonus'] > 0): ?>
                    <tr>
                        <td><?php echo $i++; ?></td>
                        <td class="desc">پاداش</td>
                        <td class="amt"><?php echo fm($preview['bonus']); ?></td>
                    </tr>
                    <?php endif; ?>
                    
                    <!-- جمع مزایا -->
                    <tr class="total">
                        <td colspan="2">جمع مزایا</td>
                        <td class="amt"><?php echo fm($preview['total_gross']); ?></td>
                    </tr>
                    
                    <!-- ===== بخش کسورات ===== -->
<?php 
$has_deduction = false;
if ($preview['insurance'] > 0) $has_deduction = true;
if ($preview['tax'] > 0) $has_deduction = true;
if ($preview['deduction_loan'] > 0) $has_deduction = true;
if ($preview['deduction_other'] > 0) $has_deduction = true;
?>

<?php if ($has_deduction): ?>
    <tr class="sec"><td colspan="3">کسورات</td></tr>
    
    <?php $j = 1; ?>
    
    <?php if ($preview['insurance'] > 0): ?>
    <tr>
        <td><?php echo $j++; ?></td>
        <td class="desc">بیمه تأمین اجتماعی (<?php echo $INSURANCE_PERCENT; ?>٪)</td>
        <td class="amt deduct"><?php echo fm($preview['insurance']); ?></td>
    </tr>
    <?php endif; ?>
    
    <?php if ($preview['tax'] > 0): ?>
    <tr>
        <td><?php echo $j++; ?></td>
        <td class="desc">مالیات حقوق</td>
        <td class="amt deduct"><?php echo fm($preview['tax']); ?></td>
    </tr>
    <?php endif; ?>
    
    <?php if ($preview['deduction_loan'] > 0): ?>
    <tr>
        <td><?php echo $j++; ?></td>
        <td class="desc">اقساط وام</td>
        <td class="amt deduct"><?php echo fm($preview['deduction_loan']); ?></td>
    </tr>
    <?php endif; ?>
    
    <?php if ($preview['deduction_other'] > 0): ?>
    <tr>
        <td><?php echo $j++; ?></td>
        <td class="desc">سایر کسورات</td>
        <td class="amt deduct"><?php echo fm($preview['deduction_other']); ?></td>
    </tr>
    <?php endif; ?>
    
    <tr class="total">
        <td colspan="2">جمع کسورات</td>
        <td class="amt deduct"><?php echo fm($preview['total_deductions']); ?></td>
    </tr>
<?php endif; ?>
                    
                    <!-- ===== خالص پرداختی ===== -->
                    <tr class="net">
                        <td colspan="2">خالص پرداختی</td>
                        <td class="amt"><?php echo fm($preview['net_salary']); ?></td>
                    </tr>
                    
                </tbody>
            </table>
            
            <!-- مبلغ به حروف -->
            <div class="words">
                <strong>مبلغ به حروف:</strong> <?php echo money_text($preview['net_salary']); ?>
            </div>
            
            <!-- توضیحات -->
            <?php if ($preview['notes']): ?>
            <div class="note-box">
                <strong>📌 توضیحات:</strong> <?php echo nl2br($preview['notes']); ?>
            </div>
            <?php endif; ?>
            
        </div>
        
        <!-- ===== فوتر با امضا ===== -->
        <div class="slip-foot">
            <div class="sig">
                <div class="lbl">امضاء کارمند</div>
                <div class="line"></div>
                <div class="name"><?php echo $preview['personnel_name']; ?></div>
            </div>
            <div class="sig">
                <div class="lbl">مسئول حسابداری</div>
                <div class="line"></div>
                <div class="stamp">
                    <img src="stamp.png" alt="مهر" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                    <span style="display:none;">مهر شرکت</span>
                </div>
            </div>
            <div class="sig">
                <div class="lbl">مدیرعامل</div>
                <div class="line"></div>
                <div class="name">____________________</div>
            </div>
        </div>
        
    </div>
    <!-- ===== پایان فیش ===== -->
    
    <!-- ===== دکمه‌های عملیات ===== -->
    <div class="actions no-print">
        <form method="POST" style="flex:1;display:flex;gap:8px;" onsubmit="prepareFormSubmit()">
            <?php foreach($_POST as $k => $v): if($k != 'save_slip' && $k != 'calculate'): ?>
            <input type="hidden" name="<?php echo htmlspecialchars($k); ?>" value="<?php echo htmlspecialchars($v); ?>">
            <?php endif; endforeach; ?>
            <button class="btn btn-success" name="save_slip" style="flex:1;">💾 ذخیره نهایی</button>
        </form>
        <button class="btn btn-primary" onclick="window.print()" style="flex:1;">🖨️ چاپ فیش</button>
    </div>
    
    <?php endif; ?>
    <?php endif; ?>
    
</div>

<script>
function formatNumberInput(input) {
    let value = input.value.replace(/,/g, '').replace(/[^0-9]/g, '');
    if (value) input.value = Number(value).toLocaleString('en-US');
    else input.value = '';
}

function prepareFormSubmit() {
    document.querySelectorAll('input[type="text"]').forEach(function(input) {
        if (input.name.includes('overtime_manual') || 
            input.name.includes('bonus') || 
            input.name.includes('deduction')) {
            input.value = input.value.replace(/,/g, '');
        }
    });
}
</script>

</body>
</html>