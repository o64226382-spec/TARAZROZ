<?php
/**
 * Controller - کلاس پایه کنترلرها
 * 
 * تمام کنترلرها باید از این کلاس ارث‌بری کنند
 */

namespace App\Core;

class Controller {
    
    protected $db;
    protected $auth;
    protected $request;
    protected $session;
    
    public function __construct() {
        $this->db = Database::getConnection();
        $this->request = new Request();
        $this->session = new Session();
        $this->auth = Auth::getInstance();
    }
    
    /**
     * رندر ویو با داده‌ها
     */
    protected function view(string $view, array $data = []): void {
        extract($data);
        
        $viewPath = BASE_PATH . '/resources/views/' . str_replace('.', '/', $view) . '.php';
        
        if (!file_exists($viewPath)) {
            throw new \Exception("ویو {$view} یافت نشد در مسیر: {$viewPath}");
        }
        
        include $viewPath;
    }
    
    /**
     * رندر JSON
     */
    protected function json(array $data, int $status = 200): void {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    /**
     * ریدایرکت به URL دیگر
     */
    protected function redirect(string $url, int $status = 302): void {
        header("Location: {$url}", true, $status);
        exit;
    }
    
    /**
     * ریدایرکت به عقب
     */
    protected function back(): void {
        $referer = $_SERVER['HTTP_REFERER'] ?? BASE_URL;
        $this->redirect($referer);
    }
    
    /**
     * اعتبارسنجی ورودی‌ها
     */
    protected function validate(array $rules, array $data = null): array {
        $validator = new Validator($data ?? $this->request->all());
        return $validator->validate($rules);
    }
    
    /**
     * بررسی دسترسی کاربر
     */
    protected function authorize(string $permission): bool {
        if (!$this->auth->check()) {
            $this->redirect(BASE_URL . '/login');
            return false;
        }
        
        if (!$this->auth->hasPermission($permission)) {
            http_response_code(403);
            $this->view('errors.403');
            return false;
        }
        
        return true;
    }
    
    /**
     * لاگ فعالیت کاربر
     */
    protected function logActivity(string $action, string $description = ''): void {
        if ($this->auth->check()) {
            ActivityLog::log($action, $description);
        }
    }
}
