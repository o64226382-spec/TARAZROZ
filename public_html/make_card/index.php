<?php
/**
 * ============================================
 * ساخت کارت واریز زرگری ثنا 
 * مسیر: public_html/make_card/index.php
 * ============================================
 */
error_reporting(0);
date_default_timezone_set('Asia/Tehran');

// مسیر فونت و لوگو
define('FONT', __DIR__ . '/Vazirmatn-Medium.ttf');
define('LOGO', __DIR__ . '/logo.png');

// احراز هویت
define('SECURE_ACCESS', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

if (($_SESSION['role'] ?? '') !== 'branch') {
    die('دسترسی غیرمجاز');
}

// ========== کلاس رندر فارسی ==========
class PersianRender {
    public static function render($text) {
        if (empty($text)) return '';
        $no_attach = ['ا', 'آ', 'د', 'ذ', 'ر', 'ز', 'ژ', 'و', ' '];
        $map = [
            'ا'=>["\u{FE8D}","\u{FE8D}","\u{FE8E}","\u{FE8E}"],'ب'=>["\u{FE8F}","\u{FE91}","\u{FE92}","\u{FE90}"],
            'پ'=>["\u{FB56}","\u{FB58}","\u{FB59}","\u{FB57}"],'ت'=>["\u{FE95}","\u{FE97}","\u{FE98}","\u{FE96}"],
            'ث'=>["\u{FE99}","\u{FE9B}","\u{FE9C}","\u{FE9A}"],'ج'=>["\u{FE9D}","\u{FE9F}","\u{FEA0}","\u{FE9E}"],
            'چ'=>["\u{FB7A}","\u{FB7C}","\u{FB7D}","\u{FB7B}"],'ح'=>["\u{FEA1}","\u{FEA3}","\u{FEA4}","\u{FEA2}"],
            'خ'=>["\u{FEA5}","\u{FEA7}","\u{FEA8}","\u{FEA6}"],'د'=>["\u{FEA9}","\u{FEA9}","\u{FEAA}","\u{FEAA}"],
            'ذ'=>["\u{FEAB}","\u{FEAB}","\u{FEAC}","\u{FEAC}"],'ر'=>["\u{FEAD}","\u{FEAD}","\u{FEAE}","\u{FEAE}"],
            'ز'=>["\u{FEAF}","\u{FEAF}","\u{FEB0}","\u{FEB0}"],'ژ'=>["\u{FB8A}","\u{FB8A}","\u{FB8B}","\u{FB8B}"],
            'س'=>["\u{FEB1}","\u{FEB3}","\u{FEB4}","\u{FEB2}"],'ش'=>["\u{FEB5}","\u{FEB7}","\u{FEB8}","\u{FEB6}"],
            'ص'=>["\u{FEB9}","\u{FEBB}","\u{FEBC}","\u{FEBA}"],'ض'=>["\u{FEBD}","\u{FEBF}","\u{FEC0}","\u{FEBE}"],
            'ط'=>["\u{FEC1}","\u{FEC1}","\u{FEC2}","\u{FEC2}"],'ظ'=>["\u{FEC5}","\u{FEC5}","\u{FEC6}","\u{FEC6}"],
            'ع'=>["\u{FEC9}","\u{FECB}","\u{FECC}","\u{FECA}"],'غ'=>["\u{FECD}","\u{FECF}","\u{FED0}","\u{FECE}"],
            'ف'=>["\u{FED1}","\u{FED3}","\u{FED4}","\u{FED2}"],'ق'=>["\u{FED5}","\u{FED7}","\u{FED8}","\u{FED6}"],
            'ک'=>["\u{FB8E}","\u{FB90}","\u{FB91}","\u{FB8F}"],'گ'=>["\u{FB92}","\u{FB94}","\u{FB95}","\u{FB93}"],
            'ل'=>["\u{FEDD}","\u{FEDF}","\u{FEE0}","\u{FEDE}"],'م'=>["\u{FEE1}","\u{FEE3}","\u{FEE4}","\u{FEE2}"],
            'ن'=>["\u{FEE5}","\u{FEE7}","\u{FEE8}","\u{FEE6}"],'و'=>["\u{FEED}","\u{FEED}","\u{FEEE}","\u{FEEE}"],
            'ه'=>["\u{FEE9}","\u{FEEB}","\u{FEEC}","\u{FEEA}"],'ی'=>["\u{FBFC}","\u{FBFE}","\u{FBFF}","\u{FBFD}"],
            'آ'=>["\u{FE81}","\u{FE81}","\u{FE82}","\u{FE82}"]
        ];
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $res = []; $count = count($chars);
        for ($i = 0; $i < $count; $i++) {
            $curr = $chars[$i];
            if (!isset($map[$curr])) { $res[] = $curr; continue; }
            $prev = ($i > 0) ? $chars[$i-1] : null;
            $next = ($i < $count - 1) ? $chars[$i+1] : null;
            $can_prev = $prev && isset($map[$prev]) && !in_array($prev, $no_attach);
            $can_next = $next && isset($map[$next]);
            if ($can_prev && $can_next) $res[] = $map[$curr][2];
            elseif ($can_next) $res[] = $map[$curr][1];
            elseif ($can_prev) $res[] = $map[$curr][3];
            else $res[] = $map[$curr][0];
        }
        return implode('', array_reverse($res));
    }
}

function fa($text) { return PersianRender::render($text); }

function getWidth($text, $size, $font) { 
    $box = @imagettfbbox($size, 0, $font, $text); 
    if (!$box) return 0;
    return abs($box[4] - $box[0]); 
}

function drawRTL($img, $size, $y, $color, $font, $text, $right_x) {
    $rendered = fa($text);
    $w = getWidth($rendered, $size, $font);
    imagettftext($img, $size, 0, $right_x - $w, $y, $color, $font, $rendered);
}

function drawLTR_RightAlign($img, $size, $y, $color, $font, $text, $right_x) {
    $w = getWidth($text, $size, $font);
    imagettftext($img, $size, 0, $right_x - $w, $y, $color, $font, $text);
}

function numberToWords($number) {
    if ($number == 0) return 'صفر';
    $ones = ["", "یک", "دو", "سه", "چهار", "پنج", "شش", "هفت", "هشت", "نه"];
    $tens = ["", "ده", "بیست", "سی", "چهل", "پنجاه", "شصت", "هفتاد", "هشتاد", "نود"];
    $hundreds = ["", "صد", "دویست", "سیصد", "چهارصد", "پانصد", "ششصد", "هفتصد", "هشتصد", "نهصد"];
    $teens = [10=>"ده",11=>"یازده",12=>"دوازده",13=>"سیزده",14=>"چهارده",15=>"پانزده",16=>"شانزده",17=>"هفده",18=>"هجده",19=>"نوزده"];
    $levels = ["", "هزار", "میلیون", "میلیارد", "تریلیون"];
    $parts = []; $level = 0;
    while ($number > 0) {
        $chunk = $number % 1000;
        if ($chunk > 0) {
            $chunkWords = []; $h = floor($chunk / 100); $rem = $chunk % 100;
            if ($h > 0) $chunkWords[] = $hundreds[$h];
            if ($rem >= 10 && $rem < 20) $chunkWords[] = $teens[$rem];
            else { $t = floor($rem / 10); $o = $rem % 10; if ($t > 0) $chunkWords[] = $tens[$t]; if ($o > 0) $chunkWords[] = $ones[$o]; }
            $str = implode(" و ", $chunkWords);
            if ($level > 0) $str .= " " . $levels[$level];
            array_unshift($parts, $str);
        }
        $number = floor($number / 1000); $level++;
    }
    return implode(" و ", $parts);
}

$theme = $_GET['theme'] ?? 'dark';
$is_light = ($theme === 'light');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    session_start();
    $rate_key = 'card_gen_' . $_SESSION['user_id'];
    $last = $_SESSION[$rate_key] ?? 0;
    if (time() - $last < 3) {
        die('⏳ لطفاً کمی صبر کنید.');
    }
    $_SESSION[$rate_key] = time();

    $name = trim($_POST['name'] ?? '');
    $shaba = trim($_POST['shaba'] ?? '');
    $amount = trim($_POST['amount'] ?? '');
    $nc = trim($_POST['nc'] ?? '');
    $theme_post = trim($_POST['theme'] ?? 'dark');
    $is_light_post = ($theme_post === 'light');

    $errors = [];
    if (empty($name)) $errors[] = 'نام الزامی است.';
    if (empty($shaba)) $errors[] = 'شماره شبا/کارت الزامی است.';
    if (empty($amount) || !is_numeric($amount)) $errors[] = 'مبلغ نامعتبر است.';
    
    if (!empty($errors)) {
        $error = implode('<br>', $errors);
    } elseif (!file_exists(FONT)) {
        $error = 'فونت پیدا نشد.';
    } else {
        $name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $nc = preg_replace('/[^0-9]/', '', $nc);
        
        $amount_total = (int)((float)$amount * 1000000);
        $amount_num_str = number_format($amount_total);
        $amount_words = numberToWords($amount_total) . ' تومان';

        $shaba = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $shaba));
        if (is_numeric($shaba) && strlen($shaba) === 24) {
            $shaba = 'IR' . $shaba;
        }
        
        $is_shaba = (strpos($shaba, 'IR') === 0);
        if ($is_shaba) {
            $card_label = 'شماره شبا';
            $digits = substr($shaba, 2);
            $part1 = substr($digits, 0, 2);
            $part2 = substr($digits, 2);
            $formatted_shaba = 'IR' . $part1 . ' ' . trim(chunk_split($part2, 4, ' '));
        } else {
            $card_label = 'شماره کارت';
            $formatted_shaba = trim(chunk_split($shaba, 4, ' '));
        }

        $W = 1060; $H = 680;
        $img = imagecreatetruecolor($W, $H);
        imagealphablending($img, true); imagesavealpha($img, true);
        
        if ($is_light_post) {
            $c = [
                'bg'       => imagecolorallocate($img, 248, 249, 250),
                'card_bg'  => imagecolorallocate($img, 255, 255, 255),
                'left_bg'  => imagecolorallocate($img, 240, 242, 245),
                'gold'     => imagecolorallocate($img, 190, 145, 55),
                'text_pr'  => imagecolorallocate($img, 45, 45, 55),
                'text_sec' => imagecolorallocate($img, 130, 130, 140),
                'line'     => imagecolorallocate($img, 230, 230, 235),
            ];
        } else {
            $c = [
                'bg'       => imagecolorallocate($img, 20, 20, 24),
                'card_bg'  => imagecolorallocate($img, 32, 32, 38),
                'left_bg'  => imagecolorallocate($img, 26, 26, 30),
                'gold'     => imagecolorallocate($img, 225, 185, 75),
                'text_pr'  => imagecolorallocate($img, 250, 250, 250),
                'text_sec' => imagecolorallocate($img, 150, 150, 160),
                'line'     => imagecolorallocate($img, 60, 60, 70),
            ];
        }
        
        // 1. پس‌زمینه کل تصویر
        imagefilledrectangle($img, 0, 0, $W, $H, $c['bg']);
        
        $cx = 50; $cy = 50; $cw = $W - 100; $ch = $H - 100;
        
        // 2. پس‌زمینه اصلی کارت
        imagefilledrectangle($img, $cx, $cy, $cx + $cw, $cy + $ch, $c['card_bg']);
        
        // 3. نوار سمت چپ
        $lw = 280;
        imagefilledrectangle($img, $cx, $cy, $cx + $lw, $cy + $ch, $c['left_bg']);
        
        // 4. رسم حاشیه طلایی دوگانه
        imagesetthickness($img, 2);
        imagerectangle($img, $cx + 8, $cy + 8, $cx + $cw - 8, $cy + $ch - 8, $c['gold']);
        imagesetthickness($img, 1);
        imagerectangle($img, $cx, $cy, $cx + $cw, $cy + $ch, $c['gold']);
        
        // رسم لوگو و عنوان در سمت چپ
        if (file_exists(LOGO)) {
            $logo = @imagecreatefrompng(LOGO);
            if ($logo) {
                $lo = imagesx($logo); $lh = imagesy($logo);
                $nw = 150; $nh = (int)(($lh/$lo)*$nw);
                $lx_center = $cx + $lw/2; $logo_y = $cy + 160;
                imagecopyresampled($img, $logo, (int)($lx_center-$nw/2), $logo_y, 0, 0, $nw, $nh, $lo, $lh);
                $brand_y = $logo_y + $nh + 45;
                $tw1 = getWidth(fa('زرگری ثنا'), 20, FONT);
                imagettftext($img, 20, 0, (int)($lx_center-$tw1/2), $brand_y, $c['gold'], FONT, fa('زرگری ثنا'));
                imagedestroy($logo);
            }
        }
        
        $rx = $cx + $cw - 50; 
        $line_start_x = $cx + $lw + 40;

        // تنظیم ارتفاع پایه (بالاتر از نسخه قبل)
        $y = $cy + 65; 
        
        // 1. عنوان شماره کارت/شبا
        drawRTL($img, 14, $y, $c['text_sec'], FONT, $card_label, $rx); $y += 45;
        // 2. شماره شبا یا کارت
        drawLTR_RightAlign($img, 28, $y, $c['gold'], FONT, $formatted_shaba, $rx); $y += 70;
        
        imageline($img, $line_start_x, $y-30, $rx, $y-30, $c['line']);
        
        // 3. نام 
        drawRTL($img, 14, $y, $c['text_sec'], FONT, 'نام صاحب حساب', $rx); $y += 45;
        drawRTL($img, 32, $y, $c['text_pr'], FONT, $name, $rx); $y += 70;
        
        imageline($img, $line_start_x, $y-30, $rx, $y-30, $c['line']);
        
        // 4. مبلغ
        drawRTL($img, 14, $y, $c['text_sec'], FONT, 'مبلغ واریزی', $rx); $y += 50;
        $toman_text = fa('تومان');
        $amount_w = getWidth($amount_num_str, 42, FONT);
        $toman_w = getWidth($toman_text, 18, FONT);
        
        imagettftext($img, 18, 0, $rx - $amount_w - $toman_w - 15, $y - 5, $c['text_sec'], FONT, $toman_text);
        drawLTR_RightAlign($img, 42, $y, $c['text_pr'], FONT, $amount_num_str, $rx); $y += 50;
        
        drawRTL($img, 15, $y, $c['gold'], FONT, $amount_words, $rx); $y += 75;
        
        // 5. فوتر و کد ملی (در صورت وجود)
        imageline($img, $line_start_x, $y-40, $rx, $y-40, $c['line']);
        
        if (!empty($nc)) {
            $nc_formatted = 'کد ملی: ' . trim(chunk_split($nc, 3, ' '));
            drawRTL($img, 14, $y, $c['text_pr'], FONT, $nc_formatted, $rx);
            $y += 35;
        }

        drawRTL($img, 12, $y, $c['text_sec'], FONT, "کاربر گرامی، خواهشمند است مبلغ فوق را به حساب تایید شده واریز نمایید.", $rx); $y += 30;
        drawRTL($img, 12, $y, $c['text_sec'], FONT, 'جهت تایید نهایی، تصویر فیش واریزی همراه با کد پیگیری ارسال گردد.', $rx);
        
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: image/png');
        header('Content-Disposition: attachment; filename="card_' . time() . '.png"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        imagepng($img, null, 9);
        imagedestroy($img);
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ساخت کارت واریز | زرگری ثنا</title>
    <style>
        :root {
            --bg: <?php echo $is_light ? '#f8f9fa' : '#141418'; ?>;
            --card-bg: <?php echo $is_light ? '#ffffff' : '#1e1e24'; ?>;
            --border: <?php echo $is_light ? '#e0e0e0' : '#2b2b36'; ?>;
            --gold: <?php echo $is_light ? '#be9137' : '#e1b94b'; ?>;
            --text: <?php echo $is_light ? '#2d2d37' : '#fafafa'; ?>;
            --text-secondary: <?php echo $is_light ? '#82828c' : '#9696a0'; ?>;
            --input-bg: <?php echo $is_light ? '#f0f2f5' : '#18181c'; ?>;
            --radius: 10px;
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: Tahoma, sans-serif; background: var(--bg); color: var(--text); display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 20px; transition: all 0.3s ease; }
        .container { background: var(--card-bg); padding: 35px; border-radius: 16px; width: 100%; max-width: 440px; border: 1px solid var(--border); position: relative; }
        h2 { text-align: center; color: var(--gold); margin-bottom: 30px; font-size: 22px; font-weight: 600; }
        .error { background: <?php echo $is_light ? '#ffebee' : '#3d1c1c'; ?>; color: #ef5350; padding: 12px; border-radius: var(--radius); margin-bottom: 20px; font-size: 13px; text-align: center; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; color: var(--text-secondary); font-size: 13px; font-weight: bold; }
        input { width: 100%; padding: 14px; background: var(--input-bg); border: 1px solid var(--border); color: var(--text); border-radius: var(--radius); font-family: inherit; font-size: 14px; transition: border-color 0.3s; text-align: right; }
        input[name="shaba"], input[name="nc"] { text-align: left; direction: ltr; }
        input:focus { outline: none; border-color: var(--gold); }
        button { width: 100%; background: var(--gold); color: <?php echo $is_light ? '#fff' : '#121216'; ?>; border: none; padding: 16px; border-radius: var(--radius); cursor: pointer; font-weight: bold; font-size: 16px; margin-top: 10px; transition: background 0.3s; }
        button:hover { opacity: 0.9; }
        button:disabled { background: <?php echo $is_light ? '#ccc' : '#444'; ?>; cursor: not-allowed; }
        .note { text-align: center; color: var(--text-secondary); font-size: 12px; margin-top: 20px; }
        .theme-toggle { position: absolute; top: 20px; left: 20px; text-decoration: none; font-size: 20px; transition: transform 0.2s; }
        .theme-toggle:hover { transform: scale(1.1); }
    </style>
</head>
<body>
    <div class="container">
        <a href="?theme=<?php echo $is_light ? 'dark' : 'light'; ?>" class="theme-toggle" title="تغییر تم">
            <?php echo $is_light ? '🌙' : '☀️'; ?>
        </a>
        
        <h2>🎨 صدور فاکتور واریز</h2>
        <?php if (isset($error)): ?>
            <div class="error">❌ <?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="" id="cardForm">
            <input type="hidden" name="theme" value="<?php echo $is_light ? 'light' : 'dark'; ?>">
            
            <div class="form-group">
                <label>نام صاحب حساب</label>
                <input type="text" name="name" required placeholder="مثال: علی محمدی">
            </div>
            
            <div class="form-group">
                <label>شماره شبا (یا کارت)</label>
                <input type="text" name="shaba" required placeholder="IR12 3456... یا 62198610...">
            </div>
            
            <div class="form-group">
                <label>مبلغ (میلیون تومان)</label>
                <input type="number" step="any" name="amount" required placeholder="مثال: 4.365">
            </div>
            
            <div class="form-group">
                <label>کد ملی (اختیاری)</label>
                <input type="text" name="nc" placeholder="0012345678" maxlength="10">
            </div>
            
            <button type="submit" id="submitBtn">ساخت و دانلود تصویر</button>
        </form>
        <div class="note">فایل با فرمت PNG به صورت خودکار دانلود خواهد شد.</div>
    </div>
    <script>
    document.getElementById('cardForm').addEventListener('submit', function() {
        var btn = document.getElementById('submitBtn');
        btn.disabled = true;
        btn.textContent = '⏳ در حال ساخت...';
        setTimeout(() => { btn.disabled = false; btn.textContent = 'ساخت و دانلود تصویر'; }, 3000);
    });
    </script>
</body>
</html>
