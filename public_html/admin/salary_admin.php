<?php
define('SECURE_ACCESS', true);
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireAdmin();

$months = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];

// ========== تنظیم دسترسی ==========
if (isset($_POST['action']) && $_POST['action'] == 'toggle_permission') {
    $uid = intval($_POST['user_id']);
    $perm = mysqli_real_escape_string($conn, $_POST['permission']);
    
    $res = mysqli_query($conn, "SELECT permissions FROM users WHERE id = $uid");
    $row = mysqli_fetch_assoc($res);
    $perms = $row['permissions'] ? explode(',', $row['permissions']) : [];
    
    if (in_array($perm, $perms)) {
        $perms = array_diff($perms, [$perm]);
    } else {
        $perms[] = $perm;
    }
    
    $new_perms = implode(',', array_filter($perms));
    mysqli_query($conn, "UPDATE users SET permissions = '$new_perms' WHERE id = $uid");
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit;
}

// ========== حذف فیش ==========
if (isset($_POST['action']) && $_POST['action'] == 'delete_slip') {
    $sid = intval($_POST['slip_id']);
    mysqli_query($conn, "DELETE FROM salary_archive WHERE id = $sid");
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit;
}

// ========== لیست کاربران ==========
$users = mysqli_query($conn, "SELECT id, username, branch_name, role, permissions FROM users WHERE role != 'admin' ORDER BY branch_name");

