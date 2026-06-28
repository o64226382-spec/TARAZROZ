<?php
define('SECURE_ACCESS', true);
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireAdmin();
require_once '../includes/jdf.php';

// دریافت انواع اهداف
$goalTypes = [];
$q = mysqli_query($conn, "SELECT * FROM goal_types WHERE is_active = 1 ORDER BY sort_order");
while ($row = mysqli_fetch_assoc($q)) {
    $goalTypes[] = $row;
}

// دریافت شعب
$branches = [];
$q = mysqli_query($conn, "SELECT id, branch_name FROM users WHERE role = 'branch' ORDER BY branch_name");
while ($row = mysqli_fetch_assoc($q)) {
    $branches[] = $row;
}

$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)jdate('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)jdate('m');

// ذخیره اهداف
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_all'])) {
    $year = (int)$_POST['year'];
    $month = (int)$_POST['month'];
    
    foreach ($_POST['goals'] as $branchId => $branchGoals) {
        $branchId = (int)$branchId;
        foreach ($branchGoals as $goalTypeId => $targetValue) {
            $goalTypeId = (int)$goalTypeId;
            $targetValue = (float)$targetValue;
            
            if ($targetValue > 0) {
                $check = mysqli_query($conn, "SELECT id FROM branch_goals WHERE branch_id = $branchId AND goal_type_id = $goalTypeId AND year = $year AND month = $month");
                if (mysqli_num_rows($check) > 0) {
                    mysqli_query($conn, "UPDATE branch_goals SET target_value = $targetValue WHERE branch_id = $branchId AND goal_type_id = $goalTypeId AND year = $year AND month = $month");
                } else {
                    mysqli_query($conn, "INSERT INTO branch_goals (branch_id, goal_type_id, target_value, year, month, created_by) VALUES ($branchId, $goalTypeId, $targetValue, $year, $month, {$_SESSION['user_id']})");
                }
            } else {
                mysqli_query($conn, "DELETE FROM branch_goals WHERE branch_id = $branchId AND goal_type_id = $goalTypeId AND year = $year AND month = $month");
            }
        }
    }
    header("Location: goals_manage.php?year=$year&month=$month&saved=1");
    exit;
}

// دریافت اهداف جاری
$allGoals = [];
foreach ($branches as $branch) {
    $q = mysqli_query($conn, "SELECT goal_type_id, target_value FROM branch_goals WHERE branch_id = {$branch['id']} AND year = $year AND month = $month");
    while ($row = mysqli_fetch_assoc($q)) {
        $allGoals[$branch['id']][$row['goal_type_id']] = $row['target_value'];
    }
}

$months = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
?>

<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <title>مدیریت اهداف شعب</title>
    <link href="../assets/fonts/fonts.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Vazirmatn', sans-serif; background: #0a0f1a; color: #e8ecf1; padding: 20px; }
        .container { max-width: 1400px; margin: 0 auto; }
        .card { background: rgba(255,255,255,0.05); border-radius: 16px; padding: 24px; border: 1px solid rgba(255,255,255,0.1); }
        h1 { font-size: 1.3rem; background: linear-gradient(135deg, #d4af37, #fcd34d); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin-bottom: 20px; }
        .filters { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 24px; align-items: center; justify-content: center; }
        .filters select, .filters button { padding: 8px 16px; border-radius: 8px; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: #fff; font-family: 'Vazirmatn'; cursor: pointer; }
        .filters button { background: linear-gradient(135deg, #d4af37, #fcd34d); color: #1a1a1a; border: none; font-weight: 700; }
        .table-wrapper { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 800px; }
        th, td { padding: 10px 6px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
        th { background: rgba(212,175,55,0.15); color: #d4af37; font-weight: 700; position: sticky; top: 0; }
        .branch-name { font-weight: 700; background: rgba(212,175,55,0.05); }
        .goal-input { width: 90px; padding: 6px; border-radius: 6px; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.2); color: #fff; text-align: center; }
        .goal-input:focus { outline: none; border-color: #d4af37; }
        .btn-save { background: linear-gradient(135deg, #10b981, #059669); color: #fff; border: none; padding: 12px 24px; border-radius: 10px; font-weight: 700; cursor: pointer; margin-top: 20px; }
        .success-msg { background: rgba(16,185,129,0.2); border: 1px solid #10b981; padding: 12px; border-radius: 10px; margin-bottom: 20px; text-align: center; }
        .back-link { color: #d4af37; text-decoration: none; display: inline-block; margin-bottom: 20px; }
    </style>
</head>
<body>
<div class="container">
    <a href="index.php" class="back-link">← بازگشت به پنل مدیریت</a>
    
    <div class="card">
        <h1>🎯 مدیریت اهداف شعب</h1>
        
        <?php if (isset($_GET['saved'])): ?>
        <div class="success-msg">✅ اهداف با موفقیت ذخیره شد</div>
        <?php endif; ?>
        
        <div class="filters">
            <select id="yearSelect" onchange="applyFilters()">
                <?php for($y = jdate('Y')-1; $y <= jdate('Y')+1; $y++): ?>
                    <option value="<?php echo $y; ?>" <?php echo $y == $year ? 'selected' : ''; ?>><?php echo $y; ?></option>
                <?php endfor; ?>
            </select>
            <select id="monthSelect" onchange="applyFilters()">
                <?php for($m = 1; $m <= 12; $m++): ?>
                    <option value="<?php echo $m; ?>" <?php echo $m == $month ? 'selected' : ''; ?>><?php echo $months[$m-1]; ?></option>
                <?php endfor; ?>
            </select>
            <button onclick="applyFilters()">🔍 اعمال</button>
        </div>
        
        <form method="POST">
            <input type="hidden" name="year" value="<?php echo $year; ?>">
            <input type="hidden" name="month" value="<?php echo $month; ?>">
            
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>شعبه</th>
                            <?php foreach ($goalTypes as $goal): ?>
                                <th><?php echo $goal['icon']; ?> <?php echo htmlspecialchars($goal['name']); ?><br><small>(<?php echo $goal['unit'] == 'gram' ? 'گرم' : 'میلیون ریال'; ?>)</small></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($branches as $branch): ?>
                        <tr>
                            <td class="branch-name"><?php echo htmlspecialchars($branch['branch_name']); ?></td>
                            <?php foreach ($goalTypes as $goal): 
                                $value = isset($allGoals[$branch['id']][$goal['id']]) ? $allGoals[$branch['id']][$goal['id']] : '';
                            ?>
                                <td><input type="number" step="0.001" class="goal-input" name="goals[<?php echo $branch['id']; ?>][<?php echo $goal['id']; ?>]" value="<?php echo htmlspecialchars($value); ?>" placeholder="0"></td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div style="text-align: center;">
                <button type="submit" name="save_all" class="btn-save">💾 ذخیره همه اهداف</button>
            </div>
        </form>
    </div>
</div>

<script>
function applyFilters() {
    var year = document.getElementById('yearSelect').value;
    var month = document.getElementById('monthSelect').value;
    window.location.href = 'goals_manage.php?year=' + year + '&month=' + month;
}
</script>
</body>
</html>