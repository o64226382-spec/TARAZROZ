<?php
// persian_gd.php - نسخه نهایی که جواب میده
if (!class_exists('PersianGD')) {
    class PersianGD {
        public static function text($img, $size, $x, $y, $color, $fontPath, $text) {
            if (empty($text)) return 0;
            
            // تبدیل اعداد به فارسی
            $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
            $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
            $text = str_replace($english, $persian, $text);
            
            // 🔥 راز موفقیت: استفاده از mb_convert_encoding
            $text = mb_convert_encoding($text, 'HTML-ENTITIES', 'UTF-8');
            $text = preg_replace_callback('/&(#x)?([0-9a-f]+);/i', function($m) {
                $cp = hexdec($m[2]);
                return mb_chr($cp, 'UTF-8');
            }, $text);
            
            // برعکس کردن برای راست به چپ
            $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
            $text = implode('', array_reverse($chars));
            
            // چاپ
            $bbox = imagettfbbox($size, 0, $fontPath, $text);
            $width = $bbox[2] - $bbox[0];
            imagettftext($img, $size, 0, $x - $width, $y, $color, $fontPath, $text);
            return $width;
        }
    }
    
    // تابع کمکی اگر وجود نداشت
    if (!function_exists('mb_chr')) {
        function mb_chr($code, $encoding = 'UTF-8') {
            return mb_convert_encoding('&#' . $code . ';', $encoding, 'HTML-ENTITIES');
        }
    }
}
?>