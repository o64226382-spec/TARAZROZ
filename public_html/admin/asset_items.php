<?php
define('SECURE_ACCESS', true);
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireAdmin();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_item'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('خطای امنیتی');
    }
    $name = trim($_POST['name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $sort = intval($_POST['sort_order'] ?? 0);
    
    if (!empty($name)) {
        $stmt = mysqli_prepare($conn, "INSERT INTO asset_items (name, category, sort_order) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "ssi", $name, $category, $sort);
        mysqli_stmt_execute($stmt);
    }
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    header('Location: asset_items.php?added=1');
    exit;
}

if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = mysqli_prepare($conn, "DELETE FROM asset_items WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    header('Location: asset_items.php?deleted=1');
    exit;
}

$items = [];
$res = mysqli_query($conn, "SELECT * FROM asset_items ORDER BY sort_order");
while ($row = mysqli_fetch_assoc($res)) $items[] = $row;

$message = '';
if (isset($_GET['added'])) $message = '✅ آیتم افزوده شد';
if (isset($_GET['deleted'])) $message = '🗑️ آیتم حذف شد';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>مدیریت دارایی‌ها</title>
    <link href="../assets/fonts/fonts.css" rel="stylesheet">
    <style>
        :root{--bg:#0a0f1a;--surface:rgba(255,255,255,0.03);--border:rgba(255,255,255,0.06);--text:#e8ecf1;--accent:#4b8cf7;--red:#ef4444;--green:#10b981;--radius:12px}
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Vazirmatn',sans-serif;background:var(--bg);color:var(--text);padding:16px}
        .container{max-width:700px;margin:0 auto}
        h2{margin-bottom:16px;color:#d4af37}
        .card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:14px;margin-bottom:12px}
        table{width:100%;border-collapse:collapse;font-size:.78rem}
        th,td{border:1px solid var(--border);padding:8px;text-align:center}
        th{background:rgba(255,255,255,0.03);color:#8899aa}
        input,select{padding:8px;border-radius:6px;background:rgba(255,255,255,0.03);border:1px solid var(--border);color:var(--text);font-family:'Vazirmatn';margin:4px}
        .btn{padding:6px 12px;border:none;border-radius:6px;cursor:pointer;font-family:'Vazirmatn';font-size:.7rem;color:#fff}
        .btn-add{background:var(--accent)}
        .btn-del{background:var(--red)}
        .back-link{color:var(--accent);text-decoration:none;font-size:.75rem}
        .msg{padding:10px;border-radius:8px;margin-bottom:12px;text-align:center;background:rgba(16,185,129,0.1);color:var(--green)}
        .form-row{display:flex;gap:8px;flex-wrap:wrap;align-items:end}
    </style>
</head>
<body>
<div class="container">
    <a href="index.php" class="back-link">← بازگشت</a>
    <h2>📦 مدیریت آیتم‌های دارایی</h2>
    <?php if($message): ?><div class="msg"><?php echo $message; ?></div><?php endif; ?>
    
    <div class="card">
        <form method="POST" class="form-row">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="text" name="name" placeholder="نام دارایی" required style="flex:2;">
            <input type="text" name="category" placeholder="دسته‌بندی" style="flex:1;">
            <input type="number" name="sort_order" placeholder="ترتیب" value="0" style="width:70px;">
            <button type="submit" name="add_item" class="btn btn-add">➕ افزودن</button>
        </form>
    </div>
    
    <div class="card">
        <table>
            <thead><tr><th>#</th><th>نام</th><th>دسته</th><th>ترتیب</th><th></th></tr></thead>
            <tbody>
                <?php foreach($items as $i=>$item): ?>
                <tr>
                    <td><?php echo $i+1; ?></td>
                    <td><?php echo htmlspecialchars($item['name']); ?></td>
                    <td><?php echo $item['category'] ?: '—'; ?></td>
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