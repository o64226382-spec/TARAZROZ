<?php
define('SECURE_ACCESS', true);
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireAdmin();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ========== دریافت شعب ==========
$branches = [];
$br_res = mysqli_query($conn, "SELECT id, branch_name FROM users WHERE role = 'branch' ORDER BY branch_name");
while ($b = mysqli_fetch_assoc($br_res)) $branches[] = $b;

// ========== دریافت آیتم‌های درآمد ==========
$income_items_daily = [];
$items_res = mysqli_query($conn, "SELECT id, name FROM income_daily_items ORDER BY name");
while ($item = mysqli_fetch_assoc($items_res)) $income_items_daily[] = $item;

$income_items_monthly = [];
$items_res = mysqli_query($conn, "SELECT id, name FROM income_monthly_items ORDER BY name");
while ($item = mysqli_fetch_assoc($items_res)) $income_items_monthly[] = $item;

// ========== فیلترها ==========
$selected_branch = isset($_GET['branch_id']) ? intval($_GET['branch_id']) : 0;
$date_from = $_GET['from'] ?? date('Y/m/d', strtotime('-60 days'));
$date_to = $_GET['to'] ?? date('Y/m/d');
$date_from_db = str_replace('/', '-', $date_from);
$date_to_db = str_replace('/', '-', $date_to);
$chart_type = $_GET['chart_type'] ?? 'line';
$view_mode = $_GET['view'] ?? 'dashboard';

$branch_cond = $selected_branch > 0 ? "AND dr.branch_id = $selected_branch" : "";
$branch_cond_monthly = $selected_branch > 0 ? "AND mr.branch_id = $selected_branch" : "";

// ⭐ خروجی CSV پیشرفته
if (isset($_GET['download']) && $_GET['download'] == '1') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="income_report_' . date('Y-m-d_His') . '.csv"');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    fputcsv($output, ['گزارش درآمد روزانه']);
    fputcsv($output, ['شعبه', 'تاریخ', 'نوع آیتم', 'مبلغ (ریال)', 'مبلغ (گرم)', 'نرخ طلا']);
    $query = "SELECT u.branch_name, dr.record_date, di.name as item_name, dr.amount_rial, dr.amount_gram, dr.gold_rate
              FROM income_daily_records dr 
              JOIN users u ON dr.branch_id = u.id 
              JOIN income_daily_items di ON dr.item_id = di.id 
              WHERE dr.record_date BETWEEN '$date_from_db' AND '$date_to_db' $branch_cond 
              ORDER BY u.branch_name, dr.record_date ASC";
    $res = mysqli_query($conn, $query);
    while ($row = mysqli_fetch_assoc($res)) {
        fputcsv($output, [$row['branch_name'], $row['record_date'], $row['item_name'], $row['amount_rial'], $row['amount_gram'], $row['gold_rate']]);
    }
    
    fputcsv($output, ['']);
    fputcsv($output, ['گزارش درآمد ماهانه']);
    fputcsv($output, ['شعبه', 'سال', 'ماه', 'نوع آیتم', 'مبلغ (گرم)']);
    $query = "SELECT u.branch_name, mr.record_year, mr.record_month, mi.name as item_name, mr.amount_gram
              FROM income_monthly_records mr 
              JOIN users u ON mr.branch_id = u.id 
              JOIN income_monthly_items mi ON mr.item_id = mi.id 
              WHERE CONCAT(mr.record_year, '-', LPAD(mr.record_month, 2, '0'), '-01') BETWEEN '$date_from_db' AND '$date_to_db' 
              $branch_cond_monthly 
              ORDER BY u.branch_name, mr.record_year, mr.record_month ASC";
    $res = mysqli_query($conn, $query);
    while ($row = mysqli_fetch_assoc($res)) {
        fputcsv($output, [$row['branch_name'], $row['record_year'], $row['record_month'], $row['item_name'], $row['amount_gram']]);
    }
    
    fclose($output);
    exit;
}

// ⭐ داده‌های روزانه
$daily_data = [];
$query = "SELECT dr.id, dr.branch_id, u.branch_name, dr.record_date, di.name as item_name, dr.amount_rial, dr.amount_gram, dr.gold_rate
          FROM income_daily_records dr 
          JOIN users u ON dr.branch_id = u.id 
          JOIN income_daily_items di ON dr.item_id = di.id 
          WHERE dr.record_date BETWEEN '$date_from_db' AND '$date_to_db' $branch_cond 
          ORDER BY u.branch_name, dr.record_date ASC";
$res = mysqli_query($conn, $query);
while ($row = mysqli_fetch_assoc($res)) $daily_data[] = $row;

// ⭐ داده‌های ماهانه
$monthly_data = [];
$query = "SELECT mr.id, mr.branch_id, u.branch_name, mr.record_year, mr.record_month, mi.name as item_name, mr.amount_gram
          FROM income_monthly_records mr 
          JOIN users u ON mr.branch_id = u.id 
          JOIN income_monthly_items mi ON mr.item_id = mi.id 
          WHERE CONCAT(mr.record_year, '-', LPAD(mr.record_month, 2, '0'), '-01') BETWEEN '$date_from_db' AND '$date_to_db' 
          $branch_cond_monthly 
          ORDER BY u.branch_name, mr.record_year, mr.record_month ASC";
$res = mysqli_query($conn, $query);
while ($row = mysqli_fetch_assoc($res)) {
    $row['record_date'] = $row['record_year'] . '/' . str_pad($row['record_month'], 2, '0', STR_PAD_LEFT);
    $monthly_data[] = $row;
}

