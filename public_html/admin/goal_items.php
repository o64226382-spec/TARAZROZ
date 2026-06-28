<?php
ob_start();
define('SECURE_ACCESS', true);
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireAdmin();
require_once '../includes/jdf.php';

// ========== دریافت تاریخ جاری با اعتبارسنجی ایمن ==========
$currentYear = 1405;
$currentMonth = (int)date('n');
if (function_exists('jdate')) {
    $jY = jdate('Y');
    $jM = jdate('m');
    if (is_numeric($jY) && $jY >= 1400 && $jY <= 1410) $currentYear = (int)$jY;
    if (is_numeric($jM) && $jM >= 1 && $jM <= 12) $currentMonth = (int)$jM;
}

// ========== تابع محاسبه تاریخ پایان دوره شمسی ==========
function calcEndShamsi($startY, $startM, $duration) {
    $endM = $startM + $duration - 1;
    $endY = $startY;
    while ($endM > 12) { $endM -= 12; $endY++; }
    return sprintf('%04d-%02d-31', $endY, $endM);
}

// ========== افزودن آیتم هدف جدید ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_item'])) {
    $stmt = mysqli_prepare($conn, "INSERT INTO goal_types (name, unit, icon, sort_order) VALUES (?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "sssi", trim($_POST['name']), $_POST['unit'], $_POST['icon'], (int)$_POST['sort_order']);
    mysqli_stmt_execute($stmt);
    header("Location: goal_items.php"); exit;
}

// ========== ویرایش آیتم هدف ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_item'])) {
    $stmt = mysqli_prepare($conn, "UPDATE goal_types SET name=?, unit=?, icon=?, sort_order=?, is_active=? WHERE id=?");
    mysqli_stmt_bind_param($stmt, "sssiii", trim($_POST['name']), $_POST['unit'], $_POST['icon'], (int)$_POST['sort_order'], isset($_POST['is_active'])?1:0, (int)$_POST['id']);
    mysqli_stmt_execute($stmt);
    header("Location: goal_items.php"); exit;
}

// ========== حذف آیتم هدف ==========
if (isset($_GET['delete_item'])) {
    mysqli_query($conn, "DELETE FROM goal_types WHERE id=".(int)$_GET['delete_item']);
    header("Location: goal_items.php"); exit;
}

// ========== ✅ تخصیص اهداف با بازه متغیر (بدون کلید، بدون خطا) ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_goals'])) {
    $branch_id  = (int)($_POST['branch_id'] ?? 0);
    $duration   = (int)($_POST['duration'] ?? 1);
    $start_y    = (int)($_POST['start_year'] ?? $currentYear);
    $start_m    = (int)($_POST['start_month'] ?? $currentMonth);
    $created_by = (int)($_SESSION['user_id'] ?? 1);

    $start_date = sprintf('%04d-%02d-01', $start_y, $start_m);
    $end_date   = calcEndShamsi($start_y, $start_m, $duration);

    foreach ($_POST['goals'] as $goal_type_id => $target_value) {
        $goal_type_id = (int)$goal_type_id;
        $target_value = (float)$target_value;

        // 1️⃣ حذف رکورد قدیمی این بازه (همیشه اول پاک کن)
        $del = mysqli_prepare($conn, "DELETE FROM branch_goals WHERE branch_id=? AND goal_type_id=? AND start_date=?");
        mysqli_stmt_bind_param($del, "iis", $branch_id, $goal_type_id, $start_date);
        mysqli_stmt_execute($del);

        // 2️⃣ درج رکورد جدید (فقط اگر مقدار > 0 باشد)
        if ($target_value > 0) {
            $ins = mysqli_prepare($conn, "INSERT INTO branch_goals (branch_id, goal_type_id, target_value, year, month, period_months, start_date, end_date, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($ins, "iiddiissi", $branch_id, $goal_type_id, $target_value, $start_y, $start_m, $duration, $start_date, $end_date, $created_by);
            mysqli_stmt_execute($ins);
        }
    }
    header("Location: goal_items.php?tab=assign&branch_id=$branch_id&start_date=$start_date&duration=$duration&saved=1");
    exit;
}

