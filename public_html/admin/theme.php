<?php
define('SECURE_ACCESS', true);
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireAdmin();

// ⭐ CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$settings = [];
$res = mysqli_query($conn, "SELECT * FROM theme_settings");
while ($r = mysqli_fetch_assoc($res)) {
    $settings[$r['setting_name']] = $r['setting_value'];
}

$message = '';
if (isset($_GET['saved'])) {
    $message = '✅ تنظیمات تم با موفقیت ذخیره شد';
}

// ⭐ مقادیر پیش‌فرض
$defaults = [
    'primary_color'     => '#d4af37',  // طلایی اصلی
    'bg_color'          => '#0a0f1a',  // پس‌زمینه اصلی
    'surface_color'     => '#151a25',  // پس‌زمینه کارت‌ها
    'border_color'      => '#1e2533',  // حاشیه‌ها
    'text_color'        => '#e8ecf1',  // متن اصلی
    'text_secondary'    => '#8899aa',  // متن فرعی
    'accent_color'      => '#4b8cf7',  // رنگ تأکید (لینک‌ها)
    'green_color'       => '#10b981',  // سبز (موفقیت)
    'red_color'         => '#ef4444',  // قرمز (خطا)
    'purple_color'      => '#8b5cf6',  // بنفش (تنخواه)
    'amber_color'       => '#f0a040',  // کهربایی (بنکداران)
    'btn_bg'            => '#4b8cf7',  // پس‌زمینه دکمه
    'btn_text'          => '#ffffff',  // متن دکمه
    'header_bg'         => '#151a25',  // پس‌زمینه هدر
    'icon_color'        => '#d4af37',  // رنگ آیکون‌ها
    'input_bg'          => '#1a1f2e',  // پس‌زمینه input
    'input_border'      => '#2a3040',  // حاشیه input
    'shadow_color'      => '#000000',  // رنگ سایه
];
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تنظیمات تم | تراز روزانه</title>
    <link href="../assets/fonts/fonts.css" rel="stylesheet">
    <style>
        :root {
            --bg: #0a0f1a;
            --surface: rgba(255,255,255,0.03);
            --border: rgba(255,255,255,0.06);
            --text: #e8ecf1;
            --text-secondary: #8899aa;
            --accent: #4b8cf7;
            --gold: #d4af37;
            --radius: 14px;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Vazirmatn', sans-serif;
            background: var(--bg);
            color: var(--text);
            padding: 20px;
            min-height: 100vh;
        }
        .container { max-width: 900px; margin: 0 auto; }
        h2 { margin-bottom: 20px; font-size: 1.2rem; display: flex; align-items: center; gap: 8px; }
        
        .message {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 0.85rem;
            font-weight: 600;
            text-align: center;
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }
        
        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 20px;
            margin-bottom: 16px;
        }
        
        .colors-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 16px;
        }
        
        .color-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            background: rgba(255,255,255,0.02);
            border: 1px solid var(--border);
            border-radius: 10px;
            transition: all 0.2s;
        }
        .color-item:hover {
            background: rgba(255,255,255,0.04);
            border-color: rgba(255,255,255,0.1);
        }
        
        .color-preview {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            border: 2px solid var(--border);
            flex-shrink: 0;
        }
        
        .color-info {
            flex: 1;
            min-width: 0;
        }
        .color-info label {
            display: block;
            font-size: 0.72rem;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 4px;
        }
        .color-info input[type="color"] {
            width: 100%;
            height: 36px;
            border: 1px solid var(--border);
            border-radius: 6px;
            cursor: pointer;
            background: transparent;
            padding: 2px;
        }
        .color-info input[type="text"] {
            width: 100%;
            padding: 6px 8px;
            border: 1px solid var(--border);
            border-radius: 6px;
            background: rgba(255,255,255,0.03);
            color: var(--text);
            font-family: 'Vazirmatn', sans-serif;
            font-size: 0.75rem;
            margin-top: 4px;
        }
        
        .preview-section {
            margin-top: 20px;
            padding: 16px;
            border-radius: 10px;
            border: 2px dashed var(--border);
        }
        .preview-title {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 12px;
        }
        .preview-box {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
        }
        .preview-btn {
            padding: 8px 16px;
            border-radius: 8px;
            border: none;
            font-family: 'Vazirmatn', sans-serif;
            font-size: 0.78rem;
            font-weight: 600;
            cursor: pointer;
        }
        .preview-card {
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 0.75rem;
        }
        
        .btn-save {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #d4af37, #b8960f);
            color: #1a1a1a;
            border: none;
            border-radius: 10px;
            font-family: 'Vazirmatn', sans-serif;
            font-weight: 700;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 8px;
        }
        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(212,175,55,0.3);
        }
        
        .back-link {
            color: var(--accent);
            text-decoration: none;
            font-size: 0.78rem;
            display: inline-block;
            margin-bottom: 16px;
        }
        
        .section-title {
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--gold);
            margin-bottom: 14px;
            padding-bottom: 8px;
            border-bottom: 1px solid var(--border);
        }
        
        @media (max-width: 600px) {
            .colors-grid {
                grid-template-columns: 1fr;
            }
            body { padding: 10px; }
        }
    </style>
