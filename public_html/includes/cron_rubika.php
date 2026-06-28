<?php
define('SECURE_ACCESS', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/rubika_api.php';

// خواندن تنظیمات
$config = [];
$res = mysqli_query($conn, "SELECT * FROM rubika_config");
while ($r = mysqli_fetch_assoc($res)) $config[$r['setting_name']] = $r['setting_value'];

if (($config['auto_send_daily'] ?? '0') == '1') {
    $token = $config['token'] ?? '';
    $chat_id = $config['chat_id'] ?? '';
    $template = $config['template'] ?? 'daily_summary';
    $branch_id = $config['selected_branches'] ?? 'all';
    
    if ($token && $chat_id) {
        $text = generate_report_text($template, $branch_id);
        send_rubika_message($token, $chat_id, $text);
    }
}