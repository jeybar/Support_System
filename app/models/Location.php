<?php

require_once __DIR__ . '/../core/Database.php';

class Location {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * دریافت همه مکان‌ها
     * 
     * @return array لیست مکان‌ها
     */
    public function getAllLocations() {
        try {
            $query = "SELECT * FROM locations ORDER BY name";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getAllLocations: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت اطلاعات یک مکان با شناسه
     * 
     * @param int $id شناسه مکان
     * @return array|false اطلاعات مکان یا false در صورت عدم وجود
     */
    public function getLocationById($id) {
        try {
            $query = "SELECT * FROM locations WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getLocationById: " . $e->getMessage());
            return false;
        }
    }

    /**
     * افزودن مکان جدید
     * 
     * @param array $data اطلاعات مکان
     * @return int|false شناسه مکان جدید یا false در صورت خطا
     */
    public function addLocation($data) {
        try {
            $query = "INSERT INTO locations (name, address, city, state, zip, country, notes, parent_id, created_at) 
                      VALUES (:name, :address, :city, :state, :zip, :country, :notes, :parent_id, NOW())";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':name', $data['name'], PDO::PARAM_STR);
            $stmt->bindValue(':address', $data['address'] ?? null, PDO::PARAM_STR);
            $stmt->bindValue(':city', $data['city'] ?? null, PDO::PARAM_STR);
            $stmt->bindValue(':state', $data['state'] ?? null, PDO::PARAM_STR);
            $stmt->bindValue(':zip', $data['zip'] ?? null, PDO::PARAM_STR);
            $stmt->bindValue(':country', $data['country'] ?? null, PDO::PARAM_STR);
            $stmt->bindValue(':notes', $data['notes'] ?? null, PDO::PARAM_STR);
            $stmt->bindValue(':parent_id', $data['parent_id'] ?? null, PDO::PARAM_INT);
            
            $stmt->execute();
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log("Error in addLocation: " . $e->getMessage());
            return false;
        }
    }

    /**
     * به‌روزرسانی اطلاعات مکان
     * 
     * @param int $id شناسه مکان
     * @param array $data اطلاعات جدید
     * @return bool نتیجه عملیات
     */
    public function updateLocation($id, $data) {
        try {
            $query = "UPDATE locations SET 
                      name = :name, 
                      address = :address, 
                      city = :city, 
                      state = :state, 
                      zip = :zip, 
                      country = :country, 
                      notes = :notes, 
                      parent_id = :parent_id, 
                      updated_at = NOW() 
                      WHERE id = :id";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->bindValue(':name', $data['name'], PDO::PARAM_STR);
            $stmt->bindValue(':address', $data['address'] ?? null, PDO::PARAM_STR);
            $stmt->bindValue(':city', $data['city'] ?? null, PDO::PARAM_STR);
            $stmt->bindValue(':state', $data['state'] ?? null, PDO::PARAM_STR);
            $stmt->bindValue(':zip', $data['zip'] ?? null, PDO::PARAM_STR);
            $stmt->bindValue(':country', $data['country'] ?? null, PDO::PARAM_STR);
            $stmt->bindValue(':notes', $data['notes'] ?? null, PDO::PARAM_STR);
            $stmt->bindValue(':parent_id', $data['parent_id'] ?? null, PDO::PARAM_INT);
            
            $stmt->execute();
            return true;
        } catch (PDOException $e) {
            error_log("Error in updateLocation: " . $e->getMessage());
            return false;
        }
    }

    /**
     * حذف مکان
     * 
     * @param int $id شناسه مکان
     * @return bool نتیجه عملیات
     */
    public function deleteLocation($id) {
        try {
            $query = "DELETE FROM locations WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return true;
        } catch (PDOException $e) {
            error_log("Error in deleteLocation: " . $e->getMessage());
            return false;
        }
    }
}