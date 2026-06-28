<?php
define('SECURE_ACCESS', true);
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/jdf.php';

if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

$user = getCurrentUser();
$role = $user['role'];
$userId = $_SESSION['user_id'];

// دریافت تاریخ از URL یا استفاده از تاریخ امروز
$selectedDate = isset($_GET['date']) ? $_GET['date'] : jdate('Y-m-d');
// تبدیل تاریخ شمسی به فرمت صحیح (1405-03-05)
$selectedDate = str_replace('/', '-', $selectedDate);

// تعیین شعبه (برای ناظر و ادمین)
$branchId = $userId;
if ($role === 'observer' || $role === 'admin') {
    $branchId = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : $userId;
}

// دریافت لیست شعب برای ناظر و ادمین
$branchesList = [];
if ($role === 'observer') {
    $q = mysqli_query($conn, "SELECT u.id, u.branch_name FROM observer_assignments oa JOIN users u ON oa.branch_id = u.id WHERE oa.observer_id = $userId");
    while ($row = mysqli_fetch_assoc($q)) {
        $branchesList[] = $row;
    }
} elseif ($role === 'admin') {
    $q = mysqli_query($conn, "SELECT id, branch_name FROM users WHERE role = 'branch' ORDER BY branch_name");
    while ($row = mysqli_fetch_assoc($q)) {
        $branchesList[] = $row;
    }
}

// دریافت نام شعبه جاری
$branchName = '';
$q = mysqli_query($conn, "SELECT branch_name FROM users WHERE id = $branchId");
if ($row = mysqli_fetch_assoc($q)) $branchName = $row['branch_name'];

// استخراج سال و ماه از تاریخ انتخاب شده
$dateParts = explode('-', $selectedDate);
$selectedYear = (int)$dateParts[0];
$selectedMonth = (int)$dateParts[1];
$selectedDay = (int)$dateParts[2];

// دریافت انواع اهداف
$goalTypes = [];
$q = mysqli_query($conn, "SELECT * FROM goal_types WHERE is_active = 1 ORDER BY sort_order");
while ($row = mysqli_fetch_assoc($q)) {
    $goalTypes[] = $row;
}

// دریافت اهداف شعبه برای ماه جاری
$branchGoals = [];
$q = mysqli_query($conn, "SELECT goal_type_id, target_value FROM branch_goals WHERE branch_id = $branchId AND year = $selectedYear AND month = $selectedMonth");
while ($row = mysqli_fetch_assoc($q)) {
    $branchGoals[$row['goal_type_id']] = $row['target_value'];
}

