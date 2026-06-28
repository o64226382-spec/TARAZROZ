<?php
/**
 * محتوای پاپ‌آپ اعلان‌ها - یکپارچه برای همه نقش‌ها
 */
define('SECURE_ACCESS', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/reminder_functions.php';
require_once __DIR__ . '/../includes/jdf.php';

if (!isLoggedIn()) die('دسترسی غیرمجاز');

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// ========== پردازش ارسال پیام (ناظر) ==========
if ($role === 'observer' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_msg'])) {
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $body = mysqli_real_escape_string($conn, $_POST['message']);
    $target = $_POST['target_user'] ?? 'all';
    
    if (!empty($title) && !empty($body)) {
        $targets = [];
        
        if ($target === 'all') {
            $q = mysqli_query($conn, "SELECT id FROM users WHERE role = 'branch'");
            while ($r = mysqli_fetch_assoc($q)) $targets[] = $r['id'];
        } else {
            $targets[] = (int)$target;
        }
        
        foreach ($targets as $uid) {
            mysqli_query($conn, "INSERT INTO observer_messages (observer_id, target_user_id, title, message) VALUES ($user_id, $uid, '$title', '$body')");
        }
    }
}

// ========== تابع تبدیل تاریخ میلادی به شمسی ==========
function toJalaliDate($datetime) {
    if (empty($datetime)) return '';
    $timestamp = strtotime($datetime);
    return jdate('Y/m/d H:i', $timestamp);
}

// ========== دریافت داده‌ها ==========
$notifications = [];

if ($role === 'branch') {
    $notifications = getAllNotifications($user_id);
}

if ($role === 'observer') {
    $q = mysqli_query($conn, "
        SELECT om.*, u.branch_name as target_name 
        FROM observer_messages om 
        LEFT JOIN users u ON om.target_user_id = u.id 
        WHERE om.observer_id = $user_id AND om.deleted_by IS NULL 
        ORDER BY om.created_at DESC LIMIT 30
    ");
    while ($row = mysqli_fetch_assoc($q)) {
        $row['type'] = 'sent_message';
        $notifications[] = $row;
    }
    
    // همه کاربران branch
    $all_branches = [];
    $q = mysqli_query($conn, "SELECT id, branch_name FROM users WHERE role = 'branch' ORDER BY branch_name");
    while ($r = mysqli_fetch_assoc($q)) $all_branches[] = $r;
}
?>

<!-- ========== هدر ========== -->
<div class="notif-popup-header">
    <span>
        <?php if ($role === 'branch'): ?>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-4px;margin-left:6px;"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
            یادآوری‌ها
        <?php elseif ($role === 'observer'): ?>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-4px;margin-left:6px;"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            پیام‌رسانی
        <?php endif; ?>
    </span>
    <button class="notif-popup-close" onclick="closeNotifPopup()">✕</button>
</div>

<!-- ========== بدنه ========== -->
<div class="notif-popup-body">

    <?php if ($role === 'observer'): ?>
    <!-- ===== بخش ارسال پیام (ناظر) ===== -->
    <div class="notif-compose">
        <div class="notif-compose-title">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-3px;margin-left:4px;"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
            ارسال پیام جدید
        </div>
        <form method="POST" onsubmit="event.preventDefault(); sendObserverMsg(this);">
            <div class="notif-field-icon">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
            <select name="target_user" class="notif-select notif-select-icon">
                <?php foreach ($all_branches as $br): ?>
                    <option value="<?php echo $br['id']; ?>">🏢 <?php echo htmlspecialchars($br['branch_name']); ?></option>
                <?php endforeach; ?>
                <option value="all" class="all-option">👥 همه شعبه‌ها</option>
            </select>
            
            <div class="notif-field-icon">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg>
            </div>
            <input type="text" name="title" class="notif-input notif-input-icon" placeholder="عنوان پیام..." required>
            
            <textarea name="message" class="notif-textarea" placeholder="متن پیام خود را بنویسید..." required></textarea>
            
            <button type="submit" name="send_msg" class="notif-send-btn">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-4px;margin-left:6px;"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                ارسال پیام
            </button>
        </form>
    </div>
    
    <!-- ===== بخش ناظر: هشدارهای شعبه‌های تخصیصی (فقط نمایش) ===== -->
    <?php
    $can_view = getSetting("observer_view_{$user_id}", '0');
    if ($can_view == '1'):
        
        $my_branches = [];
        $q = mysqli_query($conn, "
            SELECT u.id, u.branch_name 
            FROM observer_assignments oa 
            JOIN users u ON oa.branch_id = u.id 
            WHERE oa.observer_id = $user_id
            ORDER BY u.branch_name
        ");
        while ($r = mysqli_fetch_assoc($q)) $my_branches[] = $r;
        
        if (!empty($my_branches)):
            $my_branch_ids = array_column($my_branches, 'id');
            $my_branch_ids_str = implode(',', $my_branch_ids);
            
            $my_reminders = [];
            if (!empty($my_branch_ids_str)) {
                $q = mysqli_query($conn, "
                    SELECT r.*, u.branch_name 
                    FROM reminders r 
                    JOIN users u ON r.user_id = u.id 
                    WHERE r.is_active = 1 
                      AND r.user_id IN ($my_branch_ids_str)
                    ORDER BY r.date_shamsi DESC, u.branch_name
                    LIMIT 50
                ");
                while ($r = mysqli_fetch_assoc($q)) $my_reminders[] = $r;
            }
        ?>
        
        <div class="notif-compose" style="background:rgba(239,68,68,0.05);border-color:rgba(239,68,68,0.15);">
            <div class="notif-compose-title" style="color:#ef4444;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-4px;margin-left:6px;"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
                هشدارهای شعبه‌های من
                <span style="font-size:0.7rem;font-weight:400;color:#94a3b8;margin-right:8px;">(فقط نمایش)</span>
            </div>
            
            <?php if (empty($my_reminders)): ?>
                <div style="text-align:center;padding:20px;color:#94a3b8;">
                    <span style="font-size:2rem;display:block;margin-bottom:8px;">✅</span>
                    <span style="font-weight:700;">همه شعبه‌ها ثبت دارند</span>
                </div>
            <?php else: ?>
                <div style="display:flex;flex-direction:column;gap:8px;">
                    <?php foreach ($my_reminders as $rem): 
                        $items = json_decode($rem['missing_items'], true) ?: [];
                    ?>
                        <div style="display:flex;align-items:flex-start;justify-content:space-between;padding:10px 14px;background:rgba(239,68,68,0.08);border-radius:10px;flex-wrap:wrap;gap:8px;">
                            <div style="flex:1;min-width:120px;">
                                <div style="font-weight:800;font-size:0.82rem;color:#fca5a5;margin-bottom:4px;">
                                    🏢 <?php echo htmlspecialchars($rem['branch_name']); ?>
                                </div>
                                <div style="font-size:0.7rem;color:#94a3b8;">
                                    📅 <?php echo htmlspecialchars($rem['date_shamsi']); ?>
                                </div>
                            </div>
                            <div style="display:flex;flex-wrap:wrap;gap:4px;">
                                <?php foreach ($items as $item): ?>
                                    <span style="display:inline-block;padding:3px 10px;background:rgba(239,68,68,0.15);color:#fca5a5;border-radius:12px;font-size:0.68rem;font-weight:700;white-space:nowrap;">
                                        #<?php echo htmlspecialchars($item); ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <?php else: ?>
        <div class="notif-compose" style="background:rgba(255,255,255,0.02);border-color:rgba(255,255,255,0.06);">
            <div class="notif-compose-title" style="color:#94a3b8;">
                📋 شعبه‌ای به شما تخصیص داده نشده است
            </div>
        </div>
        <?php endif; ?>
        
    <?php endif; ?>
    <?php endif; ?>

    <!-- ===== لیست اعلان‌ها / پیام‌ها ===== -->
    <?php if (empty($notifications)): ?>
        <div class="notif-empty">
            <span class="empty-icon">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </span>
            <p>همه چی مرتبه!</p>
        </div>
    <?php else: ?>
        <?php foreach ($notifications as $notif): ?>
            
            <?php if (($notif['type'] ?? '') === 'reminder'): ?>
                <div class="notif-item reminder-item">
                    <div class="notif-item-header">
                        <span class="notif-item-badge reminder-badge">هشدار</span>
                    </div>
                    <div class="notif-date">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#f87171" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        <?php echo htmlspecialchars($notif['date_shamsi']); ?>
                    </div>
                    <div class="notif-tags">
                        <?php foreach ($notif['missing_items'] as $item): ?>
                            <span class="notif-tag">
                                <svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor" stroke="none"><circle cx="12" cy="12" r="10"/></svg>
                                <?php echo htmlspecialchars($item); ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
                
            <?php elseif (($notif['type'] ?? '') === 'message'): ?>
                <div class="notif-item message-item <?php echo $notif['is_read'] ? 'read' : 'unread'; ?>" 
                     onclick="markMessageRead(<?php echo $notif['id']; ?>)">
                    <div class="notif-item-header">
                        <span class="notif-item-badge message-badge <?php echo $notif['is_read'] ? '' : 'unread-badge'; ?>">
                            <?php echo $notif['is_read'] ? 'خوانده شده' : 'پیام جدید'; ?>
                        </span>
                    </div>
                    <div class="notif-sender">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        <?php echo htmlspecialchars($notif['observer_name']); ?>
                    </div>
                    <div class="notif-title"><?php echo htmlspecialchars($notif['title']); ?></div>
                    <div class="notif-text"><?php echo nl2br(htmlspecialchars($notif['message'])); ?></div>
                    <div class="notif-time">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        <?php echo toJalaliDate($notif['created_at']); ?>
                    </div>
                </div>
                
            <?php elseif (($notif['type'] ?? '') === 'sent_message'): ?>
                <div class="notif-item sent-item">
                    <div class="notif-item-header">
                        <span class="notif-item-badge sent-badge">ارسال شده</span>
                    </div>
                    <div class="notif-sender">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        <?php echo htmlspecialchars($notif['target_name'] ?? 'همه شعبه‌ها'); ?>
                    </div>
                    <div class="notif-title"><?php echo htmlspecialchars($notif['title']); ?></div>
                    <div class="notif-text"><?php echo nl2br(htmlspecialchars($notif['message'])); ?></div>
                    <div class="notif-time">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        <?php echo toJalaliDate($notif['created_at']); ?>
                    </div>
                    <button class="notif-delete-btn" onclick="deleteObserverMsg(<?php echo $notif['id']; ?>)">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    </button>
                </div>
            <?php endif; ?>
            
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<style>
.notif-compose {
    background: rgba(0,0,0,0.15);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 16px;
    padding: 18px;
    margin-bottom: 18px;
}
body.light .notif-compose {
    background: rgba(0,0,0,0.03);
    border: 1px solid rgba(0,0,0,0.08);
}
.notif-compose-title {
    font-weight: 800;
    font-size: 0.88rem;
    color: #d4af37;
    margin-bottom: 14px;
}
.notif-select, .notif-input, .notif-textarea {
    width: 100%;
    padding: 12px 16px;
    margin-bottom: 10px;
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 12px;
    background: rgba(255,255,255,0.06);
    color: #fff;
    font-family: 'Vazirmatn';
    font-size: 0.82rem;
}
body.light .notif-select,
body.light .notif-input,
body.light .notif-textarea {
    border: 1px solid rgba(0,0,0,0.15);
    background: #fff;
    color: #1e293b;
}
.notif-select:focus, .notif-input:focus, .notif-textarea:focus {
    border-color: #d4af37;
    outline: none;
}
.notif-textarea {
    min-height: 80px;
    resize: vertical;
    line-height: 1.8;
}
.notif-send-btn {
    width: 100%;
    padding: 13px;
    background: #d4af37;
    color: #fff;
    border: none;
    border-radius: 12px;
    font-family: 'Vazirmatn';
    font-weight: 800;
    font-size: 0.88rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}
body.light .notif-send-btn { color: #fff; }
.notif-send-btn:hover { background: #c19b2e; }

.notif-item {
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 14px;
    padding: 16px 18px;
    margin-bottom: 12px;
    position: relative;
}
body.light .notif-item {
    background: #fff;
    border: 1px solid rgba(0,0,0,0.08);
}
.notif-item.reminder-item { border-right: 3px solid #ef4444; }
.notif-item.message-item { cursor: pointer; }
.notif-item.message-item.unread {
    border-right: 3px solid #d4af37;
    background: rgba(212,175,55,0.08);
}
body.light .notif-item.message-item.unread { background: rgba(212,175,55,0.06); }
.notif-item.message-item.read { opacity: 0.55; }
.notif-item.sent-item {
    border-right: 3px solid #10b981;
    padding-left: 55px;
}

.notif-item-header { margin-bottom: 10px; }
.notif-item-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.68rem;
    font-weight: 700;
}
.reminder-badge { background: #ef4444; color: #fff; }
.message-badge { background: #d4af37; color: #fff; }
.sent-badge { background: #10b981; color: #fff; }

.notif-date {
    font-weight: 700;
    font-size: 0.88rem;
    color: #e2e8f0;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 8px;
}
body.light .notif-date { color: #1e293b; }
.notif-sender {
    font-weight: 700;
    font-size: 0.82rem;
    color: #e2e8f0;
    margin-bottom: 6px;
    display: flex;
    align-items: center;
    gap: 8px;
}
body.light .notif-sender { color: #1e293b; }
.notif-title {
    font-weight: 800;
    font-size: 0.9rem;
    color: #f1f5f9;
    margin-bottom: 6px;
    line-height: 1.5;
}
body.light .notif-title { color: #0f172a; }
.notif-text {
    font-size: 0.8rem;
    color: #94a3b8;
    line-height: 1.8;
    margin-bottom: 10px;
}
body.light .notif-text { color: #64748b; }
.notif-time {
    font-size: 0.7rem;
    color: #64748b;
    display: flex;
    align-items: center;
    gap: 6px;
}
body.light .notif-time { color: #94a3b8; }

.notif-tags { display: flex; flex-wrap: wrap; gap: 8px; }
.notif-tag {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 16px;
    background: rgba(239,68,68,0.2);
    color: #fca5a5;
    font-size: 0.75rem;
    font-weight: 700;
    border-radius: 20px;
    border: 1px solid rgba(239,68,68,0.3);
}
body.light .notif-tag {
    background: rgba(239,68,68,0.1);
    color: #dc2626;
    border: 1px solid rgba(239,68,68,0.2);
}

.notif-delete-btn {
    position: absolute;
    top: 14px;
    left: 14px;
    background: transparent;
    color: #ef4444;
    border: 1px solid rgba(239,68,68,0.3);
    border-radius: 8px;
    padding: 6px 10px;
    cursor: pointer;
    transition: all 0.2s;
}
.notif-delete-btn:hover { background: #ef4444; color: #fff; }

.notif-empty {
    text-align: center;
    padding: 50px 20px;
    color: #94a3b8;
}
body.light .notif-empty { color: #64748b; }
.notif-empty .empty-icon {
    display: block;
    margin-bottom: 16px;
    color: #10b981;
}
.notif-empty p {
    font-weight: 700;
    font-size: 0.95rem;
}
</style>