// ⭐ آمار کلی شعب
$branch_stats = [];
$query = "SELECT u.branch_name, 
          COALESCE(SUM(dr.amount_rial), 0) as total_rial, 
          COALESCE(SUM(dr.amount_gram), 0) as total_daily_gram,
          COUNT(DISTINCT dr.record_date) as active_days
          FROM income_daily_records dr 
          JOIN users u ON dr.branch_id = u.id 
          WHERE dr.record_date BETWEEN '$date_from_db' AND '$date_to_db' $branch_cond 
          GROUP BY u.id, u.branch_name 
          ORDER BY total_rial DESC";
$res = mysqli_query($conn, $query);
while ($r = mysqli_fetch_assoc($res)) $branch_stats[] = $r;

// ⭐ آمار کلی محاسبات
$total_rial_all = array_sum(array_column($branch_stats, 'total_rial'));
$total_gram_all = array_sum(array_column($branch_stats, 'total_daily_gram'));
$avg_daily = count($branch_stats) > 0 ? $total_rial_all / max(array_sum(array_column($branch_stats, 'active_days')), 1) : 0;

// ⭐ نمودار ۱: روند درآمد روزانه به تفکیک شعب (هر شعبه یک خط)
$chart_branches_daily = [];
$query = "SELECT u.branch_name, dr.record_date, COALESCE(SUM(dr.amount_rial), 0) as total 
          FROM income_daily_records dr 
          JOIN users u ON dr.branch_id = u.id 
          WHERE dr.record_date BETWEEN '$date_from_db' AND '$date_to_db' $branch_cond 
          GROUP BY u.branch_name, dr.record_date 
          ORDER BY dr.record_date ASC, u.branch_name ASC";
$res = mysqli_query($conn, $query);
while ($r = mysqli_fetch_assoc($res)) $chart_branches_daily[] = $r;

// ⭐ نمودار ۲: مقایسه شعب (نمودار میله‌ای)
$chart_branches_comparison = [];
$query = "SELECT u.branch_name, COALESCE(SUM(dr.amount_rial), 0) as total 
          FROM income_daily_records dr 
          JOIN users u ON dr.branch_id = u.id 
          WHERE dr.record_date BETWEEN '$date_from_db' AND '$date_to_db' $branch_cond 
          GROUP BY u.branch_name 
          ORDER BY total DESC";
$res = mysqli_query($conn, $query);
while ($r = mysqli_fetch_assoc($res)) $chart_branches_comparison[] = $r;

// ⭐ نمودار ۳: مقایسه آیتم‌های روزانه
$chart_items_daily = [];
$query = "SELECT di.name, COALESCE(SUM(dr.amount_rial), 0) as total 
          FROM income_daily_records dr 
          JOIN income_daily_items di ON dr.item_id = di.id 
          WHERE dr.record_date BETWEEN '$date_from_db' AND '$date_to_db' $branch_cond 
          GROUP BY di.name 
          ORDER BY total DESC";
$res = mysqli_query($conn, $query);
while ($r = mysqli_fetch_assoc($res)) $chart_items_daily[] = $r;

// ⭐ نمودار ۴: روند ماهانه به تفکیک شعب
$chart_monthly_branches = [];
$query = "SELECT u.branch_name, CONCAT(mr.record_year, '/', LPAD(mr.record_month, 2, '0')) as month_label, 
          COALESCE(SUM(mr.amount_gram), 0) as total 
          FROM income_monthly_records mr 
          JOIN users u ON mr.branch_id = u.id 
          WHERE CONCAT(mr.record_year, '-', LPAD(mr.record_month, 2, '0'), '-01') BETWEEN '$date_from_db' AND '$date_to_db' 
          $branch_cond_monthly 
          GROUP BY u.branch_name, mr.record_year, mr.record_month 
          ORDER BY mr.record_year, mr.record_month ASC, u.branch_name ASC";
$res = mysqli_query($conn, $query);
while ($r = mysqli_fetch_assoc($res)) $chart_monthly_branches[] = $r;

$selected_branch_name = 'همه شعب';
if ($selected_branch > 0) {
    foreach ($branches as $b) {
        if ($b['id'] == $selected_branch) {
            $selected_branch_name = $b['branch_name'];
            break;
        }
    }
}

