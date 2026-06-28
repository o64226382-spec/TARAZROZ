<?php
define('SECURE_ACCESS', true);
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireLogin();

// دریافت تنظیمات فعلی
$settings_query = mysqli_query($conn, "SELECT * FROM salary_settings ORDER BY id");
$settings = [];
while ($row = mysqli_fetch_assoc($settings_query)) {
    $settings[$row['setting_key']] = $row;
}

$msg = '';
$error = '';

// ذخیره تنظیمات
if (isset($_POST['save_settings'])) {
    $housing = intval(str_replace(',', '', $_POST['housing'] ?? '9000000'));
    $food = intval(str_replace(',', '', $_POST['food'] ?? '11000000'));
    $child_per = intval(str_replace(',', '', $_POST['child_per'] ?? '2100000'));
    $insurance_percent = floatval($_POST['insurance_percent'] ?? 7);
    $tax_exemption = intval(str_replace(',', '', $_POST['tax_exemption'] ?? '120000000'));
    $tax_percent = floatval($_POST['tax_percent'] ?? 10);
    $overtime_multiplier = floatval($_POST['overtime_multiplier'] ?? 1.4);
    
    // اعتبارسنجی
    if ($housing < 0 || $food < 0 || $child_per < 0) {
        $error = 'مقادیر نمی‌توانند منفی باشند';
    } elseif ($insurance_percent < 0 || $insurance_percent > 100) {
        $error = 'درصد بیمه باید بین ۰ تا ۱۰۰ باشد';
    } elseif ($tax_percent < 0 || $tax_percent > 100) {
    $error = 'درصد مالیات باید بین ۰ تا ۱۰۰ باشد';
} elseif ($overtime_multiplier < 1 || $overtime_multiplier > 3) {
    $error = 'ضریب اضافه‌کاری باید بین ۱ تا ۳ باشد';
} else {
        $updates = [
    ['housing', $housing, 'حق مسکن ماهانه (ریال)'],
    ['food', $food, 'بن کارگری ماهانه (ریال)'],
    ['child_per', $child_per, 'حق اولاد به ازای هر فرزند (ریال)'],
    ['insurance_percent', $insurance_percent, 'درصد بیمه تأمین اجتماعی'],
    ['tax_exemption', $tax_exemption, 'معافیت مالیاتی سالانه (ریال)'],
    ['tax_percent', $tax_percent, 'درصد مالیات حقوق'],
    ['overtime_multiplier', $overtime_multiplier, 'ضریب اضافه‌کاری']
];
        
        $success = true;
        foreach ($updates as $update) {
            $key = $update[0];
            $value = $update[1];
            $label = $update[2];
            
            $result = mysqli_query($conn, "
                INSERT INTO salary_settings (setting_key, setting_value, setting_label) 
                VALUES ('$key', '$value', '$label') 
                ON DUPLICATE KEY UPDATE setting_value = '$value', setting_label = '$label'
            ");
            
            if (!$result) {
                $success = false;
                break;
            }
        }
        
        if ($success) {
            $msg = 'تنظیمات با موفقیت ذخیره شد و به تمام محاسبات اعمال گردید';
            // بروزرسانی مقادیر نمایشی
            $settings_query = mysqli_query($conn, "SELECT * FROM salary_settings ORDER BY id");
            $settings = [];
            while ($row = mysqli_fetch_assoc($settings_query)) {
                $settings[$row['setting_key']] = $row;
            }
        } else {
            $error = 'خطا در ذخیره تنظیمات';
        }
    }
}

function fm($n) { return number_format($n); }
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تنظیمات محاسبات حقوق</title>
    <link href="../assets/fonts/fonts.css" rel="stylesheet">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Vazirmatn', sans-serif;
            background: #f5f6f8;
            color: #1a1f2e;
            min-height: 100vh;
            padding: 16px;
        }
        .container { max-width: 700px; margin: 0 auto; }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 20px;
            background: #fff;
            border: 1px solid #e0e3e8;
            border-radius: 14px;
            margin-bottom: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
        }
        .header h2 { font-size: 0.95rem; font-weight: 700; }
        
        .btn {
            padding: 8px 16px;
            border-radius: 8px;
            border: 1px solid #e0e3e8;
            background: #fff;
            color: #1a1f2e;
            cursor: pointer;
            font-family: 'Vazirmatn';
            font-size: 0.75rem;
            text-decoration: none;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn:hover { border-color: #3b6fd4; color: #3b6fd4; }
        .btn-primary { background: #3b6fd4; color: #fff; border: none; }
        .btn-primary:hover { opacity: 0.9; color: #fff; }
        
        .card {
            background: #fff;
            border: 1px solid #e0e3e8;
            border-radius: 14px;
            padding: 24px;
            margin-bottom: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
        }
        .card h3 {
            font-size: 0.85rem;
            margin-bottom: 16px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e0e3e8;
            color: #3b6fd4;
        }
        
        .toast {
            padding: 10px 14px;
            border-radius: 10px;
            margin-bottom: 12px;
            font-weight: 600;
            font-size: 0.78rem;
        }
        .toast-success { background: #10b981; color: #fff; }
        .toast-error { background: #ef4444; color: #fff; }
        
        .form-group { margin-bottom: 14px; }
        .form-group label {
            display: block;
            font-size: 0.72rem;
            color: #555f6e;
            margin-bottom: 5px;
            font-weight: 600;
        }
        .form-group input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #e0e3e8;
            border-radius: 8px;
            background: #f8f9fb;
            color: #1a1f2e;
            font-family: 'Vazirmatn';
            font-size: 0.8rem;
        }
        .form-group input:focus {
            border-color: #3b6fd4;
            outline: none;
            box-shadow: 0 0 0 3px rgba(59,111,212,0.08);
        }
        .form-group .hint {
            font-size: 0.65rem;
            color: #888;
            margin-top: 4px;
        }
        
        .setting-row {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            background: #f8fafc;
            border-radius: 8px;
            margin-bottom: 10px;
            border: 1px solid #e2e8f0;
        }
        .setting-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            background: #eff6ff;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .setting-info { flex: 1; }
        .setting-name { font-size: 0.75rem; font-weight: 600; }
        .setting-desc { font-size: 0.65rem; color: #888; }
        .setting-current {
            background: #3b6fd4;
            color: #fff;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.65rem;
            white-space: nowrap;
        }
        
        @media (max-width: 600px) {
            .setting-row { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h2>تنظیمات محاسبات حقوق و دستمزد</h2>
        <a href="index.php" class="btn">بازگشت</a>
    </div>
    
    <?php if ($msg): ?>
    <div class="toast toast-success"><?php echo $msg; ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
    <div class="toast toast-error"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <div class="card">
        <h3>مقادیر پایه حقوق (ریال)</h3>
        
        <form method="POST">
            <div class="setting-row">
                <div class="setting-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#3b6fd4" stroke-width="2">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                    </svg>
                </div>
                <div class="setting-info">
                    <div class="setting-name">حق مسکن ماهانه</div>
                    <div class="setting-desc">مبلغ کمک هزینه مسکن برای هر ماه کاری</div>
                </div>
                <span class="setting-current">فعلی: <?php echo fm($settings['housing']['setting_value'] ?? 9000000); ?> ریال</span>
            </div>
            <div class="form-group">
                <input type="text" name="housing" value="<?php echo fm($settings['housing']['setting_value'] ?? 9000000); ?>" oninput="formatNumberInput(this)">
            </div>
            
            <div class="setting-row" style="margin-top:16px;">
                <div class="setting-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#3b6fd4" stroke-width="2">
                        <path d="M18 8h1a4 4 0 0 1 0 8h-1M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"/>
                    </svg>
                </div>
                <div class="setting-info">
                    <div class="setting-name">بن کارگری ماهانه</div>
                    <div class="setting-desc">مبلغ بن کارگری (کمک هزینه اقلام مصرفی) برای هر ماه</div>
                </div>
                <span class="setting-current">فعلی: <?php echo fm($settings['food']['setting_value'] ?? 11000000); ?> ریال</span>
            </div>
            <div class="form-group">
                <input type="text" name="food" value="<?php echo fm($settings['food']['setting_value'] ?? 11000000); ?>" oninput="formatNumberInput(this)">
            </div>
            
            <div class="setting-row" style="margin-top:16px;">
                <div class="setting-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#3b6fd4" stroke-width="2">
                        <circle cx="12" cy="8" r="4"/><path d="M8 14c-4 0-6 2-6 4h20c0-2-2-4-6-4"/>
                    </svg>
                </div>
                <div class="setting-info">
                    <div class="setting-name">حق اولاد (هر فرزند)</div>
                    <div class="setting-desc">مبلغ حق اولاد به ازای هر فرزند در ماه</div>
                </div>
                <span class="setting-current">فعلی: <?php echo fm($settings['child_per']['setting_value'] ?? 2100000); ?> ریال</span>
            </div>
            <div class="form-group">
                <input type="text" name="child_per" value="<?php echo fm($settings['child_per']['setting_value'] ?? 2100000); ?>" oninput="formatNumberInput(this)">
            </div>
            
            <hr style="border:0; border-top:1px solid #e0e3e8; margin:20px 0;">
            
            <h3 style="font-size:0.85rem; color:#3b6fd4; margin-bottom:14px;">درصدهای کسورات و مالیات</h3>
            
            <div class="setting-row">
                <div class="setting-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2">
                        <path d="M22 12h-4l-3 9L9 3l-3 9H2"/>
                    </svg>
                </div>
                <div class="setting-info">
                    <div class="setting-name">درصد بیمه تأمین اجتماعی</div>
                    <div class="setting-desc">درصد کسر بیمه از حقوق (سهم کارگر)</div>
                </div>
                <span class="setting-current" style="background:#ef4444;">فعلی: <?php echo ($settings['insurance_percent']['setting_value'] ?? 7); ?>٪</span>
            </div>
            <div class="form-group">
                <input type="number" name="insurance_percent" value="<?php echo ($settings['insurance_percent']['setting_value'] ?? 7); ?>" step="0.1" min="0" max="100">
                <div class="hint">درصد بیمه سهم کارگر (معمولاً ۷٪)</div>
            </div>
            
            <div class="setting-row" style="margin-top:16px;">
                <div class="setting-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2">
                        <line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                    </svg>
                </div>
                <div class="setting-info">
                    <div class="setting-name">معافیت مالیاتی</div>
                    <div class="setting-desc">سقف معافیت مالیات حقوق (سالانه)</div>
                </div>
                <span class="setting-current" style="background:#ef4444;">فعلی: <?php echo fm($settings['tax_exemption']['setting_value'] ?? 120000000); ?> ریال</span>
            </div>
            <div class="form-group">
                <input type="text" name="tax_exemption" value="<?php echo fm($settings['tax_exemption']['setting_value'] ?? 120000000); ?>" oninput="formatNumberInput(this)">
                <div class="hint">مبلغ معافیت از مالیات (سال ۱۴۰۴: ۱۲۰,۰۰۰,۰۰۰ ریال ماهانه)</div>
            </div>
            
            <div class="setting-row" style="margin-top:16px;">
                <div class="setting-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/>
                    </svg>
                </div>
                <div class="setting-info">
                    <div class="setting-name">درصد مالیات حقوق</div>
                    <div class="setting-desc">درصد مالیات بر مازاد معافیت</div>
                </div>
                <span class="setting-current" style="background:#ef4444;">فعلی: <?php echo ($settings['tax_percent']['setting_value'] ?? 10); ?>٪</span>
            </div>
            <div class="form-group">
                <input type="number" name="tax_percent" value="<?php echo ($settings['tax_percent']['setting_value'] ?? 10); ?>" step="0.1" min="0" max="100">
                <div class="hint">درصد مالیات (معمولاً ۱۰٪)</div>
            </div>
            
            
            <hr style="border:0; border-top:1px solid #e0e3e8; margin:20px 0;">

<h3 style="font-size:0.85rem; color:#f59e0b; margin-bottom:14px;">ضریب اضافه‌کاری</h3>

<div class="setting-row">
    <div class="setting-icon">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2">
            <circle cx="12" cy="12" r="10"/>
            <polyline points="12 6 12 12 16 14"/>
        </svg>
    </div>
    <div class="setting-info">
        <div class="setting-name">ضریب اضافه‌کاری</div>
        <div class="setting-desc">ضریب محاسبه اضافه‌کاری (طبق قانون کار: ۱.۴ برابر مزد ساعتی)</div>
    </div>
    <span class="setting-current" style="background:#f59e0b;">فعلی: <?php echo ($settings['overtime_multiplier']['setting_value'] ?? '1.4'); ?> برابر</span>
</div>
<div class="form-group">
    <input type="number" name="overtime_multiplier" value="<?php echo ($settings['overtime_multiplier']['setting_value'] ?? '1.4'); ?>" step="0.1" min="1" max="3">
    <div class="hint">معمولاً ۱.۴ برابر (۴۰٪ اضافه بر مزد عادی). حداقل: ۱، حداکثر: ۳</div>
</div>

<div style="background:#fffbea; border:1px solid #fde68a; border-radius:8px; padding:12px; margin:16px 0; font-size:0.7rem; color:#92400e;">
    <strong>توجه:</strong> تغییر این مقادیر بلافاصله بر تمام محاسبات فیش‌های جدید اعمال می‌شود.
    فیش‌های ذخیره شده قبلی تغییر نمی‌کنند.
</div>

<button type="submit" name="save_settings" class="btn btn-primary" style="width:100%; padding:14px; font-size:0.85rem;">
    ذخیره تنظیمات و اعمال به تمام محاسبات
</button>
        </form>
    </div>
    
    <!-- نمایش خلاصه محاسبات -->
    <div class="card">
        <h3>خلاصه محاسبات با مقادیر فعلی</h3>
        <?php 
        $sample_base = 100000000; // حقوق نمونه ۱۰ میلیون تومان
        $sample_children = 2;
        $sample_gross = $sample_base + ($settings['housing']['setting_value'] ?? 9000000) + ($settings['food']['setting_value'] ?? 11000000) + ($sample_children * ($settings['child_per']['setting_value'] ?? 2100000));
        $sample_insurance = intval(round($sample_gross * ($settings['insurance_percent']['setting_value'] ?? 7) / 100));
        $sample_taxable = $sample_gross - $sample_insurance - ($settings['tax_exemption']['setting_value'] ?? 120000000);
        $sample_tax = $sample_taxable > 0 ? intval(round($sample_taxable * ($settings['tax_percent']['setting_value'] ?? 10) / 100)) : 0;
        $sample_net = $sample_gross - $sample_insurance - $sample_tax;
        ?>
        
        <div style="font-size:0.72rem; line-height:2.2;">
            <div><strong>حقوق پایه نمونه:</strong> <?php echo fm($sample_base); ?> ریال</div>
            <div><strong>فرزندان:</strong> <?php echo $sample_children; ?> نفر</div>
            <div style="color:#3b6fd4;">+ حق مسکن: <?php echo fm($settings['housing']['setting_value'] ?? 9000000); ?> ریال</div>
            <div style="color:#3b6fd4;">+ بن کارگری: <?php echo fm($settings['food']['setting_value'] ?? 11000000); ?> ریال</div>
            <div style="color:#3b6fd4;">+ حق اولاد: <?php echo fm($sample_children * ($settings['child_per']['setting_value'] ?? 2100000)); ?> ریال</div>
            <div style="font-weight:700; border-top:1px solid #e0e3e8; padding-top:4px;">جمع: <?php echo fm($sample_gross); ?> ریال</div>
            <div style="color:#ef4444;">- بیمه (<?php echo ($settings['insurance_percent']['setting_value'] ?? 7); ?>٪): <?php echo fm($sample_insurance); ?> ریال</div>
            <div style="color:#ef4444;">- مالیات: <?php echo fm($sample_tax); ?> ریال</div>
            <div style="font-weight:800; font-size:0.85rem; color:#10b981; border-top:1px solid #e0e3e8; padding-top:4px;">خالص: <?php echo fm($sample_net); ?> ریال</div>
        </div>
    </div>
</div>

<script>
function formatNumberInput(input) {
    let value = input.value.replace(/,/g, '');
    value = value.replace(/[^0-9]/g, '');
    if (value) {
        input.value = Number(value).toLocaleString('en-US');
    } else {
        input.value = '';
    }
}
</script>
</body>
</html>