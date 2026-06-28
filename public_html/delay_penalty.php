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
if (strpos($permissions, 'delay_penalty') === false && $_SESSION['role'] !== 'admin') {
    die('<div style="text-align:center;padding:60px;font-family:Vazirmatn;background:#0a0f1a;color:#e8ecf1;">⛔ شما دسترسی به این بخش را ندارید.<br><a href="index.php" style="color:#4b8cf7;">بازگشت</a></div>');
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>جریمه دیرکرد | تراز روزانه</title>
    <link href="assets/fonts/fonts.css" rel="stylesheet">
    <link rel="icon" href="assets/images/logo.png">
    <style>
        :root {
            --bg: #0a0f1a; --surface: rgba(255,255,255,0.03);
            --border: rgba(255,255,255,0.06); --text: #e8ecf1;
            --text-secondary: #8899aa; --accent: #4b8cf7;
            --gold: #d4af37; --gold-light: #ffd700;
            --green: #10b981; --red: #ef4444;
            --radius: 14px; --radius-sm: 8px;
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Vazirmatn', sans-serif;
            background: var(--bg); color: var(--text);
            min-height: 100vh; padding: 14px;
        }
        .container { max-width: 550px; margin: 0 auto; display: flex; flex-direction: column; gap: 12px; }
        .header {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: var(--radius); padding: 12px 18px;
            display: flex; align-items: center; justify-content: space-between;
            backdrop-filter: blur(12px);
        }
        .header h2 { font-size: 1rem; font-weight: 700; }
        .back-btn { color: var(--text-secondary); text-decoration: none; font-size: 0.75rem; }
        
        .card {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: var(--radius); padding: 18px; backdrop-filter: blur(10px);
        }
        label { display: block; margin-top: 12px; font-weight: 600; color: var(--text-secondary); font-size: 0.75rem; }
        input, select {
            width: 100%; padding: 10px 12px; margin-top: 4px;
            background: rgba(255,255,255,0.03); border: 1px solid var(--border);
            border-radius: var(--radius-sm); color: var(--text);
            font-family: 'Vazirmatn', sans-serif; font-size: 0.85rem;
        }
        input:focus, select:focus { border-color: var(--accent); outline: none; }
        .btn-calc {
            margin-top: 16px; padding: 12px; width: 100%;
            background: linear-gradient(135deg, #d4af37, #b8960f);
            color: #1a1a1a; border: none; border-radius: var(--radius-sm);
            cursor: pointer; font-family: 'Vazirmatn', sans-serif;
            font-weight: 700; font-size: 0.9rem;
        }
        .result-card {
            margin-top: 12px; padding: 16px;
            background: rgba(212,175,55,0.05);
            border: 1px solid rgba(212,175,55,0.2);
            border-radius: var(--radius); display: none;
            font-size: 0.82rem; line-height: 1.8;
        }
        .result-card .line { margin-bottom: 4px; }
        .result-card .final {
            margin-top: 8px; padding-top: 8px;
            border-top: 1px dashed rgba(212,175,55,0.3);
            font-weight: 700; color: var(--gold-light); font-size: 0.9rem;
        }
        .result-card b { color: var(--gold); }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h2>🕐 جریمه دیرکرد</h2>
        <a href="index.php" class="back-btn">← بازگشت</a>
    </div>
    
    <div class="card">
        <label>نوع مبلغ:</label>
        <select id="unit" onchange="toggleUnit()">
            <option value="rial">ریال</option>
            <option value="gram">گرم طلا</option>
        </select>
        
        <div id="rialInput"><label>مبلغ قسط (ریال):</label><input type="text" id="amtRial" placeholder="مثال: 60,000,000" oninput="formatNum(this)"></div>
        <div id="gramInput" style="display:none;">
            <label>مبلغ قسط (گرم):</label><input type="text" id="amtGram" placeholder="مثال: 5">
            <label>نرخ طلا (ریال):</label><input type="text" id="goldPrice" placeholder="مثال: 138,000,000" oninput="formatNum(this)">
        </div>
        <label>تعداد روزهای تأخیر:</label><input type="number" id="days" placeholder="مثال: 65">
        <label>نرخ جریمه (% ماهانه):</label><input type="number" id="rate" placeholder="مثال: 6">
        
        <button class="btn-calc" onclick="calc()">🧮 محاسبه</button>
        
        <div class="result-card" id="result">
            <div class="line"><b>مبلغ:</b> <span id="rAmt"></span></div>
            <div class="line"><b>جریمه:</b> <span id="rPenalty"></span></div>
            <div class="final"><b>کل بدهی:</b> <span id="rTotal"></span></div>
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
function parseNum(s) { return parseFloat(String(s||'').replace(/,/g, '')) || 0; }
function formatNum(el) {
    let raw = el.value.replace(/,/g, '').replace(/[^0-9]/g, '');
    if (raw) el.value = Number(raw).toLocaleString('en-US');
}

function toggleUnit() {
    document.getElementById('rialInput').style.display = document.getElementById('unit').value === 'rial' ? 'block' : 'none';
    document.getElementById('gramInput').style.display = document.getElementById('unit').value === 'gram' ? 'block' : 'none';
}

function calc() {
    let unit = document.getElementById('unit').value;
    let days = parseFloat(document.getElementById('days').value) || 0;
    let rate = parseFloat(document.getElementById('rate').value) || 0;
    let amtRial = 0, amtDisplay = '';
    
    if (unit === 'rial') {
        amtRial = parseNum(document.getElementById('amtRial').value);
        amtDisplay = fmt(amtRial) + ' ریال';
    } else {
        let gram = parseFloat(document.getElementById('amtGram').value) || 0;
        let gp = parseNum(document.getElementById('goldPrice').value);
        amtRial = gram * gp;
        amtDisplay = fmtGram(gram) + ' گرم (' + fmt(amtRial) + ' ریال)';
    }
    
    let penalty = Math.ceil(amtRial * (rate / 100) * (days / 30));
    let total = Math.ceil(amtRial + penalty);
    
    document.getElementById('rAmt').textContent = amtDisplay;
    document.getElementById('rPenalty').textContent = fmt(penalty) + ' ریال';
    document.getElementById('rTotal').textContent = fmt(total) + ' ریال';
    document.getElementById('result').style.display = 'block';
}
</script>
</body>
</html>