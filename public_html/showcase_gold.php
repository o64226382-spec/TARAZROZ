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
if (strpos($permissions, 'showcase_gold') === false && $_SESSION['role'] !== 'admin') {
    die('<div style="text-align:center;padding:60px;font-family:Vazirmatn;background:#0a0f1a;color:#e8ecf1;">⛔ دسترسی غیرمجاز<br><a href="index.php" style="color:#4b8cf7;">بازگشت</a></div>');
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>طلای ویترین | تراز روزانه</title>
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
    <div class="header"><h2>💍 طلای ویترین</h2><a href="index.php" class="back-btn">← بازگشت</a></div>
    <div class="card">
        <label>نرخ طلا (ریال):</label><input type="text" id="gp" placeholder="مثال: 138,000,000" oninput="formatNum(this)">
        <label>وزن طلا (گرم):</label><input type="text" id="weight" placeholder="مثال: 1.5">
        <label>درصد اجرت (%):</label><input type="number" id="wage" placeholder="مثال: 16">
        <label>درصد سود (%):</label><input type="number" id="profit" placeholder="مثال: 5">
        <label>درصد ارزش افزوده (%):</label><input type="number" id="vat" placeholder="مثال: 2">
        <button class="btn-calc" onclick="calc()">🧮 محاسبه</button>
        <div class="result-card" id="result">
            <div class="line"><b>ارزش پایه:</b> <span id="rBase"></span></div>
            <div class="line"><b>اجرت:</b> <span id="rWage"></span></div>
            <div class="line"><b>سود:</b> <span id="rProfit"></span></div>
            <div class="line"><b>مالیات:</b> <span id="rVat"></span></div>
            <div class="final"><b>قیمت نهایی:</b> <span id="rFinal"></span></div>
        </div>
    </div>
</div>
<script>
function fmt(n) { let s=Math.round(n).toString(); return s.replace(/\B(?=(\d{3})+(?!\d))/g,',').replace(/\d/g,d=>['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'][d]); }
function parseNum(s) { return parseFloat(String(s||'').replace(/,/g,''))||0; }
function formatNum(el) { let r=el.value.replace(/,/g,'').replace(/[^0-9]/g,''); if(r) el.value=Number(r).toLocaleString('en-US'); }
function calc() {
    let gp=parseNum(document.getElementById('gp').value), w=parseFloat(document.getElementById('weight').value)||0, wageR=parseFloat(document.getElementById('wage').value)||0, profitR=parseFloat(document.getElementById('profit').value)||0, vatR=parseFloat(document.getElementById('vat').value)||0;
    let base=gp*w, wage=base*(wageR/100), profit=(base+wage)*(profitR/100), vat=(base+wage+profit)*(vatR/100), final=base+wage+profit+vat;
    document.getElementById('rBase').textContent=fmt(base)+' ریال';
    document.getElementById('rWage').textContent=fmt(wage)+' ریال';
    document.getElementById('rProfit').textContent=fmt(profit)+' ریال';
    document.getElementById('rVat').textContent=fmt(vat)+' ریال';
    document.getElementById('rFinal').textContent=fmt(final)+' ریال';
    document.getElementById('result').style.display='block';
}
</script>
</body>
</html>