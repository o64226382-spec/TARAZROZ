<?php
define('SECURE_ACCESS', true);
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/reminder_functions.php';  // ← اینو اضافه کن
require_once '../includes/jdf.php';

requireLogin();
redirectIfAdmin();
if (isObserver()) { header('Location: ../observer/index.php'); exit(); }

$user = getCurrentUser();
$branch_name = $user['branch_name'];

// ✅ دریافت تاریخ از URL (همیشه از تقویم اصلی)
$selected_date = isset($_GET['date']) ? $_GET['date'] : jdate('Y-m-d');
$selected_date = str_replace('/', '-', $selected_date);
$selected_date = str_replace(['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'], ['0','1','2','3','4','5','6','7','8','9'], $selected_date);
$back_year = substr($selected_date, 0, 4);
$back_month = substr($selected_date, 5, 2);
$user_id = $_SESSION['user_id'];

// ⭐ دریافت گزارش ذخیره‌شده
$stmt = mysqli_prepare($conn, "SELECT id, report_data FROM daily_reports WHERE user_id = ? AND report_date = ?");
mysqli_stmt_bind_param($stmt, "is", $user_id, $selected_date);
mysqli_stmt_execute($stmt);
$report_result = mysqli_stmt_get_result($stmt);
$saved_report = mysqli_fetch_assoc($report_result);
$report_id = $saved_report['id'] ?? 0;
$report_json = $saved_report['report_data'] ?? 'null';

