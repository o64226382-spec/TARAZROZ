<?php
/**
 * Guest Middleware - فقط برای کاربران وارد نشده
 */

namespace App\Http\Middleware;

use App\Core\Auth;

class GuestMiddleware {
    
    /**
     * اجرای middleware
     */
    public function handle(): bool {
        $auth = Auth::getInstance();
        
        // اگر کاربر وارد شده، ریدایرکت به داشبورد
        if ($auth->check()) {
            header('Location: ' . BASE_URL . '/user/dashboard');
            exit;
        }
        
        return true;
    }
}
