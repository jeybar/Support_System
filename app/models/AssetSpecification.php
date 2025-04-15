<?php

class AssetSpecification {
    private $db;

    public function __construct() {
        try {
            // دریافت اتصال به پایگاه داده از طریق کلاس Database
            $this->db = Database::getInstance()->getConnection();
        } catch (Exception $e) {
            error_log("Error connecting to database: " . $e->getMessage());
            throw new Exception("خطا در اتصال به پایگاه داده. لطفاً با مدیر سیستم تماس بگیرید.");
        }
    }

    /**
     * ایجاد مشخصات جدید برای تجهیز
     * 
     * @param array $data اطلاعات مشخصات
     * @return int|bool شناسه مشخصات جدید یا false در صورت خطا
     */
    public function create($data) {
        try {
            $query = "
                INSERT INTO asset_specifications (
                    asset_id, spec_name, spec_value, created_at, updated_at
                ) VALUES (
                    :asset_id, :spec_name, :spec_value, NOW(), NOW()
                )
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':asset_id', $data['asset_id'], PDO::PARAM_INT);
            $stmt->bindParam(':spec_name', $data['spec_name'], PDO::PARAM_STR);
            $stmt->bindParam(':spec_value', $data['spec_value'], PDO::PARAM_STR);
            
            if ($stmt->execute()) {
                return $this->db->lastInsertId();
            }
            
            return false;
        } catch (PDOException $e) {
            error_log("Error in AssetSpecification::create: " . $e->getMessage());
            return false;
        }
    }

    /**
     * به‌روزرسانی مشخصات تجهیز
     * 
     * @param int $id شناسه مشخصات
     * @param array $data اطلاعات جدید
     * @return bool نتیجه عملیات
     */
    public function update($id, $data) {
        try {
            $query = "
                UPDATE asset_specifications 
                SET spec_name = :spec_name, 
                    spec_value = :spec_value, 
                    updated_at = NOW() 
                WHERE id = :id
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':spec_name', $data['spec_name'], PDO::PARAM_STR);
            $stmt->bindParam(':spec_value', $data['spec_value'], PDO::PARAM_STR);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error in AssetSpecification::update: " . $e->getMessage());
            return false;
        }
    }

    /**
     * حذف مشخصات تجهیز
     * 
     * @param int $id شناسه مشخصات
     * @return bool نتیجه عملیات
     */
    public function delete($id) {
        try {
            $query = "DELETE FROM asset_specifications WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error in AssetSpecification::delete: " . $e->getMessage());
            return false;
        }
    }

    /**
     * دریافت مشخصات یک تجهیز
     * 
     * @param int $assetId شناسه تجهیز
     * @return array لیست مشخصات
     */
    public function getByAssetId($assetId) {
        try {
            $query = "
                SELECT * FROM asset_specifications 
                WHERE asset_id = :asset_id 
                ORDER BY spec_name
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':asset_id', $assetId, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in AssetSpecification::getByAssetId: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت یک مشخصه با شناسه
     * 
     * @param int $id شناسه مشخصه
     * @return array|bool اطلاعات مشخصه یا false در صورت خطا
     */
    public function getById($id) {
        try {
            $query = "SELECT * FROM asset_specifications WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in AssetSpecification::getById: " . $e->getMessage());
            return false;
        }
    }

    /**
     * بررسی وجود مشخصه با نام مشخص برای یک تجهیز
     * 
     * @param int $assetId شناسه تجهیز
     * @param string $specName نام مشخصه
     * @return bool نتیجه بررسی
     */
    public function specExists($assetId, $specName) {
        try {
            $query = "
                SELECT COUNT(*) FROM asset_specifications 
                WHERE asset_id = :asset_id AND spec_name = :spec_name
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':asset_id', $assetId, PDO::PARAM_INT);
            $stmt->bindParam(':spec_name', $specName, PDO::PARAM_STR);
            $stmt->execute();
            
            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            error_log("Error in AssetSpecification::specExists: " . $e->getMessage());
            return false;
        }
    }

    /**
     * دریافت مقدار یک مشخصه خاص برای تجهیز
     * 
     * @param int $assetId شناسه تجهیز
     * @param string $specName نام مشخصه
     * @return string|null مقدار مشخصه یا null در صورت عدم وجود
     */
    public function getSpecValue($assetId, $specName) {
        try {
            $query = "
                SELECT spec_value FROM asset_specifications 
                WHERE asset_id = :asset_id AND spec_name = :spec_name
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':asset_id', $assetId, PDO::PARAM_INT);
            $stmt->bindParam(':spec_name', $specName, PDO::PARAM_STR);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['spec_value'] : null;
        } catch (PDOException $e) {
            error_log("Error in AssetSpecification::getSpecValue: " . $e->getMessage());
            return null;
        }
    }

    /**
     * به‌روزرسانی یا ایجاد مشخصه برای تجهیز
     * 
     * @param int $assetId شناسه تجهیز
     * @param string $specName نام مشخصه
     * @param string $specValue مقدار مشخصه
     * @return bool نتیجه عملیات
     */
    public function updateOrCreate($assetId, $specName, $specValue) {
        try {
            // بررسی وجود مشخصه
            $query = "
                SELECT id FROM asset_specifications 
                WHERE asset_id = :asset_id AND spec_name = :spec_name
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':asset_id', $assetId, PDO::PARAM_INT);
            $stmt->bindParam(':spec_name', $specName, PDO::PARAM_STR);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                // به‌روزرسانی مشخصه موجود
                $updateQuery = "
                    UPDATE asset_specifications 
                    SET spec_value = :spec_value, updated_at = NOW() 
                    WHERE id = :id
                ";
                
                $updateStmt = $this->db->prepare($updateQuery);
                $updateStmt->bindParam(':id', $result['id'], PDO::PARAM_INT);
                $updateStmt->bindParam(':spec_value', $specValue, PDO::PARAM_STR);
                
                return $updateStmt->execute();
            } else {
                // ایجاد مشخصه جدید
                $insertQuery = "
                    INSERT INTO asset_specifications (
                        asset_id, spec_name, spec_value, created_at, updated_at
                    ) VALUES (
                        :asset_id, :spec_name, :spec_value, NOW(), NOW()
                    )
                ";
                
                $insertStmt = $this->db->prepare($insertQuery);
                $insertStmt->bindParam(':asset_id', $assetId, PDO::PARAM_INT);
                $insertStmt->bindParam(':spec_name', $specName, PDO::PARAM_STR);
                $insertStmt->bindParam(':spec_value', $specValue, PDO::PARAM_STR);
                
                return $insertStmt->execute();
            }
        } catch (PDOException $e) {
            error_log("Error in AssetSpecification::updateOrCreate: " . $e->getMessage());
            return false;
        }
    }

    /**
     * ذخیره مشخصات متعدد برای یک تجهیز
     * 
     * @param int $assetId شناسه تجهیز
     * @param array $specifications آرایه‌ای از مشخصات (کلید: نام مشخصه، مقدار: مقدار مشخصه)
     * @return bool نتیجه عملیات
     */
    public function saveMultiple($assetId, $specifications) {
        try {
            $this->db->beginTransaction();
            
            foreach ($specifications as $specName => $specValue) {
                $this->updateOrCreate($assetId, $specName, $specValue);
            }
            
            $this->db->commit();
            return true;
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Error in AssetSpecification::saveMultiple: " . $e->getMessage());
            return false;
        }
    }
}