<?php
define('SECURE_ACCESS', true);
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireLogin();

$personnel_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($personnel_id == 0) {
    header('Location: index.php');
    exit;
}

// دریافت اطلاعات پرسنل
$stmt = mysqli_prepare($conn, "SELECT * FROM salary_personnel WHERE id = ? AND status = 'active'");
mysqli_stmt_bind_param($stmt, "i", $personnel_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) == 0) {
    die('<div style="text-align:center;padding:60px;font-family:Vazirmatn;">پرسنل یافت نشد. <a href="index.php">بازگشت</a></div>');
}

$person = mysqli_fetch_assoc($result);
$msg = '';
$error = '';

// ذخیره تغییرات
if (isset($_POST['update_personnel'])) {
    $fname = mysqli_real_escape_string($conn, $_POST['first_name']);
    $lname = mysqli_real_escape_string($conn, $_POST['last_name']);
    $pos = mysqli_real_escape_string($conn, $_POST['position'] ?? '');
    $dept = mysqli_real_escape_string($conn, $_POST['department'] ?? '');
    $ncode = mysqli_real_escape_string($conn, $_POST['national_code'] ?? '');
    $pcode = mysqli_real_escape_string($conn, $_POST['personnel_code'] ?? '');
    $bsalary = intval(str_replace(',', '', $_POST['base_salary'] ?? '0'));
    $children = intval($_POST['children_count'] ?? 0);
    $insnum = mysqli_real_escape_string($conn, $_POST['insurance_number'] ?? '');
    $hdate = mysqli_real_escape_string($conn, $_POST['hire_date'] ?? '');
    
    if (empty($fname) || empty($lname)) {
        $error = 'نام و نام خانوادگی الزامی است';
    } elseif ($bsalary < 0) {
        $error = 'حقوق پایه نمی‌تواند منفی باشد';
    } elseif ($children < 0 || $children > 20) {
        $error = 'تعداد فرزندان نامعتبر است';
    } else {
        $update_stmt = mysqli_prepare($conn, "
            UPDATE salary_personnel 
            SET first_name = ?, last_name = ?, position = ?, department = ?, 
                national_code = ?, personnel_code = ?, base_salary = ?, 
                children_count = ?, insurance_number = ?, hire_date = ?
            WHERE id = ?
        ");
        
        mysqli_stmt_bind_param($update_stmt, "ssssssiissi", 
            $fname, $lname, $pos, $dept, $ncode, $pcode, $bsalary,
            $children, $insnum, $hdate, $personnel_id
        );
        
        if (mysqli_stmt_execute($update_stmt)) {
            $msg = 'اطلاعات با موفقیت بروزرسانی شد';
            // بروزرسانی اطلاعات نمایشی
            $stmt = mysqli_prepare($conn, "SELECT * FROM salary_personnel WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $personnel_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $person = mysqli_fetch_assoc($result);
        } else {
            $error = 'خطا در بروزرسانی اطلاعات';
        }
    }
}

function fm($n) { return number_format($n); }
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ویرایش اطلاعات - <?php echo $person['first_name'] . ' ' . $person['last_name']; ?></title>
    <link href="../assets/fonts/fonts.css" rel="stylesheet">
    <style>
        :root {
            --bg: #f5f6f8;
            --surface: #ffffff;
            --border: #e0e3e8;
            --text: #1a1f2e;
            --text-secondary: #555f6e;
            --accent: #3b6fd4;
            --danger: #ef4444;
            --success: #10b981;
            --radius: 14px;
            --shadow: 0 2px 12px rgba(0,0,0,0.06);
        }
        
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Vazirmatn', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            padding: 16px;
        }
        .container { max-width: 800px; margin: 0 auto; }
        
        .header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 14px 20px; background: var(--surface); border: 1px solid var(--border);
            border-radius: var(--radius); margin-bottom: 16px; box-shadow: var(--shadow);
            flex-wrap: wrap; gap: 10px;
        }
        .header h2 { font-size: 0.95rem; font-weight: 700; display: flex; align-items: center; gap: 8px; }
        
        .btn {
            padding: 8px 16px; border-radius: 8px; border: 1px solid var(--border);
            background: var(--surface); color: var(--text); text-decoration: none;
            font-family: 'Vazirmatn'; font-size: 0.75rem; cursor: pointer;
            transition: all 0.2s; display: inline-flex; align-items: center; gap: 6px;
        }
        .btn:hover { border-color: var(--accent); color: var(--accent); }
        .btn-primary { background: var(--accent); color: #fff; border: none; font-weight: 600; }
        .btn-primary:hover { opacity: 0.9; color: #fff; }
        .btn-danger { color: var(--danger); border-color: var(--danger); }
        .btn-danger:hover { background: var(--danger); color: #fff; }
        
        .card {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: var(--radius); padding: 24px; margin-bottom: 16px;
            box-shadow: var(--shadow);
        }
        .card h3 {
            font-size: 0.85rem; margin-bottom: 16px; padding-bottom: 10px;
            border-bottom: 1px solid var(--border); color: var(--accent);
        }
        
        .toast {
            padding: 10px 14px; border-radius: 10px; margin-bottom: 12px;
            font-weight: 600; font-size: 0.78rem;
        }
        .toast-success { background: var(--success); color: #fff; }
        .toast-error { background: var(--danger); color: #fff; }
        
        .form-group { margin-bottom: 14px; }
        .form-group label {
            display: block; font-size: 0.72rem; color: var(--text-secondary);
            margin-bottom: 5px; font-weight: 600;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%; padding: 10px 12px; border: 1px solid var(--border);
            border-radius: 8px; background: var(--bg); color: var(--text);
            font-family: 'Vazirmatn'; font-size: 0.8rem; transition: border-color 0.2s;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            border-color: var(--accent); outline: none;
            box-shadow: 0 0 0 3px rgba(59,111,212,0.1);
        }
        
        .row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .row3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; }
        
        .actions { display: flex; gap: 10px; margin-top: 20px; }
        .actions button { flex: 1; padding: 12px; font-size: 0.82rem; }
        
        @media (max-width: 600px) {
            .row2, .row3 { grid-template-columns: 1fr; }
            .header { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <div>
            <h2>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                </svg>
                ویرایش اطلاعات پرسنل
            </h2>
            <span style="font-size:0.7rem;color:var(--text-secondary);margin-right:28px;">
                <?php echo $person['first_name'] . ' ' . $person['last_name']; ?>
            </span>
        </div>
        <div style="display:flex; gap:8px;">
            <a href="archive.php?personnel_id=<?php echo $personnel_id; ?>" class="btn">مشاهده فیش‌ها</a>
            <a href="index.php" class="btn">بازگشت</a>
        </div>
    </div>
    
    <?php if ($msg): ?>
    <div class="toast toast-success"><?php echo $msg; ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
    <div class="toast toast-error"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <div class="card">
        <h3>
            اطلاعات پرسنلی
            <?php if ($person['personnel_code']): ?>
            <span style="font-size:0.7rem;color:var(--text-secondary);font-weight:400;">
                | کد پرسنلی: <?php echo $person['personnel_code']; ?>
            </span>
            <?php endif; ?>
        </h3>
        
        <form method="POST">
            <div class="row2">
                <div class="form-group">
                    <label>نام *</label>
                    <input type="text" name="first_name" value="<?php echo htmlspecialchars($person['first_name']); ?>" required>
                </div>
                <div class="form-group">
                    <label>نام خانوادگی *</label>
                    <input type="text" name="last_name" value="<?php echo htmlspecialchars($person['last_name']); ?>" required>
                </div>
            </div>
            
            <div class="row2">
                <div class="form-group">
                    <label>کد ملی</label>
                    <input type="text" name="national_code" maxlength="10" 
                           value="<?php echo htmlspecialchars($person['national_code']); ?>"
                           placeholder="۱۰ رقم">
                </div>
                <div class="form-group">
                    <label>کد پرسنلی</label>
                    <input type="text" name="personnel_code" 
                           value="<?php echo htmlspecialchars($person['personnel_code']); ?>">
                </div>
            </div>
            
            <div class="row2">
                <div class="form-group">
                    <label>سمت</label>
                    <input type="text" name="position" value="<?php echo htmlspecialchars($person['position']); ?>">
                </div>
                <div class="form-group">
                    <label>واحد / دپارتمان</label>
                    <input type="text" name="department" value="<?php echo htmlspecialchars($person['department']); ?>">
                </div>
            </div>
            
            <div class="row3">
                <div class="form-group">
                    <label>حقوق پایه (ریال)</label>
                    <input type="text" name="base_salary" value="<?php echo fm($person['base_salary']); ?>"
                           oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                </div>
                <div class="form-group">
                    <label>تعداد اولاد</label>
                    <input type="number" name="children_count" value="<?php echo $person['children_count']; ?>" 
                           min="0" max="20">
                </div>
                <div class="form-group">
                    <label>شماره بیمه</label>
                    <input type="text" name="insurance_number" value="<?php echo htmlspecialchars($person['insurance_number']); ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label>تاریخ استخدام</label>
                <input type="text" name="hire_date" value="<?php echo htmlspecialchars($person['hire_date']); ?>"
                       placeholder="مثال: ۱۴۰۲/۰۱/۰۱">
            </div>
            
            <div class="actions">
                <button type="submit" name="update_personnel" class="btn btn-primary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                        <polyline points="17 21 17 13 7 13 7 21"/>
                        <polyline points="7 3 7 8 15 8"/>
                    </svg>
                    ذخیره تغییرات
                </button>
                <button type="button" class="btn btn-danger" 
                        onclick="if(confirm('آیا از غیرفعال کردن این پرسنل اطمینان دارید؟')) window.location.href='index.php?delete=<?php echo $personnel_id; ?>'">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                    </svg>
                    غیرفعال کردن پرسنل
                </button>
            </div>
        </form>
    </div>
</div>
</body>
</html>