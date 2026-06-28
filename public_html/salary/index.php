<?php
define('SECURE_ACCESS', true);
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireLogin();
require_once '../includes/jdf.php';

$personnel_list = mysqli_query($conn, "SELECT * FROM salary_personnel WHERE status='active' ORDER BY last_name, first_name");

// حذف پرسنل
if (isset($_GET['delete'])) {
    $did = intval($_GET['delete']);
    mysqli_query($conn, "UPDATE salary_personnel SET status='inactive' WHERE id=$did");
    header('Location: index.php?msg=deleted');
    exit;
}

$msg = '';
if (isset($_GET['msg']) && $_GET['msg'] == 'deleted') $msg = 'پرسنل غیرفعال شد';
if (isset($_GET['msg']) && $_GET['msg'] == 'saved') $msg = 'فیش با موفقیت ثبت شد';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت فیش حقوقی</title>
    <link href="../assets/fonts/fonts.css" rel="stylesheet">
    <style>
        :root {
            --bg: #0a0f1a;
            --surface: rgba(255,255,255,0.03);
            --border: rgba(255,255,255,0.06);
            --text: #e8ecf1;
            --text2: #8899aa;
            --accent: #5b9cf5;
            --green: #10b981;
            --red: #ef4444;
            --radius: 16px;
        }
        body.light {
            --bg: #f5f6f8;
            --surface: #ffffff;
            --border: #e0e3e8;
            --text: #1a1f2e;
            --text2: #555f6e;
            --accent: #3b6fd4;
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Vazirmatn', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            padding: 16px;
            padding-bottom: 100px;
            transition: all 0.3s;
        }
        .container { max-width: 900px; margin: 0 auto; }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 16px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            margin-bottom: 14px;
            gap: 10px;
        }
        .header h1 { font-size: 0.95rem; font-weight: 700; display: flex; align-items: center; gap: 8px; }
        .header-actions { display: flex; gap: 6px; }
        .btn {
            padding: 7px 14px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: var(--surface);
            color: var(--text);
            cursor: pointer;
            font-family: 'Vazirmatn';
            font-size: 0.72rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }
        .btn:hover { border-color: var(--accent); color: var(--accent); }
        
        .toast {
            padding: 10px 14px;
            border-radius: 10px;
            margin-bottom: 12px;
            font-weight: 600;
            font-size: 0.78rem;
        }
        .toast-success { background: var(--green); color: #fff; }
        
        .person-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }
        .person-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 18px 12px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            color: var(--text);
            display: block;
        }
        .person-card:hover {
            transform: translateY(-3px);
            border-color: var(--accent);
        }
        .avatar {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(91,156,245,0.15), rgba(16,185,129,0.10));
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-weight: 700;
            font-size: 1.2rem;
            color: var(--accent);
            border: 2px solid var(--border);
        }
        .person-name {
            font-weight: 600;
            font-size: 0.82rem;
            margin-bottom: 4px;
        }
        .person-pos {
            font-size: 0.65rem;
            color: var(--text2);
        }
        .person-salary {
            font-size: 0.68rem;
            color: var(--accent);
            margin-top: 4px;
            font-weight: 600;
        }
        
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: var(--surface);
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: space-around;
            padding: 10px 12px 16px;
            backdrop-filter: blur(12px);
            z-index: 100;
        }
        .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            background: none;
            border: none;
            color: var(--text2);
            cursor: pointer;
            font-family: 'Vazirmatn';
            font-size: 0.65rem;
            padding: 8px 16px;
            border-radius: 12px;
            text-decoration: none;
            transition: all 0.2s;
        }
        .nav-item:hover {
            color: var(--accent);
            background: rgba(91,156,245,0.08);
        }
        .nav-item svg {
            width: 22px;
            height: 22px;
        }
        
        .empty-state {
            grid-column: 1 / -1;
            text-align: center;
            padding: 50px 20px;
            color: var(--text2);
        }
        .empty-state svg {
            width: 64px;
            height: 64px;
            margin-bottom: 14px;
            opacity: 0.4;
        }
    </style>
</head>
<body class="dark">

<div class="container">
    <div class="header">
        <h1>
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="7" width="18" height="13" rx="2"/>
                <path d="M8 7V5a2 2 0 012-2h4a2 2 0 012 2v2"/>
                <line x1="12" y1="12" x2="12" y2="16"/>
            </svg>
            فیش حقوقی
        </h1>
        <div class="header-actions">
            <a href="settings.php" class="btn">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="3"/>
                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                </svg>
                تنظیمات
            </a>
            <button class="btn" onclick="toggleTheme()" id="themeBtn">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="5"/>
                    <path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/>
                </svg>
                <span id="themeLabel">روشن</span>
            </button>
            <a href="../index.php" class="btn">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M15 18l-6-6 6-6"/>
                </svg>
                بازگشت
            </a>
        </div>
    </div>
    
    <?php if ($msg): ?>
    <div class="toast toast-success"><?php echo $msg; ?></div>
    <?php endif; ?>
    
    <div class="person-grid">
    <?php if (mysqli_num_rows($personnel_list) > 0): ?>
        <?php while ($p = mysqli_fetch_assoc($personnel_list)): 
            $initials = mb_substr($p['first_name'], 0, 1) . mb_substr($p['last_name'], 0, 1);
        ?>
        <a href="personnel.php?id=<?php echo $p['id']; ?>" class="person-card">
            <div class="avatar"><?php echo $initials; ?></div>
            <div class="person-name"><?php echo $p['first_name'] . ' ' . $p['last_name']; ?></div>
            <div class="person-pos"><?php echo $p['position'] ?: '---'; ?></div>
            <div class="person-salary"><?php echo number_format($p['base_salary']); ?> ریال</div>
        </a>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="empty-state">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                <circle cx="12" cy="8" r="4"/>
                <path d="M4 20c0-4 4-6 8-6s8 2 8 6"/>
            </svg>
            <p>هنوز پرسنلی ثبت نشده است</p>
        </div>
    <?php endif; ?>
</div>
</div>

<div class="bottom-nav">
    <button class="nav-item" onclick="location.href='slip.php?action=add_person'">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"/>
            <line x1="12" y1="8" x2="12" y2="16"/>
            <line x1="8" y1="12" x2="16" y2="12"/>
        </svg>
        پرسنل جدید
    </button>
    <button class="nav-item" onclick="location.href='slip.php'">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="3" width="18" height="18" rx="2"/>
            <line x1="8" y1="8" x2="16" y2="8"/>
            <line x1="8" y1="12" x2="16" y2="12"/>
            <line x1="8" y1="16" x2="12" y2="16"/>
        </svg>
        صدور فیش
    </button>
    <button class="nav-item" onclick="location.href='archive.php'">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="4" width="18" height="16" rx="2"/>
            <line x1="8" y1="2" x2="8" y2="6"/>
            <line x1="16" y1="2" x2="16" y2="6"/>
            <line x1="3" y1="10" x2="21" y2="10"/>
        </svg>
        بایگانی
    </button>
</div>

<script>
function toggleTheme() {
    document.body.classList.toggle('light');
    var isLight = document.body.classList.contains('light');
    document.getElementById('themeLabel').textContent = isLight ? 'تاریک' : 'روشن';
    localStorage.setItem('theme', isLight ? 'light' : 'dark');
}
if (localStorage.getItem('theme') === 'light') {
    document.body.classList.add('light');
    document.getElementById('themeLabel').textContent = 'تاریک';
}
</script>
</body>
</html>