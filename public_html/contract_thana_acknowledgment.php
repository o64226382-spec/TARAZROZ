<?php
define('SECURE_ACCESS', true);
require_once 'includes/config.php';
require_once 'includes/auth.php';
requireLogin();

// بارگذاری JDF برای تاریخ شمسی
require_once 'includes/jdf.php';
$today_gregorian = date('Y/m/d');
list($gy, $gm, $gd) = explode('/', $today_gregorian);
$today_jalali = jdate('Y/m/d', strtotime("$gy-$gm-$gd"));

$user_id = intval($_SESSION['user_id']);
$perm_query = "SELECT permissions FROM users WHERE id = $user_id";
$perm_result = mysqli_query($conn, $perm_query);
$permissions = '';
if ($perm_result && mysqli_num_rows($perm_result) > 0) {
    $permissions = mysqli_fetch_assoc($perm_result)['permissions'];
}
if (strpos($permissions, 'contract_acknowledgment') === false && $_SESSION['role'] !== 'admin') {
die('<div style="text-align:center;padding:60px;font-family:Vazirmatn;background:#0a0f1a;color:#e8ecf1;">
⛔ شما دسترسی به این بخش را ندارید.<br><br>
<a href="#" onclick="if(window.history.length>1&&document.referrer!==\'\'){window.history.back();}else{window.location.href=\'index.php\';} return false;" style="color:#4b8cf7;text-decoration:none;padding:10px 20px;border:1px solid #4b8cf7;border-radius:8px;">🔙 بازگشت به صفحه قبلی</a>
</div>');}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>اقرارنامه و تعهدنامه مشتری | تراز روزانه</title>
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
        <h2>اقرارنامه و تعهدنامه مشتری معاملات آنلاین</h2>
        <div class="header-actions">
            <button class="btn-outline" onclick="toggleTheme()" id="themeBtn">حالت تیره</button>
            <a href="index.php" class="btn-outline">بازگشت</a>
        </div>
    </div>
    
    <div class="card">
        <div class="card-title">مشخصات مشتری (مقرّ)</div>
        <div class="form-row">
            <div><label>نام و نام خانوادگی</label><input type="text" id="cName" placeholder="نام کامل"></div>
            <div><label>کد ملی</label><input type="text" id="cNational" placeholder="کد ملی ۱۰ رقمی"></div>
            <div><label>تاریخ تنظیم</label><input type="text" id="cDate" value="<?php echo $today_jalali; ?>"></div>
        </div>
        <div class="form-row">
            <div><label>تلفن ثابت</label><input type="text" id="cPhone" placeholder="پیش‌شماره + تلفن"></div>
            <div><label>تلفن همراه</label><input type="text" id="cMobile" placeholder="۰۹۱۲۳۴۵۶۷۸۹"></div>
            <div></div>
        </div>
        <div><label>نشانی دقیق اقامتگاه</label><textarea id="cAddress" rows="2" placeholder="استان، شهر، خیابان، کوچه، پلاک..."></textarea></div>
    </div>

    <div class="card">
        <div class="card-title">مواد اقرارنامه (جهت استحضار)</div>
        <div style="font-size:0.8rem; color: var(--text-secondary); line-height: 2;">
            <p><b>۱- صحت اطلاعات:</b> کلیه اطلاعات ارائه‌شده صحیح و متعلق به اینجانب است.</p>
            <p><b>۲- مالکیت حساب بانکی:</b> پرداخت‌ها فقط از حساب‌های بانکی خودم انجام می‌شود.</p>
            <p><b>۳- منبع قانونی وجوه:</b> وجوه مورد استفاده دارای منشأ قانونی است.</p>
            <p><b>۴- آگاهی از نوسانات قیمت:</b> مسئولیت سود یا زیان نوسانات قیمت پس از معامله با من است.</p>
            <p><b>۵- قطعی بودن معامله:</b> پس از تأیید پرداخت، معامله قطعی و غیرقابل فسخ است.</p>
            <p><b>۶- نگهداری امانی:</b> مالکیت طلای امانی نزد فروشنده متعلق به اینجانب است.</p>
            <p><b>۷- تحویل فیزیکی:</b> ارائه مدارک شناسایی برای تحویل فیزیکی الزامی است.</p>
            <p><b>۸- رعایت قوانین:</b> متعهد به رعایت قوانین معاملات طلا و مبارزه با پولشویی هستم.</p>
            <p><b>۹- حل اختلاف:</b> در صورت اختلاف، مرجع صالح قضایی محل استقرار فروشنده است.</p>
        </div>
    </div>
    
    <button class="btn-print" onclick="openPrintTab()">صدور و چاپ اقرارنامه رسمی</button>
    
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

function openPrintPreview(htmlContent) {
    localStorage.setItem('printContent', htmlContent);
    window.open('print/', '_blank');
}

function openPrintTab() {
    let cName = document.getElementById('cName').value || '............................';
    let cNational = document.getElementById('cNational').value || '............................';
    let cDate = document.getElementById('cDate').value || '............................';
    let cPhone = document.getElementById('cPhone').value || '............................';
    let cMobile = document.getElementById('cMobile').value || '............................';
    let cAddress = document.getElementById('cAddress').value || '............................';
    
    let cleanName = cName.replace(/\s+/g, '_').replace(/[^آ-یa-zA-Z0-9_]/g, '') || 'مشتری';
    let fileName = 'اقرارنامه_' + cleanName;
    
    let html = `<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>${fileName}</title>
    <link href="${window.location.origin}/~tarazroz/assets/fonts/fonts.css" rel="stylesheet">
    <style>
        @page { size: A4; margin: 0; }
        body { font-family: 'Vazirmatn', Tahoma, sans-serif; color: #000; background: #fff; direction: rtl; font-size: 9pt; line-height: 1.8; margin: 0; padding: 0; }
        
        .page-container {
            width: 190mm; min-height: 277mm; margin: 8mm auto;
            border: 1px solid #000; padding: 8mm; box-sizing: border-box;
            position: relative;
        }
        
        .print-header { text-align: center; margin-bottom: 5mm; }
        .print-header h1 { font-size: 12pt; margin: 0; }
        .print-header h2 { font-size: 10pt; margin: 2mm 0 5mm 0; border-bottom: 1px solid #000; padding-bottom: 2mm; }
        
        .customer-info { margin-bottom: 6mm; }
        .customer-info p { margin: 1mm 0; }
        
        ol { padding-right: 15px; }
        ol li { margin-bottom: 2mm; }
        
        .signature-section {
            margin-top: 10mm;
            display: flex;
            justify-content: space-between;
        }
        .signature-box {
            width: 45%;
            text-align: center;
        }
        .signature-line {
            border-top: 1px solid #000;
            margin-top: 15mm;
            padding-top: 2mm;
        }
        
        .date-section {
            margin-top: 8mm;
            text-align: left;
        }
    </style>
</head>
<body>
    <div class="page-container">
        <div class="print-header">
            <h1>بسمه تعالی</h1>
            <h2>اقرارنامه و تعهدنامه مشتری معاملات آنلاین طلا و سکه</h2>
        </div>
        
        <div class="customer-info">
            <p>اینجانب: <b>${cName}</b></p>
            <p>به کد ملی: <b>${cNational}</b></p>
            <p>با شماره تلفن ثابت: <b>${cPhone}</b></p>
            <p>با شماره تلفن همراه: <b>${cMobile}</b></p>
            <p>به نشانی: <b>${cAddress}</b></p>
            <p>با آگاهی کامل و در عین صحت و سلامتی، موارد زیر را اقرار و تعهد می‌نمایم:</p>
        </div>
        
        <ol>
            <li><b>صحت اطلاعات:</b> کلیه اطلاعات هویتی، بانکی و ارتباطی ارائه‌شده توسط اینجانب صحیح، کامل و متعلق به خودم است و مسئولیت هرگونه مغایرت بر عهده اینجانب خواهد بود.</li>
            <li><b>مالکیت حساب بانکی:</b> تمامی پرداخت‌های انجام‌شده جهت خرید طلا از حساب‌های بانکی متعلق به اینجانب صورت می‌گیرد و از حساب اشخاص ثالث استفاده نخواهم کرد.</li>
            <li><b>منبع قانونی وجوه:</b> اقرار می‌کنم وجوه مورد استفاده برای خرید طلا و سکه دارای منشأ قانونی بوده و حاصل فعالیت‌های مجرمانه، پولشویی یا تأمین مالی غیرقانونی نیست.</li>
            <li><b>آگاهی از نوسانات قیمت:</b> آگاه هستم که قیمت طلا به‌صورت لحظه‌ای تغییر می‌کند و مسئولیت سود یا زیان ناشی از نوسانات قیمت پس از انجام معامله بر عهده اینجانب است.</li>
            <li><b>قطعی بودن معامله:</b> پس از ثبت نهایی سفارش و تأیید پرداخت، معامله قطعی بوده و صرف تغییر قیمت بازار موجب فسخ یا ابطال معامله نخواهد شد.</li>
            <li><b>نگهداری امانی:</b> در صورت انتخاب نگهداری امانی طلا و سکه نزد فروشنده، مالکیت طلا و سکه متعلق به اینجانب بوده و سوابق مربوطه در سامانه فروشنده ثبت خواهد شد.</li>
            <li><b>تحویل فیزیکی:</b> در زمان تحویل فیزیکی طلا و سکه ارائه مدارک شناسایی معتبر و انجام تشریفات احراز هویت را می‌پذیرم.</li>
            <li><b>رعایت قوانین:</b> متعهد می‌شوم تمامی قوانین و مقررات مربوط به معاملات طلا و سکه و مقررات مبارزه با پولشویی را رعایت نمایم.</li>
            <li><b>حل اختلاف:</b> در صورت بروز اختلاف، ابتدا موضوع از طریق مذاکره حل‌وفصل شده و در صورت عدم توافق، مراجع قضایی صالح محل استقرار فروشنده مرجع رسیدگی خواهند بود.</li>
        </ol>
        
        <p style="margin-top: 5mm;">اینجانب با مطالعه کامل مفاد این اقرارنامه، تمامی موارد فوق را تأیید و قبول می‌نمایم.</p>
        
        <div class="signature-section">
            <div class="signature-box">
                <p>نام و نام خانوادگی مشتری:</p>
                <div class="signature-line">${cName}</div>
            </div>
            <div class="signature-box">
                <p>امضاء یا تأیید الکترونیکی:</p>
                <div class="signature-line"></div>
            </div>
        </div>
        
        <div class="date-section">
            <p><b>تاریخ:</b> ${cDate}</p>
        </div>
    </div>
</body>
</html>`;
    
    openPrintPreview(html);
}

document.getElementById('cDate').value = '<?php echo $today_jalali; ?>';
</script>
</body>
</html>