function formatMoney($amount, $currency = 'rial') {
    if ($currency == 'rial') {
        if ($amount >= 1000000000) return number_format($amount / 1000000000, 2) . ' میلیارد';
        if ($amount >= 1000000) return number_format($amount / 1000000, 1) . ' میلیون';
        return number_format($amount);
    }
    return number_format($amount, 3);
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>داشبورد درآمد | تراز روزانه</title>
    <link href="../assets/fonts/fonts.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        /* ========== متغیرهای تم ========== */
        :root {
            --bg: #0a0f1a;
            --bg-gradient: linear-gradient(135deg, #0a0f1a 0%, #1a1f2e 100%);
            --surface: rgba(255,255,255,0.03);
            --surface-hover: rgba(255,255,255,0.05);
            --border: rgba(255,255,255,0.06);
            --text: #e8ecf1;
            --text-secondary: #8899aa;
            --accent: #4b8cf7;
            --accent-hover: #3b7ce7;
            --gold: #d4af37;
            --green: #10b981;
            --red: #ef4444;
            --purple: #8b5cf6;
            --radius: 14px;
            --shadow: 0 8px 25px rgba(0,0,0,0.3);
            --card-bg: rgba(255,255,255,0.03);
            --input-bg: rgba(255,255,255,0.05);
        }
        
        /* ========== تم روشن ========== */
        [data-theme="light"] {
            --bg: #f5f7fa;
            --bg-gradient: linear-gradient(135deg, #f5f7fa 0%, #e8ecf1 100%);
            --surface: rgba(255,255,255,0.8);
            --surface-hover: rgba(255,255,255,0.95);
            --border: rgba(0,0,0,0.08);
            --text: #1a1f2e;
            --text-secondary: #6b7280;
            --accent: #3b82f6;
            --accent-hover: #2563eb;
            --gold: #b8960f;
            --green: #059669;
            --red: #dc2626;
            --purple: #7c3aed;
            --shadow: 0 8px 25px rgba(0,0,0,0.1);
            --card-bg: rgba(255,255,255,0.9);
            --input-bg: rgba(0,0,0,0.03);
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Vazirmatn', sans-serif; 
            background: var(--bg-gradient);
            color: var(--text); 
            min-height: 100vh; 
            padding: 20px;
            transition: all 0.3s ease;
        }
        .container { max-width: 1600px; margin: 0 auto; }
        
        /* ========== هدر داشبورد ========== */
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 16px;
        }
        .dashboard-title h1 {
            font-size: 1.8rem;
            background: linear-gradient(135deg, var(--gold), #fbbf24);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 8px;
        }
        .dashboard-title .subtitle {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        .header-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        /* ========== دکمه تم ========== */
        .theme-toggle {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 25px;
            padding: 8px 16px;
            cursor: pointer;
            font-size: 1.2rem;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text);
        }
        .theme-toggle:hover {
            background: var(--surface-hover);
            transform: scale(1.05);
        }
        .theme-icon {
            transition: transform 0.5s;
        }
        [data-theme="light"] .theme-icon {
            transform: rotate(180deg);
        }
        
        /* ========== دکمه‌ها ========== */
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-family: 'Vazirmatn';
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--accent), var(--accent-hover));
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(75, 140, 247, 0.3);
        }
        .btn-success {
            background: linear-gradient(135deg, var(--green), #059669);
            color: white;
        }
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
        }
        .btn-gold {
            background: linear-gradient(135deg, var(--gold), #b8960f);
            color: #1a1f2e;
        }
        .btn-outline {
            background: transparent;
            border: 1px solid var(--accent);
            color: var(--accent);
        }
        .btn-outline:hover {
            background: var(--accent);
            color: white;
        }
        .btn-sm {
            padding: 6px 12px;
            font-size: 0.75rem;
        }
        
        /* ========== کارت‌های خلاصه ========== */
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .summary-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 20px;
            backdrop-filter: blur(10px);
            transition: all 0.3s;
        }
        .summary-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow);
            background: var(--surface-hover);
        }
        .summary-card .card-icon {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        .summary-card .card-label {
            color: var(--text-secondary);
            font-size: 0.8rem;
            margin-bottom: 8px;
        }
        .summary-card .card-value {
            font-size: 1.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--accent), var(--purple));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .summary-card.gold .card-value {
            background: linear-gradient(135deg, var(--gold), #fbbf24);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        /* ========== فیلترها ========== */
        .filters {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 20px;
            margin-bottom: 24px;
            backdrop-filter: blur(10px);
        }
        .filters-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            align-items: end;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .filter-group label {
            font-size: 0.8rem;
            color: var(--text-secondary);
            font-weight: 600;
        }
        select, input {
            padding: 10px 14px;
            border-radius: 10px;
            background: var(--input-bg);
            border: 1px solid var(--border);
            color: var(--text);
            font-family: 'Vazirmatn';
            font-size: 0.85rem;
            transition: all 0.3s;
        }
        select:focus, input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(75, 140, 247, 0.1);
        }
        
        /* ========== تب‌های نمایش ========== */
        .view-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }
        .view-tab {
            padding: 10px 20px;
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 10px;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.3s;
            font-family: 'Vazirmatn';
            font-size: 0.85rem;
        }
        .view-tab.active {
            background: var(--accent);
            border-color: var(--accent);
            color: white;
        }
        .view-tab:hover {
            border-color: var(--accent);
        }
        
        /* ========== گرید نمودارها ========== */
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }
        .chart-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 20px;
            backdrop-filter: blur(10px);
        }
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            flex-wrap: wrap;
            gap: 12px;
        }
        .chart-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--gold);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .chart-type-btns {
            display: flex;
            gap: 4px;
        }
        .chart-type-btn {
            padding: 5px 10px;
            border: 1px solid var(--border);
            background: transparent;
            color: var(--text-secondary);
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.7rem;
            transition: all 0.3s;
            font-family: 'Vazirmatn';
        }
        .chart-type-btn.active {
            background: var(--accent);
            border-color: var(--accent);
            color: white;
        }
        .chart-wrap {
            position: relative;
            height: 400px;
        }
        .full-width {
            grid-column: 1 / -1;
        }
        
        /* ========== جداول ========== */
        .table-section {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 20px;
            margin-bottom: 24px;
            backdrop-filter: blur(10px);
        }
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            flex-wrap: wrap;
            gap: 12px;
        }
        .section-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--gold);
        }
        .table-wrap {
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
            min-width: 900px;
        }
        th {
            background: rgba(75, 140, 247, 0.1);
            padding: 12px;
            text-align: center;
            color: var(--accent);
            font-weight: 600;
            border-bottom: 2px solid var(--border);
            white-space: nowrap;
        }
        td {
            padding: 10px 12px;
            text-align: center;
            border-bottom: 1px solid var(--border);
            color: var(--text);
        }
        tr:hover td {
            background: var(--surface-hover);
        }
        .amount-rial {
            color: var(--gold);
            font-weight: 600;
        }
        .amount-gram {
            color: var(--green);
            font-weight: 600;
        }
        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
        }
        .badge-success { background: rgba(16, 185, 129, 0.2); color: var(--green); }
        .badge-info { background: rgba(75, 140, 247, 0.2); color: var(--accent); }
        .badge-warning { background: rgba(245, 158, 11, 0.2); color: #f59e0b; }
        
        /* ========== Responsive ========== */
        @media (max-width: 768px) {
            .charts-grid {
                grid-template-columns: 1fr;
            }
            .filters-form {
                grid-template-columns: 1fr;
            }
            .summary-cards {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        /* ========== پرینت ========== */
        @media print {
            body { 
                background: white !important; 
                color: black !important; 
                padding: 0; 
            }
            .filters, .view-tabs, .header-actions, .chart-type-btns, .back-link, .theme-toggle { 
                display: none !important; 
            }
            .chart-card, .table-section, .summary-card { 
                background: white !important; 
                border: 1px solid #ddd !important;
                box-shadow: none !important;
                break-inside: avoid;
            }
            .chart-title, .section-title, .card-value { 
                color: #333 !important; 
                -webkit-text-fill-color: #333 !important;
            }
            table { border: 1px solid #ddd; }
            th { background: #f5f5f5; color: #333; }
            td { color: #333; }
        }
        
        .back-link {
            color: var(--accent);
            text-decoration: none;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 20px;
            transition: color 0.3s;
        }
        .back-link:hover { color: var(--gold); }
    </style>
</head>
<body>
<div class="container">
    
    <a href="index.php" class="back-link">← بازگشت به پنل مدیریت</a>
    
    <!-- هدر داشبورد -->
    <div class="dashboard-header">
        <div class="dashboard-title">
            <h1>💰 داشبورد مدیریت درآمد</h1>
            <div class="subtitle">
                شعبه: <strong><?php echo htmlspecialchars($selected_branch_name); ?></strong> | 
                بازه: <?php echo $date_from; ?> تا <?php echo $date_to; ?>
            </div>
        </div>
        <div class="header-actions">
            <!-- دکمه تغییر تم -->
            <button class="theme-toggle" onclick="toggleTheme()" title="تغییر تم روز/شب">
                <span class="theme-icon">🌙</span>
                <span class="theme-text">حالت شب</span>
            </button>
            <button class="btn btn-outline btn-sm" onclick="window.print()">🖨️ پرینت</button>
            <a href="?download=1&branch_id=<?php echo $selected_branch; ?>&from=<?php echo $date_from; ?>&to=<?php echo $date_to; ?>" class="btn btn-success btn-sm">📥 دانلود CSV</a>
            <button class="btn btn-gold btn-sm" onclick="exportToExcel()">📊 Excel</button>
        </div>
    </div>
    
    <!-- کارت‌های خلاصه -->
    <div class="summary-cards">
        <div class="summary-card">
            <div class="card-icon">💵</div>
            <div class="card-label">مجموع درآمد (ریال)</div>
            <div class="card-value"><?php echo formatMoney($total_rial_all); ?></div>
        </div>
        <div class="summary-card gold">
            <div class="card-icon">🪙</div>
            <div class="card-label">مجموع درآمد (گرم طلا)</div>
            <div class="card-value"><?php echo number_format($total_gram_all, 3); ?> گرم</div>
        </div>
        <div class="summary-card">
            <div class="card-icon">📊</div>
            <div class="card-label">میانگین درآمد روزانه</div>
            <div class="card-value"><?php echo formatMoney($avg_daily); ?></div>
        </div>
        <div class="summary-card">
            <div class="card-icon">🏢</div>
            <div class="card-label">تعداد شعب فعال</div>
            <div class="card-value"><?php echo count(array_filter($branch_stats, function($s) { return $s['total_rial'] > 0; })); ?> شعبه</div>
        </div>
        <div class="summary-card">
            <div class="card-icon">📅</div>
            <div class="card-label">روزهای کاری</div>
            <div class="card-value"><?php echo count(array_unique(array_column($daily_data, 'record_date'))); ?> روز</div>
        </div>
    </div>
    
    <!-- فیلترها -->
    <div class="filters">
        <form class="filters-form" method="GET" action="income_stats.php">
            <div class="filter-group">
                <label>🏢 انتخاب شعبه</label>
                <select name="branch_id">
                    <option value="0">همه شعب</option>
                    <?php foreach($branches as $b): ?>
                        <option value="<?php echo $b['id']; ?>" <?php echo $b['id'] == $selected_branch ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($b['branch_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>📅 از تاریخ</label>
                <input type="text" name="from" value="<?php echo $date_from; ?>" placeholder="۱۴۰۴/۰۱/۰۱">
            </div>
            <div class="filter-group">
                <label>📅 تا تاریخ</label>
                <input type="text" name="to" value="<?php echo $date_to; ?>" placeholder="۱۴۰۴/۰۳/۰۱">
            </div>
            <div class="filter-group">
                <label>📈 نوع نمودار پیش‌فرض</label>
                <select name="chart_type">
                    <option value="line" <?php echo $chart_type == 'line' ? 'selected' : ''; ?>>نمودار خطی</option>
                    <option value="bar" <?php echo $chart_type == 'bar' ? 'selected' : ''; ?>>نمودار میله‌ای</option>
                    <option value="area" <?php echo $chart_type == 'area' ? 'selected' : ''; ?>>نمودار سطحی</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">🔍 اعمال فیلترها</button>
        </form>
    </div>
    
    <!-- تب‌های نمایش -->
    <div class="view-tabs">
        <button class="view-tab active" onclick="showView('dashboard')">📊 داشبورد نمودارها</button>
        <button class="view-tab" onclick="showView('daily')">📆 جدول روزانه</button>
        <button class="view-tab" onclick="showView('monthly')">📅 جدول ماهانه</button>
        <button class="view-tab" onclick="showView('branches')">🏢 مقایسه شعب</button>
    </div>
    
    <!-- نمای داشبورد -->
    <div id="view-dashboard" class="view-content">
        <div class="charts-grid">
            <!-- نمودار ۱: روند درآمد روزانه به تفکیک شعب -->
            <div class="chart-card full-width">
                <div class="chart-header">
                    <div class="chart-title">📈 روند درآمد روزانه به تفکیک شعب (میلیون ریال)</div>
                    <div class="chart-type-btns">
                        <button class="chart-type-btn active" onclick="changeChartType('branchesDailyChart', 'line', this)">خطی</button>
                        <button class="chart-type-btn" onclick="changeChartType('branchesDailyChart', 'bar', this)">میله‌ای</button>
                        <button class="chart-type-btn" onclick="changeChartType('branchesDailyChart', 'area', this)">سطحی</button>
                    </div>
                </div>
                <div class="chart-wrap">
                    <canvas id="branchesDailyChart"></canvas>
                </div>
            </div>
            
            <!-- نمودار ۲: مقایسه کل شعب -->
            <div class="chart-card">
                <div class="chart-header">
                    <div class="chart-title">🏢 مقایسه کل درآمد شعب (میلیون ریال)</div>
                    <div class="chart-type-btns">
                        <button class="chart-type-btn active" onclick="changeChartType('branchesChart', 'bar', this)">میله‌ای</button>
                        <button class="chart-type-btn" onclick="changeChartType('branchesChart', 'pie', this)">دایره‌ای</button>
                        <button class="chart-type-btn" onclick="changeChartType('branchesChart', 'doughnut', this)">دوناتی</button>
                    </div>
                </div>
                <div class="chart-wrap">
                    <canvas id="branchesChart"></canvas>
                </div>
            </div>
            
            <!-- نمودار ۳: ترکیب آیتم‌های درآمد -->
            <div class="chart-card">
                <div class="chart-header">
                    <div class="chart-title">📦 ترکیب آیتم‌های درآمد روزانه</div>
                    <div class="chart-type-btns">
                        <button class="chart-type-btn active" onclick="changeChartType('itemsDailyChart', 'doughnut', this)">دوناتی</button>
                        <button class="chart-type-btn" onclick="changeChartType('itemsDailyChart', 'pie', this)">دایره‌ای</button>
                        <button class="chart-type-btn" onclick="changeChartType('itemsDailyChart', 'polarArea', this)">قطبی</button>
                    </div>
                </div>
                <div class="chart-wrap">
                    <canvas id="itemsDailyChart"></canvas>
                </div>
            </div>
            
            <!-- نمودار ۴: روند ماهانه به تفکیک شعب -->
            <div class="chart-card full-width">
                <div class="chart-header">
                    <div class="chart-title">📅 روند درآمد ماهانه به تفکیک شعب (گرم طلا)</div>
                    <div class="chart-type-btns">
                        <button class="chart-type-btn active" onclick="changeChartType('monthlyBranchesChart', 'bar', this)">میله‌ای</button>
                        <button class="chart-type-btn" onclick="changeChartType('monthlyBranchesChart', 'line', this)">خطی</button>
                        <button class="chart-type-btn" onclick="changeChartType('monthlyBranchesChart', 'area', this)">سطحی</button>
                    </div>
                </div>
                <div class="chart-wrap">
                    <canvas id="monthlyBranchesChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <!-- نمای جدول روزانه -->
    <div id="view-daily" class="view-content" style="display:none;">
        <div class="table-section">
            <div class="section-header">
                <div class="section-title">📆 گزارش درآمد روزانه</div>
                <span class="badge badge-info"><?php echo count($daily_data); ?> رکورد</span>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ردیف</th>
                            <th>شعبه</th>
                            <th>تاریخ</th>
                            <th>نوع آیتم</th>
                            <th>مبلغ (ریال)</th>
                            <th>مبلغ (گرم)</th>
                            <th>نرخ طلا</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $row_num = 1; foreach($daily_data as $r): ?>
                        <tr>
                            <td><?php echo $row_num++; ?></td>
                            <td><strong><?php echo htmlspecialchars($r['branch_name']); ?></strong></td>
                            <td><?php echo $r['record_date']; ?></td>
                            <td><?php echo htmlspecialchars($r['item_name']); ?></td>
                            <td class="amount-rial"><?php echo number_format($r['amount_rial']); ?></td>
                            <td class="amount-gram"><?php echo number_format($r['amount_gram'], 4); ?></td>
                            <td><?php echo number_format($r['gold_rate']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($daily_data)): ?>
                        <tr><td colspan="7" style="color: var(--text-secondary);">هیچ داده‌ای یافت نشد</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- نمای جدول ماهانه -->
    <div id="view-monthly" class="view-content" style="display:none;">
        <div class="table-section">
            <div class="section-header">
                <div class="section-title">📅 گزارش درآمد ماهانه</div>
                <span class="badge badge-success"><?php echo count($monthly_data); ?> رکورد</span>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ردیف</th>
                            <th>شعبه</th>
                            <th>سال/ماه</th>
                            <th>نوع آیتم</th>
                            <th>مبلغ (گرم طلا)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $row_num = 1; foreach($monthly_data as $r): ?>
                        <tr>
                            <td><?php echo $row_num++; ?></td>
                            <td><strong><?php echo htmlspecialchars($r['branch_name']); ?></strong></td>
                            <td><?php echo $r['record_date']; ?></td>
                            <td><?php echo htmlspecialchars($r['item_name']); ?></td>
                            <td class="amount-gram"><?php echo number_format($r['amount_gram'], 3); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($monthly_data)): ?>
                        <tr><td colspan="5" style="color: var(--text-secondary);">هیچ داده‌ای یافت نشد</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- نمای مقایسه شعب -->
    <div id="view-branches" class="view-content" style="display:none;">
        <div class="table-section">
            <div class="section-header">
                <div class="section-title">🏢 مقایسه عملکرد شعب</div>
                <span class="badge badge-warning"><?php echo count($branch_stats); ?> شعبه</span>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>رتبه</th>
                            <th>نام شعبه</th>
                            <th>مجموع درآمد (ریال)</th>
                            <th>مجموع (گرم طلا)</th>
                            <th>روزهای کاری</th>
                            <th>میانگین روزانه (ریال)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $rank = 1; foreach($branch_stats as $s): ?>
                        <tr>
                            <td>
                                <?php if ($rank == 1): ?>🥇
                                <?php elseif ($rank == 2): ?>🥈
                                <?php elseif ($rank == 3): ?>🥉
                                <?php else: echo $rank; endif; ?>
                            </td>
                            <td><strong><?php echo htmlspecialchars($s['branch_name']); ?></strong></td>
                            <td class="amount-rial"><?php echo number_format($s['total_rial']); ?></td>
                            <td class="amount-gram"><?php echo number_format($s['total_daily_gram'], 3); ?></td>
                            <td><?php echo $s['active_days']; ?> روز</td>
                            <td><?php echo $s['active_days'] > 0 ? number_format($s['total_rial'] / $s['active_days']) : 0; ?></td>
                        </tr>
                        <?php $rank++; endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
</div>

<script>
// ========== داده‌های PHP ==========
var branchesDailyData = <?php echo json_encode($chart_branches_daily, JSON_UNESCAPED_UNICODE); ?>;
var branchesComparisonData = <?php echo json_encode($chart_branches_comparison, JSON_UNESCAPED_UNICODE); ?>;
var itemsDailyData = <?php echo json_encode($chart_items_daily, JSON_UNESCAPED_UNICODE); ?>;
var monthlyBranchesData = <?php echo json_encode($chart_monthly_branches, JSON_UNESCAPED_UNICODE); ?>;

// ========== رنگ‌های ثابت برای شعب ==========
var branchColors = {
    default: [
        { border: '#d4af37', bg: 'rgba(212, 175, 55, 0.2)' },
        { border: '#4b8cf7', bg: 'rgba(75, 140, 247, 0.2)' },
        { border: '#10b981', bg: 'rgba(16, 185, 129, 0.2)' },
        { border: '#f59e0b', bg: 'rgba(245, 158, 11, 0.2)' },
        { border: '#8b5cf6', bg: 'rgba(139, 92, 246, 0.2)' },
        { border: '#ec4899', bg: 'rgba(236, 72, 153, 0.2)' },
        { border: '#06b6d4', bg: 'rgba(6, 182, 212, 0.2)' },
        { border: '#84cc16', bg: 'rgba(132, 204, 22, 0.2)' },
        { border: '#f97316', bg: 'rgba(249, 115, 22, 0.2)' },
        { border: '#6366f1', bg: 'rgba(99, 102, 241, 0.2)' }
    ]
};

var chartInstances = {};

// ========== تابع کمکی: پردازش داده‌ها برای نمودار چند خطی ==========
function processMultiLineData(data, groupKey, dateKey, valueKey) {
    var dates = [];
    var dateSet = {};
    var groups = {};
    var groupSet = {};
    
    // جمع‌آوری تاریخ‌ها و گروه‌ها
    for (var i = 0; i < data.length; i++) {
        var d = data[i][dateKey];
        var g = data[i][groupKey];
        
        if (!dateSet[d]) {
            dateSet[d] = true;
            dates.push(d);
        }
        if (!groupSet[g]) {
            groupSet[g] = true;
            groups[g] = {};
        }
    }
    
    // مرتب‌سازی تاریخ‌ها
    dates.sort();
    
    // پر کردن داده‌ها
    for (var i = 0; i < data.length; i++) {
        var d = data[i][dateKey];
        var g = data[i][groupKey];
        var v = parseFloat(data[i][valueKey]) || 0;
        groups[g][d] = v;
    }
    
    return { dates: dates, groups: groups };
}

// ========== نمودار ۱: روند روزانه به تفکیک شعب ==========
var dailyProcessed = processMultiLineData(branchesDailyData, 'branch_name', 'record_date', 'total');
var dailyDates = dailyProcessed.dates;
var dailyGroups = dailyProcessed.groups;
var dailyGroupNames = Object.keys(dailyGroups);
var dailyDatasets = [];

for (var i = 0; i < dailyGroupNames.length; i++) {
    var name = dailyGroupNames[i];
    var color = branchColors.default[i % branchColors.default.length];
    var data = [];
    
    for (var j = 0; j < dailyDates.length; j++) {
        data.push((dailyGroups[name][dailyDates[j]] || 0) / 1000000);
    }
    
    dailyDatasets.push({
        label: name,
        data: data,
        borderColor: color.border,
        backgroundColor: color.bg,
        borderWidth: 2,
        fill: false,
        tension: 0.4,
        pointRadius: 3,
        pointHoverRadius: 6,
        pointBackgroundColor: color.border
    });
}

var ctx1 = document.getElementById('branchesDailyChart').getContext('2d');
chartInstances['branchesDailyChart'] = new Chart(ctx1, {
    type: 'line',
    data: {
        labels: dailyDates,
        datasets: dailyDatasets
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: {
            mode: 'index',
            intersect: false,
        },
        plugins: {
            legend: {
                position: 'top',
                labels: {
                    color: '#8899aa',
                    font: { family: 'Vazirmatn', size: 11 },
                    padding: 15,
                    usePointStyle: true
                }
            },
            tooltip: {
                rtl: true,
                titleFont: { family: 'Vazirmatn' },
                bodyFont: { family: 'Vazirmatn' },
                callbacks: {
                    label: function(context) {
                        return context.dataset.label + ': ' + context.parsed.y.toFixed(2) + ' میلیون ریال';
                    }
                }
            }
        },
        scales: {
            x: {
                ticks: { color: '#8899aa', font: { family: 'Vazirmatn', size: 10 } },
                grid: { color: 'rgba(255,255,255,0.03)' }
            },
            y: {
                ticks: { 
                    color: '#8899aa', 
                    font: { family: 'Vazirmatn', size: 10 },
                    callback: function(value) { return value.toFixed(1) + ' M'; }
                },
                grid: { color: 'rgba(255,255,255,0.03)' },
                beginAtZero: true
            }
        }
    }
});

// ========== نمودار ۲: مقایسه کل شعب ==========
var ctx2 = document.getElementById('branchesChart').getContext('2d');
chartInstances['branchesChart'] = new Chart(ctx2, {
    type: 'bar',
    data: {
        labels: branchesComparisonData.map(function(d) { return d.branch_name; }),
        datasets: [{
            label: 'مجموع درآمد (میلیون ریال)',
            data: branchesComparisonData.map(function(d) { return d.total / 1000000; }),
            backgroundColor: branchColors.default.map(function(c) { return c.bg; }),
            borderColor: branchColors.default.map(function(c) { return c.border; }),
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                labels: { color: '#8899aa', font: { family: 'Vazirmatn', size: 12 }, padding: 15 }
            },
            tooltip: {
                rtl: true,
                callbacks: {
                    label: function(context) {
                        return 'مجموع: ' + context.parsed.y.toFixed(1) + ' میلیون ریال';
                    }
                }
            }
        },
        scales: {
            x: {
                ticks: { color: '#8899aa', font: { family: 'Vazirmatn', size: 10 } },
                grid: { color: 'rgba(255,255,255,0.03)' }
            },
            y: {
                ticks: { 
                    color: '#8899aa', 
                    font: { family: 'Vazirmatn', size: 10 },
                    callback: function(value) { return value.toFixed(0) + ' M'; }
                },
                grid: { color: 'rgba(255,255,255,0.03)' },
                beginAtZero: true
            }
        }
    }
});

// ========== نمودار ۳: آیتم‌های روزانه ==========
var ctx3 = document.getElementById('itemsDailyChart').getContext('2d');
chartInstances['itemsDailyChart'] = new Chart(ctx3, {
    type: 'doughnut',
    data: {
        labels: itemsDailyData.map(function(d) { return d.name; }),
        datasets: [{
            data: itemsDailyData.map(function(d) { return d.total; }),
            backgroundColor: branchColors.default.map(function(c) { return c.bg.replace('0.2', '0.7'); }),
            borderColor: branchColors.default.map(function(c) { return c.border; }),
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: { 
                    color: '#8899aa', 
                    font: { family: 'Vazirmatn', size: 11 }, 
                    padding: 15,
                    usePointStyle: true
                }
            },
            tooltip: {
                rtl: true,
                callbacks: {
                    label: function(context) {
                        var total = context.dataset.data.reduce(function(a, b) { return a + b; }, 0);
                        var percentage = ((context.parsed / total) * 100).toFixed(1);
                        return context.label + ': ' + (context.parsed / 1000000).toFixed(1) + ' میلیون (' + percentage + '%)';
                    }
                }
            }
        }
    }
});

// ========== نمودار ۴: روند ماهانه به تفکیک شعب ==========
var monthlyProcessed = processMultiLineData(monthlyBranchesData, 'branch_name', 'month_label', 'total');
var monthlyDates = monthlyProcessed.dates;
var monthlyGroups = monthlyProcessed.groups;
var monthlyGroupNames = Object.keys(monthlyGroups);
var monthlyDatasets = [];

for (var i = 0; i < monthlyGroupNames.length; i++) {
    var name = monthlyGroupNames[i];
    var color = branchColors.default[i % branchColors.default.length];
    var data = [];
    
    for (var j = 0; j < monthlyDates.length; j++) {
        data.push(monthlyGroups[name][monthlyDates[j]] || 0);
    }
    
    monthlyDatasets.push({
        label: name,
        data: data,
        borderColor: color.border,
        backgroundColor: color.bg,
        borderWidth: 2,
        fill: false,
        tension: 0.4,
        pointRadius: 4,
        pointHoverRadius: 7,
        pointBackgroundColor: color.border
    });
}

var ctx4 = document.getElementById('monthlyBranchesChart').getContext('2d');
chartInstances['monthlyBranchesChart'] = new Chart(ctx4, {
    type: 'bar',
    data: {
        labels: monthlyDates,
        datasets: monthlyDatasets
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: {
            mode: 'index',
            intersect: false,
        },
        plugins: {
            legend: {
                position: 'top',
                labels: {
                    color: '#8899aa',
                    font: { family: 'Vazirmatn', size: 11 },
                    padding: 15,
                    usePointStyle: true
                }
            },
            tooltip: {
                rtl: true,
                titleFont: { family: 'Vazirmatn' },
                bodyFont: { family: 'Vazirmatn' },
                callbacks: {
                    label: function(context) {
                        return context.dataset.label + ': ' + context.parsed.y.toFixed(2) + ' گرم';
                    }
                }
            }
        },
        scales: {
            x: {
                ticks: { color: '#8899aa', font: { family: 'Vazirmatn', size: 10 } },
                grid: { color: 'rgba(255,255,255,0.03)' }
            },
            y: {
                ticks: { 
                    color: '#8899aa', 
                    font: { family: 'Vazirmatn', size: 10 },
                    callback: function(value) { return value.toFixed(1) + ' g'; }
                },
                grid: { color: 'rgba(255,255,255,0.03)' },
                beginAtZero: true
            }
        }
    }
});

// ========== تغییر نوع نمودار ==========
function changeChartType(chartId, type, btn) {
    var chart = chartInstances[chartId];
    if (!chart) return;
    
    // به‌روزرسانی دکمه‌های فعال
    var buttons = btn.parentElement.querySelectorAll('.chart-type-btn');
    buttons.forEach(function(b) { b.classList.remove('active'); });
    btn.classList.add('active');
    
    // تنظیم نوع نمودار
    var actualType = type === 'area' ? 'line' : type;
    chart.config.type = actualType;
    
    // تنظیم fill برای نمودار سطحی
    if (type === 'area') {
        chart.data.datasets.forEach(function(dataset) {
            dataset.fill = true;
        });
    } else {
        chart.data.datasets.forEach(function(dataset) {
            dataset.fill = false;
        });
    }
    
    // تنظیمات خاص برای نمودارهای دایره‌ای
    if (['pie', 'doughnut', 'polarArea'].includes(type)) {
        chart.options.scales = {};
        // برای نمودارهای دایره‌ای فقط از dataset اول استفاده می‌کنیم
        if (chart.data.datasets.length > 1) {
            // ترکیب همه dataset ها در یک dataset
            var allLabels = [];
            var allData = [];
            var allColors = [];
            var allBorders = [];
            
            chart.data.datasets.forEach(function(ds, idx) {
                ds.data.forEach(function(val, i) {
                    allLabels.push(ds.label + ' - ' + chart.data.labels[i]);
                    allData.push(val);
                    allColors.push(branchColors.default[idx % branchColors.default.length].bg.replace('0.2', '0.7'));
                    allBorders.push(branchColors.default[idx % branchColors.default.length].border);
                });
            });
            
            chart.data.labels = allLabels;
            chart.data.datasets = [{
                data: allData,
                backgroundColor: allColors,
                borderColor: allBorders,
                borderWidth: 2
            }];
        }
    } else {
        // بازگرداندن تنظیمات scales
        chart.options.scales = {
            x: {
                ticks: { color: '#8899aa', font: { family: 'Vazirmatn', size: 10 } },
                grid: { color: 'rgba(255,255,255,0.03)' }
            },
            y: {
                ticks: { color: '#8899aa', font: { family: 'Vazirmatn', size: 10 } },
                grid: { color: 'rgba(255,255,255,0.03)' },
                beginAtZero: true
            }
        };
    }
    
    chart.update();
}

// ========== تغییر نماها ==========
function showView(viewName) {
    var views = document.querySelectorAll('.view-content');
    views.forEach(function(v) { v.style.display = 'none'; });
    
    var targetView = document.getElementById('view-' + viewName);
    if (targetView) {
        targetView.style.display = 'block';
    }
    
    var tabs = document.querySelectorAll('.view-tab');
    tabs.forEach(function(t) { t.classList.remove('active'); });
    event.target.classList.add('active');
    
    if (viewName === 'dashboard') {
        setTimeout(function() {
            Object.values(chartInstances).forEach(function(chart) {
                chart.resize();
            });
        }, 100);
    }
}

// ========== تغییر تم روز/شب ==========
function toggleTheme() {
    var html = document.documentElement;
    var currentTheme = html.getAttribute('data-theme');
    var themeToggle = document.querySelector('.theme-toggle');
    var themeIcon = themeToggle.querySelector('.theme-icon');
    var themeText = themeToggle.querySelector('.theme-text');
    
    if (currentTheme === 'light') {
        html.removeAttribute('data-theme');
        themeIcon.textContent = '🌙';
        themeText.textContent = 'حالت شب';
        localStorage.setItem('dashboard-theme', 'dark');
        updateChartsTheme('dark');
    } else {
        html.setAttribute('data-theme', 'light');
        themeIcon.textContent = '☀️';
        themeText.textContent = 'حالت روز';
        localStorage.setItem('dashboard-theme', 'light');
        updateChartsTheme('light');
    }
}

// ========== به‌روزرسانی تم نمودارها ==========
function updateChartsTheme(theme) {
    var textColor = theme === 'light' ? '#4b5563' : '#8899aa';
    var gridColor = theme === 'light' ? 'rgba(0,0,0,0.06)' : 'rgba(255,255,255,0.03)';
    
    Object.values(chartInstances).forEach(function(chart) {
        if (chart.options.scales) {
            if (chart.options.scales.x) {
                chart.options.scales.x.ticks.color = textColor;
                chart.options.scales.x.grid.color = gridColor;
            }
            if (chart.options.scales.y) {
                chart.options.scales.y.ticks.color = textColor;
                chart.options.scales.y.grid.color = gridColor;
            }
        }
        if (chart.options.plugins && chart.options.plugins.legend) {
            chart.options.plugins.legend.labels.color = textColor;
        }
        chart.update();
    });
}

// ========== بارگذاری تم ذخیره شده ==========
(function() {
    var savedTheme = localStorage.getItem('dashboard-theme');
    if (savedTheme === 'light') {
        document.documentElement.setAttribute('data-theme', 'light');
        var themeToggle = document.querySelector('.theme-toggle');
        if (themeToggle) {
            themeToggle.querySelector('.theme-icon').textContent = '☀️';
            themeToggle.querySelector('.theme-text').textContent = 'حالت روز';
        }
    }
})();

// ========== خروجی Excel ==========
function exportToExcel() {
    var tables = document.querySelectorAll('.table-section table');
    var html = '<html><head><meta charset="UTF-8"></head><body>';
    
    tables.forEach(function(table) {
        html += table.outerHTML + '<br><br>';
    });
    
    html += '</body></html>';
    
    var blob = new Blob(['\uFEFF' + html], { type: 'application/vnd.ms-excel' });
    var url = URL.createObjectURL(blob);
    var a = document.createElement('a');
    a.href = url;
    a.download = 'income_report_' + new Date().toISOString().slice(0,10) + '.xls';
    a.click();
    URL.revokeObjectURL(url);
}
</script>
</body>
</html>