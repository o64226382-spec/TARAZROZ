<?php
/**
 * Auth Middleware - بررسی احراز هویت کاربر
 */

namespace App\Http\Middleware;

use App\Core\Auth;
use App\Core\Session;

class AuthMiddleware {
    
    /**
     * اجرای middleware
     */
    public function handle(): bool {
        $auth = Auth::getInstance();
        
        if (!$auth->check()) {
            // کاربر وارد نشده، ریدایرکت به لاگین
            Session::flash('error', 'لطفاً ابتدا وارد حساب کاربری شوید');
            header('Location: ' . BASE_URL . '/login');
            exit;
        }
        
        return true;
    }
}
