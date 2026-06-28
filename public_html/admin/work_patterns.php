<?php
define('SECURE_ACCESS', true);
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php'); exit;
}

$message = '';
$edit_pattern = null;
$weekly_hours = [];

// نام روزهای هفته
$day_names = [
    0 => 'شنبه',
    1 => 'یکشنبه',
    2 => 'دوشنبه',
    3 => 'سه‌شنبه',
    4 => 'چهارشنبه',
    5 => 'پنجشنبه',
    6 => 'جمعه'
];

// === مدیریت الگوها ===
if (isset($_POST['save_pattern'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $pid = $_POST['pattern_id'] ?? null;
    
    if ($pid && !empty($pid)) {
        mysqli_query($conn, "UPDATE working_hour_patterns SET name='$name' WHERE id=" . (int)$pid);
        // حذف ساعات قبلی
        mysqli_query($conn, "DELETE FROM pattern_weekly_hours WHERE pattern_id=" . (int)$pid);
    } else {
        mysqli_query($conn, "INSERT INTO working_hour_patterns (name) VALUES ('$name')");
        $pid = mysqli_insert_id($conn);
    }
    
    // ذخیره ساعات هر روز
    for ($day = 0; $day < 7; $day++) {
        $is_working = isset($_POST["day_{$day}_active"]) ? 1 : 0;
        $m_start = !empty($_POST["day_{$day}_m_start"]) ? "'" . mysqli_real_escape_string($conn, $_POST["day_{$day}_m_start"]) . ":00'" : "NULL";
        $m_end = !empty($_POST["day_{$day}_m_end"]) ? "'" . mysqli_real_escape_string($conn, $_POST["day_{$day}_m_end"]) . ":00'" : "NULL";
        $a_start = !empty($_POST["day_{$day}_a_start"]) ? "'" . mysqli_real_escape_string($conn, $_POST["day_{$day}_a_start"]) . ":00'" : "NULL";
        $a_end = !empty($_POST["day_{$day}_a_end"]) ? "'" . mysqli_real_escape_string($conn, $_POST["day_{$day}_a_end"]) . ":00'" : "NULL";
        
        // فقط روزهایی که حداقل یک بازه زمانی دارن رو ذخیره کن
        if ($m_start != "NULL" || $a_start != "NULL") {
            $sql = "INSERT INTO pattern_weekly_hours 
                    (pattern_id, day_of_week, is_working_day, morning_start, morning_end, afternoon_start, afternoon_end) 
                    VALUES ($pid, $day, $is_working, $m_start, $m_end, $a_start, $a_end)";
            mysqli_query($conn, $sql);
        }
    }
    
    $message = "✅ الگو با موفقیت " . ($_POST['pattern_id'] ? "ویرایش" : "افزوده") . " شد";
    // ریدایرکت برای جلوگیری از ارسال مجدد فرم
    header('Location: work_patterns.php?msg=success');
    exit;
}

if (isset($_GET['msg']) && $_GET['msg'] == 'success') {
    $message = "✅ عملیات با موفقیت انجام شد";
}

// بارگذاری الگو برای ویرایش
if (isset($_GET['edit_pattern'])) {
    $edit_id = (int)$_GET['edit_pattern'];
    $edit_query = mysqli_query($conn, "SELECT * FROM working_hour_patterns WHERE id=$edit_id");
    if ($edit_query && mysqli_num_rows($edit_query) > 0) {
        $edit_pattern = mysqli_fetch_assoc($edit_query);
        
        // بارگذاری ساعات هفتگی
        $hours_query = mysqli_query($conn, "SELECT * FROM pattern_weekly_hours WHERE pattern_id=$edit_id ORDER BY day_of_week");
        while ($hour = mysqli_fetch_assoc($hours_query)) {
            $weekly_hours[$hour['day_of_week']] = $hour;
        }
    }
}

// حذف الگو
if (isset($_GET['delete_pattern'])) {
    $pid = (int)$_GET['delete_pattern'];
    mysqli_query($conn, "DELETE FROM user_work_patterns WHERE pattern_id=$pid");
    mysqli_query($conn, "DELETE FROM working_hour_patterns WHERE id=$pid");
    $message = "✅ الگو با موفقیت حذف شد";
    header('Location: work_patterns.php?msg=deleted');
    exit;
}

if (isset($_GET['msg']) && $_GET['msg'] == 'deleted') {
    $message = "✅ الگو با موفقیت حذف شد";
}

// === تخصیص الگو به کاربر ===
if (isset($_POST['assign_pattern'])) {
    $uid = (int)$_POST['user_id'];
    $pid = (int)$_POST['pattern_id'];
    $aid = $_SESSION['user_id'];
    
    if ($pid > 0) {
        $check = mysqli_query($conn, "SELECT id FROM user_work_patterns WHERE user_id=$uid");
        if (mysqli_num_rows($check) > 0) {
            mysqli_query($conn, "UPDATE user_work_patterns SET pattern_id=$pid, assigned_by=$aid WHERE user_id=$uid");
        } else {
            mysqli_query($conn, "INSERT INTO user_work_patterns (user_id, pattern_id, assigned_by) VALUES ($uid, $pid, $aid)");
        }
        $message = "✅ الگو به کاربر تخصیص داده شد";
    }
}

// دریافت لیست الگوها
$patterns = mysqli_query($conn, "SELECT * FROM working_hour_patterns ORDER BY name");

// دریافت لیست کاربران - اصلاح‌شده بر اساس ساختار واقعی دیتابیس
$users = mysqli_query($conn, "SELECT u.id, u.username, u.branch_name, wp.name as pattern_name 
    FROM users u 
    LEFT JOIN user_work_patterns uwp ON u.id = uwp.user_id 
    LEFT JOIN working_hour_patterns wp ON uwp.pattern_id = wp.id 
    WHERE u.role = 'branch' 
    ORDER BY u.branch_name, u.username");

// دریافت لیست الگوها برای dropdown
$patterns_dropdown = mysqli_query($conn, "SELECT id, name FROM working_hour_patterns ORDER BY name");

// تابع کمکی برای نمایش خلاصه الگو
function getPatternSummary($conn, $pattern_id) {
    $summary = [];
    $day_names = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه'];
    $day_short = ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج'];
    
    $hours = mysqli_query($conn, "SELECT * FROM pattern_weekly_hours WHERE pattern_id=$pattern_id ORDER BY day_of_week");
    
    while ($h = mysqli_fetch_assoc($hours)) {
        if ($h['is_working_day']) {
            $parts = [];
            if ($h['morning_start'] && $h['morning_end']) {
                $parts[] = substr($h['morning_start'], 0, 5) . '-' . substr($h['morning_end'], 0, 5);
            }
            if ($h['afternoon_start'] && $h['afternoon_end']) {
                $parts[] = substr($h['afternoon_start'], 0, 5) . '-' . substr($h['afternoon_end'], 0, 5);
            }
            if (!empty($parts)) {
                $summary[] = $day_short[$h['day_of_week']] . ':' . implode('|', $parts);
            }
        } else {
            $summary[] = $day_short[$h['day_of_week']] . ':تعطیل';
        }
    }
    return implode(' • ', $summary);
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>مدیریت الگوهای کاری | ادمین</title>
    <link href="../assets/fonts/fonts.css" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Vazirmatn', sans-serif;
            background: linear-gradient(135deg, #0b101e, #1a2235);
            color: #f0f2f5;
            padding: 20px;
            min-height: 100vh;
        }
        .container { max-width: 1400px; margin: 0 auto; }
        
        .card {
            background: rgba(25,33,50,0.6);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 20px;
        }
        
        h2, h3 { color: #d4af37; margin-top: 0; }
        h2 { margin-bottom: 25px; font-size: 1.5rem; }
        
        .msg {
            background: rgba(16,185,129,0.2);
            color: #34d399;
            padding: 12px;
            border-radius: 12px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: bold;
        }
        
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: 10px;
            border: none;
            cursor: pointer;
            font-weight: 700;
            font-family: 'Vazirmatn', sans-serif;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
            font-size: 0.9rem;
        }
        .btn:hover { transform: translateY(-2px); opacity: 0.9; }
        .btn-gold {
            background: linear-gradient(135deg, #d4af37, #fcd34d);
            color: #1a1a1a;
        }
        .btn-red {
            background: rgba(248,113,113,0.2);
            color: #f87171;
            border: 1px solid rgba(248,113,113,0.3);
        }
        .btn-blue {
            background: rgba(96,165,250,0.2);
            color: #60a5fa;
            border: 1px solid rgba(96,165,250,0.3);
        }
        .btn-sm { padding: 5px 10px; font-size: 0.8rem; }
        
        input, select {
            padding: 10px;
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,0.2);
            background: rgba(0,0,0,0.3);
            color: #fff;
            font-family: 'Vazirmatn', sans-serif;
            width: 100%;
        }
        input:focus, select:focus {
            outline: none;
            border-color: #d4af37;
        }
        
        .day-row {
            display: grid;
            grid-template-columns: 70px 1fr;
            gap: 10px;
            padding: 12px;
            background: rgba(0,0,0,0.2);
            border-radius: 10px;
            margin-bottom: 8px;
            align-items: center;
            transition: all 0.3s;
        }
        .day-row.disabled {
            opacity: 0.4;
            background: rgba(0,0,0,0.1);
        }
        
        .day-label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: bold;
            color: #d4af37;
            font-size: 0.9rem;
        }
        
        .day-times {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        
        .time-group label {
            font-size: 0.7rem;
            color: #94a3b8;
            display: block;
            margin-bottom: 4px;
        }
        
        .time-inputs {
            display: flex;
            gap: 5px;
            align-items: center;
        }
        
        .time-inputs input {
            width: calc(50% - 8px);
            padding: 6px;
        }
        
        .time-inputs span {
            color: #94a3b8;
            font-size: 0.8rem;
        }
        
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 44px;
            height: 24px;
        }
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ef4444;
            transition: .3s;
            border-radius: 24px;
        }
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .3s;
            border-radius: 50%;
        }
        input:checked + .toggle-slider {
            background-color: #10b981;
        }
        input:checked + .toggle-slider:before {
            transform: translateX(20px);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 12px;
            text-align: right;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        th { color: #d4af37; font-weight: 700; font-size: 0.9rem; }
        
        .pattern-summary {
            font-size: 0.75rem;
            color: #94a3b8;
            line-height: 1.8;
            margin-top: 5px;
        }
        
        .pattern-actions {
            display: flex;
            gap: 8px;
        }
        
        .back-link {
            color: #60a5fa;
            text-decoration: none;
            display: inline-block;
            margin-top: 20px;
        }
        .back-link:hover { text-decoration: underline; }
        
        @media (max-width: 768px) {
            .grid-2 { grid-template-columns: 1fr; }
            .day-times { grid-template-columns: 1fr; }
            .day-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>⚙️ مدیریت الگوهای ساعات کاری</h2>
        
        <?php if($message): ?>
            <div class="msg"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <div class="grid-2">
            <!-- فرم ویرایش/افزودن الگو -->
            <div class="card">
                <h3><?php echo $edit_pattern ? '✏️ ویرایش الگو' : '➕ افزودن الگوی جدید'; ?></h3>
                
                <form method="post">
                    <input type="hidden" name="pattern_id" value="<?php echo $edit_pattern['id'] ?? ''; ?>">
                    
                    <div style="margin-bottom: 20px;">
                        <label style="color: #94a3b8; font-size: 0.8rem; display: block; margin-bottom: 5px;">نام الگو</label>
                        <input type="text" name="name" value="<?php echo htmlspecialchars($edit_pattern['name'] ?? ''); ?>" 
                               placeholder="مثال: الگوی استاندارد" required>
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <label style="color: #94a3b8; font-size: 0.8rem;">⏰ تنظیم ساعات هر روز</label>
                    </div>
                    
                    <?php for($day = 0; $day < 7; $day++): 
                        $day_data = $weekly_hours[$day] ?? null;
                        $is_working = $day_data ? $day_data['is_working_day'] : ($day != 6);
                        
                        // مقادیر پیش‌فرض
                        $m_start_val = '';
                        $m_end_val = '';
                        $a_start_val = '';
                        $a_end_val = '';
                        
                        if ($day_data) {
                            $m_start_val = $day_data['morning_start'] ? substr($day_data['morning_start'], 0, 5) : '';
                            $m_end_val = $day_data['morning_end'] ? substr($day_data['morning_end'], 0, 5) : '';
                            $a_start_val = $day_data['afternoon_start'] ? substr($day_data['afternoon_start'], 0, 5) : '';
                            $a_end_val = $day_data['afternoon_end'] ? substr($day_data['afternoon_end'], 0, 5) : '';
                        }
                    ?>
                    <div class="day-row <?php echo !$is_working ? 'disabled' : ''; ?>" id="day_row_<?php echo $day; ?>">
                        <div class="day-label">
                            <label class="toggle-switch">
                                <input type="checkbox" name="day_<?php echo $day; ?>_active" 
                                       <?php echo $is_working ? 'checked' : ''; ?>
                                       onchange="toggleDay(<?php echo $day; ?>, this)">
                                <span class="toggle-slider"></span>
                            </label>
                            <?php echo $day_names[$day]; ?>
                        </div>
                        
                        <div class="day-times">
                            <div class="time-group">
                                <label>🌅 صبح</label>
                                <div class="time-inputs">
                                    <input type="time" name="day_<?php echo $day; ?>_m_start" value="<?php echo $m_start_val; ?>">
                                    <span>تا</span>
                                    <input type="time" name="day_<?php echo $day; ?>_m_end" value="<?php echo $m_end_val; ?>">
                                </div>
                            </div>
                            
                            <div class="time-group">
                                <label>🌆 عصر</label>
                                <div class="time-inputs">
                                    <input type="time" name="day_<?php echo $day; ?>_a_start" value="<?php echo $a_start_val; ?>">
                                    <span>تا</span>
                                    <input type="time" name="day_<?php echo $day; ?>_a_end" value="<?php echo $a_end_val; ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endfor; ?>
                    
                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                        <button type="submit" name="save_pattern" class="btn btn-gold" style="flex: 1;">
                            💾 <?php echo $edit_pattern ? 'بروزرسانی الگو' : 'ذخیره الگو'; ?>
                        </button>
                        <?php if($edit_pattern): ?>
                            <a href="work_patterns.php" class="btn btn-blue">❌ لغو</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            
            <!-- بخش راست: لیست الگوها و تخصیص -->
            <div>
                <!-- لیست الگوهای موجود -->
                <div class="card">
                    <h3>📋 الگوهای تعریف شده</h3>
                    <div style="max-height: 350px; overflow-y: auto;">
                        <?php 
                        if ($patterns && mysqli_num_rows($patterns) > 0):
                            while($p = mysqli_fetch_assoc($patterns)): 
                                $summary = getPatternSummary($conn, $p['id']);
                        ?>
                        <div style="padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.1);">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                <div style="flex: 1;">
                                    <strong style="color: #d4af37;"><?php echo htmlspecialchars($p['name']); ?></strong>
                                    <div class="pattern-summary"><?php echo $summary; ?></div>
                                </div>
                                <div class="pattern-actions">
                                    <a href="?edit_pattern=<?php echo $p['id']; ?>" class="btn btn-blue btn-sm">✏️</a>
                                    <a href="?delete_pattern=<?php echo $p['id']; ?>" 
                                       class="btn btn-red btn-sm" 
                                       onclick="return confirm('آیا از حذف این الگو اطمینان دارید؟')">🗑️</a>
                                </div>
                            </div>
                        </div>
                        <?php 
                            endwhile;
                        else:
                        ?>
                        <p style="text-align: center; color: #94a3b8; padding: 20px;">⏳ هیچ الگویی تعریف نشده است</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- تخصیص الگو به کاربران -->
                <div class="card">
                    <h3>👥 تخصیص الگو به کاربران شعبه</h3>
                    <div style="overflow-x: auto;">
                        <table>
                            <thead>
                                <tr>
                                    <th>شعبه</th>
                                    <th>کاربر</th>
                                    <th>الگوی فعلی</th>
                                    <th>عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                if ($users && mysqli_num_rows($users) > 0):
                                    while($u = mysqli_fetch_assoc($users)): 
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($u['branch_name']); ?></td>
                                    <td><?php echo htmlspecialchars($u['username']); ?></td>
                                    <td>
                                        <span style="color: <?php echo $u['pattern_name'] ? '#34d399' : '#94a3b8'; ?>; font-size: 0.85rem;">
                                            <?php echo htmlspecialchars($u['pattern_name'] ?: 'تعیین نشده'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <form method="post" style="display: flex; gap: 5px;">
                                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                            <select name="pattern_id" style="padding: 6px; font-size: 0.8rem; width: 140px;">
                                                <option value="">-- انتخاب --</option>
                                                <?php 
                                                mysqli_data_seek($patterns_dropdown, 0);
                                                while($pp = mysqli_fetch_assoc($patterns_dropdown)): 
                                                ?>
                                                    <option value="<?php echo $pp['id']; ?>">
                                                        <?php echo htmlspecialchars($pp['name']); ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                            <button type="submit" name="assign_pattern" class="btn btn-gold btn-sm">
                                                ✓ تخصیص
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php 
                                    endwhile;
                                else:
                                ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; color: #94a3b8; padding: 20px;">
                                        ⏳ هیچ کاربر شعبه‌ای یافت نشد
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <div style="text-align: center;">
            <a href="../index.php" class="back-link">← بازگشت به داشبورد</a>
        </div>
    </div>
    
    <script>
    function toggleDay(day, checkbox) {
        const row = document.getElementById('day_row_' + day);
        if (checkbox.checked) {
            row.classList.remove('disabled');
        } else {
            row.classList.add('disabled');
        }
    }
    </script>
</body>
</html>