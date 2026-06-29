<?php
namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Database;

/**
 * مدیریت آیتم‌های درآمد در پنل ادمین
 */
class IncomeItemController extends Controller
{
    private $db;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
    }
    
    /**
     * نمایش لیست آیتم‌های درآمد
     */
    public function index()
    {
        Auth::check(['admin']);
        
        $daily_items = $this->db->query("SELECT * FROM income_daily_items ORDER BY sort_order ASC")->fetchAll();
        $monthly_items = $this->db->query("SELECT * FROM income_monthly_items ORDER BY sort_order ASC")->fetchAll();
        
        return $this->view('admin.income_items.index', [
            'daily_items' => $daily_items,
            'monthly_items' => $monthly_items,
            'pageTitle' => 'مدیریت آیتم‌های درآمد'
        ]);
    }
    
    /**
     * تغییر وضعیت فعال/غیرفعال
     */
    public function toggle()
    {
        Auth::check(['admin']);
        
        $id = $_POST['id'] ?? 0;
        $type = $_POST['type'] ?? 'daily'; // daily or monthly
        $active = $_POST['active'] ?? 0;
        
        $table = $type === 'daily' ? 'income_daily_items' : 'income_monthly_items';
        
        if ($id) {
            $this->db->query("UPDATE {$table} SET active = ? WHERE id = ?", [$active, $id]);
            echo json_encode(['success' => true, 'message' => 'وضعیت آیتم تغییر کرد']);
        } else {
            echo json_encode(['success' => false, 'message' => 'خطا در تغییر وضعیت']);
        }
    }
    
    /**
     * ذخیره آیتم جدید
     */
    public function store()
    {
        Auth::check(['admin']);
        
        $name = $_POST['name'] ?? '';
        $type = $_POST['type'] ?? 'daily';
        $sort_order = $_POST['sort_order'] ?? 0;
        $active = $_POST['active'] ?? 1;
        
        if (empty($name)) {
            echo json_encode(['success' => false, 'message' => 'نام آیتم الزامی است']);
            return;
        }
        
        $table = $type === 'daily' ? 'income_daily_items' : 'income_monthly_items';
        
        $this->db->query(
            "INSERT INTO {$table} (name, sort_order, active) VALUES (?, ?, ?)",
            [$name, $sort_order, $active]
        );
        
        echo json_encode(['success' => true, 'message' => 'آیتم با موفقیت اضافه شد']);
    }
    
    /**
     * حذف آیتم
     */
    public function delete($id)
    {
        Auth::check(['admin']);
        
        $type = $_POST['type'] ?? 'daily';
        $table = $type === 'daily' ? 'income_daily_items' : 'income_monthly_items';
        
        if ($id) {
            $this->db->query("DELETE FROM {$table} WHERE id = ?", [$id]);
            echo json_encode(['success' => true, 'message' => 'آیتم حذف شد']);
        } else {
            echo json_encode(['success' => false, 'message' => 'خطا در حذف آیتم']);
        }
    }
}
