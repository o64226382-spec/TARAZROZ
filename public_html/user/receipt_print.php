<?php
define('SECURE_ACCESS', true);
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireLogin();

$branch_id = $_GET['branch_id'] ?? 0;
$selected_date = $_GET['date'] ?? '';
$person_input = $_GET['person'] ?? '';
$theme = $_GET['theme'] ?? 'classic';

if (!$selected_date || !$person_input) die('اطلاعات ناقص');

list($person_name, $person_type) = explode('|', $person_input);
$is_debtor = ($person_type === 'debtor');

$stmt = mysqli_prepare($conn, "SELECT report_data FROM daily_reports WHERE user_id = ? AND report_date = ?");
mysqli_stmt_bind_param($stmt, "is", $branch_id, $selected_date);
mysqli_stmt_execute($stmt);
$report = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!$report) die('گزارشی یافت نشد');

$data = json_decode($report['report_data'], true);
$totalAmount = 0; $personId = 0;

if ($is_debtor) {
    foreach (($data['debtors'] ?? []) as $d) { if ($d['name'] === $person_name) { $totalAmount = (float)($d['amt'] ?? 0); $personId = $d['id']; break; } }
} else {
    foreach (($data['creditors'] ?? []) as $c) { if ($c['name'] === $person_name) { $totalAmount = (float)($c['amt'] ?? 0); $personId = $c['id']; break; } }
}

$controlRows = $data['controlRows'] ?? [];
$controlDescs = $data['controlDescs'] ?? [];
$matrixVals = $data['matrixValues'] ?? [];
$debtorsList = $data['debtors'] ?? [];
$creditorsList = $data['creditors'] ?? [];

$activeRelations = [];
foreach ($creditorsList as $c) { foreach ($debtorsList as $d) { $key = $c['id'].'_'.$d['id']; $val = (float)($matrixVals[$key] ?? 0); if ($val > 0) $activeRelations[] = ['creditor_id' => $c['id'], 'debtor_id' => $d['id']]; } }

$targetCols = [];
foreach ($activeRelations as $colIdx => $rel) { if ($is_debtor && $rel['debtor_id'] === $personId) $targetCols[] = $colIdx; if (!$is_debtor && $rel['creditor_id'] === $personId) $targetCols[] = $colIdx; }

$transactions = []; $paidTotal = 0;
foreach ($controlRows as $rowIdx => $row) { foreach ($targetCols as $colIdx) { if (!isset($row[$colIdx])) continue; $amount = (float)$row[$colIdx]; if ($amount <= 0) continue; $desc = trim($controlDescs[$rowIdx][$colIdx] ?? ''); if (empty($desc) || $desc === $person_name) $desc = '—'; $transactions[] = ['amount' => $amount, 'desc' => $desc]; $paidTotal += $amount; } }
$remaining = $totalAmount - $paidTotal;
// ⭐ تبدیل به ریال
$totalAmount = $totalAmount * 10000000;
$paidTotal = $paidTotal * 10000000;
$remaining = $remaining * 10000000;
foreach ($transactions as &$t) {
    $t['amount'] = $t['amount'] * 10000000;
}
unset($t);

// ⭐ ساخت رسید متنی
$person_type_fa = $is_debtor ? 'بدهکار' : 'بستانکار';
$amount_label = $is_debtor ? 'کل بدهی' : 'کل طلب';

$receipt_text = '';
$receipt_text .= 'تاریخ گزارش: ' . $selected_date . "\n";
$receipt_text .= 'نام شخص: ' . $person_name . "\n";
$receipt_text .= 'نوع حساب: ' . $person_type_fa . "\n\n";
$receipt_text .= $amount_label . ': ' . number_format($totalAmount) . ' ریال' . "\n";
$receipt_text .= 'مبلغ پرداخت شده: ' . number_format($paidTotal) . ' ریال' . "\n";
$receipt_text .= 'مانده: ' . number_format($remaining) . ' ریال' . "\n\n";

