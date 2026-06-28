<?php
define('SECURE_ACCESS', true);
require_once 'includes/config.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'receipt') {
    header('Location: login.php');
    exit();
}

$branch_id = $_GET['branch_id'] ?? 0;
$selected_date = $_GET['date'] ?? '';
$message = '';

// دریافت شعب
$branches = mysqli_query($conn, "SELECT id, branch_name FROM users WHERE role = 'branch' ORDER BY branch_name");

// دریافت تاریخ‌های موجود به محض انتخاب شعبه
$available_dates = [];
if ($branch_id) {
    $dateRes = mysqli_query($conn, "SELECT DISTINCT report_date FROM daily_reports WHERE user_id = '$branch_id' ORDER BY report_date DESC");
    while ($row = mysqli_fetch_assoc($dateRes)) {
        $available_dates[] = $row['report_date'];
    }
}

// دریافت بدهکار/بستانکار بر اساس تاریخ و شعبه
$debtors = $creditors = [];
if ($branch_id && $selected_date) {
    $rep = mysqli_fetch_assoc(mysqli_query($conn, "SELECT report_data FROM daily_reports WHERE user_id = '$branch_id' AND report_date = '$selected_date'"));
    if ($rep) {
        $data = json_decode($rep['report_data'], true);
        $debtors = $data['debtors'] ?? [];
        $creditors = $data['creditors'] ?? [];
    }
    if (!$debtors && !$creditors) {
        $message = "⚠️ این تاریخ بدهکار یا بستانکاری ندارد.";
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>دریافت رسید | تراز روزانه</title>
    <link href="assets/fonts/fonts.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Vazirmatn', sans-serif;
            background: linear-gradient(135deg, #f0f4f8 0%, #ffffff 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 500px;
            margin: 0 auto;
        }
        .card {
            background: #ffffff;
            border-radius: 24px;
            padding: 32px 28px;
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.06);
            border: 1px solid #e8ecf1;
        }
        .card-title {
            font-size: 1.4em;
            font-weight: 900;
            color: #1a1f2e;
            margin-bottom: 6px;
            text-align: center;
        }
        .card-subtitle {
            color: #6b7280;
            font-size: 0.85em;
            text-align: center;
            margin-bottom: 28px;
        }
        .step {
            margin-bottom: 22px;
        }
        .step-label {
            display: block;
            font-weight: 700;
            font-size: 0.9em;
            color: #374151;
            margin-bottom: 8px;
        }
        .step-label .icon {
            margin-left: 6px;
        }
        select {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 14px;
            font-family: 'Vazirmatn', sans-serif;
            font-size: 1em;
            background: #f9fafb;
            color: #1f2937;
            cursor: pointer;
            transition: all 0.2s;
            appearance: none;
            -webkit-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%236b7280' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: left 16px center;
        }
        select:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
            background: #ffffff;
        }
        select:disabled {
            background: #f3f4f6;
            color: #9ca3af;
            cursor: not-allowed;
        }
        
        /* استایل انتخاب فونت */
        .font-selector-container {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .font-preview-box {
            padding: 15px;
            border: 2px dashed #e5e7eb;
            border-radius: 12px;
            text-align: center;
            font-size: 1.3em;
            background: #fafafa;
            min-height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }
        .font-sample-text {
            line-height: 1.8;
        }
        
        /* استایل انتخاب تم */
        .theme-selector {
            display: flex;
            gap: 12px;
            margin-top: 5px;
        }
        .theme-option {
            flex: 1;
            cursor: pointer;
            text-align: center;
            padding: 10px;
            border-radius: 12px;
            border: 2px solid #e5e7eb;
            transition: all 0.3s;
            background: #fafafa;
        }
        .theme-option:hover {
            border-color: #94a3b8;
            transform: translateY(-2px);
        }
        .theme-option.active {
            border-color: #3b82f6;
            background: #eff6ff;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.15);
        }
        .theme-option span {
            display: block;
            font-size: 0.8rem;
            margin-top: 8px;
            font-weight: 600;
            color: #374151;
        }
        .theme-preview {
            height: 60px;
            border-radius: 6px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .mini-header {
            height: 25px;
        }
        .mini-body {
            height: 35px;
            background: white;
        }
        .classic-preview .mini-header {
            background: linear-gradient(135deg, #1e40af, #3b82f6);
        }
        .modern-preview .mini-header {
            background: #1a1a1a;
        }
        .luxury-preview .mini-header {
            background: linear-gradient(180deg, #2d2d2d, #1a1a1a);
        }
        
        .btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #2563eb, #4f46e5);
            color: white;
            border: none;
            border-radius: 14px;
            font-family: 'Vazirmatn', sans-serif;
            font-weight: 700;
            font-size: 1.05em;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 4px 16px rgba(37, 99, 235, 0.25);
            margin-top: 10px;
        }
        .btn:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(37, 99, 235, 0.35);
        }
        .btn:disabled {
            background: #cbd5e1;
            box-shadow: none;
            cursor: not-allowed;
        }
        .message {
            padding: 12px 16px;
            border-radius: 12px;
            text-align: center;
            font-size: 0.85em;
            margin-top: 16px;
        }
        .message-warning {
            background: #fef3c7;
            color: #92400e;
        }
        .message-info {
            background: #dbeafe;
            color: #1e40af;
        }
        .divider {
            border: none;
            border-top: 1px solid #e8ecf1;
            margin: 20px 0;
        }
        .logout-link {
            display: block;
            text-align: center;
            color: #9ca3af;
            font-size: 0.8em;
            margin-top: 16px;
            text-decoration: none;
        }
        .logout-link:hover {
            color: #ef4444;
        }
    </style>
</head>
<body>

    <div class="container">
        <div class="card">
            <h1 class="card-title">📄 صدور رسید</h1>
            <p class="card-subtitle">انتخاب شعبه، تاریخ و طرف حساب</p>

            <form id="receiptForm" method="GET" action="receipt.php" onsubmit="return validateForm()">
                
                <!-- فیلدهای مخفی -->
                <input type="hidden" name="font" id="selectedFont" value="">
                <input type="hidden" name="font_name" id="selectedFontName" value="">
                <input type="hidden" name="theme" id="selectedTheme" value="classic">

                <!-- مرحله ۱: انتخاب شعبه -->
                <div class="step">
                    <label class="step-label">
                        <span class="icon">🏢</span> شعبه
                    </label>
                    <select name="branch_id" id="branchSelect" required onchange="onBranchChange()">
                        <option value="">-- انتخاب شعبه --</option>
                        <?php while ($b = mysqli_fetch_assoc($branches)): ?>
                            <option value="<?= $b['id'] ?>" <?= $branch_id == $b['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($b['branch_name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <!-- مرحله ۲: انتخاب تاریخ -->
                <div class="step">
                    <label class="step-label">
                        <span class="icon">📅</span> تاریخ تراز
                    </label>
                    <select name="date" id="dateSelect" required onchange="onDateChange()" <?= !$branch_id ? 'disabled' : '' ?>>
                        <option value="">-- انتخاب تاریخ --</option>
                        <?php foreach ($available_dates as $d): ?>
                            <option value="<?= $d ?>" <?= $selected_date == $d ? 'selected' : '' ?>>
                                <?= str_replace('-', '/', $d) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($branch_id && empty($available_dates)): ?>
                        <div class="message message-warning">⚠️ این شعبه هیچ گزارشی ندارد.</div>
                    <?php endif; ?>
                </div>

                <!-- مرحله ۳: انتخاب شخص -->
                <div class="step">
                    <label class="step-label">
                        <span class="icon">👤</span> طرف حساب
                    </label>
                    <select name="person" id="personSelect" required <?= (!$selected_date) ? 'disabled' : '' ?>>
                        <option value="">-- انتخاب طرف حساب --</option>
                        <?php if ($debtors): ?>
                            <optgroup label="🔴 بدهکاران">
                                <?php foreach ($debtors as $d): ?>
                                    <option value="<?= htmlspecialchars($d['name']) ?>|debtor">
                                        <?= htmlspecialchars($d['name']) ?> (<?= number_format($d['amt']) ?> ریال)
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endif; ?>
                        <?php if ($creditors): ?>
                            <optgroup label="🟢 بستانکاران">
                                <?php foreach ($creditors as $c): ?>
                                    <option value="<?= htmlspecialchars($c['name']) ?>|creditor">
                                        <?= htmlspecialchars($c['name']) ?> (<?= number_format($c['amt']) ?> ریال)
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endif; ?>
                    </select>
                </div>

                <!-- مرحله ۴: انتخاب فونت -->
                <div class="step">
                    <label class="step-label">
                        <span class="icon">🔤</span> فونت رسید
                    </label>
                    <div class="font-selector-container">
                        <select id="fontSelect" onchange="changeFont(this.value)">
                            <option value="">در حال بارگذاری فونت‌ها...</option>
                        </select>
                        <div class="font-preview-box" id="fontPreview">
                            <span class="font-sample-text">نمایش نمونه فونت - آب‌انبار ۱۲۳</span>
                        </div>
                    </div>
                </div>

                <!-- مرحله ۵: انتخاب تم رسید -->
                <div class="step">
                    <label class="step-label">
                        <span class="icon">🎨</span> طرح رسید
                    </label>
                    <div class="theme-selector">
                        <div class="theme-option active" data-theme="classic" onclick="selectTheme('classic', this)">
                            <div class="theme-preview classic-preview">
                                <div class="mini-header"></div>
                                <div class="mini-body"></div>
                            </div>
                            <span>کلاسیک</span>
                        </div>
                        <div class="theme-option" data-theme="modern" onclick="selectTheme('modern', this)">
                            <div class="theme-preview modern-preview">
                                <div class="mini-header"></div>
                                <div class="mini-body"></div>
                            </div>
                            <span>مدرن</span>
                        </div>
                        <div class="theme-option" data-theme="luxury" onclick="selectTheme('luxury', this)">
                            <div class="theme-preview luxury-preview">
                                <div class="mini-header"></div>
                                <div class="mini-body"></div>
                            </div>
                            <span>لوکس</span>
                        </div>
                    </div>
                </div>

                <?php if ($message): ?>
                    <div class="message message-warning"><?= $message ?></div>
                <?php endif; ?>

                <!-- دکمه دریافت رسید -->
                <button type="submit" class="btn" id="submitBtn" disabled>
                    📥 دریافت رسید
                </button>
            </form>

            <hr class="divider">
            <div class="message message-info">
                ✅ فقط تاریخ‌هایی که گزارش ثبت‌شده دارند نمایش داده می‌شوند.
            </div>
            <a href="logout.php" class="logout-link">🚪 خروج از حساب</a>
        </div>
    </div>

    <script>
        let fontsList = [];
        
        // بارگذاری لیست فونت‌ها
        async function loadFonts() {
            try {
                const response = await fetch('get_fonts.php');
                fontsList = await response.json();
                
                const select = document.getElementById('fontSelect');
                select.innerHTML = '';
                
                if (fontsList.length === 0) {
                    select.innerHTML = '<option value="">هیچ فونتی یافت نشد</option>';
                    document.getElementById('fontPreview').querySelector('.font-sample-text').textContent = 
                        'هیچ فونتی در پوشه assets/fonts وجود ندارد';
                    return;
                }
                
                fontsList.forEach((font, index) => {
                    const option = document.createElement('option');
                    option.value = font.path;
                    option.textContent = font.name;
                    option.setAttribute('data-index', index);
                    
                    if (font.name.toLowerCase().includes('vazir')) {
                        option.selected = true;
                    }
                    
                    select.appendChild(option);
                });
                
                if (select.value) {
                    changeFont(select.value);
                } else if (fontsList.length > 0) {
                    select.selectedIndex = 0;
                    changeFont(select.value);
                }
                
            } catch (error) {
                console.error('خطا در بارگذاری فونت‌ها:', error);
                document.getElementById('fontSelect').innerHTML = '<option value="">خطا در بارگذاری</option>';
            }
        }
        
        // تغییر فونت
        function changeFont(fontPath) {
            if (!fontPath) return;
            
            const preview = document.getElementById('fontPreview');
            const selectedFont = document.getElementById('selectedFont');
            const selectedFontName = document.getElementById('selectedFontName');
            
            const fontInfo = fontsList.find(f => f.path === fontPath);
            if (!fontInfo) return;
            
            selectedFont.value = fontPath;
            selectedFontName.value = fontInfo.name;
            
            const fontName = 'CustomFont_' + Date.now();
            const style = document.createElement('style');
            style.id = 'dynamic-font-style';
            
            style.textContent = `
                @font-face {
                    font-family: '${fontName}';
                    src: url('${fontPath}') format('truetype');
                    font-weight: normal;
                    font-style: normal;
                }
                #fontPreview .font-sample-text {
                    font-family: '${fontName}', 'Vazirmatn', sans-serif;
                }
            `;
            
            const oldStyle = document.getElementById('dynamic-font-style');
            if (oldStyle) oldStyle.remove();
            
            document.head.appendChild(style);
            
            preview.querySelector('.font-sample-text').textContent = 
                `نمونه فونت ${fontInfo.name} - آب‌انبار ۱۲۳`;
        }
        
        // انتخاب تم
        function selectTheme(theme, element) {
            document.querySelectorAll('.theme-option').forEach(opt => {
                opt.classList.remove('active');
            });
            element.classList.add('active');
            document.getElementById('selectedTheme').value = theme;
        }
        
        // تغییر شعبه
        function onBranchChange() {
            var branch = document.getElementById('branchSelect').value;
            if (branch) {
                window.location.href = 'receipt_select.php?branch_id=' + branch;
            } else {
                window.location.href = 'receipt_select.php';
            }
        }

        // تغییر تاریخ
        function onDateChange() {
            var branch = document.getElementById('branchSelect').value;
            var date = document.getElementById('dateSelect').value;
            if (branch && date) {
                window.location.href = 'receipt_select.php?branch_id=' + branch + '&date=' + date;
            }
        }

        // فعال/غیرفعال کردن دکمه
        document.getElementById('personSelect').addEventListener('change', function() {
            var personVal = this.value;
            var submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = !personVal;
        });

        // اعتبارسنجی
        function validateForm() {
            var branch = document.getElementById('branchSelect').value;
            var date = document.getElementById('dateSelect').value;
            var person = document.getElementById('personSelect').value;

            if (!branch || !date || !person) {
                alert('❌ لطفاً همه موارد را انتخاب کنید.');
                return false;
            }
            return true;
        }

        // لود اولیه
        window.onload = function() {
            var personVal = document.getElementById('personSelect').value;
            var submitBtn = document.getElementById('submitBtn');
            if (personVal) {
                submitBtn.disabled = false;
            }
            loadFonts();
        };
    </script>

</body>
</html>