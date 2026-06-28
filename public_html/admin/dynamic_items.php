<?php
define('SECURE_ACCESS', true);
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireAdmin();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ========== افزودن آیتم ==========
if (isset($_POST['add_item'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('خطای امنیتی. لطفاً صفحه را رفرش کنید.');
    }
    
    $name = trim($_POST['name'] ?? '');
    $sort = intval($_POST['sort_order'] ?? 0);
    
    if (!empty($name)) {
        $stmt = mysqli_prepare($conn, "INSERT INTO dynamic_items (name, sort_order) VALUES (?, ?)");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "si", $name, $sort);
            mysqli_stmt_execute($stmt);
        }
    }
    
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    header('Location: dynamic_items.php?added=1');
    exit();
}

// ========== حذف آیتم ==========
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    if ($id > 0) {
        $stmt = mysqli_prepare($conn, "DELETE FROM dynamic_items WHERE id = ?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $id);
            mysqli_stmt_execute($stmt);
        }
    }
    header('Location: dynamic_items.php?deleted=1');
    exit();
}

// ========== لیست آیتم‌ها ==========
$items = [];
$items_result = mysqli_query($conn, "SELECT * FROM dynamic_items ORDER BY sort_order");
if ($items_result) {
    while ($row = mysqli_fetch_assoc($items_result)) {
        $items[] = $row;
    }
}

$message = '';
if (isset($_GET['added'])) $message = '✅ آیتم با موفقیت افزوده شد';
if (isset($_GET['deleted'])) $message = '🗑️ آیتم حذف شد';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>آیتم‌های داینامیک | تراز روزانه</title>
    <link href="../assets/fonts/fonts.css" rel="stylesheet">
    <style>
        :root { --bg: #0a0f1a; --surface: rgba(255,255,255,0.03); --border: rgba(255,255,255,0.06); --text: #e8ecf1; --accent: #4b8cf7; --red: #ef4444; --green: #10b981; --gold: #d4af37; --radius: 12px; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Vazirmatn', sans-serif; background: var(--bg); color: var(--text); padding: 16px; }
        .container { max-width: 600px; margin: 0 auto; }
        h2 { color: var(--gold); margin-bottom: 16px; }
        .card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 14px; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; font-size: 0.78rem; }
        th, td { border: 1px solid var(--border); padding: 8px; text-align: center; }
        th { background: rgba(255,255,255,0.03); color: #8899aa; }
        input { padding: 8px; border-radius: 6px; background: rgba(255,255,255,0.03); border: 1px solid var(--border); color: var(--text); font-family: 'Vazirmatn'; margin: 4px; }
        .btn { padding: 6px 12px; border: none; border-radius: 6px; cursor: pointer; font-family: 'Vazirmatn'; font-size: 0.7rem; color: white; }
        .btn-add { background: var(--accent); }
        .btn-del { background: var(--red); }
        .back-link { color: var(--accent); text-decoration: none; font-size: 0.75rem; display: inline-block; margin-bottom: 12px; }
        .message { padding: 10px; border-radius: 8px; margin-bottom: 12px; text-align: center; font-size: 0.8rem; font-weight: 600; background: rgba(16,185,129,0.1); color: var(--green); }
        .form-row { display: flex; gap: 8px; flex-wrap: wrap; align-items: end; }
    </style>
</head>
<body>
<div class="container">
    <a href="index.php" class="back-link">← بازگشت به پنل مدیریت</a>
    <h2>⚙️ مدیریت آیتم‌های داینامیک</h2>
    
    <?php if ($message): ?>
        <div class="message"><?php echo $message; ?></div>
    <?php endif; ?>
    
    <div class="card">
        <form method="POST" class="form-row">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="text" name="name" placeholder="نام آیتم" required style="flex: 2;">
            <input type="number" name="sort_order" placeholder="ترتیب" value="0" style="width: 70px;">
            <button type="submit" name="add_item" class="btn btn-add">➕ افزودن</button>
        </form>
    </div>
    
    <div class="card">
        <table>
            <thead>
                <tr><th>#</th><th>نام آیتم</th><th>ترتیب</th><th>عملیات</th></tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                    <tr><td colspan="4">هیچ آیتمی ثبت نشده است.</td></tr>
                <?php else: ?>
                    <?php foreach ($items as $i => $item): ?>
                    <tr>
                        <td><?php echo $i + 1; ?></td>
                        <td><?php echo htmlspecialchars($item['name']); ?></td>
                        <td><?php echo $item['sort_order']; ?></td>
                        <td>
                            <a href="?delete=<?php echo $item['id']; ?>" class="btn btn-del" onclick="return confirm('آیا از حذف این آیتم مطمئن هستید؟')">🗑️ حذف</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>