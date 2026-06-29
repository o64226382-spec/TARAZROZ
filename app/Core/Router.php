<?php
/**
 * Router - سیستم مسیریابی مدرن MVC
 * 
 * این کلاس تمام درخواست‌ها را دریافت و به کنترلر مناسب هدایت می‌کند
 */

namespace App\Core;

class Router {
    
    private static $instance = null;
    private $routes = [];
    private $middlewareGroups = [];
    private $currentRoute = null;
    
    /**
     * جلوگیری از ایجاد نمونه مستقیم
     */
    private function __construct() {}
    
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
     * تعریف مسیر GET
     */
    public static function get(string $path, $handler, array $middleware = []): void {
        $router = self::getInstance();
        $router->addRoute('GET', $path, $handler, $middleware);
    }
    
    /**
     * تعریف مسیر POST
     */
    public static function post(string $path, $handler, array $middleware = []): void {
        $router = self::getInstance();
        $router->addRoute('POST', $path, $handler, $middleware);
    }
    
    /**
     * تعریف مسیر PUT
     */
    public static function put(string $path, $handler, array $middleware = []): void {
        $router = self::getInstance();
        $router->addRoute('PUT', $path, $handler, $middleware);
    }
    
    /**
     * تعریف مسیر DELETE
     */
    public static function delete(string $path, $handler, array $middleware = []): void {
        $router = self::getInstance();
        $router->addRoute('DELETE', $path, $handler, $middleware);
    }
    
    /**
     * تعریف مسیر برای همه متدها
     */
    public static function match(array $methods, string $path, $handler, array $middleware = []): void {
        $router = self::getInstance();
        foreach ($methods as $method) {
            $router->addRoute(strtoupper($method), $path, $handler, $middleware);
        }
    }
    
    /**
     * افزودن مسیر به لیست مسیرها
     */
    private function addRoute(string $method, string $path, $handler, array $middleware = []): void {
        // نرمال‌سازی مسیر
        $path = '/' . trim($path, '/');
        
        $this->routes[$method][$path] = [
            'handler' => $handler,
            'middleware' => $middleware,
            'pattern' => $this->convertToPattern($path)
        ];
    }
    
    /**
     * تبدیل مسیر به الگوی Regex
     */
    private function convertToPattern(string $path): string {
        // تبدیل پارامترهای داینامیک {id} به (?P<id>[^/]+)
        $pattern = preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^/]+)', $path);
        return '#^' . $pattern . '$#u';
    }
    
    /**
     * ثبت گروه middleware
     */
    public static function middlewareGroup(string $name, array $middleware): void {
        $router = self::getInstance();
        $router->middlewareGroups[$name] = $middleware;
    }
    
    /**
     * اجرای روتر و یافتن مسیر مناسب
     */
    public function dispatch(): void {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $uri = '/' . trim($uri, '/');
        
        // بررسی CORS برای preflight requests
        if ($method === 'OPTIONS') {
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization');
            exit;
        }
        
        // جستجوی مسیر
        $route = $this->findRoute($method, $uri);
        
        if ($route === null) {
            // بررسی مسیرهای داینامیک
            $route = $this->findDynamicRoute($method, $uri);
        }
        
        if ($route === null) {
            http_response_code(404);
            $this->renderError('صفحه مورد نظر یافت نشد', 404);
            return;
        }
        
        $this->currentRoute = $route;
        
        // اجرای middlewareها
        if (!empty($route['middleware'])) {
            foreach ($route['middleware'] as $middleware) {
                $middlewares = $this->resolveMiddleware($middleware);
                foreach ($middlewares as $mw) {
                    if (method_exists($mw, 'handle')) {
                        $result = $mw->handle();
                        if ($result === false) {
                            return;
                        }
                    }
                }
            }
        }
        
        // اجرای handler
        $this->executeHandler($route['handler'], $route['params'] ?? []);
    }
    
    /**
     * یافتن مسیر ثابت
     */
    private function findRoute(string $method, string $uri): ?array {
        if (!isset($this->routes[$method])) {
            return null;
        }
        
        if (isset($this->routes[$method][$uri])) {
            return array_merge($this->routes[$method][$uri], ['params' => []]);
        }
        
        return null;
    }
    
    /**
     * یافتن مسیر داینامیک با Regex
     */
    private function findDynamicRoute(string $method, string $uri): ?array {
        if (!isset($this->routes[$method])) {
            return null;
        }
        
        foreach ($this->routes[$method] as $path => $route) {
            if (preg_match($route['pattern'], $uri, $matches)) {
                // حذف match کامل و نگهداری فقط پارامترها
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                return array_merge($route, ['params' => $params]);
            }
        }
        
        return null;
    }
    
    /**
     * resolve middleware نام به کلاس
     */
    private function resolveMiddleware(string $name): array {
        if (isset($this->middlewareGroups[$name])) {
            return $this->middlewareGroups[$name];
        }
        
        // تبدیل نام middleware به کلاس
        $className = "\\App\\Http\\Middleware\\ucfirst($name)";
        if (class_exists($className)) {
            return [$className];
        }
        
        return [];
    }
    
    /**
     * اجرای handler (Controller@method یا Closure)
     */
    private function executeHandler($handler, array $params = []): void {
        if (is_callable($handler)) {
            // Closure
            call_user_func_array($handler, $params);
            return;
        }
        
        if (is_string($handler) && strpos($handler, '@') !== false) {
            // Controller@method
            [$controllerClass, $method] = explode('@', $handler);
            
            if (!class_exists($controllerClass)) {
                throw new \Exception("کنترلر {$controllerClass} یافت نشد");
            }
            
            $controller = new $controllerClass();
            
            if (!method_exists($controller, $method)) {
                throw new \Exception("متد {$method} در کنترلر {$controllerClass} یافت نشد");
            }
            
            call_user_func_array([$controller, $method], $params);
            return;
        }
        
        throw new \Exception("Handler نامعتبر است");
    }
    
    /**
     * رندر خطا
     */
    private function renderError(string $message, int $code = 500): void {
        http_response_code($code);
        
        // بررسی JSON request
        if ($this->isJsonRequest()) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => $message,
                'code' => $code
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } else {
            // رندر صفحه خطا
            if (file_exists(BASE_PATH . '/resources/views/errors/' . $code . '.php')) {
                include BASE_PATH . '/resources/views/errors/' . $code . '.php';
            } else {
                echo "<h1>Error {$code}</h1><p>{$message}</p>";
            }
        }
    }
    
    /**
     * بررسی JSON request
     */
    private function isJsonRequest(): bool {
        return isset($_SERVER['HTTP_ACCEPT']) && 
               strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;
    }
    
    /**
     * تولید URL برای یک مسیر
     */
    public static function route(string $name, array $params = []): string {
        // پیاده‌سازی برای نام‌گذاری مسیرها
        return BASE_URL . '/' . $name;
    }
    
    /**
     * دریافت مسیر فعلی
     */
    public function getCurrentRoute(): ?array {
        return $this->currentRoute;
    }
}
