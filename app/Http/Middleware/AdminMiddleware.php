<?php
/**
 * Admin Middleware - بررسی دسترسی ادمین
 */

namespace App\Http\Middleware;

use App\Core\Auth;
use App\Core\Session;

class AdminMiddleware {
    
    /**
     * اجرای middleware
     */
    public function handle(): bool {
        $auth = Auth::getInstance();
        
        // اول بررسی احراز هویت
        if (!$auth->check()) {
            Session::flash('error', 'لطفاً ابتدا وارد حساب کاربری شوید');
            header('Location: ' . BASE_URL . '/login');
            exit;
        }
        
        // بررسی نقش ادمین
        if (!$auth->hasRole('admin')) {
            Session::flash('error', 'دسترسی غیرمجاز - فقط مدیران می‌توانند به این بخش دسترسی داشته باشند');
            header('Location: ' . BASE_URL . '/user');
            exit;
        }
        
        return true;
    }
}
