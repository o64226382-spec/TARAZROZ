<?php
/**
 * CSRF Protection Helper
 * 
 * استفاده:
 * require_once 'includes/csrf.php';
 * 
 * در فرم:
 * <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
 * 
 * در پردازش POST:
 * if (!csrf_check($_POST['csrf_token'] ?? '')) {
 *     die('خطای امنیتی');
 * }
 */

if (!defined('SECURE_ACCESS')) {
    define('SECURE_ACCESS', true);
}

/**
 * تولید یا دریافت CSRF Token
 */
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * اعتبارسنجی CSRF Token
 */
function csrf_check($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * تولید CSRF Token جدید (بعد از عملیات موفق)
 */
function csrf_refresh() {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}

/**
 * نمایش فیلد مخفی CSRF برای فرم‌ها
 */
function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

/**
 * چک CSRF و نمایش خطا در صورت نامعتبر بودن
 */
function csrf_require() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!csrf_check($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            die(json_encode(['success' => false, 'message' => 'خطای امنیتی. لطفاً صفحه را رفرش کنید.']));
        }
    }
}