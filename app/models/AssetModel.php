<?php
require_once __DIR__ . '/../core/Database.php';

class AssetModel {
    private $db;
    
    public function __construct() {
        // بررسی کنید که آیا Database از الگوی Singleton استفاده می‌کند
        if (method_exists('Database', 'getInstance')) {
            $this->db = Database::getInstance();
        } else {
            // اگر getInstance وجود ندارد، ممکن است نیاز به تغییر سطح دسترسی سازنده باشد
            $this->db = new Database();
        }
    }
    
    /**
     * دریافت همه مدل‌ها
     * 
     * @return array لیست مدل‌ها
     */
    public function getAllModels() {
        try {
            $query = "SELECT id, name, category_id FROM asset_models ORDER BY name";
            
            // دریافت اتصال اصلی
            $connection = $this->db->getConnection();
            
            if ($connection instanceof PDO) {
                $stmt = $connection->prepare($query);
                $stmt->execute();
                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                error_log("getAllModels using PDO, result count: " . count($result));
                return $result;
            } 
            else {
                error_log("Unknown database connection type: " . get_class($connection));
                return [];
            }
        } catch (Exception $e) {
            error_log("Error in getAllModels: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            return [];
        }
    }
    
   /**
     * دریافت مدل‌ها بر اساس دسته‌بندی
     * 
     * @param int $categoryId شناسه دسته‌بندی
     * @return array لیست مدل‌ها
     */
    public function getModelsByCategory($categoryId) {
        try {
            $query = "SELECT id, name FROM asset_models WHERE category_id = ? ORDER BY name";
            
            // دریافت اتصال اصلی
            $connection = $this->db->getConnection();
            
            if ($connection instanceof PDO) {
                $stmt = $connection->prepare($query);
                $stmt->execute([$categoryId]);
                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // ثبت لاگ برای اشکال‌زدایی
                error_log("Query result count: " . count($result));
                
                return $result;
            } 
            else {
                error_log("Unknown database connection type: " . get_class($connection));
                return [];
            }
        } catch (Exception $e) {
            error_log("Error in getModelsByCategory: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * دریافت یک مدل با شناسه
     * 
     * @param int $id شناسه مدل
     * @return array|null اطلاعات مدل یا null در صورت عدم وجود
     */
    public function getModelById($id) {
        try {
            $query = "SELECT am.*, ac.name as category_name,
                      (SELECT COUNT(*) FROM assets WHERE model_id = am.id) as asset_count
                      FROM asset_models am
                      JOIN asset_categories ac ON am.category_id = ac.id
                      WHERE am.id = ?";
            $result = $this->db->query($query, [$id]);
            
            if ($result && count($result) > 0) {
                $model = $result[0];
                
                // دریافت مشخصات پیش‌فرض
                $model['default_specs'] = $this->getDefaultSpecsForModel($id);
                
                return $model;
            }
            
            return null;
        } catch (Exception $e) {
            error_log("Error in getModelById: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * بررسی وجود مدل با نام مشخص در یک دسته‌بندی
     * 
     * @param string $name نام مدل
     * @param int $categoryId شناسه دسته‌بندی
     * @param int|null $excludeId شناسه مدلی که باید از بررسی مستثنی شود (برای به‌روزرسانی)
     * @return bool آیا مدل با این نام در دسته‌بندی وجود دارد
     */
    public function modelNameExistsInCategory($name, $categoryId, $excludeId = null) {
        try {
            $params = [$name, $categoryId];
            $query = "SELECT COUNT(*) as count FROM asset_models WHERE name = ? AND category_id = ?";
            
            if ($excludeId !== null) {
                $query .= " AND id != ?";
                $params[] = $excludeId;
            }
            
            $result = $this->db->query($query, $params);
            return ($result[0]['count'] ?? 0) > 0;
        } catch (Exception $e) {
            error_log("Error in modelNameExistsInCategory: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * ایجاد مدل جدید
     * 
     * @param int $categoryId شناسه دسته‌بندی
     * @param string $name نام مدل
     * @param string $manufacturer سازنده
     * @param string $description توضیحات
     * @param array $defaultSpecs مشخصات پیش‌فرض (اختیاری)
     * @return int|bool شناسه مدل جدید یا false در صورت خطا
     */
    public function createModel($categoryId, $name, $manufacturer, $description = '', $defaultSpecs = []) {
        try {
            // بررسی تکراری نبودن نام مدل در دسته‌بندی
            if ($this->modelNameExistsInCategory($name, $categoryId)) {
                return false;
            }
            
            $this->db->beginTransaction();
            
            $query = "INSERT INTO asset_models (category_id, name, manufacturer, description, created_at, updated_at) 
                      VALUES (?, ?, ?, ?, NOW(), NOW())";
            $result = $this->db->query($query, [$categoryId, $name, $manufacturer, $description]);
            
            if ($result) {
                $modelId = $this->db->lastInsertId();
                
                // افزودن مشخصات پیش‌فرض
                if (!empty($defaultSpecs) && is_array($defaultSpecs)) {
                    foreach ($defaultSpecs as $spec) {
                        if (isset($spec['spec_name']) && isset($spec['spec_value'])) {
                            $this->saveDefaultSpecForModel($modelId, $spec['spec_name'], $spec['spec_value']);
                        }
                    }
                }
                
                $this->db->commit();
                return $modelId;
            }
            
            $this->db->rollback();
            return false;
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Error in createModel: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * به‌روزرسانی مدل
     * 
     * @param int $id شناسه مدل
     * @param int $categoryId شناسه دسته‌بندی
     * @param string $name نام مدل
     * @param string $manufacturer سازنده
     * @param string $description توضیحات
     * @return bool نتیجه عملیات
     */
    public function updateModel($id, $categoryId, $name, $manufacturer, $description = '') {
        try {
            // بررسی تکراری نبودن نام مدل در دسته‌بندی (به جز خود این مدل)
            if ($this->modelNameExistsInCategory($name, $categoryId, $id)) {
                return false;
            }
            
            $query = "UPDATE asset_models 
                      SET category_id = ?, name = ?, manufacturer = ?, description = ?, updated_at = NOW() 
                      WHERE id = ?";
            return $this->db->query($query, [$categoryId, $name, $manufacturer, $description, $id]);
        } catch (Exception $e) {
            error_log("Error in updateModel: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * حذف مدل
     * 
     * @param int $id شناسه مدل
     * @return bool نتیجه عملیات
     */
    public function deleteModel($id) {
        try {
            // بررسی وجود تجهیز‌های وابسته به این مدل
            $checkQuery = "SELECT COUNT(*) as count FROM assets WHERE model_id = ?";
            $checkResult = $this->db->query($checkQuery, [$id]);
            
            if (($checkResult[0]['count'] ?? 0) > 0) {
                // اگر تجهیز‌های وابسته وجود دارند، حذف انجام نمی‌شود
                return false;
            }
            
            $this->db->beginTransaction();
            
            // حذف مشخصات پیش‌فرض مدل
            $this->db->query("DELETE FROM model_default_specs WHERE model_id = ?", [$id]);
            
            // حذف مدل
            $result = $this->db->query("DELETE FROM asset_models WHERE id = ?", [$id]);
            
            if ($result) {
                $this->db->commit();
                return true;
            } else {
                $this->db->rollback();
                return false;
            }
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Error in deleteModel: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * دریافت مشخصات پیش‌فرض برای یک مدل
     * 
     * @param int $modelId شناسه مدل
     * @return array لیست مشخصات پیش‌فرض
     */
    public function getDefaultSpecsForModel($modelId) {
        try {
            $query = "SELECT id, spec_name, spec_value
                      FROM model_default_specs
                      WHERE model_id = ?
                      ORDER BY spec_name";
            return $this->db->query($query, [$modelId]);
        } catch (Exception $e) {
            error_log("Error in getDefaultSpecsForModel: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * ذخیره مشخصات پیش‌فرض برای یک مدل
     * 
     * @param int $modelId شناسه مدل
     * @param string $specName نام مشخصه
     * @param string $specValue مقدار مشخصه
     * @return bool نتیجه عملیات
     */
    public function saveDefaultSpecForModel($modelId, $specName, $specValue) {
        try {
            // بررسی وجود مشخصه
            $query1 = "SELECT id FROM model_default_specs WHERE model_id = ? AND spec_name = ?";
            $result = $this->db->query($query1, [$modelId, $specName]);
            
            if ($result && count($result) > 0) {
                // به‌روزرسانی مشخصه موجود
                $query2 = "UPDATE model_default_specs SET spec_value = ? WHERE model_id = ? AND spec_name = ?";
                return $this->db->query($query2, [$specValue, $modelId, $specName]);
            } else {
                // ایجاد مشخصه جدید
                $query3 = "INSERT INTO model_default_specs (model_id, spec_name, spec_value, created_at) 
                          VALUES (?, ?, ?, NOW())";
                return $this->db->query($query3, [$modelId, $specName, $specValue]);
            }
        } catch (Exception $e) {
            error_log("Error in saveDefaultSpecForModel: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * ذخیره چندین مشخصه پیش‌فرض برای یک مدل
     * 
     * @param int $modelId شناسه مدل
     * @param array $specs آرایه‌ای از مشخصات (هر عنصر باید spec_name و spec_value داشته باشد)
     * @return bool نتیجه عملیات
     */
    public function saveMultipleDefaultSpecs($modelId, $specs) {
        try {
            if (empty($specs) || !is_array($specs)) {
                return false;
            }
            
            $this->db->beginTransaction();
            $success = true;
            
            foreach ($specs as $spec) {
                if (!isset($spec['spec_name']) || !isset($spec['spec_value'])) {
                    continue;
                }
                
                $result = $this->saveDefaultSpecForModel($modelId, $spec['spec_name'], $spec['spec_value']);
                
                if (!$result) {
                    $success = false;
                    break;
                }
            }
            
            if ($success) {
                $this->db->commit();
                return true;
            } else {
                $this->db->rollback();
                return false;
            }
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Error in saveMultipleDefaultSpecs: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * حذف مشخصه پیش‌فرض برای یک مدل
     * 
     * @param int $specId شناسه مشخصه
     * @return bool نتیجه عملیات
     */
    public function deleteDefaultSpec($specId) {
        try {
            $query = "DELETE FROM model_default_specs WHERE id = ?";
            return $this->db->query($query, [$specId]);
        } catch (Exception $e) {
            error_log("Error in deleteDefaultSpec: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * حذف تمام مشخصات پیش‌فرض یک مدل
     * 
     * @param int $modelId شناسه مدل
     * @return bool نتیجه عملیات
     */
    public function deleteAllDefaultSpecs($modelId) {
        try {
            $query = "DELETE FROM model_default_specs WHERE model_id = ?";
            return $this->db->query($query, [$modelId]);
        } catch (Exception $e) {
            error_log("Error in deleteAllDefaultSpecs: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * اعمال مشخصات پیش‌فرض مدل به یک تجهیز
     * 
     * @param int $assetId شناسه تجهیز
     * @param int $modelId شناسه مدل
     * @return bool نتیجه عملیات
     */
    public function applyDefaultSpecsToAsset($assetId, $modelId) {
        try {
            $specs = $this->getDefaultSpecsForModel($modelId);
            
            if (empty($specs)) {
                return true; // اگر مشخصات پیش‌فرض وجود نداشت، موفقیت‌آمیز تلقی می‌شود
            }
            
            $this->db->beginTransaction();
            $success = true;
            
            foreach ($specs as $spec) {
                $query = "INSERT INTO asset_specifications (asset_id, spec_name, spec_value, created_at) 
                          VALUES (?, ?, ?, NOW())";
                $result = $this->db->query($query, [$assetId, $spec['spec_name'], $spec['spec_value']]);
                
                if (!$result) {
                    $success = false;
                    break;
                }
            }
            
            if ($success) {
                $this->db->commit();
                return true;
            } else {
                $this->db->rollback();
                return false;
            }
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Error in applyDefaultSpecsToAsset: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * جستجوی مدل‌ها با صفحه‌بندی
     * 
     * @param string $searchTerm عبارت جستجو
     * @param int|null $categoryId شناسه دسته‌بندی (اختیاری)
     * @param int $page شماره صفحه
     * @param int $perPage تعداد آیتم در هر صفحه
     * @return array آرایه‌ای شامل مدل‌ها و اطلاعات صفحه‌بندی
     */
    public function searchModels($searchTerm, $categoryId = null, $page = 1, $perPage = 10) {
        try {
            $params = [];
            $conditions = [];
            
            if (!empty($searchTerm)) {
                $conditions[] = "(am.name LIKE ? OR am.manufacturer LIKE ? OR am.description LIKE ?)";
                $searchParam = '%' . $searchTerm . '%';
                $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
            }
            
            if (!empty($categoryId)) {
                $conditions[] = "am.category_id = ?";
                $params[] = $categoryId;
            }
            
            $whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
            
            // محاسبه تعداد کل رکوردها
            $countQuery = "SELECT COUNT(*) as total 
                          FROM asset_models am
                          $whereClause";
            $countResult = $this->db->query($countQuery, $params);
            $totalRecords = $countResult[0]['total'] ?? 0;
            
            // محاسبه تعداد کل صفحات
            $totalPages = ceil($totalRecords / $perPage);
            
            // تصحیح شماره صفحه
            $page = max(1, min($page, max(1, $totalPages)));
            
            // محاسبه آفست
            $offset = ($page - 1) * $perPage;
            
            $query = "SELECT am.*, ac.name as category_name,
                      (SELECT COUNT(*) FROM assets WHERE model_id = am.id) as asset_count
                      FROM asset_models am
                      JOIN asset_categories ac ON am.category_id = ac.id
                      $whereClause
                      ORDER BY ac.name, am.name
                      LIMIT $perPage OFFSET $offset";
                      
            $models = $this->db->query($query, $params);
            
            return [
                'models' => $models,
                'pagination' => [
                    'total' => $totalRecords,
                    'per_page' => $perPage,
                    'current_page' => $page,
                    'total_pages' => $totalPages,
                    'has_next' => $page < $totalPages,
                    'has_prev' => $page > 1,
                ]
            ];
        } catch (Exception $e) {
            error_log("Error in searchModels: " . $e->getMessage());
            return [
                'models' => [],
                'pagination' => [
                    'total' => 0,
                    'per_page' => $perPage,
                    'current_page' => 1,
                    'total_pages' => 0,
                    'has_next' => false,
                    'has_prev' => false,
                ]
            ];
        }
    }
    
    /**
     * دریافت تجهیز‌های یک مدل با صفحه‌بندی
     * 
     * @param int $modelId شناسه مدل
     * @param int $page شماره صفحه
     * @param int $perPage تعداد آیتم در هر صفحه
     * @return array آرایه‌ای شامل تجهیز‌ها و اطلاعات صفحه‌بندی
     */
    public function getAssetsByModelId($modelId, $page = 1, $perPage = 10) {
        try {
            // محاسبه تعداد کل رکوردها
            $countQuery = "SELECT COUNT(*) as total FROM assets WHERE model_id = ?";
            $countResult = $this->db->query($countQuery, [$modelId]);
            $totalRecords = $countResult[0]['total'] ?? 0;
            
            // محاسبه تعداد کل صفحات
            $totalPages = ceil($totalRecords / $perPage);
            
            // تصحیح شماره صفحه
            $page = max(1, min($page, max(1, $totalPages)));
            
            // محاسبه آفست
            $offset = ($page - 1) * $perPage;
            
            $query = "SELECT a.*, u.fullname as assigned_to
                      FROM assets a
                      LEFT JOIN asset_assignments aa ON a.id = aa.asset_id AND aa.is_current = 1
                      LEFT JOIN users u ON aa.user_id = u.id
                      WHERE a.model_id = ?
                      ORDER BY a.asset_tag
                      LIMIT $perPage OFFSET $offset";
            
            $assets = $this->db->query($query, [$modelId]);
            
            return [
                'assets' => $assets,
                'pagination' => [
                    'total' => $totalRecords,
                    'per_page' => $perPage,
                    'current_page' => $page,
                    'total_pages' => $totalPages,
                    'has_next' => $page < $totalPages,
                    'has_prev' => $page > 1,
                ]
            ];
        } catch (Exception $e) {
            error_log("Error in getAssetsByModelId: " . $e->getMessage());
            return [
                'assets' => [],
                'pagination' => [
                    'total' => 0,
                    'per_page' => $perPage,
                    'current_page' => 1,
                    'total_pages' => 0,
                    'has_next' => false,
                    'has_prev' => false,
                ]
            ];
        }
    }
    
    /**
     * دریافت مدل‌های محبوب
     * 
     * @param int $limit تعداد مدل‌ها
     * @return array لیست مدل‌های محبوب
     */
    public function getPopularModels($limit = 5) {
        try {
            $query = "SELECT am.id, am.name, am.manufacturer, ac.name as category_name, COUNT(a.id) as asset_count
                      FROM asset_models am
                      JOIN asset_categories ac ON am.category_id = ac.id
                      JOIN assets a ON am.id = a.model_id
                      GROUP BY am.id, am.name, am.manufacturer, ac.name
                      ORDER BY asset_count DESC
                      LIMIT ?";
            return $this->db->query($query, [$limit]);
        } catch (Exception $e) {
            error_log("Error in getPopularModels: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * دریافت مدل‌های سازنده‌های مختلف
     * 
     * @return array لیست سازنده‌ها با تعداد مدل‌ها
     */
    public function getManufacturersWithModelCount() {
        try {
            $query = "SELECT manufacturer, COUNT(*) as model_count
                      FROM asset_models
                      WHERE manufacturer IS NOT NULL AND manufacturer != ''
                      GROUP BY manufacturer
                      ORDER BY model_count DESC";
            return $this->db->query($query);
        } catch (Exception $e) {
            error_log("Error in getManufacturersWithModelCount: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * دریافت مدل‌های یک سازنده
     * 
     * @param string $manufacturer نام سازنده
     * @return array لیست مدل‌ها
     */
    public function getModelsByManufacturer($manufacturer) {
        try {
            $query = "SELECT am.*, ac.name as category_name,
                      (SELECT COUNT(*) FROM assets WHERE model_id = am.id) as asset_count
                      FROM asset_models am
                      JOIN asset_categories ac ON am.category_id = ac.id
                      WHERE am.manufacturer = ?
                      ORDER BY am.name";
            return $this->db->query($query, [$manufacturer]);
        } catch (Exception $e) {
            error_log("Error in getModelsByManufacturer: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * ایجاد چندین مدل به صورت یکجا
     * 
     * @param array $models آرایه‌ای از مدل‌ها (هر عنصر باید category_id، name و manufacturer داشته باشد)
     * @return array آرایه‌ای از شناسه‌های مدل‌های ایجاد شده
     */
    public function createMultipleModels($models) {
        try {
            if (empty($models) || !is_array($models)) {
                return [];
            }
            
            $this->db->beginTransaction();
            $createdIds = [];
            
            foreach ($models as $model) {
                if (!isset($model['category_id']) || !isset($model['name']) || !isset($model['manufacturer'])) {
                    continue;
                }
                
                $description = $model['description'] ?? '';
                
                // بررسی تکراری نبودن نام مدل در دسته‌بندی
                if ($this->modelNameExistsInCategory($model['name'], $model['category_id'])) {
                    continue;
                }
                
                $query = "INSERT INTO asset_models (category_id, name, manufacturer, description, created_at, updated_at) 
                          VALUES (?, ?, ?, ?, NOW(), NOW())";
                $result = $this->db->query($query, [$model['category_id'], $model['name'], $model['manufacturer'], $description]);
                
                if ($result) {
                    $modelId = $this->db->lastInsertId();
                    $createdIds[] = $modelId;
                    
                    // افزودن مشخصات پیش‌فرض
                    if (isset($model['default_specs']) && is_array($model['default_specs'])) {
                        foreach ($model['default_specs'] as $spec) {
                            if (isset($spec['spec_name']) && isset($spec['spec_value'])) {
                                $this->saveDefaultSpecForModel($modelId, $spec['spec_name'], $spec['spec_value']);
                            }
                        }
                    }
                }
            }
            
            $this->db->commit();
            return $createdIds;
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Error in createMultipleModels: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * دریافت مشخصات سخت‌افزاری رایج برای یک دسته‌بندی
     * 
     * @param int $categoryId شناسه دسته‌بندی
     * @param int $limit تعداد مشخصات
     * @return array لیست مشخصات رایج
     */
    public function getCommonSpecsForCategory($categoryId, $limit = 10) {
        try {
            $query = "SELECT spec_name, COUNT(*) as count
                      FROM asset_specifications asp
                      JOIN assets a ON asp.asset_id = a.id
                      JOIN asset_models am ON a.model_id = am.id
                      WHERE am.category_id = ?
                      GROUP BY spec_name
                      ORDER BY count DESC
                      LIMIT ?";
            return $this->db->query($query, [$categoryId, $limit]);
        } catch (Exception $e) {
            error_log("Error in getCommonSpecsForCategory: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * دریافت مقادیر رایج برای یک مشخصه سخت‌افزاری
     * 
     * @param string $specName نام مشخصه
     * @param int|null $categoryId شناسه دسته‌بندی (اختیاری)
     * @param int $limit تعداد مقادیر
     * @return array لیست مقادیر رایج
     */
    public function getCommonValuesForSpec($specName, $categoryId = null, $limit = 10) {
        try {
            $params = [$specName];
            $categoryClause = "";
            
            if ($categoryId) {
                $categoryClause = "AND am.category_id = ?";
                $params[] = $categoryId;
            }
            
            $params[] = $limit;
            
            $query = "SELECT spec_value, COUNT(*) as count
                      FROM asset_specifications asp
                      JOIN assets a ON asp.asset_id = a.id
                      JOIN asset_models am ON a.model_id = am.id
                      WHERE asp.spec_name = ? $categoryClause
                      GROUP BY spec_value
                      ORDER BY count DESC
                      LIMIT ?";
            return $this->db->query($query, $params);
        } catch (Exception $e) {
            error_log("Error in getCommonValuesForSpec: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * دریافت آمار تجهیز‌های نیازمند به‌روزرسانی
     * 
     * @param array $thresholds آرایه‌ای از آستانه‌ها (مثلاً ['RAM' => 4, 'CPU' => 2.0])
     * @return array آمار تجهیز‌های نیازمند به‌روزرسانی
     */
    public function getUpgradeStats($thresholds) {
        try {
            if (empty($thresholds) || !is_array($thresholds)) {
                return [];
            }
            
            $stats = [];
            
            foreach ($thresholds as $specName => $threshold) {
                $query = "SELECT COUNT(*) as count
                          FROM assets a
                          JOIN asset_specifications asp ON a.id = asp.asset_id
                          WHERE asp.spec_name = ? AND asp.spec_value < ?";
                $result = $this->db->query($query, [$specName, $threshold]);
                $stats[$specName] = $result[0]['count'] ?? 0;
            }
            
            return $stats;
        } catch (Exception $e) {
            error_log("Error in getUpgradeStats: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * دریافت آمار تجهیز‌های هر دسته‌بندی
     * 
     * @return array آمار تجهیز‌ها بر اساس دسته‌بندی
     */
    public function getCategoryStats() {
        try {
            $query = "SELECT ac.id, ac.name, COUNT(a.id) as asset_count
                      FROM asset_categories ac
                      LEFT JOIN asset_models am ON ac.id = am.category_id
                      LEFT JOIN assets a ON am.id = a.model_id
                      GROUP BY ac.id, ac.name
                      ORDER BY asset_count DESC";
            return $this->db->query($query);
        } catch (Exception $e) {
            error_log("Error in getCategoryStats: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * دریافت آمار تجهیز‌ها بر اساس سازنده
     * 
     * @return array آمار تجهیز‌ها بر اساس سازنده
     */
    public function getManufacturerStats() {
        try {
            $query = "SELECT am.manufacturer, COUNT(a.id) as asset_count
                      FROM asset_models am
                      JOIN assets a ON am.id = a.model_id
                      WHERE am.manufacturer IS NOT NULL AND am.manufacturer != ''
                      GROUP BY am.manufacturer
                      ORDER BY asset_count DESC";
            return $this->db->query($query);
        } catch (Exception $e) {
            error_log("Error in getManufacturerStats: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * دریافت آمار تجهیز‌ها بر اساس وضعیت
     * 
     * @return array آمار تجهیز‌ها بر اساس وضعیت
     */
    public function getStatusStats() {
        try {
            $query = "SELECT status, COUNT(*) as count
                      FROM assets
                      GROUP BY status
                      ORDER BY count DESC";
            return $this->db->query($query);
        } catch (Exception $e) {
            error_log("Error in getStatusStats: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * دریافت آمار تجهیز‌های تخصیص داده شده
     * 
     * @return array آمار تخصیص تجهیز‌ها
     */
    public function getAssignmentStats() {
        try {
            $query = "SELECT 
                        (SELECT COUNT(*) FROM assets) as total,
                        (SELECT COUNT(*) FROM assets a
                         JOIN asset_assignments aa ON a.id = aa.asset_id
                         WHERE aa.is_current = 1) as assigned,
                        (SELECT COUNT(*) FROM assets a
                         LEFT JOIN asset_assignments aa ON a.id = aa.asset_id AND aa.is_current = 1
                         WHERE aa.id IS NULL) as unassigned";
            $result = $this->db->query($query);
            return $result ? $result[0] : ['total' => 0, 'assigned' => 0, 'unassigned' => 0];
        } catch (Exception $e) {
            error_log("Error in getAssignmentStats: " . $e->getMessage());
            return ['total' => 0, 'assigned' => 0, 'unassigned' => 0];
        }
    }
    
    /**
     * دریافت آمار سن تجهیز‌ها
     * 
     * @return array آمار سن تجهیز‌ها
     */
    public function getAssetAgeStats() {
        try {
            $query = "SELECT 
                        SUM(CASE WHEN purchase_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR) THEN 1 ELSE 0 END) as less_than_1_year,
                        SUM(CASE WHEN purchase_date < DATE_SUB(CURDATE(), INTERVAL 1 YEAR) AND purchase_date >= DATE_SUB(CURDATE(), INTERVAL 2 YEAR) THEN 1 ELSE 0 END) as between_1_2_years,
                        SUM(CASE WHEN purchase_date < DATE_SUB(CURDATE(), INTERVAL 2 YEAR) AND purchase_date >= DATE_SUB(CURDATE(), INTERVAL 3 YEAR) THEN 1 ELSE 0 END) as between_2_3_years,
                        SUM(CASE WHEN purchase_date < DATE_SUB(CURDATE(), INTERVAL 3 YEAR) AND purchase_date >= DATE_SUB(CURDATE(), INTERVAL 4 YEAR) THEN 1 ELSE 0 END) as between_3_4_years,
                        SUM(CASE WHEN purchase_date < DATE_SUB(CURDATE(), INTERVAL 4 YEAR) THEN 1 ELSE 0 END) as more_than_4_years
                      FROM assets
                      WHERE purchase_date IS NOT NULL";
            $result = $this->db->query($query);
            return $result ? $result[0] : [
                'less_than_1_year' => 0,
                'between_1_2_years' => 0,
                'between_2_3_years' => 0,
                'between_3_4_years' => 0,
                'more_than_4_years' => 0
            ];
        } catch (Exception $e) {
            error_log("Error in getAssetAgeStats: " . $e->getMessage());
            return [
                'less_than_1_year' => 0,
                'between_1_2_years' => 0,
                'between_2_3_years' => 0,
                'between_3_4_years' => 0,
                'more_than_4_years' => 0
            ];
        }
    }
    
    /**
     * دریافت آمار تجهیز‌های نیازمند توجه
     * 
     * @return array آمار تجهیز‌های نیازمند توجه
     */
    public function getAssetsNeedingAttentionCount() {
        try {
            $today = date('Y-m-d');
            $thirtyDaysLater = date('Y-m-d', strtotime('+30 days'));
            
            $query = "SELECT 
                        (SELECT COUNT(*) FROM assets WHERE warranty_expiry_date BETWEEN ? AND ?) as expiring_warranty,
                        (SELECT COUNT(*) FROM assets WHERE status = 'in_repair') as in_repair,
                        (SELECT COUNT(*) FROM maintenance_schedules WHERE next_maintenance_date < ?) as overdue_maintenance,
                        (SELECT COUNT(*) FROM asset_software WHERE expiry_date BETWEEN ? AND ?) as expiring_licenses";
            
            $result = $this->db->query($query, [$today, $thirtyDaysLater, $today, $today, $thirtyDaysLater]);
            
            return $result ? $result[0] : [
                'expiring_warranty' => 0,
                'in_repair' => 0,
                'overdue_maintenance' => 0,
                'expiring_licenses' => 0
            ];
        } catch (Exception $e) {
            error_log("Error in getAssetsNeedingAttentionCount: " . $e->getMessage());
            return [
                'expiring_warranty' => 0,
                'in_repair' => 0,
                'overdue_maintenance' => 0,
                'expiring_licenses' => 0
            ];
        }
    }
    
    /**
     * دریافت لیست سازنده‌ها
     * 
     * @return array لیست سازنده‌ها
     */
    public function getManufacturersList() {
        try {
            $query = "SELECT DISTINCT manufacturer 
                      FROM asset_models 
                      WHERE manufacturer IS NOT NULL AND manufacturer != '' 
                      ORDER BY manufacturer";
            return $this->db->query($query);
        } catch (Exception $e) {
            error_log("Error in getManufacturersList: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * دریافت لیست مشخصات سخت‌افزاری
     * 
     * @return array لیست مشخصات سخت‌افزاری
     */
    public function getSpecNamesList() {
        try {
            $query = "SELECT DISTINCT spec_name 
                      FROM asset_specifications 
                      ORDER BY spec_name";
            return $this->db->query($query);
        } catch (Exception $e) {
            error_log("Error in getSpecNamesList: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * دریافت لیست نرم‌افزارها
     * 
     * @return array لیست نرم‌افزارها
     */
    public function getSoftwareList() {
        try {
            $query = "SELECT DISTINCT software_name 
                      FROM asset_software 
                      ORDER BY software_name";
            return $this->db->query($query);
        } catch (Exception $e) {
            error_log("Error in getSoftwareList: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * دریافت همه دسته‌بندی‌ها
     * 
     * @return array لیست دسته‌بندی‌ها
     */
    public function getAllCategories() {
        try {
            $query = "SELECT * FROM asset_categories ORDER BY name";
            return $this->db->query($query);
        } catch (Exception $e) {
            error_log("Error in getAllCategories: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * دریافت یک دسته‌بندی با شناسه
     * 
     * @param int $id شناسه دسته‌بندی
     * @return array|null دسته‌بندی یا null در صورت عدم وجود
     */
    public function getCategoryById($id) {
        try {
            $query = "SELECT * FROM asset_categories WHERE id = ?";
            $result = $this->db->query($query, [$id]);
            return $result ? $result[0] : null;
        } catch (Exception $e) {
            error_log("Error in getCategoryById: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * ایجاد دسته‌بندی جدید
     * 
     * @param string $name نام دسته‌بندی
     * @param string $description توضیحات دسته‌بندی
     * @return int|bool شناسه دسته‌بندی جدید یا false در صورت خطا
     */
    public function createCategory($name, $description = '') {
        try {
            // بررسی تکراری نبودن نام دسته‌بندی
            $checkQuery = "SELECT COUNT(*) as count FROM asset_categories WHERE name = ?";
            $checkResult = $this->db->query($checkQuery, [$name]);
            
            if (($checkResult[0]['count'] ?? 0) > 0) {
                return false;
            }
            
            $query = "INSERT INTO asset_categories (name, description, created_at, updated_at) 
                      VALUES (?, ?, NOW(), NOW())";
            $result = $this->db->query($query, [$name, $description]);
            
            if ($result) {
                return $this->db->lastInsertId();
            }
            
            return false;
        } catch (Exception $e) {
            error_log("Error in createCategory: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * به‌روزرسانی دسته‌بندی
     * 
     * @param int $id شناسه دسته‌بندی
     * @param string $name نام جدید دسته‌بندی
     * @param string $description توضیحات جدید دسته‌بندی
     * @return bool نتیجه عملیات
     */
    public function updateCategory($id, $name, $description = '') {
        try {
            // بررسی تکراری نبودن نام دسته‌بندی (به جز خود این دسته‌بندی)
            $checkQuery = "SELECT COUNT(*) as count FROM asset_categories WHERE name = ? AND id != ?";
            $checkResult = $this->db->query($checkQuery, [$name, $id]);
            
            if (($checkResult[0]['count'] ?? 0) > 0) {
                return false;
            }
            
            $query = "UPDATE asset_categories 
                      SET name = ?, description = ?, updated_at = NOW() 
                      WHERE id = ?";
            return $this->db->query($query, [$name, $description, $id]);
        } catch (Exception $e) {
            error_log("Error in updateCategory: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * حذف دسته‌بندی
     * 
     * @param int $id شناسه دسته‌بندی
     * @return bool نتیجه عملیات
     */
    public function deleteCategory($id) {
        try {
            // بررسی وجود مدل‌های وابسته به این دسته‌بندی
            $checkQuery = "SELECT COUNT(*) as count FROM asset_models WHERE category_id = ?";
            $checkResult = $this->db->query($checkQuery, [$id]);
            
            if (($checkResult[0]['count'] ?? 0) > 0) {
                // اگر مدل‌های وابسته وجود دارند، حذف انجام نمی‌شود
                return false;
            }
            
            $query = "DELETE FROM asset_categories WHERE id = ?";
            return $this->db->query($query, [$id]);
        } catch (Exception $e) {
            error_log("Error in deleteCategory: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * دریافت تعداد تجهیز‌ها بر اساس مدل
     * 
     * @param int $modelId شناسه مدل
     * @return int تعداد تجهیز‌ها
     */
    public function getAssetsCountByModelId($modelId) {
        try {
            $query = "SELECT COUNT(*) as count FROM assets WHERE model_id = ?";
            $result = $this->db->query($query, [$modelId]);
            return $result ? $result[0]['count'] : 0;
        } catch (Exception $e) {
            error_log("Error in getAssetsCountByModelId: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * دریافت تعداد تجهیز‌ها بر اساس دسته‌بندی
     * 
     * @param int $categoryId شناسه دسته‌بندی
     * @return int تعداد تجهیز‌ها
     */
    public function getAssetsCountByCategoryId($categoryId) {
        try {
            $query = "SELECT COUNT(*) as count 
                      FROM assets a
                      JOIN asset_models am ON a.model_id = am.id
                      WHERE am.category_id = ?";
            $result = $this->db->query($query, [$categoryId]);
            return $result ? $result[0]['count'] : 0;
        } catch (Exception $e) {
            error_log("Error in getAssetsCountByCategoryId: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * دریافت تعداد مدل‌ها بر اساس دسته‌بندی
     * 
     * @param int $categoryId شناسه دسته‌بندی
     * @return int تعداد مدل‌ها
     */
    public function getModelsCountByCategoryId($categoryId) {
        try {
            $query = "SELECT COUNT(*) as count FROM asset_models WHERE category_id = ?";
            $result = $this->db->query($query, [$categoryId]);
            return $result ? $result[0]['count'] : 0;
        } catch (Exception $e) {
            error_log("Error in getModelsCountByCategoryId: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * افزودن مدل جدید
     * 
     * @param array $data داده‌های مدل
     * @return int|bool شناسه مدل جدید یا false در صورت خطا
     */
    public function addModel($data) {
        try {
            // بررسی وجود فیلدهای ضروری
            if (empty($data['name']) || empty($data['category_id'])) {
                error_log("Missing required fields in addModel");
                return false;
            }
            
            $query = "INSERT INTO asset_models (name, category_id, manufacturer, model_number, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, NOW(), NOW())";
            
            // دریافت اتصال اصلی
            $connection = $this->db->getConnection();
            
            if ($connection instanceof PDO) {
                $stmt = $connection->prepare($query);
                $result = $stmt->execute([
                    $data['name'],
                    $data['category_id'],
                    $data['manufacturer'] ?? null,
                    $data['model_number'] ?? null
                ]);
                
                if ($result) {
                    $newId = $connection->lastInsertId();
                    error_log("Added model with ID: " . $newId);
                    return $newId;
                }
                
                error_log("PDO execute failed in addModel: " . implode(', ', $stmt->errorInfo()));
                return false;
            } 
            else {
                error_log("Unknown database connection type in addModel: " . get_class($connection));
                return false;
            }
        } catch (Exception $e) {
            error_log("Error in addModel: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            return false;
        }
    }

    /**
     * تخصیص دارایی به کاربر
     * 
     * @param array $data داده‌های تخصیص
     * @return bool نتیجه عملیات
     */
    public function assignAsset($data) {
        try {
            $connection = $this->db->getConnection();
            
            // قبل از تخصیص جدید، تخصیص‌های قبلی را غیرفعال می‌کنیم
            $query1 = "UPDATE asset_assignments 
                    SET is_current = 0, updated_at = NOW() 
                    WHERE asset_id = ? AND is_current = 1";
            
            $stmt1 = $connection->prepare($query1);
            $stmt1->execute([$data['asset_id']]);
            
            // ایجاد تخصیص جدید
            $query2 = "INSERT INTO asset_assignments 
                    (asset_id, user_id, assigned_date, assigned_by, notes, is_current, created_at, updated_at) 
                    VALUES (?, ?, NOW(), ?, ?, 1, NOW(), NOW())";
            
            $stmt2 = $connection->prepare($query2);
            return $stmt2->execute([
                $data['asset_id'],
                $data['user_id'],
                $data['assigned_by'],
                $data['notes'] ?? null
            ]);
        } catch (Exception $e) {
            error_log("Error in assignAsset: " . $e->getMessage());
            return false;
        }
    }

    /**
     * به‌روزرسانی وضعیت دارایی
     * 
     * @param int $assetId شناسه دارایی
     * @param string $status وضعیت جدید
     * @return bool نتیجه عملیات
     */
    public function updateAssetStatus($assetId, $status) {
        try {
            $connection = $this->db->getConnection();
            
            $query = "UPDATE assets SET status = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $connection->prepare($query);
            return $stmt->execute([$status, $assetId]);
        } catch (Exception $e) {
            error_log("Error in updateAssetStatus: " . $e->getMessage());
            return false;
        }
    }

    /**
     * دریافت همه تجهیزات با فیلترها و صفحه‌بندی
     * 
     * @param array $filters فیلترهای جستجو
     * @param string $sortBy ستون مرتب‌سازی
     * @param string $order ترتیب مرتب‌سازی (asc یا desc)
     * @param int $page شماره صفحه
     * @param int $perPage تعداد آیتم در هر صفحه
     * @return array آرایه‌ای شامل تجهیزات و اطلاعات صفحه‌بندی
     */
    public function getAllAssets($filters = [], $sortBy = 'id', $order = 'desc', $page = 1, $perPage = 10) {
        try {
            $params = [];
            $conditions = [];
            
            // اعمال فیلترها
            if (!empty($filters['query'])) {
                $searchTerm = '%' . $filters['query'] . '%';
                $conditions[] = "(a.name LIKE ? OR a.asset_tag LIKE ? OR a.serial_number LIKE ? OR a.notes LIKE ?)";
                array_push($params, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
            }
            
            if (!empty($filters['model_id'])) {
                $conditions[] = "a.model_id = ?";
                $params[] = $filters['model_id'];
            }
            
            if (!empty($filters['category_id'])) {
                $conditions[] = "m.category_id = ?";
                $params[] = $filters['category_id'];
            }
            
            if (!empty($filters['status'])) {
                $conditions[] = "a.status = ?";
                $params[] = $filters['status'];
            }
            
            if (!empty($filters['location'])) {
                $searchLocation = '%' . $filters['location'] . '%';
                $conditions[] = "a.location LIKE ?";
                $params[] = $searchLocation;
            }
            
            if (!empty($filters['asset_tag'])) {
                $conditions[] = "a.asset_tag LIKE ?";
                $params[] = '%' . $filters['asset_tag'] . '%';
            }
            
            if (!empty($filters['serial_number'])) {
                $conditions[] = "a.serial_number LIKE ?";
                $params[] = '%' . $filters['serial_number'] . '%';
            }
            
            if (!empty($filters['employee_number'])) {
                $conditions[] = "u.employee_number LIKE ?";
                $params[] = '%' . $filters['employee_number'] . '%';
            }
            
            $whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
            
            // محاسبه تعداد کل رکوردها
            $countQuery = "SELECT COUNT(DISTINCT a.id) as total 
                        FROM assets a
                        LEFT JOIN asset_models m ON a.model_id = m.id
                        LEFT JOIN asset_categories c ON m.category_id = c.id
                        LEFT JOIN asset_assignments aa ON a.id = aa.asset_id AND aa.is_current = 1
                        LEFT JOIN users u ON aa.user_id = u.id
                        $whereClause";
                        
            $countResult = $this->db->query($countQuery, $params);
            $totalRecords = $countResult[0]['total'] ?? 0;
            
            // محاسبه تعداد کل صفحات
            $totalPages = ceil($totalRecords / $perPage);
            
            // تصحیح شماره صفحه
            $page = max(1, min($page, max(1, $totalPages)));
            
            // محاسبه آفست
            $offset = ($page - 1) * $perPage;
            
            // تعیین ستون مرتب‌سازی معتبر
            $allowedColumns = [
                'id', 'name', 'asset_tag', 'serial_number', 'status', 'location',
                'category_name', 'model_name', 'assigned_to', 'created_at', 'updated_at'
            ];
            
            if (!in_array($sortBy, $allowedColumns)) {
                $sortBy = 'id';
            }
            
            // تعیین ترتیب مرتب‌سازی معتبر
            $order = strtolower($order) === 'asc' ? 'ASC' : 'DESC';
            
            // تبدیل نام ستون‌های مجازی به ستون‌های واقعی برای مرتب‌سازی
            $sortColumn = $sortBy;
            if ($sortBy === 'category_name') {
                $sortColumn = 'c.name';
            } elseif ($sortBy === 'model_name') {
                $sortColumn = 'm.name';
            } elseif ($sortBy === 'assigned_to') {
                $sortColumn = 'u.fullname';
            } else {
                $sortColumn = "a.$sortBy";
            }
            
            $query = "SELECT a.*, 
                    c.name as category_name, 
                    m.name as model_name, 
                    u.fullname as assigned_to,
                    u.employee_number as assigned_employee_number,
                    u.id as assigned_user_id,
                    aa.assigned_date
                    FROM assets a
                    LEFT JOIN asset_models m ON a.model_id = m.id
                    LEFT JOIN asset_categories c ON m.category_id = c.id
                    LEFT JOIN asset_assignments aa ON a.id = aa.asset_id AND aa.is_current = 1
                    LEFT JOIN users u ON aa.user_id = u.id
                    $whereClause
                    GROUP BY a.id
                    ORDER BY $sortColumn $order
                    LIMIT $perPage OFFSET $offset";
            
            $assets = $this->db->query($query, $params);
            
            // ثبت لاگ برای اشکال‌زدایی
            error_log("Assets query: $query");
            error_log("Assets query params: " . json_encode($params));
            error_log("Assets result count: " . count($assets));
            
            // بررسی داده‌های برگشتی
            if (!empty($assets)) {
                error_log("First asset data: " . print_r($assets[0], true));
            }
            
            return [
                'assets' => $assets,
                'pagination' => [
                    'total' => $totalRecords,
                    'per_page' => $perPage,
                    'current_page' => $page,
                    'total_pages' => $totalPages,
                    'has_next' => $page < $totalPages,
                    'has_prev' => $page > 1,
                ]
            ];
        } catch (Exception $e) {
            error_log("Error in getAllAssets: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            return [
                'assets' => [],
                'pagination' => [
                    'total' => 0,
                    'per_page' => $perPage,
                    'current_page' => 1,
                    'total_pages' => 0,
                    'has_next' => false,
                    'has_prev' => false,
                ]
            ];
        }
    }

    /**
     * جستجوی دارایی‌ها با فیلترها و صفحه‌بندی
     * 
     * @param array $filters فیلترهای جستجو
     * @param string $sort ستون مرتب‌سازی
     * @param string $order ترتیب مرتب‌سازی (ASC یا DESC)
     * @param int $page شماره صفحه
     * @param int $perPage تعداد آیتم در هر صفحه
     * @return array آرایه‌ای شامل دارایی‌ها و اطلاعات صفحه‌بندی
     */
    public function searchAssets($filters = [], $sort = 'created_at', $order = 'DESC', $page = 1, $perPage = 10) {
        try {
            $params = [];
            $conditions = [];
            
            // اعمال فیلترها
            if (!empty($filters['name'])) {
                $conditions[] = "a.name LIKE ?";
                $params[] = '%' . $filters['name'] . '%';
            }
            
            if (!empty($filters['asset_tag'])) {
                $conditions[] = "a.asset_tag LIKE ?";
                $params[] = '%' . $filters['asset_tag'] . '%';
            }
            
            if (!empty($filters['serial'])) {
                $conditions[] = "a.serial_number LIKE ?";
                $params[] = '%' . $filters['serial'] . '%';
            }
            
            if (!empty($filters['category_id'])) {
                $conditions[] = "m.category_id = ?";
                $params[] = $filters['category_id'];
            }
            
            if (!empty($filters['model_id'])) {
                $conditions[] = "a.model_id = ?";
                $params[] = $filters['model_id'];
            }
            
            if (!empty($filters['status'])) {
                $conditions[] = "a.status = ?";
                $params[] = $filters['status'];
            }
            
            if (!empty($filters['assigned_to'])) {
                $conditions[] = "aa.user_id = ?";
                $params[] = $filters['assigned_to'];
            }
            
            if (!empty($filters['employee_number'])) {
                $conditions[] = "u.employee_number LIKE ?";
                $params[] = '%' . $filters['employee_number'] . '%';
            }
            
            if (!empty($filters['location'])) {
                $conditions[] = "a.location LIKE ?";
                $params[] = '%' . $filters['location'] . '%';
            }
            
            $whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
            
            // محاسبه تعداد کل رکوردها
            $countQuery = "SELECT COUNT(DISTINCT a.id) as total 
                        FROM assets a
                        LEFT JOIN asset_models m ON a.model_id = m.id
                        LEFT JOIN asset_categories c ON m.category_id = c.id
                        LEFT JOIN asset_assignments aa ON a.id = aa.asset_id AND aa.is_current = 1
                        LEFT JOIN users u ON aa.user_id = u.id
                        $whereClause";
                        
            $countResult = $this->db->query($countQuery, $params);
            $totalRecords = $countResult[0]['total'] ?? 0;
            
            // محاسبه تعداد کل صفحات
            $totalPages = ceil($totalRecords / $perPage);
            
            // تصحیح شماره صفحه
            $page = max(1, min($page, max(1, $totalPages)));
            
            // محاسبه آفست
            $offset = ($page - 1) * $perPage;
            
            // تعیین ستون مرتب‌سازی
            $sortColumn = $sort;
            if ($sort === 'category_name') {
                $sortColumn = 'c.name';
            } elseif ($sort === 'model_name') {
                $sortColumn = 'm.name';
            } elseif ($sort === 'assigned_to') {
                $sortColumn = 'u.fullname';
            } else {
                $sortColumn = "a.$sort";
            }
            
            // تعیین ترتیب مرتب‌سازی معتبر
            $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
            
            $query = "SELECT a.*, 
                    c.name as category_name, 
                    m.name as model_name, 
                    u.fullname as assigned_to,
                    u.employee_number as assigned_employee_number,
                    u.id as assigned_user_id,
                    aa.assigned_date
                    FROM assets a
                    LEFT JOIN asset_models m ON a.model_id = m.id
                    LEFT JOIN asset_categories c ON m.category_id = c.id
                    LEFT JOIN asset_assignments aa ON a.id = aa.asset_id AND aa.is_current = 1
                    LEFT JOIN users u ON aa.user_id = u.id
                    $whereClause
                    GROUP BY a.id
                    ORDER BY $sortColumn $order
                    LIMIT $perPage OFFSET $offset";
            
            $assets = $this->db->query($query, $params);
            
            // ثبت لاگ برای اشکال‌زدایی
            error_log("Assets search query: $query");
            error_log("Assets search params: " . json_encode($params));
            error_log("Assets result count: " . count($assets));
            
            // بررسی داده‌های برگشتی
            if (!empty($assets)) {
                error_log("First asset data: " . print_r($assets[0], true));
            }
            
            return [
                'assets' => $assets,
                'total' => $totalRecords,
                'page' => $page,
                'perPage' => $perPage,
                'totalPages' => $totalPages,
            ];
        } catch (Exception $e) {
            error_log("Error in searchAssets: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            return [
                'assets' => [],
                'total' => 0,
                'page' => 1,
                'perPage' => $perPage,
                'totalPages' => 0,
            ];
        }
    }

    /**
 * اصلاح وضعیت تجهیزات با وضعیت خالی
 */
public function fixEmptyStatuses() {
    try {
        $db = Database::getInstance();
        $pdo = $db->getConnection();
        
        // لاگ برای دیباگ
        error_log("Fixing empty statuses in assets table");
        
        $sql = "UPDATE assets SET status = 'available' WHERE status IS NULL OR status = ''";
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute();
        
        if ($result) {
            $count = $stmt->rowCount();
            error_log("Fixed $count assets with empty status");
            return $count;
        }
        
        error_log("No assets with empty status were found or update failed");
        return 0;
    } catch (PDOException $e) {
        error_log("Error fixing empty statuses: " . $e->getMessage());
        return false;
    }
}
    
}