// ========== همه فیش‌ها ==========
$all_slips = mysqli_query($conn, "
    SELECT sa.*, sp.first_name, sp.last_name 
    FROM salary_archive sa 
    JOIN salary_personnel sp ON sa.personnel_id = sp.id 
    ORDER BY sa.year DESC, sa.month DESC
");

function fmt($n) { return number_format($n); }
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت فیش حقوقی | پنل ادمین</title>
    <link href="../assets/fonts/fonts.css" rel="stylesheet">
    <style>
        :root {
            --bg: #0a0f1a;
            --surface: rgba(255,255,255,0.03);
            --border: rgba(255,255,255,0.06);
            --text: #e8ecf1;
            --text-secondary: #8899aa;
            --accent: #4b8cf7;
            --green: #10b981;
            --red: #ef4444;
            --radius: 14px;
            --radius-sm: 8px;
        }
        body.light {
            --bg: #f5f6f8;
            --surface: #ffffff;
            --border: #e0e3e8;
            --text: #1a1f2e;
            --text-secondary: #555f6e;
            --accent: #3b6fd4;
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Vazirmatn', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            padding: 16px;
        }
        .container { max-width: 1300px; margin: 0 auto; }
        .header {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 16px 20px;
            margin-bottom: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .header h2 { font-size: 1rem; font-weight: 700; display: flex; align-items: center; gap: 8px; }
        .header-actions { display: flex; gap: 8px; }
        .back-btn, .theme-btn {
            padding: 8px 16px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: var(--surface);
            color: var(--text);
            text-decoration: none;
            font-family: 'Vazirmatn';
            font-size: 0.75rem;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-block;
        }
        .back-btn:hover, .theme-btn:hover { border-color: var(--accent); color: var(--accent); }
        
        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 18px;
            margin-bottom: 16px;
        }
        .card-title {
            font-size: 0.85rem;
            font-weight: 700;
            margin-bottom: 14px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border);
            color: var(--accent);
        }
        
        .table-wrap { overflow-x: auto; }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.7rem;
            min-width: 1000px;
        }
        th, td {
            padding: 8px 5px;
            border-bottom: 1px solid var(--border);
            text-align: center;
            white-space: nowrap;
        }
        th {
            color: var(--text-secondary);
            font-size: 0.65rem;
            font-weight: 600;
            position: sticky;
            top: 0;
            background: var(--surface);
        }
        tr:hover td { background: rgba(255,255,255,0.02); }
        .amount { text-align: left; }
        .net { color: var(--green); font-weight: 700; }
        .deduction { color: var(--red); }
        
        .toggle-btn {
            padding: 5px 12px;
            border-radius: 20px;
            border: 2px solid var(--border);
            cursor: pointer;
            font-family: 'Vazirmatn';
            font-size: 0.65rem;
            font-weight: 600;
            transition: all 0.2s;
            min-width: 65px;
            background: transparent;
        }
        .toggle-on {
            background: rgba(16,185,129,0.12);
            border-color: var(--green);
            color: var(--green);
        }
        .toggle-off {
            background: rgba(239,68,68,0.08);
            border-color: var(--red);
            color: var(--red);
        }
        
        .delete-btn {
            padding: 4px 10px;
            border-radius: 6px;
            border: 1px solid var(--red);
            background: rgba(239,68,68,0.1);
            color: var(--red);
            cursor: pointer;
            font-family: 'Vazirmatn';
            font-size: 0.63rem;
            transition: all 0.2s;
        }
        .delete-btn:hover {
            background: var(--red);
            color: #fff;
        }
        .print-btn {
            padding: 4px 10px;
            border-radius: 6px;
            border: 1px solid var(--accent);
            background: rgba(75,140,247,0.1);
            color: var(--accent);
            cursor: pointer;
            font-family: 'Vazirmatn';
            font-size: 0.63rem;
            text-decoration: none;
            transition: all 0.2s;
            display: inline-block;
        }
        .print-btn:hover {
            background: var(--accent);
            color: #fff;
        }
        
        .toast {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            padding: 10px 24px;
            border-radius: 10px;
            color: #fff;
            font-size: 0.78rem;
            font-weight: 600;
            z-index: 999;
            animation: slideDown 0.3s ease;
        }
        .toast.success { background: #10b981; }
        .toast.error { background: #ef4444; }
        @keyframes slideDown {
            from { opacity:0; transform: translateX(-50%) translateY(-15px); }
            to { opacity:1; transform: translateX(-50%) translateY(0); }
        }
    </style>
</head>
<body class="<?php echo isset($_COOKIE['theme']) && $_COOKIE['theme'] == 'light' ? 'light' : ''; ?>">
<div class="container">
    
    <div class="header">
        <h2>
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="7" width="18" height="13" rx="2"/>
                <path d="M8 7V5a2 2 0 012-2h4a2 2 0 012 2v2"/>
                <line x1="12" y1="12" x2="12" y2="16"/>
                <line x1="9" y1="14" x2="15" y2="14"/>
            </svg>
            مدیریت فیش حقوقی
        </h2>
        <div class="header-actions">
            <button class="theme-btn" onclick="toggleTheme()" id="themeBtn">حالت روشن</button>
            <a href="index.php" class="back-btn">پنل مدیریت</a>
        </div>
    </div>
    
    <!-- بخش ۱: دسترسی کاربران -->
    <div class="card">
        <div class="card-title">دسترسی کاربران به بخش فیش حقوقی</div>
        <table style="min-width:auto;">
            <thead>
                <tr>
                    <th>کاربر</th>
                    <th>نقش</th>
                    <th>دسترسی</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($user = mysqli_fetch_assoc($users)): 
                    $user_perms = explode(',', $user['permissions'] ?? '');
                    $has_access = in_array('salary', $user_perms);
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($user['branch_name'] ?: $user['username']); ?></td>
                    <td><?php echo $user['role'] === 'branch' ? 'شعبه' : 'ناظر'; ?></td>
                    <td>
                        <button 
                            class="toggle-btn <?php echo $has_access ? 'toggle-on' : 'toggle-off'; ?>"
                            onclick="togglePermission(<?php echo $user['id']; ?>, this)">
                            <?php echo $has_access ? 'فعال' : 'محدود'; ?>
                        </button>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    
    <!-- بخش ۲: جزئیات کامل فیش‌ها -->
    <div class="card">
        <div class="card-title">جزئیات تمام فیش‌های ثبت شده</div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>پرسنل</th>
                        <th>ماه/سال</th>
                        <th>روز</th>
                        <th>پایه</th>
                        <th>مسکن</th>
                        <th>بن</th>
                        <th>اولاد</th>
                        <th>اضافه‌کار</th>
                        <th>پاداش</th>
                        <th>ناخالص</th>
                        <th>بیمه</th>
                        <th>مالیات</th>
                        <th>وام</th>
                        <th>سایر کسور</th>
                        <th>خالص</th>
                        <th>چاپ</th>
                        <th>حذف</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $row_num = 1;
                    mysqli_data_seek($all_slips, 0);
                    if (mysqli_num_rows($all_slips) > 0): 
                        while ($slip = mysqli_fetch_assoc($all_slips)): 
                            $base = $slip['total_gross'] - $slip['overtime_amount'] - $slip['bonus'] - $slip['housing_allowance'] - $slip['food_allowance'] - $slip['child_allowance'];
                    ?>
                    <tr id="slip-<?php echo $slip['id']; ?>">
                        <td><?php echo $row_num++; ?></td>
                        <td><?php echo $slip['first_name'] . ' ' . $slip['last_name']; ?></td>
                        <td><?php echo $months[$slip['month']-1] . ' ' . $slip['year']; ?></td>
                        <td><?php echo $slip['working_days']; ?></td>
                        <td class="amount"><?php echo fmt($base); ?></td>
                        <td class="amount"><?php echo fmt($slip['housing_allowance']); ?></td>
                        <td class="amount"><?php echo fmt($slip['food_allowance']); ?></td>
                        <td class="amount"><?php echo fmt($slip['child_allowance']); ?></td>
                        <td class="amount"><?php echo fmt($slip['overtime_amount']); ?></td>
                        <td class="amount"><?php echo fmt($slip['bonus']); ?></td>
                        <td class="amount" style="font-weight:700;"><?php echo fmt($slip['total_gross']); ?></td>
                        <td class="amount deduction"><?php echo fmt($slip['insurance_deduction']); ?></td>
                        <td class="amount deduction"><?php echo fmt($slip['tax_deduction']); ?></td>
                        <td class="amount deduction"><?php echo fmt($slip['deduction_loan']); ?></td>
                        <td class="amount deduction"><?php echo fmt($slip['deduction_other']); ?></td>
                        <td class="amount net" style="font-size:0.78rem;"><?php echo fmt($slip['net_salary']); ?></td>
                        <td>
                            <a href="../salary/print_slip.php?id=<?php echo $slip['id']; ?>" target="_blank" class="print-btn">چاپ</a>
                        </td>
                        <td>
                            <button class="delete-btn" onclick="deleteSlip(<?php echo $slip['id']; ?>)">حذف</button>
                        </td>
                    </tr>
                    <?php endwhile; 
                    else: ?>
                    <tr><td colspan="18" style="padding:30px;color:var(--text-secondary);">هیچ فیشی ثبت نشده است</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
</div>

<script>
function togglePermission(userId, btn) {
    fetch('salary_admin.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=toggle_permission&user_id=' + userId + '&permission=salary'
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            if (btn.classList.contains('toggle-on')) {
                btn.classList.remove('toggle-on');
                btn.classList.add('toggle-off');
                btn.textContent = 'محدود';
            } else {
                btn.classList.remove('toggle-off');
                btn.classList.add('toggle-on');
                btn.textContent = 'فعال';
            }
            showToast('دسترسی بروزرسانی شد', 'success');
        }
    });
}

function deleteSlip(slipId) {
    if (!confirm('آیا از حذف این فیش مطمئن هستید؟')) return;
    
    fetch('salary_admin.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=delete_slip&slip_id=' + slipId
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            document.getElementById('slip-' + slipId).remove();
            showToast('فیش حذف شد', 'success');
        }
    });
}

function showToast(msg, type) {
    const existing = document.querySelector('.toast');
    if (existing) existing.remove();
    const toast = document.createElement('div');
    toast.className = 'toast ' + type;
    toast.textContent = msg;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 2500);
}

function toggleTheme() {
    document.body.classList.toggle('light');
    const btn = document.getElementById('themeBtn');
    btn.textContent = document.body.classList.contains('light') ? 'حالت تاریک' : 'حالت روشن';
    document.cookie = 'theme=' + (document.body.classList.contains('light') ? 'light' : 'dark') + ';path=/;max-age=31536000';
}
(function() {
    const btn = document.getElementById('themeBtn');
    if (document.body.classList.contains('light')) btn.textContent = 'حالت تاریک';
})();
</script>
</body>
</html>