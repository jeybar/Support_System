<?php

require_once __DIR__ . '/../core/Database.php';

class Supplier {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * دریافت همه تامین‌کنندگان
     * 
     * @return array لیست تامین‌کنندگان
     */
    public function getAllSuppliers() {
        try {
            $query = "SELECT * FROM suppliers ORDER BY name";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getAllSuppliers: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت اطلاعات یک تامین‌کننده با شناسه
     * 
     * @param int $id شناسه تامین‌کننده
     * @return array|false اطلاعات تامین‌کننده یا false در صورت عدم وجود
     */
    public function getSupplierById($id) {
        try {
            $query = "SELECT * FROM suppliers WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getSupplierById: " . $e->getMessage());
            return false;
        }
    }

    /**
     * افزودن تامین‌کننده جدید
     * 
     * @param array $data اطلاعات تامین‌کننده
     * @return int|false شناسه تامین‌کننده جدید یا false در صورت خطا
     */
    public function addSupplier($data) {
        try {
            $query = "INSERT INTO suppliers (name, contact_name, email, phone, address, website, notes, created_at) 
                      VALUES (:name, :contact_name, :email, :phone, :address, :website, :notes, NOW())";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':name', $data['name'], PDO::PARAM_STR);
            $stmt->bindValue(':contact_name', $data['contact_name'] ?? null, PDO::PARAM_STR);
            $stmt->bindValue(':email', $data['email'] ?? null, PDO::PARAM_STR);
            $stmt->bindValue(':phone', $data['phone'] ?? null, PDO::PARAM_STR);
            $stmt->bindValue(':address', $data['address'] ?? null, PDO::PARAM_STR);
            $stmt->bindValue(':website', $data['website'] ?? null, PDO::PARAM_STR);
            $stmt->bindValue(':notes', $data['notes'] ?? null, PDO::PARAM_STR);
            
            $stmt->execute();
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log("Error in addSupplier: " . $e->getMessage());
            return false;
        }
    }

    /**
     * به‌روزرسانی اطلاعات تامین‌کننده
     * 
     * @param int $id شناسه تامین‌کننده
     * @param array $data اطلاعات جدید
     * @return bool نتیجه عملیات
     */
    public function updateSupplier($id, $data) {
        try {
            $query = "UPDATE suppliers SET 
                      name = :name, 
                      contact_name = :contact_name, 
                      email = :email, 
                      phone = :phone, 
                      address = :address, 
                      website = :website, 
                      notes = :notes, 
                      updated_at = NOW() 
                      WHERE id = :id";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->bindValue(':name', $data['name'], PDO::PARAM_STR);
            $stmt->bindValue(':contact_name', $data['contact_name'] ?? null, PDO::PARAM_STR);
            $stmt->bindValue(':email', $data['email'] ?? null, PDO::PARAM_STR);
            $stmt->bindValue(':phone', $data['phone'] ?? null, PDO::PARAM_STR);
            $stmt->bindValue(':address', $data['address'] ?? null, PDO::PARAM_STR);
            $stmt->bindValue(':website', $data['website'] ?? null, PDO::PARAM_STR);
            $stmt->bindValue(':notes', $data['notes'] ?? null, PDO::PARAM_STR);
            
            $stmt->execute();
            return true;
        } catch (PDOException $e) {
            error_log("Error in updateSupplier: " . $e->getMessage());
            return false;
        }
    }

    /**
     * حذف تامین‌کننده
     * 
     * @param int $id شناسه تامین‌کننده
     * @return bool نتیجه عملیات
     */
    public function deleteSupplier($id) {
        try {
            $query = "DELETE FROM suppliers WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return true;
        } catch (PDOException $e) {
            error_log("Error in deleteSupplier: " . $e->getMessage());
            return false;
        }
    }
}