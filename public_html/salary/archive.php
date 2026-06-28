<?php
define('SECURE_ACCESS', true);
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireLogin();
require_once '../includes/jdf.php';

// ===== خطاها رو فعال کن =====
error_reporting(E_ALL);
ini_set('display_errors', 1);

$user_id = intval($_SESSION['user_id']);
$perm_query = "SELECT permissions FROM users WHERE id = $user_id";
$perm_result = mysqli_query($conn, $perm_query);
$permissions = '';
if ($perm_result && mysqli_num_rows($perm_result) > 0) {
    $permissions = mysqli_fetch_assoc($perm_result)['permissions'];
}
if (strpos($permissions, 'salary') === false && $_SESSION['role'] !== 'admin') {
    die('<div style="text-align:center;padding:60px;font-family:Vazirmatn;">دسترسی شما محدود است. با ادمین تماس بگیرید.</div>');
}

$months = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
$current_year = jdate('Y');

// فیلترها
$filter_personnel = isset($_GET['personnel_id']) ? intval($_GET['personnel_id']) : 0;
$filter_month = isset($_GET['month']) ? intval($_GET['month']) : 0;
$filter_year = isset($_GET['year']) ? intval($_GET['year']) : 0;

// ===== کوئری بایگانی =====
$where = [];
$params = [];
$types = "";

if ($filter_personnel) {
    $where[] = "sa.personnel_id = ?";
    $params[] = $filter_personnel;
    $types .= "i";
}
if ($filter_month) {
    $where[] = "sa.month = ?";
    $params[] = $filter_month;
    $types .= "i";
}
if ($filter_year) {
    $where[] = "sa.year = ?";
    $params[] = $filter_year;
    $types .= "i";
}

$where_clause = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "
    SELECT sa.*, sp.first_name, sp.last_name, sp.position 
    FROM salary_archive sa 
    JOIN salary_personnel sp ON sa.personnel_id = sp.id 
    $where_clause 
    ORDER BY sa.year DESC, sa.month DESC 
    LIMIT 50
";

$stmt = mysqli_prepare($conn, $sql);

if (count($params) > 0) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$archive_query = mysqli_stmt_get_result($stmt);

// ===== تعداد رکوردها =====
$row_count = mysqli_num_rows($archive_query);

// کارنامه سالانه
$yearly_personnel = isset($_GET['yearly_pid']) ? intval($_GET['yearly_pid']) : 0;
$yearly_year = isset($_GET['yearly_year']) ? intval($_GET['yearly_year']) : $current_year;

$yearly_data = [];
$worked_months = 0;
$person_info = null;

