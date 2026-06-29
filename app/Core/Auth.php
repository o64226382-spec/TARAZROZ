<?php
/**
 * Auth - سیستم احراز هویت و مدیریت نشست
 */

namespace App\Core;

class Auth {
    
    private static $instance = null;
    private $user = null;
    private $sessionId = 'taraz_auth';
    
    /**
     * جلوگیری از ایجاد نمونه مستقیم
     */
    private function __construct() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $this->loadUser();
    }
    
    /**
     * دریافت نمونه Singleton
     */
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * بارگذاری کاربر از نشست
     */
    private function loadUser(): void {
        if (isset($_SESSION[$this->sessionId])) {
            $this->user = $_SESSION[$this->sessionId];
        }
    }
    
    /**
     * ورود کاربر
     */
    public function login(int $userId, array $userData): bool {
        $_SESSION[$this->sessionId] = array_merge($userData, ['id' => $userId]);
        $this->user = $_SESSION[$this->sessionId];
        
        // بروزرسانی last_login
        Database::update('users', ['last_login' => date('Y-m-d H:i:s')], 'id = ?', [$userId]);
        
        return true;
    }
    
    /**
     * خروج کاربر
     */
    public function logout(): void {
        unset($_SESSION[$this->sessionId]);
        $this->user = null;
    }
    
    /**
     * بررسی ورود کاربر
     */
    public function check(): bool {
        return $this->user !== null;
    }
    
    /**
     * دریافت کاربر فعلی
     */
    public function user(): ?array {
        return $this->user;
    }
    
    /**
     * دریافت ID کاربر فعلی
     */
    public function id(): ?int {
        return $this->user['id'] ?? null;
    }
    
    /**
     * بررسی نقش کاربر
     */
    public function role(): ?string {
        return $this->user['role'] ?? null;
    }
    
    /**
     * بررسی داشتن نقش خاص
     */
    public function hasRole(string $role): bool {
        if (!$this->check()) {
            return false;
        }
        
        $userRoles = explode(',', $this->user['role'] ?? '');
        return in_array($role, $userRoles);
    }
    
    /**
     * بررسی داشتن دسترسی خاص
     */
    public function hasPermission(string $permission): bool {
        if (!$this->check()) {
            return false;
        }
        
        // ادمین کل دسترسی‌ها را دارد
        if ($this->hasRole('admin')) {
            return true;
        }
        
        // دریافت دسترسی‌های نقش از دیتابیس
        $roleId = $this->user['role_id'] ?? null;
        if (!$roleId) {
            return false;
        }
        
        $sql = "SELECT p.permission FROM role_permissions rp
                JOIN permissions p ON rp.permission_id = p.id
                WHERE rp.role_id = ?";
        $permissions = Database::fetchAll($sql, [$roleId]);
        
        foreach ($permissions as $perm) {
            if ($perm['permission'] === $permission || $perm['permission'] === '*') {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * بررسی داشتن هر یک از دسترسی‌ها
     */
    public function hasAnyPermission(array $permissions): bool {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * بررسی داشتن همه دسترسی‌ها
     */
    public function hasAllPermissions(array $permissions): bool {
        foreach ($permissions as $permission) {
            if (!$this->hasPermission($permission)) {
                return false;
            }
        }
        return true;
    }
    
    /**
     * دریافت شعبه کاربر
     */
    public function branch(): ?string {
        return $this->user['branch'] ?? null;
    }
    
    /**
     * بررسی تعلق به شعبه خاص
     */
    public function isBranch(string $branch): bool {
        if (!$this->check()) {
            return false;
        }
        
        $userBranches = explode(',', $this->user['branch'] ?? '');
        return in_array($branch, $userBranches);
    }
    
    /**
     * تولید توکن CSRF
     */
    public function generateCsrfToken(): string {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * بررسی توکن CSRF
     */
    public function verifyCsrfToken(string $token): bool {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * بازسازی توکن CSRF
     */
    public function regenerateCsrfToken(): string {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return $_SESSION['csrf_token'];
    }
}
