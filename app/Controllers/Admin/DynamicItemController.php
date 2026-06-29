<?php
namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Database;

/**
 * مدیریت آیتم‌های داینامیک در پنل ادمین
 */
class DynamicItemController extends Controller
{
    private $db;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
    }
    
    /**
     * نمایش لیست آیتم‌های داینامیک
     */
    public function index()
    {
        Auth::check(['admin']);
        
        $items = $this->db->query("SELECT * FROM dynamic_items ORDER BY sort_order ASC")->fetchAll();
        
        return $this->view('admin.dynamic_items.index', [
            'items' => $items,
            'pageTitle' => 'مدیریت آیتم‌های داینامیک'
        ]);
    }
    
    /**
     * تغییر وضعیت فعال/غیرفعال
     */
    public function toggle()
    {
        Auth::check(['admin']);
        
        $id = $_POST['id'] ?? 0;
        $active = $_POST['active'] ?? 0;
        
        if ($id) {
            $this->db->query("UPDATE dynamic_items SET active = ? WHERE id = ?", [$active, $id]);
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
        $type = $_POST['type'] ?? 'text'; // text, number, select
        $options = $_POST['options'] ?? null; // برای نوع select
        $sort_order = $_POST['sort_order'] ?? 0;
        $active = $_POST['active'] ?? 1;
        
        if (empty($name)) {
            echo json_encode(['success' => false, 'message' => 'نام آیتم الزامی است']);
            return;
        }
        
        $this->db->query(
            "INSERT INTO dynamic_items (name, type, options, sort_order, active) VALUES (?, ?, ?, ?, ?)",
            [$name, $type, $options, $sort_order, $active]
        );
        
        echo json_encode(['success' => true, 'message' => 'آیتم با موفقیت اضافه شد']);
    }
    
    /**
     * حذف آیتم
     */
    public function delete($id)
    {
        Auth::check(['admin']);
        
        if ($id) {
            $this->db->query("DELETE FROM dynamic_items WHERE id = ?", [$id]);
            echo json_encode(['success' => true, 'message' => 'آیتم حذف شد']);
        } else {
            echo json_encode(['success' => false, 'message' => 'خطا در حذف آیتم']);
        }
    }
}
