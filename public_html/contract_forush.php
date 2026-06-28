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
    <title>قرارداد وام ثنا | تراز روزانه</title>
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
        <h2>فرم ساز قرارداد تسهیلات ثنا (اقساط ثابت ریالی)</h2>
        <div class="header-actions">
            <button class="btn-outline" onclick="toggleTheme()" id="themeBtn">حالت تیره</button>
            <a href="index.php" class="btn-outline">بازگشت</a>
        </div>
    </div>
    
    <div class="card">
        <div class="card-title">اطلاعات سند</div>
        <div class="form-row">
            <div><label>شماره قرارداد</label><input type="text" id="cNumber" placeholder="مثال: ۱۲۵۱۴"></div>
            <div><label>شماره فاکتور</label><input type="text" id="cFactor" placeholder="شماره فاکتور طلا"></div>
            <div><label>شماره جلد</label><input type="text" id="cVolume" placeholder="جلد"></div>
        </div>
        <div class="form-row">
            <div><label>تاریخ تنظیم</label><input type="text" id="cDate" value="<?php echo date('Y/m/d'); ?>"></div>
            <div></div><div></div>
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
            <div><label>تاریخ تولد</label><input type="text" id="cBirth" placeholder="مثال: ۱۳۶۳/۰۳/۰۲"></div>
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
        <div class="card-title">مشخصات ضامن / وثیقه‌گذار</div>
        <div class="form-row">
            <div><label>نام و نام خانوادگی ضامن</label><input type="text" id="gName" placeholder="نام کامل ضامن"></div>
            <div><label>نام پدر</label><input type="text" id="gFather" placeholder="نام پدر"></div>
            <div><label>کد ملی (شماره ثبت)</label><input type="text" id="gNational" placeholder="کد ملی یا شناسه ثبت"></div>
        </div>
        <div class="form-row">
            <div><label>شماره شناسنامه ضامن</label><input type="text" id="gIdNumber" placeholder="شماره شناسنامه"></div>
            <div><label>محل صدور ضامن</label><input type="text" id="gIdPlace" placeholder="محل صدور"></div>
            <div><label>تلفن همراه ضامن</label><input type="text" id="gMobile" placeholder="۰۹۱۲۳۴۵۶۷۸۹"></div>
        </div>
        <div><label>مشخصات دقیق وثیقه (ماده ۷)</label><input type="text" id="cCollateral" placeholder="مثال: مدال ورساچه ۲.۸۶۰ گرم" style="margin-bottom:15px;"></div>
        <div><label>نشانی ضامن</label><textarea id="gAddress" rows="1" placeholder="آدرس دقیق ضامن..."></textarea></div>
    </div>
    
    <div class="card">
        <div class="card-title">شرایط تسهیلات و مالی</div>
        <div class="form-row">
            <div><label>مبلغ پایه تسهیلات (ریال)</label><input type="text" id="cLoan" placeholder="مبلغ وام به ریال" oninput="formatNum(this); calcAll();"></div>
            <div><label>نرخ روز طلا (ریال/گرم)</label><input type="text" id="cGoldRate" placeholder="قیمت هر گرم" oninput="formatNum(this); calcAll();"></div>
            <div><label>تعداد اقساط (ماه)</label><input type="number" id="cInstCount" value="6" oninput="calcAll();"></div>
        </div>
        <div class="form-row">
            <div><label>سود کل دوره (٪) - مثلا 5 برای کل 6 ماه</label><input type="number" id="cMonthlyRate" value="5" step="0.1" oninput="calcAll();"></div>
            <div><label>تاریخ سررسید نهایی</label><input type="text" id="cEndDate" readonly></div>
            <div></div>
        </div>
        
        <div class="card-title" style="margin-top:20px;">خلاصه محاسبات سیستم</div>
        <div class="form-row">
            <div class="summary-box"><b>مبلغ سود کل</b> <span id="rProfit">۰</span> ریال</div>
            <div class="summary-box"><b>مجموع اصل و سود</b> <span id="rTotal">۰</span> ریال</div>
            <div class="summary-box"><b>معادل طلای کل</b> <span id="rGold">۰</span> گرم</div>
        </div>
        <div class="form-row">
            <div class="summary-box"><b>مبلغ هر قسط ثابت</b> <span id="rInstAmount">۰</span> </div>
            <div class="summary-box"><b>تعداد پرداخت</b> <span id="rInstCount">۰</span> قسط</div>
            <div class="summary-box"><b>تاریخ پایان</b> <span id="rEndDate">—</span></div>
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
    let rate = parseNum(document.getElementById('cGoldRate').value);
    let count = parseInt(document.getElementById('cInstCount').value) || 1;
    let periodRate = parseFloat(document.getElementById('cMonthlyRate').value) || 0;
    
    let profit = Math.round(loan * (periodRate / 100) * count); 
    let total = loan + profit;
    
    let totalGold = rate > 0 ? total / rate : 0;
    let instRial = Math.round(total / count);
    
    document.getElementById('rProfit').textContent = fmtNum(profit);
    document.getElementById('rTotal').textContent = fmtNum(total);
    document.getElementById('rGold').textContent = totalGold.toFixed(3);
    document.getElementById('rInstAmount').textContent = fmtNum(instRial) + ' ریال';
    document.getElementById('rInstCount').textContent = count;
    
    let startDate = document.getElementById('cDate').value;
    if (startDate.match(/^\d{4}\/\d{2}\/\d{2}$/) || startDate.match(/^\d{8}$/)) {
        let y, m, d;
        if(startDate.includes('/')){
            let parts = startDate.split('/');
            y = parseInt(parts[0]); m = parseInt(parts[1]); d = parseInt(parts[2]);
        } else {
            y = parseInt(startDate.substring(0,4)); m = parseInt(startDate.substring(4,6)); d = parseInt(startDate.substring(6,8));
        }
        
        m += count;
        while (m > 12) { y++; m -= 12; }
        let endDate = y + '/' + String(m).padStart(2,'0') + '/' + String(d).padStart(2,'0');
        document.getElementById('cEndDate').value = endDate;
        document.getElementById('rEndDate').textContent = endDate;
    }
}

