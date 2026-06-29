<?php
/**
 * Model - کلاس پایه مدل‌ها
 * 
 * تمام مدل‌ها باید از این کلاس ارث‌بری کنند
 */

namespace App\Core;

abstract class Model {
    
    protected $db;
    protected $table;
    protected $primaryKey = 'id';
    protected $fillable = [];
    protected $hidden = [];
    protected $timestamps = true;
    
    public function __construct() {
        $this->db = Database::getConnection();
    }
    
    /**
     * دریافت همه رکوردها
     */
    public function all(array $columns = ['*']): array {
        $cols = implode(', ', $columns);
        $sql = "SELECT {$cols} FROM {$this->table}";
        return Database::fetchAll($sql);
    }
    
    /**
     * یافتن رکورد بر اساس ID
     */
    public function find($id): ?array {
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ?";
        return Database::fetchOne($sql, [$id]);
    }
    
    /**
     * یافتن رکورد بر اساس شرط
     */
    public function where(array $conditions): array {
        $where = [];
        $params = [];
        
        foreach ($conditions as $key => $value) {
            $where[] = "{$key} = ?";
            $params[] = $value;
        }
        
        $whereClause = implode(' AND ', $where);
        $sql = "SELECT * FROM {$this->table} WHERE {$whereClause}";
        return Database::fetchAll($sql, $params);
    }
    
    /**
     * یافتن یک رکورد بر اساس شرط
     */
    public function firstWhere(array $conditions): ?array {
        $result = $this->where($conditions);
        return $result[0] ?? null;
    }
    
    /**
     * درج رکورد جدید
     */
    public function create(array $data): int {
        // فیلتر کردن فیلدهای قابل پر شدن
        $data = array_intersect_key($data, array_flip($this->fillable));
        
        // افزودن timestamps
        if ($this->timestamps) {
            $data['created_at'] = date('Y-m-d H:i:s');
            $data['updated_at'] = date('Y-m-d H:i:s');
        }
        
        return Database::insert($this->table, $data);
    }
    
    /**
     * بروزرسانی رکورد
     */
    public function update($id, array $data): bool {
        // فیلتر کردن فیلدهای قابل پر شدن
        $data = array_intersect_key($data, array_flip($this->fillable));
        
        // افزودن timestamp
        if ($this->timestamps) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }
        
        return Database::update($this->table, $data, "{$this->primaryKey} = ?", [$id]);
    }
    
    /**
     * حذف رکورد
     */
    public function delete($id): bool {
        return Database::delete($this->table, "{$this->primaryKey} = ?", [$id]);
    }
    
    /**
     * شمارش رکوردها
     */
    public function count(array $conditions = []): int {
        if (empty($conditions)) {
            $sql = "SELECT COUNT(*) as count FROM {$this->table}";
            $result = Database::fetchOne($sql);
        } else {
            $where = [];
            $params = [];
            
            foreach ($conditions as $key => $value) {
                $where[] = "{$key} = ?";
                $params[] = $value;
            }
            
            $whereClause = implode(' AND ', $where);
            $sql = "SELECT COUNT(*) as count FROM {$this->table} WHERE {$whereClause}";
            $result = Database::fetchOne($sql, $params);
        }
        
        return (int)($result['count'] ?? 0);
    }
    
    /**
     * اجرای کوئری سفارشی
     */
    public function query(string $sql, array $params = []) {
        return Database::query($sql, $params);
    }
    
    /**
     * دریافت نتیجه کوئری
     */
    public function fetchAll(string $sql, array $params = []): array {
        return Database::fetchAll($sql, $params);
    }
    
    /**
     * دریافت یک رکورد از کوئری
     */
    public function fetchOne(string $sql, array $params = []): ?array {
        return Database::fetchOne($sql, $params);
    }
    
    /**
     * مرتب‌سازی
     */
    public function orderBy(string $column, string $direction = 'ASC'): self {
        $this->orderByClause = "ORDER BY {$column} {$direction}";
        return $this;
    }
    
    /**
     * محدود کردن تعداد نتایج
     */
    public function limit(int $limit): self {
        $this->limitClause = "LIMIT {$limit}";
        return $this;
    }
    
    /**
     * بررسی وجود رکورد
     */
    public function exists(array $conditions): bool {
        return $this->count($conditions) > 0;
    }
}
