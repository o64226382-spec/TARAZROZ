<?php
define('SECURE_ACCESS', true);
require_once 'includes/config.php';
require_once 'includes/auth.php';

// ========== CSRF Token ==========
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$portal = $_GET['portal'] ?? '';
$error = '';

// ========== ۱. اگر کاربر قبلاً لاگین کرده، هوشمندانه هدایتش کن ==========
if (isLoggedIn()) {
    $user = getCurrentUser();
    $role = $user['role'];
    $permissions = $user['permissions'] ?? '';

    // مقصدهای ثابت برای هر نقش
    $redirects = [
        'observer' => 'index.php',
        'branch'   => 'index.php',   // ✅ اصلاح: کاربر branch به داشبورد اصلی میره
        'admin'    => 'index.php'
    ];
    
    // اگر portal مشخص شده
    if ($portal) {
        $stmt = mysqli_prepare($conn, "SELECT url FROM tools WHERE slug = ? AND active = 1");
        mysqli_stmt_bind_param($stmt, "s", $portal);
        mysqli_stmt_execute($stmt);
        $check_tool = mysqli_stmt_get_result($stmt);
        
        if ($check_tool && mysqli_num_rows($check_tool) > 0) {
            $tool_url = mysqli_fetch_assoc($check_tool)['url'];
            
            if (strpos($permissions, $portal) !== false || $role === 'admin') {
                header('Location: ' . $tool_url);
                exit;
            } else {
                header('Location: tool_intro.php?tool=' . urlencode($portal) . '&denied=1');
                exit;
            }
        }
    }

    // هدایت به مسیر اصلی نقش
    if (isset($redirects[$role])) {
        header('Location: ' . $redirects[$role]);
    } else {
        header('Location: index.php');
    }
    exit();
}

// ========== ۲. پردازش فرم لاگین ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    
    // چک CSRF Token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = 'خطای امنیتی. لطفاً صفحه را رفرش کنید.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password_input = $_POST['password'] ?? '';
        
        if (empty($username) || empty($password_input)) {
            $error = 'لطفاً نام کاربری و رمز عبور را وارد کنید.';
        } else {
            // ⭐ Prepared Statement برای جلوگیری از SQL Injection
            $stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE username = ? LIMIT 1");
            mysqli_stmt_bind_param($stmt, "s", $username);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if (mysqli_num_rows($result) === 1) {
                $user = mysqli_fetch_assoc($result);
                $stored_password = $user['password'];
                $authenticated = false;
                
                // ⭐ چک پسورد: اول Bcrypt، بعد MD5 (برای سازگاری با پسوردهای قدیمی)
                if (password_verify($password_input, $stored_password)) {
                    $authenticated = true;
                } elseif (strlen($stored_password) === 32 && md5($password_input) === $stored_password) {
                    // ⭐ تبدیل خودکار MD5 به Bcrypt
                    $authenticated = true;
                    $new_hash = password_hash($password_input, PASSWORD_BCRYPT);
                    $update_stmt = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id = ?");
                    mysqli_stmt_bind_param($update_stmt, "si", $new_hash, $user['id']);
                    mysqli_stmt_execute($update_stmt);
                }
                
                if ($authenticated) {
                    // ⭐ لاگین موفق
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['branch_name'] = $user['branch_name'];
                    $_SESSION['role'] = $user['role'] ?? 'branch';
                    
                    // ⭐ بروزرسانی last_activity با Prepared Statement
                    $update_stmt = mysqli_prepare($conn, "UPDATE users SET last_activity = NOW() WHERE id = ?");
                    mysqli_stmt_bind_param($update_stmt, "i", $user['id']);
                    mysqli_stmt_execute($update_stmt);
                    
                    // تولید CSRF Token جدید بعد از لاگین
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    
                    $role = $user['role'];
                    $permissions = $user['permissions'] ?? '';
                    
                    $redirects = [
                        'observer' => 'index.php',
                        'branch'   => 'index.php',   // ✅ اصلاح: کاربر branch به داشبورد اصلی میره
                        'admin'    => 'index.php'
                    ];
                    
                    if ($portal) {
                        $stmt = mysqli_prepare($conn, "SELECT url FROM tools WHERE slug = ? AND active = 1");
                        mysqli_stmt_bind_param($stmt, "s", $portal);
                        mysqli_stmt_execute($stmt);
                        $check_tool = mysqli_stmt_get_result($stmt);
                        
                        if ($check_tool && mysqli_num_rows($check_tool) > 0) {
                            $tool_url = mysqli_fetch_assoc($check_tool)['url'];
                            if (strpos($permissions, $portal) !== false || $role === 'admin') {
                                header('Location: ' . $tool_url);
                                exit;
                            } else {
                                header('Location: tool_intro.php?tool=' . urlencode($portal) . '&denied=1');
                                exit;
                            }
                        }
                    }
                    
                    if (isset($redirects[$role])) {
                        header('Location: ' . $redirects[$role]);
                    } else {
                        header('Location: index.php');
                    }
                    exit();
                    
                } else {
                    $error = 'نام کاربری یا رمز عبور اشتباه است.';
                }
            } else {
                $error = 'نام کاربری یا رمز عبور اشتباه است.';
            }
        }
    }
}

