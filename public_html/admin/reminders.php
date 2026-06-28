<?php
/**
 * پنل مدیریت ادمین - تنظیمات هشدار + تخصیص ناظر + حذف هشدارها و پیام‌ها
 */
define('SECURE_ACCESS', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/reminder_functions.php';

requireAdmin();

$message = '';
$error = '';

// ========== پردازش فرم‌ها ==========

// ۱. ذخیره تنظیمات
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $time = $_POST['reminder_time'] ?? '20:00';
    $active = isset($_POST['reminder_active']) ? '1' : '0';
    
    mysqli_query($conn, "UPDATE settings SET setting_value = '$time' WHERE setting_key = 'reminder_time'");
    mysqli_query($conn, "UPDATE settings SET setting_value = '$active' WHERE setting_key = 'reminder_active'");
    $message = '✅ تنظیمات ذخیره شد';
}

// ۲. حذف هشدار تکی
if (isset($_GET['delete_reminder'])) {
    $id = (int)$_GET['delete_reminder'];
    mysqli_query($conn, "UPDATE reminders SET is_active = 0 WHERE id = $id");
    $message = '🗑️ هشدار حذف شد';
}

// ۳. حذف همه هشدارها
if (isset($_POST['delete_all_reminders'])) {
    mysqli_query($conn, "UPDATE reminders SET is_active = 0 WHERE is_active = 1");
    $message = '🗑️ همه هشدارها حذف شدند';
}

// ۴. حذف پیام ناظر
if (isset($_GET['delete_message'])) {
    $id = (int)$_GET['delete_message'];
    mysqli_query($conn, "UPDATE observer_messages SET deleted_by = " . $_SESSION['user_id'] . ", deleted_at = NOW() WHERE id = $id");
    $message = '🗑️ پیام حذف شد';
}

// ۵. تخصیص کاربر به ناظر
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_users'])) {
    $observer_id = (int)$_POST['observer_id'];
    $user_ids = $_POST['user_ids'] ?? [];
    
    mysqli_query($conn, "DELETE FROM observer_assignments WHERE observer_id = $observer_id");
    
    foreach ($user_ids as $uid) {
        $uid = (int)$uid;
        mysqli_query($conn, "INSERT IGNORE INTO observer_assignments (observer_id, branch_id) VALUES ($observer_id, $uid)");
    }
    $message = '✅ تخصیص کاربران بروز شد';
}

// ۶. تنظیم دسترسی مشاهده ناظر
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_view_access_btn'])) {
    $view_observer_id = (int)$_POST['view_observer_id'];
    $view_active = isset($_POST['view_active']) ? '1' : '0';
    
    mysqli_query($conn, "INSERT INTO settings (setting_key, setting_value) 
                         VALUES ('observer_view_{$view_observer_id}', '$view_active') 
                         ON DUPLICATE KEY UPDATE setting_value = '$view_active'");
    $message = '✅ تنظیمات دسترسی مشاهده ذخیره شد';
}

// ========== دریافت داده‌ها ==========
$reminder_time = getSetting('reminder_time', '20:00');
$reminder_active = getSetting('reminder_active', '1');

// لیست ناظرها
$observers = mysqli_query($conn, "SELECT id, branch_name FROM users WHERE role = 'observer' ORDER BY branch_name");

// لیست کاربران عادی
$branches = mysqli_query($conn, "SELECT id, branch_name FROM users WHERE role = 'branch' ORDER BY branch_name");

