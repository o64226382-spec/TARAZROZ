<?php
define('SECURE_ACCESS', true);
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireLogin();

$current_user = getCurrentUser();
$role = $_SESSION['role'];
$user_id = intval($_SESSION['user_id']);
$is_admin = ($role === 'admin');
$is_observer = ($role === 'observer');
$is_readonly = $is_observer;

// ========== branch_id ==========
if ($is_admin || $is_observer) {
    if ($is_observer) {
        $branches = [];
        // ⭐ Prepared Statement
        $stmt = mysqli_prepare($conn, "SELECT u.id, u.branch_name FROM observer_assignments oa JOIN users u ON oa.branch_id = u.id WHERE oa.observer_id = ?");
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $br_res = mysqli_stmt_get_result($stmt);
        while ($b = mysqli_fetch_assoc($br_res)) $branches[] = $b;
        $branch_id = isset($_GET['branch_id']) ? intval($_GET['branch_id']) : ($branches[0]['id'] ?? 0);
    } else {
        $branches = [];
        $br_res = mysqli_query($conn, "SELECT id, branch_name FROM users WHERE role='branch' ORDER BY branch_name");
        while ($b = mysqli_fetch_assoc($br_res)) $branches[] = $b;
        $branch_id = isset($_GET['branch_id']) ? intval($_GET['branch_id']) : ($branches[0]['id'] ?? 0);
    }
} else {
    $branch_id = $user_id;
}

// ⭐ Prepared Statement
$branch_name = '';
$stmt = mysqli_prepare($conn, "SELECT branch_name FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $branch_id);
mysqli_stmt_execute($stmt);
$b_res = mysqli_stmt_get_result($stmt);
if ($b_res && mysqli_num_rows($b_res) > 0) $branch_name = mysqli_fetch_assoc($b_res)['branch_name'];

$selected_date = $_GET['date'] ?? date('Y/m/d');
$current_section = $_GET['section'] ?? 'daily';

// ========== API ذخیره ==========
if (isset($_POST['save'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    $date = $_POST['date'] ?? '';
    $rate = floatval($_POST['gold_rate'] ?? 0);
    $type = $_POST['type'] ?? 'daily';
    $items_json = $_POST['items'] ?? '[]';
    $items = json_decode($items_json, true);
    
    if (empty($date)) { echo json_encode(['success' => false, 'message' => 'تاریخ خالی است']); exit; }
    
    $recordDate = ($type === 'monthly') ? str_replace('/', '-', $date) . '-01' : str_replace('/', '-', $date);
    
    // ⭐ Prepared Statement
    $stmt = mysqli_prepare($conn, "DELETE FROM income_records WHERE branch_id = ? AND record_date = ?");
    mysqli_stmt_bind_param($stmt, "is", $branch_id, $recordDate);
    mysqli_stmt_execute($stmt);
    
    $count = 0;
    $stmt = mysqli_prepare($conn, "INSERT INTO income_records (branch_id, item_id, record_date, gold_rate, amount_rial, amount_gram, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
    foreach ($items as $item) {
        $item_id = intval($item['id']);
        $rial = floatval($item['rial'] ?? 0);
        $gram = floatval($item['gram'] ?? 0);
        if ($item_id > 0 && ($rial > 0 || $gram > 0)) {
            mysqli_stmt_bind_param($stmt, "iisiddi", $branch_id, $item_id, $recordDate, $rate, $rial, $gram, $user_id);
            if (mysqli_stmt_execute($stmt)) $count++;
        }
    }
    
    echo json_encode(['success' => true, 'message' => "✅ $count مورد ذخیره شد"]);
    exit;
}

// ========== آیتم‌ها ==========
$daily_items = []; $monthly_items = [];
$items_res = mysqli_query($conn, "SELECT * FROM income_items WHERE active=1 ORDER BY sort_order");
while ($item = mysqli_fetch_assoc($items_res)) {
    if ($item['type'] === 'daily') $daily_items[] = $item;
    else $monthly_items[] = $item;
}

// ========== داده‌های ذخیره‌شده ==========
$today_data = [];
$today_rate = 0;
$recordDate = str_replace('/', '-', $selected_date);
if ($current_section === 'monthly') $recordDate .= '-01';

// ⭐ Prepared Statement
$stmt = mysqli_prepare($conn, "SELECT * FROM income_records WHERE branch_id = ? AND record_date = ?");
mysqli_stmt_bind_param($stmt, "is", $branch_id, $recordDate);
mysqli_stmt_execute($stmt);
$rec_res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($rec_res)) {
    $today_data[$row['item_id']] = $row;
    if ($row['gold_rate'] > 0) $today_rate = $row['gold_rate'];
}

$items = ($current_section === 'daily') ? $daily_items : $monthly_items;
?>
<!-- بقیه فایل HTML و JS دقیقاً مثل قبل بمونه -->
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8"><title>آیتم‌های درآمد</title>
    <link href="fonts.css" rel="stylesheet">
    <style>
        :root { --bg: #0a0f1a; --surface: rgba(255,255,255,0.03); --border: rgba(255,255,255,0.06); --text: #e8ecf1; --accent: #4b8cf7; --red: #ef4444; }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: 'Vazirmatn', sans-serif; background: var(--bg); color: var(--text); padding: 16px; }
        .container { max-width: 700px; margin: 0 auto; }
        h2 { margin-bottom: 16px; }
        .card { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 14px; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; font-size: 0.75rem; }
        th, td { border: 1px solid var(--border); padding: 6px; text-align: center; }
        th { background: rgba(255,255,255,0.03); }
        input, select { padding: 6px 8px; border-radius: 5px; background: rgba(255,255,255,0.03); border: 1px solid var(--border); color: var(--text); font-family: 'Vazirmatn'; margin: 3px; }
        .btn { padding: 6px 12px; border: none; border-radius: 5px; cursor: pointer; font-family: 'Vazirmatn'; font-size: 0.7rem; color: white; }
        .btn-add { background: var(--accent); } .btn-del { background: var(--red); }
        .back-link { color: var(--accent); text-decoration: none; font-size: 0.75rem; }
    </style>
</head>
<body>
<div class="container">
    <a href="../admin/index.php" class="back-link">← بازگشت به پنل</a>
    <h2>📋 مدیریت آیتم‌های درآمد</h2>
    <div class="card">
        <form method="POST">
            <input type="text" name="name" placeholder="نام آیتم" required>
            <select name="type"><option value="daily">روزانه</option><option value="monthly">ماهانه</option></select>
            <input type="text" name="category" placeholder="دسته (اختیاری)">
            <input type="number" name="sort_order" placeholder="ترتیب" value="0" style="width:60px;">
            <button type="submit" name="add_item" class="btn btn-add">➕ افزودن</button>
        </form>
    </div>
    <div class="card">
        <table>
            <thead><tr><th>#</th><th>نام</th><th>نوع</th><th>دسته</th><th>ترتیب</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($items as $i => $item): ?>
                <tr>
                    <td><?php echo $i+1; ?></td>
                    <td><?php echo htmlspecialchars($item['name']); ?></td>
                    <td><?php echo $item['type']==='daily'?'روزانه':'ماهانه'; ?></td>
                    <td><?php echo $item['category']?:'—'; ?></td>
                    <td><?php echo $item['sort_order']; ?></td>
                    <td><a href="?delete=<?php echo $item['id']; ?>" class="btn btn-del" onclick="return confirm('حذف؟')">🗑️</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>