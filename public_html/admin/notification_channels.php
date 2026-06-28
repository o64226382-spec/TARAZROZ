<?php
define('SECURE_ACCESS', true);
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireAdmin();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ⭐ تابع ارسال تست (برای بله و روبیکا)
function send_test_message($platform, $token, $chat_id) {
    $text = '🧪 تست کانال - ' . date('Y-m-d H:i:s');
    
    if ($platform === 'bale') {
        $url = "https://tapi.bale.ai/bot{$token}/sendMessage";
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query(['chat_id' => $chat_id, 'text' => $text]),
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10
        ]);
    } else {
        $url = "https://botapi.rubika.ir/v3/{$token}/sendMessage";
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['chat_id' => $chat_id, 'text' => $text]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10
        ]);
    }
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $http_code === 200;
}

// ⭐ افزودن کانال
if (isset($_POST['add_channel'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) die('خطای امنیتی');
    
    $name = trim($_POST['name'] ?? '');
    $platform = $_POST['platform'] ?? 'bale';
    $token = trim($_POST['token'] ?? '');
    $chat_id = trim($_POST['chat_id'] ?? '');
    $purpose = $_POST['purpose'] ?? 'backup';
    
    if ($name && $token && $chat_id) {
        $stmt = mysqli_prepare($conn, "INSERT INTO notification_channels (name, platform, token, chat_id, purpose) VALUES (?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "sssss", $name, $platform, $token, $chat_id, $purpose);
        mysqli_stmt_execute($stmt);
    }
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    header('Location: notification_channels.php?added=1');
    exit;
}

// ⭐ ویرایش
if (isset($_POST['edit_channel'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) die('خطای امنیتی');
    
    $id = intval($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $token = trim($_POST['token'] ?? '');
    $chat_id = trim($_POST['chat_id'] ?? '');
    $purpose = $_POST['purpose'] ?? 'backup';
    $active = intval($_POST['active'] ?? 1);
    
    $stmt = mysqli_prepare($conn, "UPDATE notification_channels SET name=?, token=?, chat_id=?, purpose=?, active=? WHERE id=?");
    mysqli_stmt_bind_param($stmt, "ssssii", $name, $token, $chat_id, $purpose, $active, $id);
    mysqli_stmt_execute($stmt);
    
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    header('Location: notification_channels.php?updated=1');
    exit;
}

// ⭐ تست فوری (AJAX)
if (isset($_GET['test'])) {
    $id = intval($_GET['test'] ?? 0);
    $stmt = mysqli_prepare($conn, "SELECT * FROM notification_channels WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $ch = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    
    if ($ch) {
        $result = send_test_message($ch['platform'], $ch['token'], $ch['chat_id']);
        echo json_encode(['success' => $result, 'message' => $result ? '✅ پیام تست ارسال شد' : '❌ خطا در ارسال']);
    } else {
        echo json_encode(['success' => false, 'message' => 'کانال یافت نشد']);
    }
    exit;
}

// ⭐ حذف
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = mysqli_prepare($conn, "DELETE FROM notification_channels WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    header('Location: notification_channels.php?deleted=1');
    exit;
}

// ⭐ لیست
$channels = [];
$res = mysqli_query($conn, "SELECT * FROM notification_channels ORDER BY platform, purpose, name");
while ($r = mysqli_fetch_assoc($res)) $channels[] = $r;

$message = '';
if (isset($_GET['added'])) $message = '✅ کانال افزوده شد';
if (isset($_GET['updated'])) $message = '✅ کانال ویرایش شد';
if (isset($_GET['deleted'])) $message = '🗑️ کانال حذف شد';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>مدیریت کانال‌ها | تراز روزانه</title>
    <link href="../assets/fonts/fonts.css" rel="stylesheet">
    <style>
        :root{--bg:#0a0f1a;--surface:rgba(255,255,255,0.03);--border:rgba(255,255,255,0.06);--text:#e8ecf1;--accent:#4b8cf7;--red:#ef4444;--green:#10b981;--gold:#d4af37;--radius:12px}
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Vazirmatn';background:var(--bg);color:var(--text);padding:20px}
        .container{max-width:1200px;margin:0 auto}
        h2{color:var(--gold);margin-bottom:16px}
        .msg{padding:10px;border-radius:8px;margin-bottom:12px;text-align:center;background:rgba(16,185,129,0.1);color:var(--green)}
        .card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:14px;margin-bottom:12px}
        table{width:100%;border-collapse:collapse;font-size:.78rem}
        th,td{border:1px solid var(--border);padding:10px;text-align:center}
        th{background:rgba(255,255,255,0.03);color:#8899aa}
        input,select{padding:8px;border-radius:6px;background:rgba(255,255,255,0.03);border:1px solid var(--border);color:var(--text);font-family:'Vazirmatn';margin:4px}
        .btn{padding:6px 12px;border:none;border-radius:6px;cursor:pointer;font-family:'Vazirmatn';font-size:.7rem;color:#fff}
        .btn-add{background:var(--accent)}.btn-del{background:var(--red)}.btn-edit{background:var(--green)}.btn-test{background:#f59e0b}
        .form-row{display:flex;gap:8px;flex-wrap:wrap;align-items:end}
        a{color:var(--accent);text-decoration:none;font-size:.8rem}
        .badge{padding:3px 8px;border-radius:10px;font-size:.65rem}
        .bale{background:rgba(0,191,255,0.2);color:#00bfff}
        .rubika{background:rgba(255,215,0,0.2);color:#ffd700}
        .purpose-badge{padding:2px 8px;border-radius:8px;font-size:.6rem}
        .backup{background:rgba(239,68,68,0.15);color:#ef4444}
        .report{background:rgba(16,185,129,0.15);color:#10b981}
        .test-purpose{background:rgba(245,158,11,0.15);color:#f59e0b}
    </style>
</head>
<body>
<div class="container">
    <a href="index.php">← بازگشت به پنل</a>
    <h2>📡 مدیریت کانال‌های اطلاع‌رسانی</h2>
    
    <?php if($message): ?><div class="msg"><?php echo $message; ?></div><?php endif; ?>
    
    <!-- فرم افزودن -->
    <div class="card">
        <form method="POST" class="form-row">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="text" name="name" placeholder="اسم (مثلاً Backup)" required style="flex:1.5">
            <select name="platform" style="flex:1"><option value="bale">بله</option><option value="rubika">روبیکا</option></select>
            <select name="purpose" style="flex:1">
                <option value="backup">💾 بکاپ</option>
                <option value="report">📊 گزارش</option>
                <option value="test">🧪 تست</option>
            </select>
            <input type="text" name="token" placeholder="توکن" required style="flex:2">
            <input type="text" name="chat_id" placeholder="Chat ID" required style="flex:1.5">
            <button type="submit" name="add_channel" class="btn btn-add">➕ افزودن</button>
        </form>
    </div>
    
    <!-- لیست -->
    <div class="card">
        <table>
            <thead>
                <tr><th>#</th><th>نام</th><th>پلتفرم</th><th>کاربرد</th><th>توکن</th><th>Chat ID</th><th>وضعیت</th><th>عملیات</th></tr>
            </thead>
            <tbody>
                <?php foreach($channels as $i=>$ch): ?>
                <tr>
                    <td><?php echo $i+1; ?></td>
                    <td><?php echo htmlspecialchars($ch['name']); ?></td>
                    <td><span class="badge <?php echo $ch['platform']; ?>"><?php echo $ch['platform']==='bale'?'بله':'روبیکا'; ?></span></td>
                    <td>
                        <span class="purpose-badge <?php echo $ch['purpose']; ?>">
                            <?php 
                            echo $ch['purpose']==='backup'?'💾 بکاپ':($ch['purpose']==='report'?'📊 گزارش':'🧪 تست');
                            ?>
                        </span>
                    </td>
                    <td style="font-size:.65rem;max-width:120px;overflow:hidden"><?php echo htmlspecialchars(substr($ch['token'],0,15).'...'); ?></td>
                    <td><?php echo htmlspecialchars($ch['chat_id']); ?></td>
                    <td><?php echo $ch['active']?'✅':'❌'; ?></td>
                    <td>
                        <button class="btn btn-test" onclick="testChannel(<?php echo $ch['id']; ?>)">🧪 تست</button>
                        <button class="btn btn-edit" onclick="editChannel(<?php echo $ch['id']; ?>, '<?php echo addslashes($ch['name']); ?>', '<?php echo $ch['token']; ?>', '<?php echo $ch['chat_id']; ?>', '<?php echo $ch['purpose']; ?>', <?php echo $ch['active']; ?>)">✏️</button>
                        <a href="?delete=<?php echo $ch['id']; ?>" class="btn btn-del">🗑️</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- مودال ویرایش -->
<div id="editModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.7);z-index:1000;justify-content:center;align-items:center">
    <div style="background:#1a1f2e;padding:24px;border-radius:16px;width:90%;max-width:500px">
        <h3 style="color:#d4af37;margin-bottom:16px">✏️ ویرایش کانال</h3>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="id" id="editId">
            <label style="color:#8899aa;font-size:.8rem">نام</label>
            <input type="text" name="name" id="editName" style="width:100%;margin-bottom:8px">
            <label style="color:#8899aa;font-size:.8rem">توکن</label>
            <input type="text" name="token" id="editToken" style="width:100%;margin-bottom:8px">
            <label style="color:#8899aa;font-size:.8rem">Chat ID</label>
            <input type="text" name="chat_id" id="editChatId" style="width:100%;margin-bottom:8px">
            <label style="color:#8899aa;font-size:.8rem">کاربرد</label>
            <select name="purpose" id="editPurpose" style="width:100%;margin-bottom:8px">
                <option value="backup">💾 بکاپ</option>
                <option value="report">📊 گزارش</option>
                <option value="test">🧪 تست</option>
            </select>
            <label style="color:#8899aa;font-size:.8rem">وضعیت</label>
            <select name="active" id="editActive" style="width:100%;margin-bottom:16px">
                <option value="1">✅ فعال</option>
                <option value="0">❌ غیرفعال</option>
            </select>
            <div style="display:flex;gap:8px">
                <button type="submit" name="edit_channel" class="btn btn-add" style="flex:1">💾 ذخیره</button>
                <button type="button" class="btn btn-del" style="flex:1" onclick="document.getElementById('editModal').style.display='none'">انصراف</button>
            </div>
        </form>
    </div>
</div>

<script>
function editChannel(id, name, token, chatId, purpose, active) {
    document.getElementById('editId').value = id;
    document.getElementById('editName').value = name;
    document.getElementById('editToken').value = token;
    document.getElementById('editChatId').value = chatId;
    document.getElementById('editPurpose').value = purpose;
    document.getElementById('editActive').value = active;
    document.getElementById('editModal').style.display = 'flex';
}

async function testChannel(id) {
    let resp = await fetch(`notification_channels.php?test=${id}`);
    let data = await resp.json();
    alert(data.message);
}
</script>
</body>
</html>