// دریافت پیشرفت تا امروز (برای هر هدف)
$progressData = [];
foreach ($goalTypes as $goal) {
    $target = isset($branchGoals[$goal['id']]) ? (float)$branchGoals[$goal['id']] : 0;
    
    // مجموع پیشرفت از ابتدای ماه تا تاریخ انتخاب شده
    $startDate = sprintf("%04d-%02d-01", $selectedYear, $selectedMonth);
    $endDate = sprintf("%04d-%02d-%02d", $selectedYear, $selectedMonth, $selectedDay);
    
    $q = mysqli_query($conn, "SELECT COALESCE(SUM(achieved_value), 0) as total 
                              FROM goal_daily_progress 
                              WHERE branch_id = $branchId 
                              AND goal_type_id = {$goal['id']}
                              AND progress_date BETWEEN '$startDate' AND '$endDate'");
    $row = mysqli_fetch_assoc($q);
    $achieved = round($row['total'] ?? 0, 3);
    
    // پیشرفت امروز (برای نمایش در فرم)
    $todayProgress = 0;
    $q2 = mysqli_query($conn, "SELECT achieved_value 
                               FROM goal_daily_progress 
                               WHERE branch_id = $branchId 
                               AND goal_type_id = {$goal['id']}
                               AND progress_date = '$endDate'");
    if ($row2 = mysqli_fetch_assoc($q2)) {
        $todayProgress = $row2['achieved_value'];
    }
    
    $progressData[$goal['id']] = [
        'target' => $target,
        'achieved' => $achieved,
        'today_progress' => $todayProgress,
        'percentage' => $target > 0 ? round(($achieved / $target) * 100, 1) : 0,
        'remaining' => max(0, $target - $achieved)
    ];
}

// پردازش ثبت پیشرفت روزانه
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_progress'])) {
    $progressDate = $_POST['progress_date'];
    $branchIdPost = (int)$_POST['branch_id'];
    
    foreach ($_POST['progress'] as $goalTypeId => $value) {
        $value = (float)$value;
        if ($value > 0) {
            $check = mysqli_query($conn, "SELECT id FROM goal_daily_progress 
                                          WHERE branch_id = $branchIdPost 
                                          AND goal_type_id = $goalTypeId 
                                          AND progress_date = '$progressDate'");
            if (mysqli_num_rows($check) > 0) {
                mysqli_query($conn, "UPDATE goal_daily_progress SET achieved_value = $value 
                                      WHERE branch_id = $branchIdPost 
                                      AND goal_type_id = $goalTypeId 
                                      AND progress_date = '$progressDate'");
            } else {
                mysqli_query($conn, "INSERT INTO goal_daily_progress (branch_id, goal_type_id, achieved_value, progress_date, created_by) 
                                      VALUES ($branchIdPost, $goalTypeId, $value, '$progressDate', $userId)");
            }
        }
    }
    header("Location: index.php?date=$selectedDate&branch_id=$branchId&saved=1");
    exit;
}

$months = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
?>

<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <title>مدیریت اهداف روزانه</title>
    <link href="../assets/fonts/fonts.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Vazirmatn', sans-serif; background: #0a0f1a; color: #e8ecf1; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        .card { background: rgba(255,255,255,0.05); border-radius: 16px; padding: 20px; margin-bottom: 20px; border: 1px solid rgba(255,255,255,0.1); }
        h1 { font-size: 1.3rem; background: linear-gradient(135deg, #d4af37, #fcd34d); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin-bottom: 20px; }
        .filters { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 20px; align-items: center; }
        .filters select, .filters input, .filters button { padding: 8px 12px; border-radius: 8px; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: #fff; font-family: 'Vazirmatn'; }
        .filters button { background: linear-gradient(135deg, #d4af37, #fcd34d); color: #1a1a1a; border: none; font-weight: 700; cursor: pointer; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 8px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
        th { color: #d4af37; }
        .goal-input { width: 100px; padding: 6px; border-radius: 6px; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.2); color: #fff; text-align: center; }
        .btn-save { background: #10b981; border: none; padding: 8px 16px; border-radius: 8px; color: #fff; cursor: pointer; font-weight: 700; }
        .btn-back { background: rgba(255,255,255,0.1); padding: 8px 16px; border-radius: 8px; text-decoration: none; color: #fff; display: inline-block; margin-bottom: 20px; }
        .success-msg { background: rgba(16,185,129,0.2); border: 1px solid #10b981; padding: 12px; border-radius: 10px; margin-bottom: 20px; text-align: center; }
        .progress-bar { width: 80px; height: 6px; background: rgba(255,255,255,0.1); border-radius: 3px; overflow: hidden; display: inline-block; }
        .progress-fill { height: 100%; background: linear-gradient(90deg, #d4af37, #fcd34d); border-radius: 3px; }
        @media (max-width: 600px) { .goal-input { width: 70px; } th, td { font-size: 0.7rem; padding: 8px 4px; } }
    </style>
</head>
<body>
<div class="container">
    <a href="../index.php" class="btn-back">← بازگشت به صفحه اصلی</a>
    
    <div class="card">
        <h1>🎯 ثبت پیشرفت روزانه اهداف</h1>
        
        <?php if (isset($_GET['saved'])): ?>
        <div class="success-msg">✅ پیشرفت با موفقیت ثبت شد</div>
        <?php endif; ?>
        
        <div class="filters">
            <div>
                <label>📅 تاریخ:</label>
                <input type="text" id="dateInput" value="<?php echo $selectedDate; ?>" onchange="changeDate()">
            </div>
            
            <?php if (count($branchesList) > 1): ?>
            <div>
                <label>🏢 شعبه:</label>
                <select id="branchSelect" onchange="changeBranch()">
                    <?php foreach ($branchesList as $b): ?>
                        <option value="<?php echo $b['id']; ?>" <?php echo $b['id'] == $branchId ? 'selected' : ''; ?>><?php echo htmlspecialchars($b['branch_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            
            <div>
                <span style="color: #94a3b8;">📍 <?php echo htmlspecialchars($branchName); ?></span>
            </div>
        </div>
        
        <form method="POST">
            <input type="hidden" name="progress_date" value="<?php echo $selectedDate; ?>">
            <input type="hidden" name="branch_id" value="<?php echo $branchId; ?>">
            
            <table>
                <thead>
                    <tr>
                        <th>نوع هدف</th>
                        <th>هدف ماهانه</th>
                        <th>پیشرفت تا امروز</th>
                        <th>پیشرفت امروز</th>
                        <th>باقی‌مانده</th>
                        <th>ثبت</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($goalTypes as $goal): 
                        $data = $progressData[$goal['id']];
                        if ($data['target'] == 0) continue;
                        $unitText = $goal['unit'] == 'gram' ? 'گرم' : 'میلیون ریال';
                        $barWidth = min($data['percentage'], 100);
                    ?>
                    <tr>
                        <td><span style="font-size:1.2rem;"><?php echo $goal['icon']; ?></span> <?php echo htmlspecialchars($goal['name']); ?></td>
                        <td><?php echo number_format($data['target']); ?> <?php echo $unitText; ?></td>
                        <td>
                            <div style="display:flex; align-items:center; gap:5px;">
                                <span><?php echo number_format($data['achieved']); ?></span>
                                <div class="progress-bar"><div class="progress-fill" style="width: <?php echo $barWidth; ?>%;"></div></div>
                                <span style="font-size:0.7rem;">(<?php echo $data['percentage']; ?>%)</span>
                            </div>
                        </td>
                        <td><input type="number" step="0.001" class="goal-input" name="progress[<?php echo $goal['id']; ?>]" value="<?php echo $data['today_progress']; ?>"></td>
                        <td><?php echo number_format($data['remaining']); ?> <?php echo $unitText; ?></td>
                        <td><button type="submit" name="save_progress" class="btn-save">💾</button></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </form>
    </div>
</div>

<script>
function changeDate() {
    var date = document.getElementById('dateInput').value;
    var url = 'index.php?date=' + date;
    <?php if (count($branchesList) > 1): ?>
    url += '&branch_id=' + document.getElementById('branchSelect').value;
    <?php endif; ?>
    window.location.href = url;
}

function changeBranch() {
    var branchId = document.getElementById('branchSelect').value;
    var date = document.getElementById('dateInput').value;
    window.location.href = 'index.php?date=' + date + '&branch_id=' + branchId;
}
</script>
</body>
</html>