// ⭐ دریافت پیام‌ها
$messages = [];
if ($report_id > 0) {
    $stmt = mysqli_prepare($conn, "SELECT c.*, u.username, u.branch_name FROM comments c 
                  JOIN users u ON c.user_id = u.id 
                  WHERE c.report_id = ? 
                  ORDER BY c.created_at ASC");
    mysqli_stmt_bind_param($stmt, "i", $report_id);
    mysqli_stmt_execute($stmt);
    $msg_result = mysqli_stmt_get_result($stmt);
    while ($m = mysqli_fetch_assoc($msg_result)) $messages[] = $m;
    
    $stmt = mysqli_prepare($conn, "UPDATE comments SET is_read_by_branch = 1 WHERE report_id = ? AND sender_role = 'observer'");
    mysqli_stmt_bind_param($stmt, "i", $report_id);
    mysqli_stmt_execute($stmt);
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>تراز روزانه | <?php echo $branch_name; ?></title>
    <link rel="icon" type="image/png" href="../assets/images/logo.png">
    <link href="../assets/fonts/fonts.css" rel="stylesheet">
    <style>
        :root {
            --bg: #f5f6f8;
            --card: #ffffff;
            --input: #f8fafc;
            --item: #f1f5f9;
            --border: #e2e8f0;
            --border-lite: #cbd5e1;
            --text-1: #1e293b;
            --text-2: #475569;
            --text-3: #64748b;
            --accent: #3b5998;
            --debtor: #047857;
            --debtor-bg: #ecfdf5;
            --debtor-border: #a7f3d0;
            --creditor: #b91c1c;
            --creditor-bg: #fef2f2;
            --creditor-border: #fecaca;
            --purple: #6d28d9;
            --purple-bg: #f5f3ff;
            --purple-border: #ddd6fe;
            --banker: #b45309;
            --banker-bg: #fffbeb;
            --banker-border: #fde68a;
            --green: #10b981;
            --green-bg: rgba(16,185,129,0.1);
            --toast-success: #059669;
            --toast-error: #dc2626;
            --r-xs: 4px;
            --r-sm: 6px;
            --r-md: 8px;
            --r-lg: 10px;
        }

        body.dark {
            --bg: #0f172a;
            --card: #1e293b;
            --input: #1e293b;
            --item: #334155;
            --border: #334155;
            --border-lite: #475569;
            --text-1: #f1f5f9;
            --text-2: #cbd5e1;
            --text-3: #94a3b8;
            --accent: #60a5fa;
            --debtor: #34d399;
            --debtor-bg: #064e3b;
            --debtor-border: #047857;
            --creditor: #f87171;
            --creditor-bg: #7f1d1d;
            --creditor-border: #991b1b;
            --purple: #a78bfa;
            --purple-bg: #3b0764;
            --purple-border: #4c1d95;
            --banker: #fbbf24;
            --banker-bg: #451a03;
            --banker-border: #78350f;
            --green: #34d399;
            --green-bg: rgba(52,211,153,0.15);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Vazirmatn', sans-serif;
            background: var(--bg);
            color: var(--text-1);
            padding: 16px 12px;
            direction: rtl;
            min-height: 100vh;
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        .wrapper { max-width: 1480px; margin: 0 auto; display: flex; flex-direction: column; gap: 16px; }

        .app-header {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--r-md);
            padding: 12px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.04);
        }
        .app-brand { display: flex; align-items: center; gap: 10px; }
        .app-logo { height: 32px; width: auto; }
        .app-title { font-size: 1.1rem; font-weight: 700; color: var(--text-1); }

        .header-actions { display: flex; align-items: center; gap: 12px; }
        .theme-toggle {
            display: flex;
            align-items: center;
            gap: 6px;
            background: var(--input);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 5px 12px;
            cursor: pointer;
            font-family: 'Vazirmatn', sans-serif;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-2);
            transition: all 0.2s;
            white-space: nowrap;
        }
        .theme-toggle:hover { border-color: var(--accent); color: var(--accent); }
        .theme-dot {
            width: 10px; height: 10px;
            border-radius: 50%;
            background: var(--text-3);
            transition: background 0.3s;
        }
        .dark .theme-dot { background: #fbbf24; }

        .logout-btn {
            background: var(--input);
            border: 1px solid var(--border);
            border-radius: var(--r-sm);
            padding: 6px 14px;
            color: var(--text-2);
            font-weight: 600;
            text-decoration: none;
            font-size: 0.8rem;
            transition: all 0.2s;
        }
        .logout-btn:hover { border-color: var(--text-3); }

        .panel {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--r-md);
            overflow: hidden;
            margin-bottom: 16px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.04);
        }
        .panel-body { padding: 16px; }

        .date-bar-new {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            padding: 12px 16px;
            background: var(--input);
            border: 1px solid var(--border);
            border-radius: var(--r-md);
        }
        .date-display {
            font-weight: 700;
            font-size: 1rem;
            color: var(--accent);
            background: var(--card);
            padding: 6px 16px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .date-actions { display: flex; gap: 8px; margin-right: auto; }
        .btn-date-action {
            background: transparent;
            border: 1px solid var(--border);
            color: var(--text-2);
            padding: 6px 14px;
            border-radius: var(--r-sm);
            cursor: pointer;
            font-family: 'Vazirmatn', sans-serif;
            font-size: 0.8rem;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-date-action.delete { color: var(--creditor); border-color: var(--creditor-border); }
        .btn-date-action.delete:hover { background: var(--creditor-bg); }

        .input-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; margin-bottom: 14px; }
        @media (max-width: 900px) { .input-row { grid-template-columns: 1fr; } }

        .icard { border-radius: var(--r-md); border: 1px solid var(--border); overflow: hidden; background: var(--input); }
        .icard-head { padding: 10px 14px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border); }
        .icard-head.debtors { background: var(--debtor-bg); color: var(--debtor); }
        .icard-head.creditors { background: var(--creditor-bg); color: var(--creditor); }
        .icard-head.petty { background: var(--purple-bg); color: var(--purple); }
        .icard-head.bankers { background: var(--banker-bg); color: var(--banker); }
        .icard-title { font-size: 0.9em; font-weight: 700; }
        .sum-chip { background: var(--card); color: var(--text-2); border: 1px solid var(--border); border-radius: 20px; padding: 2px 10px; font-size: 0.75em; font-weight: 700; }
        .icard-body { padding: 10px 12px; }
        .ceiling-wrap { display: flex; align-items: center; gap: 8px; background: var(--purple-bg); border: 1px solid var(--purple-border); border-radius: var(--r-sm); padding: 6px 10px; margin-bottom: 10px; }
        .ceiling-wrap label { font-size: 0.8em; color: var(--purple); white-space: nowrap; font-weight: 600; }
        .ceiling-wrap input { flex: 1; border: none; outline: none; font-family: 'Vazirmatn', sans-serif; font-size: 0.9em; text-align: center; background: transparent; color: var(--text-1); }
        .item-list { display: flex; flex-direction: column; gap: 6px; margin-bottom: 8px; }
        .item-row { display: flex; align-items: center; gap: 6px; background: var(--item); border: 1px solid var(--border); border-radius: var(--r-sm); padding: 5px 10px; }
        .item-row input[type="text"] { flex: 2; border: none; outline: none; font-family: 'Vazirmatn', sans-serif; font-size: 0.85em; background: transparent; color: var(--text-1); }
        .item-row input[type="number"] { flex: 1.2; border: none; outline: none; font-family: 'Vazirmatn', sans-serif; font-size: 0.9em; font-weight: 600; text-align: center; background: transparent; color: var(--text-1); }
        .btn-rm { background: none; border: none; color: var(--text-3); cursor: pointer; padding: 2px 8px; font-size: 1em; }
        .btn-rm:hover { color: var(--creditor); }
        .btn-add { display: flex; align-items: center; justify-content: center; gap: 6px; width: 100%; padding: 8px 0; border: 1px dashed var(--border-lite); border-radius: var(--r-sm); cursor: pointer; font-size: 0.8em; color: var(--text-3); background: transparent; font-family: 'Vazirmatn', sans-serif; transition: all 0.2s; }
        .btn-add:hover { border-color: var(--accent); color: var(--accent); }
        .deficit-strip { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px; }
        @media (max-width: 600px) { .deficit-strip { grid-template-columns: 1fr; } }
        .def-card { border-radius: var(--r-md); border: 1px solid var(--border); background: var(--input); padding: 14px 18px; display: flex; align-items: center; justify-content: space-between; }
        .def-label { font-weight: 700; font-size: 0.9em; color: var(--text-2); }
        .def-value { font-size: 1.2em; font-weight: 800; }
        .pos { color: var(--debtor); }
        .neg { color: var(--creditor); }
        .zer { color: var(--text-3); }
        .action-row { display: flex; justify-content: center; margin-bottom: 20px; }
        .btn-gen { background: var(--accent); color: #ffffff; border: none; border-radius: var(--r-sm); padding: 10px 24px; font-family: 'Vazirmatn', sans-serif; font-weight: 700; cursor: pointer; font-size: 0.9em; transition: opacity 0.2s; }
        .btn-gen:hover { opacity: 0.9; }
        .matrix-scroll { overflow-x: auto; border-radius: var(--r-md); border: 1px solid var(--border); background: var(--card); }
        .matrix-scroll table thead th { position: sticky; top: 0; background: var(--input); z-index: 2; }
        table.mx { border-collapse: collapse; min-width: 100%; font-size: 0.8em; table-layout: fixed; }
        table.mx th, table.mx td { border: 1px solid var(--border); padding: 8px 6px; text-align: center; }
        .th-corner { background: var(--item); color: var(--text-2); font-weight: 700; }
        .th-dname { background: var(--debtor-bg); color: var(--debtor); font-weight: 700; }
        .td-cname { background: var(--creditor-bg); color: var(--creditor); font-weight: 700; }
        .td-inp input { width: 90%; padding: 8px 4px; text-align: center; background: var(--card); border: 1px solid var(--border); border-radius: 5px; font-family: 'Vazirmatn', sans-serif; font-weight: 600; }
        .tfoot-bal { background: var(--debtor-bg); color: var(--debtor); font-weight: 700; }
        .tfoot-sum { background: var(--item); color: var(--text-2); font-weight: 700; }
        .export-container { display: flex; justify-content: center; gap: 12px; margin: 25px 0 120px; flex-wrap: wrap; }
        .btn-export { border: none; border-radius: var(--r-sm); padding: 12px 24px; font-family: 'Vazirmatn', sans-serif; font-weight: 700; cursor: pointer; color: #fff; transition: opacity 0.2s; }
        .btn-save { background: #475569; }
        .btn-pdf { background: #334155; }
        .btn-export:hover { opacity: 0.9; }
        .chat-section { margin-top: 30px; border-top: 2px solid var(--border); padding-top: 20px; margin-bottom: 80px; }
        .chat-messages { max-height: 300px; overflow-y: auto; margin-bottom: 15px; border: 1px solid var(--border); border-radius: 10px; padding: 15px; background: var(--card); }
        .chat-message { display: flex; gap: 10px; margin-bottom: 12px; }
        .chat-message.observer { justify-content: flex-end; }
        .chat-bubble { max-width: 70%; padding: 10px 14px; border-radius: 10px; background: var(--banker-bg); color: var(--text-1); }
        .chat-message.branch .chat-bubble { background: var(--input); }
        .chat-time { font-size: 0.7rem; color: var(--text-3); margin-top: 4px; text-align: left; }
        .reply-area { margin-top: 15px; display: none; }
        .reply-area textarea { width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border); font-family: 'Vazirmatn', sans-serif; resize: vertical; background: var(--card); color: var(--text-1); }
        .reply-area button { background: var(--accent); color: white; border: none; padding: 10px 20px; border-radius: 8px; margin-top: 10px; cursor: pointer; font-weight: 700; font-family: 'Vazirmatn', sans-serif; }
        .ctrl-cell { min-width: 160px; padding: 6px !important; }
        .ctrl-amount { width: 100%; padding: 6px; text-align: center; background: var(--card); border: 1px solid var(--border); border-radius: 5px; font-family: 'Vazirmatn', sans-serif; font-weight: 600; }
        .ctrl-desc { width: 100%; padding: 6px; margin-top: 5px; text-align: right; background: var(--input); border: 1px solid var(--border-lite); border-radius: 5px; font-family: 'Vazirmatn', sans-serif; font-size: 0.75em; }
        #control-list-wrapper table thead th { position: sticky; top: 0; background: var(--purple-bg); color: var(--purple); z-index: 2; font-weight: 700; }
        .toast-container { position: fixed; bottom: 80px; right: 20px; z-index: 9999; display: flex; flex-direction: column; gap: 8px; }
        .toast { padding: 12px 20px; border-radius: 10px; color: white; font-size: 0.9rem; font-weight: 600; box-shadow: 0 4px 12px rgba(0,0,0,0.15); animation: slideIn 0.3s ease; }
        .toast.success { background: var(--toast-success); }
        .toast.error { background: var(--toast-error); }
        @keyframes slideIn { from { opacity: 0; transform: translateX(50px); } to { opacity: 1; transform: translateX(0); } }
        .print-only-header { display: none; }
        
        /* ⭐ استایل فیلدهای داینامیک */
        .dyn-input {
            flex: 1.2;
            border: none;
            outline: none;
            font-family: 'Vazirmatn', sans-serif;
            font-size: 0.9em;
            font-weight: 600;
            text-align: left;
            direction: ltr;
            background: transparent;
            color: var(--text-1);
        }

        @media print {
            .print-only-header { display: block !important; text-align: center !important; border-bottom: 2px solid #000 !important; padding-bottom: 10px !important; margin-bottom: 20px !important; }
            .app-header, .date-bar-new, .export-container, .chat-section, button, .btn-rm, .btn-add, .toast-container, .theme-toggle { display: none !important; }
            body { background: #fff !important; color: #000 !important; font-family: Tahoma, Arial, sans-serif !important; font-size: 10pt !important; }
            input { border: none !important; background: transparent !important; color: #000 !important; box-shadow: none !important; text-align: center !important; font-size: 10pt !important; padding: 0 !important; width: 100% !important; -moz-appearance: textfield !important; }
            .wrapper, .panel, .panel-body, .icard, .def-card, .input-row, .deficit-strip { display: block !important; border: none !important; box-shadow: none !important; border-radius: 0 !important; padding: 0 !important; margin: 0 0 10px 0 !important; background: transparent !important; width: 100% !important; }
            table, .item-row { width: 100% !important; border-collapse: collapse !important; border-spacing: 0 !important; margin-bottom: 15px !important; }
            .item-row { border: 1px solid #000 !important; border-radius: 0 !important; margin-bottom: 0 !important; border-bottom: none !important; }
            .item-row:last-child { border-bottom: 1px solid #000 !important; }
            th, td { border: 1px solid #000 !important; padding: 6px !important; text-align: center !important; vertical-align: middle !important; color: #000 !important; }
            th, .icard-head { background-color: #e0e0e0 !important; color: #000 !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; font-weight: bold !important; border: 1px solid #000 !important; }
            .icard-head { padding: 8px !important; border-bottom: none !important; display: block !important; text-align: center !important; }
            .matrix-scroll, .table-responsive { overflow: visible !important; width: 100% !important; display: block !important; }
            tr { page-break-inside: avoid; }
        }
        /* ⭐ استایل تب‌های سوییچ */
.tab-switch {
    display: flex;
    gap: 4px;
    padding: 6px;
    background: var(--input);
    border: 1px solid var(--border);
    border-radius: 12px;
    margin-bottom: 16px;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

.tab-switch-item {
    flex: 1;
    min-width: 90px;
    padding: 10px 16px;
    text-align: center;
    text-decoration: none;
    font-family: 'Vazirmatn', sans-serif;
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--text-2);
    border-radius: 8px;
    transition: all 0.25s ease;
    white-space: nowrap;
    position: relative;
}

.tab-switch-item:hover {
    color: var(--text-1);
    background: rgba(255,255,255,0.03);
}

.tab-switch-item.active {
    background: var(--accent);
    color: #fff;
    font-weight: 700;
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}

/* برای تم روشن */
body.light .tab-switch-item.active {
    color: #fff;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
.person-card {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr 1fr;
    gap: 4px;
    align-items: center;
    padding: 8px 10px;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
    margin-bottom: 5px;
    text-align: center;
}
.person-card.debtor-card {
    background: var(--debtor-bg);
    border: 1px solid var(--debtor-border);
}
.person-card.creditor-card {
    background: var(--creditor-bg);
    border: 1px solid var(--creditor-border);
}
.person-card:hover {
    transform: translateX(-2px);
    box-shadow: 0 2px 6px rgba(0,0,0,0.06);
}
.person-name {
    font-weight: 700;
    font-size: 0.8rem;
    color: var(--text-1);
}
.person-detail {
    font-size: 0.68rem;
    color: var(--text-3);
    margin-top: 2px;
}
.person-remaining {
    font-weight: 700;
    font-size: 0.8rem;
}
/* ⭐ پاپ‌آپ رسید */
.receipt-popup-overlay {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.7);
    backdrop-filter: blur(6px);
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
    animation: popupFadeIn 0.25s ease;
}
@keyframes popupFadeIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes popupSlideUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }

.receipt-popup-container {
    background: var(--card);
    border-radius: 20px;
    width: 100%;
    max-width: 520px;
    max-height: 85vh;
    overflow-y: auto;
    box-shadow: 0 25px 60px rgba(0,0,0,0.3);
    animation: popupSlideUp 0.3s ease;
    position: relative;
    border: 1px solid var(--border);
}
.receipt-popup-close {
    position: absolute;
    top: 12px; right: 12px;
    width: 30px; height: 30px;
    border-radius: 50%;
    border: none;
    background: rgba(128,128,128,0.15);
    cursor: pointer;
    font-size: 1rem;
    z-index: 10;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-2);
}
.receipt-popup-close:hover { background: rgba(128,128,128,0.3); }

.receipt-theme-tabs {
    display: flex;
    border-bottom: 1px solid var(--border);
    position: sticky;
    top: 0;
    background: var(--card);
    z-index: 5;
    border-radius: 20px 20px 0 0;
}
.receipt-theme-tab {
    flex: 1;
    padding: 11px 6px;
    text-align: center;
    cursor: pointer;
    font-size: 0.78rem;
    font-weight: 600;
    border: none;
    background: transparent;
    color: var(--text-3);
    border-bottom: 2px solid transparent;
    font-family: 'Vazirmatn', sans-serif;
    transition: all 0.2s;
}
.receipt-theme-tab:hover { color: var(--text-1); }
.receipt-theme-tab.active { color: var(--accent); border-bottom-color: var(--accent); }

.receipt-popup-content { padding: 16px 18px; }
.receipt-popup-actions {
    display: flex;
    gap: 8px;
    padding: 14px 18px;
    border-top: 1px solid var(--border);
    position: sticky;
    bottom: 0;
    background: var(--card);
    border-radius: 0 0 20px 20px;
}
.btn-action {
    flex: 1;
    padding: 10px;
    border-radius: 10px;
    border: none;
    cursor: pointer;
    font-family: 'Vazirmatn', sans-serif;
    font-weight: 700;
    font-size: 0.8rem;
}
.btn-print-popup { background: var(--text-1); color: var(--card); }
.btn-pdf-popup { background: var(--creditor); color: #fff; }
.btn-close-popup { background: var(--input); color: var(--text-2); border: 1px solid var(--border); }

/* استایل رسید داخل پاپ‌آپ */
.rp-inner { border-radius: 12px; overflow: hidden; border: 1px solid var(--border); }
.rp-header { padding: 20px 16px; text-align: center; }
.rp-header.classic-h { background: linear-gradient(135deg, #1e40af, #3b82f6); color: #fff; }
.rp-header.modern-h { background: #1a1a1a; color: #fff; text-align: right; border-bottom: 3px solid #00c853; }
.rp-header.luxury-h { background: linear-gradient(180deg, rgba(212,175,55,0.15), rgba(212,175,55,0.03)); color: #d4af37; text-align: center; border-bottom: 1px solid rgba(212,175,55,0.3); }
.rp-header h3 { font-size: 0.9rem; margin-bottom: 4px; }
.rp-name { font-size: 1.3rem; font-weight: 800; }
.modern-h .rp-name { font-weight: 400; font-size: 1.5rem; }
.luxury-h .rp-name { color: #ffd700; }
.rp-body { padding: 16px; }
.rp-table { width: 100%; border-collapse: collapse; margin: 10px 0; font-size: 0.8rem; }
.rp-table th { padding: 8px 6px; border-bottom: 2px solid var(--border); text-align: center; font-weight: 700; }
.rp-table td { padding: 7px 6px; border-bottom: 1px solid var(--border); text-align: center; }
.rp-summary { padding: 14px; border-radius: 10px; text-align: center; margin-top: 10px; }
.rp-summary.classic-s { background: #f0fdf4; border: 1px solid #bbf7d0; }
.rp-summary.modern-s { background: #fafafa; border-left: 3px solid #00c853; text-align: right; }
.rp-summary.luxury-s { background: rgba(212,175,55,0.05); border: 1px solid rgba(212,175,55,0.2); }
.rp-summary p { margin: 3px 0; font-size: 0.82rem; }
.rp-footer { text-align: center; margin-top: 14px; padding-top: 10px; border-top: 1px solid var(--border); font-size: 0.65rem; color: var(--text-3); }
.rp-footer img { height: 20px; vertical-align: middle; margin-right: 4px; }

@media print {
    .receipt-popup-overlay { position: static !important; background: #fff !important; backdrop-filter: none !important; padding: 0 !important; }
    .receipt-popup-container { box-shadow: none !important; max-width: 100% !important; border-radius: 0 !important; max-height: none !important; }
    .receipt-popup-close, .receipt-theme-tabs, .receipt-popup-actions { display: none !important; }
    body * { visibility: hidden; }
    .receipt-popup-overlay, .receipt-popup-overlay * { visibility: visible; }
}
.person-card-mini {
    padding: 10px 12px;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.2s ease;
}
.person-card-mini:hover {
    transform: translateY(-2px);
    box-shadow: 0 3px 10px rgba(0,0,0,0.08);
}

    </style>
</head>
<body>

<!-- هدر مخصوص پرینت -->
<div class="print-only-header">
    <h2>گزارش تراز مالی روزانه</h2>
    <div class="print-meta">
        <span>شعبه: <?php echo htmlspecialchars($branch_name); ?></span>
        <span>تاریخ: <?php echo $selected_date; ?></span>
    </div>
</div>

<div class="wrapper">
    <div class="app-header">
        <div class="app-brand">
            <img src="../assets/images/logo.png" alt="لوگو" class="app-logo" onerror="this.style.display='none'">
            <span class="app-title">تراز روزانه | <?php echo $branch_name; ?></span>
        </div>
        <div class="header-actions">
            <button class="theme-toggle" id="themeToggle">
                <span class="theme-dot"></span>
                <span id="themeLabel">حالت شب</span>
            </button>
            <a href="../index.php?year=<?php echo $back_year; ?>&month=<?php echo $back_month; ?>" class="logout-btn">🔄 بازگشت به تقویم</a>
        </div>
    </div>
    <!-- ⭐ تب‌های ناوبری -->
<div class="tab-switch">
    <a href="../user/index.php?date=<?php echo $selected_date; ?>" 
       class="tab-switch-item <?php echo basename(dirname($_SERVER['SCRIPT_NAME'])) == 'user' ? 'active' : ''; ?>">
        تراز روزانه
    </a>
    <a href="../income/monthly.php?date=<?php echo $selected_date; ?>" 
   class="tab-switch-item">
    درآمد
</a>
    <a href="../goals/daily.php?date=<?php echo $selected_date; ?>" 
       class="tab-switch-item <?php echo strpos($_SERVER['SCRIPT_NAME'], 'goals/daily.php') !== false ? 'active' : ''; ?>">
        ثبت پیشرفت
    </a>
</div>
    <div class="toast-container" id="toastContainer"></div>

    <div id="view-calendar" class="view-container">
        <div class="panel">
            <div class="panel-body">
                <!-- ✅ تاریخ ثابت با لینک بازگشت -->
                <div class="date-bar-new">
                    <div class="date-display">
                        📌 <?php echo $selected_date; ?>
                    </div>
                    <div class="date-actions">
                    </div>
                </div>

                <input type="hidden" id="currentDate" value="<?php echo $selected_date; ?>">
                
                <div class="input-row">
                    <div class="icard"><div class="icard-head debtors"><div class="icard-title">بدهکاران</div><span class="sum-chip" id="chip-d">0 م</span></div><div class="icard-body"><div class="item-list" id="list-d"></div><button class="btn-add" onclick="addDebtor()">+ افزودن بدهکار</button></div></div>
                    <div class="icard"><div class="icard-head creditors"><div class="icard-title">بستانکاران</div><span class="sum-chip" id="chip-c">0 م</span></div><div class="icard-body"><div class="item-list" id="list-c"></div><button class="btn-add" onclick="addCreditor()">+ افزودن بستانکار</button></div></div>
                    <div class="icard"><div class="icard-head petty"><div class="icard-title">تنخواه</div><span class="sum-chip" id="chip-p">0 م</span></div><div class="icard-body"><div class="ceiling-wrap"><label>سقف تنخواه:</label><input type="number" id="ceiling" oninput="onCeilingChange()" value="1000"><span>م</span></div><div class="item-list" id="list-p"></div><button class="btn-add" onclick="addPetty()">+ افزودن قلم</button></div></div>
                </div>
                <div class="deficit-strip"><div class="def-card"><div class="def-label">اختلاف کل</div><div class="def-value zer" id="def1">0 م</div></div><div class="def-card"><div class="def-label">وضعیت تنخواه</div><div class="def-value neg" id="def2">-1,000 م</div></div></div>
                <div class="action-row"><button class="btn-gen" onclick="generateMatrix()">ایجاد جدول جابجایی</button></div>
                <div id="matrix-wrap" class="matrix-scroll"></div>
                <div class="deficit-strip" id="unsettled-strip" style="display:none;margin-top:20px;"><div class="def-card" style="background:var(--creditor-bg);"><div class="def-label" style="color:var(--creditor);">بلاتکلیف بستانکاران</div><div class="def-value neg" id="unsettled-c">0 م</div></div><div class="def-card" style="background:var(--debtor-bg);"><div class="def-label" style="color:var(--debtor);">بلاتکلیف بدهکاران</div><div class="def-value pos" id="unsettled-d">0 م</div></div></div>
                <div style="display:flex;justify-content:center;margin:20px 0;"><button class="btn-gen" onclick="generateControlList()" style="background:linear-gradient(135deg,#059669,#10b981);">ایجاد لیست کنترل</button></div>
                <div id="control-list-wrapper" style="display:none;">
                    <div style="display:flex;justify-content:space-between;margin-bottom:12px;">
                        <h4>ریز تراکنش‌ها</h4>
                        <button class="btn-gen" onclick="addControlRow()" style="padding:6px 16px;background:linear-gradient(135deg,#8b5cf6,#6d28d9);">+ افزودن ردیف</button>
                    </div>
                    <div class="matrix-scroll">
                        <table class="mx" id="control-tbl">
                            <thead id="control-head"></thead>
                            <tbody id="control-body"></tbody>
                            <tfoot id="control-foot"></tfoot>
                        </table>
                    </div>
                </div>
               
<!-- ⭐ بخش ریز حساب افراد -->
<div id="person-summary-section" style="display:none; margin:20px 0;">
    <div class="icard" style="grid-column:span 3;">
        <div class="icard-head" style="background:var(--purple-bg); color:var(--purple);">
    <div class="icard-title"> ریز حساب افراد</div>
            <span class="sum-chip" id="chip-person-count">0 نفر</span>
        </div>
        <div class="icard-body">
            <div id="person-summary-list" style="display:flex; flex-direction:column; gap:8px;">
                <div style="text-align:center; color:var(--text-3); padding:20px;">
                    پس از ایجاد جدول جابجایی، لیست افراد اینجا نمایش داده می‌شود
                </div>
            </div>
        </div>
    </div>
</div>
               <div class="input-row" style="margin-top:20px;"><div class="icard" style="grid-column:span 3;"><div class="icard-head bankers"><div class="icard-title">بنکداران</div><span class="sum-chip" id="chip-b">0 گرم</span></div><div class="icard-body"><div class="item-list" id="list-b"></div><button class="btn-add" onclick="addBanker()">+ افزودن بنکدار</button></div></div></div>
                
                    
                <!-- ⭐ آیتم‌های داینامیک - فقط فیلد گرم -->
                <?php
                $dyn_items_query = "SELECT * FROM dynamic_items WHERE active = 1 ORDER BY sort_order";
                $dyn_items_result = mysqli_query($conn, $dyn_items_query);
                if ($dyn_items_result && mysqli_num_rows($dyn_items_result) > 0):
                    $dyn_data = [];
                    if ($report_id > 0) {
                        $dyn_stmt = mysqli_prepare($conn, "SELECT item_id, amount_gram FROM dynamic_records WHERE report_id = ?");
                        mysqli_stmt_bind_param($dyn_stmt, "i", $report_id);
                        mysqli_stmt_execute($dyn_stmt);
                        $dyn_res = mysqli_stmt_get_result($dyn_stmt);
                        while ($dr = mysqli_fetch_assoc($dyn_res)) {
                            $dyn_data[$dr['item_id']] = $dr['amount_gram'];
                        }
                    }
                ?>
                <div class="input-row" style="margin-top:20px;">
                    <div class="icard" style="grid-column:span 3;">
                        <div class="icard-head" style="background:rgba(16,185,129,0.08);color:var(--debtor);">
                            <div class="icard-title" style="color:var(--debtor);">اقلام داینامیک</div>
                            <span class="sum-chip" id="chip-dyn">0 گرم</span>
                        </div>
                        <div class="icard-body">
                            <div class="item-list">
                                <?php while ($ditem = mysqli_fetch_assoc($dyn_items_result)): 
                                    $gram = $dyn_data[$ditem['id']] ?? '';
                                    $gram_display = ($gram !== '' && $gram !== null) ? number_format((float)$gram, 3, '.', '') : '';
                                ?>
                                <div class="item-row">
                                    <span style="flex:1;font-size:0.85em;"><?php echo htmlspecialchars($ditem['name']); ?></span>
                                    <input type="text" class="dyn-input" data-id="<?php echo $ditem['id']; ?>" value="<?php echo $gram_display; ?>" placeholder="مثال: 4.333" oninput="formatNumDynamic(this); updateDynSummary(); debounceAutoSave();">
                                </div>
                                <?php endwhile; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="export-container">
                    <button class="btn-export btn-ex-print" onclick="openPrintPage()">🖨️ پرینت رسمی</button>
                    <button class="btn-export btn-save" onclick="saveReport(true)">💾 ذخیره گزارش</button>
                </div>

                <?php if ($report_id > 0): ?>
                <div class="chat-section">
                    <h4>💬 پیام‌های ناظر</h4>
                    <div class="chat-messages" id="chatMessages">
                        <?php foreach ($messages as $msg): ?>
                        <div class="chat-message <?php echo $msg['sender_role']; ?>">
                            <div class="chat-bubble">
                                <strong><?php echo $msg['sender_role'] === 'observer' ? htmlspecialchars($msg['branch_name']) : 'شما'; ?></strong>
                                <div><?php echo nl2br(htmlspecialchars($msg['message'])); ?></div>
                                <div class="chat-time"><?php echo $msg['created_at']; ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button class="btn-gen" onclick="showReplyBox()" style="margin-top:10px;">💬 پاسخ به ناظر</button>
                    <div class="reply-area" id="replyArea">
                        <textarea id="replyMessage" placeholder="پاسخ خود را بنویسید..."></textarea>
                        <button onclick="sendReply()">ارسال پاسخ</button>
                        <button onclick="hideReplyBox()" style="background:#8a9199; margin-right:10px;">انصراف</button>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// ========== توابع پایه ==========
function showToast(msg, type) { type = type || 'success'; var c = document.getElementById('toastContainer'); var t = document.createElement('div'); t.className = 'toast ' + type; t.textContent = msg; c.appendChild(t); setTimeout(function() { t.remove(); }, 3000); }
function fmtN(n) { return Number(n).toLocaleString('en-US'); }
function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;'); }

// ⭐ تابع parseNum برای پشتیبانی از نقطه و کاما
function parseNum(s) { 
    s = String(s||'');
    s = s.replace(/[۰-۹]/g, function(d) { return '۰۱۲۳۴۵۶۷۸۹'.indexOf(d); });
    s = s.replace(/,/g, '');
    return parseFloat(s) || 0; 
}

// ⭐ تابع فرمت‌دهی مخصوص فیلدهای داینامیک - فقط نقطه مجاز
function formatNumDynamic(el) {
    var raw = el.value.replace(/[^\d.]/g, '');
    var parts = raw.split('.');
    if (parts.length > 2) {
        raw = parts[0] + '.' + parts.slice(1).join('');
    }
    if (parts.length === 2 && parts[1].length > 3) {
        raw = parts[0] + '.' + parts[1].substring(0, 3);
    }
    el.value = raw;
}

// ⭐ تابع formatNum برای سایر فیلدها (با کاما)
function formatNum(el) { 
    var raw = el.value.replace(/,/g, '');
    raw = raw.replace(/[۰-۹]/g, function(d) { return '۰۱۲۳۴۵۶۷۸۹'.indexOf(d); });
    var num = parseFloat(raw); 
    if (!isNaN(num)) el.value = num.toLocaleString('en-US'); 
}

// ========== متغیرها ==========
var debtors = [], creditors = [], pettys = [], bankers = [],
    nd = 1, nc = 1, np = 1, nb = 1,
    activeRelations = [],
    savedReportData = <?php echo $report_json; ?>,
    saveTimer = null;

function debounceAutoSave() { clearTimeout(saveTimer); saveTimer = setTimeout(function() { saveReport(false); }, 2000); }

// ========== ذخیره گزارش ==========
function saveReport(showMsg) {
    if (showMsg === undefined) showMsg = false;
    var date = '<?php echo $selected_date; ?>',
        controlRows = [], controlDescs = [];
    document.querySelectorAll('#control-body tr').forEach(function(tr) {
        var row = [], descRow = [];
        tr.querySelectorAll('.ctrl-amount').forEach(function(inp) { row.push(inp.value || ''); });
        tr.querySelectorAll('.ctrl-desc').forEach(function(inp) { descRow.push(inp.value || ''); });
        controlRows.push(row); controlDescs.push(descRow);
    });
    
    // ⭐ جمع‌آوری داده‌های داینامیک - فقط گرم
    var dynItems = [];
    document.querySelectorAll('.dyn-input').forEach(function(inp) {
        var id = parseInt(inp.dataset.id);
        var gram = parseNum(inp.value);
        dynItems.push({ id: id, gram: gram });
    });
    
    var state = { debtors: debtors, creditors: creditors, pettys: pettys, bankers: bankers, ceiling: document.getElementById('ceiling').value, matrixValues: getMatrixValues(), controlRows: controlRows, controlDescs: controlDescs, dyn_items: dynItems };
    fetch('save_report.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({ date:date, data:JSON.stringify(state), dyn_items:JSON.stringify(dynItems) }) })
    .then(function(r) { return r.json(); })
    .then(function(d) { if (d.success && showMsg) showToast('✓ ذخیره شد', 'success'); else if (!d.success) showToast(d.message || 'خطا', 'error'); })
    .catch(function() { if(showMsg) showToast('قطع ارتباط', 'error'); });
}

// ⭐ به‌روزرسانی خلاصه داینامیک - فقط گرم
function updateDynSummary() { 
    var sum = 0; 
    document.querySelectorAll('.dyn-input').forEach(function(inp) { sum += parseNum(inp.value); }); 
    var chip = document.getElementById('chip-dyn'); 
    if (chip) chip.textContent = sum.toFixed(3) + ' گرم'; 
}

// ========== مدیریت ردیف‌ها ==========
function addDebtor() { debtors.push({id:nd, name:'', amt:''}); nd++; renderD(); if(document.getElementById('mxtbl')) rebuildMatrix(); debounceAutoSave(); }
function addCreditor() { creditors.push({id:nc, name:'', amt:''}); nc++; renderC(); if(document.getElementById('mxtbl')) rebuildMatrix(); debounceAutoSave(); }
function addPetty() { pettys.push({id:np, desc:'', amt:''}); np++; renderP(); updateSummary(); debounceAutoSave(); }
function addBanker() { bankers.push({id:nb, name:'', amt:''}); nb++; renderB(); updateSummary(); debounceAutoSave(); }
function removeDebtor(id) { debtors = debtors.filter(function(x) { return x.id !== id; }); renderD(); if(document.getElementById('mxtbl')) rebuildMatrix(); debounceAutoSave(); }
function removeCreditor(id) { creditors = creditors.filter(function(x) { return x.id !== id; }); renderC(); if(document.getElementById('mxtbl')) rebuildMatrix(); debounceAutoSave(); }
function removePetty(id) { pettys = pettys.filter(function(x) { return x.id !== id; }); renderP(); updateSummary(); debounceAutoSave(); }
function removeBanker(id) { bankers = bankers.filter(function(x) { return x.id !== id; }); renderB(); updateSummary(); debounceAutoSave(); }
function upD(id,f,v) { var o = debtors.find(function(x) { return x.id === id; }); if(o) { o[f]=v; updateSummary(); if(f==='amt' && document.getElementById('mxtbl')) { updateMatrixHeaders(); recalc(); } debounceAutoSave(); } }
function upC(id,f,v) { var o = creditors.find(function(x) { return x.id === id; }); if(o) { o[f]=v; updateSummary(); if(f==='amt' && document.getElementById('mxtbl')) { updateMatrixHeaders(); recalc(); } debounceAutoSave(); } }
function upP(id,f,v) { var o = pettys.find(function(x) { return x.id === id; }); if(o) { o[f]=v; updateSummary(); debounceAutoSave(); } }
function upB(id,f,v) { var o = bankers.find(function(x) { return x.id === id; }); if(o) { o[f]=v; updateSummary(); debounceAutoSave(); } }
function onCeilingChange() { updateSummary(); debounceAutoSave(); }

// ========== رندر ==========
function renderD() { var h=''; debtors.forEach(function(d) { h += '<div class="item-row"><input type="text" placeholder="نام" value="'+esc(d.name)+'" oninput="upD('+d.id+',\'name\',this.value)"><input type="number" placeholder="مبلغ" value="'+esc(d.amt)+'" oninput="upD('+d.id+',\'amt\',this.value)"><button class="btn-rm" onclick="removeDebtor('+d.id+')">✕</button></div>'; }); document.getElementById('list-d').innerHTML = h; updateSummary(); }
function renderC() { var h=''; creditors.forEach(function(c) { h += '<div class="item-row"><input type="text" placeholder="نام" value="'+esc(c.name)+'" oninput="upC('+c.id+',\'name\',this.value)"><input type="number" placeholder="مبلغ" value="'+esc(c.amt)+'" oninput="upC('+c.id+',\'amt\',this.value)"><button class="btn-rm" onclick="removeCreditor('+c.id+')">✕</button></div>'; }); document.getElementById('list-c').innerHTML = h; updateSummary(); }
function renderP() { var h=''; pettys.forEach(function(p) { h += '<div class="item-row"><input type="text" placeholder="شرح" value="'+esc(p.desc)+'" oninput="upP('+p.id+',\'desc\',this.value)"><input type="number" placeholder="مبلغ" value="'+esc(p.amt)+'" oninput="upP('+p.id+',\'amt\',this.value)"><button class="btn-rm" onclick="removePetty('+p.id+')">✕</button></div>'; }); document.getElementById('list-p').innerHTML = h; updateSummary(); }
function renderB() { var h=''; bankers.forEach(function(b) { h += '<div class="item-row"><input type="text" placeholder="نام" value="'+esc(b.name)+'" oninput="upB('+b.id+',\'name\',this.value)"><input type="number" placeholder="وزن" value="'+esc(b.amt)+'" oninput="upB('+b.id+',\'amt\',this.value)"><button class="btn-rm" onclick="removeBanker('+b.id+')">✕</button></div>'; }); document.getElementById('list-b').innerHTML = h; updateSummary(); }

function updateSummary() {
    var sD = debtors.reduce(function(s,x) { return s+(parseFloat(x.amt)||0); },0),
        sC = creditors.reduce(function(s,x) { return s+(parseFloat(x.amt)||0); },0),
        sP = pettys.reduce(function(s,x) { return s+(parseFloat(x.amt)||0); },0),
        sB = bankers.reduce(function(s,x) { return s+(parseFloat(x.amt)||0); },0),
        ceil = parseFloat(document.getElementById('ceiling').value)||0;
    document.getElementById('chip-d').textContent = fmtN(sD)+' م';
    document.getElementById('chip-c').textContent = fmtN(sC)+' م';
    document.getElementById('chip-p').textContent = fmtN(sP)+' م';
    document.getElementById('chip-b').textContent = fmtN(sB)+' گرم';
    setDefRaw('def1', sD - sC);
    setDefRaw('def2', sP - ceil);
    updateDynSummary();
}
function setDefRaw(id, v) { var el = document.getElementById(id); var sign = v > 0 ? '+' : v < 0 ? '-' : ''; el.innerHTML = '<span dir="ltr">'+sign+fmtN(Math.abs(v))+'</span> <span>م</span>'; el.className = 'def-value '+(v>0?'pos':v<0?'neg':'zer'); }

// ========== ماتریس ==========
function rebuildMatrix() {
    if (!debtors.length || !creditors.length) return;
    var oldValues = {};
    if (document.getElementById('mxtbl')) {
        document.querySelectorAll('#mxtbl .td-inp input').forEach(function(inp) {
            if (inp.dataset.cid && inp.dataset.did) { var key = inp.dataset.cid+'_'+inp.dataset.did; if (inp.value) oldValues[key] = inp.value; }
        });
    }
    var colW = Math.max(100, Math.min(150, Math.floor(800 / debtors.length)));
    var h = '<table class="mx" id="mxtbl" style="table-layout:fixed; min-width:100%;"><thead><tr><th class="th-corner" rowspan="2" style="width:110px;">بستانکار<br>به<br>بدهکار</th>';
    debtors.forEach(function(d) { h += '<th class="th-dname" style="width:'+colW+'px;">↓ '+esc(d.name||'بدهکار')+'</th>'; });
    h += '<th class="th-corner" rowspan="2" style="width:90px;">مانده</th></tr><tr>';
    debtors.forEach(function(d) { h += '<th class="th-damount" style="width:'+colW+'px;">'+fmtN(parseFloat(d.amt)||0)+'</th>'; });
    h += '</tr></thead><tbody>';
    creditors.forEach(function(c) {
        h += '<tr><td class="td-cname">↑ '+esc(c.name||'بستانکار')+'<br><small>'+fmtN(parseFloat(c.amt)||0)+'</small></td>';
        debtors.forEach(function(d) { h += '<td class="td-inp"><input type="number" oninput="onMatrixChange()" data-cid="'+c.id+'" data-did="'+d.id+'" value=""></td>'; });
        h += '<td class="td-cbal tfoot-bal" id="cbal-'+c.id+'">0</td></tr>';
    });
    h += '</tbody><tfoot><tr><td class="tfoot-sum">مانده بدهکار</td>';
    debtors.forEach(function(d) { h += '<td class="tfoot-bal" id="dbal-'+d.id+'">0</td>'; });
    h += '<td class="tfoot-sum">!</td></tr></tfoot></table>';
    document.getElementById('matrix-wrap').innerHTML = h;
    Object.entries(oldValues).forEach(function(_ref) { var key = _ref[0], val = _ref[1]; var p = key.split('_'); var inp = document.querySelector('#mxtbl input[data-cid="'+p[0]+'"][data-did="'+p[1]+'"]'); if (inp) inp.value = val; });
    recalc();
    document.getElementById('unsettled-strip').style.display = 'grid';
}
function updateMatrixHeaders() {
    if (!document.getElementById('mxtbl')) return;
    debtors.forEach(function(d, i) {
        var th = document.querySelector('#mxtbl .th-dname:nth-child('+(i+2)+')'); if (th) th.innerHTML = '↓ '+esc(d.name);
        var amtTh = document.querySelector('#mxtbl thead tr:nth-child(2) th:nth-child('+(i+2)+')'); if (amtTh) amtTh.innerHTML = fmtN(parseFloat(d.amt)||0);
    });
    creditors.forEach(function(c, i) {
        var td = document.querySelector('#mxtbl tbody tr:nth-child('+(i+1)+') td:first-child');
        if (td) { td.innerHTML = '↑ '+esc(c.name)+'<br><small>'+fmtN(parseFloat(c.amt)||0)+'</small>'; recalc(); }
    });
}
function recalc() {
    var tbl = document.getElementById('mxtbl'); if (!tbl) return;
    creditors.forEach(function(c) {
        var sum = 0; tbl.querySelectorAll('input[data-cid="'+c.id+'"]').forEach(function(inp) { sum += parseFloat(inp.value)||0; });
        var el = document.getElementById('cbal-'+c.id); if (el) { var bal = (parseFloat(c.amt)||0)-sum; var sign = bal<0?'-':''; el.innerHTML = sign+fmtN(Math.abs(bal)); }
    });
    debtors.forEach(function(d) {
        var sum = 0; tbl.querySelectorAll('input[data-did="'+d.id+'"]').forEach(function(inp) { sum += parseFloat(inp.value)||0; });
        var el = document.getElementById('dbal-'+d.id); if (el) { var bal = (parseFloat(d.amt)||0)-sum; var sign = bal<0?'-':''; el.innerHTML = sign+fmtN(Math.abs(bal)); }
    });
    updateUnsettled();
    generatePersonSummary();
}
function updateUnsettled() { var uD=0, uC=0; debtors.forEach(function(d) { var el=document.getElementById('dbal-'+d.id); if(el) uD+=parseFloat(el.textContent.replace(/[^\d.-]/g,''))||0; }); creditors.forEach(function(c) { var el=document.getElementById('cbal-'+c.id); if(el) uC+=parseFloat(el.textContent.replace(/[^\d.-]/g,''))||0; }); document.getElementById('unsettled-d').innerHTML = '<span dir="ltr">+'+fmtN(Math.abs(uD))+'</span> م'; document.getElementById('unsettled-c').innerHTML = '<span dir="ltr">-'+fmtN(Math.abs(uC))+'</span> م'; }
function onMatrixChange() { recalc(); debounceAutoSave(); }
// ⭐ تولید ریز حساب افراد
function generatePersonSummary() {
    var container = document.getElementById('person-summary-section');
    var listEl = document.getElementById('person-summary-list');
    
    if ((!debtors.length && !creditors.length) || !document.getElementById('mxtbl')) {
        container.style.display = 'none';
        return;
    }
    
    container.style.display = 'block';
    
    var debtorPaid = {}, creditorPaid = {};
    document.querySelectorAll('#mxtbl .td-inp input').forEach(function(inp) {
        var cid = inp.dataset.cid, did = inp.dataset.did, val = parseFloat(inp.value) || 0;
        if (val > 0) { debtorPaid[did] = (debtorPaid[did] || 0) + val; creditorPaid[cid] = (creditorPaid[cid] || 0) + val; }
    });
    
    // جدا کردن بدهکاران و بستانکاران
    var debtorCards = [], creditorCards = [];
    
    debtors.forEach(function(d) {
        if (!d.name) return;
        var total = parseFloat(d.amt) || 0, paid = debtorPaid[d.id] || 0, rem = total - paid;
        debtorCards.push({ name: d.name, total: total, paid: paid, rem: rem, label: 'پرداخت', color: 'var(--debtor)' });
    });
    
    creditors.forEach(function(c) {
        if (!c.name) return;
        var total = parseFloat(c.amt) || 0, paid = creditorPaid[c.id] || 0, rem = total - paid;
        creditorCards.push({ name: c.name, total: total, paid: paid, rem: rem, label: 'دریافت', color: 'var(--creditor)' });
    });
    
    var personCount = debtorCards.length + creditorCards.length;
    var maxRows = Math.max(debtorCards.length, creditorCards.length);
    var html = '';
    
    for (var i = 0; i < maxRows; i += 2) {
        html += '<div style="display:grid; grid-template-columns:1fr 1fr 1fr 1fr; gap:8px; margin-bottom:8px;">';
        
        // ۲ تا بدهکار
        for (var d = 0; d < 2; d++) {
            var idx = i + d;
            if (idx < debtorCards.length) {
                var card = debtorCards[idx];
                html += buildMiniCard(card, 'debtor');
            } else {
                html += '<div></div>';
            }
        }
        
        // ۲ تا بستانکار
        for (var c = 0; c < 2; c++) {
            var idx2 = i + c;
            if (idx2 < creditorCards.length) {
                var card2 = creditorCards[idx2];
                html += buildMiniCard(card2, 'creditor');
            } else {
                html += '<div></div>';
            }
        }
        
        html += '</div>';
    }
    
    listEl.innerHTML = html || '<div style="color:var(--text-3);text-align:center;padding:10px;">—</div>';
    document.getElementById('chip-person-count').textContent = personCount + ' نفر';
}

function buildMiniCard(card, type) {
    var bg = type === 'debtor' ? 'var(--debtor-bg)' : 'var(--creditor-bg)';
    var border = type === 'debtor' ? 'var(--debtor-border)' : 'var(--creditor-border)';
    var remColor = card.rem > 0 ? 'var(--creditor)' : 'var(--debtor)';
    var icon = card.rem > 0 ? '▸' : '✓';
    
    return '<div class="person-card-mini" onclick="openPersonReceipt(\''+esc(card.name).replace(/'/g,"\\'")+'\',\''+type+'\')" style="background:'+bg+';border:1px solid '+border+';">' +
        '<div style="font-weight:700;font-size:0.8rem;color:'+card.color+';margin-bottom:4px;">'+esc(card.name)+'</div>' +
        '<div style="font-size:0.65rem;color:var(--text-3);display:flex;justify-content:space-between;">' +
            '<span>کل: '+fmtN(card.total)+'</span>' +
            '<span>'+card.label+': '+fmtN(card.paid)+'</span>' +
        '</div>' +
        '<div style="font-size:0.7rem;font-weight:700;color:'+remColor+';margin-top:3px;text-align:left;">'+icon+' '+fmtN(Math.abs(card.rem))+'</div>' +
    '</div>';
}

// ⭐ باز کردن رسید
function openPersonReceipt(name, type) {
    var date = '<?php echo $selected_date; ?>';
    
    // نمایش overlay
    var overlay = document.getElementById('receiptOverlay');
    overlay.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    
    // لود محتوا از receipt_popup_data.php
    fetch('receipt_popup_data.php?date=' + date + '&person=' + encodeURIComponent(name + '|' + type))
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.error) {
                document.getElementById('receiptPopupContent').innerHTML = '<div style="text-align:center;padding:30px;color:var(--creditor);">' + data.error + '</div>';
                return;
            }
            renderReceiptHTML(data);
        })
        .catch(function() {
            document.getElementById('receiptPopupContent').innerHTML = '<div style="text-align:center;padding:30px;color:var(--creditor);">خطا در بارگذاری</div>';
        });
}
function generateControlList() {
    var tbl = document.getElementById('mxtbl'); if (!tbl) { showToast('ابتدا جدول جابجایی ایجاد کنید', 'error'); return; }
    activeRelations = [];
    creditors.forEach(function(c) { debtors.forEach(function(d) { var inp = tbl.querySelector('input[data-cid="'+c.id+'"][data-did="'+d.id+'"]'); var val = inp ? parseFloat(inp.value)||0 : 0; if (val > 0) activeRelations.push({ id:'rel_'+d.id+'_'+c.id, title:(d.name||'بدهکار')+' به '+(c.name||'بستانکار'), target:val }); }); });
    if (!activeRelations.length) { document.getElementById('control-list-wrapper').style.display = 'none'; return; }
    var hTitle = '<tr>'; activeRelations.forEach(function(r) { hTitle += '<th>'+esc(r.title)+'<br><small>'+fmtN(r.target)+'</small></th>'; });
    document.getElementById('control-head').innerHTML = hTitle;
    document.getElementById('control-body').innerHTML = '';
    buildControlFooter();
    document.getElementById('control-list-wrapper').style.display = 'block';
    debounceAutoSave();
}
function addControlRow() { var tbody = document.getElementById('control-body'), tr = document.createElement('tr'); activeRelations.forEach(function(r) { tr.innerHTML += '<td class="ctrl-cell"><input type="number" class="ctrl-amount" data-rel="'+r.id+'" placeholder="مبلغ" oninput="onControlChange()"><input type="text" class="ctrl-desc" data-desc-rel="'+r.id+'" placeholder="واریزکننده" oninput="onControlChange()"></td>'; }); tbody.appendChild(tr); debounceAutoSave(); }
function buildControlFooter() { var h = '<tr>'; activeRelations.forEach(function(r) { h += '<td id="diff_'+r.id+'">0</td>'; }); h += '</tr>'; document.getElementById('control-foot').innerHTML = h; calcControl(); }
function calcControl() { activeRelations.forEach(function(r) { var sum = 0; document.querySelectorAll('.ctrl-amount[data-rel="'+r.id+'"]').forEach(function(inp) { sum += parseFloat(inp.value)||0; }); var diff = sum - r.target, el = document.getElementById('diff_'+r.id); if (el) { var sign = diff>0?'+':(diff<0?'-':''); el.innerHTML = sign+fmtN(Math.abs(diff)); el.style.color = diff===0?'#1a7f37':'#d12f2a'; } }); }
function onControlChange() { calcControl(); debounceAutoSave(); }
function getMatrixValues() { var vals = {}; document.querySelectorAll('#mxtbl .td-inp input').forEach(function(inp) { if (inp.dataset.cid) vals[inp.dataset.cid+'_'+inp.dataset.did] = inp.value; }); return vals; }

// ========== لود داده‌ها ==========
function loadSavedReport(data) {
    if (!data) return;
    debtors = data.debtors || []; creditors = data.creditors || []; pettys = data.pettys || []; bankers = data.bankers || [];
    nd = Math.max.apply(Math, debtors.map(function(d) { return d.id; }).concat([0]))+1;
    nc = Math.max.apply(Math, creditors.map(function(c) { return c.id; }).concat([0]))+1;
    np = Math.max.apply(Math, pettys.map(function(p) { return p.id; }).concat([0]))+1;
    nb = Math.max.apply(Math, bankers.map(function(b) { return b.id; }).concat([0]))+1;
    document.getElementById('ceiling').value = data.ceiling || 1000;
    renderD(); renderC(); renderP(); renderB();
    
    // ⭐ لود داده‌های داینامیک - فقط گرم
    if (data.dyn_records) {
        for (var i = 0; i < data.dyn_records.length; i++) { 
            var rec = data.dyn_records[i]; 
            var inp = document.querySelector('.dyn-input[data-id="'+rec.item_id+'"]'); 
            if (inp) inp.value = rec.amount_gram ? Number(rec.amount_gram).toFixed(3) : ''; 
        }
        updateDynSummary();
    }
    
    if (debtors.length && creditors.length) {
        rebuildMatrix();
        setTimeout(function() {
            Object.entries(data.matrixValues || {}).forEach(function(_ref) { var key = _ref[0], val = _ref[1]; var p = key.split('_'); var inp = document.querySelector('#mxtbl input[data-cid="'+p[0]+'"][data-did="'+p[1]+'"]'); if (inp) inp.value = val; });
            recalc();
            generatePersonSummary();
            activeRelations = [];
            creditors.forEach(function(c) { debtors.forEach(function(d) { var k = c.id+'_'+d.id; var v = (data.matrixValues||{})[k]||0; if (v > 0) activeRelations.push({ id:'rel_'+d.id+'_'+c.id, title:(d.name||'بدهکار')+' به '+(c.name||'بستانکار'), target:v }); }); });
            if (activeRelations.length) {
                var hTitle = '<tr>'; activeRelations.forEach(function(r) { hTitle += '<th>'+esc(r.title)+'<br><small>'+fmtN(r.target)+'</small></th>'; });
                document.getElementById('control-head').innerHTML = hTitle; document.getElementById('control-body').innerHTML = '';
                var controlDescs = data.controlDescs || [];
                if (data.controlRows && data.controlRows.length) { data.controlRows.forEach(function(rowData, idx) { var descRow = (controlDescs[idx]||[]); addControlRowWithData(rowData, descRow); }); }
                buildControlFooter(); calcControl();
                document.getElementById('control-list-wrapper').style.display = 'block';
            }
            document.getElementById('unsettled-strip').style.display = 'grid';
        }, 100);
    }
    updateSummary();
}
function addControlRowWithData(amounts, descs) { var tbody = document.getElementById('control-body'), tr = document.createElement('tr'); activeRelations.forEach(function(r, idx) { tr.innerHTML += '<td class="ctrl-cell"><input type="number" class="ctrl-amount" data-rel="'+r.id+'" value="'+(amounts[idx]||'')+'" oninput="onControlChange()"><input type="text" class="ctrl-desc" value="'+esc(descs[idx]||'')+'" oninput="onControlChange()"></td>'; }); tbody.appendChild(tr); }

// ========== چت ==========
function showReplyBox() { document.getElementById('replyArea').style.display = 'block'; }
function hideReplyBox() { document.getElementById('replyArea').style.display = 'none'; document.getElementById('replyMessage').value = ''; }
function sendReply() { var msg = document.getElementById('replyMessage').value; if (!msg.trim()) { showToast('پیام را بنویسید', 'error'); return; } fetch('send_reply.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({ date:'<?php echo $selected_date; ?>', message:msg }) }).then(function(r) { return r.json(); }).then(function(d) { showToast(d.message, d.success?'success':'error'); if(d.success) location.reload(); }); }

// ========== پرینت ==========
function openPrintPage() { window.open('print.php?date=<?php echo $selected_date; ?>&print=1', '_blank'); }

// ========== تم ==========
(function() {
    var body = document.body, toggle = document.getElementById('themeToggle'), label = document.getElementById('themeLabel');
    if (localStorage.getItem('theme') === 'dark') { body.classList.add('dark'); label.textContent = 'حالت روز'; }
    toggle.addEventListener('click', function() { body.classList.toggle('dark'); var isDark = body.classList.contains('dark'); localStorage.setItem('theme', isDark ? 'dark' : 'light'); label.textContent = isDark ? 'حالت روز' : 'حالت شب'; });
})();

// ========== راه‌اندازی ==========
window.onload = function() { addDebtor(); addCreditor(); addPetty(); addBanker(); if (savedReportData) loadSavedReport(savedReportData); };
fetch('../update_activity.php');
setInterval(function() { fetch('../update_activity.php'); }, 120000);

var currentReceiptTheme = 'classic';

function renderReceiptHTML(data) {
    var html = '<div class="rp-inner">';
    
    var headerClass = 'classic-h';
    if (currentReceiptTheme === 'modern') headerClass = 'modern-h';
    if (currentReceiptTheme === 'luxury') headerClass = 'luxury-h';
    
    html += '<div class="rp-header ' + headerClass + '">';
    html += '<h3>' + (data.is_debtor ? 'رسید پرداختی' : 'صورتحساب دریافتی') + '</h3>';
    html += '<div class="rp-name">' + esc(data.person_name) + '</div>';
    html += '<p style="font-size:0.7rem;opacity:0.8;margin:4px 0;">تاریخ: ' + data.selected_date.replace(/-/g, '/') + '</p>';
    html += '<div style="font-size:0.8rem;margin-top:8px;padding-top:6px;border-top:1px dashed rgba(255,255,255,0.2);">' + (data.is_debtor ? 'کل بدهی' : 'کل طلب') + ': ' + fmtN(data.totalAmount) + ' ریال</div>';
    html += '</div>';
    
    html += '<div class="rp-body">';
    
    if (data.transactions && data.transactions.length > 0) {
        html += '<table class="rp-table"><thead><tr><th>ردیف</th><th>مبلغ (ریال)</th><th>شرح</th></tr></thead><tbody>';
        for (var i = 0; i < data.transactions.length; i++) {
            html += '<tr><td>' + (i+1) + '</td><td>' + fmtN(data.transactions[i].amount) + '</td><td>' + esc(data.transactions[i].desc) + '</td></tr>';
        }
        html += '</tbody></table>';
    } else {
        html += '<p style="text-align:center;color:var(--text-3);">تراکنشی ثبت نشده</p>';
    }
    
    var summaryClass = 'classic-s';
    if (currentReceiptTheme === 'modern') summaryClass = 'modern-s';
    if (currentReceiptTheme === 'luxury') summaryClass = 'luxury-s';
    
    html += '<div class="rp-summary ' + summaryClass + '">';
    html += '<p><strong>جمع پرداختی:</strong> ' + fmtN(data.paidTotal) + ' ریال</p>';
    if (data.remaining > 0) {
        html += '<p style="color:#dc2626;font-weight:700;">' + (data.is_debtor ? 'مانده بدهی' : 'مانده طلب') + ': ' + fmtN(data.remaining) + ' ریال</p>';
    } else if (data.remaining == 0) {
        html += '<p style="color:#16a34a;font-weight:700;">تسویه کامل</p>';
    } else {
        html += '<p style="color:#16a34a;font-weight:700;">بستانکاری: ' + fmtN(Math.abs(data.remaining)) + ' ریال</p>';
    }
    html += '</div>';
    
    html += '<div class="rp-footer">ساخته شده توسط <img src="../assets/images/logo2.png" alt="لوگو" onerror="this.style.display=\'none\'"></div>';
    
    html += '</div></div>';
    
    document.getElementById('receiptPopupContent').innerHTML = html;
    document.getElementById('receiptPopupContent').dataset.personName = data.person_name;
document.getElementById('receiptPopupContent').dataset.personType = data.is_debtor ? 'debtor' : 'creditor';
}

function switchReceiptTheme(theme, btn) {
    currentReceiptTheme = theme;
    document.querySelectorAll('.receipt-theme-tab').forEach(function(t) { t.classList.remove('active'); });
    btn.classList.add('active');
    
    var content = document.getElementById('receiptPopupContent');
    if (content.querySelector('.rp-inner')) {
        var header = content.querySelector('.rp-header');
        var summary = content.querySelector('.rp-summary');
        header.className = 'rp-header ' + (theme === 'modern' ? 'modern-h' : theme === 'luxury' ? 'luxury-h' : 'classic-h');
        summary.className = 'rp-summary ' + (theme === 'modern' ? 'modern-s' : theme === 'luxury' ? 'luxury-s' : 'classic-s');
    }
}

function closeReceiptPopup() {
    document.getElementById('receiptOverlay').style.display = 'none';
    document.body.style.overflow = '';
}

function printReceipt() {
    var date = '<?php echo $selected_date; ?>';
    var branchId = '<?php echo $user_id; ?>';
    var content = document.getElementById('receiptPopupContent');
    var personName = content.dataset.personName || '';
    var personType = content.dataset.personType || '';
    
    if (!personName) { 
        showToast('اطلاعات شخص یافت نشد', 'error'); 
        return; 
    }
    
    // باز کردن print.php قدیمی خودت با پارامترهای GET
    var url = 'receipt_print.php?branch_id=' + branchId + '&date=' + date + '&person=' + encodeURIComponent(personName + '|' + personType) + '&theme=' + currentReceiptTheme;
    window.open(url, '_blank');
}

function downloadReceiptPDF() {
    printReceipt();
}


document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeReceiptPopup();
});
</script>
<!-- ⭐ پاپ‌آپ رسید -->
<div class="receipt-popup-overlay" id="receiptOverlay" onclick="closeReceiptPopup()" style="display:none;">
    <div class="receipt-popup-container" id="receiptPopupContainer" onclick="event.stopPropagation()">
        <button class="receipt-popup-close" onclick="closeReceiptPopup()">✕</button>
        
        <div class="receipt-theme-tabs">
            <button class="receipt-theme-tab active" data-rt="classic" onclick="switchReceiptTheme('classic', this)">کلاسیک</button>
            <button class="receipt-theme-tab" data-rt="modern" onclick="switchReceiptTheme('modern', this)">مدرن</button>
            <button class="receipt-theme-tab" data-rt="luxury" onclick="switchReceiptTheme('luxury', this)">لوکس</button>
        </div>
        
        <div class="receipt-popup-content" id="receiptPopupContent">
            <div style="text-align:center;padding:40px;color:var(--text-3);">در حال بارگذاری...</div>
        </div>
        
        <div class="receipt-popup-actions">
            <button class="btn-action btn-print-popup" onclick="printReceipt()">چاپ</button>
            <button class="btn-action btn-pdf-popup" onclick="downloadReceiptPDF()">دانلود PDF</button>
            <button class="btn-action btn-close-popup" onclick="closeReceiptPopup()">بستن</button>
        </div>
    </div>
</div>
</body>
</html>