<?php
namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Database;

/**
 * مدیریت ابزارها در پنل ادمین
 */
class ToolController extends Controller
{
    private $db;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
    }
    
    /**
     * نمایش لیست ابزارها
     */
    public function index()
    {
        Auth::check(['admin']);
        
        $tools = $this->db->query("SELECT * FROM tools ORDER BY sort_order ASC")->fetchAll();
        
        return $this->view('admin.tools.index', [
            'tools' => $tools,
            'pageTitle' => 'مدیریت ابزارها'
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
            $this->db->query("UPDATE tools SET active = ? WHERE id = ?", [$active, $id]);
            echo json_encode(['success' => true, 'message' => 'وضعیت ابزار تغییر کرد']);
        } else {
            echo json_encode(['success' => false, 'message' => 'خطا در تغییر وضعیت']);
        }
    }
    
    /**
     * ذخیره ابزار جدید
     */
    public function store()
    {
        Auth::check(['admin']);
        
        $name = $_POST['name'] ?? '';
        $slug = $_POST['slug'] ?? '';
        $icon = $_POST['icon'] ?? 'fa-tool';
        $url = $_POST['url'] ?? '#';
        $description = $_POST['description'] ?? '';
        $sort_order = $_POST['sort_order'] ?? 0;
        $active = $_POST['active'] ?? 1;
        
        if (empty($name) || empty($slug)) {
            echo json_encode(['success' => false, 'message' => 'نام و شناسه الزامی است']);
            return;
        }
        
        $this->db->query(
            "INSERT INTO tools (name, slug, icon, url, description, sort_order, active) VALUES (?, ?, ?, ?, ?, ?, ?)",
            [$name, $slug, $icon, $url, $description, $sort_order, $active]
        );
        
        echo json_encode(['success' => true, 'message' => 'ابزار با موفقیت اضافه شد']);
    }
    
    /**
     * حذف ابزار
     */
    public function delete($id)
    {
        Auth::check(['admin']);
        
        if ($id) {
            $this->db->query("DELETE FROM tools WHERE id = ?", [$id]);
            echo json_encode(['success' => true, 'message' => 'ابزار حذف شد']);
        } else {
            echo json_encode(['success' => false, 'message' => 'خطا در حذف ابزار']);
        }
    }
}
