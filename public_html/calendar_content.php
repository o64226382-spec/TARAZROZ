<?php
define('SECURE_ACCESS', true);
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/jdf.php';
require_once 'includes/icons.php';

// تنظیم هدرهای مهم برای شبکه‌های مختلف
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('Access-Control-Allow-Origin: *');
header('X-Content-Type-Options: nosniff');
header('Connection: keep-alive');

// فعال کردن gzip compression برای کاهش حجم
if (!ob_start("ob_gzhandler")) ob_start();

// لاگین چک سریع
if (!isLoggedIn()) { 
    ob_end_clean();
    echo '<p style="text-align:center;padding:20px;">لطفا وارد شوید</p>'; 
    exit; 
}

$user = getCurrentUser();
$role = $user['role'];
$uid = $_SESSION['user_id'];

// تابع تبدیل اعداد فارسی به انگلیسی - بهینه‌شده
function fa_to_en($str) {
    $persian = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
    $english = ['0','1','2','3','4','5','6','7','8','9'];
    return str_replace($persian, $english, $str);
}

// دریافت و اعتبارسنجی پارامترها
$year = isset($_GET['year']) ? (int)fa_to_en($_GET['year']) : (int)jdate('Y');
$month = isset($_GET['month']) ? (int)fa_to_en($_GET['month']) : (int)jdate('m');

// محدود کردن مقادیر
$year = max(1300, min(1500, $year));
$month = max(1, min(12, $month));

// محاسبه تعداد روزهای ماه
if ($month <= 6) $dim = 31;
elseif ($month <= 11) $dim = 30;
else $dim = (jdate('L', $year, 0, 0, 0, $year) ? 30 : 29);

// محاسبه روز شروع هفته
list($gy, $gm, $gd) = jalali_to_gregorian($year, $month, 1);
$ts = mktime(0, 0, 0, $gm, $gd, $gy);
$fdow = (date('w', $ts) + 1) % 7;

