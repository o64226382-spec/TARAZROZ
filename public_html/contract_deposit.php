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
if (strpos($permissions, 'pre_invoice') === false && $_SESSION['role'] !== 'admin') {
    die('<div style="text-align:center;padding:60px;font-family:Vazirmatn;background:#0a0f1a;color:#e8ecf1;">⛔ شما دسترسی به این بخش را ندارید.<br><a href="index.php" style="color:#4b8cf7;">بازگشت</a></div>');
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>قرارداد افتتاح سپرده سرمایه‌گذاری طلا | تراز روزانه</title>
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
            --danger: #ef4444; --danger-bg: rgba(239,68,68,0.1);
        }
        body.light {
            --bg: #f5f6f8; --surface: #ffffff; --border: #e0e3e8;
            --text: #1a1f2e; --text-secondary: #555f6e;
            --accent: #3b6fd4; --gold: #6b5500; --gold-light: ##6b5500;
            --danger-bg: #fef2f2;
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
        .required-star {
            color: var(--danger);
            margin-right: 3px;
            font-weight: bold;
        }
        input, textarea, select {
            width: 100%; padding: 10px 12px; margin-top: 4px;
            background: rgba(255,255,255,0.03); border: 1px solid var(--border);
            border-radius: var(--radius-sm); color: var(--text);
            font-family: 'Vazirmatn', sans-serif; font-size: 0.8rem;
            transition: all 0.2s;
        }
        input:focus, textarea:focus, select:focus { 
            border-color: var(--accent); 
            outline: none; 
            box-shadow: 0 0 0 3px rgba(75,140,247,0.1); 
        }
        input.error, textarea.error, select.error {
            border-color: var(--danger);
            box-shadow: 0 0 0 3px var(--danger-bg);
            animation: shake 0.3s ease-in-out;
        }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-3px); }
            75% { transform: translateX(3px); }
        }
        .error-message {
            color: var(--danger);
            font-size: 0.65rem;
            margin-top: 3px;
            display: none;
        }
        .error-message.show {
            display: block;
        }
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
            letter-spacing: 1px; position: relative; overflow: hidden;
        }
        .btn-print:hover:not(:disabled) { 
            background: linear-gradient(135deg, #e0be4a, #c9a418); 
            transform: translateY(-1px); 
            box-shadow: 0 4px 15px rgba(212,175,55,0.3);
        }
        .btn-print:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            background: linear-gradient(135deg, #888, #666);
        }
        .btn-print .required-count {
            display: block;
            font-size: 0.65rem;
            margin-top: 4px;
            opacity: 0.8;
            font-weight: normal;
        }
        .print-hint { text-align: center; color: var(--text-secondary); font-size: 0.7rem; margin-top: 6px; }
        
        .branch-info-display {
            background: rgba(212,175,55,0.08);
            border: 1px solid rgba(212,175,55,0.2);
            border-radius: var(--radius-sm);
            padding: 10px; margin-top: 6px; font-size: 0.75rem;
            color: var(--gold-light);
        }

        .validation-summary {
            background: var(--danger-bg);
            border: 1px solid var(--danger);
            border-radius: var(--radius-sm);
            padding: 12px;
            margin-top: 8px;
            display: none;
            font-size: 0.75rem;
            color: var(--danger);
        }
        .validation-summary.show {
            display: block;
        }
        .validation-summary ul {
            margin: 6px 16px 0;
            list-style-type: disc;
        }
        .validation-summary li {
            margin-bottom: 2px;
        }
    </style>
</head>
<body>
<div class="container">
    
    <div class="header">
        <h2>📜 <span>قرارداد افتتاح حساب سپرده سرمایه‌گذاری طلا</span></h2>
        <div class="header-actions">
            <button class="theme-btn" onclick="toggleTheme()" id="themeBtn">🌙 تیره</button>
            <a href="index.php" class="back-btn">← بازگشت</a>
        </div>
    </div>
    
    <!-- بخش ۱: اطلاعات صاحب حساب -->
    <div class="card">
        <div class="card-title">👤 اطلاعات صاحب حساب (سپرده‌گذار)</div>
        <div class="form-row">
            <div>
                <label>جنسیت <span class="required-star">*</span></label>
                <select id="ownerGender" data-required="true">
                    <option value="">-- انتخاب کنید --</option>
                    <option value="آقای">آقای</option>
                    <option value="خانم">خانم</option>
                </select>
                <span class="error-message" id="ownerGender-error">این فیلد الزامی است</span>
            </div>
            <div><label>نام و نام خانوادگی <span class="required-star">*</span></label><input type="text" id="ownerName" placeholder="محمدباقر حسین زاده" data-required="true"><span class="error-message" id="ownerName-error">این فیلد الزامی است</span></div>
            <div><label>نام پدر <span class="required-star">*</span></label><input type="text" id="ownerFather" placeholder="نام پدر" data-required="true"><span class="error-message" id="ownerFather-error">این فیلد الزامی است</span></div>
        </div>
        <div class="form-row">
            <div><label>کد ملی <span class="required-star">*</span></label><input type="text" id="ownerNational" placeholder="۶۰۴۹۶۸۰۴۶۹" data-required="true"><span class="error-message" id="ownerNational-error">این فیلد الزامی است</span></div>
            <div><label>صادره از <span class="required-star">*</span></label><input type="text" id="ownerIssuePlace" placeholder="مثلاً: تبریز" data-required="true"><span class="error-message" id="ownerIssuePlace-error">این فیلد الزامی است</span></div>
        </div>
        <div class="form-row">
            <div><label>تلفن‌ثابت</label><input type="text" id="ownerPhone" placeholder="۰۴۱-xxxxxxxx"></div>
            <div><label>تلفن‌همراه <span class="required-star">*</span></label><input type="text" id="ownerMobile" placeholder="۰۹۱۴xxxxxxx" data-required="true"><span class="error-message" id="ownerMobile-error">این فیلد الزامی است</span></div>
        </div>
        <div><label>آدرس کامل <span class="required-star">*</span></label><textarea id="ownerAddress" rows="2" placeholder="تبریز، مرزداران، ۱۶ متری راجی تبریزی، قطعه ۸۴۴" data-required="true"></textarea><span class="error-message" id="ownerAddress-error">این فیلد الزامی است</span></div>
    </div>
    
    <!-- بخش ۲: اطلاعات وکیل/ولی/قیم -->
    <div class="card">
        <div class="card-title">👤 اطلاعات ولی / قیم / وکیل (برداشت در غیاب صاحب حساب)</div>
        <div class="form-row">
            <div>
                <label>نسبت <span class="required-star">*</span></label>
                <select id="agentRelation" data-required="true">
                    <option value="">-- انتخاب کنید --</option>
                    <option value="ولی">ولی</option>
                    <option value="قیم">قیم</option>
                    <option value="وکیل">وکیل</option>
                </select>
                <span class="error-message" id="agentRelation-error">این فیلد الزامی است</span>
            </div>
            <div>
                <label>جنسیت <span class="required-star">*</span></label>
                <select id="agentGender" data-required="true">
                    <option value="">-- انتخاب کنید --</option>
                    <option value="آقای">آقای</option>
                    <option value="خانم">خانم</option>
                </select>
                <span class="error-message" id="agentGender-error">این فیلد الزامی است</span>
            </div>
            <div><label>نام و نام خانوادگی <span class="required-star">*</span></label><input type="text" id="agentName" placeholder="نجیبه موذن" data-required="true"><span class="error-message" id="agentName-error">این فیلد الزامی است</span></div>
        </div>
        <div class="form-row">
            <div><label>کد ملی <span class="required-star">*</span></label><input type="text" id="agentNational" placeholder="۶۰۴۹۶۴۸۹۱۳" data-required="true"><span class="error-message" id="agentNational-error">این فیلد الزامی است</span></div>
            <div><label>تلفن‌ثابت</label><input type="text" id="agentPhone" placeholder="۰۴۱-xxxxxxxx"></div>
            <div><label>تلفن‌همراه <span class="required-star">*</span></label><input type="text" id="agentMobile" placeholder="۰۹۱۴xxxxxxx" data-required="true"><span class="error-message" id="agentMobile-error">این فیلد الزامی است</span></div>
        </div>
        <div><label>آدرس <span class="required-star">*</span></label><textarea id="agentAddress" rows="2" placeholder="آدرس ولی/قیم/وکیل" data-required="true"></textarea><span class="error-message" id="agentAddress-error">این فیلد الزامی است</span></div>
    </div>
    
    <!-- بخش ۳: اطلاعات طلا، دوره و پرداخت -->
    <div class="card">
        <div class="card-title">💰 اطلاعات سپرده و نحوه پرداخت</div>
        <div class="form-row">
            <div><label>مقدار طلای آبشده (گرم) <span class="required-star">*</span></label><input type="text" id="goldWeight" placeholder="۱۶.۴۹۲" data-required="true"><span class="error-message" id="goldWeight-error">این فیلد الزامی است</span></div>
            <div><label>نرخ طلا (ریال/گرم) <span class="required-star">*</span></label><input type="text" id="goldRate" placeholder="۱۸۲,۰۰۰,۰۰۰" oninput="formatNum(this)" data-required="true"><span class="error-message" id="goldRate-error">این فیلد الزامی است</span></div>
            <div><label>مبلغ معادل (ریال) <span class="required-star">*</span></label><input type="text" id="goldAmount" placeholder="۳,۰۰۰,۰۰۰,۰۰۰" oninput="formatNum(this)" data-required="true"><span class="error-message" id="goldAmount-error">این فیلد الزامی است</span></div>
        </div>
        <div class="form-row">
            <div>
                <label>دوره سپرده‌گذاری <span class="required-star">*</span></label>
                <select id="depositPeriod" onchange="updateProfitRate()" data-required="true">
                    <option value="">-- انتخاب کنید --</option>
                    <option value="یکساله">یکساله (سود ۸٪)</option>
                    <option value="شش ماهه">شش ماهه (سود ۶٪)</option>
                    <option value="سه ماهه">سه ماهه (سود ۴٪)</option>
                </select>
                <span class="error-message" id="depositPeriod-error">این فیلد الزامی است</span>
            </div>
            <div><label>نرخ سود</label><input type="text" id="profitRate" value="" readonly></div>
            <div><label>تاریخ شروع سپرده <span class="required-star">*</span></label><input type="text" id="startDate" value="<?php echo $today_jalali; ?>" data-required="true"><span class="error-message" id="startDate-error">این فیلد الزامی است</span></div>
        </div>
        <div class="form-row">
            <div>
                <label>نحوه پرداخت <span class="required-star">*</span></label>
                <select id="paymentMethod" data-required="true">
                    <option value="">-- انتخاب کنید --</option>
                    <option value="کارتخوان">واریز وجه (کارتخوان زرگری ثنا)</option>
                    <option value="نقد">پرداخت نقدی</option>
                    <option value="طلای متفرقه">تحویل طلای متفرقه</option>
                    <option value="حواله طلا">حواله طلا</option>
                </select>
                <span class="error-message" id="paymentMethod-error">این فیلد الزامی است</span>
            </div>
            <div><label>شماره پیگیری / سریال کارتخوان <span class="required-star">*</span></label><input type="text" id="paymentRef" placeholder="شماره پیگیری" data-required="true"><span class="error-message" id="paymentRef-error">این فیلد الزامی است</span></div>
            <div><label>شماره حساب مقصد</label><input type="text" id="bankAccount" placeholder="IR-.........................."></div>
        </div>
        <div class="form-row">
            <div><label>تاریخ تنظیم قرارداد <span class="required-star">*</span></label><input type="text" id="contractDate" value="<?php echo $today_jalali; ?>" data-required="true"><span class="error-message" id="contractDate-error">این فیلد الزامی است</span></div>
            <div><label>شماره قرارداد <span class="required-star">*</span></label><input type="text" id="contractNumber" placeholder="شماره قرارداد" data-required="true"><span class="error-message" id="contractNumber-error">این فیلد الزامی است</span></div>
        </div>
    </div>

    <!-- بخش ۴: اطلاعات شعبه و مدیر -->
    <div class="card">
        <div class="card-title">🏢 اطلاعات شعبه و مدیریت</div>
        <div class="form-row">
            <div>
                <label>انتخاب شعبه <span class="required-star">*</span></label>
                <select id="branchSelect" onchange="updateBranchInfo()" data-required="true">
                    <option value="">-- انتخاب کنید --</option>
                    <option value="1">شعبه ۱: چهار راه ابوریحان، پاساژ ایران، همکف، پلاک ۱۵</option>
                    <option value="2">شعبه ۲: چهار راه طالقانی، روبروی مسجد طالقانی، پلاک ۸۴۸</option>
                    <option value="3">شعبه ۳: آبرسان، برج سفید، همکف پلاک ۲۱</option>
                </select>
                <span class="error-message" id="branchSelect-error">این فیلد الزامی است</span>
            </div>
            <div>
                <label>نام مدیر / مباشر <span class="required-star">*</span></label>
                <input type="text" id="managerName" value="امیر فرشباف قیداری نژاد" placeholder="نام مدیر" data-required="true">
                <span class="error-message" id="managerName-error">این فیلد الزامی است</span>
            </div>
            <div>
                <label>کد ملی مدیر <span class="required-star">*</span></label>
                <input type="text" id="managerNational" value="۱۸۱۷۱۷۹۷۸۰" placeholder="کد ملی مدیر" data-required="true">
                <span class="error-message" id="managerNational-error">این فیلد الزامی است</span>
            </div>
        </div>
        <div class="branch-info-display" id="branchInfoDisplay">
            📍 لطفاً شعبه مورد نظر را انتخاب کنید
        </div>
    </div>
    
    <div class="validation-summary" id="validationSummary">
        <strong>⚠ لطفاً فیلدهای زیر را تکمیل نمایید:</strong>
        <ul id="validationList"></ul>
    </div>
    
    <button class="btn-print" onclick="openPrintTab()" id="printBtn">
        🖨️ پیش‌نمایش و چاپ قرارداد رسمی
        <span class="required-count" id="requiredCount">در حال بررسی فیلدهای الزامی...</span>
    </button>
    <p class="print-hint">💡 تمامی فیلدهای ستاره‌دار (*) باید تکمیل شوند تا امکان چاپ فراهم گردد</p>
    
</div>

<script>
// ========== اطلاعات شعبه‌ها ==========
const branches = {
    1: {
        title: 'شعبه ۱',
        address: 'تبریز، چهار راه ابوریحان، پاساژ ایران، همکف، پلاک ۱۵',
        phone: '۰۴۱۳۴۷۸۲۳۷۳'
    },
    2: {
        title: 'شعبه ۲',
        address: 'تبریز، چهار راه طالقانی، روبروی مسجد طالقانی، پلاک ۸۴۸',
        phone: '۰۴۱۳۵۴۰۶۴۸۶'
    },
    3: {
        title: 'شعبه ۳',
        address: 'تبریز، آبرسان، برج سفید، همکف پلاک ۲۱',
        phone: '۰۴۱۳۳۳۴۷۷۰۹'
    }
};

function updateBranchInfo() {
    const branchId = document.getElementById('branchSelect').value;
    const display = document.getElementById('branchInfoDisplay');
    if (branchId && branches[branchId]) {
        const branch = branches[branchId];
        display.innerHTML = '📍 ' + branch.title + ': ' + branch.address + ' – تلفن: ' + branch.phone;
    } else {
        display.innerHTML = '📍 لطفاً شعبه مورد نظر را انتخاب کنید';
    }
    validateForm();
}

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

// ========== به‌روزرسانی نرخ سود ==========
function updateProfitRate() {
    const period = document.getElementById('depositPeriod').value;
    const rateInput = document.getElementById('profitRate');
    switch(period) {
        case 'یکساله': rateInput.value = '۸٪'; break;
        case 'شش ماهه': rateInput.value = '۶٪'; break;
        case 'سه ماهه': rateInput.value = '۴٪'; break;
        default: rateInput.value = '';
    }
}

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

// ========== اعتبارسنجی ==========
const fieldLabels = {
    'ownerGender': 'جنسیت صاحب حساب',
    'ownerName': 'نام و نام خانوادگی صاحب حساب',
    'ownerFather': 'نام پدر صاحب حساب',
    'ownerNational': 'کد ملی صاحب حساب',
    'ownerIssuePlace': 'محل صدور شناسنامه',
    'ownerMobile': 'تلفن همراه صاحب حساب',
    'ownerAddress': 'آدرس صاحب حساب',
    'agentRelation': 'نسبت وکیل/ولی/قیم',
    'agentGender': 'جنسیت وکیل/ولی/قیم',
    'agentName': 'نام و نام خانوادگی وکیل/ولی/قیم',
    'agentNational': 'کد ملی وکیل/ولی/قیم',
    'agentMobile': 'تلفن همراه وکیل/ولی/قیم',
    'agentAddress': 'آدرس وکیل/ولی/قیم',
    'goldWeight': 'مقدار طلای آبشده (گرم)',
    'goldRate': 'نرخ طلا (ریال/گرم)',
    'goldAmount': 'مبلغ معادل (ریال)',
    'depositPeriod': 'دوره سپرده‌گذاری',
    'startDate': 'تاریخ شروع سپرده',
    'paymentMethod': 'نحوه پرداخت',
    'paymentRef': 'شماره پیگیری / سریال کارتخوان',
    'contractDate': 'تاریخ تنظیم قرارداد',
    'contractNumber': 'شماره قرارداد',
    'branchSelect': 'انتخاب شعبه',
    'managerName': 'نام مدیر / مباشر',
    'managerNational': 'کد ملی مدیر'
};

function getRequiredFields() {
    return document.querySelectorAll('[data-required="true"]');
}

function validateField(field) {
    const errorEl = document.getElementById(field.id + '-error');
    let isValid = true;
    
    if (!field.value || field.value.trim() === '') {
        isValid = false;
        field.classList.add('error');
        if (errorEl) errorEl.classList.add('show');
    } else {
        field.classList.remove('error');
        if (errorEl) errorEl.classList.remove('show');
    }
    
    return isValid;
}

function validateForm() {
    const requiredFields = getRequiredFields();
    const invalidFields = [];
    let allValid = true;
    
    requiredFields.forEach(field => {
        if (!validateField(field)) {
            allValid = false;
            const label = fieldLabels[field.id] || field.id;
            invalidFields.push(label);
        }
    });
    
    // به‌روزرسانی دکمه چاپ
    const printBtn = document.getElementById('printBtn');
    const requiredCount = document.getElementById('requiredCount');
    const validationSummary = document.getElementById('validationSummary');
    const validationList = document.getElementById('validationList');
    
    if (allValid) {
        printBtn.disabled = false;
        requiredCount.textContent = '✅ تمامی فیلدهای الزامی تکمیل شده‌اند';
        requiredCount.style.color = '#4ade80';
        validationSummary.classList.remove('show');
    } else {
        printBtn.disabled = true;
        requiredCount.textContent = `❌ ${invalidFields.length} فیلد الزامی تکمیل نشده است`;
        requiredCount.style.color = 'var(--danger)';
        
        // نمایش خلاصه خطاها
        validationList.innerHTML = invalidFields.map(f => `<li>${f}</li>`).join('');
        validationSummary.classList.add('show');
    }
    
    return allValid;
}

// ========== رویدادهای ورودی ==========
document.addEventListener('DOMContentLoaded', function() {
    // اعتبارسنجی اولیه
    updateProfitRate();
    validateForm();
    
    // افزودن رویداد به تمام فیلدهای الزامی
    const requiredFields = getRequiredFields();
    requiredFields.forEach(field => {
        field.addEventListener('input', function() {
            validateField(this);
            validateForm();
        });
        field.addEventListener('change', function() {
            validateField(this);
            validateForm();
        });
    });
    
    // رویداد برای فیلدهای غیر الزامی که روی دکمه تأثیر ندارند
    // ولی باز هم می‌خواهیم وضعیت را چک کنیم
    const allInputs = document.querySelectorAll('input, textarea, select');
    allInputs.forEach(input => {
        if (!input.hasAttribute('data-required')) {
            input.addEventListener('input', validateForm);
            input.addEventListener('change', validateForm);
        }
    });
});

// ========== پیش‌نمایش رسمی ==========
function openPrintPreview(htmlContent) {
    localStorage.setItem('printContent', htmlContent);
    window.open('print/', '_blank');
}

function openPrintTab() {
    // اعتبارسنجی نهایی قبل از چاپ
    if (!validateForm()) {
        // اسکرول به اولین فیلد خطادار
        const firstError = document.querySelector('.error');
        if (firstError) {
            firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            firstError.focus();
        }
        
        // لرزش دکمه
        const printBtn = document.getElementById('printBtn');
        printBtn.style.animation = 'shake 0.5s ease-in-out';
        setTimeout(() => {
            printBtn.style.animation = '';
        }, 500);
        
        return;
    }
    
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
    let depositPeriod = document.getElementById('depositPeriod').value;
    let profitRate = document.getElementById('profitRate').value;
    let startDate = dash(document.getElementById('startDate').value);
    let payMethod = document.getElementById('paymentMethod').value;
    let payRef = dash(document.getElementById('paymentRef').value);
    let bankAcc = dash(document.getElementById('bankAccount').value);
    let cDate = dash(document.getElementById('contractDate').value);
    let cNum = dash(document.getElementById('contractNumber').value);
    
    let branchId = document.getElementById('branchSelect').value;
    let branch = branches[branchId];
    let branchAddress = branch.address;
    let branchPhone = branch.phone;
    let branchTitle = branch.title;
    let managerName = dash(document.getElementById('managerName').value);
    let managerNational = dash(document.getElementById('managerNational').value);
    
    let b1_main = '';
    if (payMethod === 'کارتخوان') b1_main = 'واریز وجه به حساب شماره ' + bankAcc + ' (دریافت طلای آبشده)';
    else if (payMethod === 'نقد') b1_main = 'پرداخت نقدی به زرگری ثنا';
    else if (payMethod === 'طلای متفرقه') b1_main = 'تحویل طلای متفرقه به زرگری ثنا';
    else if (payMethod === 'حواله طلا') b1_main = 'تحویل حواله طلا به زرگری ثنا';
    
    let b1_detail = '';
    if (payMethod === 'کارتخوان') b1_detail = 'از طریق کارتخوان زرگری ثنا به شماره پیگیری ' + payRef;
    else if (payMethod === 'نقد') b1_detail = 'وجه نقد ';
    else if (payMethod === 'طلای متفرقه') b1_detail = 'طلای متفرقه ';
    else if (payMethod === 'حواله طلا') b1_detail = 'حواله طلا به شماره ' + payRef + ' ';
    
    let clean = oName.replace(/\s+/g, '_').replace(/[^\u0600-\u06FF\w]/g, '') || 'قرارداد';
    
    let html = `<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>قرارداد افتتاح سپرده سرمایه‌گذاری - ${oName}</title>
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
        .terms-box {
            border: 1px solid #e0d5a0; background: #fefdf5;
            padding: 3mm; margin: 2mm 0; border-radius: 2px;
            font-size: 8.5pt; line-height: 2;
        }
        .branch-line { font-size: 7pt; color: #666; }
        .branch-line strong { color: #444; }
    </style>
</head>
<body>

<div class="top-bar">
    <div class="right">
        <div class="logo">زرگری ثنا</div>
        <div class="branch-line">شعبه ۱: چهار راه ابوریحان، پاساژ ایران، همکف، پلاک ۱۵</div>
        <div class="branch-line">شعبه ۲: چهار راه طالقانی، روبروی مسجد طالقانی، پلاک ۸۴۸</div>
        <div class="branch-line">شعبه ۳: آبرسان، برج سفید، همکف پلاک ۲۱</div>
    </div>
    <div class="bismillah">بسمه تعالی</div>
    <div class="left">
        <div>تاریخ: <strong>${cDate}</strong></div>
        <div>شماره: <strong>${cNum}</strong></div>
    </div>
</div>

<div class="title">قرارداد افتتاح حساب سپرده سرمایه‌گذاری طلا</div>

<div class="content">
    <p>این قرارداد بین <strong>${oGender} ${oName}</strong> فرزند <strong>${oFather}</strong> به شماره ملی <strong>${oNational}</strong> صادره از <strong>${oIssuePlace}</strong>، تلفن‌ثابت: <strong>${oPhone}</strong>، تلفن‌همراه: <strong>${oMobile}</strong> به آدرس <strong>${oAddress}</strong> که از این پس «<strong>سپرده‌گذار</strong>» نامیده می‌شود،</p>
    
    <p>و از طرف دیگر <strong>زرگری ثنا</strong> به مدیریت <strong>آقای ${managerName}</strong> به شماره ملی <strong>${managerNational}</strong> به آدرس: <strong>${branchAddress}</strong> که از این پس «<strong>سپرده پذیر</strong>» نامیده می‌شود،</p>
    
    <p>با شرایط و مفاد ذیل منعقد می‌گردد:</p>
    
        <p class="section-label">بند اول – موضوع قرارداد و مشخصات سپرده</p>
    <p>${oGender} <strong>${oName}</strong> به میزان <span class="highlight">${goldW} گرم</span> طلای آبشده با نرخ <span class="highlight">${goldR} ریال/گرم</span> معادل <span class="highlight">${goldA} ریال</span> از طریق <strong>${b1_main}</strong> ${b1_detail} به عنوان <strong>سپرده سرمایه‌گذاری ${depositPeriod}</strong> با نرخ سود <strong>${profitRate}</strong> در تاریخ <strong>${startDate}</strong> نزد سپرده پذیر افتتاح نمود.</p>
    
    <p class="section-label">بند دوم – سود سپرده</p>
    <div class="terms-box">
        <p><strong>الف) نرخ‌های سود:</strong> نرخ سود  سالانه برای سپرده‌های یکساله <strong>۸ درصد</strong>، شش‌ماهه <strong>۶ درصد</strong> و سه‌ماهه <strong>۴ درصد</strong> می‌باشد.</p>
        <p><strong>ب) شکست سود:</strong>  به سپرده‌هایی که قبل از سررسید برداشت می‌شوند،  <strong>۲.۵٪ مشمول شکست سود</strong> (به میزان وزن برداشت شده) می‌گردد.</p>
        <p><strong>ج) محاسبه سود:</strong> به حداقل موجودی در ماه، سود تعیین شده در بند اول به حساب آتیه طلایی سپرده‌گذار پرداخت خواهد شد.</p>
    </div>
    
    <p class="section-label">بند سوم – حق برداشت و فسخ</p>
    <p>حق برداشت یا فسخ حساب <strong>صرفاً</strong> با سپرده‌گذار، ${oGender} <strong>${oName}</strong> به کد ملی <strong>${oNational}</strong> می‌باشد.</p>
    
    <p><strong>تبصره:</strong> در غیاب صاحب حساب، ${aGender} <strong>${aName}</strong> به کد ملی <strong>${aNational}</strong> به عنوان <strong>${aRelation}</strong> صاحب حساب، به آدرس <strong>${aAddress}</strong> و تلفن‌ثابت <strong>${aPhone}</strong> و تلفن‌همراه <strong>${aMobile}</strong> حق برداشت یا فسخ قرارداد را دارد. <em>(امضا و اثرانگشت موکل الزامی است)</em></p>
    
    <p class="section-label">بند چهارم – تعهدات سپرده پذیر</p>
    <p>زرگری ثنا به مدیریت آقای ${managerName} متعهد می‌گردد به محض درخواست صاحب حساب یا ${aRelation} ایشان، نسبت به پرداخت موجودی طلای آبشده  به هر میزان به صورت <strong>طلا</strong> یا <strong>ریال</strong> (به نرخ روز) به حساب ایشان واریز نماید.</p>
</div>

<div class="signatures">
    <div class="sig-box">
        <div class="label">امضاء و اثر انگشت<br>سپرده‌گذار</div>
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
        <div style="font-size:7pt;">${managerName}</div>
    </div>
</div>

<div class="footer">
    این قرارداد در تاریخ <strong>${cDate}</strong> در دو نسخه متحدالمتن تنظیم و امضا گردید و هر دو نسخه دارای اعتبار واحد می‌باشد.<br>
    <strong>مدارک مورد نیاز:</strong> اصل کارت ملی سپرده‌گذار و ${aRelation} – اصل شناسنامه<br>
    <strong>آدرس ${branchTitle}:</strong> ${branchAddress} – تلفن: ${branchPhone}
</div>

</body>
</html>`;
    
    openPrintPreview(html);
}
</script>
</body>
</html>