<?php
define('SECURE_ACCESS', true);
require_once '../includes/config.php';
require_once '../includes/auth.php';

requireLogin();
redirectIfAdmin();
if (isObserver()) { header('Location: ../observer/index.php'); exit(); }

$user_id = $_SESSION['user_id'];

// ========== حذف روز ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_date'])) {
    $delete_date = $_POST['delete_date'];
    
    // ⭐ چک مالکیت
    $check_stmt = mysqli_prepare($conn, "SELECT id FROM daily_reports WHERE user_id = ? AND report_date = ?");
    mysqli_stmt_bind_param($check_stmt, "is", $user_id, $delete_date);
    mysqli_stmt_execute($check_stmt);
    
    if (mysqli_num_rows(mysqli_stmt_get_result($check_stmt)) == 0) {
        echo json_encode(['success' => false, 'message' => 'گزارشی یافت نشد']);
        exit();
    }
    
    // ⭐ حذف امن
    $del_stmt = mysqli_prepare($conn, "DELETE FROM daily_reports WHERE user_id = ? AND report_date = ?");
    mysqli_stmt_bind_param($del_stmt, "is", $user_id, $delete_date);
    $del = mysqli_stmt_execute($del_stmt);
    
    echo json_encode(['success' => $del, 'message' => $del ? "روز $delete_date حذف شد" : "خطا در حذف"]);
    exit();
}

// ========== ویرایش تاریخ ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['old_date']) && isset($_POST['new_date'])) {
    $old_date = $_POST['old_date'];
    $new_date = $_POST['new_date'];
    
    // ⭐ چک تکراری نبودن تاریخ جدید
    $check_stmt = mysqli_prepare($conn, "SELECT id FROM daily_reports WHERE user_id = ? AND report_date = ?");
    mysqli_stmt_bind_param($check_stmt, "is", $user_id, $new_date);
    mysqli_stmt_execute($check_stmt);
    
    if (mysqli_num_rows(mysqli_stmt_get_result($check_stmt)) > 0) {
        echo json_encode(['success' => false, 'message' => 'تاریخ جدید تکراری است']);
        exit();
    }
    
    // ⭐ چک وجود گزارش قدیمی
    $check_stmt = mysqli_prepare($conn, "SELECT id FROM daily_reports WHERE user_id = ? AND report_date = ?");
    mysqli_stmt_bind_param($check_stmt, "is", $user_id, $old_date);
    mysqli_stmt_execute($check_stmt);
    
    if (mysqli_num_rows(mysqli_stmt_get_result($check_stmt)) == 0) {
        echo json_encode(['success' => false, 'message' => 'گزارش اصلی یافت نشد']);
        exit();
    }
    
    // ⭐ آپدیت امن تاریخ
    $update_stmt = mysqli_prepare($conn, "UPDATE daily_reports SET report_date = ? WHERE user_id = ? AND report_date = ?");
    mysqli_stmt_bind_param($update_stmt, "sis", $new_date, $user_id, $old_date);
    $update = mysqli_stmt_execute($update_stmt);
    
    echo json_encode(['success' => $update, 'message' => $update ? "تاریخ با موفقیت تغییر کرد" : "خطا در بروزرسانی"]);
    exit();
}

// ========== دریافت لیست تاریخ‌ها ==========
$archived_dates = [];
$dates_stmt = mysqli_prepare($conn, "SELECT DISTINCT report_date FROM daily_reports WHERE user_id = ? ORDER BY report_date DESC");
mysqli_stmt_bind_param($dates_stmt, "i", $user_id);
mysqli_stmt_execute($dates_stmt);
$dates_result = mysqli_stmt_get_result($dates_stmt);

