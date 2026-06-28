<?php
/**
 * ============================================
 * فایل: index.php
 * توضیح: داشبورد اصلی پلتفرم تراز روزانه
 * شامل منطق PHP و ساختار اصلی HTML
 * ============================================
 */

// ** شروع بافر خروجی برای فشرده‌سازی **
if (substr_count($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip')) ob_start("ob_gzhandler");
else ob_start();

// ** تنظیم هدرها **
header('Cache-Control: no-cache, must-revalidate'); // هدرهای کش
header('Content-Type: text/html; charset=utf-8'); // نوع محتوا

// ** بارگذاری فایل‌های کانفیگ و توابع اصلی **
define('SECURE_ACCESS', true);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/reminder_functions.php';
require_once __DIR__ . '/includes/jdf.php';

// ** وضعیت کاربر **
$isLoggedIn = isLoggedIn();
$userRole = $_SESSION['role'] ?? '';
$current_user = $isLoggedIn ? getCurrentUser() : null;
$current_user_id = $_SESSION['user_id'] ?? 0;
$userPermissions = $current_user['permissions'] ?? '';

// ** دریافت لیست ابزارها **
$all_tools = [];
$tools_query = "SELECT * FROM tools WHERE active = 1 ORDER BY name";
$tools_result = mysqli_query($conn, $tools_query);
while ($t = mysqli_fetch_assoc($tools_result)) $all_tools[] = $t;

// ** تاریخ جلالی امروز **
$jalali_today = jdate('l j F Y');

// ** تابع تبدیل اعداد فارسی به انگلیسی **
function fa_to_en($str) {
    return str_replace(['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'], 
                       ['0','1','2','3','4','5','6','7','8','9'], $str);
}

// ** متغیرهای سال و ماه جاری برای تقویم **
$current_year = (int) fa_to_en(jdate('Y'));
$current_month = (int) fa_to_en(jdate('m'));
$todayDateEn = fa_to_en(jdate('Y-m-d'));
$test_day = (int)fa_to_en(jdate('w'));
echo '<!-- day_of_week: ' . $test_day . ' -->';

// ** تابع دریافت الگوی کاری کاربر **
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

// ** تابع محاسبه تایمر شروع/پایان کار **
function getWorkCountdownData($pattern) {
    if (!$pattern) return null;
    
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

// ** محاسبه تایمر کاربر جاری **
$work_timer = null;
if ($isLoggedIn && $current_user_id) {
    $pattern = getUserWorkPattern($conn, $current_user_id);
    if ($pattern) $work_timer = getWorkCountdownData($pattern);
}

// ⭐ DEBUG — اینو اضافه کن
echo '<!-- DEBUG: now=' . time() . ' | a_start=' . strtotime(date('Y-m-d') . ' 17:00') . ' | a_end=' . strtotime(date('Y-m-d') . ' 20:45') . ' | status=' . ($work_timer['status'] ?? 'null') . ' | seconds=' . ($work_timer['seconds'] ?? 'null') . ' -->';

// ** تابع تولید SVG برای آیکون ابزارها **
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

// چک کردن ثبت امروز و ساخت هشدار در لحظه
if ($isLoggedIn && $userRole === 'branch') {
    include __DIR__ . '/cron/nightly_reminder.php';
}

// ** تعداد اعلان‌ها **
$notificationCount = 0;
if ($isLoggedIn) {
    if ($userRole === 'branch') {
        $notificationCount = getNotificationCount($current_user_id);
    }
}

// ** بررسی ناظرین برای نمایش کاربران آنلاین **
$has_assigned_users = false;
if ($isLoggedIn && $userRole === 'observer') {
    $check_query = "SELECT COUNT(*) as cnt FROM observer_assignments WHERE observer_id = $current_user_id";
    $check_result = mysqli_query($conn, $check_query);
    $check_row = mysqli_fetch_assoc($check_result);
    $has_assigned_users = ($check_row['cnt'] > 0);
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
    
    <!-- فونت وزیر متن -->
    <link href="assets/fonts/fonts.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="assets/images/logo.png">
    <link rel="apple-touch-icon" href="assets/images/logo.png">
    
    <!-- استایل‌های اصلی -->
    <link href="assets/css/style.css?v=1.0" rel="stylesheet">
    <script src="assets/js/main.js?v=1.0"></script>
        <!-- ========== استایل‌های لودینگ ========== -->
    <style>
        #preloader {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: transparent;
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            transition: opacity 0.6s ease-out, visibility 0.6s ease-out;
        }
        .preloader-hidden {
            opacity: 0;
            visibility: hidden;
        }
        @keyframes lineMove {
            0%, 100% { transform: translateX(0); }
            50% { transform: translateX(12px); }
        }
        @keyframes lineMoveReverse {
            0%, 100% { transform: translateX(0); }
            50% { transform: translateX(-12px); }
        }
        @keyframes shrinkCircle {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(0.6); }
        }
        .top-part {
            animation: lineMove 2s ease-in-out infinite;
        }
        .bottom-part {
            animation: lineMoveReverse 2s ease-in-out infinite;
        }
        .circle-shrink {
            animation: shrinkCircle 2s ease-in-out infinite;
            transform-origin: 183.7px 146.65px;
        }
        .loading-text {
            text-align: center;
            margin-top: 30px;
            font-size: 13px;
            letter-spacing: 2px;
            color: inherit;
        }
        .dot {
            display: inline-block;
            animation: dotBlink 1.4s ease-in-out infinite;
        }
        .dot:nth-child(1) { animation-delay: 0s; }
        .dot:nth-child(2) { animation-delay: 0.2s; }
        .dot:nth-child(3) { animation-delay: 0.4s; }
        @keyframes dotBlink {
            0%, 60% { opacity: 0.2; }
            30% { opacity: 1; }
        }
        #main-content {
            display: none;
        }
    </style>
    
    <!-- پارامترهای اولیه برای فایل JS -->
    <script>
        window.curYear = <?php echo $current_year; ?>;
        window.curMonth = <?php echo $current_month; ?>;
        var userRole = '<?php echo $userRole; ?>';
        window.selectedCalDate = '<?php echo $todayDateEn; ?>';
    </script>
</head>
<body>
        <!-- ========== لودینگ لوگو انتظار ========== -->
    <div id="preloader">
        <div style="text-align: center;">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 367.34 292.35" width="160" height="160">
                <defs>
                    <linearGradient id="allBlue" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stop-color="#2b638d"/>
                        <stop offset="50%" stop-color="#1c4671"/>
                        <stop offset="100%" stop-color="#142c4e"/>
                    </linearGradient>
                </defs>
                <path class="top-part" fill="url(#allBlue)" fill-rule="evenodd" d="M44.86,138.87S41.96,12.24,164.25,12.24c80.47,0,203.09,0,203.09,0l-84.16,86.67.41-27.93s-44.28-1.34-106.87.08c-35.82.81-100.47,36.71-55.81,140.64"/>
                <path class="bottom-part" fill="url(#allBlue)" fill-rule="evenodd" d="M322.47,156.23s2.9,126.64-119.39,126.64c-80.47,0-203.09,0-203.09,0l84.16-86.67-.41,27.93s44.28,1.34,106.87-.08c35.82-.81,100.47-36.71,55.81-140.64"/>
                <circle class="circle-shrink" fill="url(#allBlue)" cx="183.7" cy="146.65" r="45.55"/>
            </svg>
            <div class="loading-text">
                در حال بارگذاری
                <span class="dot">.</span><span class="dot">.</span><span class="dot">.</span>
            </div>
        </div>
    </div>

    <div id="main-content">

    <!-- دکمه تغییر تم (تاریک/روشن) -->
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

    <?php if ($isLoggedIn && ($userRole === 'branch' || $userRole === 'observer')): ?>
    <!-- زنگوله اعلان‌ها -->
    <div class="notification-bell <?php echo $notificationCount > 0 ? 'has-notif' : ''; ?>" 
         onclick="openNotifPopup()" 
         id="notifBell"
         title="اعلان‌ها">
        <svg class="bell-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
            <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
        </svg>
        <?php if ($notificationCount > 0): ?>
            <span class="notif-badge"><?php echo $notificationCount; ?></span>
        <?php endif; ?>
    </div>

    <!-- پاپ‌آپ اعلان‌ها -->
    <div class="notif-popup-overlay" id="notifOverlay" onclick="closeNotifPopup()"></div>
    <div class="notif-popup glass-panel" id="notifPopup">
        <div class="loading-state">⏳ در حال بارگذاری...</div>
    </div>
    <?php endif; ?>

    <div class="app-container">
        <!-- تب خانه: داشبورد اصلی -->
        <div class="tab-content active" id="tab-home">
            <!-- کارت خوش‌آمدگویی -->
            <div class="welcome-card glass-panel">
                <img src="assets/images/logo.png" alt="لوگو" class="welcome-logo" loading="lazy">
                <h1 class="welcome-title">پلتفرم تراز روزانه</h1>
                <p class="welcome-subtitle">سیستم جابجایی وجوه</p>
                <?php if ($isLoggedIn): ?>
                    <p class="welcome-user">خوش آمدید، <?php echo htmlspecialchars($current_user['branch_name'] ?? $current_user['username']); ?></p>
                <?php else: ?>
                    <a href="login.php" class="btn-primary">ورود به حساب کاربری</a>
                <?php endif; ?>
            </div>

            <!-- تایمر شروع/پایان کار -->
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
    <span id="workCountdown" class="work-timer-value">
        <?php 
            $h = floor($work_timer['seconds'] / 3600);
            $m = floor(($work_timer['seconds'] % 3600) / 60);
            $s = $work_timer['seconds'] % 60;
            echo ($h > 0 ? $h . ':' : '') . str_pad($m, 2, '0', STR_PAD_LEFT) . ':' . str_pad($s, 2, '0', STR_PAD_LEFT);
        ?>
    </span>
<?php else: ?>
    <span style="font-weight:700;color:var(--gold-light);">خسته نباشید</span>
<?php endif; ?>
                </div>
                <input type="hidden" id="countdownSeconds" value="<?php echo $work_timer['seconds']; ?>">
            </div>
            <?php endif; ?>

            <!-- بخش تقویم (با AJAX بارگذاری می‌شود) -->
            <div id="calContainer">
                <div class="glass-panel loading-state">
                    <span style="font-size:2rem; display:block; margin-bottom:10px;">⏳</span> 
                    در حال بارگذاری تقویم...
                </div>
            </div>
                        <!-- ===== درآمد ماهانه شعب ===== -->
            <?php if (false): ?>
            <div class="monthly-sec">
                <div class="summary-title">💰 درآمد ماهانه شعب</div>
                <div class="monthly-cards">
                    <?php
                    $month_query = "SELECT b.id as branch_id, b.name as branch_name, 
                                           COALESCE(SUM(i.rial_amount), 0) as total_rial,
                                           COALESCE(SUM(i.gold_amount), 0) as total_gold
                                    FROM branches b
                                    LEFT JOIN incomes i ON b.id = i.branch_id 
                                        AND i.jalali_year = $current_year 
                                        AND i.jalali_month = $current_month
                                    WHERE b.active = 1
                                    GROUP BY b.id, b.name";
                    $month_result = mysqli_query($conn, $month_query);
                    while ($b = mysqli_fetch_assoc($month_result)):
                    ?>
                    <div class="mon-card">
                        <div class="mon-name">🏢 <?php echo htmlspecialchars($b['branch_name']); ?></div>
                        <div class="mon-amounts">
                            <div class="mon-amount rial">
                                <span>ریال</span>
                                <span><?php echo number_format($b['total_rial']); ?></span>
                            </div>
                            <div class="mon-amount gold">
                                <span>طلا (گرم)</span>
                                <span><?php echo number_format($b['total_gold'], 2); ?></span>
                            </div>
                        </div>
                        <a href="reports.php?branch=<?php echo $b['branch_id'] ?? 0; ?>" class="mon-link">مشاهده گزارش</a>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- ===== پیشرفت اهداف شعب ===== -->
            <?php if (false): ?>
            <div class="branch-goals-wrapper">
                <?php
                $goals_query = "SELECT g.*, b.name as branch_name 
                                FROM branch_goals g 
                                INNER JOIN branches b ON g.branch_id = b.id 
                                WHERE g.is_active = 1 
                                AND g.jalali_year = $current_year 
                                AND g.jalali_month = $current_month";
                
                if ($userRole === 'branch') {
                    $goals_query .= " AND g.branch_id IN (SELECT id FROM branches WHERE user_id = $current_user_id)";
                }
                
                $goals_result = mysqli_query($conn, $goals_query);
                while ($goal = mysqli_fetch_assoc($goals_result)):
                    $progress = $goal['current_amount'] / max($goal['target_amount'], 1) * 100;
                    $progress = min(100, $progress);
                ?>
                <div class="goal-card-pro">
                    <div class="goal-card-header">
                        <div class="goal-icon-box">🎯</div>
                        <div class="goal-title-box">
                            <div class="goal-title"><?php echo htmlspecialchars($goal['name']); ?></div>
                            <div style="font-size:0.7rem; color:#94a3b8;"><?php echo htmlspecialchars($goal['branch_name']); ?></div>
                        </div>
                        <div class="goal-percent-badge"><?php echo round($progress, 1); ?>%</div>
                    </div>
                    
                    <div class="goal-mini-stats">
                        <div class="stat-item ach">✅ <?php echo number_format($goal['current_amount']); ?></div>
                        <div class="stat-item tgt">🎯 <?php echo number_format($goal['target_amount']); ?></div>
                    </div>
                    
                    <div class="goal-progress-wrapper">
                        <div class="goal-progress-bg">
                            <div class="goal-progress-bar" style="width: <?php echo $progress; ?>%;"></div>
                        </div>
                    </div>
                    
                    <div class="goal-card-footer">
                        <div class="footer-stat">
                            <span class="label">مانده تا هدف</span>
                            <span class="value"><?php echo number_format(max(0, $goal['target_amount'] - $goal['current_amount'])); ?></span>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            <?php endif; ?>

            <!-- وضعیت کاربران آنلاین برای ناظرین -->
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

        <!-- تب ابزارها -->
        <div class="tab-content" id="tab-tools">
            <div class="tools-grid">
                                <?php foreach ($all_tools as $tool): 
                    $hasAccess = $isLoggedIn && (strpos($userPermissions, $tool['slug']) !== false || $userRole === 'admin');
                    if (!$hasAccess) continue;
                    $link = $tool['url'];
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

    <!-- نوار ناوبری پایین -->
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
    
    <!-- ===== یادآوری ثبت گزارش ===== -->
    <?php if ($isLoggedIn && $userRole === 'branch'): ?>
    <script>
    let reminderChecked = false;
    let reminderAudio = null;

    function checkReminder() {
        if (reminderChecked) return;
        
        fetch('/api/check_reminder.php')
            .then(res => res.json())
            .then(data => {
                if (data.remind) {
                    reminderChecked = true;
                    
                    // پخش صدا
                    try {
                        reminderAudio = new Audio('/assets/sounds/reminder.mp3');
                        reminderAudio.play();
                    } catch(e) {
                        console.log('پخش صدا ناموفق:', e);
                    }
                    
                    // باز کردن پاپ‌آپ
                    if (typeof openNotifPopup === 'function') {
                        setTimeout(() => openNotifPopup(), 500);
                    }
                }
            })
            .catch(err => console.log('خطای checkReminder:', err));
    }

    // هر ۳۰ ثانیه چک کن
    setInterval(checkReminder, 30000);

    // اول بار بعد از ۲ ثانیه چک کن
    setTimeout(checkReminder, 2000);
    </script>
    <?php endif; ?>
    
        </div> <!-- بستن main-content -->

    <script>
    window.addEventListener('load', function() {
        const preloader = document.getElementById('preloader');
        const mainContent = document.getElementById('main-content');
        
        if (preloader && mainContent) {
            preloader.classList.add('preloader-hidden');
            mainContent.style.display = 'block';
            
            setTimeout(function() {
                preloader.remove();
            }, 600);
        }
    });
</script>
    
</body>
</html>
<?php
// ** پایان بافر خروجی **
ob_end_flush();
?>