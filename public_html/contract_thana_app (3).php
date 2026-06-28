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
if (strpos($permissions, 'contract_thana') === false && $_SESSION['role'] !== 'admin') {
    die('<div style="text-align:center;padding:60px;font-family:Vazirmatn;background:#0a0f1a;color:#e8ecf1;">⛔ شما دسترسی به این بخش را ندارید.<br><a href="index.php" style="color:#4b8cf7;">بازگشت</a></div>');
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>قرارداد وام آتیه طلا | تراز روزانه</title>
    <link href="assets/fonts/fonts.css" rel="stylesheet">
    <link href="assets/css/light-theme.css" rel="stylesheet">
    <link rel="icon" href="assets/images/logo.png">
    <style>
        :root {
            --bg: #0a0f1a; --surface: rgba(255,255,255,0.03);
            --border: rgba(255,255,255,0.06); --text: #e8ecf1;
            --text-secondary: #8899aa; --accent: #4b8cf7;
            --gold: #d4af37; --gold-light: #ffd700;
            --radius: 8px; --radius-sm: 4px;
            --input-bg: rgba(255,255,255,0.03);
        }
        
        body.light {
            --bg: #f8fafc; --surface: #ffffff;
            --border: #cbd5e1; --text: #0f172a;
            --text-secondary: #475569; --accent: #2563eb;
            --gold: #b48600; --gold-light: #d4af37;
            --input-bg: #f1f5f9;
        }

        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Vazirmatn', sans-serif;
            background: var(--bg); color: var(--text);
            min-height: 100vh; padding: 20px;
            transition: background 0.3s ease, color 0.3s ease;
        }
        .container { max-width: 950px; margin: 0 auto; display: flex; flex-direction: column; gap: 15px; }
        
        .header {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: var(--radius); padding: 15px 20px;
            display: flex; align-items: center; justify-content: space-between;
        }
        .header h2 { font-size: 1.1rem; font-weight: 700; color: var(--gold-light); }
        .header-actions { display: flex; gap: 10px; align-items: center; }
        .btn-outline {
            background: transparent; border: 1px solid var(--border);
            color: var(--text); padding: 6px 15px; border-radius: var(--radius-sm);
            cursor: pointer; font-family: 'Vazirmatn'; font-size: 0.8rem;
            text-decoration: none; transition: all 0.2s;
        }
        .btn-outline:hover { background: var(--surface); border-color: var(--text-secondary); }
        
        .card {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: var(--radius); padding: 20px;
        }
        .card-title { font-weight: 700; margin-bottom: 15px; font-size: 0.9rem; color: var(--text); border-bottom: 1px solid var(--border); padding-bottom: 8px; }
        
        .form-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 15px; }
        .form-row-2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin-bottom: 15px; }
        @media (max-width: 768px) { .form-row, .form-row-2 { grid-template-columns: 1fr; } }
        
        label { display: block; margin-bottom: 6px; font-weight: 600; color: var(--text-secondary); font-size: 0.8rem; }
        input, textarea, select {
            width: 100%; padding: 10px 12px;
            background: var(--input-bg); border: 1px solid var(--border);
            border-radius: var(--radius-sm); color: var(--text);
            font-family: 'Vazirmatn', sans-serif; font-size: 0.85rem;
        }
        input:focus, textarea:focus { border-color: var(--accent); outline: none; }
        input[readonly] { opacity: 0.7; cursor: not-allowed; }
        
        .summary-box {
            padding: 12px; background: rgba(212,175,55,0.08);
            border: 1px solid rgba(212,175,55,0.2); border-radius: var(--radius-sm);
            font-size: 0.85rem; text-align: center; color: var(--text);
        }
        .summary-box b { color: var(--gold-light); display: block; margin-bottom: 4px; font-size: 0.75rem; }
        
        .btn-print {
            margin-top: 10px; padding: 14px; width: 100%;
            background: #2c3e50; color: #fff; border: 1px solid #1a252f;
            border-radius: var(--radius-sm); cursor: pointer;
            font-family: 'Vazirmatn', sans-serif; font-weight: 700; font-size: 1rem;
            transition: background 0.2s; letter-spacing: 0.5px;
        }
        body.light .btn-print { background: #1e293b; }
        .btn-print:hover { background: #1a252f; }
    </style>
</head>
<body>
<div class="container">
    
    <div class="header">
        <h2>فرم ساز قرارداد تسهیلات آتیه طلا</h2>
        <div class="header-actions">
            <button class="btn-outline" onclick="toggleTheme()" id="themeBtn">حالت تیره</button>
            <a href="index.php" class="btn-outline">بازگشت</a>
        </div>
    </div>
    
    <div class="card">
        <div class="card-title">اطلاعات سند</div>
        <div class="form-row">
            <div><label>شماره قرارداد</label><input type="text" id="cNumber" placeholder="مثال: ۲۳۴"></div>
            <div><label>شعبه</label><input type="text" id="cSeries" placeholder="مثال: شعبه ۲"></div>
            <div><label>تاریخ تنظیم</label><input type="text" id="cDate" value="<?php echo date('Y/m/d'); ?>"></div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-title">مشخصات متقاضی (خریدار)</div>
        <div class="form-row">
            <div><label>نام و نام خانوادگی</label><input type="text" id="cName" placeholder="نام کامل"></div>
            <div><label>نام پدر</label><input type="text" id="cFather" placeholder="نام پدر"></div>
            <div><label>کد ملی</label><input type="text" id="cNational" placeholder="کد ملی ۱۰ رقمی"></div>
        </div>
        <div class="form-row">
            <div><label>تاریخ تولد</label><input type="text" id="cBirth" placeholder="مثال: ۱۳۴۹/۱۲/۰۵"></div>
            <div><label>شماره شناسنامه</label><input type="text" id="cIdNumber" placeholder="شماره شناسنامه"></div>
            <div><label>محل صدور</label><input type="text" id="cIdPlace" placeholder="شهر صدور"></div>
        </div>
        <div class="form-row-2">
            <div><label>تلفن همراه</label><input type="text" id="cMobile" placeholder="۰۹۱۲۳۴۵۶۷۸۹"></div>
            <div><label>تلفن ثابت</label><input type="text" id="cPhone" placeholder="پیش‌شماره + تلفن"></div>
        </div>
        <div><label>نشانی دقیق اقامتگاه</label><textarea id="cAddress" rows="2" placeholder="استان، شهر، خیابان، کوچه، پلاک..."></textarea></div>
    </div>

    <div class="card">
        <div class="card-title">مشخصات ضامن / حساب آتیه طلا</div>
        <div class="form-row">
            <div><label>نام و نام خانوادگی ضامن</label><input type="text" id="gName" placeholder="نام کامل ضامن"></div>
            <div><label>نام پدر ضامن</label><input type="text" id="gFather" placeholder="نام پدر"></div>
            <div><label>کد ملی ضامن</label><input type="text" id="gNational" placeholder="کد ملی"></div>
        </div>
        <div class="form-row">
            <div><label>تلفن ضامن</label><input type="text" id="gMobile" placeholder="تلفن ضامن"></div>
            <div><label>شماره حساب آتیه طلا (ماده ۵)</label><input type="text" id="cAtiyehAccount" placeholder="شماره حساب"></div>
            <div><label>نام صاحب حساب آتیه طلا</label><input type="text" id="cAtiyehName" placeholder="نام کامل صاحب حساب"></div>
        </div>
        <div><label>نشانی ضامن</label><textarea id="gAddress" rows="1" placeholder="آدرس دقیق ضامن..."></textarea></div>
    </div>
    
    <div class="card">
        <div class="card-title">شرایط تسهیلات و مالی</div>
        <div class="form-row">
            <div><label>مبلغ پایه تسهیلات (ریال)</label><input type="text" id="cLoan" placeholder="مبلغ وام به ریال" oninput="formatNum(this); calcAll();"></div>
            <div><label>تعداد اقساط (ماه)</label><input type="number" id="cInstCount" value="24" oninput="calcAll();"></div>
            <div><label>نرخ سود ماهانه (٪)</label><input type="number" id="cMonthlyRate" value="4.8" step="0.1" oninput="calcAll();"></div>
        </div>
        <div class="form-row">
            <div><label>تاریخ سررسید نهایی</label><input type="text" id="cEndDate" readonly></div>
        </div>
        
        <div class="card-title" style="margin-top:20px;">خلاصه محاسبات سیستم</div>
        <div class="form-row">
            <div class="summary-box"><b>مبلغ سود کل</b> <span id="rProfit">۰</span> ریال</div>
            <div class="summary-box"><b>مجموع اصل و سود</b> <span id="rTotal">۰</span> ریال</div>
            <div class="summary-box"><b>مبلغ هر قسط</b> <span id="rInstAmount">۰</span> ریال</div>
        </div>
        <div class="form-row">
            <div class="summary-box"><b>تعداد پرداخت</b> <span id="rInstCount">۰</span> قسط</div>
            <div class="summary-box"><b>تاریخ پایان</b> <span id="rEndDate">—</span></div>
            <div class="summary-box" style="visibility: hidden;"></div>
        </div>
    </div>
    
    <button class="btn-print" onclick="openPrintTab()">صدور و چاپ نسخه رسمی قرارداد</button>
    
</div>

<script>
function toggleTheme() {
    document.body.classList.toggle('light');
    let btn = document.getElementById('themeBtn');
    if (document.body.classList.contains('light')) { 
        btn.textContent = 'حالت تیره'; 
        localStorage.setItem('theme', 'light'); 
    } else { 
        btn.textContent = 'حالت روشن'; 
        localStorage.setItem('theme', 'dark'); 
    }
}
(function() { 
    if (localStorage.getItem('theme') === 'light') { 
        document.body.classList.add('light'); 
        document.getElementById('themeBtn').textContent = 'حالت تیره'; 
    } 
})();

function fmtNum(n) { return Number(n||0).toLocaleString('fa-IR'); }
function parseNum(s) { return parseFloat(String(s||'').replace(/,/g, '')) || 0; }
function formatNum(el) { let raw = el.value.replace(/,/g, '').replace(/[^0-9]/g, ''); if (raw) el.value = Number(raw).toLocaleString('en-US'); }

function numberToWords(num) {
    if (num === 0) return 'صفر';
    const ones = ['', 'یک', 'دو', 'سه', 'چهار', 'پنج', 'شش', 'هفت', 'هشت', 'نه'];
    const tens = ['', 'ده', 'بیست', 'سی', 'چهل', 'پنجاه', 'شصت', 'هفتاد', 'هشتاد', 'نود'];
    const teens = ['ده', 'یازده', 'دوازده', 'سیزده', 'چهارده', 'پانزده', 'شانزده', 'هفده', 'هجده', 'نوزده'];
    const hundreds = ['', 'صد', 'دویست', 'سیصد', 'چهارصد', 'پانصد', 'ششصد', 'هفتصد', 'هشتصد', 'نهصد'];
    const scales = ['', 'هزار', 'میلیون', 'میلیارد', 'تریلیون'];
    function convert(n) {
        if (n < 10) return ones[n]; if (n < 20) return teens[n-10];
        if (n < 100) return tens[Math.floor(n/10)] + (n%10 ? ' و ' + ones[n%10] : '');
        if (n < 1000) return hundreds[Math.floor(n/100)] + (n%100 ? ' و ' + convert(n%100) : '');
        for (let i=1; i<scales.length; i++) { let div=Math.pow(1000,i); if (n < Math.pow(1000,i+1)) return convert(Math.floor(n/div))+' '+scales[i]+(n%div?' و '+convert(n%div):''); }
        return '';
    }
    return convert(num);
}

function calcAll() {
    let loan = parseNum(document.getElementById('cLoan').value);
    let count = parseInt(document.getElementById('cInstCount').value) || 1;
    let monthlyRate = parseFloat(document.getElementById('cMonthlyRate').value) || 0;
    
    let profit = Math.round(loan * (monthlyRate / 100) * count);
    let total = loan + profit;
    let instAmount = Math.round(total / count);
    
    document.getElementById('rProfit').textContent = fmtNum(profit);
    document.getElementById('rTotal').textContent = fmtNum(total);
    document.getElementById('rInstAmount').textContent = fmtNum(instAmount);
    document.getElementById('rInstCount').textContent = count;
    
    let startDate = document.getElementById('cDate').value;
    if (startDate.match(/^\d{4}\/\d{2}\/\d{2}$/)) {
        let parts = startDate.split('/');
        let y = parseInt(parts[0]), m = parseInt(parts[1]), d = parseInt(parts[2]);
        m += count;
        while (m > 12) { y++; m -= 12; }
        let endDate = y + '/' + String(m).padStart(2,'0') + '/' + String(d).padStart(2,'0');
        document.getElementById('cEndDate').value = endDate;
        document.getElementById('rEndDate').textContent = endDate;
    }
}

function generateInstallmentDates(startDate, count) {
    let dates = [];
    if (!startDate.match(/^\d{4}\/\d{2}\/\d{2}$/)) return dates;
    let parts = startDate.split('/');
    let y = parseInt(parts[0]), m = parseInt(parts[1]), d = parseInt(parts[2]);
    
    for (let i = 0; i < count; i++) {
        m++;
        if (m > 12) { y++; m = 1; }
        dates.push(y + '/' + String(m).padStart(2,'0') + '/' + String(d).padStart(2,'0'));
    }
    return dates;
}

function openPrintPreview(htmlContent) {
    localStorage.setItem('printContent', htmlContent);
    window.open('print/', '_blank');
}

function openPrintTab() {
    let cNumber = document.getElementById('cNumber').value || '—';
    let cSeries = document.getElementById('cSeries').value || '—';
    let cDate = document.getElementById('cDate').value || '—';
    
    // Customer
    let cName = document.getElementById('cName').value || '—';
    let cFather = document.getElementById('cFather').value || '—';
    let cNational = document.getElementById('cNational').value || '—';
    let cBirth = document.getElementById('cBirth').value || '—';
    let cIdNumber = document.getElementById('cIdNumber').value || '—';
    let cIdPlace = document.getElementById('cIdPlace').value || '—';
    let cMobile = document.getElementById('cMobile').value || '—';
    let cPhone = document.getElementById('cPhone').value || '—';
    let cAddress = document.getElementById('cAddress').value || '—';
    
    // Guarantor / Atiyeh
    let gName = document.getElementById('gName').value || '—';
    let gFather = document.getElementById('gFather').value || '—';
    let gNational = document.getElementById('gNational').value || '—';
    let gMobile = document.getElementById('gMobile').value || '—';
    let gAddress = document.getElementById('gAddress').value || '—';
    let cAtiyehAccount = document.getElementById('cAtiyehAccount').value || '—';
    let cAtiyehName = document.getElementById('cAtiyehName').value || '—';

    let cLoan = parseNum(document.getElementById('cLoan').value);
    let cInstCount = parseInt(document.getElementById('cInstCount').value) || 24;
    let cMonthlyRate = parseFloat(document.getElementById('cMonthlyRate').value) || 4.8;
    let cEndDate = document.getElementById('cEndDate').value || '—';
    
    let profit = Math.round(cLoan * (cMonthlyRate / 100) * cInstCount);
    let total = cLoan + profit;
    let instAmount = Math.round(total / cInstCount);
    let instDates = generateInstallmentDates(cDate, cInstCount);
    
    let cleanName = cName.replace(/\s+/g, '_').replace(/[^آ-یa-zA-Z0-9_]/g, '') || 'مشتری';
    let fileName = 'قرارداد_آتیه_طلا_' + cleanName;
    
    let instTable = '';
    let rowsPerCol = Math.ceil(cInstCount / 3);
    for (let r = 0; r < rowsPerCol; r++) {
        instTable += '<tr>';
        for (let col = 0; col < 3; col++) {
            let idx = r + col * rowsPerCol;
            if (idx < cInstCount) {
                instTable += `<td>${idx+1}</td><td dir="ltr">${instDates[idx] || '—'}</td><td>${fmtNum(instAmount)} ریال</td>`;
            } else {
                instTable += `<td></td><td></td><td></td>`;
            }
        }
        instTable += '</tr>';
    }
    
    let html = `<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>${fileName}</title>
    <link href="${window.location.origin}/~tarazroz/assets/fonts/fonts.css" rel="stylesheet">
    <style>
        @page { size: A4; margin: 0; }
        body { font-family: 'Vazirmatn', Tahoma, sans-serif; color: #000; background: #fff; direction: rtl; font-size: 8pt; line-height: 1.5; margin: 0; padding: 0; }
        
        .page-container {
            width: 190mm; min-height: 277mm; margin: 3mm auto 10mm auto;
            border: none; padding: 2mm 2mm 2mm 2mm; box-sizing: border-box;
            position: relative;
        }
        
        .print-header { width: 100%; border-bottom: 2px solid #000; padding-bottom: 2mm; margin-bottom: 3mm; }
        .header-table { width: 100%; border: none; }
        .header-table td { border: none; padding: 0; vertical-align: top; }
        
        .header-right { width: 33%; text-align: right; }
        .header-center { width: 34%; text-align: center; }
        .header-center h1 { font-size: 12.5pt; margin: 0 0 1mm 0; }
        .header-center h2 { font-size: 11pt; margin: 0; font-weight: bold; }
        .header-left { width: 33%; text-align: left; font-size: 9pt; line-height: 1.5; }
        
        .content { text-align: justify; text-justify: inter-word; }
        
        .section-title {
            font-size: 10.5pt; font-weight: bold; margin: 3mm 0 1mm 0;
            display: inline-block; width: 100%; border-bottom: 1px dashed #000; padding-bottom: 1mm;
        }
        
        p { margin: 0.8mm 0; }
        b { font-weight: bold; }
        
        .data-table { width: 100%; border-collapse: collapse; margin: 4mm 0; font-size: 9pt; }
        .data-table th, .data-table td { border: 1px solid #000; padding: 1.5mm; text-align: center; }
        .data-table th { background-color: #f0f0f0; font-weight: bold; }
        
        .signatures { width: 100%; margin-top: 4mm; border-collapse: collapse; }
        .signatures td { 
            border: none; height: 25mm; vertical-align: top; 
            padding: 2mm; text-align: center; width: 33.33%; font-size: 9.5pt; font-weight: bold;
        }
        
        .footer-note {
            text-align: center; margin-top: 6mm; font-size: 9pt;
            border-top: 1px solid #000; padding-top: 2mm; font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="page-container">
        <div class="print-header">
            <table class="header-table">
                <tr>
                    <td class="header-right"></td>
                    <td class="header-center">
                        <h1>بسمه تعالی</h1>
                        <h2>« قرارداد اعطای تسهیلات آتیه طلا »</h2>
                    </td>
                    <td class="header-left">
                        <b>شماره:</b> ${cNumber}<br>
                        <b>شعبه:</b> ${cSeries}<br>
                        <b>تاریخ:</b> <span dir="ltr">${cDate}</span>
                    </td>
                </tr>
            </table>
        </div>
        
        <div class="content">
            <div class="section-title">ماده ۱ : طرفین قرارداد</div>
            <p>این قرارداد بین امضاء کنندگان زیر منعقد می گردد:</p>
            <p><b>الف) زرگری ثنا به نشانی:</b><br>
            شعبه ۱: تبریز، چهارراه ابوریحان، پاساژ ایران، همکف، پلاک ۱۵ – تلفن: ۰۴۱۳۴۷۸۲۳۷۳<br>
            شعبه ۲: تبریز، چهارراه طالقانی، روبروی مسجد عربلر، پلاک ۸۴۸ – تلفن: ۰۴۱۳۵۴۰۶۴۸۶<br>
            شعبه ۳: تبریز، آبرسان، برج سفید، پلاک ۲۱ – تلفن: ۰۴۱۳۳۳۴۷۷۰۹<br>
            به نمایندگی آقای امیر فرشباف قیداری نژاد که از این پس در قرارداد زرگری ثنا نامیده می شود و</p>
            <p><b>ب) آقای / خانم :</b> <b>${cName}</b> <b>فرزند:</b> ${cFather} <b>تاریخ تولد:</b> <span dir="ltr">${cBirth}</span> <b>شماره شناسنامه:</b> ${cIdNumber} <b>محل صدور:</b> ${cIdPlace} <b>کد ملی:</b> ${cNational} <b>به نشانی:</b> ${cAddress} <b>تلفن ثابت:</b> ${cPhone} <b>موبایل:</b> ${cMobile} که از این پس در قرارداد متقاضی تسهیلات نامیده می شود.</p>
            <p><b>ج) ضامن :</b> شرکت / آقای / خانم <b>${gName}</b> <b>فرزند:</b> ${gFather} <b>کد ملی:</b> ${gNational} <b>به نشانی:</b> ${gAddress} <b>تلفن:</b> ${gMobile} با امضای این قرارداد منفرداً و متفقاً کلیه مطالبات ناشی از گیرنده تسهیلات ذکر شده در بند ب ذیل ماده یک این قرارداد را پذیرفته و متعهد می گردد کلیه مطالبات معوق مربوط به قرارداد فوق در سررسید هر قسط به حساب معرفی شده از سوی زرگری ثنا پرداخت نماید.</p>

            <div class="section-title">ماده ۲ : بهای معامله و شرایط پرداخت</div>
            <p>کل بهای فروش مورد معامله به متقاضی <b>${fmtNum(total)} ریال</b> به حروف (${numberToWords(total)} ریال) مشتمل بر قیمت تمام شده خرید مورد معامله توسط زرگری ثنا معادل <b>${fmtNum(cLoan)} ریال</b> به حروف (${numberToWords(cLoan)} ریال) و سود مربوطه معادل <b>${cMonthlyRate}% (ماهانه)</b> به مبلغ <b>${fmtNum(profit)} ریال</b> به حروف (${numberToWords(profit)} ریال) می‌باشد.</p>

            <div class="section-title">ماده ۳ : مدت قرارداد</div>
            <p>مدت این قرارداد از تاریخ امضای آن لغایت <b><span dir="ltr">${cEndDate}</span></b> به صورت <b>${cInstCount} ماهه</b> می باشد.</p>

            <div class="section-title">ماده ۴ : جرایم تاخیر تادیه</div>
            <p>چناچه متقاضی در سررسید هر قسط نسبت به پرداخت با تأخیر یا عدم پرداخت قسط مبادرت نماید این زرگری کلیه مطالبات ناشی از این قرارداد را به دین حال تبدیل نموده و جرایم ناشی از تأخیر تادیه به مأخذ ۶٪+ از متقاضی دریافت خواهد نمود.</p>

            <div class="section-title">ماده ۵ : ضمانت اجرا</div>
            <p>متقاضی متعهد می گردد در صورت تخطی از مفاد این قرارداد به هر عنوان بدون مراجعه به مراجع قضائی به دین حال تبدیل شده و قرارداد فوق به صورت یک طرفه فسخ و کلیه مطالبات این قرارداد با جرایم مربوطه به مأخذ ۶٪+ از حساب شماره <b>${cAtiyehAccount}</b> آتیه طلایی خانم/آقای <b>${cAtiyehName}</b> کسر گردد.</p>

            <div class="section-title">ماده ۶ : فسخ وام قبل از پایان قرارداد</div>
            <p><b>تبصره ۱:</b> چنانچه وام گیرنده قبل از سپری شدن یک ماه از تاریخ عقد قرارداد نسبت به فسخ آن اقدام نماید، سود ماه اول بطور کامل از مشتری اخذ خواهد شد.</p>
            <p><b>تبصره ۲:</b> چناچه وام گیرنده بعد از سپری شدن یک ماه و قبل از اتمام قراداد مربوطه نسبت به فسخ وام اقدام نماید، کسر ماه به صورت روز شمار محاسبه و اصل مانده بدهی از وام گیرنده اخذ خواهد شد .</p>

            <div class="section-title">ماده ۷ : نسخ قرارداد</div>
            <p>این قرارداد در ۷ ماده و ۲ تبصره و در ۲ نسخه تنظیم که هر دو حکم واحد را دارند، به رویت کامل زرگری ثنا، متقاضی، ضامن و وثیقه گذار رسید و ایشان ضمن اقرار به اطلاع و آگاهی کامل از مفاد این قرارداد و پذیرش آن به امضای تمامی صفحات آن مبادرت نمودند و یک نسخه از این قرارداد به هریک از آنها تسلیم گردید.</p>
            
            <div style="margin: 4mm 0; padding: 3mm; border: 1px dashed #666; background: #fafafa; line-height: 2;">
                شرکت / اینجانب <b>${cName}</b> به کد ملی <b>${cNational}</b> تعهد می نمایم در سررسید اقساط مندرج در قرارداد شماره <b>${cNumber}</b> تاریخ <b><span dir="ltr">${cDate}</span></b> را بپردازم و در صورت تأخیر در تأدیه بدهی جرایم ناشی از آن را بدون هیچ گونه عذر و بهانه ای پرداخت نمایم.<br>
                لطفا مبلغ <b>........................................</b> ریال به حساب شماره <b>..............................................................</b> اینجانب <b>..............................</b> واریز فرمایید.
                <div style="text-align: left; margin-top: 3mm;"><b>امضاء و اثر انگشت متعهد</b></div>
            </div>

            <table class="data-table">
                <thead><tr><th>ردیف</th><th>تاریخ سررسید</th><th>میزان قسط</th><th>ردیف</th><th>تاریخ سررسید</th><th>میزان قسط</th><th>ردیف</th><th>تاریخ سررسید</th><th>میزان قسط</th></tr></thead>
                <tbody>${instTable}</tbody>
            </table>
            <p style="text-align:center; font-weight: bold; margin-bottom: 4mm;">شماره تلفن همراه آقای/خانم: ${cName} (${cMobile}) &nbsp;&nbsp;&nbsp; | &nbsp;&nbsp;&nbsp; مقدار هر قسط: ${fmtNum(instAmount)} ریال</p>
            
            <table class="signatures">
                <tr>
                    <td>امضاء و اثرانگشت مشتری</td>
                    <td>امضاء و اثرانگشت وثیقه گذار</td>
                    <td>مهر و امضاء زرگری ثنا<br><span style="font-size:8pt;font-weight:normal;">به نام امیر فرشباف قیداری نژاد</span></td>
                </tr>
            </table>
            
            <div class="footer-note">
                شماره کارت و شبا جهت واریز اقساط: <span dir="ltr">۵۰۴۱-۷۲۱۲-۲۲۳۱-۲۸۴۶</span> | <span dir="ltr">IR920700010001111815908001</span><br>
                لطفاً پس از واریز رسید آن را به شماره ۰۹۱۴۳۱۷۴۶۹۴ ارسال فرمایید.
            </div>
        </div>
    </div>
</body>
</html>`;
    
    openPrintPreview(html);
}

calcAll();
</script>
</body>
</html>
