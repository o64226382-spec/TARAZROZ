<?php
/**
 * تعریف مسیرهای سیستم
 * 
 * تمام مسیرهای سایت در این فایل تعریف می‌شوند
 */

use App\Core\Router;

// ═══════════════════════════════════════════
// 🏠 صفحه اصلی و ورود
// ═══════════════════════════════════════════
Router::get('/', 'App\Controllers\HomeController@index');
Router::get('/login', 'App\Controllers\AuthController@showLogin');
Router::post('/login', 'App\Controllers\AuthController@login');
Router::get('/logout', 'App\Controllers\AuthController@logout');

// ═══════════════════════════════════════════
// 👤 پنل کاربری
// ═══════════════════════════════════════════
Router::get('/user', 'App\Controllers\User\DashboardController@index', ['auth']);
Router::get('/user/dashboard', 'App\Controllers\User\DashboardController@index', ['auth']);
Router::get('/user/daily-report', 'App\Controllers\User\DailyReportController@index', ['auth']);
Router::post('/user/daily-report/save', 'App\Controllers\User\DailyReportController@save', ['auth']);
Router::get('/user/income/daily', 'App\Controllers\User\IncomeController@daily', ['auth']);
Router::get('/user/income/monthly', 'App\Controllers\User\IncomeController@monthly', ['auth']);
Router::get('/user/goals', 'App\Controllers\User\GoalsController@index', ['auth']);

// ═══════════════════════════════════════════
// 🔧 مدیریت ابزارها (ماژولار)
// ═══════════════════════════════════════════
Router::get('/tools/{tool}', 'App\Controllers\Tools\ToolController@show');
Router::post('/tools/{tool}/action', 'App\Controllers\Tools\ToolController@action', ['auth']);

// ═══════════════════════════════════════════
// 📊 APIها
// ═══════════════════════════════════════════
Router::get('/api/get_dates', 'App\Controllers\Api\DateController@getDates');
Router::get('/api/get_calendar_data', 'App\Controllers\Api\CalendarController@getData');
Router::get('/api/observer_feed', 'App\Controllers\Api\ObserverController@feed');

// ═══════════════════════════════════════════
// ⚙️ پنل ادمین
// ═══════════════════════════════════════════
Router::get('/admin', 'App\Controllers\Admin\DashboardController@index', ['auth', 'admin']);
Router::get('/admin/users', 'App\Controllers\Admin\UserController@index', ['auth', 'admin']);
Router::post('/admin/users/store', 'App\Controllers\Admin\UserController@store', ['auth', 'admin']);
Router::post('/admin/users/update/{id}', 'App\Controllers\Admin\UserController@update', ['auth', 'admin']);
Router::post('/admin/users/delete/{id}', 'App\Controllers\Admin\UserController@delete', ['auth', 'admin']);

Router::get('/admin/tools', 'App\Controllers\Admin\ToolController@index', ['auth', 'admin']);
Router::post('/admin/tools/toggle', 'App\Controllers\Admin\ToolController@toggle', ['auth', 'admin']);
Router::post('/admin/tools/store', 'App\Controllers\Admin\ToolController@store', ['auth', 'admin']);
Router::post('/admin/tools/delete/{id}', 'App\Controllers\Admin\ToolController@delete', ['auth', 'admin']);

Router::get('/admin/income-items', 'App\Controllers\Admin\IncomeItemController@index', ['auth', 'admin']);
Router::post('/admin/income-items/toggle', 'App\Controllers\Admin\IncomeItemController@toggle', ['auth', 'admin']);
Router::post('/admin/income-items/store', 'App\Controllers\Admin\IncomeItemController@store', ['auth', 'admin']);
Router::post('/admin/income-items/delete/{id}', 'App\Controllers\Admin\IncomeItemController@delete', ['auth', 'admin']);

Router::get('/admin/dynamic-items', 'App\Controllers\Admin\DynamicItemController@index', ['auth', 'admin']);
Router::post('/admin/dynamic-items/toggle', 'App\Controllers\Admin\DynamicItemController@toggle', ['auth', 'admin']);
Router::post('/admin/dynamic-items/store', 'App\Controllers\Admin\DynamicItemController@store', ['auth', 'admin']);
Router::post('/admin/dynamic-items/delete/{id}', 'App\Controllers\Admin\DynamicItemController@delete', ['auth', 'admin']);

Router::get('/admin/goal-types', 'App\Controllers\Admin\GoalTypeController@index', ['auth', 'admin']);
Router::post('/admin/goal-types/toggle', 'App\Controllers\Admin\GoalTypeController@toggle', ['auth', 'admin']);
Router::post('/admin/goal-types/store', 'App\Controllers\Admin\GoalTypeController@store', ['auth', 'admin']);
Router::post('/admin/goal-types/delete/{id}', 'App\Controllers\Admin\GoalTypeController@delete', ['auth', 'admin']);

Router::get('/admin/themes', 'App\Controllers\Admin\ThemeController@index', ['auth', 'admin']);
Router::post('/admin/themes/activate', 'App\Controllers\Admin\ThemeController@activate', ['auth', 'admin']);
Router::post('/admin/themes/upload', 'App\Controllers\Admin\ThemeController@upload', ['auth', 'admin']);

Router::get('/admin/files', 'App\Controllers\Admin\FileController@index', ['auth', 'admin']);
Router::post('/admin/files/upload', 'App\Controllers\Admin\FileController@upload', ['auth', 'admin']);
Router::post('/admin/files/delete/{id}', 'App\Controllers\Admin\FileController@delete', ['auth', 'admin']);

Router::get('/admin/fonts', 'App\Controllers\Admin\FontController@index', ['auth', 'admin']);
Router::post('/admin/fonts/upload', 'App\Controllers\Admin\FontController@upload', ['auth', 'admin']);
Router::post('/admin/fonts/activate/{id}', 'App\Controllers\Admin\FontController@activate', ['auth', 'admin']);

Router::get('/admin/backups', 'App\Controllers\Admin\BackupController@index', ['auth', 'admin']);
Router::post('/admin/backups/create', 'App\Controllers\Admin\BackupController@create', ['auth', 'admin']);
Router::post('/admin/backups/restore/{id}', 'App\Controllers\Admin\BackupController@restore', ['auth', 'admin']);
Router::get('/admin/backups/download/{id}', 'App\Controllers\Admin\BackupController@download', ['auth', 'admin']);

Router::get('/admin/logs', 'App\Controllers\Admin\LogController@index', ['auth', 'admin']);

// ═══════════════════════════════════════════
// Middleware Groups
// ═══════════════════════════════════════════
Router::middlewareGroup('auth', [\App\Http\Middleware\AuthMiddleware::class]);
Router::middlewareGroup('admin', [\App\Http\Middleware\AdminMiddleware::class]);
Router::middlewareGroup('guest', [\App\Http\Middleware\GuestMiddleware::class]);
