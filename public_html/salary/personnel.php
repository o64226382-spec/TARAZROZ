<?php
define('SECURE_ACCESS', true);
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireLogin();
require_once '../includes/jdf.php';

$personnel_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($personnel_id == 0) {
    header('Location: index.php');
    exit;
}

// دریافت اطلاعات پرسنل
$person_query = mysqli_query($conn, "SELECT * FROM salary_personnel WHERE id = $personnel_id AND status = 'active'");

if (mysqli_num_rows($person_query) == 0) {
    die('<div style="text-align:center;padding:60px;font-family:Vazirmatn;">پرسنل یافت نشد. <a href="index.php">بازگشت</a></div>');
}

$person = mysqli_fetch_assoc($person_query);
$msg = '';
$error = '';
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'info';

// ذخیره تغییرات
if (isset($_POST['update_personnel'])) {
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
    
    if (empty($fname) || empty($lname)) {
        $error = 'نام و نام خانوادگی الزامی است';
    } elseif ($bsalary < 0) {
        $error = 'حقوق پایه نمی‌تواند منفی باشد';
    } else {
        mysqli_query($conn, "
            UPDATE salary_personnel 
            SET first_name = '$fname', last_name = '$lname', position = '$pos', department = '$dept', 
                national_code = '$ncode', personnel_code = '$pcode', base_salary = $bsalary, 
                children_count = $children, insurance_number = '$insnum', hire_date = '$hdate'
            WHERE id = $personnel_id
        ");
        
        if (!mysqli_error($conn)) {
            $msg = 'اطلاعات با موفقیت بروزرسانی شد';
            $person_query = mysqli_query($conn, "SELECT * FROM salary_personnel WHERE id = $personnel_id");
            $person = mysqli_fetch_assoc($person_query);
            $active_tab = 'info';
        } else {
            $error = 'خطا در بروزرسانی اطلاعات: ' . mysqli_error($conn);
        }
    }
}

// دریافت لیست فیش‌ها
$months = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
$current_year = jdate('Y');

$slips_result = mysqli_query($conn, "
    SELECT * FROM salary_archive 
    WHERE personnel_id = $personnel_id 
    ORDER BY year DESC, month DESC 
    LIMIT 24
");

// آمار
$stats_query = mysqli_query($conn, "
    SELECT 
        COUNT(*) as total_slips,
        COALESCE(SUM(net_salary), 0) as total_paid,
        COALESCE(AVG(net_salary), 0) as avg_salary,
        MAX(year) as last_year,
        MAX(month) as last_month
    FROM salary_archive 
    WHERE personnel_id = $personnel_id
");
$stats = mysqli_fetch_assoc($stats_query);

function fm($n) { 
    return number_format($n); 
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo ($person['first_name'] ?? '') . ' ' . ($person['last_name'] ?? ''); ?> - پروفایل پرسنلی</title>
    <link href="../assets/fonts/fonts.css" rel="stylesheet">
    <style>
        :root {
            --bg: #f5f6f8;
            --surface: #ffffff;
            --border: #e0e3e8;
            --text: #1a1f2e;
            --text2: #555f6e;
            --accent: #3b6fd4;
            --danger: #ef4444;
            --success: #10b981;
            --radius: 16px;
        }
        
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Vazirmatn', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            padding: 16px;
            line-height: 1.6;
        }
        .container { max-width: 1000px; margin: 0 auto; }
        
        .header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 14px 20px; background: var(--surface); border: 1px solid var(--border);
            border-radius: var(--radius); margin-bottom: 16px;
            flex-wrap: wrap; gap: 10px;
        }
        .header h2 { font-size: 0.95rem; font-weight: 700; display: flex; align-items: center; gap: 8px; }
        
        .btn {
            padding: 8px 16px; border-radius: 8px; border: 1px solid var(--border);
            background: var(--surface); color: var(--text); text-decoration: none;
            font-family: 'Vazirmatn'; font-size: 0.75rem; cursor: pointer;
            transition: all 0.2s; display: inline-flex; align-items: center; gap: 6px;
            font-weight: 500; white-space: nowrap;
        }
        .btn:hover { border-color: var(--accent); color: var(--accent); }
        .btn-primary { background: var(--accent); color: #fff; border: none; font-weight: 600; }
        .btn-primary:hover { opacity: 0.92; color: #fff; }
        .btn-danger { color: var(--danger); border-color: var(--danger); }
        .btn-danger:hover { background: var(--danger); color: #fff; }
        
        .btn svg { width: 16px; height: 16px; flex-shrink: 0; }
        
        .toast {
            padding: 10px 14px; border-radius: 10px; margin-bottom: 12px;
            font-weight: 600; font-size: 0.78rem;
        }
        .toast-success { background: var(--success); color: #fff; }
        .toast-error { background: var(--danger); color: #fff; }
        
        /* پروفایل هدر */
        .profile-header {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: var(--radius); padding: 24px; margin-bottom: 16px;
        }
        .profile-top {
            display: flex; gap: 20px; align-items: center; margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .avatar-lg {
            width: 72px; height: 72px; border-radius: 50%;
            background: linear-gradient(135deg, rgba(59,111,212,0.12), rgba(16,185,129,0.08));
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 1.6rem; color: var(--accent);
            border: 3px solid var(--border); flex-shrink: 0;
        }
        .profile-info { flex: 1; min-width: 200px; }
        .profile-name { font-size: 1.1rem; font-weight: 700; margin-bottom: 4px; }
        .profile-meta { font-size: 0.75rem; color: var(--text2); display: flex; gap: 16px; flex-wrap: wrap; }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 10px;
        }
        .stat-card {
            background: var(--bg); padding: 12px 14px; border-radius: 10px;
            text-align: center; border: 1px solid var(--border);
        }
        .stat-value { font-size: 1rem; font-weight: 700; color: var(--accent); }
        .stat-label { font-size: 0.68rem; color: var(--text2); margin-top: 2px; }
        
        /* تب‌ها */
        .tabs {
            display: flex; gap: 0; margin-bottom: 16px;
            background: var(--surface); border-radius: var(--radius);
            border: 1px solid var(--border); overflow: hidden;
        }
        .tab {
            flex: 1; padding: 12px; text-align: center; cursor: pointer;
            font-family: 'Vazirmatn'; font-size: 0.78rem; font-weight: 600;
            border: none; background: transparent; color: var(--text2);
            transition: all 0.2s; border-bottom: 3px solid transparent;
        }
        .tab.active {
            color: var(--accent); background: rgba(59,111,212,0.04);
            border-bottom-color: var(--accent);
        }
        .tab:hover { color: var(--accent); background: rgba(59,111,212,0.02); }
        
        /* کارت‌ها */
        .card {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: var(--radius); padding: 24px; margin-bottom: 16px;
        }
        .card h3 {
            font-size: 0.85rem; margin-bottom: 16px; padding-bottom: 10px;
            border-bottom: 1px solid var(--border); color: var(--accent);
            display: flex; align-items: center; gap: 8px;
        }
        
        /* فرم */
        .form-group { margin-bottom: 14px; }
        .form-group label {
            display: block; font-size: 0.72rem; color: var(--text2);
            margin-bottom: 5px; font-weight: 600;
        }
        .form-group input, .form-group select {
            width: 100%; padding: 10px 12px; border: 1px solid var(--border);
            border-radius: 8px; background: var(--bg); color: var(--text);
            font-family: 'Vazirmatn'; font-size: 0.8rem; transition: border-color 0.2s;
        }
        .form-group input:focus, .form-group select:focus {
            border-color: var(--accent); outline: none;
        }
        .row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .row3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; }
        
        .form-actions { display: flex; gap: 10px; margin-top: 20px; }
        .form-actions button { flex: 1; padding: 12px; font-size: 0.82rem; }
        
        /* جدول فیش‌ها */
        .table-wrapper {
            overflow-x: auto; border-radius: 10px;
            border: 1px solid var(--border); margin-top: 12px;
        }
        table {
            width: 100%; border-collapse: collapse; font-size: 0.73rem;
        }
        th {
            text-align: right; padding: 12px 10px; background: var(--bg);
            color: var(--text2); font-weight: 700; font-size: 0.7rem;
            border-bottom: 2px solid var(--border); white-space: nowrap;
        }
        td {
            padding: 11px 10px; border-bottom: 1px solid var(--border);
            transition: background 0.15s;
        }
        tbody tr:hover td { background: #f8fafc; }
        tbody tr:last-child td { border-bottom: none; }
        
        .amount { text-align: left; font-weight: 600; direction: ltr; }
        .net { color: var(--success); font-weight: 700; }
        .deduction { color: var(--danger); }
        
        .action-btn {
            padding: 5px 10px; border-radius: 6px; border: 1px solid var(--border);
            background: var(--surface); color: var(--accent); cursor: pointer;
            font-family: 'Vazirmatn'; font-size: 0.67rem; transition: all 0.2s;
            text-decoration: none; display: inline-flex; align-items: center; gap: 4px;
        }
        .action-btn:hover { background: var(--accent); color: #fff; border-color: var(--accent); }
        
        .empty-state { text-align: center; padding: 40px; color: var(--text2); }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 16px;
        }
        .info-item {
            padding: 12px; background: var(--bg); border-radius: 8px;
        }
        .info-label { font-size: 0.7rem; color: var(--text2); margin-bottom: 4px; }
        .info-value { font-weight: 600; }
        
        @media (max-width: 768px) {
            .row2, .row3 { grid-template-columns: 1fr; }
            .header { flex-direction: column; align-items: flex-start; }
            .profile-top { flex-direction: column; text-align: center; }
            .profile-meta { justify-content: center; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .tabs { flex-direction: column; }
        }
    </style>
</head>
<body>

<div class="container">
    <!-- Header -->
    <div class="header">
        <h2>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                <circle cx="12" cy="7" r="4"/>
            </svg>
            پروفایل پرسنلی
        </h2>
        <div style="display:flex; gap:8px;">
            <a href="slip.php?personnel_id=<?php echo $personnel_id; ?>" class="btn btn-primary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                صدور فیش
            </a>
            <a href="index.php" class="btn">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M15 18l-6-6 6-6"/>
                </svg>
                بازگشت
            </a>
        </div>
    </div>
    
    <?php if ($msg): ?>
    <div class="toast toast-success"><?php echo $msg; ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
    <div class="toast toast-error"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <!-- پروفایل هدر -->
    <div class="profile-header">
        <div class="profile-top">
            <div class="avatar-lg">
                <?php echo mb_substr($person['first_name'] ?? '؟', 0, 1) . mb_substr($person['last_name'] ?? '؟', 0, 1); ?>
            </div>
            <div class="profile-info">
                <div class="profile-name"><?php echo ($person['first_name'] ?? '') . ' ' . ($person['last_name'] ?? ''); ?></div>
                <div class="profile-meta">
                    <?php if (!empty($person['position'])): ?>
                    <span><?php echo $person['position']; ?></span>
                    <?php endif; ?>
                    <?php if (!empty($person['department'])): ?>
                    <span><?php echo $person['department']; ?></span>
                    <?php endif; ?>
                    <?php if (!empty($person['personnel_code'])): ?>
                    <span>کد: <?php echo $person['personnel_code']; ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo fm($person['base_salary'] ?? 0); ?></div>
                <div class="stat-label">حقوق پایه (ریال)</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['total_slips']; ?></div>
                <div class="stat-label">تعداد فیش</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo fm($stats['total_paid']); ?></div>
                <div class="stat-label">مجموع پرداختی (ریال)</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo fm(round($stats['avg_salary'])); ?></div>
                <div class="stat-label">میانگین خالص (ریال)</div>
            </div>
            <?php if ($stats['last_month']): ?>
            <div class="stat-card">
                <div class="stat-value"><?php echo $months[$stats['last_month']-1] . ' ' . $stats['last_year']; ?></div>
                <div class="stat-label">آخرین فیش</div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- تب‌ها -->
    <div class="tabs">
        <button class="tab <?php echo $active_tab == 'info' ? 'active' : ''; ?>" onclick="switchTab('info')">
            اطلاعات پرسنلی
        </button>
        <button class="tab <?php echo $active_tab == 'edit' ? 'active' : ''; ?>" onclick="switchTab('edit')">
            ویرایش اطلاعات
        </button>
        <button class="tab <?php echo $active_tab == 'slips' ? 'active' : ''; ?>" onclick="switchTab('slips')">
            فیش‌های حقوقی
        </button>
    </div>
    
    <!-- تب اطلاعات پرسنلی -->
    <div id="tab-info" class="card" style="display: <?php echo $active_tab == 'info' ? 'block' : 'none'; ?>;">
        <h3>اطلاعات پرسنلی</h3>
        
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">نام و نام خانوادگی</div>
                <div class="info-value"><?php echo ($person['first_name'] ?? '') . ' ' . ($person['last_name'] ?? ''); ?></div>
            </div>
            
            <?php if (!empty($person['national_code'])): ?>
            <div class="info-item">
                <div class="info-label">کد ملی</div>
                <div class="info-value"><?php echo $person['national_code']; ?></div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($person['personnel_code'])): ?>
            <div class="info-item">
                <div class="info-label">کد پرسنلی</div>
                <div class="info-value"><?php echo $person['personnel_code']; ?></div>
            </div>
            <?php endif; ?>
            
            <div class="info-item">
                <div class="info-label">سمت</div>
                <div class="info-value"><?php echo !empty($person['position']) ? $person['position'] : 'تعریف نشده'; ?></div>
            </div>
            
            <div class="info-item">
                <div class="info-label">واحد</div>
                <div class="info-value"><?php echo !empty($person['department']) ? $person['department'] : 'تعریف نشده'; ?></div>
            </div>
            
            <div class="info-item">
                <div class="info-label">حقوق پایه</div>
                <div class="info-value"><?php echo fm($person['base_salary'] ?? 0); ?> ریال</div>
            </div>
            
            <div class="info-item">
                <div class="info-label">تعداد اولاد</div>
                <div class="info-value"><?php echo $person['children_count'] ?? 0; ?> نفر</div>
            </div>
            
            <?php if (!empty($person['insurance_number'])): ?>
            <div class="info-item">
                <div class="info-label">شماره بیمه</div>
                <div class="info-value"><?php echo $person['insurance_number']; ?></div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($person['hire_date'])): ?>
            <div class="info-item">
                <div class="info-label">تاریخ استخدام</div>
                <div class="info-value"><?php echo $person['hire_date']; ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- تب ویرایش اطلاعات -->
    <div id="tab-edit" class="card" style="display: <?php echo $active_tab == 'edit' ? 'block' : 'none'; ?>;">
        <h3>ویرایش اطلاعات پرسنل</h3>
        
        <form method="POST" id="editForm">
            <div class="row2">
                <div class="form-group">
                    <label>نام *</label>
                    <input type="text" name="first_name" value="<?php echo htmlspecialchars($person['first_name'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label>نام خانوادگی *</label>
                    <input type="text" name="last_name" value="<?php echo htmlspecialchars($person['last_name'] ?? ''); ?>" required>
                </div>
            </div>
            
            <div class="row2">
                <div class="form-group">
                    <label>کد ملی</label>
                    <input type="text" name="national_code" maxlength="10" value="<?php echo htmlspecialchars($person['national_code'] ?? ''); ?>" placeholder="۱۰ رقم">
                </div>
                <div class="form-group">
                    <label>کد پرسنلی</label>
                    <input type="text" name="personnel_code" value="<?php echo htmlspecialchars($person['personnel_code'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="row2">
                <div class="form-group">
                    <label>سمت</label>
                    <input type="text" name="position" value="<?php echo htmlspecialchars($person['position'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>واحد / دپارتمان</label>
                    <input type="text" name="department" value="<?php echo htmlspecialchars($person['department'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="row3">
                <div class="form-group">
                    <label>حقوق پایه (ریال)</label>
                    <input type="text" name="base_salary" id="base_salary" value="<?php echo fm($person['base_salary'] ?? 0); ?>" oninput="formatNumberInput(this)">
                </div>
                <div class="form-group">
                    <label>تعداد اولاد</label>
                    <input type="number" name="children_count" value="<?php echo $person['children_count'] ?? 0; ?>" min="0" max="20">
                </div>
                <div class="form-group">
                    <label>شماره بیمه</label>
                    <input type="text" name="insurance_number" value="<?php echo htmlspecialchars($person['insurance_number'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label>تاریخ استخدام</label>
                <input type="text" name="hire_date" value="<?php echo htmlspecialchars($person['hire_date'] ?? ''); ?>" placeholder="مثال: ۱۴۰۲/۰۱/۰۱">
            </div>
            
            <div class="form-actions">
                <button type="submit" name="update_personnel" class="btn btn-primary">ذخیره تغییرات</button>
                <button type="button" class="btn btn-danger" onclick="if(confirm('آیا از غیرفعال کردن این پرسنل اطمینان دارید؟')) window.location.href='index.php?delete=<?php echo $personnel_id; ?>'">غیرفعال کردن پرسنل</button>
            </div>
        </form>
    </div>
    
    <!-- تب فیش‌های حقوقی -->
    <div id="tab-slips" class="card" style="display: <?php echo $active_tab == 'slips' ? 'block' : 'none'; ?>;">
        <h3>فیش‌های حقوقی</h3>
        
        <?php if (mysqli_num_rows($slips_result) > 0): ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>دوره</th>
                        <th>روز کارکرد</th>
                        <th>ناخالص (ریال)</th>
                        <th>بیمه (ریال)</th>
                        <th>مالیات (ریال)</th>
                        <th>کسورات (ریال)</th>
                        <th>خالص (ریال)</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($slip = mysqli_fetch_assoc($slips_result)): ?>
                    <tr>
                        <td><strong><?php echo $months[$slip['month']-1] . ' ' . $slip['year']; ?></strong></td>
                        <td><?php echo $slip['working_days']; ?></td>
                        <td class="amount"><?php echo fm($slip['total_gross']); ?></td>
                        <td class="amount deduction"><?php echo fm($slip['insurance_deduction']); ?></td>
                        <td class="amount deduction"><?php echo fm($slip['tax_deduction']); ?></td>
                        <td class="amount deduction"><?php echo fm($slip['total_deductions']); ?></td>
                        <td class="amount net"><?php echo fm($slip['net_salary']); ?></td>
                        <td>
                            <button class="action-btn" onclick="window.open('print_slip.php?id=<?php echo $slip['id']; ?>','_blank')">
                                چاپ
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <p>هنوز فیش حقوقی برای این پرسنل ثبت نشده است.</p>
            <a href="slip.php?personnel_id=<?php echo $personnel_id; ?>" class="btn btn-primary" style="margin-top:12px;">صدور اولین فیش</a>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function switchTab(tabName) {
    document.getElementById('tab-info').style.display = 'none';
    document.getElementById('tab-edit').style.display = 'none';
    document.getElementById('tab-slips').style.display = 'none';
    
    document.getElementById('tab-' + tabName).style.display = 'block';
    
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    event.target.closest('.tab').classList.add('active');
    
    const url = new URL(window.location);
    url.searchParams.set('tab', tabName);
    window.history.pushState({}, '', url);
}

function formatNumberInput(input) {
    let value = input.value.replace(/,/g, '');
    value = value.replace(/[^0-9]/g, '');
    if (value) {
        input.value = Number(value).toLocaleString('en-US');
    } else {
        input.value = '';
    }
}

document.getElementById('editForm').addEventListener('submit', function() {
    const salaryInput = document.getElementById('base_salary');
    if (salaryInput) {
        salaryInput.value = salaryInput.value.replace(/,/g, '');
    }
});
</script>

</body>
</html>