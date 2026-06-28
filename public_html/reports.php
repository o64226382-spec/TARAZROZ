<?php
define('SECURE_ACCESS', true);
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/jdf.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// ========== توابع کمکی تاریخ ==========
function normalize_shamsi_date($date) {
    $date = str_replace(['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'], 
                        ['0','1','2','3','4','5','6','7','8','9'], $date);
    $date = str_replace('/', '-', $date);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return jdate('Y-m-d');
    }
    return $date;
}

function today_shamsi() {
    $today = jdate('Y-m-d');
    return str_replace(['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'], 
                       ['0','1','2','3','4','5','6','7','8','9'], $today);
}

$user = getCurrentUser();
$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];
$is_admin = ($role === 'admin');
$is_observer = ($role === 'observer');

// دریافت شعب مجاز
$allowedBranches = [];
if ($is_admin) {
    $q = mysqli_query($conn, "SELECT id, branch_name FROM users WHERE role='branch' ORDER BY branch_name");
    while ($row = mysqli_fetch_assoc($q)) {
        $allowedBranches[] = $row;
    }
} elseif ($is_observer) {
    $stmt = mysqli_prepare($conn, "SELECT u.id, u.branch_name FROM observer_assignments oa JOIN users u ON oa.branch_id = u.id WHERE oa.observer_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $r = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($r)) {
        $allowedBranches[] = $row;
    }
    mysqli_stmt_close($stmt);
} else {
    $stmt = mysqli_prepare($conn, "SELECT id, branch_name FROM users WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $r = mysqli_stmt_get_result($stmt);
    if ($row = mysqli_fetch_assoc($r)) {
        $allowedBranches[] = $row;
    }
    mysqli_stmt_close($stmt);
}

// دریافت پارامترها
$report_type = isset($_GET['type']) ? $_GET['type'] : 'daily_income';
$branch_id = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : ($allowedBranches[0]['id'] ?? 0);
$date_from = isset($_GET['from']) ? $_GET['from'] : jdate('Y-m-01');
$date_to = isset($_GET['to']) ? $_GET['to'] : jdate('Y-m-d');
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)jdate('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)jdate('m');

$date_from = normalize_shamsi_date($date_from);
$date_to = normalize_shamsi_date($date_to);
$today_shamsi = today_shamsi();

// ========== دریافت داده ==========
$report_title = '';
$report_data = [];
$columns = [];
$chart_config = [];

function prepareChartData($data, $dateField, $valueField, $branchField = null) {
    $result = [];
    foreach ($data as $row) {
        $date = $row[$dateField];
        $value = is_numeric($row[$valueField]) ? (float)$row[$valueField] : 0;
        $branch = $branchField ? $row[$branchField] : 'کل';
        if (!isset($result[$branch])) $result[$branch] = [];
        if (isset($result[$branch][$date])) {
            $result[$branch][$date] += $value;
        } else {
            $result[$branch][$date] = $value;
        }
    }
    return $result;
}

if ($report_type == 'daily_income') {
    $report_title = 'گزارش درآمد روزانه';
    $columns = ['تاریخ', 'شعبه', 'نوع آیتم', 'مبلغ (ریال)', 'مبلغ (گرم)', 'نرخ طلا'];
    $allowed_ids = array_column($allowedBranches, 'id');
    $branch_list = ($branch_id > 0) ? [$branch_id] : $allowed_ids;
    if (!empty($branch_list)) {
        $placeholders = implode(',', array_fill(0, count($branch_list), '?'));
        $sql = "SELECT dr.record_date, u.branch_name, di.name as item_name, dr.amount_rial, dr.amount_gram, dr.gold_rate
                FROM income_daily_records dr JOIN users u ON dr.branch_id = u.id JOIN income_daily_items di ON dr.item_id = di.id
                WHERE dr.record_date BETWEEN ? AND ? AND dr.branch_id IN ($placeholders) ORDER BY dr.record_date ASC, u.branch_name";
        $stmt = mysqli_prepare($conn, $sql);
        $types = 'ss' . str_repeat('i', count($branch_list));
        $params = array_merge([$date_from, $date_to], $branch_list);
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) $report_data[] = $row;
        mysqli_stmt_close($stmt);
    }
    $chart_data = prepareChartData($report_data, 'record_date', 'amount_rial', 'branch_name');
    $chart_config = ['type' => 'line', 'title' => 'روند درآمد روزانه', 'xTitle' => 'تاریخ', 'yTitle' => 'مبلغ (ریال)', 'series' => []];
    foreach ($chart_data as $branch => $dateValues) {
        $dataPoints = [];
        foreach ($dateValues as $date => $value) $dataPoints[] = ['x' => $date, 'y' => $value];
        $chart_config['series'][] = ['name' => $branch, 'data' => $dataPoints];
    }
} elseif ($report_type == 'monthly_income') {
    $report_title = 'گزارش درآمد ماهانه (طلا)';
    $columns = ['سال', 'ماه', 'شعبه', 'نوع آیتم', 'مبلغ (گرم)'];
    $allowed_ids = array_column($allowedBranches, 'id');
    $branch_list = ($branch_id > 0) ? [$branch_id] : $allowed_ids;
    if (!empty($branch_list)) {
        $placeholders = implode(',', array_fill(0, count($branch_list), '?'));
        $sql = "SELECT mr.record_year, mr.record_month, u.branch_name, mi.name as item_name, mr.amount_gram
                FROM income_monthly_records mr JOIN users u ON mr.branch_id = u.id JOIN income_monthly_items mi ON mr.item_id = mi.id
                WHERE mr.record_year = ? AND mr.record_month = ? AND mr.branch_id IN ($placeholders) ORDER BY u.branch_name, mi.name";
        $stmt = mysqli_prepare($conn, $sql);
        $types = 'ii' . str_repeat('i', count($branch_list));
        $params = array_merge([$year, $month], $branch_list);
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $months_names = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
        while ($row = mysqli_fetch_assoc($result)) {
            $row['record_month'] = $months_names[$row['record_month'] - 1];
            $report_data[] = $row;
        }
        mysqli_stmt_close($stmt);
    }
    $chart_items = [];
    foreach ($report_data as $row) {
        $key = $row['item_name'];
        $chart_items[$key] = ($chart_items[$key] ?? 0) + $row['amount_gram'];
    }
    $chart_config = ['type' => 'bar', 'title' => 'مقایسه مقدار طلا بر اساس آیتم', 'xTitle' => 'آیتم', 'yTitle' => 'مبلغ (گرم)', 'series' => [['name' => 'مقدار طلا', 'data' => array_map(function($k, $v) { return ['x' => $k, 'y' => $v]; }, array_keys($chart_items), array_values($chart_items))]]];
} elseif ($report_type == 'daily_balance') {
    $report_title = 'گزارش تراز روزانه';
    $columns = ['تاریخ', 'شعبه', 'بدهکاران (م.ریال)', 'بستانکاران (م.ریال)', 'تنخواه (م.ریال)', 'بنکداران (گرم)', 'داینامیک (گرم)', 'اختلاف (م.ریال)'];
    $allowed_ids = array_column($allowedBranches, 'id');
    $branch_list = ($branch_id > 0) ? [$branch_id] : $allowed_ids;
    if (!empty($branch_list)) {
        $placeholders = implode(',', array_fill(0, count($branch_list), '?'));
        $sql = "SELECT dr.report_date, u.branch_name, dr.report_data, dr.id FROM daily_reports dr JOIN users u ON dr.user_id = u.id
                WHERE dr.report_date BETWEEN ? AND ? AND u.id IN ($placeholders) ORDER BY dr.report_date ASC, u.branch_name";
        $stmt = mysqli_prepare($conn, $sql);
        $types = 'ss' . str_repeat('i', count($branch_list));
        $params = array_merge([$date_from, $date_to], $branch_list);
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $data = json_decode($row['report_data'], true);
            $debtors = round(array_sum(array_column($data['debtors'] ?? [], 'amt')), 2);
            $creditors = round(array_sum(array_column($data['creditors'] ?? [], 'amt')), 2);
            $petty = round(array_sum(array_column($data['pettys'] ?? [], 'amt')), 2);
            $bankers = round(array_sum(array_column($data['bankers'] ?? [], 'amt')), 2);
            $dynamic = 0;
            $qd = mysqli_prepare($conn, "SELECT SUM(amount_gram) as t FROM dynamic_records WHERE report_id = ?");
            mysqli_stmt_bind_param($qd, "i", $row['id']);
            mysqli_stmt_execute($qd);
            $rd = mysqli_fetch_assoc(mysqli_stmt_get_result($qd));
            if ($rd && $rd['t']) $dynamic = round($rd['t'], 3);
            mysqli_stmt_close($qd);
            $diff = round($creditors - $debtors, 2);
            $report_data[] = ['report_date' => $row['report_date'], 'branch_name' => $row['branch_name'], 'debtors' => $debtors, 'creditors' => $creditors, 'petty' => $petty, 'bankers' => $bankers, 'dynamic' => $dynamic, 'difference' => $diff];
        }
        mysqli_stmt_close($stmt);
    }
    $chart_config = ['type' => 'area', 'title' => 'روند تراز روزانه', 'xTitle' => 'تاریخ', 'yTitle' => 'مبلغ (میلیون ریال)', 'series' => [['name' => 'بستانکاران', 'data' => []], ['name' => 'بدهکاران', 'data' => []], ['name' => 'اختلاف', 'data' => []]]];
    foreach ($report_data as $row) {
    $chart_config['series'][0]['data'][] = ['x' => $row['report_date'], 'y' => $row['creditors'] / 1000000];
    $chart_config['series'][1]['data'][] = ['x' => $row['report_date'], 'y' => $row['debtors'] / 1000000];
    $chart_config['series'][2]['data'][] = ['x' => $row['report_date'], 'y' => $row['difference'] / 1000000];
}
} elseif ($report_type == 'dynamic_items') {
    $report_title = 'گزارش آیتم‌های داینامیک';
    $columns = ['تاریخ', 'شعبه', 'آیتم', 'مقدار (گرم)'];
    $allowed_ids = array_column($allowedBranches, 'id');
    $branch_list = ($branch_id > 0) ? [$branch_id] : $allowed_ids;
    if (!empty($branch_list)) {
        $placeholders = implode(',', array_fill(0, count($branch_list), '?'));
        $sql = "SELECT dr.report_date, u.branch_name, di.name as item_name, drec.amount_gram
                FROM dynamic_records drec JOIN daily_reports dr ON drec.report_id = dr.id JOIN users u ON dr.user_id = u.id JOIN dynamic_items di ON drec.item_id = di.id
                WHERE dr.report_date BETWEEN ? AND ? AND u.id IN ($placeholders) ORDER BY dr.report_date DESC, u.branch_name";
        $stmt = mysqli_prepare($conn, $sql);
        $types = 'ss' . str_repeat('i', count($branch_list));
        $params = array_merge([$date_from, $date_to], $branch_list);
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) $report_data[] = $row;
        mysqli_stmt_close($stmt);
    }
    $raw_data = [];
    foreach ($report_data as $row) {
        $date = $row['report_date']; $item = $row['item_name']; $amount = (float)$row['amount_gram'];
        if (!isset($raw_data[$item])) $raw_data[$item] = [];
        if (isset($raw_data[$item][$date])) { $raw_data[$item][$date] += $amount; } else { $raw_data[$item][$date] = $amount; }
    }
                $series = [];
    foreach ($raw_data as $item => $dateValues) {
        ksort($dateValues);
        $dataPoints = [];
        foreach ($dateValues as $date => $value) {
            $dataPoints[] = ['x' => $date, 'y' => round($value, 3)];
        }
        $series[] = ['name' => $item, 'data' => $dataPoints];
    }
    $chart_config = ['type' => 'line', 'title' => 'روند تغییرات آیتم‌های داینامیک', 'xTitle' => 'تاریخ', 'yTitle' => 'مقدار (گرم)', 'series' => $series];
} elseif ($report_type == 'goals_progress') {
    $report_title = 'گزارش پیشرفت اهداف';
    $columns = ['تاریخ', 'شعبه', 'نوع هدف', 'مقدار پیشرفت', 'واحد'];
    $allowed_ids = array_column($allowedBranches, 'id');
    $branch_list = ($branch_id > 0) ? [$branch_id] : $allowed_ids;
    if (!empty($branch_list)) {
        $placeholders = implode(',', array_fill(0, count($branch_list), '?'));
        $sql = "SELECT gdp.progress_date, u.branch_name, gt.name as goal_name, gdp.achieved_value, gt.unit
                FROM goal_daily_progress gdp JOIN users u ON gdp.branch_id = u.id JOIN goal_types gt ON gdp.goal_type_id = gt.id
                WHERE gdp.progress_date BETWEEN ? AND ? AND gdp.branch_id IN ($placeholders) ORDER BY gdp.progress_date DESC, u.branch_name";
        $stmt = mysqli_prepare($conn, $sql);
        $types = 'ss' . str_repeat('i', count($branch_list));
        $params = array_merge([$date_from, $date_to], $branch_list);
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $unitText = $row['unit'] == 'gram' ? 'گرم' : 'میلیون ریال';
            $report_data[] = ['progress_date' => $row['progress_date'], 'branch_name' => $row['branch_name'], 'goal_name' => $row['goal_name'], 'achieved_value' => round($row['achieved_value'], 3), 'unit' => $unitText];
        }
        mysqli_stmt_close($stmt);
    }
    $chart_branches_rial = []; $chart_branches_gram = []; $chart_goals_rial = []; $chart_goals_gram = [];
    foreach ($report_data as $row) {
        $branch = $row['branch_name']; $goal = $row['goal_name']; $value = $row['achieved_value']; $is_gram = ($row['unit'] == 'گرم');
        if ($is_gram) { if (!in_array($goal, $chart_goals_gram)) $chart_goals_gram[] = $goal; $chart_branches_gram[$branch][$goal] = ($chart_branches_gram[$branch][$goal] ?? 0) + $value; }
        else { if (!in_array($goal, $chart_goals_rial)) $chart_goals_rial[] = $goal; $chart_branches_rial[$branch][$goal] = ($chart_branches_rial[$branch][$goal] ?? 0) + $value; }
    }
    $series_rial = []; foreach ($chart_goals_rial as $goal) { $data = []; foreach ($chart_branches_rial as $branch => $goals) $data[] = ['x' => $branch, 'y' => round($goals[$goal] ?? 0, 2)]; $series_rial[] = ['name' => $goal, 'data' => $data]; }
    $series_gram = []; foreach ($chart_goals_gram as $goal) { $data = []; foreach ($chart_branches_gram as $branch => $goals) $data[] = ['x' => $branch, 'y' => round($goals[$goal] ?? 0, 3)]; $series_gram[] = ['name' => $goal, 'data' => $data]; }
    $chart_config = ['dual' => true, 'rial' => ['type' => 'bar', 'title' => 'پیشرفت اهداف ریالی', 'xTitle' => 'شعبه', 'yTitle' => 'مقدار (میلیون ریال)', 'series' => $series_rial], 'gram' => ['type' => 'bar', 'title' => 'پیشرفت اهداف گرمی', 'xTitle' => 'شعبه', 'yTitle' => 'مقدار (گرم)', 'series' => $series_gram]];
}

