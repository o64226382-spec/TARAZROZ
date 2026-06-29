<?php
/**
 * Session - مدیریت نشست‌ها
 */

namespace App\Core;

class Session {
    
    private static $initialized = false;
    
    /**
     * شروع نشست
     */
    public static function start(): void {
        if (!self::$initialized && session_status() === PHP_SESSION_NONE) {
            session_start();
            self::$initialized = true;
        }
    }
    
    /**
     * ذخیره مقدار در نشست
     */
    public static function set(string $key, $value): void {
        self::start();
        $_SESSION[$key] = $value;
    }
    
    /**
     * دریافت مقدار از نشست
     */
    public static function get(string $key, $default = null) {
        self::start();
        return $_SESSION[$key] ?? $default;
    }
    
    /**
     * بررسی وجود کلید در نشست
     */
    public static function has(string $key): bool {
        self::start();
        return isset($_SESSION[$key]);
    }
    
    /**
     * حذف مقدار از نشست
     */
    public static function forget(string $key): void {
        self::start();
        unset($_SESSION[$key]);
    }
    
    /**
     * دریافت و حذف همزمان
     */
    public static function pull(string $key, $default = null) {
        $value = self::get($key, $default);
        self::forget($key);
        return $value;
    }
    
    /**
     * فلش پیام (فقط برای درخواست بعدی)
     */
    public static function flash(string $key, $value): void {
        self::set("_flash_{$key}", $value);
    }
    
    /**
     * دریافت فلش پیام
     */
    public static function getFlash(string $key, $default = null) {
        return self::pull("_flash_{$key}", $default);
    }
    
    /**
     * دریافت همه فلش پیام‌ها
     */
    public static function getAllFlash(): array {
        self::start();
        $flashes = [];
        
        foreach ($_SESSION as $key => $value) {
            if (strpos($key, '_flash_') === 0) {
                $realKey = str_replace('_flash_', '', $key);
                $flashes[$realKey] = $value;
                unset($_SESSION[$key]);
            }
        }
        
        return $flashes;
    }
    
    /**
     * ریدایرکت با پیام خطا
     */
    public static function redirectWithError(string $url, string $message): void {
        self::flash('error', $message);
        header("Location: {$url}");
        exit;
    }
    
    /**
     * ریدایرکت با پیام موفقیت
     */
    public static function redirectWithSuccess(string $url, string $message): void {
        self::flash('success', $message);
        header("Location: {$url}");
        exit;
    }
    
    /**
     * دریافت پیام خطا
     */
    public static function getError(): ?string {
        return self::getFlash('error');
    }
    
    /**
     * دریافت پیام موفقیت
     */
    public static function getSuccess(): ?string {
        return self::getFlash('success');
    }
    
    /**
     * نابودی کامل نشست
     */
    public static function destroy(): void {
        self::start();
        session_destroy();
        self::$initialized = false;
    }
    
    /**
     * بازسازی ID نشست
     */
    public static function regenerate(): void {
        self::start();
        session_regenerate_id(true);
    }
}
