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
    <title>پیش‌فاکتور | تراز روزانه</title>
    <link href="assets/fonts/fonts.css" rel="stylesheet">
    <link href="assets/css/light-theme.css" rel="stylesheet">
    <link rel="icon" href="assets/images/logo.png">
    <style>
        :root {
            --bg: #0a0f1a; --surface: rgba(255,255,255,0.03);
            --border: rgba(255,255,255,0.06); --text: #e8ecf1;
            --text-secondary: #8899aa; --accent: #4b8cf7;
            --gold: #d4af37; --gold-light: #ffd700;
            --red: #ef4444;
            --radius: 14px; --radius-sm: 8px;
        }
        body.light {
            --bg: #f5f6f8; --surface: #ffffff; --border: #e0e3e8;
            --text: #1a1f2e; --text-secondary: #555f6e;
            --accent: #3b6fd4; --gold: #b8960f; --gold-light: #8b6914;
            background-image: none;
        }
        body.light .card { box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
        body.light .header { box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
        body.light input, body.light textarea { background: #f6f8fa; border-color: #d0d7de; color: #1a1f2e; }
        body.light table th { background: #f6f8fa; color: #555f6e; }
        body.light table td { border-color: #e0e3e8; color: #1a1f2e; }
        body.light .btn-add-row { border-color: #c0c7ce; color: #555f6e; }
        body.light .total-display { background: #fef9e7; border-color: #f0d060; color: #8b6914; }
        
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Vazirmatn', sans-serif;
            background: var(--bg); color: var(--text);
            min-height: 100vh; padding: 14px;
        }
        .container { max-width: 800px; margin: 0 auto; display: flex; flex-direction: column; gap: 12px; }
        
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
        td input { width: 100%; padding: 5px; text-align: center; margin: 0; }
        td input.desc { text-align: right; }
        
        .btn-add-row {
            display: block; width: 100%; padding: 7px; margin-top: 6px;
            border: 1px dashed var(--border); border-radius: var(--radius-sm);
            cursor: pointer; font-size: 0.7rem; color: var(--text-secondary);
            background: transparent; font-family: 'Vazirmatn', sans-serif;
        }
        .btn-rm-row { background: none; border: none; color: var(--red); cursor: pointer; font-size: 0.9rem; }
        
        .installment-row {
            display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px;
            margin-top: 14px; padding-top: 14px; border-top: 1px solid var(--border);
        }
        .total-display {
            margin-top: 8px; padding: 10px;
            background: rgba(212,175,55,0.05);
            border: 1px solid rgba(212,175,55,0.15);
            border-radius: var(--radius-sm);
            font-weight: 700; font-size: 0.85rem; color: var(--gold-light);
        }
        
        .btn-print {
            margin-top: 12px; padding: 12px; width: 100%;
            background: linear-gradient(135deg, #d4af37, #b8960f);
            color: #1a1a1a; border: none; border-radius: var(--radius-sm);
            cursor: pointer; font-family: 'Vazirmatn', sans-serif;
            font-weight: 700; font-size: 0.9rem;
        }
        .print-hint { text-align: center; color: var(--text-secondary); font-size: 0.7rem; margin-top: 6px; }
    </style>
</head>
<body>
<div class="container">
    
    <div class="header">
        <h2>🧾 پیش‌فاکتور رسمی</h2>
        <div class="header-actions">
            <button class="theme-btn" onclick="toggleTheme()" id="themeBtn">🌙 تیره</button>
            <a href="index.php" class="back-btn">← بازگشت</a>
        </div>
    </div>
    
    <div class="card">
        <div class="card-title">📋 اطلاعات فاکتور</div>
        <div class="form-row">
            <div><label>تاریخ</label><input type="text" id="invDate" value="<?php echo date('Y/m/d'); ?>"></div>
            <div><label>شماره فاکتور</label><input type="text" id="invNumber" placeholder="۲۴۸۷"></div>
            <div><label>جلد</label><input type="text" id="invVolume" placeholder="۵۲"></div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-title">👤 اطلاعات خریدار</div>
        <div class="form-row">
            <div><label>آقای/خانم</label><input type="text" id="buyerName" placeholder="رقیه مساجدی"></div>
            <div><label>کد ملی</label><input type="text" id="buyerNational" placeholder="۹۴۰۶۱۰۴۹۳"></div>
            <div><label>شماره پرسنلی</label><input type="text" id="buyerPersonnel" placeholder="۵۱۵۰۵۳۹۶"></div>
        </div>
        <div class="form-row">
            <div><label>شاغل در</label><input type="text" id="buyerJob" placeholder="بازنشسته"></div>
            <div><label>تلفن</label><input type="text" id="buyerPhone" placeholder="۹۱۴۱۰۶۱۹۵۱"></div>
        </div>
        <div><label>آدرس</label><textarea id="buyerAddress" rows="1" placeholder="ایل گلی، رجایی شهر، ژاستور شرقی، پ ۲۶"></textarea></div>
    </div>
    
    <div class="card">
        <div class="card-title">📦 شرح کالاها</div>
        <div style="overflow-x:auto;">
            <table>
                <thead><tr><th>ردیف</th><th>شرح کالا</th><th></th></tr></thead>
                <tbody id="itemsBody"></tbody>
            </table>
        </div>
        <button class="btn-add-row" onclick="addRow()">+ افزودن کالا</button>
        
        <div class="installment-row">
            <div><label>مبلغ قسط ماهانه (ریال)</label><input type="text" id="instAmount" placeholder="۳,۰۰۰,۰۰۰" oninput="formatNum(this); updateTotal();"></div>
            <div><label>تعداد اقساط</label><input type="number" id="instCount" value="1" oninput="updateTotal();"></div>
            <div class="total-display">کل مانده بدهی: <span id="grandTotal">۰</span> ریال</div>
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
    if (document.body.classList.contains('light')) { btn.textContent = '☀️ روشن'; localStorage.setItem('theme', 'light'); }
    else { btn.textContent = '🌙 تیره'; localStorage.setItem('theme', 'dark'); }
}
(function() { if (localStorage.getItem('theme') === 'light') { document.body.classList.add('light'); document.getElementById('themeBtn').textContent = '☀️ روشن'; } })();

// ========== توابع ==========
function fmtNum(n) { return Number(n||0).toLocaleString('fa-IR'); }
function parseNum(s) { return parseFloat(String(s||'').replace(/,/g, '')) || 0; }

function addRow() {
    let tbody = document.getElementById('itemsBody');
    let idx = tbody.children.length + 1;
    let row = document.createElement('tr');
    row.innerHTML = `
        <td>${idx}</td>
        <td><input type="text" class="desc" placeholder="شرح کالا" style="text-align:right;"></td>
        <td><button class="btn-rm-row" onclick="this.closest('tr').remove(); updateRowNumbers();">✕</button></td>
    `;
    tbody.appendChild(row);
}

function updateRowNumbers() {
    document.querySelectorAll('#itemsBody tr').forEach((row, i) => {
        row.querySelector('td:first-child').textContent = i + 1;
    });
}

function updateTotal() {
    let inst = parseNum(document.getElementById('instAmount').value);
    let cnt = parseInt(document.getElementById('instCount').value) || 0;
    let total = inst * cnt;
    document.getElementById('grandTotal').textContent = fmtNum(Math.round(total));
}

function formatNum(el) {
    let raw = el.value.replace(/,/g, '').replace(/[^0-9]/g, '');
    if (raw) el.value = Number(raw).toLocaleString('en-US');
}

function numberToWords(num) {
    if (num === 0) return 'صفر';
    const ones = ['', 'یک', 'دو', 'سه', 'چهار', 'پنج', 'شش', 'هفت', 'هشت', 'نه'];
    const tens = ['', 'ده', 'بیست', 'سی', 'چهل', 'پنجاه', 'شصت', 'هفتاد', 'هشتاد', 'نود'];
    const teens = ['ده', 'یازده', 'دوازده', 'سیزده', 'چهارده', 'پانزده', 'شانزده', 'هفده', 'هجده', 'نوزده'];
    const hundreds = ['', 'صد', 'دویست', 'سیصد', 'چهارصد', 'پانصد', 'ششصد', 'هفتصد', 'هشتصد', 'نهصد'];
    const scales = ['', 'هزار', 'میلیون', 'میلیارد'];
    function convert(n) {
        if (n < 10) return ones[n]; if (n < 20) return teens[n-10];
        if (n < 100) return tens[Math.floor(n/10)] + (n%10 ? ' و ' + ones[n%10] : '');
        if (n < 1000) return hundreds[Math.floor(n/100)] + (n%100 ? ' و ' + convert(n%100) : '');
        for (let i=1; i<scales.length; i++) { let div=Math.pow(1000,i); if (n < Math.pow(1000,i+1)) return convert(Math.floor(n/div))+' '+scales[i]+(n%div?' و '+convert(n%div):''); }
        return '';
    }
    return convert(num) + ' ریال';
}

// ========== ارسال به Print Preview ==========
function openPrintPreview(htmlContent) {
    localStorage.setItem('printContent', htmlContent);
    window.open('print/', '_blank');
}

function openPrintTab() {
    let invDate = document.getElementById('invDate').value || '—';
    let invNumber = document.getElementById('invNumber').value || '—';
    let invVolume = document.getElementById('invVolume').value || '—';
    let buyerName = document.getElementById('buyerName').value || '—';
    let buyerNational = document.getElementById('buyerNational').value || '—';
    let buyerPersonnel = document.getElementById('buyerPersonnel').value || '—';
    let buyerJob = document.getElementById('buyerJob').value || '—';
    let buyerPhone = document.getElementById('buyerPhone').value || '—';
    let buyerAddress = document.getElementById('buyerAddress').value || '—';
    let instAmount = parseNum(document.getElementById('instAmount').value);
    let instCount = parseInt(document.getElementById('instCount').value) || 0;
    let grandTotal = instAmount * instCount;
    
    let descs = [];
    document.querySelectorAll('#itemsBody .desc').forEach(inp => {
        let v = inp.value.trim();
        if (v) descs.push(v);
    });
    
    let itemsHtml = descs.map((d, i) => `<tr><td>${i+1}</td><td>${d}</td></tr>`).join('');
    let words = numberToWords(Math.round(grandTotal));
    let cleanName = buyerName.replace(/\s+/g, '_').replace(/[^آ-یa-zA-Z0-9_]/g, '') || 'مشتری';
    let fileName = 'پیش_فاکتور_فرهنگیان_-_' + cleanName;
    
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
        .subtitle { text-align: center; font-size: 8pt; margin-bottom: 2mm; }
        .header-row { display: flex; justify-content: space-between; font-size: 8pt; margin-bottom: 2mm; }
        .seller-box { border: 1px solid #000; padding: 1.5mm; margin-bottom: 2mm; font-size: 7pt; line-height: 1.4; }
        .buyer-box { border: 1px solid #000; padding: 1.5mm; margin-bottom: 2mm; }
        .buyer-box table { width: 100%; border-collapse: collapse; font-size: 8pt; }
        .buyer-box th, .buyer-box td { border: 1px solid #000; padding: 1.5mm; text-align: center; }
        .buyer-box th { background: #eee; font-size: 7pt; }
        .items-table { width: 100%; border-collapse: collapse; margin: 2mm 0; font-size: 8pt; }
        .items-table th, .items-table td { border: 1px solid #000; padding: 2mm; text-align: center; }
        .items-table th { background: #eee; }
        .summary-row { display: flex; gap: 4mm; margin: 2mm 0; font-size: 8pt; }
        .summary-box { flex: 1; border: 1px solid #000; padding: 1.5mm; }
        .amount-words { margin: 2mm 0; padding: 1.5mm; border: 1px solid #000; font-size: 8pt; }
        .sign-row { display: flex; justify-content: space-between; margin-top: 6mm; font-size: 8pt; }
        .sign-box { text-align: center; flex: 1; }
        .footer-note { text-align: center; margin-top: 2mm; font-size: 6pt; color: #999; }
    </style>
</head>
<body>
    <h2>فاکتور فروش کالا</h2>
    <div class="subtitle">معرفی به شرکت تعاونی مصرف فرهنگیان استان آذربایجان شرقی</div>
    
    <div class="header-row">
        <span><strong>تاریخ:</strong> ${invDate}</span>
        <span><strong>شماره فاکتور:</strong> ${invNumber}</span>
        <span><strong>جلد:</strong> ${invVolume}</span>
    </div>
    
    <div class="seller-box">
        <strong>فروشنده:</strong> فروشگاه زرگری ثنا<br>
        شعبه ۱: چهارراه ابوریحان، پاساژ ایران، طبقه همکف، پلاک ۱۵<br>
        شعبه ۲: چهارراه طالقانی، اول طالقانی جنوبی، روبروی مسجد عربلر<br>
        شعبه ۳: چهارراه آبرسان، برج سفید، طبقه همکف، پلاک ۲۱<br>
        <strong>تلفن:</strong> ۳۴۷۸۲۳۷۳ — ۳۵۴۰۶۴۸۶ — ۳۳۳۴۷۷۰۹
    </div>
    
    <div class="buyer-box">
        <table>
            <thead><tr><th>آقای/خانم</th><th>کد ملی</th><th>شماره پرسنلی</th><th>شاغل در</th><th>تلفن</th><th>آدرس</th></tr></thead>
            <tbody><tr><td>${buyerName}</td><td>${buyerNational}</td><td>${buyerPersonnel}</td><td>${buyerJob}</td><td>${buyerPhone}</td><td>${buyerAddress}</td></tr></tbody>
        </table>
    </div>
    
    <table class="items-table">
        <thead><tr><th>ردیف</th><th>شرح کالا</th></tr></thead>
        <tbody>${itemsHtml}</tbody>
    </table>
    
    <div class="summary-row">
        <div class="summary-box"><strong>مبلغ قسط ماهانه:</strong> ${fmtNum(Math.round(instAmount))} ریال</div>
        <div class="summary-box"><strong>تعداد اقساط:</strong> ${instCount}</div>
        <div class="summary-box"><strong>کل مانده بدهی:</strong> ${fmtNum(Math.round(grandTotal))} ریال</div>
    </div>
    
    <div class="amount-words"><strong>مبلغ به حروف:</strong> ${words}</div>
    
    <div class="sign-row">
        <div class="sign-box">امضا خریدار</div>
        <div class="sign-box">مهر و امضا<br>شرکت تعاونی مصرف فرهنگیان استان آ.ش</div>
        <div class="sign-box">مهر و امضا<br>فروشگاه زرگری ثنا</div>
    </div>
    
    <div class="footer-note">این فاکتور توسط سامانه تراز روزانه صادر شده است.</div>
</body>
</html>`;
    
    openPrintPreview(html);
}

window.onload = function() { addRow(); addRow(); addRow(); };
</script>
</body>
</html>