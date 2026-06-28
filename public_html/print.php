<?php
session_start();

if (!isset($_SESSION['receipt_data'])) {
    die('اطلاعاتی برای چاپ وجود ندارد.');
}

$data = $_SESSION['receipt_data'];
$person_name = $data['person_name'];
$is_debtor = $data['is_debtor'];
$selected_date = $data['selected_date'];
$totalAmount = $data['totalAmount'] * 10000000;
$transactions = $data['transactions'];
$paidTotal = $data['paidTotal'] * 10000000;
$remaining = $data['remaining'] * 10000000;
$font_path = $data['font_path'] ?? 'assets/fonts/Vazirmatn-Medium.ttf';
$font_name = $data['font_name'] ?? 'Vazirmatn';
$theme = $data['theme'] ?? 'classic';

// ضرب مقادیر تراکنش‌ها
foreach ($transactions as &$t) {
    $t['amount'] = $t['amount'] * 10000000;
}
unset($t);

unset($_SESSION['receipt_data']);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>رسید <?= htmlspecialchars($person_name) ?> - <?= $theme ?></title>
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
    
    <style>
        @font-face {
            font-family: 'CustomReceiptFont';
            src: url('<?= $font_path ?>') format('truetype');
            font-weight: normal;
            font-style: normal;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'CustomReceiptFont', 'Vazirmatn', sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        
        .receipt {
            max-width: 500px;
            width: 100%;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .content {
            padding: 20px 25px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        
        .summary {
            padding: 18px;
            margin-top: 20px;
            text-align: center;
        }
        
        .summary p {
            margin: 5px 0;
        }
        
        .remaining-red {
            font-weight: 800;
        }
        
        .remaining-green {
            font-weight: 800;
        }
        
        .footer-note {
            text-align: center;
            margin-top: 20px;
            padding-top: 12px;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
        }
        
        .footer-note img {
            height: 24px;
            width: auto;
            vertical-align: middle;
        }
        
        .btn-print {
            display: block;
            text-align: center;
            padding: 14px;
            margin: 20px 25px 25px;
            text-decoration: none;
            font-weight: 700;
            transition: 0.2s;
            border: none;
            width: calc(100% - 50px);
            cursor: pointer;
            font-family: 'CustomReceiptFont', 'Vazirmatn', sans-serif;
            font-size: 0.9rem;
        }
        
        /* ============ تم کلاسیک ============ */
        .theme-classic body {
            background: #e2e8f0;
        }
        .theme-classic .receipt {
            background: white;
            border-radius: 24px;
            box-shadow: 0 20px 35px -10px rgba(0,0,0,0.2);
        }
        .theme-classic .header {
            background: linear-gradient(135deg, #1e40af, #3b82f6);
            color: white;
            padding: 30px 25px;
            text-align: center;
        }
        .theme-classic .header h2 {
            font-size: 1.3rem;
            margin-bottom: 8px;
            font-weight: 600;
        }
        .theme-classic .header h3 {
            font-size: 1.6rem;
            margin-bottom: 12px;
            font-weight: 800;
        }
        .theme-classic .header p {
            font-size: 0.85rem;
            opacity: 0.9;
            margin: 5px 0;
        }
        .theme-classic .header .total {
            font-size: 1.1rem;
            margin-top: 12px;
            padding-top: 10px;
            border-top: 1px dashed rgba(255,255,255,0.3);
        }
        .theme-classic th {
            background: #f1f5f9;
            padding: 12px 8px;
            font-weight: 700;
            font-size: 0.85rem;
            color: #1e293b;
            border-bottom: 2px solid #e2e8f0;
            text-align: center;
        }
        .theme-classic td {
            padding: 10px 8px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 0.9rem;
            text-align: center;
        }
        .theme-classic .summary {
            background: #f0fdf4;
            border-radius: 16px;
            border: 1px solid #bbf7d0;
        }
        .theme-classic .remaining-red { color: #dc2626; }
        .theme-classic .remaining-green { color: #16a34a; }
        .theme-classic .footer-note { border-top: 1px solid #e2e8f0; }
        .theme-classic .footer-note span { font-size: 0.7rem; color: #64748b; }
        .theme-classic .footer-note img {
            border-radius: 8px;
            padding: 2px;
            background: #f8fafc;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .theme-classic .btn-print {
            background: #1e293b;
            color: white;
            border-radius: 40px;
        }
        
        /* ============ تم مدرن ============ */
        .theme-modern body {
            background: #f5f5f5;
        }
        .theme-modern .receipt {
            background: #ffffff;
            border-radius: 2px;
            box-shadow: 0 2px 20px rgba(0,0,0,0.08);
        }
        .theme-modern .header {
            background: #1a1a1a;
            color: white;
            padding: 40px 35px 30px;
            text-align: right;
            position: relative;
        }
        .theme-modern .header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: #00c853;
        }
        .theme-modern .header h2 {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 3px;
            margin-bottom: 15px;
            font-weight: 300;
            color: #999;
        }
        .theme-modern .header h3 {
            font-size: 1.8rem;
            margin-bottom: 5px;
            font-weight: 400;
            letter-spacing: -0.5px;
        }
        .theme-modern .header p {
            font-size: 0.8rem;
            color: #888;
            margin: 3px 0;
        }
        .theme-modern .header .total {
            font-size: 1.4rem;
            margin-top: 25px;
            font-weight: 300;
            letter-spacing: -0.5px;
        }
        .theme-modern .content { padding: 35px; }
        .theme-modern th {
            background: transparent;
            padding: 15px 8px 10px;
            font-weight: 400;
            font-size: 0.7rem;
            color: #999;
            border-bottom: 1px solid #eee;
            text-align: right;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .theme-modern td {
            padding: 12px 8px;
            border-bottom: 1px solid #f5f5f5;
            font-size: 0.9rem;
            text-align: right;
            color: #333;
        }
        .theme-modern .summary {
            background: #fafafa;
            text-align: right;
            border-left: 3px solid #00c853;
            padding: 25px;
            margin-top: 10px;
        }
        .theme-modern .summary p {
            margin: 8px 0;
            font-size: 0.9rem;
            color: #555;
            display: flex;
            justify-content: space-between;
        }
        .theme-modern .remaining-red { color: #ff1744; }
        .theme-modern .remaining-green { color: #00c853; }
        .theme-modern .footer-note {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #f0f0f0;
        }
        .theme-modern .footer-note span {
            font-size: 0.65rem;
            color: #aaa;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .theme-modern .footer-note img { height: 20px; opacity: 0.5; }
        .theme-modern .btn-print {
            background: #1a1a1a;
            color: white;
            border-radius: 0;
            margin: 0;
            width: 100%;
            text-transform: uppercase;
            letter-spacing: 2px;
            font-size: 0.8rem;
        }
        
        /* ============ تم لوکس ============ */
        .theme-luxury body {
            background: linear-gradient(135deg, #0a0a0a 0%, #1a1a2e 100%);
        }
        .theme-luxury .receipt {
            background: linear-gradient(145deg, #1a1a1a, #2d2d2d);
            border-radius: 16px;
            box-shadow: 0 30px 60px rgba(0,0,0,0.5), 0 0 0 1px rgba(212,175,55,0.1);
            color: #e0d5c0;
            position: relative;
        }
        .theme-luxury .receipt::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, #d4af37, #ffd700, #d4af37, transparent);
            z-index: 1;
        }
        .theme-luxury .header {
            background: linear-gradient(180deg, rgba(212,175,55,0.1) 0%, rgba(212,175,55,0.02) 100%);
            padding: 40px 30px 35px;
            text-align: center;
            position: relative;
            border-bottom: 1px solid rgba(212,175,55,0.2);
        }
        .theme-luxury .header::after {
            content: '◆';
            position: absolute;
            bottom: -8px;
            left: 50%;
            transform: translateX(-50%);
            color: #d4af37;
            font-size: 12px;
        }
        .theme-luxury .header h2 {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 4px;
            margin-bottom: 20px;
            font-weight: 300;
            color: #d4af37;
        }
        .theme-luxury .header h3 {
            font-size: 2rem;
            margin-bottom: 8px;
            font-weight: 400;
            color: #ffd700;
            letter-spacing: -0.5px;
        }
        .theme-luxury .header p {
            font-size: 0.75rem;
            color: #999;
            margin: 3px 0;
        }
        .theme-luxury .header .total {
            font-size: 1.2rem;
            margin-top: 25px;
            color: #d4af37;
            font-weight: 300;
            letter-spacing: 0.5px;
        }
        .theme-luxury .content { padding: 35px 30px; }
        .theme-luxury th {
            background: rgba(212,175,55,0.05);
            padding: 15px 10px;
            font-weight: 400;
            font-size: 0.7rem;
            color: #d4af37;
            border-bottom: 1px solid rgba(212,175,55,0.2);
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .theme-luxury td {
            padding: 14px 10px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            font-size: 0.9rem;
            text-align: center;
            color: #c0b090;
        }
        .theme-luxury .summary {
            background: rgba(212,175,55,0.05);
            padding: 25px;
            margin-top: 10px;
            border: 1px solid rgba(212,175,55,0.2);
            border-radius: 4px;
        }
        .theme-luxury .summary p {
            margin: 10px 0;
            font-size: 0.9rem;
            color: #c0b090;
        }
        .theme-luxury .remaining-red {
            color: #ff6b6b;
            font-weight: 600;
            font-size: 1.1rem;
        }
        .theme-luxury .remaining-green {
            color: #4caf50;
            font-weight: 600;
            font-size: 1.1rem;
        }
        .theme-luxury .footer-note {
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid rgba(212,175,55,0.1);
        }
        .theme-luxury .footer-note span {
            font-size: 0.65rem;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        .theme-luxury .footer-note img {
            height: 22px;
            opacity: 0.4;
            filter: grayscale(100%) brightness(200%);
        }
        .theme-luxury .btn-print {
            background: linear-gradient(135deg, #d4af37, #ffd700);
            color: #1a1a1a;
            border-radius: 0;
            margin: 0;
            width: 100%;
            text-transform: uppercase;
            letter-spacing: 2px;
            font-size: 0.85rem;
        }
        
        @media print {
            body {
                background: white !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .btn-print {
                display: none !important;
            }
            .receipt {
                box-shadow: none !important;
                border-radius: 0 !important;
                max-width: 100% !important;
            }
        }
    </style>
</head>
<body class="theme-<?= $theme ?>">
    <div class="receipt" id="receipt">
        <div class="header">
            <h2><?= $is_debtor ? 'رسید پرداختی' : 'صورتحساب دریافتی' ?></h2>
            <h3><?= htmlspecialchars($person_name) ?></h3>
            <p>تاریخ معامله: <?= str_replace('-', '/', $selected_date) ?></p>
            <div class="total">
                <?= $is_debtor ? 'کل بدهی' : 'کل طلب' ?>: <?= number_format($totalAmount) ?> ریال
            </div>
        </div>

        <div class="content">
            <?php if (!empty($transactions)): ?>
            <table>
                <thead>
                    <tr><th>ردیف</th><th>مبلغ (ریال)</th><th>شرح</th></tr>
                </thead>
                <tbody>
                    <?php $i=1; foreach ($transactions as $t): ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td><?= number_format($t['amount']) ?></td>
                        <td><?= htmlspecialchars($t['desc']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <p style="text-align:center; color:#64748b;">موردی یافت نشد</p>
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
                <img src="assets/images/logo2.png" alt="لوگو" onerror="this.style.display='none'">
            </div>
        </div>

        <button class="btn-print" onclick="window.print()">
            <?= $theme == 'luxury' ? '✦ چاپ رسید ✦' : '🖨 چاپ / دریافت PDF' ?>
        </button>
    </div>
</body>
</html>