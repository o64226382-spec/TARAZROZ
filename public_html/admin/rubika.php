<?php
define('SECURE_ACCESS', true);
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireAdmin();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ⭐ گرفتن کانال‌های فعال از notification_channels
$channels = [];
$ch_res = mysqli_query($conn, "SELECT * FROM notification_channels WHERE active = 1 ORDER BY platform, name");
while ($c = mysqli_fetch_assoc($ch_res)) $channels[] = $c;

// ⭐ تنظیمات فعلی از rubika_config
$config = [];
$res = mysqli_query($conn, "SELECT * FROM rubika_config");
while ($r = mysqli_fetch_assoc($res)) $config[$r['setting_name']] = $r['setting_value'];

// ذخیره تنظیمات
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_config'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) die('خطای امنیتی');
    
    $settings = ['selected_channel', 'auto_send_daily', 'auto_send_time', 'template', 'selected_branches', 'enable_hooks'];
    $stmt = mysqli_prepare($conn, "INSERT INTO rubika_config (setting_name, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    
    foreach ($settings as $key) {
        $value = $_POST[$key] ?? '';
        mysqli_stmt_bind_param($stmt, "ss", $key, $value);
        mysqli_stmt_execute($stmt);
    }
    
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    header('Location: rubika.php?saved=1');
    exit;
}

$saved = isset($_GET['saved']);
$test_result = '';

