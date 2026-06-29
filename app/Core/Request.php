<?php
/**
 * Request - مدیریت درخواست‌های HTTP
 */

namespace App\Core;

class Request {
    
    private $get;
    private $post;
    private $files;
    private $server;
    
    public function __construct() {
        $this->get = $_GET;
        $this->post = $_POST;
        $this->files = $_FILES;
        $this->server = $_SERVER;
    }
    
    /**
     * دریافت همه پارامترهای GET
     */
    public function query(): array {
        return $this->get;
    }
    
    /**
     * دریافت همه پارامترهای POST
     */
    public function all(): array {
        return $this->post;
    }
    
    /**
     * دریافت پارامتر خاص از GET
     */
    public function get(string $key, $default = null) {
        return $this->get[$key] ?? $default;
    }
    
    /**
     * دریافت پارامتر خاص از POST
     */
    public function post(string $key, $default = null) {
        return $this->post[$key] ?? $default;
    }
    
    /**
     * دریافت پارامتر از GET یا POST
     */
    public function input(string $key, $default = null) {
        return $this->post[$key] ?? $this->get[$key] ?? $default;
    }
    
    /**
     * بررسی وجود پارامتر
     */
    public function has(string $key): bool {
        return isset($this->post[$key]) || isset($this->get[$key]);
    }
    
    /**
     * دریافت فایل آپلود شده
     */
    public function file(string $key): ?array {
        return $this->files[$key] ?? null;
    }
    
    /**
     * بررسی وجود فایل
     */
    public function hasFile(string $key): bool {
        return isset($this->files[$key]) && $this->files[$key]['error'] === UPLOAD_ERR_OK;
    }
    
    /**
     * دریافت متد HTTP
     */
    public function method(): string {
        return $this->server['REQUEST_METHOD'];
    }
    
    /**
     * بررسی AJAX request
     */
    public function ajax(): bool {
        return isset($this->server['HTTP_X_REQUESTED_WITH']) && 
               strtolower($this->server['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
    
    /**
     * دریافت URL فعلی
     */
    public function url(): string {
        $protocol = $this->isHttps() ? 'https' : 'http';
        $host = $this->server['HTTP_HOST'] ?? 'localhost';
        $path = $this->server['REQUEST_URI'] ?? '/';
        return $protocol . '://' . $host . $path;
    }
    
    /**
     * بررسی HTTPS
     */
    public function isHttps(): bool {
        return (!empty($this->server['HTTPS']) && $this->server['HTTPS'] !== 'off') ||
               (isset($this->server['SERVER_PORT']) && $this->server['SERVER_PORT'] == 443) ||
               (isset($this->server['HTTP_X_FORWARDED_PROTO']) && $this->server['HTTP_X_FORWARDED_PROTO'] === 'https');
    }
    
    /**
     * دریافت IP کاربر
     */
    public function ip(): string {
        $keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        
        foreach ($keys as $key) {
            if (!empty($this->server[$key])) {
                $ip = $this->server[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                return trim($ip);
            }
        }
        
        return '127.0.0.1';
    }
    
    /**
     * دریافت User Agent
     */
    public function userAgent(): string {
        return $this->server['HTTP_USER_AGENT'] ?? '';
    }
    
    /**
     * بررسی JSON request
     */
    public function isJson(): bool {
        $contentType = $this->server['CONTENT_TYPE'] ?? '';
        return strpos($contentType, 'application/json') !== false;
    }
    
    /**
     * دریافت بدنه JSON
     */
    public function json(): array {
        $content = file_get_contents('php://input');
        return json_decode($content, true) ?? [];
    }
    
    /**
     * اعتبارسنجی توکن CSRF
     */
    public function isValidCsrf(): bool {
        $token = $this->post('_token') ?? $this->get('_token');
        return Auth::getInstance()->verifyCsrfToken($token ?? '');
    }
}
