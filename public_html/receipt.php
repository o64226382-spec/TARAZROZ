<?php
define('SECURE_ACCESS', true);
require_once 'includes/config.php';
session_start();

// ⭐ اصلاح: چک لاگین به جای چک نقش receipt
if (!isset($_SESSION['user_id'])) {
    die('دسترسی غیرمجاز - لطفاً وارد شوید');
}

// ⭐ فقط branch و admin می‌تونن رسید بگیرن
$allowed_roles = ['branch', 'admin'];
if (!in_array($_SESSION['role'] ?? '', $allowed_roles)) {
    die('دسترسی غیرمجاز - فقط کاربر شعبه و مدیر می‌توانند رسید دریافت کنند');
}

$branch_id = (int)($_GET['branch_id'] ?? 0);
$selected_date = $_GET['date'] ?? '';
$person_input = $_GET['person'] ?? '';

if (!$branch_id || !$selected_date || !$person_input) {
    die('اطلاعات ناقص است');
}

list($person_name, $person_type) = explode('|', $person_input);
$is_debtor = ($person_type === 'debtor');

// ⭐ Prepared Statement
$stmt = mysqli_prepare($conn, "SELECT report_data FROM daily_reports WHERE user_id = ? AND report_date = ?");
mysqli_stmt_bind_param($stmt, "is", $branch_id, $selected_date);
mysqli_stmt_execute($stmt);
$report = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$report) die('گزارشی برای این تاریخ یافت نشد');

$data = json_decode($report['report_data'], true);

$totalAmount = 0;
$personId = 0;

if ($is_debtor) {
    foreach (($data['debtors'] ?? []) as $d) {
        if ($d['name'] === $person_name) {
            $totalAmount = (float)($d['amt'] ?? 0);
            $personId = $d['id'];
            break;
        }
    }
} else {
    foreach (($data['creditors'] ?? []) as $c) {
        if ($c['name'] === $person_name) {
            $totalAmount = (float)($c['amt'] ?? 0);
            $personId = $c['id'];
            break;
        }
    }
}

if ($totalAmount <= 0) die('مبلغی برای این شخص ثبت نشده است');

$controlRows   = $data['controlRows'] ?? [];
$controlDescs  = $data['controlDescs'] ?? [];
$matrixVals    = $data['matrixValues'] ?? [];
$debtorsList   = $data['debtors'] ?? [];
$creditorsList = $data['creditors'] ?? [];

// ⭐ ساخت activeRelations
$activeRelations = [];
foreach ($creditorsList as $c) {
    foreach ($debtorsList as $d) {
        $key = $c['id'] . '_' . $d['id'];
        $val = (float)($matrixVals[$key] ?? 0);
        if ($val > 0) {
            $activeRelations[] = [
                'creditor_id' => $c['id'],
                'debtor_id'   => $d['id'],
                'creditor'    => $c['name'],
                'debtor'      => $d['name'],
                'target'      => $val,
                'title'       => $d['name'] . ' به ' . $c['name']
            ];
        }
    }
}

// ⭐ پیدا کردن ستون‌های مربوط به این شخص
$targetCols = [];
foreach ($activeRelations as $colIdx => $rel) {
    if ($is_debtor && isset($rel['debtor_id']) && $rel['debtor_id'] === $personId) {
        $targetCols[] = $colIdx;
    } elseif (!$is_debtor && isset($rel['creditor_id']) && $rel['creditor_id'] === $personId) {
        $targetCols[] = $colIdx;
    }
}

// ⭐ استخراج تراکنش‌ها
$transactions = [];
$paidTotal = 0;

foreach ($controlRows as $rowIdx => $row) {
    foreach ($targetCols as $colIdx) {
        if (!isset($row[$colIdx])) continue;
        $amount = (float)$row[$colIdx];
        if ($amount <= 0) continue;
        
        $desc = '';
        if (isset($controlDescs[$rowIdx][$colIdx])) {
            $desc = trim($controlDescs[$rowIdx][$colIdx]);
        }
        if ($desc === '' || $desc === $person_name) $desc = '—';
        
        $transactions[] = ['amount' => $amount, 'desc' => $desc];
        $paidTotal += $amount;
    }
}

$remaining = $totalAmount - $paidTotal;

// ⭐ فونت و تم
$font_path = $_GET['font'] ?? 'assets/fonts/Vazirmatn-Medium.ttf';
$font_name = $_GET['font_name'] ?? 'Vazirmatn';
$theme = $_GET['theme'] ?? 'classic';

if (!file_exists($font_path)) {
    $font_path = 'assets/fonts/Vazirmatn-Medium.ttf';
    $font_name = 'Vazirmatn';
}

// ⭐ ذخیره در session و هدایت به print.php
$_SESSION['receipt_data'] = [
    'person_name'       => $person_name,
    'is_debtor'         => $is_debtor,
    'selected_date'     => $selected_date,
    'totalAmount'       => $totalAmount,
    'transactions'      => $transactions,
    'paidTotal'         => $paidTotal,
    'remaining'         => $remaining,
    'font_path'         => $font_path,
    'font_name'         => $font_name,
    'theme'             => $theme
];

header('Location: print.php');
exit;