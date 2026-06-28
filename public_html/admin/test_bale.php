<?php
define('SECURE_ACCESS', true);
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireAdmin();

$message = '';
$result = '';

if (isset($_POST['test'])) {
    $bale_token = '2047185171:48SKKO5dzswnDnBcH-KDpMYs2zWfwNXVinY';
    $chat_id = '5838291218';
    $test_msg = $_POST['message'] ?? '🧪 تست از پنل ادمین تراز روزانه - ' . date('Y/m/d H:i:s');
    
    $ch = curl_init("https://tapi.bale.ai/bot{$bale_token}/sendMessage");
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['chat_id' => $chat_id, 'text' => $test_msg]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10
    ]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    if ($http_code === 200 && isset($result['ok']) && $result['ok'] === true) {
        $message = '✅ پیام با موفقیت به کانال بله ارسال شد!';
    } else {
        $message = '❌ خطا: ' . ($result['description'] ?? 'مشکل در ارتباط');
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تست بله | تراز روزانه</title>
    <link href="../assets/fonts/fonts.css" rel="stylesheet">
    <style>
        :root { --bg: #0a0f1a; --surface: rgba(255,255,255,0.03); --border: rgba(255,255,255,0.06); --text: #e8ecf1; --accent: #4b8cf7; --gold: #d4af37; --green: #10b981; --red: #ef4444; --radius: 14px; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Vazirmatn', sans-serif; background: var(--bg); color: var(--text); padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; }
        h2 { color: var(--gold); margin-bottom: 20px; }
        .card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 20px; margin-bottom: 16px; }
        textarea { width: 100%; padding: 10px; border-radius: 8px; background: rgba(255,255,255,0.03); border: 1px solid var(--border); color: var(--text); font-family: 'Vazirmatn'; font-size: 0.85rem; margin-bottom: 12px; resize: vertical; }
        .btn { padding: 10px 24px; background: var(--accent); color: #fff; border: none; border-radius: 8px; cursor: pointer; font-family: 'Vazirmatn'; font-weight: 600; font-size: 0.85rem; }
        .btn:hover { opacity: 0.9; }
        .result { padding: 12px; border-radius: 8px; margin-top: 12px; text-align: center; font-weight: 600; }
        .success { background: rgba(16,185,129,0.1); color: var(--green); }
        .error { background: rgba(239,68,68,0.1); color: var(--red); }
        .info { font-size: 0.75rem; color: #8899aa; margin-bottom: 8px; }
        a { color: var(--accent); text-decoration: none; font-size: 0.8rem; }
    </style>
</head>
<body>
<div class="container">
    <a href="index.php">← بازگشت به پنل</a>
    <h2>🧪 تست ارسال پیام به بله</h2>
    
    <div class="card">
        <p class="info">📱 کانال: <strong>Backup</strong></p>
        <p class="info">🤖 ربات: <strong>bakupbot@</strong></p>
        
        <form method="POST">
            <textarea name="message" rows="3" placeholder="پیام تست... (خالی بذاری پیام پیش‌فرض می‌ره)"><?php echo $_POST['message'] ?? ''; ?></textarea>
            <button type="submit" name="test" class="btn">🚀 ارسال به بله</button>
        </form>
        
        <?php if ($message): ?>
        <div class="result <?php echo strpos($message, '✅') === 0 ? 'success' : 'error'; ?>">
            <?php echo $message; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>