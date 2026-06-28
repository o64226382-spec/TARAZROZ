<?php
define('SECURE_ACCESS', true);
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireAdmin();

// ========== دریافت شعب ==========
$branches = [];
$br_res = mysqli_query($conn, "SELECT id, branch_name FROM users WHERE role = 'branch' ORDER BY branch_name");
while ($b = mysqli_fetch_assoc($br_res)) {
    $branches[] = $b;
}

// ========== دریافت تمام آیتم‌های داینامیک ==========
$all_items = [];
$items_res = mysqli_query($conn, "SELECT id, name FROM dynamic_items ORDER BY name");
while ($item = mysqli_fetch_assoc($items_res)) {
    $all_items[] = $item;
}

// ========== فیلترها ==========
$selected_branch = isset($_GET['branch_id']) ? intval($_GET['branch_id']) : ($branches[0]['id'] ?? 0);
$date_from = $_GET['from'] ?? date('Y/m/d', strtotime('-30 days'));
$date_to = $_GET['to'] ?? date('Y/m/d');
$date_from_db = str_replace('/', '-', $date_from);
$date_to_db = str_replace('/', '-', $date_to);
$chart_type = $_GET['chart_type'] ?? 'line';

// ========== نمودار ۱: مقایسه شعب (تمام آیتم‌ها با هم) ==========
$branches_comparison = [];
$stmt = mysqli_prepare($conn, 
    "SELECT u.branch_name, dr.report_date, SUM(drc.amount_gram) as total 
     FROM dynamic_records drc 
     JOIN daily_reports dr ON drc.report_id = dr.id 
     JOIN users u ON dr.user_id = u.id 
     WHERE u.role = 'branch' AND dr.report_date BETWEEN ? AND ? 
     GROUP BY u.branch_name, dr.report_date 
     ORDER BY dr.report_date ASC"
);
mysqli_stmt_bind_param($stmt, "ss", $date_from_db, $date_to_db);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) {
    $branches_comparison[] = $row;
}

// ========== نمودار ۲: آیتم‌های یک شعبه خاص ==========
$branch_items = [];
$stmt = mysqli_prepare($conn, 
    "SELECT dr.report_date, di.name, SUM(drc.amount_gram) as total 
     FROM dynamic_records drc 
     JOIN daily_reports dr ON drc.report_id = dr.id 
     JOIN dynamic_items di ON drc.item_id = di.id 
     WHERE dr.user_id = ? AND dr.report_date BETWEEN ? AND ? 
     GROUP BY dr.report_date, di.name 
     ORDER BY dr.report_date ASC"
);
mysqli_stmt_bind_param($stmt, "iss", $selected_branch, $date_from_db, $date_to_db);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) {
    $branch_items[] = $row;
}

// ========== آمار ماهانه ==========
$monthly_stats = [];
$stmt = mysqli_prepare($conn, 
    "SELECT DATE_FORMAT(dr.report_date, '%Y-%m') as month, di.name, SUM(drc.amount_gram) as total 
     FROM dynamic_records drc 
     JOIN daily_reports dr ON drc.report_id = dr.id 
     JOIN dynamic_items di ON drc.item_id = di.id 
     WHERE dr.user_id = ? AND dr.report_date BETWEEN ? AND ? 
     GROUP BY month, di.name 
     ORDER BY month DESC"
);
mysqli_stmt_bind_param($stmt, "iss", $selected_branch, $date_from_db, $date_to_db);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) {
    $monthly_stats[] = $row;
}

// ========== خلاصه آماری ==========
$summary = [];
$stmt = mysqli_prepare($conn, 
    "SELECT di.name, SUM(drc.amount_gram) as total, AVG(drc.amount_gram) as avg, MAX(drc.amount_gram) as max, MIN(drc.amount_gram) as min
     FROM dynamic_records drc 
     JOIN daily_reports dr ON drc.report_id = dr.id 
     JOIN dynamic_items di ON drc.item_id = di.id 
     WHERE dr.user_id = ? AND dr.report_date BETWEEN ? AND ? 
     GROUP BY di.name"
);
mysqli_stmt_bind_param($stmt, "iss", $selected_branch, $date_from_db, $date_to_db);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) {
    $summary[] = $row;
}