$today = fa_to_en(jdate('Y-m-d'));
$todayDateEn = $today;
$mn = ['', 'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
$sd = sprintf("%04d-%02d-%02d", $year, $month, 1);
$ed = sprintf("%04d-%02d-%02d", $year, $month, $dim);

// ====== کش کردن نتایج دیتابیس برای پرفورمنس ======
$branches = [];
if ($role === 'branch') {
    $branches[] = ['id' => $uid, 'name' => $user['branch_name'] ?? 'شعبه'];
} else {
    $q = $conn->prepare("SELECT u.id, u.branch_name FROM observer_assignments oa JOIN users u ON oa.branch_id = u.id WHERE oa.observer_id = ?");
    $q->bind_param("i", $uid);
    $q->execute();
    $r = $q->get_result();
    while ($b = $r->fetch_assoc()) $branches[] = ['id' => $b['id'], 'name' => $b['branch_name']];
    $q->close();
}

// ====== دریافت همه داده‌ها با یک کوئری بهینه ======
$allData = [];
$branchIds = array_column($branches, 'id');

if (!empty($branchIds)) {
    $placeholders = implode(',', array_fill(0, count($branchIds), '?'));
    
    // کوئری ترکیبی برای balance
    $types = str_repeat('i', count($branchIds)) . 'ss';
    $params = $branchIds;
    $params[] = $sd;
    $params[] = $ed;
    
    $q = $conn->prepare("SELECT id, user_id, report_date, report_data FROM daily_reports WHERE user_id IN ($placeholders) AND report_date BETWEEN ? AND ?");
    
    // bind_param داینامیک
    $bindParams = [$types];
    foreach ($params as $key => $value) {
        $bindParams[] = &$params[$key];
    }
    call_user_func_array([$q, 'bind_param'], $bindParams);
    
    $q->execute();
    $r = $q->get_result();
    
    $reportIds = [];
    $tempBal = [];
    while ($row = $r->fetch_assoc()) {
        $d = json_decode($row['report_data'], true);
        $de = 0; $cr = 0; $pe = 0; $ba = 0; $ceiling = 1000;
if (isset($d['debtors'])) foreach ($d['debtors'] as $x) $de += (float)($x['amt'] ?? 0);
if (isset($d['creditors'])) foreach ($d['creditors'] as $x) $cr += (float)($x['amt'] ?? 0);
if (isset($d['pettys'])) foreach ($d['pettys'] as $x) $pe += (float)($x['amt'] ?? 0);
if (isset($d['ceiling'])) $ceiling = (float)$d['ceiling'];
$pe_status = $pe - $ceiling;
if (isset($d['bankers'])) foreach ($d['bankers'] as $x) $ba += (float)($x['amt'] ?? 0);
        
        $tempBal[$row['user_id']][$row['report_date']] = [
    'id' => $row['id'],
    'de' => $de, 'cr' => $cr, 'pe' => $pe_status, 'ba' => $ba, 'dy' => 0
];
        $reportIds[] = $row['id'];
    }
    $q->close();
    
    // کوئری dynamic records
    if (!empty($reportIds)) {
        $rPlaceholders = implode(',', array_fill(0, count($reportIds), '?'));
        $rTypes = str_repeat('i', count($reportIds));
        
        $q = $conn->prepare("SELECT report_id, SUM(amount_gram) as t FROM dynamic_records WHERE report_id IN ($rPlaceholders) GROUP BY report_id");
        $rParams = $reportIds;
        $rBindParams = [$rTypes];
        foreach ($rParams as $key => $value) {
            $rBindParams[] = &$rParams[$key];
        }
        call_user_func_array([$q, 'bind_param'], $rBindParams);
        $q->execute();
        $r = $q->get_result();
        while ($row = $r->fetch_assoc()) {
            foreach ($tempBal as &$userData) {
                foreach ($userData as &$dayData) {
                    if ($dayData['id'] == $row['report_id']) {
                        $dayData['dy'] = (float)($row['t'] ?? 0);
                    }
                }
            }
        }
        $q->close();
    }
    
    // کوئری income
    $q = $conn->prepare("SELECT created_by, record_date, SUM(amount_rial) as rial, SUM(amount_gram) as gold FROM income_daily_records WHERE created_by IN ($placeholders) AND record_date BETWEEN ? AND ? GROUP BY created_by, record_date");
    $incParams = $branchIds;
    $incParams[] = $sd;
    $incParams[] = $ed;
    $incTypes = str_repeat('i', count($branchIds)) . 'ss';
    $incBindParams = [$incTypes];
    foreach ($incParams as $key => $value) {
        $incBindParams[] = &$incParams[$key];
    }
    call_user_func_array([$q, 'bind_param'], $incBindParams);
    $q->execute();
    $r = $q->get_result();
    
    $tempInc = [];
    while ($row = $r->fetch_assoc()) {
        $tempInc[$row['created_by']][$row['record_date']] = [
            'rial' => (float)($row['rial'] ?? 0),
            'gold' => (float)($row['gold'] ?? 0)
        ];
    }
    $q->close();
    
    // ترکیب داده‌ها
    foreach ($branches as $branch) {
        $bid = $branch['id'];
        $allData[$bid] = [
            'name' => $branch['name'],
            'bal' => $tempBal[$bid] ?? [],
            'inc' => $tempInc[$bid] ?? []
        ];
    }
}

// ====== محاسبه درآمد ماهانه ======
$monthlyIncome = [];
foreach ($branches as $branch) {
    $bid = $branch['id'];
    $totRial = 0;
    $totGold = 0;
    
    if (isset($allData[$bid]['inc'])) {
        foreach ($allData[$bid]['inc'] as $d) {
            $totRial += $d['rial'];
            $totGold += $d['gold'];
        }
    }
    
    // کوئری درآمد ماهانه
    $q = $conn->prepare("SELECT SUM(amount_gram) as t_mon FROM income_monthly_records WHERE branch_id = ? AND record_year = ? AND record_month = ?");
    $q->bind_param("iii", $bid, $year, $month);
    $q->execute();
    $r = $q->get_result();
    $row = $r->fetch_assoc();
    $totMonGold = (float)($row['t_mon'] ?? 0);
    $q->close();
    
    if ($totRial != 0 || $totGold != 0 || $totMonGold != 0) {
        $monthlyIncome[] = [
            'name' => $branch['name'],
            'bid' => $bid,
            'total_rial' => $totRial,
            'total_gold' => $totGold,
            'total_mon_gold' => $totMonGold
        ];
    }
}

// ====== دریافت اهداف ======
$allGoalTypes = [];
$q_all = $conn->query("SELECT * FROM goal_types WHERE is_active = 1 ORDER BY sort_order");
while ($row = $q_all->fetch_assoc()) $allGoalTypes[$row['id']] = $row;

$branchesForGoals = [];
if ($role === 'branch') {
    $branchesForGoals[] = ['id' => $uid, 'name' => $user['branch_name'] ?? 'شعبه من'];
} else {
    foreach ($branches as $b) $branchesForGoals[] = ['id' => $b['id'], 'name' => $b['name']];
}

$daysPassed = max(1, (int)jdate('d'));

// ====== شروع خروجی HTML ======
?>
<div class="cal-widget glass-panel">
<div class="cal-hdr">
<button class="cal-nav-arr" onclick="navCalMonth(-1)">&lt;</button>
<div class="cal-hdr-selects">
<select id="calMonth" onchange="jumpToMonth()">
<?php for($i=1; $i<=12; $i++): ?>
    <option value="<?php echo $i; ?>" <?php echo $i==$month?'selected':''; ?>><?php echo $mn[$i]; ?></option>
<?php endfor; ?>
</select>
<select id="calYear" onchange="jumpToMonth()">
<?php for($y=max(1300,$year-5); $y<=min(1500,$year+5); $y++): ?>
    <option value="<?php echo $y; ?>" <?php echo $y==$year?'selected':''; ?>><?php echo $y; ?></option>
<?php endfor; ?>
</select>
</div>
<button class="cal-nav-arr" onclick="navCalMonth(1)">&gt;</button>
<button class="cal-today-sm" onclick="goToday()">امروز</button>
</div>

<div class="cal-tbl-wrap">
<table class="cal-tbl">
<thead><tr><th>ش</th><th>ی</th><th>د</th><th>س</th><th>چ</th><th>پ</th><th>ج</th></tr></thead>
<tbody>
<?php
$day = 1;
$total = $fdow + $dim;
$rows = ceil($total / 7);

for ($r = 0; $r < $rows; $r++) {
    echo '<tr>';
    for ($c = 0; $c < 7; $c++) {
        $ci = $r * 7 + $c;
        if ($ci < $fdow || $day > $dim) {
            echo '<td class="empty"></td>';
        } else {
            $dk = sprintf("%04d-%02d-%02d", $year, $month, $day);
            $du = "$year/" . sprintf("%02d", $month) . "/" . sprintf("%02d", $day);
            $ist = ($dk == $today);
            $hi = false;
            $hb = false;
            
            // بررسی سریع وجود داده
            foreach ($allData as $ad) {
                if (!empty($ad['inc'][$dk])) $hi = true;
                if (!empty($ad['bal'][$dk])) {
                    $b = $ad['bal'][$dk];
                    if ($b['de']!=0 || $b['cr']!=0 || $b['pe']!=0 || $b['ba']!=0 || $b['dy']!=0) $hb = true;
                }
                if ($hi && $hb) break;
            }
            
            echo '<td class="' . ($ist?'today':'') . ' day-cell" onclick="showSum(\''.$dk.'\',\''.$du.'\')" data-date="'.$dk.'">';
            echo '<span class="day-num">'.$day.'</span>';
            
            if ($hi || $hb) {
                echo '<div class="day-dots">';
                if ($hi) echo '<span class="day-dot inc"></span>';
                if ($hb) echo '<span class="day-dot bal"></span>';
                echo '</div>';
            }
            
            echo '</td>';
            $day++;
        }
    }
    echo '</tr>';
}
?>
</tbody>
</table>
</div>

<div class="summary-sec" id="sumSec">
    <div class="summary-title" id="sumTitle">برای مشاهده جزئیات، روی یک روز کلیک کنید</div>
    <div id="sumContent"></div>
</div>

<?php if ($role === 'branch'): ?>
<div class="cal-actions">
    <a href="#" onclick="goToRegPage('user/index.php'); return false;" class="cal-action-btn">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="12" y1="1" x2="12" y2="23"></line>
            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
        </svg>
        تراز روزانه
    </a>
    <a href="#" onclick="goToRegPage('income/index.php'); return false;" class="cal-action-btn">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline>
            <polyline points="17 6 23 6 23 12"></polyline>
        </svg>
        درآمد روزانه
    </a>
    <a href="#" onclick="goToRegPage('income/monthly.php', true); return false;" class="cal-action-btn">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
            <line x1="16" y1="2" x2="16" y2="6"></line>
            <line x1="8" y1="2" x2="8" y2="6"></line>
            <line x1="3" y1="10" x2="21" y2="10"></line>
        </svg>
        درآمد ماهانه
    </a>
    <a href="#" onclick="goToRegPage('goals/daily.php'); return false;" class="cal-action-btn primary">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
        </svg>
        ثبت پیشرفت
    </a>
</div>
<?php endif; ?>


<?php if (!empty($monthlyIncome)): ?>
<div class="monthly-sec" style="margin-top:20px;">
    <div class="summary-title">جمع درآمد <?php echo $mn[$month] . ' ' . $year; ?></div>
    <div class="monthly-cards">
        <?php foreach ($monthlyIncome as $mi): 
            // ⭐ محاسبه درآمد سالانه این شعبه
            $bid = $mi['bid'];
            $yearStart = "$year-01-01";
            $yearEnd = "$year-12-29";
            $yearlyInc = 0;
            
            $qy = $conn->prepare("SELECT SUM(amount_gram) as t FROM income_daily_records WHERE branch_id = ? AND record_date BETWEEN ? AND ?");
            $qy->bind_param("iss", $bid, $yearStart, $yearEnd);
            $qy->execute();
            $ry = $qy->get_result();
            $rowy = $ry->fetch_assoc();
            $yearlyInc += (float)($rowy['t'] ?? 0);
            $qy->close();
            
            $qy = $conn->prepare("SELECT SUM(amount_gram) as t FROM income_monthly_records WHERE branch_id = ? AND record_year = ?");
            $qy->bind_param("ii", $bid, $year);
            $qy->execute();
            $ry = $qy->get_result();
            $rowy = $ry->fetch_assoc();
            $yearlyInc += (float)($rowy['t'] ?? 0);
            $qy->close();
        ?>
        <div class="mon-card">
            <div class="mon-name"><span><?php echo htmlspecialchars($mi['name']); ?></span></div>
            <div class="mon-amounts">
                <?php if ($mi['total_rial'] != 0): ?>
                <div class="mon-amount rial"><span>درآمد روزانه (ریال)</span><span><?php echo number_format($mi['total_rial']); ?> ریال</span></div>
                <?php endif; ?>
                <?php if ($mi['total_gold'] != 0): ?>
                <div class="mon-amount gold"><span>درآمد روزانه (طلا)</span><span><?php echo number_format($mi['total_gold'], 2); ?> گرم</span></div>
                <?php endif; ?>
                <?php if ($mi['total_mon_gold'] > 0): ?>
                <a href="income/monthly.php?branch_id=<?php echo $mi['bid']; ?>&year=<?php echo $year; ?>&month=<?php echo $month; ?>" style="text-decoration:none;">
                    <div class="mon-amount monthly"><span>درآمد ماهانه (طلا)</span><span><?php echo number_format($mi['total_mon_gold'], 2); ?> گرم</span></div>
                </a>
                <?php endif; ?>
                <?php $grandTotalGold = $mi['total_gold'] + $mi['total_mon_gold']; if ($grandTotalGold != 0): ?>
                <div class="mon-amount total"><span>جمع کل درآمد (طلا)</span><span><?php echo number_format($grandTotalGold, 2); ?> گرم</span></div>
                <?php endif; ?>
                <!-- ⭐ کارت سالانه -->
                <div class="mon-amount yearly"><span>درآمد سالانه (طلا)</span><span><?php echo number_format($yearlyInc, 2); ?> گرم</span></div>
            </div>
            <a href="reports.php?year=<?php echo $year; ?>&month=<?php echo $month; ?>&tab=income<?php echo $role==='observer'?'&branch_id='.$mi['bid']:''; ?>" class="mon-link">مشاهده گزارش کامل</a>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>
<?php if ($role === 'observer' && !empty($branches)): ?>
<div class="observer-income-wrapper">
    <div class="section-title">جمع‌بندی درآمد شعب</div>
    
    <div class="observer-totals-row">
        <?php
        $observerTotalMonthly = 0;
        $observerTotalYearly = 0;
        $observerBranchesIncome = [];
        
        foreach ($branches as $ob) {
            $obid = $ob['id'];
            $obname = $ob['name'];
            
            // درآمد ماهانه = daily + monthly این ماه
$monInc = 0;

// daily records این ماه
$q = $conn->prepare("SELECT SUM(amount_gram) as t FROM income_daily_records WHERE branch_id = ? AND record_date BETWEEN ? AND ?");
$q->bind_param("iss", $obid, $sd, $ed);
$q->execute();
$r = $q->get_result();
$row = $r->fetch_assoc();
$monInc += (float)($row['t'] ?? 0);
$q->close();

// ⭐ اینو اضافه کن: monthly records این ماه
$q = $conn->prepare("SELECT SUM(amount_gram) as t FROM income_monthly_records WHERE branch_id = ? AND record_year = ? AND record_month = ?");
$q->bind_param("iii", $obid, $year, $month);
$q->execute();
$r = $q->get_result();
$row = $r->fetch_assoc();
$monInc += (float)($row['t'] ?? 0);
$q->close();
            
            // درآمد سالانه = daily + monthly کل سال
            $yearStart = "$year-01-01";
            $yearEnd = "$year-12-29";
            $yearInc = 0;
            
            $q = $conn->prepare("SELECT SUM(amount_gram) as t FROM income_daily_records WHERE branch_id = ? AND record_date BETWEEN ? AND ?");
            $q->bind_param("iss", $obid, $yearStart, $yearEnd);
            $q->execute();
            $r = $q->get_result();
            $row = $r->fetch_assoc();
            $yearInc += (float)($row['t'] ?? 0);
            $q->close();
            
            $q = $conn->prepare("SELECT SUM(amount_gram) as t FROM income_monthly_records WHERE branch_id = ? AND record_year = ?");
            $q->bind_param("ii", $obid, $year);
            $q->execute();
            $r = $q->get_result();
            $row = $r->fetch_assoc();
            $yearInc += (float)($row['t'] ?? 0);
            $q->close();
            
            $observerBranchesIncome[] = [
                'name' => $obname,
                'monthly' => $monInc,
                'yearly' => $yearInc
            ];
            
            $observerTotalMonthly += $monInc;
            $observerTotalYearly += $yearInc;
        }
        ?>
        
        <div class="obs-total-card">
            <div class="obs-total-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            </div>
            <div class="obs-total-label">جمع درآمد ماهانه کل شعب</div>
            <div class="obs-total-value"><?php echo number_format($observerTotalMonthly, 2); ?> گرم</div>
        </div>
        
        <div class="obs-total-card yearly">
            <div class="obs-total-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </div>
            <div class="obs-total-label">جمع درآمد سالانه کل شعب</div>
            <div class="obs-total-value"><?php echo number_format($observerTotalYearly, 2); ?> گرم</div>
        </div>
    </div>
    
    <div class="obs-branches-grid">
        <?php foreach ($observerBranchesIncome as $obi): ?>
        <div class="obs-branch-card">
            <div class="obs-branch-name">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="2" width="16" height="20" rx="2" ry="2"/><path d="M9 6h.01M15 6h.01M9 10h.01M15 10h.01M9 14h.01M15 14h.01M9 18h6"/></svg>
                <?php echo htmlspecialchars($obi['name']); ?>
            </div>
            <div class="obs-branch-stats">
                <div class="obs-stat">
                    <span class="obs-stat-label">ماهانه</span>
                    <span class="obs-stat-val"><?php echo number_format($obi['monthly'], 2); ?> گرم</span>
                </div>
                <div class="obs-stat">
                    <span class="obs-stat-label">سالانه</span>
                    <span class="obs-stat-val yearly"><?php echo number_format($obi['yearly'], 2); ?> گرم</span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>
        
<?php foreach ($branchesForGoals as $targetBranch): 
    $bid = $targetBranch['id'];
    $bname = $targetBranch['name'];
    
    // کوئری اهداف شعبه
    $q_bg = $conn->prepare("SELECT goal_type_id, target_value FROM branch_goals WHERE branch_id = ? AND start_date <= ? AND end_date >= ?");
    $q_bg->bind_param("iss", $bid, $today, $today);
    $q_bg->execute();
    $r_bg = $q_bg->get_result();
    
    $branchGoals = [];
    while ($row = $r_bg->fetch_assoc()) $branchGoals[$row['goal_type_id']] = $row['target_value'];
    $q_bg->close();
    
    if (empty($branchGoals)) continue;
    
    // محاسبه پیشرفت
    $progressData = [];
    $branchDaysPassed = $daysPassed;
    
    $active_q = $conn->prepare("SELECT start_date, end_date FROM branch_goals WHERE branch_id = ? AND start_date <= ? AND end_date >= ? LIMIT 1");
    $active_q->bind_param("iss", $bid, $today, $today);
    $active_q->execute();
    $active_r = $active_q->get_result();
    
    $monthStr = str_pad($month, 2, '0', STR_PAD_LEFT);
    $searchPattern = "$year-$monthStr-%";
    
    if ($active_r->num_rows > 0) {
        $active_row = $active_r->fetch_assoc();
        $startDate = new DateTime($active_row['start_date']);
        $todayDate = new DateTime($today);
        $branchDaysPassed = max(1, $startDate->diff($todayDate)->days + 1);
        
        $q_prog = $conn->prepare("SELECT goal_type_id, SUM(achieved_value) as total FROM goal_daily_progress WHERE branch_id = ? AND progress_date BETWEEN ? AND ? GROUP BY goal_type_id");
        $q_prog->bind_param("iss", $bid, $active_row['start_date'], $active_row['end_date']);
    } else {
        $q_prog = $conn->prepare("SELECT goal_type_id, SUM(achieved_value) as total FROM goal_daily_progress WHERE branch_id = ? AND progress_date LIKE ? GROUP BY goal_type_id");
        $q_prog->bind_param("is", $bid, $searchPattern);
    }
    
    $q_prog->execute();
    $r_prog = $q_prog->get_result();
    while ($row = $r_prog->fetch_assoc()) $progressData[$row['goal_type_id']] = $row['total'];
    $q_prog->close();
    $active_q->close();
    
    $goalsList = [];
    foreach ($allGoalTypes as $goalId => $goal) {
        $target = isset($branchGoals[$goalId]) ? (float)$branchGoals[$goalId] : 0;
        if ($target <= 0) continue;
        $achieved = isset($progressData[$goalId]) ? (float)$progressData[$goalId] : 0;
        $percentage = min(100, round(($achieved / $target) * 100, 1));
        $remaining = max(0, $target - $achieved);
        $dailyAvg = $branchDaysPassed > 0 ? round($achieved / $branchDaysPassed, 3) : 0;
        $goalsList[] = [
            'id' => $goalId,
            'name' => $goal['name'],
            'unit' => $goal['unit'],
            'icon' => $goal['icon'],
            'target' => $target,
            'achieved' => $achieved,
            'percentage' => $percentage,
            'remaining' => $remaining,
            'daily_avg' => $dailyAvg,
            'has_target' => true
        ];
    }
    
    $branchTotalGoals = count($goalsList);
    $completedGoals = count(array_filter($goalsList, function($g) { return $g['percentage'] >= 100; }));
    $overallPercentage = $branchTotalGoals > 0 ? round(array_sum(array_column($goalsList, 'percentage')) / $branchTotalGoals) : 0;
?>
<div class="branch-goals-wrapper" style="margin-top:20px;">
    <div class="branch-goals-header">
        <div class="branch-name-badge"><span class="icon"><?php echo svg_icon('🏢', 18); ?></span><span><?php echo htmlspecialchars($bname); ?></span></div>
        <div class="branch-stats-badge"><?php echo $completedGoals; ?> از <?php echo $branchTotalGoals; ?> هدف تکمیل شده</div>
    </div>
    <?php if ($branchTotalGoals > 0): ?>
    <div class="branch-overall-progress">
        <div class="branch-progress-header"><span>پیشرفت کلی دوره</span><span style="color:#fcd34d;font-weight:bold;"><?php echo $overallPercentage; ?>%</span></div>
        <div class="branch-progress-track"><div class="branch-progress-fill" style="width:<?php echo $overallPercentage; ?>%;"></div></div>
    </div>
    <?php endif; ?>
    <div class="goals-grid-pro">
        <?php foreach ($goalsList as $goal): 
            $unitText = $goal['unit'] == 'gram' ? 'گرم' : 'ریال';
            $barWidth = min($goal['percentage'], 100);
        ?>
        <div class="goal-card-pro <?php echo $goal['percentage'] >= 100 ? 'completed' : ''; ?>">
            <div class="goal-card-header">
                <div style="display:flex;align-items:center;gap:12px;min-width:0;">
                    <div class="goal-icon-box"><?php echo svg_icon($goal['icon'], 18); ?></div>
                    <div class="goal-title-box"><div class="goal-title"><?php echo htmlspecialchars($goal['name']); ?></div></div>
                </div>
                <div class="goal-percent-badge"><?php echo $goal['percentage']; ?>%</div>
            </div>
            <div class="goal-mini-stats">
                <span class="stat-item ach"><?php echo number_format($goal['achieved']); ?></span>
                <span class="stat-item tgt"><?php echo number_format($goal['target']); ?></span>
            </div>
            <div class="goal-progress-wrapper">
                <div class="goal-progress-bg"><div class="goal-progress-bar" style="width:<?php echo $barWidth; ?>%;"></div></div>
            </div>
            <div class="goal-card-footer">
                <div class="footer-stat"><span class="label">باقی‌مانده</span><span class="value"><?php echo number_format($goal['remaining']); ?> <?php echo $unitText; ?></span></div>
                <div class="footer-stat"><span class="label">میانگین روز</span><span class="value"><?php echo number_format($goal['daily_avg'], 2); ?> <?php echo $unitText; ?></span></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endforeach; ?>

<script>
// داده‌های تقویم برای استفاده در صفحه اصلی
window.calAllData = <?php echo json_encode($allData, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK); ?>;
window.selectedCalDate = window.selectedCalDate || '<?php echo $todayDateEn; ?>';

// توابع navigation
window.jumpToMonth = function() { 
    var y = parseInt(document.getElementById('calYear').value); 
    var m = parseInt(document.getElementById('calMonth').value); 
    if (typeof window.loadCal === 'function') {
        window.loadCal(y, m);
    }
};

window.navCalMonth = function(delta) {
    var ye = document.getElementById('calYear');
    var me = document.getElementById('calMonth');
    if (!ye || !me) return;
    var y = parseInt(ye.value);
    var m = parseInt(me.value) + delta;
    if (m > 12) { m = 1; y++; }
    if (m < 1) { m = 12; y--; }
    if (typeof window.loadCal === 'function') {
        window.loadCal(y, m);
    }
};

window.goToday = function() {
    if (typeof window.loadCal === 'function') {
        window.loadCal(<?php echo (int)jdate('Y'); ?>, <?php echo (int)jdate('m'); ?>);
    }
};

// تابع نمایش خلاصه - بهینه‌شده
window.showSum = function(dk, du) {
    window.selectedCalDate = dk;
    var allData = window.calAllData || {};
    
    // حذف selection قبلی
    var cells = document.querySelectorAll('.day-cell.selected');
    for (var i = 0; i < cells.length; i++) cells[i].classList.remove('selected');
    
    // اضافه کردن selection جدید
    var cell = document.querySelector('[data-date="' + dk + '"]');
    if (cell) cell.classList.add('selected');
    
    // ساخت HTML خلاصه
    var html = '';
    var hasData = false;
    
    for (var bid in allData) {
        var br = allData[bid];
        var ba = br.bal[dk] || null;
        var inc = br.inc[dk] || {rial: 0, gold: 0};
        
        if (!ba && inc.rial == 0 && inc.gold == 0) continue;
        hasData = true;
        
        html += '<div class="branch-summ"><div class="branch-name">' + escapeHtml(br.name) + '</div><div class="summ-grid">';
        
        if (ba && ba.de != 0) {
            html += '<div class="summ-item" onclick="goToBal(\'' + dk + '\', ' + bid + ')">';
            html += '<div class="val d">' + Number(ba.de).toLocaleString() + '</div><div class="lbl">بدهکاران</div></div>';
        }
        
        if (ba && ba.cr != 0) {
            html += '<div class="summ-item" onclick="goToBal(\'' + dk + '\', ' + bid + ')">';
            html += '<div class="val c">' + Number(ba.cr).toLocaleString() + '</div><div class="lbl">بستانکاران</div></div>';
        }
        
        if (ba && (ba.de != 0 || ba.cr != 0)) {
            var diff = ba.cr - ba.de;
            if (diff !== 0) {
                html += '<div class="summ-item" onclick="goToBal(\'' + dk + '\', ' + bid + ')">';
                html += '<div class="val" style="color:' + (diff < 0 ? 'var(--accent)' : 'var(--red)') + ';">' + Number(Math.abs(diff)).toLocaleString() + '</div>';
                html += '<div class="lbl">' + (diff < 0 ? 'فزونی' : 'کسری') + '</div></div>';
            }
        }
        
        if (ba && ba.pe != 0) {
    var peVal = Number(ba.pe);
    var peLabel = peVal > 0 ? 'فزونی تنخواه' : 'کسری تنخواه';
    var peSign = peVal > 0 ? '+' : '';
    
    html += '<div class="summ-item" onclick="goToBal(\'' + dk + '\', ' + bid + ')">';
    html += '<div class="val" style="color:#868d97;">' + peSign + Math.abs(peVal).toLocaleString() + '</div>';
    html += '<div class="lbl">' + peLabel + '</div></div>';
}
        
        if (ba && ba.ba != 0) {
            html += '<div class="summ-item" onclick="goToBal(\'' + dk + '\', ' + bid + ')">';
            html += '<div class="val b">' + Number(ba.ba).toLocaleString() + '</div><div class="lbl">بنکداران</div></div>';
        }
        
        if (ba && ba.dy != 0) {
            html += '<div class="summ-item" onclick="goToBal(\'' + dk + '\', ' + bid + ')">';
            html += '<div class="val dy">' + Number(ba.dy).toLocaleString() + '</div><div class="lbl">داینامیک</div></div>';
        }
        
        if (inc.rial != 0 || inc.gold != 0) {
    html += '<div class="summ-item income-item" onclick="goToInc(\'' + dk + '\', ' + bid + ')">';
    if (inc.rial != 0) html += '<span class="val">' + Number(inc.rial).toLocaleString() + ' ریال</span>';
    if (inc.gold != 0) html += '<span class="gram-val">' + Number(inc.gold).toLocaleString() + ' گرم</span>';
    html += '<span class="lbl">درآمد</span></div>';
}
        
        html += '</div></div>';
    }
    
    var sumTitle = document.getElementById('sumTitle');
    var sumContent = document.getElementById('sumContent');
    if (sumTitle) sumTitle.innerHTML = hasData ? 'گزارش مالی روز: <span style="color:#fff;">' + du + '</span>' : 'برای مشاهده جزئیات، روی یک روز کلیک کنید';
    if (sumContent) sumContent.innerHTML = hasData ? html : '<div class="loading-state">هیچ تراکنشی در این روز ثبت نشده است</div>';
};

// تابع escape HTML
function escapeHtml(text) {
    if (!text) return '';
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<?php
// پایان خروجی
ob_end_flush();
?>