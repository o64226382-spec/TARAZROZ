<?php
namespace App\Core;

/**
 * کلاس مدیریت اتصال به پایگاه داده
 */
class Database {
    
    private static $instance = null;
    private $connection;
    
    /**
     * جلوگیری از ایجاد نمونه مستقیم
     */
    private function __construct() {}
    
    /**
     * دریافت نمونه Singleton
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * اتصال به دیتابیس با mysqli
     */
    public static function connect() {
        $dbConfig = require BASE_PATH . '/config/database.php';
        $config = $dbConfig['connections']['mysql'];
        
        self::$instance = self::getInstance();
        self::$instance->connection = mysqli_connect(
            $config['host'],
            $config['username'],
            $config['password'],
            $config['database']
        );
        
        if (mysqli_connect_errno()) {
            die('خطا در اتصال به پایگاه داده: ' . mysqli_connect_error());
        }
        
        mysqli_set_charset(self::$instance->connection, $config['charset']);
        
        return self::$instance->connection;
    }
    
    /**
     * دریافت اتصال فعال
     */
    public static function getConnection() {
        if (self::$instance === null || self::$instance->connection === null) {
            self::connect();
        }
        return self::$instance->connection;
    }
    
    /**
     * اجرای کوئری امن با Prepared Statement
     */
    public static function query($sql, $params = []) {
        $conn = self::getConnection();
        
        if (empty($params)) {
            return mysqli_query($conn, $sql);
        }
        
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            throw new \Exception("خطا در آماده‌سازی کوئری: " . mysqli_error($conn));
        }
        
        if (!empty($params)) {
            $types = '';
            foreach ($params as $param) {
                if (is_int($param)) $types .= 'i';
                elseif (is_float($param)) $types .= 'd';
                else $types .= 's';
            }
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        
        mysqli_stmt_execute($stmt);
        return $stmt;
    }
    
    /**
     * دریافت یک رکورد
     */
    public static function fetchOne($sql, $params = []) {
        $result = self::query($sql, $params);
        
        if ($result instanceof \mysqli_stmt) {
            $res = mysqli_stmt_get_result($result);
            return mysqli_fetch_assoc($res);
        }
        
        return mysqli_fetch_assoc($result);
    }
    
    /**
     * دریافت همه رکوردها
     */
    public static function fetchAll($sql, $params = []) {
        $result = self::query($sql, $params);
        $rows = [];
        
        if ($result instanceof \mysqli_stmt) {
            $res = mysqli_stmt_get_result($result);
            while ($row = mysqli_fetch_assoc($res)) {
                $rows[] = $row;
            }
        } else {
            while ($row = mysqli_fetch_assoc($result)) {
                $rows[] = $row;
            }
        }
        
        return $rows;
    }
    
    /**
     * INSERT و دریافت ID
     */
    public static function insert($table, $data) {
        $conn = self::getConnection();
        $keys = array_keys($data);
        $fields = implode(', ', $keys);
        $placeholders = implode(', ', array_fill(0, count($keys), '?'));
        
        $sql = "INSERT INTO {$table} ({$fields}) VALUES ({$placeholders})";
        $stmt = mysqli_prepare($conn, $sql);
        
        $types = '';
        $values = [];
        foreach ($data as $value) {
            if (is_int($value)) $types .= 'i';
            elseif (is_float($value)) $types .= 'd';
            else $types .= 's';
            $values[] = $value;
        }
        
        mysqli_stmt_bind_param($stmt, $types, ...$values);
        mysqli_stmt_execute($stmt);
        
        return mysqli_insert_id($conn);
    }
    
    /**
     * UPDATE
     */
    public static function update($table, $data, $where, $whereParams = []) {
        $conn = self::getConnection();
        
        $setParts = [];
        foreach ($data as $key => $value) {
            $setParts[] = "{$key} = ?";
        }
        $setClause = implode(', ', $setParts);
        
        $sql = "UPDATE {$table} SET {$setClause} WHERE {$where}";
        $stmt = mysqli_prepare($conn, $sql);
        
        $types = '';
        $values = array_values($data);
        foreach ($values as $value) {
            if (is_int($value)) $types .= 'i';
            elseif (is_float($value)) $types .= 'd';
            else $types .= 's';
        }
        
        mysqli_stmt_bind_param($stmt, $types, ...$values);
        return mysqli_stmt_execute($stmt);
    }
    
    /**
     * DELETE
     */
    public static function delete($table, $where, $params = []) {
        $conn = self::getConnection();
        $sql = "DELETE FROM {$table} WHERE {$where}";
        
        if (!empty($params)) {
            $stmt = mysqli_prepare($conn, $sql);
            $types = '';
            foreach ($params as $param) {
                if (is_int($param)) $types .= 'i';
                elseif (is_float($param)) $types .= 'd';
                else $types .= 's';
            }
            mysqli_stmt_bind_param($stmt, $types, ...$params);
            return mysqli_stmt_execute($stmt);
        }
        
        return mysqli_query($conn, $sql);
    }
    
    /**
     * بستن اتصال
     */
    public function __destruct() {
        if ($this->connection) {
            mysqli_close($this->connection);
        }
    }
}