if ($yearly_personnel) {
    $stmt_yr = mysqli_prepare($conn, "
        SELECT * FROM salary_archive 
        WHERE personnel_id = ? AND year = ? 
        ORDER BY month
    ");
    mysqli_stmt_bind_param($stmt_yr, "ii", $yearly_personnel, $yearly_year);
    mysqli_stmt_execute($stmt_yr);
    $yr = mysqli_stmt_get_result($stmt_yr);
    
    while ($row = mysqli_fetch_assoc($yr)) {
        $yearly_data[$row['month']] = $row;
    }
    $worked_months = count($yearly_data);
    
    $stmt_pi = mysqli_prepare($conn, "SELECT * FROM salary_personnel WHERE id = ?");
    mysqli_stmt_bind_param($stmt_pi, "i", $yearly_personnel);
    mysqli_stmt_execute($stmt_pi);
    $person_info = mysqli_stmt_get_result($stmt_pi);
    $person_info = mysqli_fetch_assoc($person_info);
}

// لیست پرسنل برای فیلتر
$personnel_list = mysqli_query($conn, "SELECT id, first_name, last_name FROM salary_personnel WHERE status = 'active' ORDER BY last_name");

function fm($n) { return number_format($n); }
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>بایگانی فیش حقوقی</title>
    <link href="../assets/fonts/fonts.css" rel="stylesheet">
    <style>
        :root {
            --bg: #f5f6f8;
            --surface: #ffffff;
            --border: #e0e3e8;
            --text: #1a1f2e;
            --text-secondary: #555f6e;
            --accent: #3b6fd4;
            --success: #10b981;
            --danger: #ef4444;
            --radius: 14px;
            --shadow: 0 2px 12px rgba(0,0,0,0.06);
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
        .container { max-width: 1100px; margin: 0 auto; }
        
        .header {
            display: flex; 
            align-items: center; 
            justify-content: space-between;
            padding: 16px 24px; 
            background: var(--surface); 
            border: 1px solid var(--border);
            border-radius: var(--radius); 
            margin-bottom: 16px; 
            box-shadow: var(--shadow);
            flex-wrap: wrap; 
            gap: 12px;
        }
        .header h2 { 
            font-size: 1rem; 
            font-weight: 700; 
            display: flex; 
            align-items: center; 
            gap: 8px;
        }
        .header-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 8px 16px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: var(--surface);
            color: var(--text);
            text-decoration: none;
            font-family: 'Vazirmatn';
            font-size: 0.75rem;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
            font-weight: 500;
        }
        .btn:hover { 
            border-color: var(--accent); 
            color: var(--accent); 
            text-decoration: none;
        }
        .btn-primary {
            background: var(--accent);
            color: #fff;
            border: none;
            font-weight: 600;
        }
        .btn-primary:hover {
            opacity: 0.92;
            color: #fff;
        }
        .btn-outline {
            border: 1px solid var(--border);
            background: transparent;
        }
        .btn-outline:hover {
            background: var(--bg);
        }
        
        .btn svg {
            width: 16px;
            height: 16px;
            flex-shrink: 0;
        }
        
        .card {
            background: var(--surface); 
            border: 1px solid var(--border);
            border-radius: var(--radius); 
            padding: 24px; 
            margin-bottom: 16px;
            box-shadow: var(--shadow);
        }
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border);
            flex-wrap: wrap;
            gap: 12px;
        }
        .card-header h3 { 
            font-size: 0.9rem; 
            font-weight: 700; 
            color: var(--accent);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .filter-wrapper {
            display: flex; 
            gap: 10px; 
            flex-wrap: wrap; 
            align-items: end;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .filter-group label {
            font-size: 0.68rem;
            color: var(--text-secondary);
            font-weight: 600;
        }
        .filter-group select {
            padding: 9px 12px; 
            border-radius: 8px; 
            border: 1px solid var(--border);
            background: var(--bg); 
            color: var(--text); 
            font-family: 'Vazirmatn';
            font-size: 0.75rem; 
            cursor: pointer;
            min-width: 140px;
            transition: border-color 0.2s;
        }
        .filter-group select:focus {
            border-color: var(--accent);
            outline: none;
            box-shadow: 0 0 0 3px rgba(59,111,212,0.1);
        }
        
        .filter-actions {
            display: flex; 
            gap: 6px; 
            flex-wrap: wrap;
            align-items: end;
        }
        
        .table-wrapper {
            overflow-x: auto;
            margin-top: 16px;
            border-radius: 10px;
            border: 1px solid var(--border);
        }
        
        table {
            width: 100%; 
            border-collapse: collapse; 
            font-size: 0.73rem;
        }
        th {
            text-align: right; 
            padding: 12px 10px; 
            background: #f8f9fb;
            color: var(--text-secondary); 
            font-weight: 700; 
            border-bottom: 2px solid var(--border);
            white-space: nowrap;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        td {
            padding: 11px 10px; 
            border-bottom: 1px solid var(--border);
            transition: background 0.15s;
        }
        tbody tr:hover td { 
            background: #f8fafc;
        }
        tbody tr:last-child td {
            border-bottom: none;
        }
        
        .amount { 
            text-align: left; 
            font-weight: 600; 
            font-variant-numeric: tabular-nums;
            direction: ltr;
        }
        .net { 
            color: var(--success); 
            font-weight: 700; 
        }
        .deduction {
            color: var(--danger);
        }
        
        .action-btn {
            padding: 5px 10px; 
            border-radius: 6px; 
            border: 1px solid var(--border);
            background: var(--surface); 
            color: var(--accent); 
            cursor: pointer;
            font-family: 'Vazirmatn'; 
            font-size: 0.67rem; 
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .action-btn:hover { 
            background: var(--accent); 
            color: #fff; 
            border-color: var(--accent);
        }
        
        .person-name-link {
            color: var(--accent);
            text-decoration: none;
            font-weight: 600;
        }
        .person-name-link:hover {
            text-decoration: underline;
        }
        
        .empty-state { 
            text-align: center; 
            padding: 48px 20px; 
            color: var(--text-secondary); 
        }
        .empty-state svg {
            width: 48px;
            height: 48px;
            margin-bottom: 12px;
            opacity: 0.5;
        }
        
        .no-data {
            text-align: center;
            padding: 30px;
            color: var(--text-secondary);
            font-size: 0.85rem;
        }
        
        @media (max-width: 768px) {
            .header { 
                flex-direction: column; 
                align-items: flex-start; 
            }
            .filter-wrapper { 
                flex-direction: column;
            }
            .filter-group select {
                width: 100%;
            }
            .filter-actions { 
                width: 100%;
            }
            .filter-actions .btn {
                flex: 1;
                justify-content: center;
            }
            table { 
                font-size: 0.65rem; 
            }
            th, td { 
                padding: 8px 6px; 
            }
        }
        
        @media print {
            body { background: #fff; }
            .header, .card-header, .filter-wrapper, .filter-actions, .action-btn, .btn { 
                display: none; 
            }
            .card { 
                border: none; 
                box-shadow: none; 
                padding: 0; 
            }
            .table-wrapper {
                border: 1px solid #ddd;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <!-- Header -->
    <div class="header">
        <h2>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="4" width="18" height="16" rx="2"/>
                <line x1="8" y1="2" x2="8" y2="6"/>
                <line x1="16" y1="2" x2="16" y2="6"/>
                <line x1="3" y1="10" x2="21" y2="10"/>
                <path d="M8 14h.01M12 14h.01M16 14h.01"/>
            </svg>
            بایگانی فیش حقوقی
        </h2>
        <div class="header-actions">
            <a href="slip.php" class="btn btn-primary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"/>
                    <line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                صدور فیش جدید
            </a>
            <a href="index.php" class="btn btn-outline">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M15 18l-6-6 6-6"/>
                </svg>
                بازگشت
            </a>
        </div>
    </div>
    
    <!-- فیلتر و جستجو -->
    <div class="card">
        <div class="card-header">
            <h3>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8"/>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                جستجو و فیلتر
            </h3>
        </div>
        
        <div class="filter-wrapper">
            <form method="GET" style="display:flex; gap:10px; flex-wrap:wrap; align-items:end; flex:1;">
                <div class="filter-group">
                    <label>پرسنل</label>
                    <select name="personnel_id">
                        <option value="">همه پرسنل</option>
                        <?php 
                        mysqli_data_seek($personnel_list, 0);
                        while ($p = mysqli_fetch_assoc($personnel_list)): 
                        ?>
                        <option value="<?php echo $p['id']; ?>" <?php echo $filter_personnel == $p['id'] ? 'selected' : ''; ?>>
                            <?php echo $p['first_name'] . ' ' . $p['last_name']; ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>ماه</label>
                    <select name="month">
                        <option value="">همه ماه‌ها</option>
                        <?php foreach ($months as $i => $m): ?>
                        <option value="<?php echo $i+1; ?>" <?php echo $filter_month == $i+1 ? 'selected' : ''; ?>><?php echo $m; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>سال</label>
                    <select name="year">
                        <option value="">همه سال‌ها</option>
                        <?php for ($y = $current_year; $y >= $current_year-5; $y--): ?>
                        <option value="<?php echo $y; ?>" <?php echo $filter_year == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary" style="height:fit-content;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="22 2 11 13 8 8 2 22 16 16 11 13"/>
                    </svg>
                    اعمال فیلتر
                </button>
            </form>
            
            <?php if ($filter_personnel): ?>
            <div class="filter-actions">
                <button type="button" 
                        onclick="window.location.href='?yearly_pid=<?php echo $filter_personnel; ?>&yearly_year=<?php echo $filter_year ?: $current_year; ?>'"
                        class="btn btn-outline">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="20" x2="18" y2="10"/>
                        <line x1="12" y1="20" x2="12" y2="4"/>
                        <line x1="6" y1="20" x2="6" y2="14"/>
                    </svg>
                    کارنامه سالانه
                </button>
                <a href="edit_personnel.php?id=<?php echo $filter_personnel; ?>" class="btn btn-outline">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                    </svg>
                    ویرایش
                </a>
                <a href="slip.php?personnel_id=<?php echo $filter_personnel; ?>" class="btn btn-primary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    فیش جدید
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- ===== نمایش فیش‌ها ===== -->
    <?php 
    // اگه کارنامه سالانه درخواست شده، کارنامه رو نشون بده
    if ($yearly_personnel && $person_info): 
    ?>
    <div class="card">
        <div class="card-header">
            <h3>کارنامه سالانه <?php echo $person_info['first_name'] . ' ' . $person_info['last_name'] . ' - ' . $yearly_year; ?></h3>
        </div>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>ماه</th>
                        <th>ناخالص</th>
                        <th>بیمه</th>
                        <th>مالیات</th>
                        <th>خالص</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $total_net = 0;
                    $total_gross = 0;
                    for ($m = 1; $m <= 12; $m++): 
                        $row = isset($yearly_data[$m]) ? $yearly_data[$m] : null;
                        if ($row) {
                            $total_net += $row['net_salary'];
                            $total_gross += $row['total_gross'];
                        }
                    ?>
                    <tr>
                        <td><strong><?php echo $months[$m-1]; ?></strong></td>
                        <?php if ($row): ?>
                        <td class="amount"><?php echo fm($row['total_gross']); ?></td>
                        <td class="amount deduction"><?php echo fm($row['insurance_deduction']); ?></td>
                        <td class="amount deduction"><?php echo fm($row['tax_deduction']); ?></td>
                        <td class="amount net"><?php echo fm($row['net_salary']); ?></td>
                        <td>
                            <button class="action-btn" onclick="window.open('print_slip.php?id=<?php echo $row['id']; ?>','_blank')">
                                چاپ
                            </button>
                        </td>
                        <?php else: ?>
                        <td colspan="5" style="text-align:center;color:#999;font-size:0.7rem;">
                            ثبت نشده
                            <a href="slip.php?personnel_id=<?php echo $yearly_personnel; ?>&month=<?php echo $m; ?>&year=<?php echo $yearly_year; ?>" style="color:var(--accent);">
                                ثبت
                            </a>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endfor; ?>
                    <tr style="font-weight:700;border-top:2px solid #ddd;">
                        <td><strong>جمع کل</strong></td>
                        <td class="amount"><?php echo fm($total_gross); ?></td>
                        <td></td>
                        <td></td>
                        <td class="amount net"><?php echo fm($total_net); ?></td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <?php 
    else: 
    // ===== جدول اصلی فیش‌ها =====
    ?>
    <div class="card">
        <div class="card-header">
            <h3>فیش‌های حقوقی</h3>
            <span style="font-size:0.7rem;color:var(--text-secondary);">
                <?php echo $row_count; ?> فیش یافت شد
            </span>
        </div>
        
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>پرسنل</th>
                        <th>دوره</th>
                        <th>ناخالص (ریال)</th>
                        <th>بیمه (ریال)</th>
                        <th>مالیات (ریال)</th>
                        <th>کسورات (ریال)</th>
                        <th>خالص پرداختی (ریال)</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($row_count > 0): ?>
                        <?php 
                        // دوباره نتیجه رو fetch کن
                        mysqli_data_seek($archive_query, 0);
                        while ($row = mysqli_fetch_assoc($archive_query)): 
                        ?>
                        <tr>
                            <td>
                                <a href="?personnel_id=<?php echo $row['personnel_id']; ?>" class="person-name-link">
                                    <?php echo $row['first_name'] . ' ' . $row['last_name']; ?>
                                </a>
                            </td>
                            <td><?php echo $months[intval($row['month'])-1] . ' ' . $row['year']; ?></td>
                            <td class="amount"><?php echo number_format($row['total_gross']); ?></td>
                            <td class="amount deduction"><?php echo number_format($row['insurance_deduction']); ?></td>
                            <td class="amount deduction"><?php echo number_format($row['tax_deduction']); ?></td>
                            <td class="amount deduction"><?php echo number_format($row['total_deductions']); ?></td>
                            <td class="amount net"><?php echo number_format($row['net_salary']); ?></td>
                            <td>
                                <button class="action-btn" onclick="window.open('print_slip.php?id=<?php echo $row['id']; ?>','_blank')">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="6 9 6 2 18 2 18 9"/>
                                        <path d="M6 12H4a2 2 0 0 0-2 2v4a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-4a2 2 0 0 0-2-2h-2"/>
                                        <rect x="6" y="14" width="12" height="8"/>
                                    </svg>
                                    چاپ
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="3" y="4" width="18" height="16" rx="2"/>
                                        <line x1="8" y1="2" x2="8" y2="6"/>
                                        <line x1="16" y1="2" x2="16" y2="6"/>
                                        <line x1="3" y1="10" x2="21" y2="10"/>
                                    </svg>
                                    <p style="margin-bottom:12px;">هیچ فیش حقوقی در بایگانی یافت نشد</p>
                                    <a href="slip.php" class="btn btn-primary">صدور اولین فیش</a>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
    
</div>
</body>
</html>