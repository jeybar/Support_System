<?php
require_once __DIR__ . '/../core/Database.php';

class Vendor {
    private $db;
    
    public function __construct() {
        // استفاده از الگوی Singleton برای دسترسی به پایگاه داده
        $this->db = Database::getInstance()->getConnection();
        
        // اگر getInstance متد استاتیک نیست یا ساختار متفاوتی دارد
        // می‌توانیم از روش زیر استفاده کنیم
        // $this->db = Database::getInstance();
    }
    
    public function getAllVendors() {
        try {
            $query = "SELECT * FROM vendors ORDER BY name";
            $stmt = $this->db->query($query);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // در صورت خطا (مثلاً عدم وجود جدول)، یک آرایه خالی برگردان
            error_log("Error in getAllVendors: " . $e->getMessage());
            return [];
        }
    }
    
    public function getVendorById($id) {
        try {
            $query = "SELECT * FROM vendors WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getVendorById: " . $e->getMessage());
            return null;
        }
    }
    
    public function createVendor($name, $contactPerson, $email, $phone, $address) {
        try {
            $query = "INSERT INTO vendors (name, contact_person, email, phone, address) VALUES (?, ?, ?, ?, ?)";
            $stmt = $this->db->prepare($query);
            return $stmt->execute([$name, $contactPerson, $email, $phone, $address]);
        } catch (PDOException $e) {
            error_log("Error in createVendor: " . $e->getMessage());
            return false;
        }
    }
    
    public function updateVendor($id, $name, $contactPerson, $email, $phone, $address) {
        try {
            $query = "UPDATE vendors SET name = ?, contact_person = ?, email = ?, phone = ?, address = ? WHERE id = ?";
            $stmt = $this->db->prepare($query);
            return $stmt->execute([$name, $contactPerson, $email, $phone, $address, $id]);
        } catch (PDOException $e) {
            error_log("Error in updateVendor: " . $e->getMessage());
            return false;
        }
    }
    
    public function deleteVendor($id) {
        try {
            $query = "DELETE FROM vendors WHERE id = ?";
            $stmt = $this->db->prepare($query);
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            error_log("Error in deleteVendor: " . $e->getMessage());
            return false;
        }
    }
    
    public function getTechnicians($vendorId) {
        try {
            $query = "SELECT * FROM vendor_technicians WHERE vendor_id = ? ORDER BY name";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$vendorId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getTechnicians: " . $e->getMessage());
            return [];
        }
    }
    
    public function createTechnician($vendorId, $name, $email, $phone, $specialization) {
        try {
            $query = "INSERT INTO vendor_technicians (vendor_id, name, email, phone, specialization) VALUES (?, ?, ?, ?, ?)";
            $stmt = $this->db->prepare($query);
            return $stmt->execute([$vendorId, $name, $email, $phone, $specialization]);
        } catch (PDOException $e) {
            error_log("Error in createTechnician: " . $e->getMessage());
            return false;
        }
    }
    
    public function updateTechnician($id, $name, $email, $phone, $specialization) {
        try {
            $query = "UPDATE vendor_technicians SET name = ?, email = ?, phone = ?, specialization = ? WHERE id = ?";
            $stmt = $this->db->prepare($query);
            return $stmt->execute([$name, $email, $phone, $specialization, $id]);
        } catch (PDOException $e) {
            error_log("Error in updateTechnician: " . $e->getMessage());
            return false;
        }
    }
    
    public function deleteTechnician($id) {
        try {
            $query = "DELETE FROM vendor_technicians WHERE id = ?";
            $stmt = $this->db->prepare($query);
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            error_log("Error in deleteTechnician: " . $e->getMessage());
            return false;
        }
    }
}