function generateInstallmentDates(startDate, count) {
    let dates = [];
    let y, m, d;
    if (startDate.includes('/')) {
        let parts = startDate.split('/');
        y = parseInt(parts[0]); m = parseInt(parts[1]); d = parseInt(parts[2]);
    } else if (startDate.length === 8) {
        y = parseInt(startDate.substring(0,4)); m = parseInt(startDate.substring(4,6)); d = parseInt(startDate.substring(6,8));
    } else {
        return dates;
    }
    
    for (let i = 0; i < count; i++) {
        m++;
        if (m > 12) { y++; m = 1; }
        // تاریخ‌ها رو با اسلش برمی‌گردونیم
        dates.push(y + '/' + String(m).padStart(2,'0') + '/' + String(d).padStart(2,'0'));
    }
    return dates;
}

function openPrintPreview(htmlContent) {
    let printWindow = window.open('', '_blank');
    printWindow.document.open();
    printWindow.document.write(htmlContent);
    printWindow.document.close();
    
    setTimeout(function() {
        printWindow.print();
    }, 500);
}

function openPrintTab() {
    let cNumber = document.getElementById('cNumber').value || '.......';
    let cFactor = document.getElementById('cFactor').value || '.......';
    let cVolume = document.getElementById('cVolume').value || '.......';
    // تاریخ شروع رو با اسلش نگه می‌داریم
    let cDate = document.getElementById('cDate').value || '.......';
    
    let cName = document.getElementById('cName').value || '.......';
    let cFather = document.getElementById('cFather').value || '.......';
    let cNational = document.getElementById('cNational').value || '.......';
    // تاریخ تولد رو هم با اسلش نگه می‌داریم
    let cBirth = document.getElementById('cBirth').value || '.......';
    let cIdNumber = document.getElementById('cIdNumber').value || '.......';
    let cIdPlace = document.getElementById('cIdPlace').value || '.......';
    let cMobile = document.getElementById('cMobile').value || '.......';
    let cPhone = document.getElementById('cPhone').value || '.......';
    let cAddress = document.getElementById('cAddress').value || '.......';
    
    let gName = document.getElementById('gName').value || '.......';
    let gFather = document.getElementById('gFather').value || '.......';
    let gNational = document.getElementById('gNational').value || '.......';
    let gIdNumber = document.getElementById('gIdNumber').value || '.......';
    let gIdPlace = document.getElementById('gIdPlace').value || '.......';
    let gMobile = document.getElementById('gMobile').value || '.......';
    let gAddress = document.getElementById('gAddress').value || '.......';

    let cLoan = parseNum(document.getElementById('cLoan').value);
    let cGoldRate = parseNum(document.getElementById('cGoldRate').value);
    let cInstCount = parseInt(document.getElementById('cInstCount').value) || 6;
    let cMonthlyRate = parseFloat(document.getElementById('cMonthlyRate').value) || 0;
    let cCollateral = document.getElementById('cCollateral').value || '.......';
    // تاریخ پایان رو با اسلش نگه می‌داریم
    let cEndDate = document.getElementById('cEndDate').value || '.......';
    
    let profit = Math.round(cLoan * (cMonthlyRate / 100) * cInstCount);
    let total = cLoan + profit;
    
    let instRial = Math.round(total / cInstCount);
    // تاریخ‌های اقساط با اسلش برگردونده می‌شن
    let instDates = generateInstallmentDates(cDate, cInstCount);
    
    let cleanName = cName.replace(/\s+/g, '_').replace(/[^آ-یa-zA-Z0-9_]/g, '') || 'مشتری';
    let fileName = 'قرارداد_سند_' + cleanName;
    
    let instTable = '';
    let rowsPerCol = Math.ceil(cInstCount / 3);
    for (let r = 0; r < rowsPerCol; r++) {
        instTable += '<tr>';
        for (let col = 0; col < 3; col++) {
            let idx = r + col * rowsPerCol;
            if (idx < cInstCount) {
                // نمایش تاریخ با اسلش
                instTable += `<td>${idx+1}</td><td dir="ltr">${instDates[idx] || '—'}</td><td>${instRial.toLocaleString('en-US')} ریال</td>`;
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
        body { font-family: 'Vazirmatn', Tahoma, sans-serif; color: #000; background: #fff; direction: rtl; font-size: 8.5pt; line-height: 1.8; margin: 0; padding: 0; }
        
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
        .header-center h1 { font-size: 11pt; margin: 0 0 1mm 0; }
        .header-center h2 { font-size: 13pt; margin: 0; font-weight: bold; }
        .header-left { width: 33%; text-align: left; font-size: 9pt; line-height: 1.5; }
        
        .content { text-align: justify; text-justify: inter-word; }
        
        .section-title {
            font-size: 10.5pt; font-weight: bold; margin: 3mm 0 1mm 0;
            display: inline-block; width: 100%;
        }
        
        p { margin: 1mm 0; }
        b { font-weight: bold; }
        
        .data-table { width: 100%; border-collapse: collapse; margin: 4mm 0; font-size: 9pt; }
        .data-table th, .data-table td { border: 1px solid #000; padding: 1.5mm; text-align: center; }
        .data-table th { background-color: #f0f0f0; font-weight: bold; }
        
        .signatures { width: 100%; margin-top: 6mm; border-collapse: collapse; }
        .signatures td { 
            border: none; height: 25mm; vertical-align: top; 
            padding: 2mm; text-align: center; width: 33.33%; font-size: 9.5pt; font-weight: bold;
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
                        <h1>باسمه تعالی</h1>
                        <h2>« قرارداد فروش قسطی طلا »</h2>
                    </td>
                    <td class="header-left">
                        <b>شماره:</b> ${cNumber}<br>
                        <b>تاریخ:</b> <span dir="ltr">${cDate}</span>
                    </td>
                </tr>
            </table>
        </div>
        
        <div class="content">
            <div class="section-title">ماده ۱ : طرفین قرارداد :</div>
            <p>این قرارداد بین امضاء کنندگان زیر منعقد می گردد<br>
            <b>الف) زرگری ثنا به نشانی:</b><br>
            شعبه ۱: تبریز، چهارراه ابوریحان، پاساژ ایران، همکف، پلاک ۱۵ – تلفن: ۰۴۱۳۴۷۸۲۳۷۳<br>
            شعبه ۲: تبریز، چهارراه طالقانی، روبروی مسجد عربلر، پلاک ۸۴۸ – تلفن: ۰۴۱۳۵۴۰۶۴۸۶<br>
            شعبه ۳: تبریز، آبرسان، برج سفید، پلاک ۲۱ – تلفن: ۰۴۱۳۳۳۴۷۷۰۹<br>
            به نمایندگی آقای امیر فرشباف قیداری نژاد که از این پس در قرارداد زرگری ثنا نامیده می شود و<br>
            <b>ب) آقای / خانم:</b> <b>${cName}</b> <b>فرزند:</b> ${cFather} <b>تاریخ تولد:</b> <span dir="ltr">${cBirth}</span> <b>شماره شناسنامه:</b> ${cIdNumber} <b>محل صدور:</b> ${cIdPlace} <b>کد ملی:</b> ${cNational}<br>
            <b>به نشانی:</b> ${cAddress} &nbsp;&nbsp;&nbsp; <b>شماره تلفن ثابت:</b> ${cPhone} &nbsp;&nbsp;&nbsp; <b>شماره تلفن همراه:</b> ${cMobile}<br>
            که از این پس در قرارداد متقاضی تسهیلات نامیده می شود.<br>
            <b>ج) ضامن :</b> شرکت / آقای / خانم <b>${gName}</b> به شماره ثبت / ملی <b>${gNational}</b> فرزند <b>${gFather}</b> شماره شناسنامه <b>${gIdNumber}</b> محل صدور <b>${gIdPlace}</b> با امضای این قرارداد منفرداً و متفقاً کلیه مطالبات ناشی از گیرنده تسهیلات ذکر شده در بند ب ذیل ماده یک این قرارداد را پذیرفته و متعهد می گردد کلیه مطالبات مربوط به قرارداد فوق در سررسید هر قسط به حساب معرفی شده از سوی زرگری ثنا پرداخت نماید.</p>

            <div class="section-title">ماده ۲ : موضوع قرارداد عبارت است از فروش اقساطی طلا با مشخصات زیر:</div>
            <p>متقاضی از کمیت و کیفیت و اوصاف مورد معامله مطلع بوده و حسب درخواست مورخ <b>${cDate}</b> که به امضای وی رسیده اقدام به خرید طلا طبق فاکتور شماره <b>${cFactor}</b> جلد <b>${cVolume}</b> از زرگری ثنا نموده است.</p>

            <div class="section-title">ماده ۳ :</div>
            <p>اصل بدهی <b>${cLoan.toLocaleString('en-US')} ریال</b> (${numberToWords(cLoan)} ریال) با احتساب <b>${cMonthlyRate}٪</b> سود دوره مجموعا به مبلغ <b>${total.toLocaleString('en-US')} ریال</b> به حروف <b>${numberToWords(total)} ریال</b> گردید.</p>

            <div class="section-title">ماده ۴ :</div>
            <p>مدت این قرارداد از تاریخ امضای آن لغایت <b><span dir="ltr">${cEndDate}</span></b> به صورت <b>${cInstCount} ماهه</b> تعیین می گردد.<br>
            (فی : <b>${cGoldRate.toLocaleString('en-US')} ریال</b> مبلغ هرقسط : <b>${instRial.toLocaleString('en-US')} ریال</b> به حروف <b>${numberToWords(instRial)} ریال</b>)</p>

            <div class="section-title">ماده ۵ :</div>
            <p>چناچه متقاضی در سررسید هر قسط نسبت به پرداخت با تأخیر یا عدم پرداخت قسط مبادرت نماید این زرگری کلیه مطالبات ناشی از این قرارداد را به دین حال تبدیل نموده و جرایم ناشی از تأخیر تادیه به مأخذ ۶٪+ از متقاضی دریافت خواهد نمود.</p>

            <div class="section-title">ماده ۶ :</div>
            <p>متقاضی متعهد می گردد در صورت تخطی از مفاد این قرارداد به هر عنوان عین وثیقه دریافت شده بدون مراجعه به ضامن و مراجع قضائی به دین حال تبدیل شده و قرارداد فوق به صورت یک طرفه فسخ و کلیه مطالبات این قرارداد با جرایم مربوطه به مأخذ ۶٪+ از عین وثیقه با نرخ روز طلا کسر گردد و باقی مانده وثیقه مربوطه به هر شکل ممکن (طلای متفرقه یا آبشده) در حساب بستانکاران موقت طلا حفظ خواهد شد.</p>

            <div class="section-title">ماده ۷ :</div>
            <p>به منظور تضمین حسن انجام تعهدات وام گیرنده / ضامن وثایق مشروحه ذیل را در تاریخ <b><span dir="ltr">${cDate}</span></b> به زرگری ثنا تحویل داد و مقرر شد عین وثایق ضبط شده نزد زرگری ثنا تا فسخ قرارداد مربوطه به رسم امانت باقی بماند.<br>
            مشخصات وثیقه / چک : <b>${cCollateral}</b></p>
            
            <table class="data-table">
                <thead><tr><th>ردیف</th><th>تاریخ سررسید</th><th>مبلغ قسط</th><th>ردیف</th><th>تاریخ سررسید</th><th>مبلغ قسط</th><th>ردیف</th><th>تاریخ سررسید</th><th>مبلغ قسط</th></tr></thead>
                <tbody>${instTable}</tbody>
            </table>

            <p><b>تبصره:</b> زرگری ثنا متعهد می گردد عین وثیقه موصوف در ماده ۷ را پس از تسویه کامل قرارداد فوق به صاحب آن متعهد/ ضامن مسترد نماید.<br>
            نام و نام خانوادگی وثیقه گذار : <b>${gName}</b> &nbsp;&nbsp;&nbsp; کد ملی : <b>${gNational}</b> &nbsp;&nbsp;&nbsp; فرزند : <b>${gFather}</b><br>
            آدرس : ${gAddress} &nbsp;&nbsp;&nbsp; تلفن ثابت: — &nbsp;&nbsp;&nbsp; تلفن همراه : ${gMobile}</p>

            <div class="section-title">ماده ۸ :</div>
            <p>این قرارداد در۸ ماده ویک تبصره و در ۲ نسخه تنظیم که هر دو حکم واحد را دارند ، به رویت کامل زرگری ثنا ،متقاضی ، ضامن و وثیقه گذار رسید و ایشان ضمن اقرار به اطلاع و آگاهی کامل از مفاد این قرارداد و پذیرش آن به امضای تمامی صفحات آن مبادرت نمودند و یک نسخه از این قرارداد به هریک از آنها تسلیم گردید.</p>
            
            <div style="margin: 4mm 0; padding: 3mm; line-height: 2;">
                شرکت / اینجانب <b>${cName}</b> کد ملی <b>${cNational}</b> تعهد می نمایم در سررسید اقساط مندرج در قرارداد شماره <b>${cNumber}</b> تاریخ <b><span dir="ltr">${cDate}</span></b> را بپردازم و در صورت تأخیر در تأدیه بدهی جرایم ناشی از آن را بدون هیچ گونه عذر و بهانه ای پرداخت نمایم.<br>
                <div style="text-align: left; margin-top: 2mm;"><b>امضاء و اثر انگشت متعهد</b></div>
            </div>

            <table class="signatures">
                <tr>
                    <td>زرگری ثنا<br><br><br>امضاء و مهر</td>
                    <td>متقاضی<br><br><br>امضاء و مهر</td>
                    <td>ضامن / وثیقه گذار<br><br><br>امضاء و مهر</td>
                </tr>
            </table>
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