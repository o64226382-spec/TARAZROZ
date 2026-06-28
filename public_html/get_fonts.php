<?php
// get_fonts.php - برگردوندن لیست فونت‌های موجود
header('Content-Type: application/json');

$fontsDir = __DIR__ . '/assets/fonts';
$fonts = [];

if (is_dir($fontsDir)) {
    $files = scandir($fontsDir);
    
    foreach ($files as $file) {
        // فقط فایل‌های ttf و otf رو بردار
        if (preg_match('/\.(ttf|otf)$/i', $file)) {
            // اسم فونت رو از اسم فایل استخراج کن
            $name = pathinfo($file, PATHINFO_FILENAME);
            // حذف پسوندهای مثل -Medium, -Bold و غیره
            $name = preg_replace('/-(Regular|Medium|Bold|Light|Thin|Black|ExtraBold|SemiBold|ExtraLight)/i', '', $name);
            
            $fonts[] = [
                'file' => $file,
                'name' => $name,
                'path' => 'assets/fonts/' . $file
            ];
        }
    }
}

echo json_encode($fonts);
?>