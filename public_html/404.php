<?php
define('SECURE_ACCESS', true);
require_once 'includes/config.php';
require_once 'includes/jdf.php';

$jalali_today = jdate('l j F Y');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="theme-color" content="#0b101e">
<title>۴۰۴ - صفحه پیدا نشد | پلتفرم تراز روزانه</title>
<link href="assets/fonts/fonts.css" rel="stylesheet">
<link rel="icon" type="image/png" href="assets/images/logo.png">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }

/* ====== تم تاریک (پیش‌فرض) ====== */
:root {
    --bg: linear-gradient(135deg, #0b101e 0%, #1a2235 100%);
    --card-bg: rgba(25, 33, 50, 0.6);
    --card-border: rgba(255, 255, 255, 0.08);
    --card-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
    --text: #f0f2f5;
    --text-secondary: #94a3b8;
    --gold: #d4af37;
    --gold-light: #fcd34d;
    --code-gradient: linear-gradient(135deg, #d4af37, #fcd34d);
    --title-color: #fcd34d;
    --btn-bg: linear-gradient(135deg, #d4af37, #fcd34d);
    --btn-color: #1a1a1a;
    --date-bg: rgba(255,255,255,0.03);
    --date-border: rgba(255,255,255,0.06);
    --divider: linear-gradient(90deg, #d4af37, #fcd34d);
    --orb1: rgba(212, 175, 55, 0.05);
    --orb2: rgba(96, 165, 250, 0.05);
}

/* ====== تم روشن ====== */
body.light {
    --bg: linear-gradient(135deg, #f0f4f8 0%, #e2e8f0 50%, #cbd5e1 100%);
    --card-bg: rgba(255, 255, 255, 0.85);
    --card-border: rgba(0, 0, 0, 0.06);
    --card-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
    --text: #1e293b;
    --text-secondary: #64748b;
    --gold: #b45309;
    --gold-light: #d97706;
    --code-gradient: linear-gradient(135deg, #b45309, #d97706);
    --title-color: #b45309;
    --btn-bg: linear-gradient(135deg, #d4af37, #f59e0b);
    --btn-color: #fff;
    --date-bg: rgba(0,0,0,0.03);
    --date-border: rgba(0,0,0,0.06);
    --divider: linear-gradient(90deg, #d4af37, #f59e0b);
    --orb1: rgba(212, 175, 55, 0.06);
    --orb2: rgba(59, 130, 246, 0.04);
}

body {
    font-family: 'Vazirmatn', sans-serif;
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 20px;
    transition: background 0.3s ease;
}

body::before {
    content: '';
    position: fixed;
    top: -20%;
    right: -10%;
    width: 50vw;
    height: 50vw;
    background: var(--orb1);
    border-radius: 50%;
    z-index: 0;
}

body::after {
    content: '';
    position: fixed;
    bottom: -15%;
    left: -5%;
    width: 40vw;
    height: 40vw;
    background: var(--orb2);
    border-radius: 50%;
    z-index: 0;
}

.error-card {
    background: var(--card-bg);
    backdrop-filter: blur(20px);
    border: 1px solid var(--card-border);
    border-radius: 24px;
    padding: 40px 24px;
    max-width: 500px;
    box-shadow: var(--card-shadow);
    position: relative;
    z-index: 1;
}

.error-logo {
    width: 80px;
    height: auto;
    border-radius: 20px;
    margin-bottom: 20px;
    box-shadow: 0 4px 20px rgba(212, 175, 55, 0.3);
}

.error-code {
    font-size: clamp(3rem, 15vw, 6rem);
    font-weight: 900;
    background: var(--code-gradient);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
    line-height: 1;
    margin-bottom: 8px;
}

.error-title {
    font-size: clamp(1.2rem, 5vw, 1.5rem);
    font-weight: 800;
    color: var(--title-color);
    margin-bottom: 12px;
}

.error-desc {
    color: var(--text-secondary);
    font-size: clamp(0.8rem, 3vw, 0.95rem);
    line-height: 1.8;
    margin-bottom: 24px;
}

.error-date {
    color: var(--text-secondary);
    font-size: 0.75rem;
    margin-bottom: 20px;
    padding: 8px 16px;
    background: var(--date-bg);
    border-radius: 30px;
    display: inline-block;
    border: 1px solid var(--date-border);
}

.btn-home {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 28px;
    background: var(--btn-bg);
    color: var(--btn-color);
    border-radius: 14px;
    text-decoration: none;
    font-weight: 800;
    font-size: 0.9rem;
    transition: all 0.3s;
    box-shadow: 0 4px 15px rgba(212, 175, 55, 0.3);
}

.btn-home:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 25px rgba(212, 175, 55, 0.5);
}

.divider {
    width: 60px;
    height: 3px;
    background: var(--divider);
    margin: 16px auto;
    border-radius: 3px;
}
</style>
</head>
<body>
<div class="error-card">
    <img src="assets/images/logo.png" alt="پلتفرم تراز روزانه" class="error-logo">
    <div class="error-code">۴۰۴</div>
    <div class="error-title">صفحه مورد نظر پیدا نشد</div>
    <div class="divider"></div>
    <p class="error-desc">
        متأسفانه صفحه‌ای که به دنبال آن هستید وجود ندارد،<br>
        حذف شده یا آدرس آن تغییر کرده است.
    </p>
    <p class="error-date">امروز: <?php echo htmlspecialchars($jalali_today); ?></p>
    <br>
    <a href="#" onclick="event.preventDefault(); if(window.history.length>1){window.history.back();}else{window.location.href='index.php';}">🔙 برگشت</a>
</div>

<script>
// تشخیص تم از localStorage و اعمال خودکار
(function() {
    var savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'light') {
        document.body.classList.add('light');
    }
})();
</script>
</body>
</html>