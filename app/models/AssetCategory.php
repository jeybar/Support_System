<?php
require_once __DIR__ . '/../core/Database.php';

class AssetCategory {
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
     * دریافت همه دسته‌بندی‌ها
     * 
     * @return array لیست دسته‌بندی‌ها
     */
    public function getAllCategories() {
        try {
            $query = "SELECT id, name FROM asset_categories ORDER BY name";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // لاگ برای دیباگ
            error_log("getAllCategories result: " . json_encode($result));
            
            return $result;
        } catch (PDOException $e) {
            error_log("Error in getAllCategories: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * دریافت دسته‌بندی با شناسه مشخص
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
     * دریافت دسته‌بندی با نام مشخص
     * 
     * @param string $name نام دسته‌بندی
     * @return array|null دسته‌بندی یا null در صورت عدم وجود
     */
    public function getCategoryByName($name) {
        try {
            $query = "SELECT * FROM asset_categories WHERE name = ?";
            $result = $this->db->query($query, [$name]);
            return $result ? $result[0] : null;
        } catch (Exception $e) {
            error_log("Error in getCategoryByName: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * بررسی وجود دسته‌بندی با نام مشخص
     * 
     * @param string $name نام دسته‌بندی
     * @param int|null $excludeId شناسه دسته‌بندی که باید از بررسی مستثنی شود (برای به‌روزرسانی)
     * @return bool آیا دسته‌بندی با این نام وجود دارد
     */
    public function categoryNameExists($name, $excludeId = null) {
        try {
            $params = [$name];
            $query = "SELECT COUNT(*) as count FROM asset_categories WHERE name = ?";
            
            if ($excludeId !== null) {
                $query .= " AND id != ?";
                $params[] = $excludeId;
            }
            
            $result = $this->db->query($query, $params);
            return ($result[0]['count'] ?? 0) > 0;
        } catch (Exception $e) {
            error_log("Error in categoryNameExists: " . $e->getMessage());
            return false;
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
            if ($this->categoryNameExists($name)) {
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
            if ($this->categoryNameExists($name, $id)) {
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
     * دریافت دسته‌بندی‌ها همراه با تعداد تجهیز‌های هر دسته‌بندی
     * 
     * @return array لیست دسته‌بندی‌ها با تعداد تجهیز‌ها
     */
    public function getCategoryWithAssetCount() {
        try {
            $query = "SELECT ac.*, COUNT(a.id) as asset_count 
                      FROM asset_categories ac
                      LEFT JOIN asset_models am ON ac.id = am.category_id
                      LEFT JOIN assets a ON am.id = a.model_id
                      GROUP BY ac.id, ac.name, ac.description, ac.created_at, ac.updated_at
                      ORDER BY ac.name";
            return $this->db->query($query);
        } catch (Exception $e) {
            error_log("Error in getCategoryWithAssetCount: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * دریافت دسته‌بندی‌ها همراه با تعداد مدل‌های هر دسته‌بندی
     * 
     * @return array لیست دسته‌بندی‌ها با تعداد مدل‌ها
     */
    public function getCategoryWithModelCount() {
        try {
            $query = "SELECT ac.*, COUNT(am.id) as model_count 
                      FROM asset_categories ac
                      LEFT JOIN asset_models am ON ac.id = am.category_id
                      GROUP BY ac.id, ac.name, ac.description, ac.created_at, ac.updated_at
                      ORDER BY ac.name";
            return $this->db->query($query);
        } catch (Exception $e) {
            error_log("Error in getCategoryWithModelCount: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * جستجوی دسته‌بندی‌ها
     * 
     * @param string $searchTerm عبارت جستجو
     * @return array نتایج جستجو
     */
    public function searchCategories($searchTerm) {
        try {
            $searchParam = '%' . $searchTerm . '%';
            $query = "SELECT * FROM asset_categories 
                      WHERE name LIKE ? OR description LIKE ? 
                      ORDER BY name";
            return $this->db->query($query, [$searchParam, $searchParam]);
        } catch (Exception $e) {
            error_log("Error in searchCategories: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * دریافت دسته‌بندی‌ها با صفحه‌بندی
     * 
     * @param int $page شماره صفحه
     * @param int $perPage تعداد آیتم در هر صفحه
     * @return array آرایه‌ای شامل دسته‌بندی‌ها و اطلاعات صفحه‌بندی
     */
    public function getCategoriesPaginated($page = 1, $perPage = 10) {
        try {
            // محاسبه تعداد کل رکوردها
            $countQuery = "SELECT COUNT(*) as total FROM asset_categories";
            $countResult = $this->db->query($countQuery);
            $totalRecords = $countResult[0]['total'] ?? 0;
            
            // محاسبه تعداد کل صفحات
            $totalPages = ceil($totalRecords / $perPage);
            
            // تصحیح شماره صفحه
            $page = max(1, min($page, max(1, $totalPages)));
            
            // محاسبه آفست
            $offset = ($page - 1) * $perPage;
            
            $query = "SELECT * FROM asset_categories ORDER BY name LIMIT $perPage OFFSET $offset";
            $categories = $this->db->query($query);
            
            return [
                'categories' => $categories,
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
            error_log("Error in getCategoriesPaginated: " . $e->getMessage());
            return [
                'categories' => [],
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
     * دریافت تعداد تجهیز‌های یک دسته‌بندی
     * 
     * @param int $categoryId شناسه دسته‌بندی
     * @return int تعداد تجهیز‌ها
     */
    public function getAssetCountForCategory($categoryId) {
        try {
            $query = "SELECT COUNT(a.id) as count 
                      FROM asset_categories ac
                      LEFT JOIN asset_models am ON ac.id = am.category_id
                      LEFT JOIN assets a ON am.id = a.model_id
                      WHERE ac.id = ?
                      GROUP BY ac.id";
            $result = $this->db->query($query, [$categoryId]);
            return $result ? ($result[0]['count'] ?? 0) : 0;
        } catch (Exception $e) {
            error_log("Error in getAssetCountForCategory: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * دریافت تعداد مدل‌های یک دسته‌بندی
     * 
     * @param int $categoryId شناسه دسته‌بندی
     * @return int تعداد مدل‌ها
     */
    public function getModelCountForCategory($categoryId) {
        try {
            $query = "SELECT COUNT(*) as count FROM asset_models WHERE category_id = ?";
            $result = $this->db->query($query, [$categoryId]);
            return $result ? ($result[0]['count'] ?? 0) : 0;
        } catch (Exception $e) {
            error_log("Error in getModelCountForCategory: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * دریافت مدل‌های یک دسته‌بندی
     * 
     * @param int $categoryId شناسه دسته‌بندی
     * @return array لیست مدل‌ها
     */
    public function getModelsForCategory($categoryId) {
        try {
            $query = "SELECT * FROM asset_models WHERE category_id = ? ORDER BY name";
            return $this->db->query($query, [$categoryId]);
        } catch (Exception $e) {
            error_log("Error in getModelsForCategory: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * دریافت تجهیز‌های یک دسته‌بندی
     * 
     * @param int $categoryId شناسه دسته‌بندی
     * @param int $page شماره صفحه
     * @param int $perPage تعداد آیتم در هر صفحه
     * @return array آرایه‌ای شامل تجهیز‌ها و اطلاعات صفحه‌بندی
     */
    public function getAssetsForCategory($categoryId, $page = 1, $perPage = 10) {
        try {
            // محاسبه تعداد کل رکوردها
            $countQuery = "SELECT COUNT(a.id) as total 
                          FROM assets a
                          JOIN asset_models am ON a.model_id = am.id
                          WHERE am.category_id = ?";
            $countResult = $this->db->query($countQuery, [$categoryId]);
            $totalRecords = $countResult[0]['total'] ?? 0;
            
            // محاسبه تعداد کل صفحات
            $totalPages = ceil($totalRecords / $perPage);
            
            // تصحیح شماره صفحه
            $page = max(1, min($page, max(1, $totalPages)));
            
            // محاسبه آفست
            $offset = ($page - 1) * $perPage;
            
            $query = "SELECT a.*, am.name as model_name, u.fullname as assigned_to
                      FROM assets a
                      JOIN asset_models am ON a.model_id = am.id
                      LEFT JOIN asset_assignments aa ON a.id = aa.asset_id AND aa.is_current = 1
                      LEFT JOIN users u ON aa.user_id = u.id
                      WHERE am.category_id = ?
                      ORDER BY a.asset_tag
                      LIMIT $perPage OFFSET $offset";
            $assets = $this->db->query($query, [$categoryId]);
            
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
            error_log("Error in getAssetsForCategory: " . $e->getMessage());
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
     * ایجاد چندین دسته‌بندی به صورت یکجا
     * 
     * @param array $categories آرایه‌ای از دسته‌بندی‌ها (هر عنصر باید name و description داشته باشد)
     * @return array آرایه‌ای از شناسه‌های دسته‌بندی‌های ایجاد شده
     */
    public function createMultipleCategories($categories) {
        try {
            if (empty($categories) || !is_array($categories)) {
                return [];
            }
            
            $this->db->beginTransaction();
            $createdIds = [];
            
            foreach ($categories as $category) {
                if (!isset($category['name'])) {
                    continue;
                }
                
                $description = $category['description'] ?? '';
                
                // بررسی تکراری نبودن نام دسته‌بندی
                if ($this->categoryNameExists($category['name'])) {
                    continue;
                }
                
                $query = "INSERT INTO asset_categories (name, description, created_at, updated_at) 
                          VALUES (?, ?, NOW(), NOW())";
                $result = $this->db->query($query, [$category['name'], $description]);
                
                if ($result) {
                    $createdIds[] = $this->db->lastInsertId();
                }
            }
            
            $this->db->commit();
            return $createdIds;
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Error in createMultipleCategories: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * دریافت دسته‌بندی‌های پرکاربرد (با بیشترین تعداد تجهیز)
     * 
     * @param int $limit تعداد دسته‌بندی‌ها
     * @return array لیست دسته‌بندی‌ها
     */
    public function getTopCategories($limit = 5) {
        try {
            $query = "SELECT ac.*, COUNT(a.id) as asset_count 
                      FROM asset_categories ac
                      LEFT JOIN asset_models am ON ac.id = am.category_id
                      LEFT JOIN assets a ON am.id = a.model_id
                      GROUP BY ac.id, ac.name, ac.description, ac.created_at, ac.updated_at
                      ORDER BY asset_count DESC
                      LIMIT ?";
            return $this->db->query($query, [$limit]);
        } catch (Exception $e) {
            error_log("Error in getTopCategories: " . $e->getMessage());
            return [];
        }
    }

    /**
     * افزودن دسته‌بندی جدید
     * 
     * @param array $data داده‌های دسته‌بندی
     * @return int|bool شناسه دسته‌بندی جدید یا false در صورت خطا
     */
    public function addCategory($data) {
        try {
            $query = "INSERT INTO asset_categories (name, description, created_at, updated_at) 
                    VALUES (?, ?, NOW(), NOW())";
            
            if ($this->db instanceof PDO) {
                $stmt = $this->db->prepare($query);
                $stmt->execute([
                    $data['name'],
                    $data['description'] ?? null
                ]);
                return $this->db->lastInsertId();
            } elseif ($this->db instanceof mysqli) {
                $stmt = $this->db->prepare($query);
                $description = $data['description'] ?? null;
                $stmt->bind_param("ss", $data['name'], $description);
                
                if ($stmt->execute()) {
                    return $this->db->insert_id;
                }
            }
            
            return false;
        } catch (Exception $e) {
            error_log("Error in addCategory: " . $e->getMessage());
            return false;
        }
    }
}