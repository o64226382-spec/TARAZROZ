-- ═══════════════════════════════════════════
-- Migration Script - جداول جدید فاز ۱
-- این اسکریپت جداول جدید را بدون تغییر داده‌های فعلی اضافه می‌کند
-- ═══════════════════════════════════════════

-- جدول نقش‌ها (roles)
CREATE TABLE IF NOT EXISTS `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `title` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول دسترسی‌ها (permissions) - اگر وجود ندارد
CREATE TABLE IF NOT EXISTS `permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `permission` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_permission` (`permission`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول ارتباط نقش و دسترسی (role_permissions)
CREATE TABLE IF NOT EXISTS `role_permissions` (
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  PRIMARY KEY (`role_id`, `permission_id`),
  KEY `permission_id` (`permission_id`),
  CONSTRAINT `role_permissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول لاگ فعالیت‌ها (activity_logs)
CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `action` (`action`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول فایل‌های آپلود شده (uploaded_files)
CREATE TABLE IF NOT EXISTS `uploaded_files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `filename` varchar(255) NOT NULL,
  `original_name` varchar(255) DEFAULT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_type` varchar(100) DEFAULT NULL,
  `file_size` bigint(20) DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `is_public` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول فونت‌ها (fonts)
CREATE TABLE IF NOT EXISTS `fonts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `font_family` varchar(100) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `font_weight` varchar(20) DEFAULT 'normal',
  `font_style` varchar(20) DEFAULT 'normal',
  `is_active` tinyint(1) DEFAULT 1,
  `is_default` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_font_family` (`font_family`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═══════════════════════════════════════════
-- داده‌های اولیه
-- ═══════════════════════════════════════════

-- نقش‌های پیش‌فرض
INSERT INTO `roles` (`name`, `title`, `description`, `is_active`) VALUES
('admin', 'مدیر کل', 'دسترسی کامل به تمام بخش‌ها', 1),
('branch', 'مدیر شعبه', 'مدیریت شعبه خاص', 1),
('observer', 'ناظر', 'فقط مشاهده گزارش‌ها', 1),
('receipt', 'صدور فیش', 'امکان صدور فیش', 1)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`);

-- دسترسی‌های پیش‌فرض
INSERT INTO `permissions` (`name`, `permission`, `description`) VALUES
('دسترسی کامل', '*', 'دسترسی به همه چیز', NOW()),
('مشاهده داشبورد', 'view_dashboard', 'امکان مشاهده داشبورد', NOW()),
('مدیریت کاربران', 'manage_users', 'امکان افزودن، ویرایش و حذف کاربران', NOW()),
('مدیریت ابزارها', 'manage_tools', 'امکان مدیریت ابزارها', NOW()),
('مدیریت درآمد', 'manage_income', 'امکان مدیریت درآمد روزانه و ماهانه', NOW()),
('مدیریت اهداف', 'manage_goals', 'امکان مدیریت اهداف', NOW()),
('مدیریت تراز', 'manage_daily_report', 'امکان مدیریت تراز روزانه', NOW()),
('مدیریت تم', 'manage_themes', 'امکان مدیریت تم‌ها', NOW()),
('مدیریت فایل', 'manage_files', 'امکان آپلود و مدیریت فایل‌ها', NOW()),
('مدیریت فونت', 'manage_fonts', 'امکان آپلود و مدیریت فونت‌ها', NOW()),
('مدیریت بکاپ', 'manage_backups', 'امکان ایجاد و بازگردانی بکاپ', NOW()),
('مشاهده لاگ', 'view_logs', 'امکان مشاهده لاگ فعالیت‌ها', NOW()),
('صدور فیش', 'create_receipt', 'امکان صدور فیش', NOW())
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- اختصاص دسترسی‌ها به نقش ادمین
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r, `permissions` p 
WHERE r.name = 'admin' AND p.permission IN ('*', 'view_dashboard', 'manage_users', 'manage_tools', 'manage_income', 'manage_goals', 'manage_daily_report', 'manage_themes', 'manage_files', 'manage_fonts', 'manage_backups', 'view_logs')
ON DUPLICATE KEY UPDATE `role_id` = VALUES(`role_id`);

-- ستون role_id به جدول users (اگر وجود ندارد)
-- توجه: این بخش باید با احتیاط اجرا شود
-- ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `role_id` int(11) DEFAULT NULL AFTER `role`;