</head>
<body>
<div class="container">
    
    <a href="index.php" class="back-link">← بازگشت به پنل مدیریت</a>
    <h2>🎨 تنظیمات تم</h2>
    
    <?php if ($message): ?>
        <div class="message"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    
    <form method="POST" action="theme_handler.php">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        
        <!-- ⭐ رنگ‌های اصلی -->
        <div class="card">
            <div class="section-title">🎯 رنگ‌های اصلی</div>
            <div class="colors-grid">
                <?php
                $main_colors = [
                    'primary_color'     => '🌟 طلایی اصلی',
                    'bg_color'          => '📦 پس‌زمینه اصلی',
                    'surface_color'     => '📋 پس‌زمینه کارت‌ها',
                    'border_color'      => '📏 حاشیه‌ها',
                    'text_color'        => '📝 متن اصلی',
                    'text_secondary'    => '📎 متن فرعی',
                    'accent_color'      => '🔗 رنگ تأکید',
                    'icon_color'        => '🎯 رنگ آیکون‌ها',
                ];
                
                foreach ($main_colors as $key => $label):
                    $value = $settings[$key] ?? $defaults[$key];
                ?>
                <div class="color-item">
                    <div class="color-preview" style="background: <?php echo htmlspecialchars($value); ?>;"></div>
                    <div class="color-info">
                        <label><?php echo $label; ?></label>
                        <input type="color" name="<?php echo $key; ?>" value="<?php echo htmlspecialchars($value); ?>" onchange="this.parentElement.parentElement.querySelector('.color-preview').style.background = this.value; this.nextElementSibling.value = this.value;">
                        <input type="text" value="<?php echo htmlspecialchars($value); ?>" readonly>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- ⭐ رنگ‌های وضعیت -->
        <div class="card">
            <div class="section-title">🚦 رنگ‌های وضعیت</div>
            <div class="colors-grid">
                <?php
                $status_colors = [
                    'green_color'   => '✅ سبز (موفقیت)',
                    'red_color'     => '❌ قرمز (خطا)',
                    'purple_color'  => '💜 بنفش (تنخواه)',
                    'amber_color'   => '🟠 کهربایی (بنکداران)',
                ];
                
                foreach ($status_colors as $key => $label):
                    $value = $settings[$key] ?? $defaults[$key];
                ?>
                <div class="color-item">
                    <div class="color-preview" style="background: <?php echo htmlspecialchars($value); ?>;"></div>
                    <div class="color-info">
                        <label><?php echo $label; ?></label>
                        <input type="color" name="<?php echo $key; ?>" value="<?php echo htmlspecialchars($value); ?>" onchange="this.parentElement.parentElement.querySelector('.color-preview').style.background = this.value; this.nextElementSibling.value = this.value;">
                        <input type="text" value="<?php echo htmlspecialchars($value); ?>" readonly>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- ⭐ رنگ‌های المان‌ها -->
        <div class="card">
            <div class="section-title">🔧 رنگ‌های المان‌ها</div>
            <div class="colors-grid">
                <?php
                $element_colors = [
                    'btn_bg'        => '🔘 پس‌زمینه دکمه‌ها',
                    'btn_text'      => '📝 متن دکمه‌ها',
                    'header_bg'     => '📌 پس‌زمینه هدر',
                    'input_bg'      => '📥 پس‌زمینه فیلدها',
                    'input_border'  => '📏 حاشیه فیلدها',
                    'shadow_color'  => '🌑 رنگ سایه',
                ];
                
                foreach ($element_colors as $key => $label):
                    $value = $settings[$key] ?? $defaults[$key];
                ?>
                <div class="color-item">
                    <div class="color-preview" style="background: <?php echo htmlspecialchars($value); ?>;"></div>
                    <div class="color-info">
                        <label><?php echo $label; ?></label>
                        <input type="color" name="<?php echo $key; ?>" value="<?php echo htmlspecialchars($value); ?>" onchange="this.parentElement.parentElement.querySelector('.color-preview').style.background = this.value; this.nextElementSibling.value = this.value;">
                        <input type="text" value="<?php echo htmlspecialchars($value); ?>" readonly>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <button type="submit" class="btn-save">💾 ذخیره تنظیمات تم</button>
    </form>
    
</div>
</body>
</html>