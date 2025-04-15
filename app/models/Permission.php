<?php

require_once __DIR__ . '/../core/Database.php';

class Permission {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    // دریافت همه دسترسی‌ها
    public function getAllPermissions() {
        $query = "SELECT * FROM permissions";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ایجاد دسترسی جدید
    public function createPermission($name, $description) {
        $query = "INSERT INTO permissions (name, description) VALUES (:name, :description)";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':name', $name, PDO::PARAM_STR);
        $stmt->bindParam(':description', $description, PDO::PARAM_STR);
        return $stmt->execute();
    }

    // حذف دسترسی
    public function deletePermission($id) {
        $query = "DELETE FROM permissions WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function getPermissionById($id) {
        $query = "SELECT * FROM permissions WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC); // بازگرداندن یک ردیف به صورت آرایه
    }

    // بررسی تکراری بودن دسترسی‌ها
    public function permissionExists($name) {
        $query = "SELECT COUNT(*) FROM permissions WHERE name = :name";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':name', $name, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetchColumn() > 0;
    }
}