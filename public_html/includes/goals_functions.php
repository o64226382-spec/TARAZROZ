<?php
if (!defined('SECURE_ACCESS')) define('SECURE_ACCESS', true);

// ========== ایجاد جداول در صورت نیاز (با کدگذاری صحیح) ==========
function goals_create_tables($conn) {
    // فقط بررسی وجود جدول، بدون درج خودکار
    $check = mysqli_query($conn, "SHOW TABLES LIKE 'goal_types'");
    if (mysqli_num_rows($check) == 0) {
        // اگر جدول وجود نداشت، خطا بده
        die("لطفاً ابتدا جداول اهداف را در phpMyAdmin ایجاد کنید.");
    }
}

// ========== دریافت اهداف یک شعبه ==========
function get_branch_goals($conn, $branch_id, $year, $month) {
    $goals = [];
    $goal_types = [];
    
    // دریافت انواع اهداف از دیتابیس
    $q = mysqli_query($conn, "SELECT * FROM goal_types WHERE is_active = 1 ORDER BY sort_order");
    while ($row = mysqli_fetch_assoc($q)) {
        $goal_types[$row['id']] = $row;
    }
    
    // دریافت مقادیر هدف
    $targets = [];
    $q2 = mysqli_query($conn, "SELECT goal_type_id, target_value FROM branch_goals WHERE branch_id = $branch_id AND year = $year AND month = $month");
    while ($row = mysqli_fetch_assoc($q2)) {
        $targets[$row['goal_type_id']] = $row['target_value'];
    }
    
    $days_passed = (int)jdate('d');
    
    foreach ($goal_types as $id => $goal) {
        $target = isset($targets[$id]) ? (float)$targets[$id] : 0;
        
        // محاسبه پیشرفت
        $achieved = 0;
        if ($target > 0) {
            $start_date = sprintf("%04d-%02d-01", $year, $month);
            $q3 = mysqli_query($conn, "SELECT COALESCE(SUM(achieved_value), 0) as total 
                                       FROM goal_daily_progress 
                                       WHERE branch_id = $branch_id 
                                       AND goal_type_id = $id 
                                       AND YEAR(progress_date) = YEAR('$start_date')
                                       AND MONTH(progress_date) = MONTH('$start_date')");
            $r3 = mysqli_fetch_assoc($q3);
            $achieved = round($r3['total'] ?? 0, 3);
        }
        
        $goals[] = [
            'id' => $id,
            'name' => $goal['name'],
            'unit' => $goal['unit'],
            'icon' => !empty($goal['icon']) ? $goal['icon'] : '🎯',
            'target_value' => $target,
            'achieved' => $achieved,
            'remaining' => max(0, $target - $achieved),
            'percentage' => $target > 0 ? round(($achieved / $target) * 100, 1) : 0,
            'daily_avg' => $days_passed > 0 ? round($achieved / $days_passed, 3) : 0
        ];
    }
    
    return $goals;
}
?>