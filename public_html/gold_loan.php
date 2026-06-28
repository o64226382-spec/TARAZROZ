<?php
define('SECURE_ACCESS', true);
require_once 'includes/config.php';
require_once 'includes/auth.php';
requireLogin();
$user_id = intval($_SESSION['user_id']);
$perm_query = "SELECT permissions FROM users WHERE id = $user_id";
$perm_result = mysqli_query($conn, $perm_query);
$permissions = '';
if ($perm_result && mysqli_num_rows($perm_result) > 0) $permissions = mysqli_fetch_assoc($perm_result)['permissions'];
if (strpos($permissions, 'gold_loan') === false && $_SESSION['role'] !== 'admin') {
    die('<div style="text-align:center;padding:60px;font-family:Vazirmatn;background:#0a0f1a;color:#e8ecf1;">⛔ دسترسی غیرمجاز<br><a href="index.php" style="color:#4b8cf7;">بازگشت</a></div>');
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>وام ثناگلد | تراز روزانه</title>
    <link href="assets/fonts/fonts.css" rel="stylesheet">
    <link rel="icon" href="assets/images/logo.png">
    <style>
        :root { --bg: #0a0f1a; --surface: rgba(255,255,255,0.03); --border: rgba(255,255,255,0.06); --text: #e8ecf1; --text-secondary: #8899aa; --accent: #4b8cf7; --gold: #d4af37; --gold-light: #ffd700; --radius: 14px; --radius-sm: 8px; }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: 'Vazirmatn', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; padding: 14px; }
        .container { max-width: 550px; margin: 0 auto; display: flex; flex-direction: column; gap: 12px; }
        .header { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 12px 18px; display: flex; align-items: center; justify-content: space-between; backdrop-filter: blur(12px); }
        .header h2 { font-size: 1rem; font-weight: 700; }
        .back-btn { color: var(--text-secondary); text-decoration: none; font-size: 0.75rem; }
        .card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 18px; backdrop-filter: blur(10px); }
        label { display: block; margin-top: 12px; font-weight: 600; color: var(--text-secondary); font-size: 0.75rem; }
        input { width: 100%; padding: 10px 12px; margin-top: 4px; background: rgba(255,255,255,0.03); border: 1px solid var(--border); border-radius: var(--radius-sm); color: var(--text); font-family: 'Vazirmatn', sans-serif; font-size: 0.85rem; }
        input:focus { border-color: var(--accent); outline: none; }
        .btn-calc { margin-top: 16px; padding: 12px; width: 100%; background: linear-gradient(135deg, #d4af37, #b8960f); color: #1a1a1a; border: none; border-radius: var(--radius-sm); cursor: pointer; font-family: 'Vazirmatn', sans-serif; font-weight: 700; font-size: 0.9rem; }
        .result-card { margin-top: 12px; padding: 16px; background: rgba(212,175,55,0.05); border: 1px solid rgba(212,175,55,0.2); border-radius: var(--radius); display: none; font-size: 0.82rem; line-height: 1.8; }
        .result-card .line { margin-bottom: 4px; }
        .result-card .final { margin-top: 8px; padding-top: 8px; border-top: 1px dashed rgba(212,175,55,0.3); font-weight: 700; color: var(--gold-light); font-size: 0.9rem; }
        .result-card b { color: var(--gold); }
    </style>
</head>
<body>
<div class="container">
    <div class="header"><h2>💰 وام ثناگلد</h2><a href="index.php" class="back-btn">← بازگشت</a></div>
    <div class="card">
        <label>نرخ طلا (ریال):</label><input type="text" id="gp" placeholder="مثال: 138,000,000" oninput="formatNum(this)">
        <label>مبلغ وام (ریال):</label><input type="text" id="loan" placeholder="مثال: 300,000,000" oninput="formatNum(this)">
        <label>نرخ بهره (% سالانه):</label><input type="number" id="rate" placeholder="مثال: 23">
        <label>تعداد اقساط (ماه):</label><input type="number" id="term" placeholder="مثال: 36">
        <button class="btn-calc" onclick="calc()">🧮 محاسبه</button>
        <div class="result-card" id="result">
            <div class="line"><b>سود:</b> <span id="rInt"></span></div>
            <div class="line"><b>هر قسط:</b> <span id="rPer"></span></div>
            <div class="line"><b>وثیقه طلا:</b> <span id="rCollat"></span></div>
            <div class="final"><b>کل بدهی:</b> <span id="rTotal"></span></div>
        </div>
    </div>
</div>
<script>
function fmt(n) { let s=Math.round(n).toString(); return s.replace(/\B(?=(\d{3})+(?!\d))/g,',').replace(/\d/g,d=>['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'][d]); }
function fmtGram(n) { return (Math.round(n*1000)/1000).toString(); }
function parseNum(s) { return parseFloat(String(s||'').replace(/,/g,''))||0; }
function formatNum(el) { let r=el.value.replace(/,/g,'').replace(/[^0-9]/g,''); if(r) el.value=Number(r).toLocaleString('en-US'); }
function calc() {
    let gp=parseNum(document.getElementById('gp').value), loan=parseNum(document.getElementById('loan').value), rate=parseFloat(document.getElementById('rate').value)||0, term=parseInt(document.getElementById('term').value)||1;
    let years=term/12, int=Math.ceil(loan*(rate/100)*years), debt=Math.ceil(loan+int), per=Math.ceil(debt/term), collat=debt/gp;
    document.getElementById('rInt').textContent=fmt(int)+' ریال ('+fmtGram(int/gp)+' گرم)';
    document.getElementById('rPer').textContent=fmt(per)+' ریال ('+fmtGram(debt/term/gp)+' گرم)';
    document.getElementById('rCollat').textContent=fmtGram(collat)+' گرم';
    document.getElementById('rTotal').textContent=fmt(debt)+' ریال';
    document.getElementById('result').style.display='block';
}
</script>
</body>
</html>