// ========== دانلود اکسل ==========
if ($is_admin && isset($_GET['excel']) && $_GET['excel'] == '1') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . str_replace(' ', '_', $report_title) . '_' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($output, $columns);
    foreach ($report_data as $row) fputcsv($output, array_values($row));
    fclose($output);
    exit;
}

$months_list = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
$report_types = ['daily_income' => 'درآمد روزانه', 'monthly_income' => 'درآمد ماهانه', 'daily_balance' => 'تراز روزانه', 'dynamic_items' => 'آیتم‌های داینامیک', 'goals_progress' => 'پیشرفت اهداف'];
$theme = isset($_COOKIE['theme']) ? $_COOKIE['theme'] : 'dark';
$report_data_json = json_encode($report_data, JSON_UNESCAPED_UNICODE);
$columns_json = json_encode($columns, JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ریز گزارش‌ها | <?php echo htmlspecialchars($report_title); ?></title>
    <link href="assets/fonts/fonts.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/@majidh1/jalalidatepicker/dist/jalalidatepicker.min.css">
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <style>
        :root { --bg-main: #0a0f1a; --bg-card: rgba(255,255,255,0.05); --border: rgba(255,255,255,0.08); --text: #e8ecf1; --text-secondary: #94a3b8; --accent: #d4af37; --accent-bg: rgba(212,175,55,0.1); --green: #10b981; --red: #ef4444; --radius: 16px; }
        body.light { --bg-main: #f5f7fa; --bg-card: rgba(255,255,255,0.9); --border: rgba(0,0,0,0.08); --text: #1e293b; --text-secondary: #64748b; --accent-bg: rgba(180,83,9,0.08); }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Vazirmatn', sans-serif; background: var(--bg-main); color: var(--text); padding: 20px; min-height: 100vh; transition: all 0.3s ease; }
        .container { max-width: 1400px; margin: 0 auto; }
        .header { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); padding: 20px; margin-bottom: 20px; backdrop-filter: blur(12px); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
        h1 { font-size: 1.3rem; background: linear-gradient(135deg, #fcd34d, #d4af37); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-weight: 700; }
        .filters { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); padding: 20px; margin-bottom: 20px; display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end; }
        .filter-group { display: flex; flex-direction: column; gap: 5px; flex: 1; min-width: 150px; }
        .filter-group label { font-size: 0.75rem; color: var(--text-secondary); font-weight: 600; }
        .filter-group select, .filter-group input { padding: 10px 14px; border-radius: 10px; background: rgba(0,0,0,0.2); border: 1px solid var(--border); color: var(--text); font-family: 'Vazirmatn'; font-size: 0.85rem; cursor: pointer; }
        .filter-group input { width: 100%; cursor: text; }
        .btn { padding: 10px 20px; border-radius: 10px; border: none; cursor: pointer; font-family: 'Vazirmatn'; font-weight: 700; font-size: 0.85rem; transition: all 0.2s; white-space: nowrap; }
        .btn-primary { background: linear-gradient(135deg, #d4af37, #fcd34d); color: #1a1a1a; }
        .btn-excel { background: var(--green); color: white; }
        .btn-print { background: #3b82f6; color: white; }
        .btn-reset { background: var(--red); color: white; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.2); }
        .table-toolbar { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 15px; align-items: center; }
        .search-box { flex: 1; min-width: 250px; position: relative; }
        .search-box input { width: 100%; padding: 10px 14px 10px 40px; border-radius: 10px; background: rgba(0,0,0,0.2); border: 1px solid var(--border); color: var(--text); font-family: 'Vazirmatn'; font-size: 0.85rem; }
        .search-box .search-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-secondary); font-size: 1.1rem; }
        .chart-wrapper { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); padding: 30px 40px; margin-bottom: 20px; backdrop-filter: blur(8px); }
        #mainChart, #mainChartRial, #mainChartGram { min-height: 400px; width: 100%; }
        .table-wrapper { overflow-x: auto; background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); padding: 15px; backdrop-filter: blur(8px); }
        table { width: 100%; border-collapse: collapse; min-width: 800px; font-size: 0.85rem; }
        th, td { padding: 12px 10px; text-align: center; border-bottom: 1px solid var(--border); }
        th { background: var(--accent-bg); color: var(--accent); font-weight: 700; cursor: pointer; user-select: none; white-space: nowrap; }
        th .sort-icon { font-size: 0.7rem; margin-right: 5px; opacity: 0.5; }
        th.sort-asc .sort-icon, th.sort-desc .sort-icon { opacity: 1; color: var(--accent); }
        th.sort-asc .sort-icon::after { content: ' ▲'; }
        th.sort-desc .sort-icon::after { content: ' ▼'; }
        tr:hover td { background: rgba(212,175,55,0.03); }
        tr.filtered-out { display: none; }
        .stats { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); padding: 15px; margin-top: 20px; text-align: center; font-weight: 600; }
        .back-link { color: var(--accent); text-decoration: none; font-weight: 600; font-size: 0.9rem; }
        .theme-btn { background: rgba(255,255,255,0.05); border: 1px solid var(--border); border-radius: 30px; width: 40px; height: 40px; cursor: pointer; font-size: 1.2rem; display: flex; align-items: center; justify-content: center; }
        @media (max-width: 600px) { body { padding: 10px; } .filter-group { min-width: 100%; } table { font-size: 0.7rem; } th, td { padding: 8px 5px; } }
    </style>
</head>
<body class="<?php echo htmlspecialchars($theme); ?>">
<div class="container">
    <div class="header">
        <div><a href="index.php" class="back-link">بازگشت به صفحه اصلی</a><h1 style="margin-top: 10px;"><?php echo htmlspecialchars($report_title); ?></h1></div>
        <div style="display: flex; gap: 10px;">
            <?php if ($is_admin): ?><button class="btn btn-excel" onclick="downloadExcel()">دانلود اکسل</button><button class="btn btn-print" onclick="window.print()">چاپ</button><?php endif; ?>
            <button class="theme-btn" onclick="toggleTheme()" title="تغییر تم"><?php echo $theme == 'light' ? '🌙' : '☀️'; ?></button>
        </div>
    </div>
    <?php if (!empty($chart_config) && isset($chart_config['dual'])): ?>
        <?php if (!empty($chart_config['rial']['series'])): ?><div class="chart-wrapper"><h3 style="color:var(--accent);text-align:center;"><?php echo htmlspecialchars($chart_config['rial']['title']); ?></h3><div id="mainChartRial"></div></div><?php endif; ?>
        <?php if (!empty($chart_config['gram']['series'])): ?><div class="chart-wrapper"><h3 style="color:var(--accent);text-align:center;"><?php echo htmlspecialchars($chart_config['gram']['title']); ?></h3><div id="mainChartGram"></div></div><?php endif; ?>
    <?php elseif (!empty($chart_config) && !empty($chart_config['series'])): ?>
        <div class="chart-wrapper"><div id="mainChart"></div></div>
    <?php endif; ?>
    <form method="GET" class="filters">
        <div class="filter-group"><label>نوع گزارش</label><select name="type" onchange="this.form.submit()"><?php foreach ($report_types as $key => $name): ?><option value="<?php echo $key; ?>" <?php echo $key == $report_type ? 'selected' : ''; ?>><?php echo $name; ?></option><?php endforeach; ?></select></div>
        <?php if (count($allowedBranches) > 1): ?><div class="filter-group"><label>انتخاب شعبه</label><select name="branch_id" onchange="this.form.submit()"><option value="0">همه شعب</option><?php foreach ($allowedBranches as $branch): ?><option value="<?php echo (int)$branch['id']; ?>" <?php echo $branch['id'] == $branch_id ? 'selected' : ''; ?>><?php echo htmlspecialchars($branch['branch_name']); ?></option><?php endforeach; ?></select></div><?php endif; ?>
        <?php if ($report_type != 'monthly_income'): ?>
            <div class="filter-group"><label>از تاریخ</label><input type="text" name="from" value="<?php echo htmlspecialchars($date_from); ?>" placeholder="1405-03-01" data-persian-datepicker autocomplete="off"></div>
            <div class="filter-group"><label>تا تاریخ</label><input type="text" name="to" value="<?php echo htmlspecialchars($date_to); ?>" placeholder="1405-03-31" data-persian-datepicker autocomplete="off"></div>
        <?php else: ?>
            <div class="filter-group"><label>سال</label><select name="year" onchange="this.form.submit()"><?php for($y=1400;$y<=1410;$y++): ?><option value="<?php echo $y; ?>" <?php echo $y==$year?'selected':''; ?>><?php echo $y; ?></option><?php endfor; ?></select></div>
            <div class="filter-group"><label>ماه</label><select name="month" onchange="this.form.submit()"><?php for($m=1;$m<=12;$m++): ?><option value="<?php echo $m; ?>" <?php echo $m==$month?'selected':''; ?>><?php echo $months_list[$m-1]; ?></option><?php endfor; ?></select></div>
        <?php endif; ?>
        <button type="submit" class="btn btn-primary">نمایش</button>
    </form>
    <div class="table-wrapper">
        <div class="table-toolbar">
            <div class="search-box"><span class="search-icon">🔍</span><input type="text" id="globalSearch" placeholder="جستجو در تمام ستون‌ها..." onkeyup="applyAllFilters()"></div>
            <button class="btn btn-reset" onclick="resetAllFilters()">حذف همه فیلترها</button>
            <span style="color:var(--text-secondary);font-size:0.8rem;">نمایش: <strong id="visibleCount"><?php echo count($report_data); ?></strong> از <?php echo count($report_data); ?></span>
        </div>
        <div class="active-filters" id="activeFilters"></div>
        <table id="reportTable">
            <thead><tr><?php foreach ($columns as $colIndex => $col): ?><th data-sortable="true" data-col="<?php echo $colIndex; ?>"><?php echo htmlspecialchars($col); ?> <span class="sort-icon">↑↓</span></th><?php endforeach; ?></tr></thead>
            <tbody>
                <?php if (empty($report_data)): ?>
                    <tr><td colspan="<?php echo count($columns); ?>" style="text-align:center;padding:40px;color:var(--text-secondary);">داده‌ای یافت نشد</td></tr>
                <?php else: ?>
                    <?php foreach ($report_data as $rowIndex => $row): ?><tr data-row="<?php echo $rowIndex; ?>"><?php $colCounter=0; foreach ($row as $key => $value): $rawValue=is_string($value)?str_replace(',','',$value):$value; $isGramColumn=false; if(isset($columns[$colCounter])){$columnTitle=$columns[$colCounter]; if(strpos($columnTitle,'گرم')!==false||strpos($columnTitle,'(گرم)')!==false)$isGramColumn=true;} $displayValue=''; if(is_numeric($rawValue)){if($isGramColumn)$displayValue=number_format((float)$rawValue,3); else $displayValue=number_format((float)$rawValue);} else $displayValue=htmlspecialchars($value); ?><td data-col="<?php echo $colCounter; ?>" data-value="<?php echo htmlspecialchars(strip_tags((string)$rawValue)); ?>"><?php echo $displayValue; ?></td><?php $colCounter++; endforeach; ?></tr><?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="stats"><span>تعداد کل رکوردها: <strong><?php echo number_format(count($report_data)); ?></strong></span></div>
</div>
<script src="https://unpkg.com/@majidh1/jalalidatepicker/dist/jalalidatepicker.min.js"></script>
<script src="assets/js/persian-datepicker.js"></script>
<script>
const reportData = <?php echo $report_data_json; ?>;
const columnNames = <?php echo $columns_json; ?>;
let sortState = { col: -1, asc: true };
let columnFilters = {};
let globalSearchTerm = '';

function toggleTheme() { var b=document.body; if(b.classList.contains('light')){b.classList.remove('light');document.cookie="theme=dark;path=/;max-age="+(365*24*60*60);} else {b.classList.add('light');document.cookie="theme=light;path=/;max-age="+(365*24*60*60);} location.reload(); }
function downloadExcel() { const u=new URLSearchParams(window.location.search); u.set('excel','1'); window.location.href='?'+u.toString(); }

<?php if (!empty($chart_config)): ?>
document.addEventListener('DOMContentLoaded', function() {
    const chartConfig = <?php echo json_encode($chart_config, JSON_UNESCAPED_UNICODE); ?>;
    function renderBarChart(elementId, config, colors) {
        if (!document.getElementById(elementId)) return;
        new ApexCharts(document.getElementById(elementId), {
            chart: { type: 'bar', height: 450, stacked: false, toolbar: { show: false }, fontFamily: 'Vazirmatn, sans-serif' },
            series: config.series, colors: colors,
            title: { text: config.title, align: 'center', style: { color: 'var(--text)', fontSize: '16px' } },
            xaxis: { title: { text: config.xTitle, style: { color: 'var(--text-secondary)' } }, labels: { style: { colors: 'var(--text-secondary)', fontSize: '12px' }, rotate: -45 } },
            yaxis: { title: { text: config.yTitle, style: { color: 'var(--text-secondary)' } }, labels: { style: { colors: 'var(--text-secondary)' }, formatter: v => v.toLocaleString('fa-IR') } },
            plotOptions: { bar: { columnWidth: '70%', borderRadius: 6, dataLabels: { position: 'top' } } },
            dataLabels: { enabled: true, formatter: v => v.toLocaleString('fa-IR'), offsetY: -20, style: { fontSize: '11px', colors: ['var(--text)'] } },
            grid: { borderColor: 'var(--border)', padding: { left: 20, right: 40, top: 20, bottom: 20 } },
            legend: { position: 'bottom', horizontalAlign: 'center', labels: { colors: 'var(--text)' } },
            tooltip: { theme: '<?php echo $theme; ?>', y: { formatter: v => v.toLocaleString('fa-IR') } }
        }).render();
    }
    if (chartConfig.dual) {
        if (chartConfig.rial?.series?.length) renderBarChart('mainChartRial', chartConfig.rial, ['#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6','#ec4899']);
        if (chartConfig.gram?.series?.length) renderBarChart('mainChartGram', chartConfig.gram, ['#d4af37','#f97316','#14b8a6','#6366f1','#84cc16','#06b6d4']);
        return;
    }
    if (!document.getElementById('mainChart')) return;
    let chartType = chartConfig.type || 'line';
    let chartOptions = {
        chart: { type: chartType, height: 450, stacked: false, fontFamily: 'Vazirmatn, sans-serif', toolbar: { show: <?php echo $is_admin?'true':'false'; ?> }, export: { csv: { show: <?php echo $is_admin?'true':'false'; ?> }, svg: { show: <?php echo $is_admin?'true':'false'; ?> }, png: { show: <?php echo $is_admin?'true':'false'; ?> } } },
        defaultLocale: 'fa',
        title: { text: chartConfig.title, align: 'center', style: { color: 'var(--text)', fontSize: '16px' } },
        stroke: { curve: 'straight', width: 2 },
        xaxis: { title: { text: chartConfig.xTitle, style: { color: 'var(--text-secondary)' } }, labels: { style: { colors: 'var(--text-secondary)' } } },
        yaxis: { title: { text: chartConfig.yTitle, style: { color: 'var(--text-secondary)' } }, labels: { style: { colors: 'var(--text-secondary)' }, formatter: v => chartConfig.title?.includes('آیتم‌های داینامیک') ? v.toLocaleString('fa-IR',{minimumFractionDigits:3,maximumFractionDigits:3}) : v.toLocaleString('fa-IR') } },
        tooltip: { theme: '<?php echo $theme; ?>', y: { formatter: v => chartConfig.title?.includes('آیتم‌های داینامیک') ? v.toLocaleString('fa-IR',{minimumFractionDigits:3,maximumFractionDigits:3})+' گرم' : v.toLocaleString('fa-IR') } },
        grid: { borderColor: 'var(--border)' },
        noData: { text: 'داده‌ای موجود نیست', align: 'center', style: { color: 'var(--text-secondary)' } },
        legend: { position: 'top', labels: { colors: 'var(--text)' } }
    };
    if (chartConfig.type === 'pie' || chartConfig.type === 'donut') {
        chartOptions.series = chartConfig.series.map(s => s.value);
        chartOptions.labels = chartConfig.series.map(s => s.name);
    } else {
        chartOptions.series = chartConfig.series;
        if (chartConfig.type === 'bar') {
            chartOptions.plotOptions = { bar: { columnWidth: '70%', borderRadius: 6, dataLabels: { position: 'top' } } };
            chartOptions.dataLabels = { enabled: true, formatter: v => v.toLocaleString('fa-IR'), offsetY: -20, style: { fontSize: '10px', colors: ['var(--text)'] } };
        }
    }
    new ApexCharts(document.getElementById('mainChart'), chartOptions).render();
});
<?php endif; ?>
</script>
</body>
</html>