while ($row = mysqli_fetch_assoc($dates_result)) {
    $archived_dates[] = $row['report_date'];
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>بایگانی | تراز روزانه</title>
    <link href="../assets/fonts/fonts.css" rel="stylesheet">
    <link rel="preload" href="../assets/fonts/Modam-Medium.woff2" as="font" type="font/woff2" crossorigin="anonymous">
    <style>
        :root {
            --bg: #f0f4f8;
            --card-bg: #ffffff;
            --text: #1a1f2e;
            --text-secondary: #555f6e;
            --accent: #1a6fd4;
            --green: #1a7f37;
            --red: #d12f2a;
            --border: #d0d7de;
            --radius: 16px;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Vazirmatn', sans-serif; 
            background: var(--bg); 
            padding: 20px; 
            direction: rtl; 
            min-height: 100vh;
        }
        .container { max-width: 700px; margin: 0 auto; }
        
        .header { 
            background: var(--card-bg); 
            border-radius: var(--radius); 
            padding: 15px 20px; 
            margin-bottom: 20px; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            box-shadow: 0 2px 8px rgba(0,0,0,0.05); 
        }
        .header h2 { color: var(--accent); font-size: 1.1rem; }
        .back-btn { 
            background: #e2e8f0; 
            padding: 8px 16px; 
            border-radius: 8px; 
            text-decoration: none; 
            color: #1e293b; 
            font-size: 0.8rem;
            font-weight: 600;
            transition: all 0.2s;
        }
        .back-btn:hover { background: #cbd5e1; }
        
        .archive-card { 
            background: var(--card-bg); 
            border-radius: var(--radius); 
            padding: 16px; 
            margin-bottom: 12px; 
            box-shadow: 0 2px 8px rgba(0,0,0,0.05); 
            transition: all 0.2s;
        }
        .archive-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        
        .archive-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            flex-wrap: wrap; 
            gap: 10px; 
        }
        .archive-date { 
            font-weight: bold; 
            font-size: 1.05rem; 
            color: var(--accent); 
            background: #eef2ff; 
            padding: 8px 16px; 
            border-radius: 20px; 
            cursor: pointer;
            transition: all 0.2s;
            border: none;
        }
        .archive-date:hover { background: #dbe4ff; transform: translateY(-1px); }
        
        .btn-danger { 
            background: var(--red); 
            color: white; 
            border: none; 
            border-radius: 8px; 
            padding: 8px 16px; 
            cursor: pointer;
            font-family: 'Vazirmatn', sans-serif;
            font-size: 0.78rem;
            font-weight: 600;
            transition: all 0.2s;
        }
        .btn-danger:hover { background: #b91c1c; transform: translateY(-1px); }
        
        .btn-edit-toggle { 
            background: var(--accent); 
            color: white; 
            border: none; 
            border-radius: 8px; 
            padding: 10px; 
            width: 100%; 
            cursor: pointer; 
            margin-top: 10px;
            font-family: 'Vazirmatn', sans-serif;
            font-size: 0.78rem;
            font-weight: 600;
            transition: all 0.2s;
        }
        .btn-edit-toggle:hover { background: #1558b0; }
        
        .edit-block { 
            display: none; 
            margin-top: 12px; 
            padding-top: 12px; 
            border-top: 2px solid #e2e8f0; 
        }
        .edit-row { 
            display: flex; 
            gap: 10px; 
            align-items: center; 
            flex-wrap: wrap; 
        }
        .edit-input { 
            flex: 2; 
            padding: 10px 12px; 
            border-radius: 8px; 
            border: 2px solid #cbd5e1; 
            font-family: 'Vazirmatn', sans-serif; 
            font-size: 0.85rem;
            transition: border-color 0.2s;
        }
        .edit-input:focus { outline: none; border-color: var(--accent); }
        
        .btn-save { 
            background: var(--green); 
            color: white; 
            padding: 10px 20px; 
            border-radius: 8px; 
            border: none; 
            cursor: pointer; 
            font-family: 'Vazirmatn', sans-serif;
            font-weight: 600;
            transition: all 0.2s;
        }
        .btn-save:hover { background: #166b2e; }
        
        .btn-cancel { 
            background: #64748b; 
            color: white; 
            padding: 10px 20px; 
            border-radius: 8px; 
            border: none; 
            cursor: pointer; 
            font-family: 'Vazirmatn', sans-serif;
            font-weight: 600;
            transition: all 0.2s;
        }
        .btn-cancel:hover { background: #475569; }
        
        .empty-state { 
            background: var(--card-bg); 
            border-radius: var(--radius); 
            padding: 40px; 
            text-align: center; 
            color: #64748b; 
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            font-size: 0.9rem;
        }
        .empty-state .icon { font-size: 3rem; display: block; margin-bottom: 10px; }
        
        .toast-container { 
            position: fixed; 
            bottom: 20px; 
            right: 20px; 
            z-index: 9999; 
            display: flex; 
            flex-direction: column; 
            gap: 8px; 
        }
        .toast { 
            padding: 12px 20px; 
            border-radius: 10px; 
            color: white; 
            font-size: 0.9rem; 
            font-family: 'Vazirmatn', sans-serif;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            animation: slideInRight 0.3s ease; 
        }
        .toast.success { background: #10b981; }
        .toast.error { background: #ef4444; }
        
        @keyframes slideInRight { 
            from { opacity: 0; transform: translateX(50px); } 
            to { opacity: 1; transform: translateX(0); } 
        }
        
        @media (max-width: 480px) {
            body { padding: 10px; }
            .header { padding: 12px 14px; }
            .edit-row { flex-direction: column; }
            .edit-input { width: 100%; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="toast-container" id="toastContainer"></div>
    
    <div class="header">
        <h2>📂 بایگانی گزارش‌ها</h2>
        <a href="index.php" class="back-btn">← بازگشت</a>
    </div>
    
    <?php if (empty($archived_dates)): ?>
        <div class="empty-state">
            <span class="icon">📭</span>
            هیچ گزارشی ذخیره نشده است.
        </div>
    <?php else: ?>
        <?php foreach ($archived_dates as $date): 
            $safeId = 'edit_' . md5($date);
        ?>
            <div class="archive-card" data-date="<?php echo htmlspecialchars($date); ?>">
                <div class="archive-header">
                    <button class="archive-date" onclick="window.location.href='index.php?date=<?php echo urlencode($date); ?>'">
                        📅 <?php echo htmlspecialchars(str_replace('-', '/', $date)); ?>
                    </button>
                    <button class="btn-danger" onclick="deleteDay('<?php echo htmlspecialchars($date); ?>')">
                        🗑️ حذف
                    </button>
                </div>
                <button class="btn-edit-toggle" onclick="toggleEditBlock('<?php echo $safeId; ?>')">
                    ✏️ ویرایش تاریخ این روز
                </button>
                <div id="<?php echo $safeId; ?>" class="edit-block">
                    <div class="edit-row">
                        <input type="text" 
                               id="input_<?php echo $safeId; ?>" 
                               value="<?php echo htmlspecialchars(str_replace('-', '/', $date)); ?>" 
                               class="edit-input" 
                               placeholder="۱۴۰۴/۰۱/۱۵">
                        <button class="btn-save" onclick="saveNewDate('<?php echo htmlspecialchars($date); ?>', '<?php echo $safeId; ?>')">
                            💾 ذخیره
                        </button>
                        <button class="btn-cancel" onclick="toggleEditBlock('<?php echo $safeId; ?>')">
                            انصراف
                        </button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
// ========== نمایش پیام ==========
function showToast(msg, type = 'success') {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = 'toast ' + type;
    toast.textContent = msg;
    container.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}

// ========== نمایش/مخفی کردن ویرایش ==========
function toggleEditBlock(id) {
    const block = document.getElementById(id);
    block.style.display = block.style.display === 'block' ? 'none' : 'block';
}

// ========== حذف روز ==========
function deleteDay(date) {
    if (!confirm('آیا از حذف کامل گزارش روز ' + date.replace(/-/g, '/') + ' مطمئن هستید؟\nاین عمل قابل بازگشت نیست!')) {
        return;
    }
    
    fetch('archive.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'delete_date=' + encodeURIComponent(date)
    })
    .then(r => r.json())
    .then(d => {
        showToast(d.message, d.success ? 'success' : 'error');
        if (d.success) {
            setTimeout(() => location.reload(), 1500);
        }
    })
    .catch(() => showToast('خطا در ارتباط با سرور', 'error'));
}

// ========== ذخیره تاریخ جدید ==========
function saveNewDate(oldDate, blockId) {
    const input = document.getElementById('input_' + blockId);
    const newDate = input.value.trim();
    
    if (!newDate.match(/^\d{4}\/\d{2}\/\d{2}$/)) {
        showToast('فرمت تاریخ باید ۱۴۰۴/۰۱/۱۵ باشد', 'error');
        return;
    }
    
    const newDateDb = newDate.replace(/\//g, '-');
    
    fetch('archive.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'old_date=' + encodeURIComponent(oldDate) + '&new_date=' + encodeURIComponent(newDateDb)
    })
    .then(r => r.json())
    .then(d => {
        showToast(d.message, d.success ? 'success' : 'error');
        if (d.success) {
            document.getElementById(blockId).style.display = 'none';
            setTimeout(() => location.reload(), 1500);
        }
    })
    .catch(() => showToast('خطا در ارتباط با سرور', 'error'));
}
</script>
</body>
</html>