// ========== ۳. تولید CSRF Token جدید برای فرم ==========
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$base_url = 'http://' . $_SERVER['HTTP_HOST'];
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>ورود | تراز روزانه</title>
    <link href="assets/fonts/fonts.css" rel="stylesheet">
    <link href="assets/css/dynamic-theme.php" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Vazirmatn', sans-serif;
            background: #080c17;
            min-height: 100vh;
            display: flex; justify-content: center; align-items: center; padding: 20px;
            background-image: radial-gradient(ellipse at 30% 20%, rgba(59,130,246,0.08) 0%, transparent 60%),
                            radial-gradient(ellipse at 70% 60%, rgba(139,92,246,0.06) 0%, transparent 60%);
        }
        .login-container {
            max-width: 400px; width: 100%;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: 20px; padding: 32px 24px;
            backdrop-filter: blur(16px);
        }
        .login-title {
            font-size: 1.6em; font-weight: 900; text-align: center; margin-bottom: 6px;
            background: linear-gradient(to left, #60a5fa, #a78bfa);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
        }
        .login-subtitle { text-align: center; color: #94a3b8; margin-bottom: 28px; font-size: 0.85em; }
        .input-group { margin-bottom: 18px; }
        .input-group label { display: block; margin-bottom: 6px; color: #e2e8f0; font-weight: 600; font-size: 0.85em; }
        .input-group input {
            width: 100%; padding: 12px 14px;
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 10px; color: #e2e8f0;
            font-family: 'Vazirmatn', sans-serif; font-size: 0.95em;
            transition: border-color 0.2s;
        }
        .input-group input:focus { outline: none; border-color: #3b82f6; }
        .login-btn {
            width: 100%; padding: 13px;
            background: linear-gradient(135deg, #2563eb, #7c3aed);
            color: white; border: none; border-radius: 10px;
            font-family: 'Vazirmatn', sans-serif; font-weight: 700; font-size: 1em;
            cursor: pointer; transition: all 0.2s; margin-top: 8px;
        }
        .login-btn:hover { filter: brightness(1.15); transform: translateY(-1px); }
        .error-message {
            background: rgba(239,68,68,0.12); border: 1px solid rgba(239,68,68,0.25);
            color: #ef4444; padding: 10px 14px; border-radius: 10px;
            margin-bottom: 18px; text-align: center; font-size: 0.85em;
        }
        .footer-note { text-align: center; margin-top: 20px; color: #64748b; font-size: 0.75rem; }
        .back-link { display: block; text-align: center; margin-top: 12px; color: #60a5fa; text-decoration: none; font-size: 0.8rem; }
        .back-link:hover { text-decoration: underline; }
    </style>
    <link rel="icon" type="image/png" href="<?php echo $base_url; ?>/assets/images/logo.png">
</head>
<body>
    <div class="login-container">
        <h1 class="login-title">تراز روزانه</h1>
        <p class="login-subtitle">ورود به سامانه مدیریت جابجایی وجوه</p>
        
        <?php if ($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <!-- ⭐ CSRF Token -->
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            
            <div class="input-group">
                <label>نام کاربری</label>
                <input type="text" name="username" placeholder="نام کاربری" required autofocus 
                       value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
            </div>
            <div class="input-group">
                <label>رمز عبور</label>
                <input type="password" name="password" placeholder="رمز عبور" required>
            </div>
            <button type="submit" name="login" class="login-btn">ورود به پنل</button>
        </form>
        <a href="index.php" class="back-link">← بازگشت به صفحه اصلی</a>
        <p class="footer-note">© ۱۴۰۵ · تراز روزانه</p>
    </div>
</body>
</html>