// ارسال تست
if (isset($_POST['test_message'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) die('خطای امنیتی');
    
    $channel_id = intval($_POST['test_channel'] ?? 0);
    $test_text = $_POST['test_text'] ?? '🧪 پیام تست - ' . date('Y-m-d H:i:s');
    
    if ($channel_id > 0) {
        $stmt = mysqli_prepare($conn, "SELECT * FROM notification_channels WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $channel_id);
        mysqli_stmt_execute($stmt);
        $ch = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        
        if ($ch) {
            $result = send_message($ch['platform'], $ch['token'], $ch['chat_id'], $test_text);
            $test_result = $result ? '✅ پیام با موفقیت ارسال شد!' : '❌ خطا در ارسال';
        }
    } else {
        $test_result = '⚠️ لطفاً یک کانال انتخاب کنید';
    }
}

function send_message($platform, $token, $chat_id, $text) {
    if ($platform === 'bale') {
        $ch = curl_init("https://tapi.bale.ai/bot{$token}/sendMessage");
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query(['chat_id' => $chat_id, 'text' => $text]),
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10
        ]);
    } else {
        $ch = curl_init("https://botapi.rubika.ir/v3/{$token}/sendMessage");
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

$selected_channel_id = $config['selected_channel'] ?? '';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>مدیریت اعلان‌ها | تراز روزانه</title>
    <link href="../assets/fonts/fonts.css" rel="stylesheet">
    <style>
        :root { --bg: #0a0f1a; --surface: rgba(255,255,255,0.03); --border: rgba(255,255,255,0.06); --text: #e8ecf1; --accent: #4b8cf7; --gold: #d4af37; --green: #10b981; --red: #ef4444; --radius: 14px; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Vazirmatn', sans-serif; background: var(--bg); color: var(--text); padding: 20px; }
        .container { max-width: 900px; margin: 0 auto; }
        h2 { color: var(--gold); margin-bottom: 20px; }
        .card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 20px; margin-bottom: 16px; }
        label { display: block; margin: 12px 0 4px; font-size: 0.8rem; color: #8899aa; font-weight: 600; }
        input, select, textarea { width: 100%; padding: 10px; border-radius: 8px; background: rgba(255,255,255,0.03); border: 1px solid var(--border); color: var(--text); font-family: 'Vazirmatn'; font-size: 0.85rem; margin-bottom: 8px; }
        .btn { padding: 10px 24px; border: none; border-radius: 8px; cursor: pointer; font-family: 'Vazirmatn'; font-weight: 600; font-size: 0.85rem; }
        .btn-save { background: var(--accent); color: #fff; }
        .btn-test { background: var(--gold); color: #000; }
        .btn:hover { opacity: 0.9; }
        .result { padding: 12px; border-radius: 8px; margin-top: 12px; text-align: center; font-weight: 600; }
        .success { background: rgba(16,185,129,0.1); color: var(--green); }
        .error { background: rgba(239,68,68,0.1); color: var(--red); }
        .row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        @media (max-width: 600px) { .row { grid-template-columns: 1fr; } }
        a { color: var(--accent); text-decoration: none; font-size: 0.8rem; display: inline-block; margin-bottom: 16px; }
    </style>
</head>
<body>
<div class="container">
    <a href="index.php">← بازگشت به پنل</a>
    <h2>🔔 تنظیمات اعلان خودکار</h2>
    
    <?php if ($saved): ?>
        <div class="result success">✅ تنظیمات ذخیره شد</div>
    <?php endif; ?>
    <?php if ($test_result): ?>
        <div class="result <?php echo strpos($test_result, '✅') === 0 ? 'success' : 'error'; ?>"><?php echo $test_result; ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        
        <div class="card">
            <h3>📡 انتخاب کانال</h3>
            <label>کانال ارسال گزارش</label>
            <select name="selected_channel">
                <option value="">-- انتخاب کانال --</option>
                <?php foreach ($channels as $ch): 
                    $sel = $selected_channel_id == $ch['id'] ? 'selected' : '';
                    $icon = $ch['platform'] === 'bale' ? '🔵' : '🟡';
                    $purpose_label = $ch['purpose'] === 'backup' ? '💾 بکاپ' : ($ch['purpose'] === 'report' ? '📊 گزارش' : '🧪 تست');
                ?>
                <option value="<?php echo $ch['id']; ?>" <?php echo $sel; ?>>
                    <?php echo $icon . ' ' . htmlspecialchars($ch['name']) . ' (' . $purpose_label . ') - ' . htmlspecialchars($ch['chat_id']); ?>
                </option>
                <?php endforeach; ?>
            </select>
            <p style="font-size:0.7rem;color:#8899aa;margin-top:4px">💡 کانال‌ها از بخش 📡 کانال‌ها مدیریت می‌شوند</p>
        </div>

        <div class="card">
            <h3>📋 تنظیمات گزارش</h3>
            <div class="row">
                <div>
                    <label>📊 قالب گزارش</label>
                    <select name="template">
                        <option value="daily_summary" <?php echo ($config['template'] ?? '') === 'daily_summary' ? 'selected' : ''; ?>>خلاصه روزانه</option>
                        <option value="income_only" <?php echo ($config['template'] ?? '') === 'income_only' ? 'selected' : ''; ?>>فقط درآمد</option>
                    </select>
                </div>
                <div>
                    <label>🏢 شعبه</label>
                    <select name="selected_branches">
                        <option value="all" <?php echo ($config['selected_branches'] ?? 'all') === 'all' ? 'selected' : ''; ?>>همه شعب</option>
                        <?php
                        $br_res = mysqli_query($conn, "SELECT id, branch_name FROM users WHERE role = 'branch' ORDER BY branch_name");
                        while ($b = mysqli_fetch_assoc($br_res)) {
                            $sel = ($config['selected_branches'] ?? '') == $b['id'] ? 'selected' : '';
                            echo "<option value=\"{$b['id']}\" {$sel}>{$b['branch_name']}</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="card">
            <h3>⏰ زمان‌بندی</h3>
            <div class="row">
                <div>
                    <label>ارسال خودکار</label>
                    <select name="auto_send_daily">
                        <option value="1" <?php echo ($config['auto_send_daily'] ?? '0') == '1' ? 'selected' : ''; ?>>✅ فعال</option>
                        <option value="0" <?php echo ($config['auto_send_daily'] ?? '0') == '0' ? 'selected' : ''; ?>>❌ غیرفعال</option>
                    </select>
                </div>
                <div>
                    <label>ساعت ارسال</label>
                    <input type="text" name="auto_send_time" value="<?php echo htmlspecialchars($config['auto_send_time'] ?? '23:00'); ?>">
                </div>
            </div>
        </div>

        <button type="submit" name="save_config" class="btn btn-save">💾 ذخیره تنظیمات</button>
    </form>

    <!-- تست فوری -->
    <div class="card" style="margin-top:20px">
        <h3>🧪 تست فوری</h3>
        <p style="font-size:0.75rem;color:#8899aa;margin-bottom:8px">پیام تست به کانال انتخاب‌شده بالا ارسال می‌شود</p>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="test_channel" value="<?php echo $selected_channel_id; ?>">
            <textarea name="test_text" rows="3">🧪 پیام تست - <?php echo date('Y-m-d H:i:s'); ?></textarea>
            <button type="submit" name="test_message" class="btn btn-test">🚀 ارسال تست</button>
        </form>
    </div>
</div>
</body>
</html>