// ========== نام شعبه انتخاب شده ==========
$selected_branch_name = 'نامشخص';
foreach ($branches as $b) {
    if ($b['id'] == $selected_branch) {
        $selected_branch_name = $b['branch_name'];
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>داشبورد حرفه‌ای | آمار آیتم‌های داینامیک</title>
    <link href="../assets/fonts/fonts.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        :root { 
            --bg: #0a0f1a; 
            --surface: rgba(255,255,255,0.03); 
            --border: rgba(255,255,255,0.06); 
            --text: #e8ecf1; 
            --text-secondary: #8899aa; 
            --accent: #4b8cf7; 
            --gold: #d4af37; 
            --green: #10b981;
            --red: #ef4444;
            --purple: #8b5cf6;
            --radius: 14px; 
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Vazirmatn', sans-serif; 
            background: linear-gradient(135deg, #0a0f1a 0%, #1a1f2e 100%);
            color: var(--text); 
            min-height: 100vh; 
            padding: 20px;
        }
        .container { max-width: 1400px; margin: 0 auto; }
        
        /* هدر */
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
        .dashboard-title p {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        /* کارت‌های خلاصه */
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .summary-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 20px;
            backdrop-filter: blur(10px);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .summary-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
        }
        .summary-card .label {
            color: var(--text-secondary);
            font-size: 0.8rem;
            margin-bottom: 8px;
        }
        .summary-card .value {
            font-size: 1.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--accent), var(--purple));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        /* فیلترها */
        .filters {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 20px;
            margin-bottom: 24px;
            backdrop-filter: blur(10px);
        }
        .filters-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
            display: flex;
            align-items: center;
            gap: 6px;
        }
        select, input {
            padding: 10px 14px;
            border-radius: 10px;
            background: rgba(255,255,255,0.05);
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
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-family: 'Vazirmatn';
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--accent), #3b7ce7);
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(75, 140, 247, 0.3);
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
        .btn-gold {
            background: linear-gradient(135deg, var(--gold), #b8960f);
            color: #1a1f2e;
        }
        .btn-gold:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(212, 175, 55, 0.3);
        }
        .btn-group {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        /* گرید نمودارها */
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }
        .chart-card {
            background: var(--surface);
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
        .chart-controls {
            display: flex;
            gap: 8px;
        }
        .chart-type-btn {
            padding: 6px 12px;
            border: 1px solid var(--border);
            background: transparent;
            color: var(--text-secondary);
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.75rem;
            transition: all 0.3s;
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
        
        /* جدول آمار ماهانه */
        .monthly-section {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 20px;
            margin-bottom: 24px;
            backdrop-filter: blur(10px);
        }
        .section-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--gold);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .table-wrap {
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }
        th {
            background: rgba(75, 140, 247, 0.1);
            padding: 12px;
            text-align: right;
            color: var(--accent);
            font-weight: 600;
            border-bottom: 2px solid var(--border);
        }
        td {
            padding: 10px 12px;
            border-bottom: 1px solid var(--border);
        }
        tr:hover td {
            background: rgba(255,255,255,0.02);
        }
        
        /* جدول خلاصه */
        .summary-table {
            margin-top: 16px;
        }
        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .badge-success { background: rgba(16, 185, 129, 0.2); color: var(--green); }
        .badge-info { background: rgba(75, 140, 247, 0.2); color: var(--accent); }
        
        /* پرینت */
        @media print {
            body { background: white; color: black; padding: 0; }
            .filters, .chart-controls, .btn, .back-link { display: none; }
            .chart-card, .monthly-section, .summary-cards { 
                background: white; 
                border: 1px solid #ddd;
                box-shadow: none;
                break-inside: avoid;
            }
            .chart-title, .section-title { color: #333; }
            table { border: 1px solid #ddd; }
            th { background: #f5f5f5; color: #333; }
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
        
        /* Responsive */
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
    </style>
</head>
<body>
<div class="container">
    
    <a href="index.php" class="back-link">← بازگشت به پنل مدیریت</a>
    
    <!-- هدر -->
    <div class="dashboard-header">
        <div class="dashboard-title">
            <h1>📊 داشبورد آیتم‌های داینامیک</h1>
            <p>شعبه: <?php echo htmlspecialchars($selected_branch_name); ?> | بازه: <?php echo $date_from; ?> تا <?php echo $date_to; ?></p>
        </div>
        <div class="btn-group">
            <button class="btn btn-outline" onclick="window.print()">🖨️ پرینت گزارش</button>
            <button class="btn btn-gold" onclick="exportToExcel()">📥 خروجی Excel</button>
        </div>
    </div>
    
    <!-- کارت‌های خلاصه -->
    <div class="summary-cards">
        <?php
        $total_all = 0;
        $max_item = ['name' => '-', 'total' => 0];
        foreach ($summary as $s) {
            $total_all += $s['total'];
            if ($s['total'] > $max_item['total']) {
                $max_item = ['name' => $s['name'], 'total' => $s['total']];
            }
        }
        ?>
        <div class="summary-card">
            <div class="label">💰 مجموع کل (گرم)</div>
            <div class="value"><?php echo number_format($total_all, 2); ?></div>
        </div>
        <div class="summary-card">
            <div class="label">🏆 بیشترین آیتم</div>
            <div class="value"><?php echo htmlspecialchars($max_item['name']); ?></div>
        </div>
        <div class="summary-card">
            <div class="label">📦 تعداد آیتم‌ها</div>
            <div class="value"><?php echo count($all_items); ?></div>
        </div>
        <div class="summary-card">
            <div class="label">🏢 تعداد شعب</div>
            <div class="value"><?php echo count($branches); ?></div>
        </div>
    </div>
    
    <!-- فیلترها -->
    <div class="filters">
        <form class="filters-form" method="GET" action="dynamic_stats.php">
            <div class="filter-group">
                <label>🏢 انتخاب شعبه</label>
                <select name="branch_id">
                    <?php foreach ($branches as $b): ?>
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
                    <option value="radar" <?php echo $chart_type == 'radar' ? 'selected' : ''; ?>>نمودار راداری</option>
                    <option value="pie" <?php echo $chart_type == 'pie' ? 'selected' : ''; ?>>نمودار دایره‌ای</option>
                    <option value="doughnut" <?php echo $chart_type == 'doughnut' ? 'selected' : ''; ?>>نمودار دوناتی</option>
                    <option value="polarArea" <?php echo $chart_type == 'polarArea' ? 'selected' : ''; ?>>نمودار قطبی</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">🔍 اعمال فیلترها</button>
        </form>
    </div>
    
    <!-- نمودارها -->
    <div class="charts-grid">
        <!-- نمودار ۱: مقایسه شعب -->
        <div class="chart-card">
            <div class="chart-header">
                <div class="chart-title">🏢 مقایسه عملکرد شعب</div>
                <div class="chart-controls">
                    <button class="chart-type-btn active" onclick="changeChartType('branchesChart', 'line')">خطی</button>
                    <button class="chart-type-btn" onclick="changeChartType('branchesChart', 'bar')">میله‌ای</button>
                    <button class="chart-type-btn" onclick="changeChartType('branchesChart', 'radar')">راداری</button>
                </div>
            </div>
            <div class="chart-wrap">
                <canvas id="branchesChart"></canvas>
            </div>
        </div>
        
        <!-- نمودار ۲: آیتم‌های شعبه -->
        <div class="chart-card">
            <div class="chart-header">
                <div class="chart-title">📦 آیتم‌های شعبه: <?php echo htmlspecialchars($selected_branch_name); ?></div>
                <div class="chart-controls">
                    <button class="chart-type-btn active" onclick="changeChartType('itemsChart', 'line')">خطی</button>
                    <button class="chart-type-btn" onclick="changeChartType('itemsChart', 'bar')">میله‌ای</button>
                    <button class="chart-type-btn" onclick="changeChartType('itemsChart', 'pie')">دایره‌ای</button>
                </div>
            </div>
            <div class="chart-wrap">
                <canvas id="itemsChart"></canvas>
            </div>
        </div>
    </div>
    
    <!-- جدول آمار ماهانه -->
    <div class="monthly-section">
        <div class="section-title">📅 آمار ماهانه - <?php echo htmlspecialchars($selected_branch_name); ?></div>
        <div class="table-wrap">
            <?php
            // گروه‌بندی داده‌های ماهانه
            $monthly_grouped = [];
            $months = [];
            $item_names_monthly = [];
            
            foreach ($monthly_stats as $row) {
                $monthly_grouped[$row['month']][$row['name']] = $row['total'];
                if (!in_array($row['month'], $months)) {
                    $months[] = $row['month'];
                }
                if (!in_array($row['name'], $item_names_monthly)) {
                    $item_names_monthly[] = $row['name'];
                }
            }
            ?>
            <table>
                <thead>
                    <tr>
                        <th>ماه</th>
                        <?php foreach ($item_names_monthly as $item): ?>
                            <th><?php echo htmlspecialchars($item); ?> (گرم)</th>
                        <?php endforeach; ?>
                        <th>مجموع</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($months as $month): ?>
                        <tr>
                            <td><strong><?php echo $month; ?></strong></td>
                            <?php 
                            $row_total = 0;
                            foreach ($item_names_monthly as $item): 
                                $val = $monthly_grouped[$month][$item] ?? 0;
                                $row_total += $val;
                            ?>
                                <td><?php echo number_format($val, 2); ?></td>
                            <?php endforeach; ?>
                            <td><span class="badge badge-info"><?php echo number_format($row_total, 2); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- جدول خلاصه آماری -->
    <div class="monthly-section">
        <div class="section-title">📋 خلاصه آماری آیتم‌ها - <?php echo htmlspecialchars($selected_branch_name); ?></div>
        <div class="table-wrap">
            <table class="summary-table">
                <thead>
                    <tr>
                        <th>نام آیتم</th>
                        <th>مجموع (گرم)</th>
                        <th>میانگین</th>
                        <th>حداکثر</th>
                        <th>حداقل</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($summary as $s): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($s['name']); ?></strong></td>
                            <td><span class="badge badge-success"><?php echo number_format($s['total'], 2); ?></span></td>
                            <td><?php echo number_format($s['avg'], 2); ?></td>
                            <td><?php echo number_format($s['max'], 2); ?></td>
                            <td><?php echo number_format($s['min'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
</div>

<script>
// ========== داده‌های PHP به JavaScript ==========
var branchesData = <?php echo json_encode($branches_comparison, JSON_UNESCAPED_UNICODE); ?>;
var itemsData = <?php echo json_encode($branch_items, JSON_UNESCAPED_UNICODE); ?>;

// ========== توابع کمکی ==========
function processData(data, groupKey, valueKey) {
    var dates = [];
    var groups = {};
    var tempDates = {};
    var tempGroups = {};
    
    for (var i = 0; i < data.length; i++) {
        var d = data[i].report_date || data[i].record_date;
        var g = data[i][groupKey];
        var v = parseFloat(data[i][valueKey]) || 0;
        
        if (!tempDates[d]) {
            tempDates[d] = true;
            dates.push(d);
        }
        if (!tempGroups[g]) {
            tempGroups[g] = true;
            groups[g] = [];
        }
        if (!groups[g]) groups[g] = [];
    }
    
    // مرتب‌سازی تاریخ‌ها
    dates.sort();
    
    // پر کردن داده‌ها
    for (var group in groups) {
        for (var j = 0; j < dates.length; j++) {
            var found = null;
            for (var k = 0; k < data.length; k++) {
                var d = data[k].report_date || data[k].record_date;
                if (d === dates[j] && data[k][groupKey] === group) {
                    found = parseFloat(data[k][valueKey]) || 0;
                    break;
                }
            }
            groups[group].push(found !== null ? found : 0);
        }
    }
    
    return { dates: dates, groups: groups };
}

// ========== رنگ‌ها ==========
var colors = [
    '#d4af37', '#4b8cf7', '#10b981', '#f59e0b', '#8b5cf6', 
    '#ec4899', '#06b6d4', '#84cc16', '#f97316', '#6366f1',
    '#14b8a6', '#e11d48', '#8b5cf6', '#f43f5e', '#0ea5e9'
];

// ========== نمودار شعب ==========
var branchesProcessed = processData(branchesData, 'branch_name', 'total');
var branchesDatasets = [];
var groupNames = Object.keys(branchesProcessed.groups);
var colorIndex = 0;

for (var i = 0; i < groupNames.length; i++) {
    branchesDatasets.push({
        label: groupNames[i],
        data: branchesProcessed.groups[groupNames[i]],
        borderColor: colors[colorIndex % colors.length],
        backgroundColor: colors[colorIndex % colors.length] + '20',
        borderWidth: 2,
        fill: false,
        tension: 0.4,
        pointRadius: 3,
        pointHoverRadius: 6
    });
    colorIndex++;
}

var branchesCtx = document.getElementById('branchesChart').getContext('2d');
var branchesChart = new Chart(branchesCtx, {
    type: '<?php echo $chart_type; ?>',
    data: {
        labels: branchesProcessed.dates,
        datasets: branchesDatasets
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
                bodyFont: { family: 'Vazirmatn' }
            }
        },
        scales: {
            x: {
                ticks: { color: '#8899aa', font: { family: 'Vazirmatn', size: 10 } },
                grid: { color: 'rgba(255,255,255,0.03)' }
            },
            y: {
                ticks: { color: '#8899aa', font: { family: 'Vazirmatn', size: 10 } },
                grid: { color: 'rgba(255,255,255,0.03)' },
                beginAtZero: true
            }
        }
    }
});

// ========== نمودار آیتم‌ها ==========
var itemsProcessed = processData(itemsData, 'name', 'total');
var itemsDatasets = [];
var itemGroupNames = Object.keys(itemsProcessed.groups);
var itemColorIndex = 0;

for (var i = 0; i < itemGroupNames.length; i++) {
    itemsDatasets.push({
        label: itemGroupNames[i],
        data: itemsProcessed.groups[itemGroupNames[i]],
        borderColor: colors[itemColorIndex % colors.length],
        backgroundColor: colors[itemColorIndex % colors.length] + '20',
        borderWidth: 2,
        fill: false,
        tension: 0.4,
        pointRadius: 3,
        pointHoverRadius: 6
    });
    itemColorIndex++;
}

var itemsCtx = document.getElementById('itemsChart').getContext('2d');
var itemsChart = new Chart(itemsCtx, {
    type: '<?php echo $chart_type; ?>',
    data: {
        labels: itemsProcessed.dates,
        datasets: itemsDatasets
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
                bodyFont: { family: 'Vazirmatn' }
            }
        },
        scales: {
            x: {
                ticks: { color: '#8899aa', font: { family: 'Vazirmatn', size: 10 } },
                grid: { color: 'rgba(255,255,255,0.03)' }
            },
            y: {
                ticks: { color: '#8899aa', font: { family: 'Vazirmatn', size: 10 } },
                grid: { color: 'rgba(255,255,255,0.03)' },
                beginAtZero: true
            }
        }
    }
});

// ========== تغییر نوع نمودار ==========
function changeChartType(chartId, type) {
    var chart;
    if (chartId === 'branchesChart') {
        chart = branchesChart;
        // به‌روزرسانی دکمه‌های فعال
        var buttons = document.querySelectorAll('#branchesChart').parentElement.parentElement.querySelectorAll('.chart-type-btn');
        buttons.forEach(function(btn) { btn.classList.remove('active'); });
        event.target.classList.add('active');
    } else {
        chart = itemsChart;
        var buttons = document.querySelectorAll('#itemsChart').parentElement.parentElement.querySelectorAll('.chart-type-btn');
        buttons.forEach(function(btn) { btn.classList.remove('active'); });
        event.target.classList.add('active');
    }
    
    chart.config.type = type;
    
    // تنظیمات خاص برای نمودارهای دایره‌ای
    if (type === 'pie' || type === 'doughnut' || type === 'polarArea') {
        chart.options.scales = {};
        chart.data.datasets.forEach(function(dataset) {
            dataset.backgroundColor = colors.map(function(c) { return c + '80'; });
            dataset.borderColor = colors;
        });
    } else {
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
        chart.data.datasets.forEach(function(dataset) {
            dataset.backgroundColor = dataset.borderColor + '20';
        });
    }
    
    chart.update();
}

// ========== خروجی Excel ==========
function exportToExcel() {
    var tables = document.querySelectorAll('table');
    var html = '<html><head><meta charset="UTF-8"></head><body>';
    
    tables.forEach(function(table) {
        html += table.outerHTML + '<br><br>';
    });
    
    html += '</body></html>';
    
    var blob = new Blob(['\uFEFF' + html], { type: 'application/vnd.ms-excel' });
    var url = URL.createObjectURL(blob);
    var a = document.createElement('a');
    a.href = url;
    a.download = 'dynamic_stats_report.xls';
    a.click();
    URL.revokeObjectURL(url);
}
</script>
</body>
</html>