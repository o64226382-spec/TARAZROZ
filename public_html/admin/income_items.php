<?php
define('SECURE_ACCESS', true);
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireAdmin();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ========== افزودن ==========
if (isset($_POST['add_item'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('خطای امنیتی. لطفاً صفحه را رفرش کنید.');
    }
    
    $name = trim($_POST['name'] ?? '');
    $type = $_POST['type'] ?? 'daily';
    $category = trim($_POST['category'] ?? '');
    $sort = intval($_POST['sort_order'] ?? 0);
    $table = ($type === 'daily') ? 'income_daily_items' : 'income_monthly_items';
    
    if (!empty($name)) {
        $stmt = mysqli_prepare($conn, "INSERT INTO $table (name, category, sort_order) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "ssi", $name, $category, $sort);
        mysqli_stmt_execute($stmt);
    }
    
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    header('Location: income_items.php?added=1');
    exit();
}

// ========== حذف ==========
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $type = $_GET['type'] ?? 'daily';
    $table = ($type === 'daily') ? 'income_daily_items' : 'income_monthly_items';
    
    if ($id > 0) {
        $stmt = mysqli_prepare($conn, "DELETE FROM $table WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
    }
    
    header('Location: income_items.php?deleted=1');
    exit();
}

// ========== لیست‌ها ==========
$daily = [];
$d_res = mysqli_query($conn, "SELECT * FROM income_daily_items WHERE active=1 ORDER BY sort_order");
if ($d_res) {
    while ($r = mysqli_fetch_assoc($d_res)) $daily[] = $r;
}

$monthly = [];
$m_res = mysqli_query($conn, "SELECT * FROM income_monthly_items WHERE active=1 ORDER BY sort_order");
if ($m_res) {
    while ($r = mysqli_fetch_assoc($m_res)) $monthly[] = $r;
}

$message = '';
if (isset($_GET['added'])) $message = '✅ آیتم با موفقیت افزوده شد';
if (isset($_GET['deleted'])) $message = '🗑️ آیتم حذف شد';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>مدیریت آیتم‌های درآمد | تراز روزانه</title>
    <link href="../assets/fonts/fonts.css" rel="stylesheet">
    <style>
        :root { --bg: #0a0f1a; --surface: rgba(255,255,255,0.03); --border: rgba(255,255,255,0.06); --text: #e8ecf1; --accent: #4b8cf7; --red: #ef4444; --gold: #d4af37; --radius: 12px; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Vazirmatn', sans-serif; background: var(--bg); color: var(--text); padding: 16px; }
        .container { max-width: 800px; margin: 0 auto; }
        h2, h3 { color: var(--gold); margin-bottom: 12px; }
        .card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 14px; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; font-size: 0.75rem; }
        th, td { border: 1px solid var(--border); padding: 6px; text-align: center; }
        th { background: rgba(255,255,255,0.03); color: #8899aa; }
        input, select { padding: 6px 8px; border-radius: 5px; background: rgba(255,255,255,0.03); border: 1px solid var(--border); color: var(--text); font-family: 'Vazirmatn'; margin: 3px; }
        .btn { padding: 6px 12px; border: none; border-radius: 5px; cursor: pointer; font-family: 'Vazirmatn'; font-size: 0.7rem; color: white; }
        .btn-add { background: var(--accent); }
        .btn-del { background: var(--red); }
        .back-link { color: var(--accent); text-decoration: none; font-size: 0.75rem; display: inline-block; margin-bottom: 12px; }
        .message { padding: 10px; border-radius: 8px; margin-bottom: 12px; text-align: center; font-size: 0.8rem; font-weight: 600; background: rgba(16,185,129,0.1); color: #10b981; }
        .form-row { display: flex; gap: 8px; flex-wrap: wrap; align-items: end; }
    </style>
</head>
<body>
<div class="container">
    <a href="index.php" class="back-link">← بازگشت به پنل مدیریت</a>
    <h2>📋 مدیریت آیتم‌های درآمد</h2>
    
    <?php if ($message): ?>
        <div class="message"><?php echo $message; ?></div>
    <?php endif; ?>
    
    <div class="card">
        <form method="POST" class="form-row">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="text" name="name" placeholder="نام آیتم" required style="flex:2;">
            <select name="type" style="flex:1;"><option value="daily">روزانه</option><option value="monthly">ماهانه</option></select>
            <input type="text" name="category" placeholder="دسته (اختیاری)" style="flex:1;">
            <input type="number" name="sort_order" placeholder="ترتیب" value="0" style="width:60px;">
            <button type="submit" name="add_item" class="btn btn-add">➕ افزودن</button>
        </form>
    </div>
    
    <div class="card">
        <h3>📆 روزانه (<?php echo count($daily); ?> عدد)</h3>
        <table>
            <thead><tr><th>#</th><th>نام</th><th>دسته</th><th>ترتیب</th><th>عملیات</th></tr></thead>
            <tbody>
                <?php if (empty($daily)): ?>
                    <tr><td colspan="5">هیچ آیتمی ثبت نشده</td></tr>
                <?php else: ?>
                    <?php foreach ($daily as $i => $item): ?>
                    <tr>
                        <td><?php echo $i+1; ?></td>
                        <td><?php echo htmlspecialchars($item['name']); ?></td>
                        <td><?php echo $item['category'] ?: '—'; ?></td>
                        <td><?php echo $item['sort_order']; ?></td>
                        <td><a href="?delete=<?php echo $item['id']; ?>&type=daily" class="btn btn-del" onclick="return confirm('حذف؟')">🗑️</a></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <div class="card">
        <h3>📅 ماهانه (<?php echo count($monthly); ?> عدد)</h3>
        <table>
            <thead><tr><th>#</th><th>نام</th><th>دسته</th><th>ترتیب</th><th>عملیات</th></tr></thead>
            <tbody>
                <?php if (empty($monthly)): ?>
                    <tr><td colspan="5">هیچ آیتمی ثبت نشده</td></tr>
                <?php else: ?>
                    <?php foreach ($monthly as $i => $item): ?>
                    <tr>
                        <td><?php echo $i+1; ?></td>
                        <td><?php echo htmlspecialchars($item['name']); ?></td>
                        <td><?php echo $item['category'] ?: '—'; ?></td>
                        <td><?php echo $item['sort_order']; ?></td>
                        <td><a href="?delete=<?php echo $item['id']; ?>&type=monthly" class="btn btn-del" onclick="return confirm('حذف؟')">🗑️</a></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>