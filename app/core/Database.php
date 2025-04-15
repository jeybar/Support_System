<?php

require_once __DIR__ . '/../../config/config.php'; // بارگذاری تنظیمات

class Database {
    private static $instance = null; // نگهداری نمونه Singleton
    private $conn;

    // متد سازنده خصوصی برای جلوگیری از ایجاد نمونه جدید
    private function __construct() {
        try {
            $this->conn = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
                DB_USER,
                DB_PASS
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // نمایش خطای دقیق برای اشکال‌زدایی
            echo "خطا در اتصال به پایگاه داده: " . $e->getMessage();
            exit;
        }
    }

    // دریافت نمونه Singleton
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    // دریافت اتصال به پایگاه داده
    public function getConnection() {
        return $this->conn;
    }

    // اجرای کوئری ساده
    public function query($sql) {
        try {
            return $this->conn->query($sql);
        } catch (PDOException $e) {
            error_log("خطا در اجرای کوئری: " . $e->getMessage());
            return false;
        }
    }

    // اجرای کوئری آماده (Prepared Statement)
    public function prepare($sql) {
        try {
            return $this->conn->prepare($sql);
        } catch (PDOException $e) {
            error_log("خطا در آماده‌سازی کوئری: " . $e->getMessage());
            return false;
        }
    }

    // شروع تراکنش
    public function beginTransaction() {
        try {
            return $this->conn->beginTransaction();
        } catch (PDOException $e) {
            error_log("خطا در شروع تراکنش: " . $e->getMessage());
            return false;
        }
    }

    // تایید تراکنش
    public function commit() {
        try {
            return $this->conn->commit();
        } catch (PDOException $e) {
            error_log("خطا در تایید تراکنش: " . $e->getMessage());
            return false;
        }
    }

    // بازگردانی تراکنش
    public function rollBack() {
        try {
            return $this->conn->rollBack();
        } catch (PDOException $e) {
            error_log("خطا در بازگردانی تراکنش: " . $e->getMessage());
            return false;
        }
    }
}