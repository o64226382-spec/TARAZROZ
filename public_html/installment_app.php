<?php
define('SECURE_ACCESS', true);
require_once 'includes/config.php';
require_once 'includes/auth.php';
requireLogin();

$user_id = intval($_SESSION['user_id']);
$perm_query = "SELECT permissions FROM users WHERE id = $user_id";
$perm_result = mysqli_query($conn, $perm_query);
$permissions = '';
if ($perm_result && mysqli_num_rows($perm_result) > 0) {
    $permissions = mysqli_fetch_assoc($perm_result)['permissions'];
}
if (strpos($permissions, 'pre_invoice') === false && $_SESSION['role'] !== 'admin') {
    die('<div style="text-align:center;padding:60px;font-family:Vazirmatn;background:#0a0f1a;color:#e8ecf1;">⛔ شما دسترسی به این بخش را ندارید.<br><a href="index.php" style="color:#4b8cf7;">بازگشت</a></div>');
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>برگه اقساط | تراز روزانه</title>
    <link href="assets/fonts/fonts.css" rel="stylesheet">
    <link href="assets/css/light-theme.css" rel="stylesheet">
    <link rel="icon" href="assets/images/logo.png">
    <style>
        :root {
            --bg: #0a0f1a; --surface: rgba(255,255,255,0.03);
            --border: rgba(255,255,255,0.06); --text: #e8ecf1;
            --text-secondary: #8899aa; --accent: #4b8cf7;
            --gold: #d4af37; --gold-light: #ffd700;
            --red: #ef4444; --green: #22c55e;
            --radius: 14px; --radius-sm: 8px;
        }
        body.light {
            --bg: #f5f6f8; --surface: #ffffff; --border: #e0e3e8;
            --text: #1a1f2e; --text-secondary: #555f6e;
            --accent: #3b6fd4; --gold: #b8960f; --gold-light: #8b6914;
            --green: #16a34a;
            background-image: none;
        }
        body.light .card { box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
        body.light .header { box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
        body.light input, body.light textarea { background: #f6f8fa; border-color: #d0d7de; color: #1a1f2e; }
        body.light table th { background: #f6f8fa; color: #555f6e; }
        body.light table td { border-color: #e0e3e8; color: #1a1f2e; }
        body.light .total-display { background: #fef9e7; border-color: #f0d060; color: #8b6914; }
        body.light .max-display { background: #ecfdf5; border-color: #6ee7b7; color: #065f46; }
        body.light .rate-badge { background: #eff6ff; border-color: #93c5fd; color: #1e40af; }
        
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Vazirmatn', sans-serif;
            background: var(--bg); color: var(--text);
            min-height: 100vh; padding: 14px;
        }
        .container { max-width: 850px; margin: 0 auto; display: flex; flex-direction: column; gap: 12px; }
        
        .header {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: var(--radius); padding: 12px 18px;
            display: flex; align-items: center; justify-content: space-between;
            backdrop-filter: blur(12px); flex-wrap: wrap; gap: 8px;
        }
        .header h2 { font-size: 1rem; font-weight: 700; }
        .header-actions { display: flex; gap: 8px; align-items: center; }
        .back-btn { color: var(--text-secondary); text-decoration: none; font-size: 0.75rem; }
        .theme-btn {
            background: var(--surface); border: 1px solid var(--border);
            color: var(--text); padding: 6px 12px; border-radius: 8px;
            cursor: pointer; font-family: 'Vazirmatn'; font-size: 0.75rem;
        }
        
        .card {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: var(--radius); padding: 18px; backdrop-filter: blur(10px);
        }
        .card-title { font-weight: 700; margin-bottom: 12px; font-size: 0.85rem; color: var(--gold-light); }
        
        .form-row { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; }
        @media (max-width: 600px) { .form-row { grid-template-columns: 1fr; } }
        
        label { display: block; margin-top: 10px; font-weight: 600; color: var(--text-secondary); font-size: 0.72rem; }
        input, textarea {
            width: 100%; padding: 9px 10px; margin-top: 3px;
            background: rgba(255,255,255,0.03); border: 1px solid var(--border);
            border-radius: var(--radius-sm); color: var(--text);
            font-family: 'Vazirmatn', sans-serif; font-size: 0.8rem;
        }
        input:focus, textarea:focus { border-color: var(--accent); outline: none; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 0.73rem; }
        th, td { border: 1px solid rgba(255,255,255,0.05); padding: 7px 5px; text-align: center; }
        th { background: rgba(255,255,255,0.03); font-size: 0.68rem; color: var(--text-secondary); }
        
        .rate-badge {
            display: inline-block; padding: 4px 10px; border-radius: 20px;
            font-size: 0.7rem; font-weight: 700; margin: 4px 2px;
            background: rgba(75,140,247,0.1); border: 1px solid rgba(75,140,247,0.2);
            color: var(--accent);
        }
        
        /* استایل ورودی درصد */
        .rate-input {
            width: 60px;
            display: inline-block;
            text-align: center;
            padding: 4px 6px;
            font-family: 'Vazirmatn', sans-serif;
            background: rgba(75,140,247,0.05);
            border: 1px solid var(--border);
            border-radius: 6px;
            color: var(--accent);
            font-weight: 700;
        }
        
        .calc-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 12px; }
        @media (max-width: 500px) { .calc-grid { grid-template-columns: 1fr; } }
        
        .total-display {
            padding: 10px; border-radius: var(--radius-sm);
            font-weight: 700; font-size: 0.8rem;
            background: rgba(212,175,55,0.05);
            border: 1px solid rgba(212,175,55,0.15);
            color: var(--gold-light);
        }
        .max-display {
            padding: 12px; border-radius: var(--radius-sm);
            font-weight: 700; font-size: 0.85rem;
            background: rgba(34,197,94,0.05);
            border: 1px solid rgba(34,197,94,0.2);
            color: var(--green);
            text-align: center;
        }
        
        .divider {
            border-top: 1px solid var(--border);
            margin: 14px 0;
        }
        
        .btn-print {
            margin-top: 12px; padding: 12px; width: 100%;
            background: linear-gradient(135deg, #d4af37, #b8960f);
            color: #1a1a1a; border: none; border-radius: var(--radius-sm);
            cursor: pointer; font-family: 'Vazirmatn', sans-serif;
            font-weight: 700; font-size: 0.9rem;
        }
        .print-hint { text-align: center; color: var(--text-secondary); font-size: 0.7rem; margin-top: 6px; }
        
        .info-label { font-size: 0.65rem; color: var(--text-secondary); }
    </style>
</head>
<body>
<div class="container">
    
    <div class="header">
        <h2>📋 برگه اقساط</h2>
        <div class="header-actions">
            <button class="theme-btn" onclick="toggleTheme()" id="themeBtn">🌙 تیره</button>
            <a href="index.php" class="back-btn">← بازگشت</a>
        </div>
    </div>
    
    <!-- بخش ۱: اطلاعات بدهکار -->
    <div class="card">
        <div class="card-title">👤 اطلاعات بدهکار</div>
        <div class="form-row">
            <div><label>نام و نام خانوادگی</label><input type="text" id="debtorName" placeholder="محسن محسنی"></div>
            <div><label>جلد</label><input type="text" id="volumeNum" placeholder="۲۲"></div>
            <div><label>شماره فاکتور</label><input type="text" id="invoiceNum" placeholder="۲۴۹"></div>
        </div>
    </div>
    
    <!-- بخش ۲: حداکثر مبلغ فاکتور - با فیلد ورودی درصد سود -->
    <div class="card">
        <div class="card-title">💰 محاسبه حداکثر مبلغ فاکتور</div>
        <p class="info-label">
            بر اساس توان پرداخت ماهانه و نرخ سود: 
            <input type="text" id="interestRate" class="rate-input" value="۷" 
                   oninput="updateRate(); calcMaxInvoice(); calcInstallments();"> 
            %
        </p>
        <div class="form-row" style="margin-top:8px;">
            <div><label>توان پرداخت ماهانه (ریال)</label><input type="text" id="maxMonthlyPayment" placeholder="۵۴,۰۰۰,۰۰۰" oninput="formatNum(this); calcMaxInvoice();"></div>
            <div><label>تعداد اقساط</label><input type="number" id="maxInstallmentCount" value="۱۲" oninput="calcMaxInvoice();"></div>
        </div>
        <div class="max-display" style="margin-top:12px;">
            📌 حداکثر مبلغ قابل فاکتور: <span id="maxInvoiceAmount">۰</span> ریال
        </div>
        <p class="info-label" style="margin-top:4px;">فرمول: (توان ماهانه × تعداد اقساط) ÷ (۱ + <span id="rateInFormula">۷</span>٪ × تعداد اقساط)</p>
    </div>
    
    <!-- بخش ۳: محاسبه اقساط - نمایش درصد پویا -->
    <div class="card">
        <div class="card-title">📊 محاسبه اقساط</div>
        <p class="info-label">
            نرخ سود: <span id="rateDisplayBadge" class="rate-badge">۷٪</span> | 
            پس از فاکتور کردن کالا، مبلغ اصلی فاکتور را وارد کنید
        </p>
        <div class="form-row" style="margin-top:8px;">
            <div><label>مبلغ اصلی فاکتور (ریال)</label><input type="text" id="orgInvoiceAmount" placeholder="مبلغ نهایی فاکتور" oninput="formatNum(this); calcInstallments();"></div>
            <div><label>تعداد اقساط</label><input type="number" id="instCount" value="۱۲" oninput="calcInstallments();"></div>
            <div><label>تاریخ اولین قسط</label><input type="text" id="firstDate" placeholder="۱۴۰۵/۰۲/۳۰" value="۱۴۰۵/۰۲/۳۰"></div>
        </div>
        
        <div class="calc-grid" style="margin-top:12px;">
            <div class="total-display">💰 سود کل: <span id="totalInterest">۰</span> ریال</div>
            <div class="total-display">💳 کل بدهی: <span id="totalDebt">۰</span> ریال</div>
            <div class="total-display">📌 اصل هر قسط: <span id="principalPer">۰</span> ریال</div>
            <div class="total-display">📌 سود هر قسط: <span id="interestPer">۰</span> ریال</div>
            <div class="total-display" style="grid-column: 1 / -1;">✅ مبلغ هر قسط: <span id="installmentPer">۰</span> ریال</div>
        </div>
    </div>
    
    <button class="btn-print" onclick="openPrintTab()">🖨️ پیش‌نمایش و پرینت</button>
    <p class="print-hint">💡 در صفحه پیش‌نمایش می‌توانید اندازه کاغذ، حاشیه و بزرگنمایی را تنظیم کنید</p>
    
</div>

<script>
// ========== تم ==========
function toggleTheme() {
    document.body.classList.toggle('light');
    let btn = document.getElementById('themeBtn');
    if (document.body.classList.contains('light')) { 
        btn.textContent = '☀️ روشن'; 
        localStorage.setItem('theme', 'light'); 
    } else { 
        btn.textContent = '🌙 تیره'; 
        localStorage.setItem('theme', 'dark'); 
    }
}
(function() { 
    if (localStorage.getItem('theme') === 'light') { 
        document.body.classList.add('light'); 
        document.getElementById('themeBtn').textContent = '☀️ روشن'; 
    } 
})();

// ========== ابزارهای عددی ==========
function toEnglishNum(str) {
    return str.replace(/[۰-۹]/g, function(d) { return '۰۱۲۳۴۵۶۷۸۹'.indexOf(d); });
}

function toPersianNum(num) {
    return String(num).replace(/\d/g, function(d) { return '۰۱۲۳۴۵۶۷۸۹'[d]; });
}

function fmtNum(n) { 
    return Number(n||0).toLocaleString('fa-IR'); 
}

function parseNum(s) { 
    let cleaned = toEnglishNum(String(s||''));
    return parseFloat(cleaned.replace(/,/g, '')) || 0; 
}

function formatNum(el) {
    let raw = toEnglishNum(el.value).replace(/,/g, '').replace(/[^0-9]/g, '');
    if (raw) {
        el.value = Number(raw).toLocaleString('en-US');
    }
}

// ========== مدیریت نرخ سود متغیر ==========
function getInterestRatePercent() {
    // مقدار وارد شده (می‌تواند فارسی یا انگلیسی باشد)
    let val = parseNum(document.getElementById('interestRate').value);
    // اگر مقدار نامعتبر بود ۷٪ پیش‌فرض
    return (val > 0) ? val : 7;
}

function getInterestRateDecimal() {
    return getInterestRatePercent() / 100;
}

function updateRate() {
    let percent = getInterestRatePercent();
    // به‌روزرسانی نمایش‌ها
    document.getElementById('rateDisplayBadge').innerText = toPersianNum(percent) + '٪';
    document.getElementById('rateInFormula').innerText = toPersianNum(percent);
}

// ========== تاریخ شمسی ==========
function parseShamsiDate(dateStr) {
    if (!dateStr) return null;
    let cleaned = toEnglishNum(dateStr).trim();
    let parts = cleaned.split(/[\/\-\.\s]+/);
    if (parts.length !== 3) return null;
    
    let y = parseInt(parts[0], 10);
    let m = parseInt(parts[1], 10);
    let d = parseInt(parts[2], 10);
    
    if (isNaN(y) || isNaN(m) || isNaN(d)) return null;
    if (m < 1 || m > 12) return null;
    if (d < 1 || d > 31) return null;
    
    return { year: y, month: m, day: d };
}

function addOneMonth(dateObj) {
    if (!dateObj) return null;
    let y = dateObj.year;
    let m = dateObj.month + 1;
    let d = dateObj.day;
    
    if (m > 12) {
        m = 1;
        y++;
    }
    
    let maxDay = 31;
    if (m > 6 && m < 12) maxDay = 30;
    if (m === 12) maxDay = 29;
    
    if (d > maxDay) d = maxDay;
    
    return { year: y, month: m, day: d };
}

function formatShamsiDate(dateObj) {
    if (!dateObj) return '---';
    return dateObj.year + '/' + 
           String(dateObj.month).padStart(2, '0') + '/' + 
           String(dateObj.day).padStart(2, '0');
}

// ========== محاسبه حداکثر مبلغ فاکتور (با نرخ متغیر) ==========
function calcMaxInvoice() {
    let rate = getInterestRateDecimal();
    let monthly = parseNum(document.getElementById('maxMonthlyPayment').value);
    let count = parseInt(document.getElementById('maxInstallmentCount').value) || 0;
    let maxEl = document.getElementById('maxInvoiceAmount');
    
    if (monthly <= 0 || count <= 0) {
        maxEl.innerText = '۰';
        return;
    }
    let maxInvoice = (monthly * count) / (1 + rate * count);
    maxEl.innerText = fmtNum(Math.round(maxInvoice));
}

// ========== محاسبه اقساط (با نرخ متغیر) ==========
function calcInstallments() {
    let rate = getInterestRateDecimal();
    let orgAmount = parseNum(document.getElementById('orgInvoiceAmount').value);
    let count = parseInt(document.getElementById('instCount').value) || 0;
    
    if (orgAmount <= 0 || count <= 0) {
        document.getElementById('totalInterest').innerText = '۰';
        document.getElementById('totalDebt').innerText = '۰';
        document.getElementById('principalPer').innerText = '۰';
        document.getElementById('interestPer').innerText = '۰';
        document.getElementById('installmentPer').innerText = '۰';
        return;
    }
    let totalInterest = orgAmount * rate * count;
    let totalDebt = orgAmount + totalInterest;
    let principalPer = orgAmount / count;
    let interestPer = totalInterest / count;
    let installmentPer = principalPer + interestPer;
    
    document.getElementById('totalInterest').innerText = fmtNum(Math.round(totalInterest));
    document.getElementById('totalDebt').innerText = fmtNum(Math.round(totalDebt));
    document.getElementById('principalPer').innerText = fmtNum(Math.round(principalPer));
    document.getElementById('interestPer').innerText = fmtNum(Math.round(interestPer));
    document.getElementById('installmentPer').innerText = fmtNum(Math.round(installmentPer));
}

// ========== عدد به حروف ==========
function numberToWords(num) {
    if (isNaN(num) || num === null) return '---';
    num = Math.round(num);
    if (num === 0) return 'صفر';
    
    const ones = ['', 'یک', 'دو', 'سه', 'چهار', 'پنج', 'شش', 'هفت', 'هشت', 'نه'];
    const tens = ['', 'ده', 'بیست', 'سی', 'چهل', 'پنجاه', 'شصت', 'هفتاد', 'هشتاد', 'نود'];
    const teens = ['ده', 'یازده', 'دوازده', 'سیزده', 'چهارده', 'پانزده', 'شانزده', 'هفده', 'هجده', 'نوزده'];
    const hundreds = ['', 'صد', 'دویست', 'سیصد', 'چهارصد', 'پانصد', 'ششصد', 'هفتصد', 'هشتصد', 'نهصد'];
    const scales = ['', 'هزار', 'میلیون', 'میلیارد'];
    
    function convert(n) {
        if (n < 0) return '';
        if (n < 10) return ones[n];
        if (n < 20) return teens[n - 10];
        if (n < 100) return tens[Math.floor(n / 10)] + (n % 10 ? ' و ' + ones[n % 10] : '');
        if (n < 1000) return hundreds[Math.floor(n / 100)] + (n % 100 ? ' و ' + convert(n % 100) : '');
        for (let i = 1; i < scales.length; i++) {
            let div = Math.pow(1000, i);
            if (n < Math.pow(1000, i + 1)) {
                return convert(Math.floor(n / div)) + ' ' + scales[i] + (n % div ? ' و ' + convert(n % div) : '');
            }
        }
        return '';
    }
    
    return convert(num) + ' ریال';
}

// ========== پیش‌نمایش پرینت ==========
function openPrintPreview(htmlContent) {
    localStorage.setItem('printContent', htmlContent);
    window.open('print/', '_blank');
}

function openPrintTab() {
    let name = document.getElementById('debtorName').value || '—';
    let vol = document.getElementById('volumeNum').value || '—';
    let inv = document.getElementById('invoiceNum').value || '—';
    let firstDateStr = document.getElementById('firstDate').value || '';
    
    let ratePercent = getInterestRatePercent();
    let rateDecimal = getInterestRateDecimal();
    
    let orgAmount = parseNum(document.getElementById('orgInvoiceAmount').value);
    let count = parseInt(document.getElementById('instCount').value) || 0;
    
    if (orgAmount <= 0 || count <= 0) {
        alert('لطفاً مبلغ اصلی فاکتور و تعداد اقساط را در بخش محاسبه اقساط وارد کنید.');
        return;
    }
    
    let totalInterest = orgAmount * rateDecimal * count;
    let totalDebt = orgAmount + totalInterest;
    let principalPer = orgAmount / count;
    let interestPer = totalInterest / count;
    let installmentPer = principalPer + interestPer;
    
    // ساخت ردیف‌های اقساط
    let rowsHtml = '';
    let currentDate = parseShamsiDate(firstDateStr);
    
    if (!currentDate) {
        for (let i = 1; i <= count; i++) {
            rowsHtml += `<tr>
                <td>${toPersianNum(i)}</td>
                <td>---</td>
                <td>${fmtNum(Math.round(principalPer))}</td>
                <td>${fmtNum(Math.round(interestPer))}</td>
                <td>${fmtNum(Math.round(installmentPer))}</td>
            </tr>`;
        }
    } else {
        for (let i = 1; i <= count; i++) {
            rowsHtml += `<tr>
                <td>${toPersianNum(i)}</td>
                <td>${formatShamsiDate(currentDate)}</td>
                <td>${fmtNum(Math.round(principalPer))}</td>
                <td>${fmtNum(Math.round(interestPer))}</td>
                <td>${fmtNum(Math.round(installmentPer))}</td>
            </tr>`;
            currentDate = addOneMonth(currentDate);
        }
    }
    
    let cleanName = name.replace(/\s+/g, '_').replace(/[^\u0600-\u06FF\w]/g, '') || 'بدهکار';
    let fileName = 'برگه_اقساط_-_' + cleanName;
    let words = numberToWords(Math.round(totalDebt));
    
    let html = `<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>${fileName}</title>
    <link href="${window.location.origin}/~tarazroz/assets/fonts/fonts.css" rel="stylesheet">
    <style>
        @page { size: A4; margin: 10mm; }
        body { font-family: 'Vazirmatn', sans-serif; color: #000; direction: rtl; background: white; font-size: 9pt; padding: 5mm; max-width: 190mm; margin: 0 auto; }
        h2 { text-align: center; font-size: 13pt; margin-bottom: 1mm; }
        .header-row { display: flex; justify-content: space-between; font-size: 8pt; margin-bottom: 2mm; }
        .info-box { border: 1px solid #000; padding: 1.5mm; margin-bottom: 2mm; }
        .info-box table { width: 100%; border-collapse: collapse; font-size: 8pt; }
        .info-box th, .info-box td { border: 1px solid #000; padding: 1.5mm; text-align: center; }
        .info-box th { background: #eee; font-size: 7pt; }
        .rate-info { margin: 2mm 0; padding: 1.5mm; border: 1px solid #000; font-size: 7pt; text-align: center; background: #f9f9f9; }
        .items-table { width: 100%; border-collapse: collapse; margin: 2mm 0; font-size: 8pt; }
        .items-table th, .items-table td { border: 1px solid #000; padding: 2mm; text-align: center; }
        .items-table th { background: #eee; }
        .calc-box { margin: 2mm 0; padding: 1.5mm; border: 1px solid #000; font-size: 8pt; }
        .amount-words { margin: 2mm 0; padding: 1.5mm; border: 1px solid #000; font-size: 8pt; }
        .sign-row { display: flex; justify-content: space-around; margin-top: 6mm; font-size: 8pt; }
        .sign-box { text-align: center; flex: 1; }
        .footer-note { text-align: center; margin-top: 2mm; font-size: 6pt; color: #999; }
    </style>
</head>
<body>
    <h2>برگه اقساط</h2>
    
    <div class="header-row">
        <span><strong>شماره فاکتور:</strong> ${inv}</span>
        <span><strong>جلد:</strong> ${vol}</span>
        <span><strong>تاریخ اولین قسط:</strong> ${firstDateStr}</span>
    </div>
    
    <div class="info-box">
        <table>
            <thead><tr><th>نام و نام خانوادگی</th><th>مبلغ اصلی فاکتور (ریال)</th><th>تعداد اقساط</th></tr></thead>
            <tbody><tr><td>${name}</td><td>${fmtNum(Math.round(orgAmount))}</td><td>${toPersianNum(count)}</td></tr></tbody>
        </table>
    </div>
    
    <div class="rate-info">
        <strong>نرخ سود:</strong> ${toPersianNum(ratePercent)}٪ سالانه | <strong>سود کل:</strong> ${fmtNum(Math.round(totalInterest))} ریال | <strong>کل بدهی:</strong> ${fmtNum(Math.round(totalDebt))} ریال
    </div>
    
    <table class="items-table">
        <thead><tr><th>ردیف</th><th>تاریخ قسط</th><th>اصل قسط (ریال)</th><th>سود قسط (ریال)</th><th>جمع قسط (ریال)</th></tr></thead>
        <tbody>${rowsHtml}</tbody>
    </table>
    
    <div class="amount-words"><strong>مبلغ کل بدهی به حروف:</strong> ${words}</div>
    
    <div class="sign-row">
        <div class="sign-box">امضا بدهکار</div>
        <div class="sign-box">مهر و امضا<br>فروشگاه زرگری ثنا</div>
    </div>
    
    <div class="footer-note">این برگه توسط سامانه تراز روزانه صادر شده است.</div>
</body>
</html>`;
    
    openPrintPreview(html);
}

// ========== بارگذاری اولیه ==========
window.onload = function() {
    updateRate();         // نمایش درصد اولیه (۷٪)
    calcMaxInvoice(); 
    calcInstallments(); 
};
</script>
</body>
</html>