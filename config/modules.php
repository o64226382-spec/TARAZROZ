<?php
/**
 * تنظیمات ماژول‌ها
 */

return [
    'modules' => [
        'daily_report' => [
            'name' => 'تراز روزانه',
            'description' => 'سیستم ثبت و مدیریت تراز روزانه شعب',
            'version' => '2.0.0',
            'active' => true,
            'order' => 1,
            'icon' => '📊',
            'routes' => ['daily-report', 'daily-report/*']
        ],
        'income' => [
            'name' => 'درآمد',
            'description' => 'مدیریت درآمد روزانه و ماهانه',
            'version' => '2.0.0',
            'active' => true,
            'order' => 2,
            'icon' => '💰',
            'routes' => ['income', 'income/*']
        ],
        'goals' => [
            'name' => 'اهداف شعب',
            'description' => 'تعریف و پیگیری اهداف شعب',
            'version' => '2.0.0',
            'active' => true,
            'order' => 3,
            'icon' => '🎯',
            'routes' => ['goals', 'goals/*']
        ],
        'tools' => [
            'name' => 'ابزارها',
            'description' => 'ابزارهای کاربردی (وام ثنا، اقساط و...)',
            'version' => '2.0.0',
            'active' => true,
            'order' => 4,
            'icon' => '🔧',
            'routes' => ['tools', 'tools/*']
        ],
        'salary' => [
            'name' => 'فیش حقوقی',
            'description' => 'محاسبه و نمایش فیش حقوقی پرسنل',
            'version' => '2.0.0',
            'active' => true,
            'order' => 5,
            'icon' => '💵',
            'routes' => ['salary', 'salary/*']
        ],
        'observer' => [
            'name' => 'پنل ناظرین',
            'description' => 'سیستم پیام‌رسانی و نظارت',
            'version' => '2.0.0',
            'active' => true,
            'order' => 6,
            'icon' => '👁️',
            'routes' => ['observer', 'observer/*']
        ],
        'reports' => [
            'name' => 'گزارش‌ها',
            'description' => 'گزارش‌های تحلیلی و نمودارها',
            'version' => '2.0.0',
            'active' => true,
            'order' => 7,
            'icon' => '📈',
            'routes' => ['reports', 'reports/*']
        ],
        'assets' => [
            'name' => 'مدیریت اموال',
            'description' => 'ثبت و پیگیری اموال و دارایی‌ها',
            'version' => '2.0.0',
            'active' => true,
            'order' => 8,
            'icon' => '🏢',
            'routes' => ['assets', 'assets/*']
        ]
    ],
    
    // ماژول‌های سیستمی (غیرقابل غیرفعال‌سازی)
    'system_modules' => [
        'users',
        'roles',
        'settings',
        'themes',
        'files',
        'backups',
        'logs'
    ]
];