if (count($transactions) > 0) {
    $receipt_text .= 'تراکنش‌ها:' . "\n";
    
    foreach ($transactions as $i => $t) {
        $row_num = $i + 1;
        $amount_str = number_format($t['amount']);
        $desc_str = $t['desc'];
        $receipt_text .= $row_num . '. ' . $amount_str . ' ریال - ' . $desc_str . "\n";
    }
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>رسید <?= htmlspecialchars($person_name) ?> - <?= $theme ?></title>
    <link href="../assets/fonts/fonts.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Vazirmatn', sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 20px; }
        .receipt { max-width: 500px; width: 100%; overflow: hidden; transition: all 0.3s ease; }
        .content { padding: 20px 25px; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        .summary { padding: 18px; margin-top: 20px; text-align: center; }
        .remaining-red { font-weight: 800; }
        .remaining-green { font-weight: 800; }
        .footer-note { text-align: center; margin-top: 20px; padding-top: 12px; display: flex; justify-content: center; align-items: center; gap: 8px; }
        .footer-note img { height: 24px; width: auto; vertical-align: middle; }
        .btn-print { display: block; text-align: center; padding: 14px; margin: 20px 25px 25px; font-weight: 700; border: none; width: calc(100% - 50px); cursor: pointer; font-family: 'Vazirmatn', sans-serif; font-size: 0.9rem; }
        
        /* ⭐ استایل دکمه رسید متنی */
        .btn-text-receipt { display: block; text-align: center; padding: 12px; margin: 0 25px 15px; font-weight: 700; border: 2px solid #4a5568; background: white; color: #4a5568; width: calc(100% - 50px); cursor: pointer; font-family: 'Vazirmatn', sans-serif; font-size: 0.85rem; border-radius: 8px; transition: all 0.2s; }
        .btn-text-receipt:hover { background: #4a5568; color: white; }
        .copy-message { text-align: center; color: #16a34a; font-size: 0.8rem; margin: 0 25px 10px; display: none; }
        
        /* کلاسیک */
        .theme-classic body { background: #e2e8f0; }
        .theme-classic .receipt { background: white; border-radius: 24px; box-shadow: 0 20px 35px -10px rgba(0,0,0,0.2); }
        .theme-classic .header { background: linear-gradient(135deg, #1e40af, #3b82f6); color: white; padding: 30px 25px; text-align: center; }
        .theme-classic .header h2 { font-size: 1.3rem; margin-bottom: 8px; font-weight: 600; }
        .theme-classic .header h3 { font-size: 1.6rem; margin-bottom: 12px; font-weight: 800; }
        .theme-classic .header p { font-size: 0.85rem; opacity: 0.9; margin: 5px 0; }
        .theme-classic .header .total { font-size: 1.1rem; margin-top: 12px; padding-top: 10px; border-top: 1px dashed rgba(255,255,255,0.3); }
        .theme-classic th { background: #f1f5f9; padding: 12px 8px; font-weight: 700; font-size: 0.85rem; color: #1e293b; border-bottom: 2px solid #e2e8f0; text-align: center; }
        .theme-classic td { padding: 10px 8px; border-bottom: 1px solid #e2e8f0; font-size: 0.9rem; text-align: center; }
        .theme-classic .summary { background: #f0fdf4; border-radius: 16px; border: 1px solid #bbf7d0; }
        .theme-classic .remaining-red { color: #dc2626; }
        .theme-classic .remaining-green { color: #16a34a; }
        .theme-classic .footer-note { border-top: 1px solid #e2e8f0; }
        .theme-classic .footer-note span { font-size: 0.7rem; color: #64748b; }
        .theme-classic .btn-print { background: #1e293b; color: white; border-radius: 40px; }
        
        /* مدرن */
        .theme-modern body { background: #f5f5f5; }
        .theme-modern .receipt { background: #ffffff; border-radius: 2px; box-shadow: 0 2px 20px rgba(0,0,0,0.08); }
        .theme-modern .header { background: #1a1a1a; color: white; padding: 40px 35px 30px; text-align: right; position: relative; }
        .theme-modern .header::after { content: ''; position: absolute; bottom: 0; left: 0; right: 0; height: 3px; background: #00c853; }
        .theme-modern .header h2 { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 3px; margin-bottom: 15px; font-weight: 300; color: #999; }
        .theme-modern .header h3 { font-size: 1.8rem; margin-bottom: 5px; font-weight: 400; }
        .theme-modern .header p { font-size: 0.8rem; color: #888; margin: 3px 0; }
        .theme-modern .header .total { font-size: 1.4rem; margin-top: 25px; font-weight: 300; }
        .theme-modern .content { padding: 35px; }
        .theme-modern th { background: transparent; padding: 15px 8px 10px; font-weight: 400; font-size: 0.7rem; color: #999; border-bottom: 1px solid #eee; text-align: right; text-transform: uppercase; letter-spacing: 1px; }
        .theme-modern td { padding: 12px 8px; border-bottom: 1px solid #f5f5f5; font-size: 0.9rem; text-align: right; color: #333; }
        .theme-modern .summary { background: #fafafa; text-align: right; border-left: 3px solid #00c853; padding: 25px; margin-top: 10px; }
        .theme-modern .remaining-red { color: #ff1744; }
        .theme-modern .remaining-green { color: #00c853; }
        .theme-modern .footer-note { margin-top: 30px; padding-top: 20px; border-top: 1px solid #f0f0f0; }
        .theme-modern .footer-note span { font-size: 0.65rem; color: #aaa; text-transform: uppercase; letter-spacing: 1px; }
        .theme-modern .footer-note img { height: 20px; opacity: 0.5; }
        .theme-modern .btn-print { background: #1a1a1a; color: white; border-radius: 0; margin: 0; width: 100%; text-transform: uppercase; letter-spacing: 2px; font-size: 0.8rem; }
        .theme-modern .btn-text-receipt { margin: 0; width: 100%; border-radius: 0; border-color: #1a1a1a; color: #1a1a1a; }
        .theme-modern .btn-text-receipt:hover { background: #1a1a1a; color: white; }
        
        /* لوکس */
        .theme-luxury body { background: linear-gradient(135deg, #0a0a0a, #1a1a2e); }
        .theme-luxury .receipt { background: linear-gradient(145deg, #1a1a1a, #2d2d2d); border-radius: 16px; box-shadow: 0 30px 60px rgba(0,0,0,0.5), 0 0 0 1px rgba(212,175,55,0.1); color: #e0d5c0; }
        .theme-luxury .header { background: linear-gradient(180deg, rgba(212,175,55,0.1), rgba(212,175,55,0.02)); padding: 40px 30px 35px; text-align: center; border-bottom: 1px solid rgba(212,175,55,0.2); }
        .theme-luxury .header h2 { font-size: 0.8rem; text-transform: uppercase; letter-spacing: 4px; margin-bottom: 20px; font-weight: 300; color: #d4af37; }
        .theme-luxury .header h3 { font-size: 2rem; margin-bottom: 8px; font-weight: 400; color: #ffd700; }
        .theme-luxury .header p { font-size: 0.75rem; color: #999; margin: 3px 0; }
        .theme-luxury .header .total { font-size: 1.2rem; margin-top: 25px; color: #d4af37; }
        .theme-luxury .content { padding: 35px 30px; }
        .theme-luxury th { background: rgba(212,175,55,0.05); padding: 15px 10px; font-weight: 400; font-size: 0.7rem; color: #d4af37; border-bottom: 1px solid rgba(212,175,55,0.2); text-align: center; text-transform: uppercase; }
        .theme-luxury td { padding: 14px 10px; border-bottom: 1px solid rgba(255,255,255,0.05); font-size: 0.9rem; text-align: center; color: #c0b090; }
        .theme-luxury .summary { background: rgba(212,175,55,0.05); padding: 25px; margin-top: 10px; border: 1px solid rgba(212,175,55,0.2); border-radius: 4px; }
        .theme-luxury .remaining-red { color: #ff6b6b; font-weight: 600; font-size: 1.1rem; }
        .theme-luxury .remaining-green { color: #4caf50; font-weight: 600; font-size: 1.1rem; }
        .theme-luxury .footer-note { margin-top: 25px; padding-top: 20px; border-top: 1px solid rgba(212,175,55,0.1); }
        .theme-luxury .footer-note span { font-size: 0.65rem; color: #666; text-transform: uppercase; letter-spacing: 2px; }
        .theme-luxury .footer-note img { height: 22px; opacity: 0.4; filter: grayscale(100%) brightness(200%); }
        .theme-luxury .btn-print { background: linear-gradient(135deg, #d4af37, #ffd700); color: #1a1a1a; border-radius: 0; margin: 0; width: 100%; text-transform: uppercase; letter-spacing: 2px; font-size: 0.85rem; }
        .theme-luxury .btn-text-receipt { margin: 0; width: 100%; border-radius: 0; border-color: #d4af37; color: #d4af37; background: transparent; }
        .theme-luxury .btn-text-receipt:hover { background: rgba(212,175,55,0.1); }
        
        @media print { body { background: white !important; padding: 0 !important; } .btn-print, .btn-text-receipt, .copy-message { display: none !important; } .receipt { box-shadow: none !important; border-radius: 0 !important; max-width: 100% !important; } }
    </style>
</head>
<body class="theme-<?= $theme ?>">
    <div class="receipt">
        <div class="header">
            <h2><?= $is_debtor ? 'رسید پرداختی' : 'صورتحساب دریافتی' ?></h2>
            <h3><?= htmlspecialchars($person_name) ?></h3>
            <p>تاریخ معامله: <?= str_replace('-', '/', $selected_date) ?></p>
            <div class="total"><?= $is_debtor ? 'کل بدهی' : 'کل طلب' ?>: <?= number_format($totalAmount) ?> ریال</div>
        </div>
        <div class="content">
            <?php if (!empty($transactions)): ?>
            <table>
                <thead><tr><th>ردیف</th><th>مبلغ (ریال)</th><th>شرح</th></tr></thead>
                <tbody>
                    <?php $i=1; foreach ($transactions as $t): ?>
                    <tr><td><?= $i++ ?></td><td><?= number_format($t['amount']) ?></td><td><?= htmlspecialchars($t['desc']) ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <p style="text-align:center;color:#64748b;">موردی یافت نشد</p>
            <?php endif; ?>
            <div class="summary">
                <p><strong>جمع پرداختی:</strong> <?= number_format($paidTotal) ?> ریال</p>
                <?php if ($remaining > 0): ?>
                    <p class="remaining-red"><strong><?= $is_debtor ? 'مانده بدهی' : 'مانده طلب' ?>:</strong> <?= number_format($remaining) ?> ریال</p>
                <?php elseif ($remaining == 0): ?>
                    <p class="remaining-green"><strong>✓ تسویه کامل</strong></p>
                <?php else: ?>
                    <p class="remaining-green"><strong>بستانکاری:</strong> <?= number_format(abs($remaining)) ?> ریال</p>
                <?php endif; ?>
            </div>
            <div class="footer-note">
                <span>ساخته شده توسط</span>
                <img src="../assets/images/logo2.png" alt="لوگو" onerror="this.style.display='none'">
            </div>
        </div>
        
        <!-- ⭐ دکمه رسید متنی -->
        <button class="btn-text-receipt" onclick="copyTextReceipt()">دریافت رسید متنی</button>
        <div class="copy-message" id="copyMessage">رسید متنی با موفقیت کپی شد</div>
        
        <button class="btn-print" onclick="window.print()"><?= $theme == 'luxury' ? '✦ چاپ رسید ✦' : '🖨 چاپ / دریافت PDF' ?></button>
    </div>
    
    <!-- ⭐ textarea مخفی برای ذخیره متن رسید -->
    <textarea id="hiddenReceiptText" style="display:none;"><?= htmlspecialchars($receipt_text) ?></textarea>
    
    <script>
    function copyTextReceipt() {
        const receiptText = document.getElementById('hiddenReceiptText').value;
        
        if (navigator.clipboard) {
            navigator.clipboard.writeText(receiptText).then(() => {
                showMessage();
            }).catch(() => {
                fallbackCopy(receiptText);
            });
        } else {
            fallbackCopy(receiptText);
        }
    }
    
    function fallbackCopy(text) {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        showMessage();
    }
    
    function showMessage() {
        const msg = document.getElementById('copyMessage');
        msg.style.display = 'block';
        setTimeout(() => {
            msg.style.display = 'none';
        }, 3000);
    }
    </script>
</body>
</html>