// ========== دریافت داده‌ها برای نمایش ==========
$items = [];
$q = mysqli_query($conn, "SELECT * FROM goal_types WHERE is_active=1 ORDER BY sort_order");
while ($r = mysqli_fetch_assoc($q)) $items[] = $r;

$branches = [];
$q = mysqli_query($conn, "SELECT id, branch_name FROM users WHERE role='branch' ORDER BY branch_name");
while ($r = mysqli_fetch_assoc($q)) $branches[] = $r;

$tab = $_GET['tab'] ?? 'items';
$selected_branch = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : ($branches[0]['id'] ?? 0);
$start_date = $_GET['start_date'] ?? sprintf('%04d-%02d-01', $currentYear, $currentMonth);
$duration = isset($_GET['duration']) ? (int)$_GET['duration'] : 1;

// دریافت اهداف تعریف‌شده برای این بازه
$currentGoals = [];
if ($selected_branch > 0) {
    $q = mysqli_query($conn, "SELECT goal_type_id, target_value FROM branch_goals WHERE branch_id=$selected_branch AND start_date='$start_date'");
    while ($r = mysqli_fetch_assoc($q)) $currentGoals[$r['goal_type_id']] = $r['target_value'];
}

$months = ['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];
$units = ['gram'=>'گرم','million_rial'=>'میلیون ریال'];
$icons = ['💰','📦','🏦','💳','⭐','🔄','🎯','📊','🔹','🔸'];
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
<meta charset="UTF-8">
<title>مدیریت اهداف شعب</title>
<link href="../assets/fonts/fonts.css" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Vazirmatn',sans-serif;background:#0a0f1a;color:#e8ecf1;padding:20px}
.container{max-width:1200px;margin:0 auto}
.card{background:rgba(255,255,255,0.05);border-radius:16px;padding:20px;margin-bottom:20px;border:1px solid rgba(255,255,255,0.1)}
h1{font-size:1.3rem;background:linear-gradient(135deg,#d4af37,#fcd34d);-webkit-background-clip:text;-webkit-text-fill-color:transparent;margin-bottom:20px}
.tabs{display:flex;gap:10px;margin-bottom:20px;border-bottom:1px solid rgba(255,255,255,0.1);padding-bottom:10px}
.tab-btn{padding:8px 20px;border-radius:8px;background:transparent;border:1px solid rgba(255,255,255,0.2);color:#fff;cursor:pointer;font-family:'Vazirmatn'}
.tab-btn.active{background:#d4af37;color:#1a1a1a;border-color:#d4af37}
table{width:100%;border-collapse:collapse}
th,td{padding:10px;text-align:center;border-bottom:1px solid rgba(255,255,255,0.1)}
th{background:rgba(212,175,55,0.2);color:#d4af37}
.form-group{margin-bottom:12px;display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.form-group label{width:100px;font-weight:600}
.form-group input,.form-group select{flex:1;padding:8px;border-radius:6px;background:rgba(0,0,0,0.3);border:1px solid rgba(255,255,255,0.2);color:#fff;font-family:'Vazirmatn'}
.btn{padding:6px 12px;border-radius:6px;border:none;cursor:pointer;font-family:'Vazirmatn';font-size:0.75rem}
.btn-add{background:#10b981;color:white}
.btn-edit{background:#d4af37;color:#1a1a1a}
.btn-delete{background:#ef4444;color:white}
.btn-save{background:linear-gradient(135deg,#d4af37,#fcd34d);color:#1a1a1a;font-weight:700;padding:10px 20px;width:100%;margin-top:20px}
.goal-input{width:100px;padding:6px;border-radius:6px;background:rgba(0,0,0,0.3);border:1px solid rgba(255,255,255,0.2);color:#fff;text-align:center}
.back-link{color:#d4af37;text-decoration:none;display:inline-block;margin-bottom:20px}
.filters{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:20px;align-items:center}
.filters select{padding:8px 16px;border-radius:8px;background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.2);color:#fff;cursor:pointer}
.success-msg{background:rgba(16,185,129,0.2);border:1px solid #10b981;padding:10px;border-radius:8px;margin-bottom:15px;text-align:center}
</style>
</head>
<body>
<div class="container">
<a href="index.php" class="back-link">← بازگشت به پنل مدیریت</a>
<div class="card">
<h1>🎯 مدیریت اهداف شعب</h1>
<div class="tabs">
<button class="tab-btn <?=$tab=='items'?'active':''?>" onclick="location.href='?tab=items'">📋 مدیریت آیتم‌ها</button>
<button class="tab-btn <?=$tab=='assign'?'active':''?>" onclick="location.href='?tab=assign'">🎯 تخصیص اهداف</button>
</div>

<?php if ($tab == 'items'): ?>
<div style="margin-bottom:20px;">
<h3 style="margin-bottom:15px;">➕ افزودن آیتم هدف جدید</h3>
<form method="POST" style="display:flex;gap:10px;flex-wrap:wrap;">
<input type="text" name="name" placeholder="نام هدف" required style="flex:2;padding:8px;border-radius:6px;background:rgba(0,0,0,0.3);border:1px solid rgba(255,255,255,0.2);color:#fff;">
<select name="unit" style="padding:8px;border-radius:6px;"><?php foreach($units as $k=>$v):?><option value="<?=$k?>"><?=$v?></option><?php endforeach;?></select>
<select name="icon" style="padding:8px;border-radius:6px;"><?php foreach($icons as $ic):?><option value="<?=$ic?>"><?=$ic?></option><?php endforeach;?></select>
<input type="number" name="sort_order" placeholder="ترتیب" value="0" style="width:80px;padding:8px;">
<button type="submit" name="add_item" class="btn btn-add">➕ افزودن</button>
</form>
</div>
<div style="overflow-x:auto;">
<table>
<thead><tr><th>آیکون</th><th>نام</th><th>واحد</th><th>ترتیب</th><th>وضعیت</th><th>عملیات</th></tr></thead>
<tbody>
<?php foreach($items as $item):?>
<tr>
<td><span style="font-size:1.3rem;"><?=$item['icon']?></span></td>
<td><?=htmlspecialchars($item['name'])?></td>
<td><?=$units[$item['unit']]?></td>
<td><?=$item['sort_order']?></td>
<td><?=$item['is_active']?'✅ فعال':'❌ غیرفعال'?></td>
<td>
<button class="btn btn-edit" onclick="editItem(<?=$item['id']?>,'<?=addslashes($item['name'])?>','<?=$item['unit']?>','<?=$item['icon']?>',<?=$item['sort_order']?>,<?=$item['is_active']?>)">✏️</button>
<a href="?delete_item=<?=$item['id']?>&tab=items" class="btn btn-delete" onclick="return confirm('حذف شود؟')">🗑️</a>
</td>
</tr>
<?php endforeach;?>
</tbody>
</table>
</div>
<?php endif; ?>

<?php if ($tab == 'assign'): ?>
<?php if (isset($_GET['saved'])):?><div class="success-msg">✅ اهداف با موفقیت ذخیره شد</div><?php endif;?>
<div class="filters">
<select id="branchSelect" onchange="applyFilters()">
<?php foreach($branches as $b):?>
<option value="<?=$b['id']?>" <?=$b['id']==$selected_branch?'selected':''?>><?=htmlspecialchars($b['branch_name'])?></option>
<?php endforeach;?>
</select>
<select id="startYear" onchange="applyFilters()">
<?php for($y=1403;$y<=1408;$y++):?>
<option value="<?=$y?>" <?=substr($start_date,0,4)==$y?'selected':''?>><?=$y?></option>
<?php endfor;?>
</select>
<select id="startMonth" onchange="applyFilters()">
<?php foreach($months as $i=>$name):$m=$i+1;?>
<option value="<?=$m?>" <?=substr($start_date,5,2)==sprintf('%02d',$m)?'selected':''?>><?=$name?></option>
<?php endforeach;?>
</select>
<select id="duration" onchange="applyFilters()">
<?php foreach([1,3,4,6,12] as $d):?>
<option value="<?=$d?>" <?=$duration==$d?'selected':''?>><?=$d?> ماهه</option>
<?php endforeach;?>
</select>
</div>
<form method="POST">
<input type="hidden" name="branch_id" value="<?=$selected_branch?>">
<input type="hidden" name="start_year" value="<?=substr($start_date,0,4)?>">
<input type="hidden" name="start_month" value="<?=substr($start_date,5,2)?>">
<input type="hidden" name="duration" value="<?=$duration?>">
<div style="overflow-x:auto;">
<table>
<thead><tr><th>آیکون</th><th>نام هدف</th><th>واحد</th><th>مقدار هدف کل دوره</th><th>پیشرفت فعلی دوره</th></tr></thead>
<tbody>
<?php foreach($items as $item):
$curr = $currentGoals[$item['id']] ?? '';
$unitText = $item['unit']=='gram'?'گرم':'میلیون ریال';
$end_m = (int)substr($start_date,5,2) + $duration - 1;
$end_y = (int)substr($start_date,0,4);
while($end_m > 12){ $end_m -= 12; $end_y++; }
$p_end = sprintf('%04d-%02d-31', $end_y, $end_m);
$prog = mysqli_query($conn, "SELECT SUM(achieved_value) as total FROM goal_daily_progress WHERE branch_id=$selected_branch AND goal_type_id={$item['id']} AND progress_date BETWEEN '$start_date' AND '$p_end'");
$p_row = mysqli_fetch_assoc($prog);
$progress = $p_row['total'] ?? 0;
?>
<tr>
<td><span style="font-size:1.2rem;"><?=$item['icon']?></span></td>
<td style="text-align:right;"><?=htmlspecialchars($item['name'])?></td>
<td><?=$unitText?></td>
<td><input type="number" step="0.001" class="goal-input" name="goals[<?=$item['id']?>]" value="<?=htmlspecialchars($curr)?>" placeholder="0"></td>
<td style="color:#10b981;"><?=number_format($progress)?> <?=$unitText?></td>
</tr>
<?php endforeach;?>
</tbody>
</table>
</div>
<button type="submit" name="assign_goals" class="btn-save">💾 ذخیره اهداف این دوره</button>
</form>
<?php endif;?>
</div>
</div>

<div id="editModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.7);z-index:1000;justify-content:center;align-items:center;">
<div style="background:#1a1f2e;padding:24px;border-radius:16px;width:90%;max-width:450px;">
<h3 style="margin-bottom:16px;">✏️ ویرایش آیتم هدف</h3>
<form method="POST">
<input type="hidden" name="id" id="editId">
<div class="form-group"><label>نام:</label><input type="text" name="name" id="editName" required></div>
<div class="form-group"><label>واحد:</label><select name="unit" id="editUnit"><?php foreach($units as $k=>$n):?><option value="<?=$k?>"><?=$n?></option><?php endforeach;?></select></div>
<div class="form-group"><label>آیکون:</label><select name="icon" id="editIcon"><?php foreach($icons as $ic):?><option value="<?=$ic?>"><?=$ic?></option><?php endforeach;?></select></div>
<div class="form-group"><label>ترتیب:</label><input type="number" name="sort_order" id="editSort"></div>
<div class="form-group"><label><input type="checkbox" name="is_active" id="editActive"> فعال</label></div>
<button type="submit" name="edit_item" class="btn btn-add">💾 ذخیره</button>
<button type="button" class="btn btn-delete" onclick="document.getElementById('editModal').style.display='none'">انصراف</button>
</form>
</div>
</div>

<script>
function applyFilters(){
    const b = document.getElementById('branchSelect').value;
    const y = document.getElementById('startYear').value;
    const m = document.getElementById('startMonth').value;
    const d = document.getElementById('duration').value;
    window.location.href = `?tab=assign&branch_id=${b}&start_date=${y}-${String(m).padStart(2,'0')}-01&duration=${d}`;
}
function editItem(id,name,unit,icon,sort,active){
    document.getElementById('editId').value = id;
    document.getElementById('editName').value = name;
    document.getElementById('editUnit').value = unit;
    document.getElementById('editIcon').value = icon;
    document.getElementById('editSort').value = sort;
    document.getElementById('editActive').checked = active == 1;
    document.getElementById('editModal').style.display = 'flex';
}
</script>
</body>
</html>