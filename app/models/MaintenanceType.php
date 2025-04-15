<?php
require_once __DIR__ . '/../core/Database.php';

class MaintenanceType {
    private $db;
    
    public function __construct() {
        // بررسی کنید که آیا Database از الگوی Singleton استفاده می‌کند
        if (method_exists('Database', 'getInstance')) {
            $this->db = Database::getInstance()->getConnection();
        } else {
            // اگر getInstance وجود ندارد، از سازنده معمولی استفاده می‌کنیم
            $this->db = new Database();
        }
    }
    
    /**
     * اجرای کوئری با پارامترها (سازگار با PDO و mysqli)
     * 
     * @param string $query کوئری SQL
     * @param array $params پارامترهای کوئری
     * @return array|bool نتیجه کوئری
     */
    private function executeQuery($query, $params = []) {
        try {
            if ($this->db instanceof PDO) {
                // اجرا با PDO
                $stmt = $this->db->prepare($query);
                $stmt->execute($params);
                
                // بررسی نوع کوئری
                if (stripos($query, 'SELECT') === 0) {
                    return $stmt->fetchAll(PDO::FETCH_ASSOC);
                } else {
                    return $stmt->rowCount() > 0;
                }
            } elseif ($this->db instanceof mysqli) {
                // اجرا با mysqli
                $stmt = $this->db->prepare($query);
                
                if (!empty($params)) {
                    // تعیین نوع پارامترها
                    $types = '';
                    foreach ($params as $param) {
                        if (is_int($param)) {
                            $types .= 'i';
                        } elseif (is_float($param)) {
                            $types .= 'd';
                        } elseif (is_string($param)) {
                            $types .= 's';
                        } else {
                            $types .= 's'; // پیش‌فرض رشته
                        }
                    }
                    
                    // اضافه کردن types به ابتدای آرایه پارامترها
                    $bindParams = array_merge([$types], $params);
                    
                    // استفاده از call_user_func_array برای پاس دادن پارامترها به bind_param
                    call_user_func_array([$stmt, 'bind_param'], $this->refValues($bindParams));
                }
                
                $stmt->execute();
                
                // بررسی نوع کوئری
                if (stripos($query, 'SELECT') === 0) {
                    $result = $stmt->get_result();
                    return $result->fetch_all(MYSQLI_ASSOC);
                } else {
                    return $stmt->affected_rows > 0;
                }
            } else {
                // اگر نوع اتصال نامشخص است، از متد query استفاده می‌کنیم
                return $this->db->query($query, $params);
            }
        } catch (Exception $e) {
            error_log("Error in executeQuery: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * تبدیل آرایه به آرایه‌ای از ارجاعات (references)
     * مورد نیاز برای bind_param در mysqli
     * 
     * @param array $arr آرایه ورودی
     * @return array آرایه خروجی با ارجاعات
     */
    private function refValues($arr) {
        $refs = [];
        foreach ($arr as $key => $value) {
            $refs[$key] = &$arr[$key];
        }
        return $refs;
    }
    
    /**
     * شروع تراکنش
     * 
     * @return bool نتیجه عملیات
     */
    private function beginTransaction() {
        try {
            if ($this->db instanceof PDO) {
                return $this->db->beginTransaction();
            } elseif ($this->db instanceof mysqli) {
                return $this->db->begin_transaction();
            } elseif (method_exists($this->db, 'beginTransaction')) {
                return $this->db->beginTransaction();
            }
            return false;
        } catch (Exception $e) {
            error_log("Error in beginTransaction: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * تایید تراکنش
     * 
     * @return bool نتیجه عملیات
     */
    private function commit() {
        try {
            if ($this->db instanceof PDO) {
                return $this->db->commit();
            } elseif ($this->db instanceof mysqli) {
                return $this->db->commit();
            } elseif (method_exists($this->db, 'commit')) {
                return $this->db->commit();
            }
            return false;
        } catch (Exception $e) {
            error_log("Error in commit: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * بازگشت تراکنش
     * 
     * @return bool نتیجه عملیات
     */
    private function rollback() {
        try {
            if ($this->db instanceof PDO) {
                return $this->db->rollBack();
            } elseif ($this->db instanceof mysqli) {
                return $this->db->rollback();
            } elseif (method_exists($this->db, 'rollback')) {
                return $this->db->rollback();
            }
            return false;
        } catch (Exception $e) {
            error_log("Error in rollback: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * دریافت آخرین شناسه درج شده
     * 
     * @return int آخرین شناسه درج شده
     */
    private function lastInsertId() {
        try {
            if ($this->db instanceof PDO) {
                return $this->db->lastInsertId();
            } elseif ($this->db instanceof mysqli) {
                return $this->db->insert_id;
            } elseif (method_exists($this->db, 'lastInsertId')) {
                return $this->db->lastInsertId();
            }
            return 0;
        } catch (Exception $e) {
            error_log("Error in lastInsertId: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * دریافت تمام انواع نگهداری
     * 
     * @return array لیست تمام انواع نگهداری
     */
    public function getAllTypes() {
        try {
            $query = "SELECT * FROM maintenance_types ORDER BY name";
            return $this->executeQuery($query);
        } catch (Exception $e) {
            error_log("Error in getAllTypes: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * دریافت انواع نگهداری با صفحه‌بندی
     * 
     * @param int $page شماره صفحه
     * @param int $perPage تعداد آیتم در هر صفحه
     * @return array آرایه‌ای شامل انواع نگهداری و اطلاعات صفحه‌بندی
     */
    public function getTypesPaginated($page = 1, $perPage = 10) {
        try {
            // محاسبه تعداد کل رکوردها
            $countQuery = "SELECT COUNT(*) as total FROM maintenance_types";
            $countResult = $this->executeQuery($countQuery);
            $totalRecords = $countResult[0]['total'] ?? 0;
            
            // محاسبه تعداد کل صفحات
            $totalPages = ceil($totalRecords / $perPage);
            
            // تصحیح شماره صفحه
            $page = max(1, min($page, max(1, $totalPages)));
            
            // محاسبه آفست
            $offset = ($page - 1) * $perPage;
            
            // تبدیل به عدد صحیح برای اطمینان
            $perPage = (int)$perPage;
            $offset = (int)$offset;
            
            $query = "SELECT mt.*, 
                      (SELECT COUNT(*) FROM maintenance_schedules ms WHERE ms.maintenance_type_id = mt.id) as schedule_count
                      FROM maintenance_types mt
                      ORDER BY mt.name
                      LIMIT $perPage OFFSET $offset";
            
            $types = $this->executeQuery($query);
            
            return [
                'types' => $types,
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
            error_log("Error in getTypesPaginated: " . $e->getMessage());
            return [
                'types' => [],
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
     * دریافت یک نوع نگهداری با شناسه مشخص
     * 
     * @param int $id شناسه نوع نگهداری
     * @return array|null نوع نگهداری یا null در صورت عدم وجود
     */
    public function getTypeById($id) {
        try {
            $query = "SELECT mt.*,
                      (SELECT COUNT(*) FROM maintenance_schedules ms WHERE ms.maintenance_type_id = mt.id) as schedule_count
                      FROM maintenance_types mt
                      WHERE mt.id = ?";
            $result = $this->executeQuery($query, [$id]);
            return $result ? $result[0] : null;
        } catch (Exception $e) {
            error_log("Error in getTypeById: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * دریافت نوع نگهداری با نام مشخص
     * 
     * @param string $name نام نوع نگهداری
     * @return array|null نوع نگهداری یا null در صورت عدم وجود
     */
    public function getTypeByName($name) {
        try {
            $query = "SELECT * FROM maintenance_types WHERE name = ?";
            $result = $this->executeQuery($query, [$name]);
            return $result ? $result[0] : null;
        } catch (Exception $e) {
            error_log("Error in getTypeByName: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * بررسی وجود نوع نگهداری با نام مشخص
     * 
     * @param string $name نام نوع نگهداری
     * @param int|null $excludeId شناسه نوع نگهداری که باید از بررسی مستثنی شود (برای به‌روزرسانی)
     * @return bool آیا نوع نگهداری با این نام وجود دارد
     */
    public function typeNameExists($name, $excludeId = null) {
        try {
            $params = [$name];
            $query = "SELECT COUNT(*) as count FROM maintenance_types WHERE name = ?";
            
            if ($excludeId !== null) {
                $query .= " AND id != ?";
                $params[] = $excludeId;
            }
            
            $result = $this->executeQuery($query, $params);
            return ($result[0]['count'] ?? 0) > 0;
        } catch (Exception $e) {
            error_log("Error in typeNameExists: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * ایجاد نوع نگهداری جدید
     * 
     * @param array|string $nameOrData نام نوع نگهداری یا آرایه داده‌ها
     * @param string|null $description توضیحات نوع نگهداری (اختیاری اگر پارامتر اول آرایه باشد)
     * @param int|null $intervalDays فاصله زمانی نگهداری (روز) (اختیاری اگر پارامتر اول آرایه باشد)
     * @param array $additionalData داده‌های اضافی (اختیاری)
     * @return int|bool شناسه نوع نگهداری جدید یا false در صورت خطا
     */
    public function createType($nameOrData, $description = null, $intervalDays = null, $additionalData = []) {
        try {
            // بررسی نوع پارامتر اول
            if (is_array($nameOrData)) {
                $data = $nameOrData;
                $name = $data['name'] ?? '';
                $description = $data['description'] ?? '';
                $intervalDays = $data['interval_days'] ?? 0;
                $additionalData = [
                    'checklist' => $data['checklist'] ?? null,
                    'is_required' => isset($data['is_required']) ? ($data['is_required'] ? 1 : 0) : 0,
                    'category' => $data['category'] ?? null
                ];
            } else {
                $name = $nameOrData;
            }
            
            // بررسی تکراری نبودن نام نوع نگهداری
            if ($this->typeNameExists($name)) {
                return false;
            }
            
            // اعتبارسنجی فاصله زمانی
            $intervalDays = max(1, intval($intervalDays));
            
            // استخراج داده‌های اضافی
            $checklist = $additionalData['checklist'] ?? null;
            $isRequired = isset($additionalData['is_required']) ? ($additionalData['is_required'] ? 1 : 0) : 0;
            $category = $additionalData['category'] ?? null;
            
            $query = "INSERT INTO maintenance_types (
                        name, description, interval_days, checklist, is_required, category, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())";
            
            $result = $this->executeQuery($query, [
                $name, $description, $intervalDays, $checklist, $isRequired, $category
            ]);
            
            if ($result) {
                return $this->lastInsertId();
            }
            
            return false;
        } catch (Exception $e) {
            error_log("Error in createType: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * به‌روزرسانی نوع نگهداری
     * 
     * @param int $id شناسه نوع نگهداری
     * @param array|string $nameOrData نام جدید نوع نگهداری یا آرایه داده‌ها
     * @param string|null $description توضیحات جدید نوع نگهداری (اختیاری اگر پارامتر دوم آرایه باشد)
     * @param int|null $intervalDays فاصله زمانی جدید نگهداری (روز) (اختیاری اگر پارامتر دوم آرایه باشد)
     * @param array $additionalData داده‌های اضافی (اختیاری)
     * @return bool نتیجه عملیات
     */
    public function updateType($id, $nameOrData, $description = null, $intervalDays = null, $additionalData = []) {
        try {
            // بررسی نوع پارامتر دوم
            if (is_array($nameOrData)) {
                $data = $nameOrData;
                $name = $data['name'] ?? '';
                $description = $data['description'] ?? '';
                $intervalDays = $data['interval_days'] ?? 0;
                $additionalData = [
                    'checklist' => $data['checklist'] ?? null,
                    'is_required' => isset($data['is_required']) ? ($data['is_required'] ? 1 : 0) : null,
                    'category' => $data['category'] ?? null
                ];
            } else {
                $name = $nameOrData;
            }
            
            // بررسی تکراری نبودن نام نوع نگهداری (به جز خود این نوع نگهداری)
            if ($this->typeNameExists($name, $id)) {
                return false;
            }
            
            // اعتبارسنجی فاصله زمانی
            $intervalDays = max(1, intval($intervalDays));
            
            // استخراج داده‌های اضافی
            $checklist = $additionalData['checklist'] ?? null;
            $isRequired = isset($additionalData['is_required']) ? ($additionalData['is_required'] ? 1 : 0) : null;
            $category = $additionalData['category'] ?? null;
            
            // ساخت پارامترها و بخش‌های کوئری برای فیلدهای اختیاری
            $updateFields = [
                "name = ?", 
                "description = ?", 
                "interval_days = ?",
                "updated_at = NOW()"
            ];
            $params = [$name, $description, $intervalDays];
            
            if ($checklist !== null) {
                $updateFields[] = "checklist = ?";
                $params[] = $checklist;
            }
            
            if ($isRequired !== null) {
                $updateFields[] = "is_required = ?";
                $params[] = $isRequired;
            }
            
            if ($category !== null) {
                $updateFields[] = "category = ?";
                $params[] = $category;
            }
            
            // افزودن شناسه به پارامترها
            $params[] = $id;
            
            $query = "UPDATE maintenance_types SET " . implode(", ", $updateFields) . " WHERE id = ?";
            return $this->executeQuery($query, $params);
        } catch (Exception $e) {
            error_log("Error in updateType: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * حذف نوع نگهداری
     * 
     * @param int $id شناسه نوع نگهداری
     * @return bool نتیجه عملیات
     */
    public function deleteType($id) {
        try {
            // بررسی وجود برنامه‌های نگهداری وابسته به این نوع
            $checkQuery = "SELECT COUNT(*) as count FROM maintenance_schedules WHERE maintenance_type_id = ?";
            $checkResult = $this->executeQuery($checkQuery, [$id]);
            
            if (($checkResult[0]['count'] ?? 0) > 0) {
                // اگر برنامه‌های نگهداری وابسته وجود دارند، حذف انجام نمی‌شود
                return false;
            }
            
            // بررسی وجود سوابق نگهداری وابسته به این نوع
            $checkQuery2 = "SELECT COUNT(*) as count FROM maintenance_logs WHERE maintenance_type_id = ?";
            $checkResult2 = $this->executeQuery($checkQuery2, [$id]);
            
            if (($checkResult2[0]['count'] ?? 0) > 0) {
                // اگر سوابق نگهداری وابسته وجود دارند، حذف انجام نمی‌شود
                return false;
            }
            
            $query = "DELETE FROM maintenance_types WHERE id = ?";
            return $this->executeQuery($query, [$id]);
        } catch (Exception $e) {
            error_log("Error in deleteType: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * دریافت برنامه‌های نگهداری مرتبط با یک نوع نگهداری
     * 
     * @param int $typeId شناسه نوع نگهداری
     * @return array لیست برنامه‌های نگهداری
     */
    public function getSchedulesByTypeId($typeId) {
        try {
            $query = "SELECT ms.*, a.asset_tag, am.name as model_name, ac.name as category_name
                      FROM maintenance_schedules ms
                      JOIN assets a ON ms.asset_id = a.id
                      JOIN asset_models am ON a.model_id = am.id
                      JOIN asset_categories ac ON am.category_id = ac.id
                      WHERE ms.maintenance_type_id = ?
                      ORDER BY ms.next_maintenance_date";
            return $this->executeQuery($query, [$typeId]);
        } catch (Exception $e) {
            error_log("Error in getSchedulesByTypeId: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * دریافت سوابق نگهداری مرتبط با یک نوع نگهداری
     * 
     * @param int $typeId شناسه نوع نگهداری
     * @param int $limit محدودیت تعداد نتایج (اختیاری)
     * @return array لیست سوابق نگهداری
     */
    public function getLogsByTypeId($typeId, $limit = null) {
        try {
            $params = [$typeId];
            $limitClause = "";
            
            if ($limit !== null) {
                $limit = (int)$limit;
                $limitClause = " LIMIT $limit";
            }
            
            $query = "SELECT ml.*, a.asset_tag, am.name as model_name, u.fullname as performed_by
                      FROM maintenance_logs ml
                      JOIN assets a ON ml.asset_id = a.id
                      JOIN asset_models am ON a.model_id = am.id
                      LEFT JOIN users u ON ml.user_id = u.id
                      WHERE ml.maintenance_type_id = ?
                      ORDER BY ml.performed_at DESC" . $limitClause;
            return $this->executeQuery($query, $params);
        } catch (Exception $e) {
            error_log("Error in getLogsByTypeId: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * دریافت انواع نگهداری مرتبط با یک دسته‌بندی تجهیز
     * 
     * @param int $categoryId شناسه دسته‌بندی تجهیز
     * @return array لیست انواع نگهداری
     */
    public function getTypesByAssetCategory($categoryId) {
        try {
            $query = "SELECT mt.* 
                      FROM maintenance_types mt
                      WHERE mt.category = ? OR mt.category IS NULL
                      ORDER BY mt.name";
            return $this->executeQuery($query, [$categoryId]);
        } catch (Exception $e) {
            error_log("Error in getTypesByAssetCategory: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * دریافت انواع نگهداری اجباری
     * 
     * @return array لیست انواع نگهداری اجباری
     */
    public function getRequiredTypes() {
        try {
            $query = "SELECT * FROM maintenance_types WHERE is_required = 1 ORDER BY name";
            return $this->executeQuery($query);
        } catch (Exception $e) {
            error_log("Error in getRequiredTypes: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * جستجوی انواع نگهداری
     * 
     * @param string $searchTerm عبارت جستجو
     * @param string|null $category دسته‌بندی (اختیاری)
     * @return array نتایج جستجو
     */
    public function searchTypes($searchTerm, $category = null) {
        try {
            $params = [];
            $conditions = [];
            
            if (!empty($searchTerm)) {
                $conditions[] = "(name LIKE ? OR description LIKE ?)";
                $searchParam = '%' . $searchTerm . '%';
                $params = array_merge($params, [$searchParam, $searchParam]);
            }
            
            if (!empty($category)) {
                $conditions[] = "(category = ? OR category IS NULL)";
                $params[] = $category;
            }
            
            $whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
            
            $query = "SELECT * FROM maintenance_types $whereClause ORDER BY name";
            return $this->executeQuery($query, $params);
        } catch (Exception $e) {
            error_log("Error in searchTypes: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * دریافت دسته‌بندی‌های منحصر به فرد برای انواع نگهداری
     * 
     * @return array لیست دسته‌بندی‌ها
     */
    public function getUniqueCategories() {
        try {
            $query = "SELECT DISTINCT category FROM maintenance_types WHERE category IS NOT NULL ORDER BY category";
            return $this->executeQuery($query);
        } catch (Exception $e) {
            error_log("Error in getUniqueCategories: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * دریافت آمار نگهداری بر اساس نوع
     * 
     * @param int|null $days تعداد روزهای اخیر (اختیاری)
     * @return array آمار نگهداری
     */
    public function getMaintenanceStatsByType($days = null) {
        try {
            $dateCondition = "";
            $params = [];
            
            if ($days !== null) {
                $dateCondition = "WHERE ml.performed_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)";
                $params[] = $days;
            }
            
            $query = "SELECT mt.id, mt.name, 
                      COUNT(ml.id) as completed_count,
                      (SELECT COUNT(*) FROM maintenance_schedules ms WHERE ms.maintenance_type_id = mt.id) as scheduled_count,
                      (SELECT COUNT(*) FROM maintenance_schedules ms WHERE ms.maintenance_type_id = mt.id AND ms.next_maintenance_date < CURDATE()) as overdue_count
                      FROM maintenance_types mt
                      LEFT JOIN maintenance_logs ml ON mt.id = ml.maintenance_type_id $dateCondition
                      GROUP BY mt.id, mt.name
                      ORDER BY completed_count DESC";
            return $this->executeQuery($query, $params);
        } catch (Exception $e) {
            error_log("Error in getMaintenanceStatsByType: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * دریافت آمار نگهداری بر اساس دسته‌بندی تجهیز
     * 
     * @param int|null $days تعداد روزهای اخیر (اختیاری)
     * @return array آمار نگهداری
     */
    public function getMaintenanceStatsByAssetCategory($days = null) {
        try {
            $dateCondition = "";
            $params = [];
            
            if ($days !== null) {
                $dateCondition = "WHERE ml.performed_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)";
                $params[] = $days;
            }
            
            $query = "SELECT ac.id, ac.name, 
                      COUNT(ml.id) as completed_count,
                      (SELECT COUNT(*) FROM maintenance_schedules ms 
                       JOIN assets a ON ms.asset_id = a.id
                       JOIN asset_models am ON a.model_id = am.id
                       WHERE am.category_id = ac.id) as scheduled_count,
                      (SELECT COUNT(*) FROM maintenance_schedules ms 
                       JOIN assets a ON ms.asset_id = a.id
                       JOIN asset_models am ON a.model_id = am.id
                       WHERE am.category_id = ac.id AND ms.next_maintenance_date < CURDATE()) as overdue_count
                      FROM asset_categories ac
                      LEFT JOIN asset_models am ON ac.id = am.category_id
                      LEFT JOIN assets a ON am.id = a.model_id
                      LEFT JOIN maintenance_logs ml ON a.id = ml.asset_id $dateCondition
                      GROUP BY ac.id, ac.name
                      ORDER BY completed_count DESC";
            return $this->executeQuery($query, $params);
        } catch (Exception $e) {
            error_log("Error in getMaintenanceStatsByAssetCategory: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * دریافت روند نگهداری در طول زمان
     * 
     * @param int $months تعداد ماه‌های اخیر
     * @return array روند نگهداری
     */
    public function getMaintenanceTrend($months = 12) {
        try {
            $months = (int)$months;
            $query = "SELECT DATE_FORMAT(performed_at, '%Y-%m') as month, 
                      COUNT(*) as count
                      FROM maintenance_logs
                      WHERE performed_at >= DATE_SUB(CURDATE(), INTERVAL $months MONTH)
                      GROUP BY DATE_FORMAT(performed_at, '%Y-%m')
                      ORDER BY month";
            return $this->executeQuery($query);
        } catch (Exception $e) {
            error_log("Error in getMaintenanceTrend: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * دریافت آمار نگهداری بر اساس کاربر
     * 
     * @param int|null $days تعداد روزهای اخیر (اختیاری)
     * @param int $limit محدودیت تعداد نتایج (اختیاری)
     * @return array آمار نگهداری
     */
    public function getMaintenanceStatsByUser($days = null, $limit = 10) {
        try {
            $dateCondition = "";
            $params = [];
            
            if ($days !== null) {
                $dateCondition = "WHERE ml.performed_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)";
                $params[] = $days;
            }
            
            $limit = (int)$limit;
            
            $query = "SELECT u.id, u.fullname, COUNT(ml.id) as completed_count
                      FROM users u
                      JOIN maintenance_logs ml ON u.id = ml.user_id
                      $dateCondition
                      GROUP BY u.id, u.fullname
                      ORDER BY completed_count DESC
                      LIMIT $limit";
            return $this->executeQuery($query, $params);
        } catch (Exception $e) {
            error_log("Error in getMaintenanceStatsByUser: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * دریافت زمان متوسط بین نگهداری‌ها برای هر نوع
     * 
     * @return array زمان متوسط بین نگهداری‌ها
     */
    public function getAverageTimeBetweenMaintenances() {
        try {
            $query = "SELECT mt.id, mt.name, mt.interval_days as expected_interval,
                      AVG(DATEDIFF(ml2.performed_at, ml1.performed_at)) as actual_interval
                      FROM maintenance_types mt
                      JOIN maintenance_logs ml1 ON mt.id = ml1.maintenance_type_id
                      JOIN maintenance_logs ml2 ON ml1.asset_id = ml2.asset_id AND ml1.maintenance_type_id = ml2.maintenance_type_id
                      WHERE ml2.performed_at > ml1.performed_at
                      GROUP BY mt.id, mt.name, mt.interval_days
                      ORDER BY mt.name";
            return $this->executeQuery($query);
        } catch (Exception $e) {
            error_log("Error in getAverageTimeBetweenMaintenances: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * دریافت آمار نگهداری‌های انجام شده و برنامه‌ریزی شده
     * 
     * @return array آمار نگهداری
     */
    public function getMaintenanceComplianceStats() {
        try {
            $query = "SELECT 
                        (SELECT COUNT(*) FROM maintenance_schedules WHERE next_maintenance_date < CURDATE()) as overdue,
                        (SELECT COUNT(*) FROM maintenance_schedules WHERE next_maintenance_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)) as due_this_week,
                        (SELECT COUNT(*) FROM maintenance_schedules WHERE next_maintenance_date BETWEEN DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)) as due_this_month,
                        (SELECT COUNT(*) FROM maintenance_logs WHERE performed_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) as completed_last_month";
            $result = $this->executeQuery($query);
            return $result ? $result[0] : [
                'overdue' => 0,
                'due_this_week' => 0,
                'due_this_month' => 0,
                'completed_last_month' => 0
            ];
        } catch (Exception $e) {
            error_log("Error in getMaintenanceComplianceStats: " . $e->getMessage());
            return [
                'overdue' => 0,
                'due_this_week' => 0,
                'due_this_month' => 0,
                'completed_last_month' => 0
            ];
        }
    }
    
    /**
     * ایجاد چندین نوع نگهداری به صورت یکجا
     * 
     * @param array $types آرایه‌ای از انواع نگهداری (هر عنصر باید name، description و interval_days داشته باشد)
     * @return array آرایه‌ای از شناسه‌های انواع نگهداری ایجاد شده
     */
    public function createMultipleTypes($types) {
        try {
            if (empty($types) || !is_array($types)) {
                return [];
            }
            
            $this->beginTransaction();
            $createdIds = [];
            
            foreach ($types as $type) {
                if (!isset($type['name']) || !isset($type['description']) || !isset($type['interval_days'])) {
                    continue;
                }
                
                $additionalData = [
                    'checklist' => $type['checklist'] ?? null,
                    'is_required' => $type['is_required'] ?? 0,
                    'category' => $type['category'] ?? null
                ];
                
                $id = $this->createType($type['name'], $type['description'], $type['interval_days'], $additionalData);
                
                if ($id) {
                    $createdIds[] = $id;
                }
            }
            
            $this->commit();
            return $createdIds;
        } catch (Exception $e) {
            $this->rollback();
            error_log("Error in createMultipleTypes: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * دریافت انواع نگهداری با بیشترین تعداد سوابق
     * 
     * @param int $limit تعداد نتایج
     * @return array لیست انواع نگهداری
     */
    public function getMostUsedTypes($limit = 5) {
        try {
            $limit = (int)$limit;
            $query = "SELECT mt.id, mt.name, COUNT(ml.id) as log_count
                      FROM maintenance_types mt
                      JOIN maintenance_logs ml ON mt.id = ml.maintenance_type_id
                      GROUP BY mt.id, mt.name
                      ORDER BY log_count DESC
                      LIMIT $limit";
            return $this->executeQuery($query);
        } catch (Exception $e) {
            error_log("Error in getMostUsedTypes: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * دریافت انواع نگهداری با بیشترین تعداد برنامه‌های معوق
     * 
     * @param int $limit تعداد نتایج
     * @return array لیست انواع نگهداری
     */
    public function getTypesWithMostOverdueSchedules($limit = 5) {
        try {
            $limit = (int)$limit;
            $query = "SELECT mt.id, mt.name, COUNT(ms.id) as overdue_count
                      FROM maintenance_types mt
                      JOIN maintenance_schedules ms ON mt.id = ms.maintenance_type_id
                      WHERE ms.next_maintenance_date < CURDATE()
                      GROUP BY mt.id, mt.name
                      ORDER BY overdue_count DESC
                      LIMIT $limit";
            return $this->executeQuery($query);
        } catch (Exception $e) {
            error_log("Error in getTypesWithMostOverdueSchedules: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * دریافت انواع نگهداری با فیلتر و صفحه‌بندی
     * 
     * @param array $filters فیلترها
     * @param int $page شماره صفحه
     * @param int $perPage تعداد آیتم در هر صفحه
     * @param string $sortBy ستون مرتب‌سازی
     * @param string $order ترتیب مرتب‌سازی
     * @return array آرایه‌ای شامل انواع نگهداری و اطلاعات صفحه‌بندی
     */
    public function getFilteredTypes($filters, $page = 1, $perPage = 10, $sortBy = 'name', $order = 'asc') {
        try {
            // اعتبارسنجی ستون مرتب‌سازی
            $validSortColumns = ['id', 'name', 'interval_days', 'category', 'is_required', 'created_at', 'updated_at'];
            if (!in_array($sortBy, $validSortColumns)) {
                $sortBy = 'name'; // مقدار پیش‌فرض
            }

            // اعتبارسنجی ترتیب مرتب‌سازی
            $order = strtolower($order) === 'desc' ? 'DESC' : 'ASC';

            // ساخت شرط WHERE
            $whereConditions = [];
            $params = [];

            if (!empty($filters['name'])) {
                $whereConditions[] = "mt.name LIKE ?";
                $params[] = '%' . $filters['name'] . '%';
            }

            if (!empty($filters['category'])) {
                $whereConditions[] = "mt.category = ?";
                $params[] = $filters['category'];
            }

            if (isset($filters['is_required']) && $filters['is_required'] !== '') {
                $whereConditions[] = "mt.is_required = ?";
                $params[] = $filters['is_required'];
            }

            if (!empty($filters['interval_min'])) {
                $whereConditions[] = "mt.interval_days >= ?";
                $params[] = (int)$filters['interval_min'];
            }

            if (!empty($filters['interval_max'])) {
                $whereConditions[] = "mt.interval_days <= ?";
                $params[] = (int)$filters['interval_max'];
            }

            // ساخت بخش WHERE کوئری
            $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

            // محاسبه تعداد کل رکوردها
            $countQuery = "SELECT COUNT(*) as total FROM maintenance_types mt $whereClause";
            $countResult = $this->executeQuery($countQuery, $params);
            $totalRecords = $countResult[0]['total'] ?? 0;
            
            // محاسبه تعداد کل صفحات
            $totalPages = ceil($totalRecords / $perPage);
            
            // تصحیح شماره صفحه
            $page = max(1, min($page, max(1, $totalPages)));
            
            // محاسبه آفست
            $offset = ($page - 1) * $perPage;
            
            // تبدیل به عدد صحیح برای اطمینان
            $perPage = (int)$perPage;
            $offset = (int)$offset;
            
            // ساخت کوئری کامل
            $query = "SELECT mt.*, 
                      (SELECT COUNT(*) FROM maintenance_schedules ms WHERE ms.maintenance_type_id = mt.id) as schedule_count,
                      (SELECT COUNT(*) FROM maintenance_logs ml WHERE ml.maintenance_type_id = mt.id) as log_count
                      FROM maintenance_types mt
                      $whereClause
                      ORDER BY $sortBy $order
                      LIMIT $perPage OFFSET $offset";
            
            $types = $this->executeQuery($query, $params);
            
            return [
                'types' => $types,
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
            error_log("Error in getFilteredTypes: " . $e->getMessage());
            return [
                'types' => [],
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
     * دریافت انواع نگهداری برای یک تجهیز خاص
     * 
     * @param int $assetId شناسه تجهیز
     * @return array لیست انواع نگهداری
     */
    public function getTypesForAsset($assetId) {
        try {
            $query = "SELECT mt.*, 
                      (SELECT COUNT(*) FROM maintenance_logs ml WHERE ml.maintenance_type_id = mt.id AND ml.asset_id = ?) as log_count,
                      (SELECT MAX(ml.performed_at) FROM maintenance_logs ml WHERE ml.maintenance_type_id = mt.id AND ml.asset_id = ?) as last_maintenance,
                      (SELECT ms.next_maintenance_date FROM maintenance_schedules ms WHERE ms.maintenance_type_id = mt.id AND ms.asset_id = ?) as next_maintenance_date
                      FROM maintenance_types mt
                      LEFT JOIN assets a ON a.id = ?
                      LEFT JOIN asset_models am ON a.model_id = am.id
                      LEFT JOIN asset_categories ac ON am.category_id = ac.id
                      WHERE mt.category IS NULL OR mt.category = ac.id
                      ORDER BY mt.name";
            return $this->executeQuery($query, [$assetId, $assetId, $assetId, $assetId]);
        } catch (Exception $e) {
            error_log("Error in getTypesForAsset: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * ایمپورت انواع نگهداری از فایل CSV
     * 
     * @param string $filePath مسیر فایل CSV
     * @return array نتیجه عملیات (تعداد رکوردهای موفق و ناموفق)
     */
    public function importTypesFromCSV($filePath) {
        try {
            if (!file_exists($filePath)) {
                return [
                    'success' => false,
                    'message' => 'فایل یافت نشد.',
                    'imported' => 0,
                    'failed' => 0
                ];
            }
            
            $file = fopen($filePath, 'r');
            if (!$file) {
                return [
                    'success' => false,
                    'message' => 'خطا در باز کردن فایل.',
                    'imported' => 0,
                    'failed' => 0
                ];
            }
            
            // خواندن سطر هدر
            $header = fgetcsv($file);
            
            // بررسی ستون‌های مورد نیاز
            $requiredColumns = ['name', 'description', 'interval_days'];
            $columnIndexes = [];
            
            foreach ($requiredColumns as $column) {
                $index = array_search($column, $header);
                if ($index === false) {
                    fclose($file);
                    return [
                        'success' => false,
                        'message' => "ستون '$column' در فایل CSV یافت نشد.",
                        'imported' => 0,
                        'failed' => 0
                    ];
                }
                $columnIndexes[$column] = $index;
            }
            
            // ستون‌های اختیاری
            $optionalColumns = ['checklist', 'is_required', 'category'];
            foreach ($optionalColumns as $column) {
                $index = array_search($column, $header);
                $columnIndexes[$column] = $index !== false ? $index : null;
            }
            
            $this->beginTransaction();
            $imported = 0;
            $failed = 0;
            
            // پردازش داده‌ها
            while (($data = fgetcsv($file)) !== false) {
                $name = $data[$columnIndexes['name']] ?? '';
                $description = $data[$columnIndexes['description']] ?? '';
                $intervalDays = $data[$columnIndexes['interval_days']] ?? 0;
                
                if (empty($name) || empty($description) || empty($intervalDays)) {
                    $failed++;
                    continue;
                }
                
                $additionalData = [];
                
                if ($columnIndexes['checklist'] !== null && isset($data[$columnIndexes['checklist']])) {
                    $additionalData['checklist'] = $data[$columnIndexes['checklist']];
                }
                
                if ($columnIndexes['is_required'] !== null && isset($data[$columnIndexes['is_required']])) {
                    $additionalData['is_required'] = filter_var($data[$columnIndexes['is_required']], FILTER_VALIDATE_BOOLEAN);
                }
                
                if ($columnIndexes['category'] !== null && isset($data[$columnIndexes['category']])) {
                    $additionalData['category'] = $data[$columnIndexes['category']];
                }
                
                $result = $this->createType($name, $description, $intervalDays, $additionalData);
                
                if ($result) {
                    $imported++;
                } else {
                    $failed++;
                }
            }
            
            fclose($file);
            
            if ($imported > 0) {
                $this->commit();
                return [
                    'success' => true,
                    'message' => "$imported نوع نگهداری با موفقیت وارد شد. $failed مورد ناموفق بود.",
                    'imported' => $imported,
                    'failed' => $failed
                ];
            } else {
                $this->rollback();
                return [
                    'success' => false,
                    'message' => "هیچ نوع نگهداری وارد نشد. $failed مورد ناموفق بود.",
                    'imported' => 0,
                    'failed' => $failed
                ];
            }
        } catch (Exception $e) {
            if (isset($file)) {
                fclose($file);
            }
            $this->rollback();
            error_log("Error in importTypesFromCSV: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در وارد کردن داده‌ها: ' . $e->getMessage(),
                'imported' => 0,
                'failed' => 0
            ];
        }
    }
    
    /**
     * اکسپورت انواع نگهداری به فایل CSV
     * 
     * @param string $filePath مسیر فایل CSV
     * @param array $filters فیلترها (اختیاری)
     * @return bool نتیجه عملیات
     */
    public function exportTypesToCSV($filePath, $filters = []) {
        try {
            // دریافت انواع نگهداری با فیلترها
            $whereConditions = [];
            $params = [];

            if (!empty($filters['name'])) {
                $whereConditions[] = "name LIKE ?";
                $params[] = '%' . $filters['name'] . '%';
            }

            if (!empty($filters['category'])) {
                $whereConditions[] = "category = ?";
                $params[] = $filters['category'];
            }

            if (isset($filters['is_required']) && $filters['is_required'] !== '') {
                $whereConditions[] = "is_required = ?";
                $params[] = $filters['is_required'];
            }

            // ساخت بخش WHERE کوئری
            $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

            $query = "SELECT * FROM maintenance_types $whereClause ORDER BY name";
            $types = $this->executeQuery($query, $params);
            
            if (empty($types)) {
                return false;
            }
            
            // ایجاد فایل CSV
            $file = fopen($filePath, 'w');
            if (!$file) {
                return false;
            }
            
            // نوشتن سطر هدر
            fputcsv($file, ['id', 'name', 'description', 'interval_days', 'checklist', 'is_required', 'category', 'created_at', 'updated_at']);
            
            // نوشتن داده‌ها
            foreach ($types as $type) {
                fputcsv($file, [
                    $type['id'],
                    $type['name'],
                    $type['description'],
                    $type['interval_days'],
                    $type['checklist'],
                    $type['is_required'],
                    $type['category'],
                    $type['created_at'],
                    $type['updated_at']
                ]);
            }
            
            fclose($file);
            return true;
        } catch (Exception $e) {
            if (isset($file)) {
                fclose($file);
            }
            error_log("Error in exportTypesToCSV: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * کپی یک نوع نگهداری
     * 
     * @param int $id شناسه نوع نگهداری
     * @param string $newName نام جدید (اختیاری)
     * @return int|bool شناسه نوع نگهداری جدید یا false در صورت خطا
     */
    public function duplicateType($id, $newName = null) {
        try {
            // دریافت اطلاعات نوع نگهداری
            $type = $this->getTypeById($id);
            
            if (!$type) {
                return false;
            }
            
            // تعیین نام جدید
            $name = $newName ?? ($type['name'] . ' (کپی)');
            
            // بررسی تکراری نبودن نام
            if ($this->typeNameExists($name)) {
                $name .= ' ' . date('Y-m-d H:i:s');
            }
            
            // ایجاد نوع نگهداری جدید
            $additionalData = [
                'checklist' => $type['checklist'],
                'is_required' => $type['is_required'],
                'category' => $type['category']
            ];
            
            return $this->createType($name, $type['description'], $type['interval_days'], $additionalData);
        } catch (Exception $e) {
            error_log("Error in duplicateType: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * بررسی امکان حذف نوع نگهداری
     * 
     * @param int $id شناسه نوع نگهداری
     * @return array نتیجه بررسی
     */
    public function checkTypeDeletePossibility($id) {
        try {
            // بررسی وجود برنامه‌های نگهداری وابسته به این نوع
            $checkQuery = "SELECT COUNT(*) as count FROM maintenance_schedules WHERE maintenance_type_id = ?";
            $checkResult = $this->executeQuery($checkQuery, [$id]);
            $scheduleCount = $checkResult[0]['count'] ?? 0;
            
            // بررسی وجود سوابق نگهداری وابسته به این نوع
            $checkQuery2 = "SELECT COUNT(*) as count FROM maintenance_logs WHERE maintenance_type_id = ?";
            $checkResult2 = $this->executeQuery($checkQuery2, [$id]);
            $logCount = $checkResult2[0]['count'] ?? 0;
            
            return [
                'can_delete' => ($scheduleCount == 0 && $logCount == 0),
                'schedule_count' => $scheduleCount,
                'log_count' => $logCount,
                'message' => $scheduleCount > 0 ? 
                    "این نوع نگهداری دارای $scheduleCount برنامه نگهداری است و نمی‌تواند حذف شود." : 
                    ($logCount > 0 ? 
                        "این نوع نگهداری دارای $logCount سابقه نگهداری است و نمی‌تواند حذف شود." : 
                        "این نوع نگهداری می‌تواند حذف شود.")
            ];
        } catch (Exception $e) {
            error_log("Error in checkTypeDeletePossibility: " . $e->getMessage());
            return [
                'can_delete' => false,
                'schedule_count' => 0,
                'log_count' => 0,
                'message' => "خطا در بررسی امکان حذف: " . $e->getMessage()
            ];
        }
    }

    /**
     * دریافت چک‌لیست نگهداری برای یک نوع نگهداری
     * 
     * @param int $typeId شناسه نوع نگهداری
     * @return array|null چک‌لیست نگهداری
     */
    public function getMaintenanceChecklist($typeId) {
        try {
            $query = "SELECT checklist FROM maintenance_types WHERE id = ?";
            $result = $this->executeQuery($query, [$typeId]);
            
            if (!$result || empty($result[0]['checklist'])) {
                return null;
            }
            
            // اگر چک‌لیست به صورت JSON ذخیره شده باشد
            $checklist = $result[0]['checklist'];
            $decodedChecklist = json_decode($checklist, true);
            
            return $decodedChecklist ?: $checklist;
        } catch (Exception $e) {
            error_log("Error in getMaintenanceChecklist: " . $e->getMessage());
            return null;
        }
    }

    /**
     * دریافت آمار کلی انواع نگهداری
     * 
     * @return array آمار کلی
     */
    public function getMaintenanceTypesStats() {
        try {
            $query = "SELECT 
                        COUNT(*) as total_types,
                        (SELECT COUNT(*) FROM maintenance_types WHERE is_required = 1) as required_types,
                        AVG(interval_days) as avg_interval,
                        MIN(interval_days) as min_interval,
                        MAX(interval_days) as max_interval,
                        COUNT(DISTINCT category) as category_count
                    FROM maintenance_types";
            $result = $this->executeQuery($query);
            return $result ? $result[0] : [
                'total_types' => 0,
                'required_types' => 0,
                'avg_interval' => 0,
                'min_interval' => 0,
                'max_interval' => 0,
                'category_count' => 0
            ];
        } catch (Exception $e) {
            error_log("Error in getMaintenanceTypesStats: " . $e->getMessage());
            return [
                'total_types' => 0,
                'required_types' => 0,
                'avg_interval' => 0,
                'min_interval' => 0,
                'max_interval' => 0,
                'category_count' => 0
            ];
        }
    }

    /**
     * دریافت انواع نگهداری پرکاربرد بر اساس تجهیز‌های مختلف
     * 
     * @param int $limit تعداد نتایج
     * @return array لیست انواع نگهداری پرکاربرد
     */
    public function getTypesWithMostAssets($limit = 5) {
        try {
            $limit = (int)$limit;
            $query = "SELECT mt.id, mt.name, COUNT(DISTINCT ms.asset_id) as asset_count
                    FROM maintenance_types mt
                    JOIN maintenance_schedules ms ON mt.id = ms.maintenance_type_id
                    GROUP BY mt.id, mt.name
                    ORDER BY asset_count DESC
                    LIMIT $limit";
            return $this->executeQuery($query);
        } catch (Exception $e) {
            error_log("Error in getTypesWithMostAssets: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت انواع نگهداری با بیشترین هزینه
     * 
     * @param int $limit تعداد نتایج
     * @param int|null $days تعداد روزهای اخیر (اختیاری)
     * @return array لیست انواع نگهداری
     */
    public function getTypesWithHighestCost($limit = 5, $days = null) {
        try {
            $limit = (int)$limit;
            $dateCondition = "";
            $params = [];
            
            if ($days !== null) {
                $dateCondition = "WHERE ml.performed_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)";
                $params[] = $days;
            }
            
            $query = "SELECT mt.id, mt.name, SUM(ml.cost) as total_cost, COUNT(ml.id) as log_count
                    FROM maintenance_types mt
                    JOIN maintenance_logs ml ON mt.id = ml.maintenance_type_id
                    $dateCondition
                    GROUP BY mt.id, mt.name
                    ORDER BY total_cost DESC
                    LIMIT $limit";
            return $this->executeQuery($query, $params);
        } catch (Exception $e) {
            error_log("Error in getTypesWithHighestCost: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت انواع نگهداری با بیشترین زمان تکمیل
     * 
     * @param int $limit تعداد نتایج
     * @param int|null $days تعداد روزهای اخیر (اختیاری)
     * @return array لیست انواع نگهداری
     */
    public function getTypesWithLongestCompletionTime($limit = 5, $days = null) {
        try {
            $limit = (int)$limit;
            $dateCondition = "";
            $params = [];
            
            if ($days !== null) {
                $dateCondition = "WHERE ml.performed_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)";
                $params[] = $days;
            }
            
            $query = "SELECT mt.id, mt.name, AVG(ml.completion_time) as avg_completion_time, COUNT(ml.id) as log_count
                    FROM maintenance_types mt
                    JOIN maintenance_logs ml ON mt.id = ml.maintenance_type_id
                    $dateCondition
                    GROUP BY mt.id, mt.name
                    ORDER BY avg_completion_time DESC
                    LIMIT $limit";
            return $this->executeQuery($query, $params);
        } catch (Exception $e) {
            error_log("Error in getTypesWithLongestCompletionTime: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت انواع نگهداری بر اساس میزان انحراف از برنامه
     * 
     * @param int $limit تعداد نتایج
     * @return array لیست انواع نگهداری
     */
    public function getTypesByScheduleDeviation($limit = 5) {
        try {
            $limit = (int)$limit;
            $query = "SELECT 
                        mt.id, 
                        mt.name, 
                        AVG(DATEDIFF(ml.performed_at, ms.next_maintenance_date)) as avg_deviation,
                        COUNT(ml.id) as log_count
                    FROM maintenance_types mt
                    JOIN maintenance_schedules ms ON mt.id = ms.maintenance_type_id
                    JOIN maintenance_logs ml ON ms.asset_id = ml.asset_id AND ms.maintenance_type_id = ml.maintenance_type_id
                    WHERE ml.performed_at >= ms.last_maintenance_date
                    GROUP BY mt.id, mt.name
                    ORDER BY ABS(avg_deviation) DESC
                    LIMIT $limit";
            return $this->executeQuery($query);
        } catch (Exception $e) {
            error_log("Error in getTypesByScheduleDeviation: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت انواع نگهداری بر اساس نرخ موفقیت
     * 
     * @param int $limit تعداد نتایج
     * @return array لیست انواع نگهداری
     */
    public function getTypesBySuccessRate($limit = 5) {
        try {
            $limit = (int)$limit;
            $query = "SELECT 
                        mt.id, 
                        mt.name,
                        COUNT(ml.id) as total_logs,
                        SUM(CASE WHEN ml.status = 'completed' THEN 1 ELSE 0 END) as completed_logs,
                        (SUM(CASE WHEN ml.status = 'completed' THEN 1 ELSE 0 END) / COUNT(ml.id) * 100) as success_rate
                    FROM maintenance_types mt
                    JOIN maintenance_logs ml ON mt.id = ml.maintenance_type_id
                    GROUP BY mt.id, mt.name
                    HAVING total_logs > 0
                    ORDER BY success_rate DESC
                    LIMIT $limit";
            return $this->executeQuery($query);
        } catch (Exception $e) {
            error_log("Error in getTypesBySuccessRate: " . $e->getMessage());
            return [];
        }
    }

    /**
     * به‌روزرسانی چک‌لیست نگهداری
     * 
     * @param int $typeId شناسه نوع نگهداری
     * @param array|string $checklist چک‌لیست جدید
     * @return bool نتیجه عملیات
     */
    public function updateMaintenanceChecklist($typeId, $checklist) {
        try {
            // اگر چک‌لیست آرایه باشد، آن را به JSON تبدیل می‌کنیم
            if (is_array($checklist)) {
                $checklist = json_encode($checklist, JSON_UNESCAPED_UNICODE);
            }
            
            $query = "UPDATE maintenance_types SET checklist = ?, updated_at = NOW() WHERE id = ?";
            return $this->executeQuery($query, [$checklist, $typeId]);
        } catch (Exception $e) {
            error_log("Error in updateMaintenanceChecklist: " . $e->getMessage());
            return false;
        }
    }

    /**
     * دریافت انواع نگهداری بر اساس دسته‌بندی
     * 
     * @param string $category دسته‌بندی
     * @return array لیست انواع نگهداری
     */
    public function getTypesByCategory($category) {
        try {
            $query = "SELECT * FROM maintenance_types WHERE category = ? ORDER BY name";
            return $this->executeQuery($query, [$category]);
        } catch (Exception $e) {
            error_log("Error in getTypesByCategory: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت آمار انواع نگهداری بر اساس دسته‌بندی
     * 
     * @return array آمار انواع نگهداری
     */
    public function getTypeStatsByCategory() {
        try {
            $query = "SELECT 
                        IFNULL(category, 'بدون دسته‌بندی') as category,
                        COUNT(*) as type_count,
                        AVG(interval_days) as avg_interval,
                        SUM(is_required) as required_count
                    FROM maintenance_types
                    GROUP BY category
                    ORDER BY type_count DESC";
            return $this->executeQuery($query);
        } catch (Exception $e) {
            error_log("Error in getTypeStatsByCategory: " . $e->getMessage());
            return [];
        }
    }

    /**
     * تغییر دسته‌بندی برای چندین نوع نگهداری
     * 
     * @param array $typeIds آرایه‌ای از شناسه‌های انواع نگهداری
     * @param string $newCategory دسته‌بندی جدید
     * @return array نتیجه عملیات
     */
    public function bulkUpdateCategory($typeIds, $newCategory) {
        try {
            if (empty($typeIds) || !is_array($typeIds)) {
                return [
                    'success' => false,
                    'message' => 'هیچ نوع نگهداری انتخاب نشده است.',
                    'updated' => 0
                ];
            }
            
            $this->beginTransaction();
            $updated = 0;
            
            foreach ($typeIds as $typeId) {
                $query = "UPDATE maintenance_types SET category = ?, updated_at = NOW() WHERE id = ?";
                $result = $this->executeQuery($query, [$newCategory, $typeId]);
                
                if ($result) {
                    $updated++;
                }
            }
            
            if ($updated > 0) {
                $this->commit();
                return [
                    'success' => true,
                    'message' => "دسته‌بندی $updated نوع نگهداری با موفقیت به‌روزرسانی شد.",
                    'updated' => $updated
                ];
            } else {
                $this->rollback();
                return [
                    'success' => false,
                    'message' => 'هیچ نوع نگهداری به‌روزرسانی نشد.',
                    'updated' => 0
                ];
            }
        } catch (Exception $e) {
            $this->rollback();
            error_log("Error in bulkUpdateCategory: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در به‌روزرسانی دسته‌بندی: ' . $e->getMessage(),
                'updated' => 0
            ];
        }
    }

    /**
     * تغییر وضعیت اجباری بودن برای چندین نوع نگهداری
     * 
     * @param array $typeIds آرایه‌ای از شناسه‌های انواع نگهداری
     * @param bool $isRequired وضعیت اجباری بودن
     * @return array نتیجه عملیات
     */
    public function bulkUpdateRequiredStatus($typeIds, $isRequired) {
        try {
            if (empty($typeIds) || !is_array($typeIds)) {
                return [
                    'success' => false,
                    'message' => 'هیچ نوع نگهداری انتخاب نشده است.',
                    'updated' => 0
                ];
            }
            
            $this->beginTransaction();
            $updated = 0;
            $isRequiredValue = $isRequired ? 1 : 0;
            
            foreach ($typeIds as $typeId) {
                $query = "UPDATE maintenance_types SET is_required = ?, updated_at = NOW() WHERE id = ?";
                $result = $this->executeQuery($query, [$isRequiredValue, $typeId]);
                
                if ($result) {
                    $updated++;
                }
            }
            
            if ($updated > 0) {
                $this->commit();
                return [
                    'success' => true,
                    'message' => "وضعیت اجباری بودن $updated نوع نگهداری با موفقیت به‌روزرسانی شد.",
                    'updated' => $updated
                ];
            } else {
                $this->rollback();
                return [
                    'success' => false,
                    'message' => 'هیچ نوع نگهداری به‌روزرسانی نشد.',
                    'updated' => 0
                ];
            }
        } catch (Exception $e) {
            $this->rollback();
            error_log("Error in bulkUpdateRequiredStatus: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در به‌روزرسانی وضعیت اجباری بودن: ' . $e->getMessage(),
                'updated' => 0
            ];
        }
    }

    /**
     * دریافت انواع نگهداری با فیلتر پیشرفته
     * 
     * @param array $filters فیلترهای پیشرفته
     * @return array لیست انواع نگهداری
     */
    public function getTypesWithAdvancedFilters($filters) {
        try {
            $whereConditions = [];
            $params = [];
            
            // فیلتر بر اساس نام
            if (!empty($filters['name'])) {
                $whereConditions[] = "(mt.name LIKE ? OR mt.description LIKE ?)";
                $searchParam = '%' . $filters['name'] . '%';
                $params = array_merge($params, [$searchParam, $searchParam]);
            }
            
            // فیلتر بر اساس دسته‌بندی
            if (!empty($filters['category'])) {
                if (is_array($filters['category'])) {
                    $placeholders = implode(',', array_fill(0, count($filters['category']), '?'));
                    $whereConditions[] = "mt.category IN ($placeholders)";
                    $params = array_merge($params, $filters['category']);
                } else {
                    $whereConditions[] = "mt.category = ?";
                    $params[] = $filters['category'];
                }
            }
            
            // فیلتر بر اساس اجباری بودن
            if (isset($filters['is_required']) && $filters['is_required'] !== '') {
                $whereConditions[] = "mt.is_required = ?";
                $params[] = $filters['is_required'];
            }
            
            // فیلتر بر اساس بازه فاصله زمانی
            if (!empty($filters['interval_min'])) {
                $whereConditions[] = "mt.interval_days >= ?";
                $params[] = (int)$filters['interval_min'];
            }
            
            if (!empty($filters['interval_max'])) {
                $whereConditions[] = "mt.interval_days <= ?";
                $params[] = (int)$filters['interval_max'];
            }
            
            // فیلتر بر اساس داشتن چک‌لیست
            if (isset($filters['has_checklist']) && $filters['has_checklist'] !== '') {
                if ($filters['has_checklist']) {
                    $whereConditions[] = "(mt.checklist IS NOT NULL AND mt.checklist != '')";
                } else {
                    $whereConditions[] = "(mt.checklist IS NULL OR mt.checklist = '')";
                }
            }
            
            // فیلتر بر اساس تاریخ ایجاد
            if (!empty($filters['created_from'])) {
                $whereConditions[] = "mt.created_at >= ?";
                $params[] = $filters['created_from'] . ' 00:00:00';
            }
            
            if (!empty($filters['created_to'])) {
                $whereConditions[] = "mt.created_at <= ?";
                $params[] = $filters['created_to'] . ' 23:59:59';
            }
            
            // فیلتر بر اساس تعداد برنامه‌های نگهداری
            if (!empty($filters['min_schedules'])) {
                $whereConditions[] = "(SELECT COUNT(*) FROM maintenance_schedules ms WHERE ms.maintenance_type_id = mt.id) >= ?";
                $params[] = (int)$filters['min_schedules'];
            }
            
            // فیلتر بر اساس تعداد سوابق نگهداری
            if (!empty($filters['min_logs'])) {
                $whereConditions[] = "(SELECT COUNT(*) FROM maintenance_logs ml WHERE ml.maintenance_type_id = mt.id) >= ?";
                $params[] = (int)$filters['min_logs'];
            }
            
            // ساخت بخش WHERE کوئری
            $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";
            
            // مرتب‌سازی
            $orderBy = "mt.name";
            if (!empty($filters['sort_by'])) {
                $validSortColumns = ['name', 'interval_days', 'category', 'is_required', 'created_at'];
                if (in_array($filters['sort_by'], $validSortColumns)) {
                    $orderBy = "mt." . $filters['sort_by'];
                }
            }
            
            $order = !empty($filters['order']) && strtolower($filters['order']) === 'desc' ? 'DESC' : 'ASC';
            
            $query = "SELECT mt.*, 
                    (SELECT COUNT(*) FROM maintenance_schedules ms WHERE ms.maintenance_type_id = mt.id) as schedule_count,
                    (SELECT COUNT(*) FROM maintenance_logs ml WHERE ml.maintenance_type_id = mt.id) as log_count
                    FROM maintenance_types mt
                    $whereClause
                    ORDER BY $orderBy $order";
            
            return $this->executeQuery($query, $params);
        } catch (Exception $e) {
            error_log("Error in getTypesWithAdvancedFilters: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت داده‌های داشبورد انواع نگهداری
     * 
     * @return array داده‌های داشبورد
     */
    public function getTypesDashboardData() {
        try {
            $result = [
                'total_types' => 0,
                'required_types' => 0,
                'types_with_checklist' => 0,
                'avg_interval' => 0,
                'category_stats' => [],
                'most_used_types' => [],
                'overdue_types' => [],
                'recent_types' => []
            ];
            
            // آمار کلی
            $statsQuery = "SELECT 
                            COUNT(*) as total_types,
                            SUM(is_required) as required_types,
                            SUM(CASE WHEN checklist IS NOT NULL AND checklist != '' THEN 1 ELSE 0 END) as types_with_checklist,
                            AVG(interval_days) as avg_interval
                        FROM maintenance_types";
            $statsResult = $this->executeQuery($statsQuery);
            
            if ($statsResult) {
                $result['total_types'] = $statsResult[0]['total_types'] ?? 0;
                $result['required_types'] = $statsResult[0]['required_types'] ?? 0;
                $result['types_with_checklist'] = $statsResult[0]['types_with_checklist'] ?? 0;
                $result['avg_interval'] = $statsResult[0]['avg_interval'] ?? 0;
            }
            
            // آمار دسته‌بندی
            $result['category_stats'] = $this->getTypeStatsByCategory();
            
            // انواع پرکاربرد
            $result['most_used_types'] = $this->getMostUsedTypes(5);
            
            // انواع با بیشترین برنامه‌های معوق
            $result['overdue_types'] = $this->getTypesWithMostOverdueSchedules(5);
            
            // انواع اخیراً اضافه شده
            $recentQuery = "SELECT * FROM maintenance_types ORDER BY created_at DESC LIMIT 5";
            $result['recent_types'] = $this->executeQuery($recentQuery);
            
            return $result;
        } catch (Exception $e) {
            error_log("Error in getTypesDashboardData: " . $e->getMessage());
            return [
                'total_types' => 0,
                'required_types' => 0,
                'types_with_checklist' => 0,
                'avg_interval' => 0,
                'category_stats' => [],
                'most_used_types' => [],
                'overdue_types' => [],
                'recent_types' => []
            ];
        }
    }

    /**
     * حذف چندین نوع نگهداری به صورت گروهی
     * 
     * @param array $typeIds آرایه‌ای از شناسه‌های انواع نگهداری
     * @param bool $force اجبار به حذف حتی با وجود وابستگی‌ها
     * @return array نتیجه عملیات
     */
    public function bulkDeleteTypes($typeIds, $force = false) {
        try {
            if (empty($typeIds) || !is_array($typeIds)) {
                return [
                    'success' => false,
                    'message' => 'هیچ نوع نگهداری انتخاب نشده است.',
                    'deleted' => 0,
                    'failed' => 0,
                    'failed_ids' => []
                ];
            }
            
            $this->beginTransaction();
            $deleted = 0;
            $failed = 0;
            $failedIds = [];
            
            foreach ($typeIds as $typeId) {
                if ($force) {
                    // حذف وابستگی‌ها
                    $this->executeQuery("DELETE FROM maintenance_logs WHERE maintenance_type_id = ?", [$typeId]);
                    $this->executeQuery("DELETE FROM maintenance_schedules WHERE maintenance_type_id = ?", [$typeId]);
                    
                    // حذف نوع نگهداری
                    $query = "DELETE FROM maintenance_types WHERE id = ?";
                    $result = $this->executeQuery($query, [$typeId]);
                    
                    if ($result) {
                        $deleted++;
                    } else {
                        $failed++;
                        $failedIds[] = $typeId;
                    }
                } else {
                    // بررسی وجود وابستگی‌ها
                    $checkResult = $this->checkTypeDeletePossibility($typeId);
                    
                    if ($checkResult['can_delete']) {
                        $query = "DELETE FROM maintenance_types WHERE id = ?";
                        $result = $this->executeQuery($query, [$typeId]);
                        
                        if ($result) {
                            $deleted++;
                        } else {
                            $failed++;
                            $failedIds[] = $typeId;
                        }
                    } else {
                        $failed++;
                        $failedIds[] = $typeId;
                    }
                }
            }
            
            if ($deleted > 0) {
                $this->commit();
                return [
                    'success' => true,
                    'message' => "$deleted نوع نگهداری با موفقیت حذف شد. $failed مورد ناموفق بود.",
                    'deleted' => $deleted,
                    'failed' => $failed,
                    'failed_ids' => $failedIds
                ];
            } else {
                $this->rollback();
                return [
                    'success' => false,
                    'message' => "هیچ نوع نگهداری حذف نشد. $failed مورد ناموفق بود.",
                    'deleted' => 0,
                    'failed' => $failed,
                    'failed_ids' => $failedIds
                ];
            }
        } catch (Exception $e) {
            $this->rollback();
            error_log("Error in bulkDeleteTypes: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در حذف انواع نگهداری: ' . $e->getMessage(),
                'deleted' => 0,
                'failed' => count($typeIds),
                'failed_ids' => $typeIds
            ];
        }
    }

    /**
     * بررسی استفاده از یک نوع نگهداری در سیستم
     * 
     * @param int $typeId شناسه نوع نگهداری
     * @return array اطلاعات استفاده
     */
    public function getTypeUsageInfo($typeId) {
        try {
            $result = [
                'type_id' => $typeId,
                'schedule_count' => 0,
                'log_count' => 0,
                'asset_count' => 0,
                'recent_logs' => [],
                'recent_schedules' => []
            ];
            
            // تعداد برنامه‌های نگهداری
            $query = "SELECT COUNT(*) as count FROM maintenance_schedules WHERE maintenance_type_id = ?";
            $scheduleResult = $this->executeQuery($query, [$typeId]);
            $result['schedule_count'] = $scheduleResult[0]['count'] ?? 0;
            
            // تعداد سوابق نگهداری
            $query = "SELECT COUNT(*) as count FROM maintenance_logs WHERE maintenance_type_id = ?";
            $logResult = $this->executeQuery($query, [$typeId]);
            $result['log_count'] = $logResult[0]['count'] ?? 0;
            
            // تعداد تجهیز‌های منحصر به فرد
            $query = "SELECT COUNT(DISTINCT asset_id) as count FROM maintenance_schedules WHERE maintenance_type_id = ?";
            $assetResult = $this->executeQuery($query, [$typeId]);
            $result['asset_count'] = $assetResult[0]['count'] ?? 0;
            
            // سوابق اخیر
            $query = "SELECT ml.*, a.asset_tag, u.fullname as performed_by
                    FROM maintenance_logs ml
                    JOIN assets a ON ml.asset_id = a.id
                    LEFT JOIN users u ON ml.user_id = u.id
                    WHERE ml.maintenance_type_id = ?
                    ORDER BY ml.performed_at DESC
                    LIMIT 5";
            $result['recent_logs'] = $this->executeQuery($query, [$typeId]);
            
            // برنامه‌های اخیر
            $query = "SELECT ms.*, a.asset_tag
                    FROM maintenance_schedules ms
                    JOIN assets a ON ms.asset_id = a.id
                    WHERE ms.maintenance_type_id = ?
                    ORDER BY ms.next_maintenance_date
                    LIMIT 5";
            $result['recent_schedules'] = $this->executeQuery($query, [$typeId]);
            
            return $result;
        } catch (Exception $e) {
            error_log("Error in getTypeUsageInfo: " . $e->getMessage());
            return [
                'type_id' => $typeId,
                'schedule_count' => 0,
                'log_count' => 0,
                'asset_count' => 0,
                'recent_logs' => [],
                'recent_schedules' => []
            ];
        }
    }

    /**
     * به‌روزرسانی فاصله زمانی برای چندین نوع نگهداری
     * 
     * @param array $typeIds آرایه‌ای از شناسه‌های انواع نگهداری
     * @param int $intervalDays فاصله زمانی جدید (روز)
     * @param bool $updateSchedules به‌روزرسانی برنامه‌های نگهداری مرتبط
     * @return array نتیجه عملیات
     */
    public function bulkUpdateInterval($typeIds, $intervalDays, $updateSchedules = false) {
        try {
            if (empty($typeIds) || !is_array($typeIds)) {
                return [
                    'success' => false,
                    'message' => 'هیچ نوع نگهداری انتخاب نشده است.',
                    'updated' => 0,
                    'schedules_updated' => 0
                ];
            }
            
            // اعتبارسنجی فاصله زمانی
            $intervalDays = max(1, intval($intervalDays));
            
            $this->beginTransaction();
            $updated = 0;
            $schedulesUpdated = 0;
            
            foreach ($typeIds as $typeId) {
                // به‌روزرسانی نوع نگهداری
                $query = "UPDATE maintenance_types SET interval_days = ?, updated_at = NOW() WHERE id = ?";
                $result = $this->executeQuery($query, [$intervalDays, $typeId]);
                
                if ($result) {
                    $updated++;
                    
                    // به‌روزرسانی برنامه‌های نگهداری مرتبط
                    if ($updateSchedules) {
                        $scheduleQuery = "UPDATE maintenance_schedules 
                                        SET next_maintenance_date = DATE_ADD(last_maintenance_date, INTERVAL ? DAY),
                                            updated_at = NOW()
                                        WHERE maintenance_type_id = ?";
                        $scheduleResult = $this->executeQuery($scheduleQuery, [$intervalDays, $typeId]);
                        
                        if ($scheduleResult) {
                            $schedulesUpdated += $this->db instanceof PDO ? $this->db->rowCount() : $this->db->affected_rows;
                        }
                    }
                }
            }
            
            if ($updated > 0) {
                $this->commit();
                
                $message = "$updated نوع نگهداری با موفقیت به‌روزرسانی شد.";
                if ($updateSchedules) {
                    $message .= " $schedulesUpdated برنامه نگهداری مرتبط نیز به‌روزرسانی شد.";
                }
                
                return [
                    'success' => true,
                    'message' => $message,
                    'updated' => $updated,
                    'schedules_updated' => $schedulesUpdated
                ];
            } else {
                $this->rollback();
                return [
                    'success' => false,
                    'message' => 'هیچ نوع نگهداری به‌روزرسانی نشد.',
                    'updated' => 0,
                    'schedules_updated' => 0
                ];
            }
        } catch (Exception $e) {
            $this->rollback();
            error_log("Error in bulkUpdateInterval: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در به‌روزرسانی فاصله زمانی: ' . $e->getMessage(),
                'updated' => 0,
                'schedules_updated' => 0
            ];
        }
    }

    /**
     * ایجاد یک نوع نگهداری جدید با کپی کردن چک‌لیست از نوع دیگر
     * 
     * @param array $data داده‌های نوع نگهداری جدید
     * @param int $sourceTypeId شناسه نوع نگهداری منبع برای کپی چک‌لیست
     * @return int|bool شناسه نوع نگهداری جدید یا false در صورت خطا
     */
    public function createTypeWithCopiedChecklist($data, $sourceTypeId) {
        try {
            // دریافت چک‌لیست از نوع منبع
            $sourceType = $this->getTypeById($sourceTypeId);
            
            if (!$sourceType) {
                return false;
            }
            
            // اضافه کردن چک‌لیست به داده‌های نوع جدید
            $data['checklist'] = $sourceType['checklist'];
            
            // ایجاد نوع نگهداری جدید
            return $this->createType($data);
        } catch (Exception $e) {
            error_log("Error in createTypeWithCopiedChecklist: " . $e->getMessage());
            return false;
        }
    }
}