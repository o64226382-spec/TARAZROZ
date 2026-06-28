<?php
define('SECURE_ACCESS', true);
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

// ⭐ فقط POST مجازه
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'متد غیرمجاز']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

switch ($action) {
    
    // ========== ذخیره درآمد روزانه ==========
    case 'save_daily':
        $branch_id = intval($input['branch_id'] ?? 0);
        $date = $input['date'] ?? '';
        $gold_rate = floatval($input['gold_rate'] ?? 0);
        $items = $input['items'] ?? [];
        $user_id = $_SESSION['user_id'];
        
        if (empty($date) || $branch_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'اطلاعات ناقص است']);
            exit();
        }
        
        // ⭐ حذف داده‌های قبلی این روز
        $del_stmt = mysqli_prepare($conn, "DELETE FROM income_records WHERE branch_id = ? AND record_date = ?");
        mysqli_stmt_bind_param($del_stmt, "is", $branch_id, $date);
        mysqli_stmt_execute($del_stmt);
        
        // ⭐ ثبت آیتم‌های جدید
        $stmt = mysqli_prepare($conn, "INSERT INTO income_records (branch_id, item_id, record_date, gold_rate, amount_rial, amount_gram, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        $success_count = 0;
        foreach ($items as $item) {
            $item_id = intval($item['item_id'] ?? 0);
            $amount_rial = floatval($item['amount_rial'] ?? 0);
            $amount_gram = floatval($item['amount_gram'] ?? 0);
            
            if ($item_id > 0 && ($amount_rial > 0 || $amount_gram > 0)) {
                mysqli_stmt_bind_param($stmt, "iisiddi", $branch_id, $item_id, $date, $gold_rate, $amount_rial, $amount_gram, $user_id);
                if (mysqli_stmt_execute($stmt)) {
                    $success_count++;
                }
            }
        }
        
        echo json_encode(['success' => $success_count > 0, 'message' => "✅ $success_count آیتم ذخیره شد"]);
        break;
    
    // ========== ذخیره درآمد ماهانه ==========
    case 'save_monthly':
        $branch_id = intval($input['branch_id'] ?? 0);
        $month = $input['month'] ?? '';
        $items = $input['items'] ?? [];
        $user_id = $_SESSION['user_id'];
        
        if (empty($month) || $branch_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'اطلاعات ناقص است']);
            exit();
        }
        
        // تبدیل 1405/02 به 1405-02-01
        $monthDb = str_replace('/', '-', $month) . '-01';
        
        // ⭐ حذف داده‌های قبلی این ماه
        $del_stmt = mysqli_prepare($conn, "DELETE FROM income_records WHERE branch_id = ? AND record_date = ?");
        mysqli_stmt_bind_param($del_stmt, "is", $branch_id, $monthDb);
        mysqli_stmt_execute($del_stmt);
        
        // ⭐ ثبت آیتم‌های جدید
        $stmt = mysqli_prepare($conn, "INSERT INTO income_records (branch_id, item_id, record_date, amount_gram, created_by) VALUES (?, ?, ?, ?, ?)");
        
        $success_count = 0;
        foreach ($items as $item) {
            $item_id = intval($item['item_id'] ?? 0);
            $amount_gram = floatval($item['amount_gram'] ?? 0);
            
            if ($item_id > 0 && $amount_gram > 0) {
                mysqli_stmt_bind_param($stmt, "iisdi", $branch_id, $item_id, $monthDb, $amount_gram, $user_id);
                if (mysqli_stmt_execute($stmt)) {
                    $success_count++;
                }
            }
        }
        
        echo json_encode(['success' => $success_count > 0, 'message' => "✅ $success_count آیتم ذخیره شد"]);
        break;
    
    // ========== دریافت گزارش سالانه ==========
    case 'get_report':
        $branch_id = intval($input['branch_id'] ?? 0);
        $year = $input['year'] ?? date('Y');
        
        if ($branch_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'شعبه انتخاب نشده']);
            exit();
        }
        
        $data = [];
        
        // ⭐ Prepared Statement برای هر ماه
        $stmt = mysqli_prepare($conn, "SELECT COALESCE(SUM(amount_gram), 0) as total 
                  FROM income_records 
                  WHERE branch_id = ? 
                  AND DATE_FORMAT(record_date, '%Y-%m') = ?");
        
        for ($m = 1; $m <= 12; $m++) {
            $month = str_pad($m, 2, '0', STR_PAD_LEFT);
            $monthDb = $year . '-' . $month;
            
            mysqli_stmt_bind_param($stmt, "is", $branch_id, $monthDb);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $row = mysqli_fetch_assoc($result);
            
            $data[] = [
                'month' => $year . '/' . $month,
                'total' => floatval($row['total'])
            ];
        }
        
        echo json_encode(['success' => true, 'data' => $data]);
        break;
    
    default:
        echo json_encode(['success' => false, 'message' => 'عملیات نامشخص']);
}

exit();