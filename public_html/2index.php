<?php
define('SECURE_ACCESS', true);
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/jdf.php';

$isLoggedIn = isLoggedIn();
$userRole = $_SESSION['role'] ?? '';
$current_user = $isLoggedIn ? getCurrentUser() : null;
$current_user_id = $_SESSION['user_id'] ?? 0;
$userPermissions = $current_user['permissions'] ?? '';

$all_tools = [];
$tools_query = "SELECT * FROM tools WHERE active = 1 ORDER BY name";
$tools_result = mysqli_query($conn, $tools_query);
while ($t = mysqli_fetch_assoc($tools_result)) $all_tools[] = $t;

$jalali_today = jdate('l j F Y');

function fa_to_en($str) {
    return str_replace(['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'], 
                       ['0','1','2','3','4','5','6','7','8','9'], $str);
}

$current_year = (int) fa_to_en(jdate('Y'));
$current_month = (int) fa_to_en(jdate('m'));
$todayDateEn = fa_to_en(jdate('Y-m-d'));

function getUserWorkPattern($conn, $user_id) {
    if (!$user_id) return null;
    
    $today_day_of_week = (int)fa_to_en(jdate('w'));
    
    $q = mysqli_query($conn, "
        SELECT p.id as pattern_id, p.name as pattern_name, 
               pwh.morning_start, pwh.morning_end, 
               pwh.afternoon_start, pwh.afternoon_end,
               pwh.is_working_day
        FROM working_hour_patterns p 
        INNER JOIN user_work_patterns up ON p.id = up.pattern_id 
        LEFT JOIN pattern_weekly_hours pwh ON p.id = pwh.pattern_id AND pwh.day_of_week = $today_day_of_week
        WHERE up.user_id = ".(int)$user_id." AND p.is_active = 1 
        LIMIT 1
    ");
    
    if (mysqli_num_rows($q) > 0) {
        $result = mysqli_fetch_assoc($q);
        if ($result['is_working_day']) return $result;
        return null;
    }
    
    $q2 = mysqli_query($conn, "
        SELECT p.id as pattern_id, p.name as pattern_name,
               pwh.morning_start, pwh.morning_end, 
               pwh.afternoon_start, pwh.afternoon_end,
               pwh.is_working_day
        FROM working_hour_patterns p
        LEFT JOIN pattern_weekly_hours pwh ON p.id = pwh.pattern_id AND pwh.day_of_week = $today_day_of_week
        WHERE p.name = 'الگوی استاندارد' AND p.is_active = 1 
        LIMIT 1
    ");
    
    if (mysqli_num_rows($q2) > 0) {
        $result = mysqli_fetch_assoc($q2);
        if ($result['is_working_day']) return $result;
    }
    
    return null;
}

function getWorkCountdownData($pattern) {
    if (!$pattern) return null;
    
    // اگر امروز تعطیله یا هیچ ساعتی تنظیم نشده
    if (empty($pattern['morning_start']) && empty($pattern['afternoon_start'])) {
        return ['label' => 'امروز تعطیل است 🌙', 'seconds' => 0, 'is_work' => false, 'status' => 'closed'];
    }
    
    $now = new DateTime(); 
    $current_date = $now->format('Y-m-d'); 
    $now_ts = time();
    
    $res = ['label' => 'خسته نباشید همکاران 🌙', 'seconds' => 0, 'is_work' => false, 'status' => 'closed'];
    
    if (!empty($pattern['morning_start']) && !empty($pattern['morning_end'])) {
        $m_start = strtotime("$current_date {$pattern['morning_start']}"); 
        $m_end = strtotime("$current_date {$pattern['morning_end']}");
        
        if ($now_ts < $m_start) {
            return ['label' => 'شروع شیفت صبح', 'seconds' => $m_start - $now_ts, 'is_work' => false, 'status' => 'before'];
        } elseif ($now_ts < $m_end) {
            return ['label' => 'پایان شیفت صبح', 'seconds' => $m_end - $now_ts, 'is_work' => true, 'status' => 'morning'];
        }
    }
    
    if (!empty($pattern['afternoon_start']) && !empty($pattern['afternoon_end'])) {
        $a_start = strtotime("$current_date {$pattern['afternoon_start']}"); 
        $a_end = strtotime("$current_date {$pattern['afternoon_end']}");
        
        if ($now_ts < $a_start) {
            return ['label' => 'شروع شیفت عصر', 'seconds' => $a_start - $now_ts, 'is_work' => false, 'status' => 'break'];
        } elseif ($now_ts < $a_end) {
            return ['label' => 'پایان شیفت عصر', 'seconds' => $a_end - $now_ts, 'is_work' => true, 'status' => 'afternoon'];
        }
    }
    
    return $res;
}

$work_timer = null;
if ($isLoggedIn && $current_user_id) {
    $pattern = getUserWorkPattern($conn, $current_user_id);
    if ($pattern) $work_timer = getWorkCountdownData($pattern);
}
function getToolIcon($tool) {
    $icon_map = [
        '⚖️' => '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>',
        '📄' => '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>',
        '🧮' => '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="2" width="16" height="20" rx="2"/><line x1="8" y1="6" x2="16" y2="6"/><line x1="8" y1="10" x2="16" y2="10"/><line x1="8" y1="14" x2="12" y2="14"/></svg>',
        '📑' => '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1"/></svg>',
        '💰' => '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>',
    ];
    
    $icon = $tool['icon'] ?? '📄';
    
    if (isset($icon_map[$icon])) {
        return $icon_map[$icon];
    }
    
    return $icon;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="theme-color" content="#0b101e">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<title>پلتفرم تراز روزانه | مدیریت جابجایی وجوه</title>
<link href="assets/fonts/fonts.css" rel="stylesheet">
<link rel="icon" type="image/png" href="assets/images/logo.png">
<link rel="apple-touch-icon" href="assets/images/logo.png">
<style>
/* ============================================
   RESET & VARIABLES
   ============================================ */
:root {
    --bg-gradient: linear-gradient(135deg, #0b101e 0%, #1a2235 100%);
    --glass-bg: rgba(25, 33, 50, 0.4);
    --glass-border: rgba(255, 255, 255, 0.08);
    --glass-border-hover: rgba(212, 175, 55, 0.3);
    --glass-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.3);
    --text: #f0f2f5;
    --text-secondary: #94a3b8;
    --accent: #60a5fa;
    --gold: #d4af37;
    --gold-light: #fcd34d;
    --green: #34d399;
    --red: #f87171;
    --purple: #a78bfa;
    --amber: #fbbf24;
    --radius: 20px;
    --radius-sm: 12px;
    --transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
}

/* Light Mode */
body.light {
    --bg-gradient: linear-gradient(135deg, #f8f9fa 0%, #e2e8f0 100%);
    --glass-bg: rgba(255, 255, 255, 0.6);
    --glass-border: rgba(255, 255, 255, 0.8);
    --glass-border-hover: rgba(212, 175, 55, 0.5);
    --glass-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.07);
    --text: #1e293b;
    --text-secondary: #64748b;
    --accent: #3b82f6;
    --gold: #b45309;
    --gold-light: #d97706;
    --green: #10b981;
    --red: #ef4444;
    --purple: #8b5cf6;
    --amber: #f59e0b;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    -webkit-tap-highlight-color: transparent;
}

body {
    font-family: 'Vazirmatn', sans-serif;
    background: var(--bg-gradient);
    color: var(--text);
    min-height: 100vh;
    padding: 15px 15px 100px 15px;
    background-attachment: fixed;
    transition: var(--transition);
}

/* Animated Background Orbs */
body::before,
body::after {
    content: '';
    position: fixed;
    border-radius: 50%;
    z-index: -1;
    filter: blur(80px);
    pointer-events: none;
}

body::before {
    top: -10%;
    left: -10%;
    width: 40vw;
    height: 40vw;
    background: rgba(212, 175, 55, 0.05);
}

body::after {
    bottom: -10%;
    right: -10%;
    width: 50vw;
    height: 50vw;
    background: rgba(96, 165, 250, 0.05);
}

/* Theme Toggle Button - Top Right Circle */
.theme-toggle {
    position: fixed;
    top: 20px;
    right: 20px;
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: var(--glass-bg);
    backdrop-filter: blur(12px);
    border: 1px solid var(--glass-border);
    cursor: pointer;
    z-index: 1001;
    box-shadow: var(--glass-shadow);
    overflow: hidden;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

.theme-toggle:hover {
    transform: scale(1.08);
    border-color: var(--gold-light);
    box-shadow: 0 0 25px rgba(212, 175, 55, 0.3);
}

.theme-toggle::before {
    content: '';
    position: absolute;
    width: 100%;
    height: 100%;
    border-radius: 50%;
    background: linear-gradient(135deg, #1e293b, #0f172a);
    transition: all 0.5s ease;
}

body.light .theme-toggle::before {
    background: linear-gradient(135deg, #fbbf24, #f59e0b);
}

.toggle-icon-wrap {
    position: relative;
    width: 24px;
    height: 24px;
    z-index: 2;
}

.sun-rays {
    position: absolute;
    top: 50%;
    left: 50%;
    width: 24px;
    height: 24px;
    transform: translate(-50%, -50%) scale(0);
    opacity: 0;
    transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
}

body.light .sun-rays {
    transform: translate(-50%, -50%) scale(1);
    opacity: 1;
}

.sun-rays .ray {
    position: absolute;
    top: 50%;
    left: 50%;
    width: 2px;
    height: 6px;
    background: #1e293b;
    border-radius: 1px;
    transform-origin: center -8px;
}

.sun-rays .ray:nth-child(1) { transform: translate(-50%, -50%) rotate(0deg); }
.sun-rays .ray:nth-child(2) { transform: translate(-50%, -50%) rotate(45deg); }
.sun-rays .ray:nth-child(3) { transform: translate(-50%, -50%) rotate(90deg); }
.sun-rays .ray:nth-child(4) { transform: translate(-50%, -50%) rotate(135deg); }
.sun-rays .ray:nth-child(5) { transform: translate(-50%, -50%) rotate(180deg); }
.sun-rays .ray:nth-child(6) { transform: translate(-50%, -50%) rotate(225deg); }
.sun-rays .ray:nth-child(7) { transform: translate(-50%, -50%) rotate(270deg); }
.sun-rays .ray:nth-child(8) { transform: translate(-50%, -50%) rotate(315deg); }

.moon-icon {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%) rotate(0deg);
    opacity: 1;
    transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
}

body.light .moon-icon {
    transform: translate(-50%, -50%) rotate(90deg) scale(0.5);
    opacity: 0;
}

.stars {
    position: absolute;
    top: 50%;
    left: 50%;
    width: 20px;
    height: 20px;
    transform: translate(-50%, -50%);
    opacity: 1;
    transition: all 0.3s ease;
}

body.light .stars {
    opacity: 0;
}

.star {
    position: absolute;
    width: 2px;
    height: 2px;
    background: #fcd34d;
    border-radius: 50%;
    animation: starTwinkle 2s infinite;
}

.star:nth-child(1) { top: 0; left: 50%; animation-delay: 0s; }
.star:nth-child(2) { top: 20%; right: 0; animation-delay: 0.5s; }
.star:nth-child(3) { bottom: 10%; left: 10%; animation-delay: 1s; }

@keyframes starTwinkle {
    0%, 100% { opacity: 0.3; transform: scale(1); }
    50% { opacity: 1; transform: scale(1.8); }
}

/* Container */
.app-container {
    max-width: 1200px;
    margin: 0 auto;
}

/* Tab Content */
.tab-content {
    display: none;
    animation: fadeIn 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}

.tab-content.active {
    display: block;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(15px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Glass Panel Base */
.glass-panel {
    background: var(--glass-bg);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid var(--glass-border);
    border-radius: var(--radius);
    box-shadow: var(--glass-shadow);
    transition: var(--transition);
}

/* Welcome Card */
.welcome-card {
    text-align: center;
    padding: clamp(20px, 5vw, 32px) clamp(16px, 4vw, 24px);
    margin-bottom: 20px;
}

.welcome-logo {
    width: clamp(48px, 10vw, 64px);
    height: auto;
    border-radius: 16px;
    margin-bottom: 12px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

.welcome-title {
    font-size: clamp(1.1rem, 5vw, 1.5rem);
    font-weight: 900;
    background: linear-gradient(135deg, var(--gold-light), var(--gold));
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
    margin-bottom: 4px;
}

.welcome-subtitle {
    color: var(--text-secondary);
    font-size: clamp(0.7rem, 3vw, 0.85rem);
}

.welcome-user {
    color: var(--gold-light);
    font-weight: 700;
    margin-top: 10px;
    font-size: clamp(0.8rem, 3.5vw, 0.9rem);
}

.welcome-date {
    color: var(--text-secondary);
    font-size: clamp(0.7rem, 3vw, 0.8rem);
    margin-top: 4px;
}

/* Buttons */
.btn-primary {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 10px 24px;
    background: linear-gradient(135deg, var(--gold), var(--gold-light));
    color: #1a1a1a;
    border-radius: var(--radius-sm);
    text-decoration: none;
    font-weight: 800;
    margin-top: 15px;
    box-shadow: 0 4px 15px rgba(212, 175, 55, 0.3);
    transition: var(--transition);
    cursor: pointer;
    border: none;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(212, 175, 55, 0.4);
}

.btn {
    padding: 12px 14px;
    border-radius: var(--radius-sm);
    border: 1px solid var(--glass-border);
    font-family: 'Vazirmatn';
    font-weight: 700;
    font-size: clamp(0.7rem, 3vw, 0.8rem);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    transition: var(--transition);
    flex: 1;
    min-width: 90px;
    text-align: center;
    backdrop-filter: blur(8px);
    color: #fff;
}

.btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 15px rgba(0, 0, 0, 0.2);
}

.btn-bal { background: rgba(59, 130, 246, 0.8); border-color: rgba(59, 130, 246, 0.5); }
.btn-inc { background: rgba(16, 185, 129, 0.8); border-color: rgba(16, 185, 129, 0.5); }
.btn-mon { background: linear-gradient(135deg, rgba(212, 175, 55, 0.9), rgba(184, 150, 15, 0.9)); color: #1a1a1a; border-color: rgba(212, 175, 55, 0.5); }

/* Work Timer */
.work-timer {
    padding: 12px 20px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: clamp(10px, 4vw, 20px);
    flex-wrap: wrap;
}

.work-timer-icon {
    font-size: clamp(1.2rem, 5vw, 1.5rem);
}

.work-timer-label {
    font-size: clamp(0.7rem, 3vw, 0.8rem);
    color: var(--text-secondary);
    text-align: center;
}

.work-timer-value {
    font-weight: 900;
    color: var(--gold-light);
    font-size: clamp(1rem, 4vw, 1.2rem);
    direction: ltr;
    font-family: monospace;
}

/* Calendar Widget */
.cal-widget {
    padding: clamp(12px, 4vw, 20px);
    margin-bottom: 20px;
}

.cal-hdr {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.cal-nav-arr {
    width: 40px;
    height: 40px;
    border-radius: 14px;
    border: 1px solid var(--glass-border);
    background: rgba(255, 255, 255, 0.05);
    color: var(--text);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: var(--transition);
    backdrop-filter: blur(5px);
}

.cal-nav-arr:hover {
    border-color: var(--gold-light);
    color: var(--gold-light);
    background: rgba(255, 255, 255, 0.1);
}

.cal-hdr-selects {
    display: flex;
    align-items: center;
    gap: 6px;
}

.cal-hdr-selects select {
    background: rgba(0, 0, 0, 0.1);
    border: 1px solid var(--glass-border);
    color: var(--gold-light);
    font-family: 'Vazirmatn';
    font-weight: 800;
    font-size: clamp(0.85rem, 4vw, 1rem);
    padding: 6px 12px;
    border-radius: 10px;
    cursor: pointer;
    -webkit-appearance: none;
    appearance: none;
    text-align: center;
    backdrop-filter: blur(5px);
    transition: var(--transition);
}

body.light .cal-hdr-selects select {
    background: rgba(255, 255, 255, 0.5);
}

.cal-hdr-selects select:focus {
    border-color: var(--gold-light);
    outline: none;
}

.cal-today-sm {
    padding: 6px 14px;
    border-radius: 10px;
    border: 1px solid rgba(212, 175, 55, 0.3);
    background: rgba(212, 175, 55, 0.1);
    color: var(--gold-light);
    cursor: pointer;
    font-family: 'Vazirmatn';
    font-weight: 700;
    font-size: clamp(0.7rem, 3vw, 0.8rem);
    transition: var(--transition);
}

.cal-today-sm:hover {
    background: rgba(212, 175, 55, 0.2);
    border-color: var(--gold-light);
}

/* Calendar Table - Responsive */
.cal-tbl-wrap {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    margin: 0 -8px;
    padding: 0 8px;
}

.cal-tbl {
    width: 100%;
    border-collapse: separate;
    border-spacing: 4px;
    min-width: 500px;
}

.cal-tbl th {
    text-align: center;
    padding: 10px 0;
    font-size: clamp(0.7rem, 3vw, 0.8rem);
    color: var(--text-secondary);
    font-weight: 700;
}

.cal-tbl td {
    position: relative;
    text-align: center;
    vertical-align: middle;
    padding: 8px 2px;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid var(--glass-border);
    border-radius: 12px;
    height: clamp(50px, 8vw, 65px);
    cursor: pointer;
    transition: var(--transition);
}

body.light .cal-tbl td {
    background: rgba(255, 255, 255, 0.4);
}

.cal-tbl td:hover:not(.empty) {
    transform: translateY(-3px) scale(1.02);
    background: rgba(255, 255, 255, 0.08);
    border-color: var(--glass-border-hover);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.cal-tbl td.empty {
    background: transparent;
    border-color: transparent;
    cursor: default;
}

.cal-tbl td.today {
    background: rgba(212, 175, 55, 0.1);
    border: 1px solid var(--gold-light);
    box-shadow: inset 0 0 10px rgba(212, 175, 55, 0.1);
}

.cal-tbl td.selected {
    background: rgba(96, 165, 250, 0.15) !important;
    border: 1px solid var(--accent) !important;
    box-shadow: 0 0 15px rgba(96, 165, 250, 0.2);
}

.day-num {
    display: block;
    font-weight: 800;
    font-size: clamp(0.8rem, 3.5vw, 0.9rem);
    margin-bottom: 4px;
}

.today .day-num {
    color: var(--gold-light);
    font-size: clamp(0.85rem, 4vw, 1rem);
}

.day-dots {
    display: flex;
    justify-content: center;
    gap: 4px;
    flex-wrap: wrap;
}

.day-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    box-shadow: 0 0 4px currentColor;
}

.day-dot.inc { background: var(--green); }
.day-dot.bal { background: var(--accent); }

/* Action Buttons Row */
.btn-row {
    display: flex;
    gap: clamp(6px, 2vw, 12px);
    margin-top: 20px;
    flex-wrap: wrap;
}

/* Summary Section */
.summary-sec {
    margin-top: 20px;
}

.summary-title {
    font-weight: 800;
    font-size: clamp(0.85rem, 4vw, 1rem);
    color: var(--gold-light);
    margin-bottom: 16px;
    text-align: center;
    padding: 10px;
    background: rgba(212, 175, 55, 0.05);
    border-radius: var(--radius-sm);
    border: 1px dashed rgba(212, 175, 55, 0.2);
}

.branch-summ {
    background: rgba(0, 0, 0, 0.1);
    border: 1px solid var(--glass-border);
    border-radius: var(--radius-sm);
    padding: clamp(12px, 3vw, 16px);
    margin-bottom: 12px;
}

body.light .branch-summ {
    background: rgba(255, 255, 255, 0.3);
}

.branch-name {
    font-weight: 800;
    font-size: clamp(0.8rem, 3.5vw, 0.9rem);
    color: var(--gold-light);
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.summ-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(min(100%, 120px), 1fr));
    gap: 10px;
}

.summ-item {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid var(--glass-border);
    border-radius: 10px;
    padding: 12px 8px;
    text-align: center;
    cursor: pointer;
    transition: var(--transition);
}

body.light .summ-item {
    background: rgba(255, 255, 255, 0.5);
}

.summ-item:hover {
    background: rgba(255, 255, 255, 0.08);
    transform: translateY(-2px);
    border-color: var(--glass-border-hover);
}

.summ-item .val {
    font-size: clamp(0.9rem, 4vw, 1.1rem);
    font-weight: 900;
    margin-bottom: 4px;
    word-break: break-word;
}

.summ-item .val.d { color: var(--red); }
.summ-item .val.c { color: var(--green); }
.summ-item .val.p { color: var(--purple); }
.summ-item .val.b { color: var(--amber); }
.summ-item .val.dy { color: var(--accent); }

.summ-item .lbl {
    font-size: clamp(0.6rem, 2.5vw, 0.7rem);
    color: var(--text-secondary);
    font-weight: 600;
}

/* Bottom Navigation - Clean without theme button */
.bottom-nav {
    position: fixed;
    bottom: 20px;
    left: 50%;
    transform: translateX(-50%);
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-radius: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: clamp(8px, 3vw, 16px);
    padding: 8px 20px;
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    z-index: 1000;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.4);
    width: auto;
    min-width: 260px;
    max-width: 90%;
}

body.light .bottom-nav {
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
}

.nav-btn {
    width: 48px;
    height: 48px;
    border-radius: 28px;
    border: none;
    background: transparent;
    color: var(--text-secondary);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
    transition: var(--transition);
    text-decoration: none;
}

.nav-btn.active {
    color: var(--gold-light);
    background: rgba(212, 175, 55, 0.15);
    box-shadow: inset 0 0 10px rgba(212, 175, 55, 0.1);
}

.nav-btn:hover:not(.active) {
    color: var(--text);
    background: rgba(255, 255, 255, 0.05);
}

.nav-logo {
    width: 62px;
    height: 62px;
    border-radius: 50%;
    background: linear-gradient(135deg, rgba(212, 175, 55, 0.3), rgba(212, 175, 55, 0.1));
    border: 2px solid rgba(212, 175, 55, 0.5);
    margin-top: -30px;
    box-shadow: 0 8px 25px rgba(212, 175, 55, 0.3);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    backdrop-filter: blur(10px);
    transition: transform 0.3s;
}

.nav-logo:hover {
    transform: scale(1.05);
}

.nav-logo img {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    object-fit: contain;
}

/* Tools Grid */
.tools-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(min(100%, 150px), 1fr));
    gap: clamp(12px, 3vw, 16px);
}

.tool-card {
    padding: 22px 14px;
    text-align: center;
    text-decoration: none;
    color: var(--text);
    transition: var(--transition);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

.tool-card:hover {
    transform: translateY(-4px);
    border-color: var(--glass-border-hover);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}

.tool-icon {
    font-size: clamp(1.8rem, 7vw, 2.2rem);
    margin-bottom: 10px;
    filter: drop-shadow(0 4px 6px rgba(0, 0, 0, 0.2));
}

.tool-name {
    font-weight: 800;
    font-size: clamp(0.8rem, 3.5vw, 0.9rem);
    margin-bottom: 4px;
    color: var(--gold-light);
}

.tool-desc {
    font-size: clamp(0.6rem, 2.5vw, 0.7rem);
    color: var(--text-secondary);
    line-height: 1.4;
}

/* User Status Cards */
.user-status-mini-card {
    background: var(--glass-bg);
    backdrop-filter: blur(12px);
    border: 1px solid var(--glass-border);
    border-radius: var(--radius);
    padding: 16px 12px;
    text-align: center;
    transition: var(--transition);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
}

.user-status-mini-card:hover {
    transform: translateY(-3px);
    border-color: var(--glass-border-hover);
}

.status-dot {
    width: 14px;
    height: 14px;
    border-radius: 50%;
}

.status-dot.online {
    background: var(--green);
    box-shadow: 0 0 10px var(--green);
    animation: dotPulse 2s infinite;
}

.status-dot.offline {
    background: var(--red);
    box-shadow: 0 0 6px rgba(248, 113, 113, 0.4);
}

@keyframes dotPulse {
    0%, 100% { box-shadow: 0 0 6px var(--green); }
    50% { box-shadow: 0 0 16px var(--green); }
}

.user-name {
    font-weight: 800;
    font-size: clamp(0.8rem, 3.5vw, 0.85rem);
    color: var(--text);
}

.user-status-text {
    font-size: clamp(0.7rem, 3vw, 0.75rem);
    font-weight: 700;
    color: var(--green);
}

.user-status-text.offline {
    color: var(--red);
}

.user-last-seen {
    font-size: clamp(0.6rem, 2.5vw, 0.65rem);
    color: var(--text-secondary);
    text-align: center;
}

/* Monthly Income Section */
.monthly-sec {
    margin: 20px 0;
    padding: clamp(16px, 4vw, 24px);
    background: linear-gradient(145deg, rgba(30, 41, 59, 0.7), rgba(15, 23, 42, 0.9));
    border: 1px solid rgba(212, 175, 55, 0.3);
    border-radius: 24px;
    backdrop-filter: blur(12px);
}

.monthly-sec .summary-title {
    font-size: clamp(1rem, 4vw, 1.2rem);
    margin-bottom: 20px;
    padding-bottom: 12px;
    border-bottom: 1px solid rgba(212, 175, 55, 0.2);
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.monthly-cards {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(min(100%, 300px), 1fr));
    gap: 16px;
}

.mon-card {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 20px;
    overflow: hidden;
    transition: var(--transition);
    display: flex;
    flex-direction: column;
}

.mon-card:hover {
    transform: translateY(-4px);
    border-color: rgba(212, 175, 55, 0.5);
    box-shadow: 0 12px 35px rgba(0, 0, 0, 0.3);
}

.mon-name {
    background: linear-gradient(135deg, rgba(212, 175, 55, 0.15), rgba(212, 175, 55, 0.05));
    padding: 14px 20px;
    font-weight: 800;
    font-size: clamp(0.9rem, 4vw, 1rem);
    color: #fff;
    border-bottom: 1px solid rgba(212, 175, 55, 0.2);
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.mon-amounts {
    padding: 16px 20px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.mon-amount {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 16px;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 14px;
    transition: var(--transition);
    flex-wrap: wrap;
    gap: 8px;
}

.mon-amount:hover {
    background: rgba(255, 255, 255, 0.07);
    border-color: rgba(212, 175, 55, 0.3);
}

.mon-amount > span:first-child {
    font-size: clamp(0.75rem, 3vw, 0.8rem);
    color: #94a3b8;
    font-weight: 500;
}

.mon-amount > span:last-child {
    font-size: clamp(0.85rem, 3.5vw, 0.95rem);
    font-weight: 800;
    color: #fff;
    background: rgba(255, 255, 255, 0.1);
    padding: 6px 14px;
    border-radius: 10px;
    white-space: nowrap;
    direction: ltr;
    text-align: left;
}

.mon-amount.rial > span:last-child {
    color: #60a5fa;
    background: rgba(96, 165, 250, 0.15);
}

.mon-amount.gold > span:last-child {
    color: #fcd34d;
    background: rgba(252, 211, 77, 0.15);
}

.mon-amount.monthly > span:last-child {
    color: #a78bfa;
    background: rgba(167, 139, 250, 0.15);
}

.mon-amount.total > span:last-child {
    color: #34d399;
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(52, 211, 153, 0.1));
    font-size: clamp(0.95rem, 4vw, 1.05rem);
}

.mon-link {
    display: block;
    text-align: center;
    padding: 12px 20px;
    background: rgba(212, 175, 55, 0.1);
    color: #fcd34d;
    text-decoration: none;
    font-weight: 600;
    font-size: clamp(0.8rem, 3.5vw, 0.85rem);
    transition: var(--transition);
    border-top: 1px solid rgba(212, 175, 55, 0.2);
    margin-top: auto;
}

.mon-link:hover {
    background: rgba(212, 175, 55, 0.2);
    color: #fff;
}

/* Branch Goals Section */
.branch-goals-wrapper {
    background: linear-gradient(145deg, rgba(30, 41, 59, 0.7), rgba(15, 23, 42, 0.9));
    border: 1px solid rgba(212, 175, 55, 0.3);
    border-radius: 24px;
    padding: clamp(16px, 4vw, 24px);
    margin: 20px 0;
    backdrop-filter: blur(12px);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
}

.branch-goals-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 20px;
    background: linear-gradient(135deg, rgba(212, 175, 55, 0.15), rgba(212, 175, 55, 0.05));
    border-radius: 16px;
    border: 1px solid rgba(212, 175, 55, 0.2);
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 10px;
}

.branch-name-badge {
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 800;
    font-size: clamp(0.9rem, 4vw, 1rem);
    color: #fff;
}

.branch-name-badge .icon {
    font-size: 1.4rem;
    background: rgba(212, 175, 55, 0.2);
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
}

.branch-stats-badge {
    background: rgba(16, 185, 129, 0.15);
    border: 1px solid rgba(16, 185, 129, 0.3);
    padding: 6px 16px;
    border-radius: 30px;
    font-size: clamp(0.7rem, 3vw, 0.8rem);
    color: #34d399;
    font-weight: 700;
}

.branch-overall-progress {
    background: rgba(255, 255, 255, 0.03);
    border-radius: 12px;
    padding: 12px 18px;
    margin-bottom: 22px;
    border: 1px solid rgba(255, 255, 255, 0.06);
}

.branch-progress-header {
    display: flex;
    justify-content: space-between;
    font-size: clamp(0.7rem, 3vw, 0.75rem);
    margin-bottom: 8px;
    color: #94a3b8;
    font-weight: 500;
    flex-wrap: wrap;
    gap: 8px;
}

.branch-progress-track {
    height: 8px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 10px;
    overflow: hidden;
}

.branch-progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #d4af37, #fcd34d);
    border-radius: 10px;
    transition: width 0.4s;
}

.goals-grid-pro {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(min(100%, 280px), 1fr));
    gap: 16px;
    margin-bottom: 20px;
}

.goal-card-pro {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 20px;
    padding: 18px;
    transition: var(--transition);
    position: relative;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.goal-card-pro::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #d4af37, #fcd34d);
}

.goal-card-pro.completed::before {
    background: linear-gradient(90deg, #10b981, #34d399);
}

.goal-card-pro.no-target::before {
    background: linear-gradient(90deg, #6b7280, #9ca3af);
}

.goal-card-pro:hover {
    background: rgba(255, 255, 255, 0.08);
    border-color: rgba(212, 175, 55, 0.5);
    transform: translateY(-4px);
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.25);
}

.goal-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 14px;
    gap: 10px;
    flex-wrap: wrap;
}

.goal-icon-box {
    width: 44px;
    height: 44px;
    background: rgba(212, 175, 55, 0.15);
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
    flex-shrink: 0;
}

.goal-title-box {
    flex: 1;
    min-width: 0;
}

.goal-title {
    font-weight: 700;
    font-size: clamp(0.85rem, 3.5vw, 0.9rem);
    color: #e8ecf1;
    word-break: break-word;
}

.goal-percent-badge {
    background: rgba(255, 255, 255, 0.1);
    padding: 4px 12px;
    border-radius: 20px;
    font-size: clamp(0.7rem, 3vw, 0.75rem);
    font-weight: 800;
    color: #fcd34d;
    white-space: nowrap;
}

.goal-mini-stats {
    display: flex;
    gap: 12px;
    margin-bottom: 12px;
    font-size: clamp(0.7rem, 3vw, 0.75rem);
    flex-wrap: wrap;
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 5px;
}

.stat-item.ach { color: #34d399; font-weight: 600; }
.stat-item.tgt { color: #fcd34d; font-weight: 600; }
.stat-item.no-tgt { color: #94a3b8; font-style: italic; }

.goal-progress-wrapper {
    margin: 8px 0 14px;
}

.goal-progress-bg {
    height: 10px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 10px;
    overflow: hidden;
}

.goal-progress-bar {
    height: 100%;
    background: linear-gradient(90deg, #d4af37, #fcd34d);
    border-radius: 10px;
    transition: width 0.5s;
}

.goal-card-pro.completed .goal-progress-bar {
    background: linear-gradient(90deg, #10b981, #34d399);
}

.goal-card-footer {
    margin-top: auto;
    padding-top: 14px;
    border-top: 1px dashed rgba(255, 255, 255, 0.1);
    display: flex;
    justify-content: space-between;
    gap: 10px;
    flex-wrap: wrap;
}

.footer-stat {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 3px;
}

.footer-stat .label {
    font-size: clamp(0.6rem, 2.5vw, 0.65rem);
    color: #94a3b8;
}

.footer-stat .value {
    font-size: clamp(0.8rem, 3.5vw, 0.85rem);
    font-weight: 800;
    color: #fff;
    background: rgba(255, 255, 255, 0.1);
    padding: 4px 12px;
    border-radius: 8px;
}

.btn-goals-action {
    text-align: center;
    margin-top: 10px;
}

.btn-goals-action a {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 28px;
    background: linear-gradient(135deg, #8b5cf6, #a78bfa);
    color: white;
    border-radius: 40px;
    text-decoration: none;
    font-weight: 800;
    font-size: clamp(0.8rem, 3.5vw, 0.85rem);
    transition: var(--transition);
    box-shadow: 0 4px 15px rgba(139, 92, 246, 0.3);
}

.btn-goals-action a:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(139, 92, 246, 0.5);
}

/* Light Mode Overrides */
body.light .monthly-sec,
body.light .branch-goals-wrapper {
    background: #fff;
    border-color: #e2e8f0;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
}

body.light .mon-card,
body.light .goal-card-pro {
    background: #fff;
    border-color: #e2e8f0;
}

body.light .mon-name {
    background: linear-gradient(135deg, #fffbeb, #fff);
    color: #1e293b;
    border-color: #fcd34d;
}

body.light .mon-amount {
    background: #f8fafc;
    border-color: #e2e8f0;
}

body.light .mon-amount > span:first-child {
    color: #64748b;
}

body.light .mon-amount > span:last-child {
    color: #1e293b;
    background: #e2e8f0;
}

body.light .mon-link {
    background: #fffbeb;
    color: #b45309;
    border-color: #fcd34d;
}

body.light .mon-link:hover {
    background: #fef3c7;
    color: #92400e;
}

body.light .branch-goals-header {
    background: linear-gradient(135deg, #fffbeb, #fff);
    border-color: #fcd34d;
}

body.light .goal-title {
    color: #1e293b;
}

body.light .footer-stat .value {
    background: #f1f5f9;
    color: #1e293b;
}

body.light .branch-name-badge {
    color: #1e293b;
}

/* Loading State */
.loading-state {
    text-align: center;
    padding: 40px;
    color: var(--text-secondary);
}

/* Responsive Utilities */
@media (max-width: 640px) {
    body {
        padding: 12px 12px 100px 12px;
    }
    
    .theme-toggle {
        top: 12px;
        right: 12px;
        width: 42px;
        height: 42px;
        font-size: 1.3rem;
    }
    
    .cal-tbl td {
        height: auto;
        min-height: 50px;
    }
    
    .btn-row {
        flex-direction: row;
        justify-content: center;
    }
    
    .btn {
        min-width: 70px;
        padding: 8px 10px;
    }
    
    .summ-grid {
        grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
    }
    
    .tools-grid {
        grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
    }
    
    .bottom-nav {
        gap: 6px;
        padding: 6px 16px;
        min-width: auto;
        width: auto;
        border-radius: 35px;
    }
    
    .nav-btn {
        width: 42px;
        height: 42px;
        font-size: 1.2rem;
    }
    
    .nav-logo {
        width: 54px;
        height: 54px;
        margin-top: -25px;
    }
    
    .nav-logo img {
        width: 40px;
        height: 40px;
    }
    
    .mon-amount {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .mon-amount > span:last-child {
        width: 100%;
        text-align: center;
    }
    
    .branch-goals-header {
        flex-direction: column;
        align-items: flex-start;
    }
}

/* Desktop Optimizations */
@media (min-width: 1024px) {
    .app-container {
        max-width: 1200px;
    }
    
    .tools-grid {
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    }
    
    .monthly-cards {
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    }
    
    .goals-grid-pro {
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    }
}

/* Touch Device Optimizations */
@media (hover: hover) {
    .cal-tbl td:hover:not(.empty) {
        transform: translateY(-3px) scale(1.02);
    }
}

@media (hover: none) {
    .cal-tbl td:active:not(.empty) {
        transform: scale(0.98);
    }
}
/* ========== Animated Icons ========== */
@keyframes iconRotate {
    0%, 30% { opacity: 1; transform: scale(1); }
    33%, 63% { opacity: 0; transform: scale(0.5); }
    66%, 96% { opacity: 0; transform: scale(0.5); }
    100% { opacity: 1; transform: scale(1); }
}

@keyframes iconRotate2 {
    0%, 30% { opacity: 0; transform: scale(0.5); }
    33%, 63% { opacity: 1; transform: scale(1); }
    66%, 96% { opacity: 0; transform: scale(0.5); }
    100% { opacity: 0; transform: scale(0.5); }
}

@keyframes iconRotate3 {
    0%, 30% { opacity: 0; transform: scale(0.5); }
    33%, 63% { opacity: 0; transform: scale(0.5); }
    66%, 96% { opacity: 1; transform: scale(1); }
    100% { opacity: 0; transform: scale(0.5); }
}

.animated-icon {
    position: relative;
    width: 24px;
    height: 24px;
}

.animated-icon svg {
    position: absolute;
    top: 0;
    left: 0;
    animation-duration: 4s;
    animation-iteration-count: infinite;
}

.animated-icon svg:nth-child(1) { animation-name: iconRotate; }
.animated-icon svg:nth-child(2) { animation-name: iconRotate2; }
.animated-icon svg:nth-child(3) { animation-name: iconRotate3; }

/* ========== Work Timer Icon Animations ========== */
@keyframes tickTock {
    0%, 100% { transform: rotate(0deg); }
    50% { transform: rotate(2deg); }
}

@keyframes waitingPulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

@keyframes moonFloat {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-2px); }
}

.icon-working { animation: tickTock 2s ease-in-out infinite; }
.icon-waiting { animation: waitingPulse 2s ease-in-out infinite; }
.icon-closed { animation: moonFloat 3s ease-in-out infinite; }
</style>
</head>
<body>

<!-- دکمه تغییر تم به صورت دایره در بالا راست -->
<button class="theme-toggle" id="themeToggle" aria-label="تغییر تم">
    <div class="toggle-icon-wrap">
        <div class="sun-rays">
            <div class="ray"></div><div class="ray"></div>
            <div class="ray"></div><div class="ray"></div>
            <div class="ray"></div><div class="ray"></div>
            <div class="ray"></div><div class="ray"></div>
        </div>
        <svg class="moon-icon" width="20" height="20" viewBox="0 0 24 24" fill="#fcd34d" stroke="none">
            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
        </svg>
        <div class="stars">
            <div class="star"></div><div class="star"></div><div class="star"></div>
        </div>
    </div>
</button>

<div class="app-container">
    <div class="tab-content active" id="tab-home">
        <div class="welcome-card glass-panel">
            <img src="assets/images/logo.png" alt="لوگو" class="welcome-logo" loading="lazy">
            <h1 class="welcome-title">پلتفرم تراز روزانه</h1>
            <p class="welcome-subtitle">سیستم جابجایی وجوه</p>
            <?php if ($isLoggedIn): ?>
                <p class="welcome-user">خوش آمدید، <?php echo htmlspecialchars($current_user['branch_name'] ?? $current_user['username']); ?></p>
                <p class="welcome-date">امروز: <?php echo htmlspecialchars($jalali_today); ?></p>
            <?php else: ?>
                <a href="login.php" class="btn-primary">ورود به حساب کاربری</a>
            <?php endif; ?>
        </div>

        <?php if ($work_timer): ?>
        <div class="glass-panel work-timer">
            <span class="work-timer-icon">
                <?php if ($work_timer['status'] === 'morning' || $work_timer['status'] === 'afternoon'): ?>
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#34d399" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-working">
                        <circle cx="12" cy="12" r="10"/>
                        <polyline points="12 6 12 12 16 14"/>
                    </svg>
                <?php elseif ($work_timer['status'] === 'before' || $work_timer['status'] === 'break'): ?>
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#fbbf24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-waiting">
                        <circle cx="12" cy="12" r="10"/>
                        <polyline points="12 6 12 12 16 14"/>
                    </svg>
                <?php else: ?>
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#a78bfa" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-closed">
                        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                    </svg>
                <?php endif; ?>
            </span>
            <div style="display:flex;flex-direction:column;align-items:center;">
                <span class="work-timer-label"><?php echo htmlspecialchars($work_timer['label']); ?></span>
                <?php if ($work_timer['seconds'] > 0): ?>
                    <span id="workCountdown" class="work-timer-value">--:--:--</span>
                <?php else: ?>
                    <span style="font-weight:700;color:var(--gold-light);">خسته نباشید</span>
                <?php endif; ?>
            </div>
            <input type="hidden" id="countdownSeconds" value="<?php echo $work_timer['seconds']; ?>">
        </div>
        <script>
        (function(){
            var seconds = parseInt(document.getElementById('countdownSeconds')?.value) || 0;
            var el = document.getElementById('workCountdown');
            if(!el || seconds <= 0) return;
            function formatTime(t){
                var h = Math.floor(t/3600);
                var m = Math.floor((t%3600)/60);
                var s = t%60;
                return (h>0?h+':':'')+String(m).padStart(2,'0')+':'+String(s).padStart(2,'0');
            }
            el.textContent = formatTime(seconds);
            var interval = setInterval(function(){
                seconds--;
                if(seconds <= 0){
                    el.parentElement.innerHTML = '<span style="font-weight:700;color:var(--gold-light);">ساعت کاری به پایان رسید ✨</span>';
                    clearInterval(interval);
                } else {
                    el.textContent = formatTime(seconds);
                }
            }, 1000);
        })();
        </script>
        <?php endif; ?>

        <div id="calContainer">
            <div class="glass-panel loading-state">
                <span style="font-size:2rem; display:block; margin-bottom:10px;">⏳</span> 
                در حال بارگذاری تقویم...
            </div>
        </div>

        <?php 
        $has_assigned_users = false;
        if ($isLoggedIn && $userRole === 'observer') {
            $check_query = "SELECT COUNT(*) as cnt FROM observer_assignments WHERE observer_id = $current_user_id";
            $check_result = mysqli_query($conn, $check_query);
            $check_row = mysqli_fetch_assoc($check_result);
            $has_assigned_users = ($check_row['cnt'] > 0);
        }
        ?>
        <?php if ($isLoggedIn && $userRole === 'observer' && $has_assigned_users): ?>
        <div style="margin-top: 18px; margin-bottom: 18px;">
            <div style="font-weight: 800; font-size: clamp(0.8rem, 4vw, 0.9rem); background: linear-gradient(135deg, var(--gold-light), var(--gold)); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent; margin-bottom: 12px; text-align: center;">وضعیت کاربران</div>
            <div id="usersOnlineGrid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 140px), 1fr)); gap: 10px;">
                <div class="glass-panel loading-state" style="grid-column: 1 / -1;">⏳ در حال بارگذاری...</div>
            </div>
            <div style="margin-top: 10px; text-align: center; font-size: 0.6rem; color: var(--text-secondary);">بروزرسانی خودکار</div>
        </div>
        <?php endif; ?>
    </div>

    <div class="tab-content" id="tab-tools">
        <div class="tools-grid">
            <?php foreach ($all_tools as $tool): 
                $hasAccess = $isLoggedIn && (strpos($userPermissions, $tool['slug']) !== false || $userRole === 'admin');
                $link = $isLoggedIn ? ($hasAccess ? $tool['url'] : "tool_intro.php?tool=" . $tool['slug']) : "login.php?portal=" . $tool['slug'];
            ?>
                <a href="<?php echo $link; ?>" class="tool-card glass-panel">
                    <span class="tool-icon"><?php echo getToolIcon($tool); ?></span>
                    <div class="tool-name"><?php echo $tool['name']; ?></div>
                    <div class="tool-desc"><?php echo $tool['description']; ?></div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Bottom Navigation -->
<nav class="bottom-nav">
    <a href="reports.php" class="nav-btn" title="گزارشات" style="text-decoration:none;color:inherit;">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 20V10"/>
            <path d="M12 20V4"/>
            <path d="M6 20v-6"/>
        </svg>
    </a>
    
    <div class="nav-logo active" data-tab="home" onclick="switchTab('home')" role="button" tabindex="0">
        <img src="assets/images/logo.png" alt="لوگو" loading="lazy">
    </div>
    
    <?php if ($isLoggedIn && $userRole === 'admin'): ?>
    <a href="admin/work_patterns.php" class="nav-btn" title="مدیریت الگوهای کاری" style="text-decoration:none;color:inherit">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="3"/>
            <path d="M12 1v2"/>
            <path d="M12 21v2"/>
            <path d="M4.22 4.22l1.42 1.42"/>
            <path d="M18.36 18.36l1.42 1.42"/>
            <path d="M1 12h2"/>
            <path d="M21 12h2"/>
            <path d="M4.22 19.78l1.42-1.42"/>
            <path d="M18.36 5.64l1.42-1.42"/>
        </svg>
    </a>
    <?php endif; ?>
    
    <button class="nav-btn" data-tab="tools" onclick="switchTab('tools')" aria-label="ابزارها">
        <span class="animated-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="4" y="2" width="16" height="20" rx="2"/>
                <line x1="8" y1="6" x2="16" y2="6"/>
                <line x1="8" y1="10" x2="16" y2="10"/>
                <line x1="8" y1="14" x2="12" y2="14"/>
                <line x1="8" y1="18" x2="12" y2="18"/>
            </svg>
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                <polyline points="14 2 14 8 20 8"/>
                <line x1="16" y1="13" x2="8" y2="13"/>
                <line x1="16" y1="17" x2="8" y2="17"/>
                <polyline points="10 9 9 9 8 9"/>
            </svg>
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="6 9 6 2 18 2 18 9"/>
                <path d="M6 12H4a2 2 0 0 0-2 2v4a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-4a2 2 0 0 0-2-2h-2"/>
                <rect x="6" y="14" width="12" height="8"/>
            </svg>
        </span>
    </button>
</nav>

<script>
window.curYear = <?php echo $current_year; ?>;
window.curMonth = <?php echo $current_month; ?>;
var userRole = '<?php echo $userRole; ?>';
window.selectedCalDate = '<?php echo $todayDateEn; ?>';

window.goToRegPage = function(page, isMonthly) {
    isMonthly = isMonthly || false;
    var date = window.selectedCalDate || '<?php echo $todayDateEn; ?>';
    if (!date || date === 'undefined' || date === '') {
        alert('لطفاً ابتدا یک روز را از تقویم انتخاب کنید');
        return;
    }
    date = date.replace(/\//g, '-');
    if (isMonthly) {
        var parts = date.split('-');
        window.location.href = page + '?year=' + parts[0] + '&month=' + parts[1] + '&date=' + date;
    } else {
        window.location.href = page + '?date=' + date;
    }
};

window.showSum = function(dk, du) {
    window.selectedCalDate = dk;
    var allData = window.calAllData || {};
    
    var cells = document.querySelectorAll('.day-cell');
    for (var i = 0; i < cells.length; i++) cells[i].classList.remove('selected');
    var cell = document.querySelector('[data-date="' + dk + '"]');
    if (cell) cell.classList.add('selected');
    
    var h = '', has = false;
    for (var bid in allData) {
        var br = allData[bid], ba = br.bal[dk] || null, inc = br.inc[dk] || {rial:0, gold:0};
        if (!ba && inc.rial == 0 && inc.gold == 0) continue;
        has = true;
        
        h += '<div class="branch-summ"><div class="branch-name">' + br.name + '</div><div class="summ-grid">';
        
        if (ba && ba.de != 0) {
            h += '<div class="summ-item" onclick="goToBal(\'' + dk + '\', ' + bid + ')">';
            h += '<div class="val d">' + Number(ba.de).toLocaleString() + '</div><div class="lbl">بدهکاران</div></div>';
        }
        
        if (ba && ba.cr != 0) {
            h += '<div class="summ-item" onclick="goToBal(\'' + dk + '\', ' + bid + ')">';
            h += '<div class="val c">' + Number(ba.cr).toLocaleString() + '</div><div class="lbl">بستانکاران</div></div>';
        }
        
        if (ba && (ba.de != 0 || ba.cr != 0)) {
            var diff = ba.cr - ba.de;
            if (diff !== 0) {
                h += '<div class="summ-item" onclick="goToBal(\'' + dk + '\', ' + bid + ')">';
                h += '<div class="val" style="color:' + (diff < 0 ? 'var(--accent)' : 'var(--red)') + ';">' + Number(Math.abs(diff)).toLocaleString() + '</div>';
                h += '<div class="lbl">' + (diff < 0 ? 'فزونی' : 'کسری') + '</div></div>';
            }
        }
        
        if (ba && ba.pe != 0) {
            h += '<div class="summ-item" onclick="goToBal(\'' + dk + '\', ' + bid + ')">';
            h += '<div class="val p">' + Number(ba.pe).toLocaleString() + '</div><div class="lbl">تنخواه</div></div>';
        }
        
        if (ba && ba.ba != 0) {
            h += '<div class="summ-item" onclick="goToBal(\'' + dk + '\', ' + bid + ')">';
            h += '<div class="val b">' + Number(ba.ba).toLocaleString() + '</div><div class="lbl">بنکداران</div></div>';
        }
        
        if (ba && ba.dy != 0) {
            h += '<div class="summ-item" onclick="goToBal(\'' + dk + '\', ' + bid + ')">';
            h += '<div class="val dy">' + Number(ba.dy).toLocaleString() + '</div><div class="lbl">داینامیک</div></div>';
        }
        
        if (inc.rial != 0 || inc.gold != 0) {
            h += '<div class="summ-item" onclick="goToInc(\'' + dk + '\', ' + bid + ')">';
            if (inc.rial != 0) h += '<div class="val" style="color:' + (inc.rial < 0 ? 'var(--red)' : 'var(--green)') + ';">' + Number(inc.rial).toLocaleString() + ' ریال</div>';
            if (inc.gold != 0) h += '<div class="val" style="color:' + (inc.gold < 0 ? 'var(--red)' : 'var(--gold-light)') + ';">' + Number(inc.gold).toLocaleString() + ' گرم</div>';
            h += '<div class="lbl">درآمد</div></div>';
        }
        
        h += '</div></div>';
    }
    
    var sumTitle = document.getElementById('sumTitle');
    var sumContent = document.getElementById('sumContent');
    if (sumTitle) sumTitle.innerHTML = has ? 'گزارش مالی روز: <span style="color:#fff;">' + du + '</span>' : 'برای مشاهده جزئیات، روی یک روز کلیک کنید';
    if (sumContent) sumContent.innerHTML = has ? h : '<div class="loading-state">هیچ تراکنشی در این روز ثبت نشده است</div>';
};

function goToBal(dk, bid) {
    if (userRole === 'branch') {
        window.location.href = 'user/index.php?date=' + dk;
    } else {
        window.location.href = 'view.php?date=' + dk.replace(/-/g, '/') + '&branch_id=' + bid + '&tab=balance';
    }
}

function goToInc(dk, bid) {
    if (userRole === 'branch') {
        window.location.href = 'income/index.php?date=' + dk;
    } else {
        window.location.href = 'view.php?date=' + dk.replace(/-/g, '/') + '&branch_id=' + bid + '&tab=income';
    }
}

window.loadCal = function(y, m) {
    var container = document.getElementById('calContainer');
    if (!container) return;
    
    container.innerHTML = '<div class="glass-panel loading-state"><span style="font-size:2rem; display:block; margin-bottom:10px;">⏳</span>در حال بارگذاری...</div>';
    
    var xhr = new XMLHttpRequest();
    var loaded = false;
    
    xhr.timeout = 30000;
    
    xhr.open('GET', 'calendar_content.php?year=' + y + '&month=' + m + '&_=' + Date.now(), true);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    
    xhr.onload = function() {
        if (loaded) return;
        loaded = true;
        
        if (xhr.status >= 200 && xhr.status < 400) {
            container.innerHTML = xhr.responseText;
            
            var scripts = container.querySelectorAll('script');
            for (var i = 0; i < scripts.length; i++) {
                var newScript = document.createElement('script');
                newScript.textContent = scripts[i].textContent;
                document.body.appendChild(newScript);
                scripts[i].remove();
            }
        } else {
            container.innerHTML = '<div class="welcome-card glass-panel">' +
                '<p style="color: var(--red); margin-bottom: 10px;">⚠️ خطا در بارگذاری تقویم</p>' +
                '<button onclick="window.loadCal(' + y + ', ' + m + ')" class="btn-primary" style="font-size: 0.8rem;">🔄 بارگذاری مجدد</button>' +
            '</div>';
        }
    };
    
    xhr.onerror = function() {
        if (loaded) return;
        loaded = true;
        container.innerHTML = '<div class="welcome-card glass-panel">' +
            '<p style="color: var(--red); margin-bottom: 10px;">⚠️ خطا در بارگذاری تقویم</p>' +
            '<button onclick="window.loadCal(' + y + ', ' + m + ')" class="btn-primary" style="font-size: 0.8rem;">🔄 بارگذاری مجدد</button>' +
        '</div>';
    };
    
    xhr.ontimeout = function() {
        if (loaded) return;
        loaded = true;
        container.innerHTML = '<div class="welcome-card glass-panel">' +
            '<p style="color: var(--red); margin-bottom: 10px;">⚠️ خطا در بارگذاری تقویم</p>' +
            '<button onclick="window.loadCal(' + y + ', ' + m + ')" class="btn-primary" style="font-size: 0.8rem;">🔄 بارگذاری مجدد</button>' +
        '</div>';
    };
    
    xhr.send();
};

window.navCalMonth = function(delta) {
    var yearSelect = document.getElementById('calYear');
    var monthSelect = document.getElementById('calMonth');
    if (!yearSelect || !monthSelect) return;
    var y = parseInt(yearSelect.value);
    var m = parseInt(monthSelect.value) + delta;
    if (m > 12) { m = 1; y++; }
    if (m < 1) { m = 12; y--; }
    window.curYear = y;
    window.curMonth = m;
    window.loadCal(y, m);
};

window.goToday = function() {
    window.curYear = <?php echo $current_year; ?>;
    window.curMonth = <?php echo $current_month; ?>;
    window.loadCal(window.curYear, window.curMonth);
};

function switchTab(tab) {
    document.querySelectorAll('.tab-content').forEach(function(t) { t.classList.remove('active'); });
    document.querySelectorAll('.nav-logo, .nav-btn').forEach(function(n) { n.classList.remove('active'); });
    var target = document.getElementById('tab-' + tab);
    if (target) target.classList.add('active');
    var btn = document.querySelector('[data-tab="' + tab + '"]');
    if (btn) btn.classList.add('active');
    if (tab === 'home') window.loadCal(window.curYear, window.curMonth);
}

function toggleTheme() {
    document.body.classList.toggle('light');
    const isLight = document.body.classList.contains('light');
    localStorage.setItem('theme', isLight ? 'light' : 'dark');
}

function applySavedTheme() {
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'light') {
        document.body.classList.add('light');
    } else {
        document.body.classList.remove('light');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    applySavedTheme();
    
    const themeBtn = document.getElementById('themeToggle');
    if (themeBtn) {
        themeBtn.removeAttribute('onclick');
        themeBtn.addEventListener('click', toggleTheme);
    }
    
    // این سه خط رو اضافه کن:
    window.loadCal(window.curYear, window.curMonth);
    if (userRole === 'observer') {
        loadUsersMiniCards();
        setInterval(loadUsersMiniCards, 30000);
    }
    fetch('update_activity.php');
    setInterval(function() { fetch('update_activity.php'); }, 120000);
});

function loadUsersMiniCards() {
    fetch('update_activity.php')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.users && data.users.length > 0) renderMiniCards(data.users);
            else {
                var grid = document.getElementById('usersOnlineGrid');
                if (grid) grid.innerHTML = '<div class="glass-panel loading-state" style="grid-column:1/-1;">هیچ کاربری تخصیص داده نشده</div>';
            }
        })
        .catch(function() {
            var grid = document.getElementById('usersOnlineGrid');
            if (grid) grid.innerHTML = '<div class="glass-panel loading-state" style="grid-column:1/-1;color:var(--red);">خطا در دریافت اطلاعات</div>';
        });
}

function renderMiniCards(users) {
    var grid = document.getElementById('usersOnlineGrid');
    if (!grid) return;
    var html = '';
    users.forEach(function(user) {
        var isOnline = user.is_online;
        var dotClass = isOnline ? 'online' : 'offline';
        var statusText = isOnline ? 'آنلاین' : 'آفلاین';
        var statusClass = isOnline ? '' : 'offline';
        var lastSeenTime = '', lastSeenDate = '';
        if (isOnline) {
            lastSeenTime = 'همین الان';
        } else if (user.last_activity_shamsi) {
            var parts = user.last_activity_shamsi.split(' ');
            if (parts.length === 2) {
                lastSeenDate = parts[0];
                lastSeenTime = parts[1];
            } else {
                lastSeenDate = user.last_activity_shamsi;
            }
        } else {
            lastSeenTime = 'نامشخص';
        }
        html += '<div class="user-status-mini-card">' +
                    '<div class="status-dot ' + dotClass + '"></div>' +
                    '<div class="user-name">' + escapeHtml(user.full_name || user.username) + '</div>' +
                    '<div class="user-status-text ' + statusClass + '">' + statusText + '</div>' +
                    '<div class="user-last-seen">' + 
                        (lastSeenTime ? '<span>' + lastSeenTime + '</span>' : '') + 
                        (lastSeenDate ? '<span>' + lastSeenDate + '</span>' : '') + 
                    '</div>' +
                '</div>';
    });
    grid.innerHTML = html;
}

function escapeHtml(text) {
    if (!text) return '';
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

</script>
</body>
</html>