// لیست هشدارهای فعال
$active_reminders = mysqli_query($conn, "
    SELECT r.*, u.branch_name 
    FROM reminders r 
    JOIN users u ON r.user_id = u.id 
    WHERE r.is_active = 1 
    ORDER BY r.date_shamsi DESC 
    LIMIT 100
");

// لیست پیام‌های ناظر
$messages = mysqli_query($conn, "
    SELECT om.*, u1.branch_name as observer_name, u2.branch_name as target_name 
    FROM observer_messages om 
    LEFT JOIN users u1 ON om.observer_id = u1.id 
    LEFT JOIN users u2 ON om.target_user_id = u2.id 
    WHERE om.deleted_by IS NULL 
    ORDER BY om.created_at DESC 
    LIMIT 100
");

// ناظر انتخاب شده برای تخصیص
$selected_observer = isset($_GET['observer']) ? (int)$_GET['observer'] : 0;
$assigned_users = [];
if ($selected_observer > 0) {
    $q = mysqli_query($conn, "SELECT branch_id FROM observer_assignments WHERE observer_id = $selected_observer");
    while ($r = mysqli_fetch_assoc($q)) {
        $assigned_users[] = $r['branch_id'];
    }
}

// ناظر انتخاب شده برای تنظیم دسترسی مشاهده
$selected_view_observer = isset($_GET['observer_view']) ? (int)$_GET['observer_view'] : 0;
$can_view = '0';
if ($selected_view_observer > 0) {
    $can_view = getSetting("observer_view_{$selected_view_observer}", '0');
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پنل مدیریت - سیستم یادآوری</title>
    <link href="../assets/fonts/fonts.css" rel="stylesheet">
    <link href="../assets/css/style.css?v=1.0" rel="stylesheet">
    <style>
        .admin-panel {
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
        }
        .section-card {
            background: var(--glass-bg);
            backdrop-filter: blur(12px);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius);
            padding: 24px;
            margin-bottom: 24px;
        }
        .section-title {
            font-weight: 800;
            font-size: 1rem;
            color: var(--gold-light);
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--glass-border);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .form-group {
            margin-bottom: 16px;
        }
        .form-group label {
            display: block;
            font-weight: 700;
            font-size: 0.85rem;
            margin-bottom: 6px;
            color: var(--text-secondary);
        }
        .form-group input[type="time"],
        .form-group select {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-sm);
            background: rgba(255,255,255,0.05);
            color: var(--text);
            font-family: 'Vazirmatn';
            font-size: 0.9rem;
        }
        .form-group input[type="time"]:focus,
        .form-group select:focus {
            border-color: var(--gold-light);
            outline: none;
        }
        .toggle-row {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .toggle-switch {
            position: relative;
            width: 50px;
            height: 28px;
        }
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(255,255,255,0.1);
            border-radius: 28px;
            transition: 0.3s;
        }
        .toggle-slider:before {
            content: '';
            position: absolute;
            height: 22px;
            width: 22px;
            left: 3px;
            bottom: 3px;
            background: #fff;
            border-radius: 50%;
            transition: 0.3s;
        }
        .toggle-switch input:checked + .toggle-slider {
            background: var(--green);
        }
        .toggle-switch input:checked + .toggle-slider:before {
            transform: translateX(22px);
        }
        .btn-save {
            background: linear-gradient(135deg, var(--gold), var(--gold-light));
            color: #1a1a1a;
            border: none;
            padding: 12px 28px;
            border-radius: var(--radius-sm);
            font-family: 'Vazirmatn';
            font-weight: 800;
            font-size: 0.9rem;
            cursor: pointer;
        }
        .btn-delete {
            background: rgba(239,68,68,0.15);
            color: var(--red);
            border: 1px solid rgba(239,68,68,0.3);
            padding: 8px 16px;
            border-radius: var(--radius-sm);
            font-family: 'Vazirmatn';
            font-weight: 700;
            font-size: 0.75rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn-delete-all {
            background: var(--red);
            color: #fff;
            border: none;
            padding: 12px 24px;
            border-radius: var(--radius-sm);
            font-family: 'Vazirmatn';
            font-weight: 800;
            cursor: pointer;
            font-size: 0.85rem;
        }
        .table-wrap {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8rem;
        }
        th, td {
            padding: 12px 10px;
            text-align: right;
            border-bottom: 1px solid var(--glass-border);
        }
        th {
            color: var(--text-secondary);
            font-weight: 700;
            font-size: 0.75rem;
        }
        .badge-item {
            display: inline-block;
            padding: 3px 10px;
            background: rgba(239,68,68,0.15);
            color: var(--red);
            border-radius: 12px;
            font-size: 0.7rem;
            margin: 2px;
        }
        .alert {
            padding: 14px 18px;
            border-radius: var(--radius-sm);
            margin-bottom: 20px;
            font-weight: 700;
            font-size: 0.85rem;
        }
        .alert-success {
            background: rgba(52,211,153,0.15);
            color: var(--green);
            border: 1px solid rgba(52,211,153,0.3);
        }
        .alert-error {
            background: rgba(239,68,68,0.15);
            color: var(--red);
            border: 1px solid rgba(239,68,68,0.3);
        }
        .user-checkboxes {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 8px;
            max-height: 300px;
            overflow-y: auto;
            padding: 8px;
            background: rgba(255,255,255,0.02);
            border-radius: var(--radius-sm);
        }
        .user-checkboxes label {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.8rem;
            transition: 0.2s;
        }
        .user-checkboxes label:hover {
            background: rgba(255,255,255,0.05);
        }
        .user-checkboxes input[type="checkbox"] {
            accent-color: var(--gold-light);
        }
        .back-link {
            display: inline-block;
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 0.8rem;
            margin-bottom: 24px;
        }
        .back-link:hover { color: var(--gold-light); }
    </style>
</head>
<body>
    <div class="admin-panel">
        <a href="../index.php" class="back-link">← بازگشت به داشبورد</a>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <!-- ⏰ تنظیمات -->
        <div class="section-card">
            <div class="section-title">⏰ تنظیمات هشدار خودکار</div>
            <form method="POST">
                <div class="form-group">
                    <label>ساعت اجرای روزانه</label>
                    <input type="time" name="reminder_time" value="<?php echo $reminder_time; ?>">
                </div>
                <div class="form-group">
                    <div class="toggle-row">
                        <label class="toggle-switch">
                            <input type="checkbox" name="reminder_active" <?php echo $reminder_active == '1' ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                        <span style="font-weight:700;">فعال بودن سیستم</span>
                    </div>
                </div>
                <button type="submit" name="save_settings" class="btn-save">💾 ذخیره تنظیمات</button>
            </form>
        </div>
        
        <!-- 👥 تخصیص کاربران -->
        <div class="section-card">
            <div class="section-title">👥 تخصیص کاربران به ناظر</div>
            <form method="GET" style="margin-bottom:16px;">
                <div class="form-group">
                    <label>انتخاب ناظر</label>
                    <select name="observer" onchange="this.form.submit()">
                        <option value="">-- انتخاب کنید --</option>
                        <?php mysqli_data_seek($observers, 0); while ($obs = mysqli_fetch_assoc($observers)): ?>
                            <option value="<?php echo $obs['id']; ?>" <?php echo $selected_observer == $obs['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($obs['branch_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </form>
            
            <?php if ($selected_observer > 0): ?>
                <form method="POST">
                    <input type="hidden" name="observer_id" value="<?php echo $selected_observer; ?>">
                    <div class="user-checkboxes">
                        <?php mysqli_data_seek($branches, 0); while ($br = mysqli_fetch_assoc($branches)): ?>
                            <label>
                                <input type="checkbox" name="user_ids[]" value="<?php echo $br['id']; ?>"
                                    <?php echo in_array($br['id'], $assigned_users) ? 'checked' : ''; ?>>
                                <?php echo htmlspecialchars($br['branch_name']); ?>
                            </label>
                        <?php endwhile; ?>
                    </div>
                    <button type="submit" name="assign_users" class="btn-save" style="margin-top:16px;">💾 ذخیره تخصیص</button>
                </form>
            <?php endif; ?>
        </div>
        
        <!-- 🔐 تنظیمات دسترسی مشاهده ناظرها -->
        <div class="section-card">
            <div class="section-title">🔐 تنظیمات دسترسی مشاهده ناظرها</div>
            <p style="font-size:0.8rem;color:var(--text-secondary);margin-bottom:16px;">
                مشخص کنید هر ناظر، وضعیت کدام شعبه‌ها را می‌تواند در پاپ‌آپ اعلان‌ها مشاهده کند.
            </p>
            
            <form method="GET" style="margin-bottom:16px;">
                <div class="form-group">
                    <label>انتخاب ناظر برای تنظیم دسترسی مشاهده</label>
                    <select name="observer_view" onchange="this.form.submit()">
                        <option value="">-- انتخاب ناظر --</option>
                        <?php 
                        mysqli_data_seek($observers, 0); 
                        while ($obs = mysqli_fetch_assoc($observers)): 
                        ?>
                            <option value="<?php echo $obs['id']; ?>" <?php echo $selected_view_observer == $obs['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($obs['branch_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </form>
            
            <?php if ($selected_view_observer > 0): ?>
                <form method="POST">
                    <input type="hidden" name="view_observer_id" value="<?php echo $selected_view_observer; ?>">
                    
                    <div class="form-group">
                        <div class="toggle-row">
                            <label class="toggle-switch">
                                <input type="checkbox" name="view_active" <?php echo $can_view == '1' ? 'checked' : ''; ?>>
                                <span class="toggle-slider"></span>
                            </label>
                            <span style="font-weight:700;">اجازه مشاهده وضعیت شعبه‌ها</span>
                        </div>
                    </div>
                    
                    <button type="submit" name="save_view_access_btn" class="btn-save" style="margin-top:8px;">
                        💾 ذخیره دسترسی
                    </button>
                </form>
            <?php endif; ?>
        </div>
        
        <!-- 🔔 هشدارهای خودکار -->
        <div class="section-card">
            <div class="section-title">🔔 هشدارهای خودکار فعال</div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>کاربر</th>
                            <th>تاریخ</th>
                            <th>آیتم‌های ثبت نشده</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($active_reminders) == 0): ?>
                            <tr><td colspan="4" style="text-align:center;color:var(--text-secondary);padding:30px;">هیچ هشداری وجود ندارد ✅</td></tr>
                        <?php else: ?>
                            <?php while ($rem = mysqli_fetch_assoc($active_reminders)): 
                                $items = json_decode($rem['missing_items'], true) ?: [];
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($rem['branch_name']); ?></td>
                                    <td><?php echo $rem['date_shamsi']; ?></td>
                                    <td>
                                        <?php foreach ($items as $item): ?>
                                            <span class="badge-item"><?php echo htmlspecialchars($item); ?></span>
                                        <?php endforeach; ?>
                                    </td>
                                    <td>
                                        <a href="?delete_reminder=<?php echo $rem['id']; ?>" 
                                           class="btn-delete" 
                                           onclick="return confirm('حذف این هشدار؟')">🗑️ حذف</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if (mysqli_num_rows($active_reminders) > 0): ?>
                <form method="POST" style="margin-top:16px;" onsubmit="return confirm('حذف همه هشدارها؟')">
                    <button type="submit" name="delete_all_reminders" class="btn-delete-all">🗑️ حذف همه هشدارها</button>
                </form>
            <?php endif; ?>
        </div>
        
        <!-- 📨 پیام‌های ناظر -->
        <div class="section-card">
            <div class="section-title">📨 پیام‌های ناظرین</div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>فرستنده</th>
                            <th>گیرنده</th>
                            <th>عنوان</th>
                            <th>تاریخ</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($messages) == 0): ?>
                            <tr><td colspan="5" style="text-align:center;color:var(--text-secondary);padding:30px;">هیچ پیامی وجود ندارد</td></tr>
                        <?php else: ?>
                            <?php while ($msg = mysqli_fetch_assoc($messages)): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($msg['observer_name']); ?></td>
                                    <td><?php echo htmlspecialchars($msg['target_name'] ?? 'همه'); ?></td>
                                    <td><?php echo htmlspecialchars($msg['title']); ?></td>
                                    <td><?php echo $msg['created_at']; ?></td>
                                    <td>
                                        <a href="?delete_message=<?php echo $msg['id']; ?>" 
                                           class="btn-delete" 
                                           onclick="return confirm('حذف این پیام؟')">🗑️ حذف</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>