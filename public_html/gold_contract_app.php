<?php
define('SECURE_ACCESS', true);
require_once 'includes/config.php';
require_once 'includes/auth.php';
requireLogin();

// بارگذاری JDF برای تاریخ شمسی
require_once 'includes/jdf.php';
$today_gregorian = date('Y/m/d');
// explode برای jdf نیاز به Y, m, d جداگانه دارد
list($gy, $gm, $gd) = explode('/', $today_gregorian);
$today_jalali = jdate('Y/m/d', strtotime("$gy-$gm-$gd"));

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
    <title>قرارداد آتیه طلا | تراز روزانه</title>
    <link href="assets/fonts/fonts.css" rel="stylesheet">
    <link href="assets/css/light-theme.css" rel="stylesheet">
    <link rel="icon" href="assets/images/logo.png">
    <style>
        :root {
            --bg: #0a0f1a; --surface: rgba(255,255,255,0.03);
            --border: rgba(255,255,255,0.06); --text: #e8ecf1;
            --text-secondary: #8899aa; --accent: #4b8cf7;
            --gold: #d4af37; --gold-light: #ffd700;
            --radius: 14px; --radius-sm: 8px;
        }
        body.light {
            --bg: #f5f6f8; --surface: #ffffff; --border: #e0e3e8;
            --text: #1a1f2e; --text-secondary: #555f6e;
            --accent: #3b6fd4; --gold: #6b5500; --gold-light: ##6b5500;
        }
        body.light .card { box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
        body.light .header { box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
        body.light input, body.light textarea, body.light select { background: #f6f8fa; border-color: #d0d7de; color: #1a1f2e; }
        
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Vazirmatn', sans-serif;
            background: var(--bg); color: var(--text);
            min-height: 100vh; padding: 14px;
        }
        .container { max-width: 900px; margin: 0 auto; display: flex; flex-direction: column; gap: 12px; }
        
        .header {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: var(--radius); padding: 14px 20px;
            display: flex; align-items: center; justify-content: space-between;
            backdrop-filter: blur(12px); flex-wrap: wrap; gap: 10px;
        }
        .header h2 { font-size: 1rem; font-weight: 700; display: flex; align-items: center; gap: 8px; }
        .header-actions { display: flex; gap: 8px; align-items: center; }
        .back-btn { color: var(--text-secondary); text-decoration: none; font-size: 0.75rem; transition: color 0.2s; }
        .back-btn:hover { color: var(--accent); }
        .theme-btn {
            background: var(--surface); border: 1px solid var(--border);
            color: var(--text); padding: 7px 14px; border-radius: 8px;
            cursor: pointer; font-family: 'Vazirmatn'; font-size: 0.75rem;
            transition: all 0.2s;
        }
        .theme-btn:hover { border-color: var(--gold); color: var(--gold-light); }
        
        .card {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: var(--radius); padding: 20px; backdrop-filter: blur(10px);
            transition: border-color 0.2s;
        }
        .card:hover { border-color: rgba(75,140,247,0.2); }
        .card-title { 
            font-weight: 700; margin-bottom: 14px; font-size: 0.85rem; 
            color: var(--gold-light); display: flex; align-items: center; gap: 6px;
            padding-bottom: 10px; border-bottom: 1px solid var(--border);
        }
        
        .form-row { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; }
        .form-row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .form-row-4 { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 12px; }
        @media (max-width: 700px) { .form-row, .form-row-2, .form-row-4 { grid-template-columns: 1fr; } }
        
        label { 
            display: block; margin-top: 10px; font-weight: 600; 
            color: var(--text-secondary); font-size: 0.7rem; letter-spacing: -0.2px;
        }
        input, textarea, select {
            width: 100%; padding: 10px 12px; margin-top: 4px;
            background: rgba(255,255,255,0.03); border: 1px solid var(--border);
            border-radius: var(--radius-sm); color: var(--text);
            font-family: 'Vazirmatn', sans-serif; font-size: 0.8rem;
            transition: all 0.2s;
        }
        input:focus, textarea:focus, select:focus { border-color: var(--accent); outline: none; box-shadow: 0 0 0 3px rgba(75,140,247,0.1); }
        select { cursor: pointer; appearance: none; background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%238899aa' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e"); background-repeat: no-repeat; background-position: left 10px center; background-size: 14px; padding-left: 32px; }
        select option { background: #1a1f2e; color: #e8ecf1; }
        body.light select option { background: #fff; color: #1a1f2e; }
        
        .gold-badge {
            display: inline-block; padding: 6px 14px; border-radius: 20px;
            font-size: 0.7rem; font-weight: 700;
            background: linear-gradient(135deg, rgba(212,175,55,0.15), rgba(255,215,0,0.08));
            border: 1px solid rgba(212,175,55,0.3); color: var(--gold-light);
        }
        
        .btn-print {
            margin-top: 12px; padding: 14px; width: 100%;
            background: linear-gradient(135deg, #d4af37, #b8960f);
            color: #1a1a1a; border: none; border-radius: var(--radius-sm);
            cursor: pointer; font-family: 'Vazirmatn', sans-serif;
            font-weight: 700; font-size: 0.9rem; transition: all 0.3s;
            letter-spacing: 1px;
        }
        .btn-print:hover { 
            background: linear-gradient(135deg, #e0be4a, #c9a418); 
            transform: translateY(-1px); 
            box-shadow: 0 4px 15px rgba(212,175,55,0.3);
        }
        .print-hint { text-align: center; color: var(--text-secondary); font-size: 0.7rem; margin-top: 6px; }
        
        .shop-info {
            background: rgba(255,255,255,0.02);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 12px; margin-top: 10px; font-size: 0.68rem;
            color: var(--text-secondary);
            line-height: 1.8;
        }
        .shop-info strong { color: var(--text); }
    </style>
</head>
<body>
<div class="container">
    
    <div class="header">
        <h2>📜 <span>قرارداد افتتاح حساب پس‌انداز آتیه طلا</span></h2>
        <div class="header-actions">
            <button class="theme-btn" onclick="toggleTheme()" id="themeBtn">🌙 تیره</button>
            <a href="index.php" class="back-btn">← بازگشت</a>
        </div>
    </div>
    
    <!-- بخش ۱: اطلاعات صاحب حساب -->
    <div class="card">
        <div class="card-title">👤 اطلاعات صاحب حساب (افتتاح‌کننده)</div>
        <div class="form-row">
            <div>
                <label>جنسیت</label>
                <select id="ownerGender">
                    <option value="آقای">آقای</option>
                    <option value="خانم">خانم</option>
                </select>
            </div>
            <div><label>نام و نام خانوادگی</label><input type="text" id="ownerName" placeholder="محمدباقر حسین زاده"></div>
            <div><label>نام پدر</label><input type="text" id="ownerFather" placeholder="نام پدر"></div>
        </div>
        <div class="form-row">
            <div><label>کد ملی</label><input type="text" id="ownerNational" placeholder="۶۰۴۹۶۸۰۴۶۹"></div>
            <div><label>صادره از</label><input type="text" id="ownerIssuePlace" placeholder="مثلاً: تبریز"></div>
        </div>
        <div class="form-row">
            <div><label>تلفن ثابت</label><input type="text" id="ownerPhone" placeholder="۰۴۱-xxxxxxxx"></div>
            <div><label>تلفن همراه</label><input type="text" id="ownerMobile" placeholder="۰۹۱۴xxxxxxx"></div>
        </div>
        <div><label>آدرس کامل</label><textarea id="ownerAddress" rows="2" placeholder="تبریز، مرزداران، ۱۶ متری راجی تبریزی، قطعه ۸۴۴"></textarea></div>
    </div>
    
    <!-- بخش ۲: اطلاعات وکیل/ولی/قیم -->
    <div class="card">
        <div class="card-title">👤 اطلاعات ولی / قیم / وکیل (برداشت در غیاب صاحب حساب)</div>
        <div class="form-row">
            <div>
                <label>نسبت</label>
                <select id="agentRelation">
                    <option value="ولی">ولی</option>
                    <option value="قیم">قیم</option>
                    <option value="وکیل">وکیل</option>
                </select>
            </div>
            <div>
                <label>جنسیت</label>
                <select id="agentGender">
                    <option value="آقای">آقای</option>
                    <option value="خانم">خانم</option>
                </select>
            </div>
            <div><label>نام و نام خانوادگی</label><input type="text" id="agentName" placeholder="نجیبه موذن"></div>
        </div>
        <div class="form-row">
            <div><label>کد ملی</label><input type="text" id="agentNational" placeholder="۶۰۴۹۶۴۸۹۱۳"></div>
            <div><label>تلفن ثابت</label><input type="text" id="agentPhone" placeholder="۰۴۱-xxxxxxxx"></div>
            <div><label>تلفن همراه</label><input type="text" id="agentMobile" placeholder="۰۹۱۴xxxxxxx"></div>
        </div>
        <div><label>آدرس</label><textarea id="agentAddress" rows="2" placeholder="آدرس ولی/قیم/وکیل"></textarea></div>
    </div>
    
    <!-- بخش ۳: اطلاعات طلا و پرداخت -->
    <div class="card">
        <div class="card-title">💰 اطلاعات طلا و نحوه پرداخت</div>
        <div class="form-row">
            <div><label>مقدار طلای آبشده (گرم)</label><input type="text" id="goldWeight" placeholder="۱۶.۴۹۲"></div>
            <div><label>نرخ طلا (ریال/گرم)</label><input type="text" id="goldRate" placeholder="۱۸۲,۰۰۰,۰۰۰" oninput="formatNum(this)"></div>
            <div><label>مبلغ معادل (ریال)</label><input type="text" id="goldAmount" placeholder="۳,۰۰۰,۰۰۰,۰۰۰" oninput="formatNum(this)"></div>
        </div>
        <div class="form-row">
            <div>
                <label>نحوه پرداخت</label>
                <select id="paymentMethod">
                    <option value="کارتخوان">واریز وجه (کارتخوان زرگری ثنا)</option>
                    <option value="نقد">پرداخت نقدی</option>
                    <option value="طلای متفرقه">تحویل طلای متفرقه</option>
                    <option value="حواله طلا">حواله طلا</option>
                </select>
            </div>
            <div><label>شماره پیگیری / سریال کارتخوان</label><input type="text" id="paymentRef" placeholder="شماره پیگیری"></div>
            <div><label>شماره حساب مقصد</label><input type="text" id="bankAccount" placeholder="IR-.........................."></div>
        </div>
        <div class="form-row">
            <div><label>تاریخ تنظیم قرارداد</label><input type="text" id="contractDate" value="<?php echo $today_jalali; ?>"></div>
            <div><label>شماره قرارداد</label><input type="text" id="contractNumber" placeholder="شماره قرارداد"></div>
        </div>
    </div>
    
    <button class="btn-print" onclick="openPrintTab()">🖨️ پیش‌نمایش و چاپ قرارداد رسمی</button>
    <p class="print-hint">💡 فرمت خروجی مناسب پرینت A4 – در پیش‌نمایش می‌توانید تنظیمات چاپ را تغییر دهید</p>
    
    <div class="shop-info">
        <strong>📍 اطلاعات فروشگاه (درج شده در قرارداد):</strong><br>
        شعبه ۱: تبریز، چهارراه ابوریحان، پاساژ ایران، همکف، پلاک ۱۵ – تلفن: ۰۴۱۳۴۷۸۲۳۷۳<br>
        شعبه ۲: تبریز، چهارراه طالقانی، روبروی مسجد عربلر، پلاک ۸۴۸ – تلفن: ۰۴۱۳۵۴۰۶۴۸۶<br>
        شعبه ۳: تبریز، آبرسان، برج سفید، پلاک ۲۱ – تلفن: ۰۴۱۳۳۳۴۷۷۰۹
    </div>
    
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

// ========== ابزارها ==========
function toEnglishNum(str) {
    return str.replace(/[۰-۹]/g, function(d) { return '۰۱۲۳۴۵۶۷۸۹'.indexOf(d); });
}
function fmtNum(n) { return Number(n||0).toLocaleString('fa-IR'); }
function parseNum(s) { return parseFloat(toEnglishNum(String(s||'')).replace(/,/g, '')) || 0; }
function formatNum(el) {
    let raw = toEnglishNum(el.value).replace(/,/g, '').replace(/[^0-9\.]/g, '');
    if (raw) {
        let parts = raw.split('.');
        el.value = parts.length === 2 
            ? Number(parts[0]).toLocaleString('en-US') + '.' + parts[1]
            : Number(raw).toLocaleString('en-US');
    }
}
function dash(val) { return (val && val.trim()) ? val.trim() : '.............................'; }

// ========== پیش‌نمایش رسمی ==========
function openPrintPreview(htmlContent) {
    localStorage.setItem('printContent', htmlContent);
    window.open('print/', '_blank');
}

function openPrintTab() {
    let oGender = document.getElementById('ownerGender').value;
    let oName = dash(document.getElementById('ownerName').value);
    let oFather = dash(document.getElementById('ownerFather').value);
    let oNational = dash(document.getElementById('ownerNational').value);
    let oIssuePlace = dash(document.getElementById('ownerIssuePlace').value);
    let oAddress = dash(document.getElementById('ownerAddress').value);
    let oPhone = dash(document.getElementById('ownerPhone').value);
    let oMobile = dash(document.getElementById('ownerMobile').value);
    
    let aRelation = document.getElementById('agentRelation').value;
    let aGender = document.getElementById('agentGender').value;
    let aName = dash(document.getElementById('agentName').value);
    let aNational = dash(document.getElementById('agentNational').value);
    let aPhone = dash(document.getElementById('agentPhone').value);
    let aMobile = dash(document.getElementById('agentMobile').value);
    let aAddress = dash(document.getElementById('agentAddress').value);
    
    let goldW = dash(document.getElementById('goldWeight').value);
    let goldR = dash(document.getElementById('goldRate').value);
    let goldA = dash(document.getElementById('goldAmount').value);
    let payMethod = document.getElementById('paymentMethod').value;
    let payRef = dash(document.getElementById('paymentRef').value);
    let bankAcc = dash(document.getElementById('bankAccount').value);
    let cDate = dash(document.getElementById('contractDate').value);
    let cNum = dash(document.getElementById('contractNumber').value);
    
    // متن بند اول بر اساس نحوه پرداخت
    let b1_main = '';
    if (payMethod === 'کارتخوان') b1_main = 'واریز وجه به حساب شماره ' + bankAcc + ' (دریافت طلای آبشده)';
    else if (payMethod === 'نقد') b1_main = 'پرداخت نقدی به زرگری ثنا';
    else if (payMethod === 'طلای متفرقه') b1_main = 'تحویل طلای متفرقه به زرگری ثنا';
    else if (payMethod === 'حواله طلا') b1_main = 'تحویل حواله طلا به زرگری ثنا';
    
    let b1_detail = '';
    if (payMethod === 'کارتخوان') b1_detail = 'از طریق کارتخوان زرگری ثنا به شماره پیگیری ' + payRef;
    else if (payMethod === 'نقد') b1_detail = 'وجه نقد دریافت گردید';
    else if (payMethod === 'طلای متفرقه') b1_detail = 'طلای متفرقه تحویل و دریافت گردید';
    else if (payMethod === 'حواله طلا') b1_detail = 'حواله طلا به شماره ' + payRef + ' دریافت گردید';
    
    let clean = oName.replace(/\s+/g, '_').replace(/[^\u0600-\u06FF\w]/g, '') || 'قرارداد';
    
    let html = `<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>قرارداد آتیه طلا - ${oName}</title>
    <link href="${window.location.origin}/~tarazroz/assets/fonts/fonts.css" rel="stylesheet">
    <style>
        @page { size: A4; margin: 12mm 14mm; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Vazirmatn', sans-serif; color: #1a1a1a;
            direction: rtl; background: #fff; font-size: 9.5pt;
            line-height: 1.8; padding: 8mm; max-width: 190mm; margin: 0 auto;
        }
        .top-bar {
            display: flex; justify-content: space-between; align-items: center;
            border-bottom: 2px solid #6b5500; padding-bottom: 3mm; margin-bottom: 5mm;
            position: relative;
        }
        .top-bar .right { text-align: right; }
        .top-bar .left { text-align: left; font-size: 8pt; color: #555; }
        .logo { font-weight: bold; font-size: 14pt; color: #6b5500; letter-spacing: 2px; }
        .bismillah {
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            top: 50%;
            margin-top: -12px;
            font-family: 'Vazirmatn', sans-serif;
            font-size: 10pt;
            font-weight: bold;
            color: #8b6914;
            border: 2px dashed #b8960f;
            padding: 4px 18px;
            background: #fef9e7;
            white-space: nowrap;
        }
        .title {
            text-align: center; font-size: 11pt; font-weight: bold;
            margin: 6mm 0; padding: 2mm; border: 1px solid #b8960f;
            background: #fef9e7; letter-spacing: 1px;
        }
        .content { text-align: justify; }
        .content p { margin-bottom: 1.5mm; }
        .highlight { background: #fef9e7; padding: 1mm 3mm; border-radius: 2px; font-weight: bold; }
        .section-label { font-weight: bold; color: #8b6914; margin-top: 3mm; }
        .dots { color: #999; }
        .signatures {
            display: flex; justify-content: space-around; margin-top: 12mm;
            border-top: 1px solid #ccc; padding-top: 5mm;
        }
        .sig-box { text-align: center; flex: 1; }
        .sig-box .line { 
            display: block; width: 120px; height: 1px; 
            background: #000; margin: 12mm auto 2mm; 
        }
        .sig-box .label { font-size: 8pt; font-weight: bold; }
        .footer {
            text-align: center; margin-top: 6mm; font-size: 8pt;
            color: #555; border-top: 1px solid #eee; padding-top: 3mm;
        }
    </style>
</head>
<body>

<div class="top-bar">
    <div class="right">
        <div class="logo">زرگری ثنا</div>
        <div style="font-size:7pt;color:#666;">شعبه ۱: چهارراه ابوریحان، پاساژ ایران، پلاک ۱۵</div>
        <div style="font-size:7pt;color:#666;">شعبه ۲: چهارراه طالقانی، روبروی مسجد عربلر، پلاک ۸۴۸</div>
        <div style="font-size:7pt;color:#666;">شعبه ۳: آبرسان، برج سفید، پلاک ۲۱</div>
    </div>
    <div class="bismillah">بسمه تعالی</div>
    <div class="left">
        <div>تاریخ: <strong>${cDate}</strong></div>
        <div>شماره: <strong>${cNum}</strong></div>
    </div>
</div>

<div class="title">قرارداد افتتاح حساب پس‌انداز آتیه طلا</div>

<div class="content">
    <p>این قرارداد بین <strong>${oGender} ${oName}</strong> فرزند <strong>${oFather}</strong> به شماره ملی <strong>${oNational}</strong> صادره از <strong>${oIssuePlace}</strong>، تلفن ثابت: <strong>${oPhone}</strong>، تلفن همراه: <strong>${oMobile}</strong> به آدرس <strong>${oAddress}</strong> که از این پس «<strong>افتتاح‌کننده حساب</strong>» نامیده می‌شود،</p>
    
    <p>و از طرف دیگر <strong>زرگری ثنا</strong> به مدیریت <strong>آقای امیر فرشباف قیداری نژاد</strong> به شماره ملی <strong>۱۸۱۷۱۷۹۷۸۰</strong> به آدرس: <strong>تبریز، چهارراه ابوریحان، پاساژ ایران، همکف، پلاک ۱۵</strong> که از این پس «<strong>پذیرنده</strong>» نامیده می‌شود،</p>
    
    <p>با شرایط و مفاد ذیل منعقد می‌گردد:</p>
    
    <p class="section-label">بند اول – موضوع قرارداد</p>
    <p>${oGender} <strong>${oName}</strong> به میزان <span class="highlight">${goldW} گرم</span> طلای آبشده با نرخ <span class="highlight">${goldR} ریال/گرم</span> معادل <span class="highlight">${goldA} ریال</span> از طریق <strong>${b1_main}</strong> به پذیرنده تحویل می‌نماید. ${b1_detail}.</p>
    
    <p class="section-label">بند دوم – تأیید دریافت</p>
    <p>زرگری ثنا (پذیرنده) دریافت طلای آبشده موضوع بند اول را تأیید و به شرح این قرارداد متعهد به استرداد آن می‌گردد.</p>
    
    <p class="section-label">بند سوم – حق برداشت و فسخ</p>
    <p>حق برداشت یا فسخ حساب <strong>صرفاً</strong> با افتتاح‌کننده حساب، ${oGender} <strong>${oName}</strong> به کد ملی <strong>${oNational}</strong> می‌باشد.</p>
    
    <p><strong>تبصره:</strong> در غیاب صاحب حساب، ${aGender} <strong>${aName}</strong> به کد ملی <strong>${aNational}</strong> به عنوان <strong>${aRelation}</strong> صاحب حساب، با آدرس <strong>${aAddress}</strong> و تلفن ثابت <strong>${aPhone}</strong> و تلفن همراه <strong>${aMobile}</strong> حق برداشت یا فسخ قرارداد را دارد. <em>(امضا و اثرانگشت موکل الزامی است)</em></p>
    
    <p class="section-label">بند چهارم – تعهدات پذیرنده</p>
    <p>زرگری ثنا به مدیریت آقای امیر فرشباف متعهد می‌گردد به محض درخواست صاحب حساب یا ${aRelation} ایشان، نسبت به پرداخت موجودی طلای آبشده به هر میزان و به صورت <strong>طلا</strong>، <strong>ارز</strong> یا <strong>ریال</strong> به حساب ایشان واریز یا حواله نماید.</p>
    
    <p><strong>تبصره:</strong> صاحب حساب در وقت بازار قادر به خرید و فروش از حساب آتیه طلا می‌باشد.</p>
</div>

<div class="signatures">
    <div class="sig-box">
        <div class="label">امضاء و اثر انگشت<br>افتتاح‌کننده حساب</div>
        <div class="line"></div>
        <div style="font-size:7pt;">${oName}</div>
    </div>
    <div class="sig-box">
        <div class="label">امضاء و اثر انگشت<br>${aRelation} (${aName})</div>
        <div class="line"></div>
        <div style="font-size:7pt;">${aName}</div>
    </div>
    <div class="sig-box">
        <div class="label">مهر و امضاء<br>مدیریت زرگری ثنا / مباشر</div>
        <div class="line"></div>
        <div style="font-size:7pt;">امیر فرشباف قیداری نژاد</div>
    </div>
</div>

<div class="footer">
    این قرارداد در تاریخ <strong>${cDate}</strong> در دو نسخه متحدالمتن تنظیم و امضا گردید و هر دو نسخه دارای اعتبار واحد می‌باشد.<br>
    <strong>مدارک مورد نیاز:</strong> اصل کارت ملی افتتاح‌کننده حساب و ${aRelation} – اصل شناسنامه
</div>

</body>
</html>`;
    
    openPrintPreview(html);
}
</script>
</body>
</html>