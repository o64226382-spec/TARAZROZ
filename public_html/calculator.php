<?php
define('SECURE_ACCESS', true);
require_once 'includes/config.php';
require_once 'includes/auth.php';
requireLogin();

// چک دسترسی
$user_id = intval($_SESSION['user_id']);
$perm_query = "SELECT permissions FROM users WHERE id = $user_id";
$perm_result = mysqli_query($conn, $perm_query);
$permissions = '';
if ($perm_result && mysqli_num_rows($perm_result) > 0) {
    $permissions = mysqli_fetch_assoc($perm_result)['permissions'];
}
if (strpos($permissions, 'calculator') === false && $_SESSION['role'] !== 'admin') {
    die('<div style="text-align:center;padding:60px;font-family:Vazirmatn;background:#0a0f1a;color:#e8ecf1;">⛔ شما دسترسی به این بخش را ندارید.<br><a href="index.php" style="color:#4b8cf7;">بازگشت</a></div>');
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ماشین حساب زرگری | تراز روزانه</title>
    <link href="assets/fonts/fonts.css" rel="stylesheet">
    <link rel="icon" href="assets/images/logo.png">
    <style>
        :root {
            --bg: #0a0f1a; --surface: rgba(255,255,255,0.03);
            --border: rgba(255,255,255,0.06); --text: #e8ecf1;
            --text-secondary: #8899aa; --accent: #4b8cf7;
            --gold: #d4af37; --gold-light: #ffd700;
            --green: #10b981; --red: #ef4444;
            --radius: 14px;
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Vazirmatn', sans-serif;
            background: var(--bg); color: var(--text);
            min-height: 100vh; padding: 14px;
            background-image: radial-gradient(ellipse at 30% 20%, rgba(212,175,55,0.04) 0%, transparent 60%);
        }
        .container { max-width: 600px; margin: 0 auto; display: flex; flex-direction: column; gap: 12px; }
        
        .header {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: var(--radius); padding: 12px 18px;
            display: flex; align-items: center; justify-content: space-between;
            backdrop-filter: blur(12px);
        }
        .header h2 { font-size: 1rem; font-weight: 700; display: flex; align-items: center; gap: 8px; }
        .back-btn { color: var(--text-secondary); text-decoration: none; font-size: 0.75rem; }
        
        .card {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: var(--radius); padding: 18px; backdrop-filter: blur(10px);
        }
        
        label {
            display: block; margin-top: 12px; font-weight: 600;
            color: var(--text-secondary); font-size: 0.75rem;
        }
        select, input {
            width: 100%; padding: 10px 12px; margin-top: 4px;
            background: rgba(255,255,255,0.03); border: 1px solid var(--border);
            border-radius: 8px; color: var(--text);
            font-family: 'Vazirmatn', sans-serif; font-size: 0.85rem;
        }
        select:focus, input:focus { border-color: var(--accent); outline: none; }
        
        .section { display: none; }
        .section.active { display: block; }
        
        .btn-calc {
            margin-top: 16px; padding: 12px; width: 100%;
            background: linear-gradient(135deg, #d4af37, #b8960f);
            color: #1a1a1a; border: none; border-radius: 8px;
            cursor: pointer; font-family: 'Vazirmatn', sans-serif;
            font-weight: 700; font-size: 0.9rem; transition: all 0.2s;
        }
        .btn-calc:hover { transform: translateY(-1px); box-shadow: 0 4px 16px rgba(212,175,55,0.3); }
        
        .result {
            margin-top: 14px; padding: 14px;
            background: rgba(212,175,55,0.05);
            border: 1px solid rgba(212,175,55,0.15);
            border-radius: 8px; font-size: 0.82rem; line-height: 1.8;
            display: none;
        }
        .result .line { margin-bottom: 2px; }
        .result .final {
            margin-top: 8px; padding-top: 8px;
            border-top: 1px dashed rgba(212,175,55,0.25);
            font-weight: 700; color: var(--gold-light);
        }
        .result b { color: var(--gold); }
        
        @media (max-width: 480px) {
            body { padding: 8px; }
        }
    </style>
</head>
<body>
<div class="container">
    
    <div class="header">
        <h2><span>🧮</span> ماشین حساب زرگری</h2>
        <a href="index.php" class="back-btn">← بازگشت</a>
    </div>
    
    <div class="card">
        <label>نوع محاسبه:</label>
        <select id="calcType" onchange="showSection()">
            <option value="">-- انتخاب کنید --</option>
            <option value="delay">جریمه دیرکرد</option>
            <option value="installment">فروش قسطی</option>
            <option value="goldLoan">وام ثناگلد (طلایی)</option>
            <option value="showcaseGold">طلای ویترین</option>
        </select>
        
        <!-- جریمه دیرکرد -->
        <div id="sec-delay" class="section">
            <label>نوع مبلغ:</label>
            <select id="delayUnit" onchange="toggleDelayUnit()">
                <option value="rial">ریال</option>
                <option value="gram">گرم طلا</option>
            </select>
            <div id="delayRial"><label>مبلغ قسط (ریال):</label><input type="text" id="delayAmtRial" placeholder="مثال: 60,000,000"></div>
            <div id="delayGram" style="display:none;">
                <label>مبلغ قسط (گرم):</label><input type="text" id="delayAmtGram" placeholder="مثال: 5" value="1">
                <label>نرخ طلا (ریال):</label><input type="text" id="delayGoldPrice" placeholder="مثال: 138,000,000" value="138000000">
            </div>
            <label>تعداد روزهای تأخیر:</label><input type="number" id="delayDays" placeholder="مثال: 65">
            <label>نرخ جریمه (% ماهانه):</label><input type="number" id="delayRate" value="6">
            <button class="btn-calc" onclick="calcDelay()">🧮 محاسبه</button>
            <div id="delayResult" class="result"></div>
        </div>
        
        <!-- فروش قسطی -->
        <div id="sec-installment" class="section">
            <label>مبلغ کل (ریال):</label><input type="text" id="instTotal" placeholder="مثال: 2,000,000,000">
            <label>پیش‌پرداخت (ریال):</label><input type="text" id="instDown" placeholder="مثال: 120,000,000">
            <label>تعداد اقساط:</label><input type="number" id="instCount" placeholder="مثال: 12">
            <label>نرخ سود (% ماهانه):</label><input type="number" id="instRate" value="4">
            <button class="btn-calc" onclick="calcInstallment()">🧮 محاسبه</button>
            <div id="instResult" class="result"></div>
        </div>
        
        <!-- وام طلا -->
        <div id="sec-goldLoan" class="section">
            <label>نرخ طلا (ریال):</label><input type="text" id="loanGoldPrice" placeholder="مثال: 138,000,000" value="138000000">
            <label>مبلغ وام (ریال):</label><input type="text" id="loanAmount" placeholder="مثال: 300,000,000">
            <label>نرخ بهره (% سالانه):</label><input type="number" id="loanRate" value="23">
            <label>تعداد اقساط (ماه):</label><input type="number" id="loanTerm" placeholder="مثال: 36">
            <button class="btn-calc" onclick="calcGoldLoan()">🧮 محاسبه</button>
            <div id="loanResult" class="result"></div>
        </div>
        
        <!-- طلای ویترین -->
        <div id="sec-showcaseGold" class="section">
            <label>نرخ طلا (ریال):</label><input type="text" id="sgGoldPrice" placeholder="مثال: 138,000,000" value="138000000">
            <label>وزن طلا (گرم):</label><input type="text" id="sgWeight" placeholder="مثال: 1" value="1">
            <label>درصد اجرت (%):</label><input type="number" id="sgWage" value="16">
            <label>درصد سود (%):</label><input type="number" id="sgProfit" value="5">
            <label>درصد ارزش افزوده (%):</label><input type="number" id="sgVat" value="2">
            <button class="btn-calc" onclick="calcShowcase()">🧮 محاسبه</button>
            <div id="sgResult" class="result"></div>
        </div>
    </div>

</div>

<script>
function fmt(n) {
    let s = Math.round(n).toString();
    let fa = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
    return s.replace(/\B(?=(\d{3})+(?!\d))/g, ',').replace(/\d/g, d => fa[d]);
}
function fmtGram(n) { return (Math.round(n * 1000) / 1000).toString(); }
function parseNum(s) { return parseFloat(String(s).replace(/,/g, '')) || 0; }

function showSection() {
    document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
    let type = document.getElementById('calcType').value;
    if (type) document.getElementById('sec-' + type).classList.add('active');
}

function toggleDelayUnit() {
    let u = document.getElementById('delayUnit').value;
    document.getElementById('delayRial').style.display = u === 'rial' ? 'block' : 'none';
    document.getElementById('delayGram').style.display = u === 'gram' ? 'block' : 'none';
}

function calcDelay() {
    let unit = document.getElementById('delayUnit').value;
    let days = parseFloat(document.getElementById('delayDays').value) || 0;
    let rate = parseFloat(document.getElementById('delayRate').value) || 0;
    let amtRial = 0, amtDisplay = '';
    if (unit === 'rial') {
        amtRial = parseNum(document.getElementById('delayAmtRial').value);
        amtDisplay = fmt(amtRial) + ' ریال';
    } else {
        let gram = parseFloat(document.getElementById('delayAmtGram').value) || 0;
        let gp = parseNum(document.getElementById('delayGoldPrice').value);
        amtRial = gram * gp;
        amtDisplay = fmtGram(gram) + ' گرم (' + fmt(amtRial) + ' ریال)';
    }
    let penalty = Math.ceil(amtRial * (rate / 100) * (days / 30));
    let total = Math.ceil(amtRial + penalty);
    let el = document.getElementById('delayResult');
    el.style.display = 'block';
    el.innerHTML = `<div class="line"><b>مبلغ:</b> ${amtDisplay}</div><div class="line"><b>جریمه:</b> ${fmt(penalty)} ریال</div><div class="final"><b>کل بدهی:</b> ${fmt(total)} ریال</div>`;
}

function calcInstallment() {
    let total = parseNum(document.getElementById('instTotal').value);
    let down = parseNum(document.getElementById('instDown').value);
    let cnt = parseInt(document.getElementById('instCount').value) || 1;
    let rate = parseFloat(document.getElementById('instRate').value) || 0;
    let bal = total - down;
    let interest = Math.ceil(bal * (rate / 100) * cnt);
    let debt = Math.ceil(bal + interest);
    let per = Math.ceil(debt / cnt);
    let el = document.getElementById('instResult');
    el.style.display = 'block';
    el.innerHTML = `<div class="line"><b>مانده:</b> ${fmt(bal)} ریال</div><div class="line"><b>سود:</b> ${fmt(interest)} ریال</div><div class="line"><b>هر قسط:</b> ${fmt(per)} ریال</div><div class="final"><b>جمع بدهی:</b> ${fmt(debt)} ریال</div>`;
}

function calcGoldLoan() {
    let gp = parseNum(document.getElementById('loanGoldPrice').value);
    let loan = parseNum(document.getElementById('loanAmount').value);
    let rate = parseFloat(document.getElementById('loanRate').value) || 0;
    let months = parseInt(document.getElementById('loanTerm').value) || 1;
    let years = months / 12;
    let interest = Math.ceil(loan * (rate / 100) * years);
    let debt = Math.ceil(loan + interest);
    let perRial = Math.ceil(debt / months);
    let collat = debt / gp;
    let el = document.getElementById('loanResult');
    el.style.display = 'block';
    el.innerHTML = `<div class="line"><b>سود:</b> ${fmt(interest)} ریال (${fmtGram(interest/gp)} گرم)</div><div class="line"><b>هر قسط:</b> ${fmt(perRial)} ریال (${fmtGram(debt/months/gp)} گرم)</div><div class="line"><b>وثیقه:</b> ${fmtGram(collat)} گرم</div><div class="final"><b>کل بدهی:</b> ${fmt(debt)} ریال</div>`;
}

function calcShowcase() {
    let gp = parseNum(document.getElementById('sgGoldPrice').value);
    let w = parseFloat(document.getElementById('sgWeight').value) || 0;
    let wageR = parseFloat(document.getElementById('sgWage').value) || 0;
    let profitR = parseFloat(document.getElementById('sgProfit').value) || 0;
    let vatR = parseFloat(document.getElementById('sgVat').value) || 0;
    let base = gp * w;
    let wage = base * (wageR / 100);
    let profit = (base + wage) * (profitR / 100);
    let vat = (base + wage + profit) * (vatR / 100);
    let final = base + wage + profit + vat;
    let el = document.getElementById('sgResult');
    el.style.display = 'block';
    el.innerHTML = `<div class="line"><b>ارزش پایه:</b> ${fmt(base)} ریال</div><div class="line"><b>اجرت (${wageR}%):</b> ${fmt(wage)} ریال</div><div class="line"><b>سود (${profitR}%):</b> ${fmt(profit)} ریال</div><div class="line"><b>مالیات (${vatR}%):</b> ${fmt(vat)} ریال</div><div class="final"><b>قیمت نهایی:</b> ${fmt(final)} ریال</div>`;
}
</script>
</body>
</html>