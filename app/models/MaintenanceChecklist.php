<?php
require_once __DIR__ . '/../core/Database.php';

class MaintenanceChecklist {
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
     * دریافت تمام چک‌لیست‌ها
     * 
     * @param array $filters فیلترها
     * @param int $page شماره صفحه
     * @param int $perPage تعداد آیتم در هر صفحه
     * @return array چک‌لیست‌ها و اطلاعات صفحه‌بندی
     */
    public function getAllChecklists($filters = [], $page = 1, $perPage = 10) {
        try {
            $whereConditions = [];
            $params = [];
            
            if (!empty($filters['name'])) {
                $whereConditions[] = "c.name LIKE ?";
                $params[] = '%' . $filters['name'] . '%';
            }
            
            if (!empty($filters['maintenance_type_id'])) {
                $whereConditions[] = "c.maintenance_type_id = ?";
                $params[] = $filters['maintenance_type_id'];
            }
            
            if (!empty($filters['category_id'])) {
                $whereConditions[] = "mt.category = ?";
                $params[] = $filters['category_id'];
            }
            
            if (isset($filters['is_active']) && $filters['is_active'] !== '') {
                $whereConditions[] = "c.is_active = ?";
                $params[] = $filters['is_active'];
            }
            
            $whereClause = '';
            if (!empty($whereConditions)) {
                $whereClause = "WHERE " . implode(" AND ", $whereConditions);
            }
            
            $offset = ($page - 1) * $perPage;
            
            $query = "
                SELECT c.*, mt.name as maintenance_type_name, ac.name as category_name
                FROM maintenance_checklists c
                LEFT JOIN maintenance_types mt ON c.maintenance_type_id = mt.id
                LEFT JOIN asset_categories ac ON mt.category = ac.id
                $whereClause
                ORDER BY c.name ASC
                LIMIT $perPage OFFSET $offset
            ";
            
            $checklists = $this->executeQuery($query, $params);
            
            // دریافت تعداد کل رکوردها
            $countQuery = "
                SELECT COUNT(*) as total
                FROM maintenance_checklists c
                LEFT JOIN maintenance_types mt ON c.maintenance_type_id = mt.id
                LEFT JOIN asset_categories ac ON mt.category = ac.id
                $whereClause
            ";
            
            $countResult = $this->executeQuery($countQuery, $params);
            $totalCount = $countResult[0]['total'];
            $totalPages = ceil($totalCount / $perPage);
            
            return [
                'checklists' => $checklists,
                'pagination' => [
                    'total' => $totalCount,
                    'per_page' => $perPage,
                    'current_page' => $page,
                    'total_pages' => $totalPages,
                    'has_next' => $page < $totalPages,
                    'has_prev' => $page > 1
                ]
            ];
        } catch (Exception $e) {
            error_log("Error in getAllChecklists: " . $e->getMessage());
            return [
                'checklists' => [],
                'pagination' => [
                    'total' => 0,
                    'per_page' => $perPage,
                    'current_page' => $page,
                    'total_pages' => 0,
                    'has_next' => false,
                    'has_prev' => false
                ]
            ];
        }
    }
    
    /**
     * دریافت چک‌لیست با شناسه مشخص
     * 
     * @param int $id شناسه چک‌لیست
     * @return array|null اطلاعات چک‌لیست و آیتم‌های آن
     */
    public function getChecklistById($id) {
        try {
            $query = "
                SELECT c.*, mt.name as maintenance_type_name, ac.name as category_name
                FROM maintenance_checklists c
                LEFT JOIN maintenance_types mt ON c.maintenance_type_id = mt.id
                LEFT JOIN asset_categories ac ON mt.category = ac.id
                WHERE c.id = ?
            ";
            
            $checklist = $this->executeQuery($query, [$id]);
            
            if (empty($checklist)) {
                return null;
            }
            
            $checklist = $checklist[0];
            
            // دریافت آیتم‌های چک‌لیست
            $itemsQuery = "
                SELECT * FROM maintenance_checklist_items
                WHERE checklist_id = ?
                ORDER BY position ASC
            ";
            
            $items = $this->executeQuery($itemsQuery, [$id]);
            $checklist['items'] = $items;
            
            return $checklist;
        } catch (Exception $e) {
            error_log("Error in getChecklistById: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * دریافت چک‌لیست‌های مرتبط با یک نوع نگهداری
     * 
     * @param int $maintenanceTypeId شناسه نوع نگهداری
     * @return array چک‌لیست‌های مرتبط
     */
    public function getChecklistsByMaintenanceType($maintenanceTypeId) {
        try {
            $query = "
                SELECT c.*, COUNT(ci.id) as item_count
                FROM maintenance_checklists c
                LEFT JOIN maintenance_checklist_items ci ON c.id = ci.checklist_id
                WHERE c.maintenance_type_id = ? AND c.is_active = 1
                GROUP BY c.id
                ORDER BY c.name ASC
            ";
            
            return $this->executeQuery($query, [$maintenanceTypeId]);
        } catch (Exception $e) {
            error_log("Error in getChecklistsByMaintenanceType: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * دریافت چک‌لیست‌های مرتبط با یک دسته‌بندی تجهیز
     * 
     * @param int $categoryId شناسه دسته‌بندی تجهیز
     * @return array چک‌لیست‌های مرتبط
     */
    public function getChecklistsByCategory($categoryId) {
        try {
            $query = "
                SELECT c.*, mt.name as maintenance_type_name, COUNT(ci.id) as item_count
                FROM maintenance_checklists c
                JOIN maintenance_types mt ON c.maintenance_type_id = mt.id
                LEFT JOIN maintenance_checklist_items ci ON c.id = ci.checklist_id
                WHERE mt.category = ? AND c.is_active = 1
                GROUP BY c.id
                ORDER BY c.name ASC
            ";
            
            return $this->executeQuery($query, [$categoryId]);
        } catch (Exception $e) {
            error_log("Error in getChecklistsByCategory: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * ایجاد چک‌لیست جدید
     * 
     * @param array $data داده‌های چک‌لیست
     * @return int|bool شناسه چک‌لیست جدید یا false در صورت خطا
     */
    public function createChecklist($data) {
        try {
            // شروع تراکنش
            if ($this->db instanceof PDO) {
                $this->db->beginTransaction();
            } else {
                $this->db->begin_transaction();
            }
            
            $query = "
                INSERT INTO maintenance_checklists 
                (name, description, maintenance_type_id, is_active, created_at, updated_at)
                VALUES (?, ?, ?, ?, NOW(), NOW())
            ";
            
            $result = $this->executeQuery($query, [
                $data['name'],
                $data['description'] ?? '',
                $data['maintenance_type_id'],
                $data['is_active'] ?? 1
            ]);
            
            if (!$result) {
                // بازگشت تراکنش
                if ($this->db instanceof PDO) {
                    $this->db->rollBack();
                } else {
                    $this->db->rollback();
                }
                return false;
            }
            
            // دریافت شناسه چک‌لیست جدید
            $checklistId = $this->db instanceof PDO ? $this->db->lastInsertId() : $this->db->insert_id;
            
            // افزودن آیتم‌های چک‌لیست
            if (!empty($data['items']) && is_array($data['items'])) {
                foreach ($data['items'] as $position => $item) {
                    $itemQuery = "
                        INSERT INTO maintenance_checklist_items 
                        (checklist_id, description, item_type, required, position, options, created_at, updated_at)
                        VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
                    ";
                    
                    $itemResult = $this->executeQuery($itemQuery, [
                        $checklistId,
                        $item['description'],
                        $item['item_type'] ?? 'checkbox',
                        $item['required'] ?? 1,
                        $position + 1,
                        $item['options'] ?? null
                    ]);
                    
                    if (!$itemResult) {
                        // بازگشت تراکنش
                        if ($this->db instanceof PDO) {
                            $this->db->rollBack();
                        } else {
                            $this->db->rollback();
                        }
                        return false;
                    }
                }
            }
            
            // تایید تراکنش
            if ($this->db instanceof PDO) {
                $this->db->commit();
            } else {
                $this->db->commit();
            }
            
            return $checklistId;
        } catch (Exception $e) {
            // بازگشت تراکنش
            if ($this->db instanceof PDO) {
                $this->db->rollBack();
            } else {
                $this->db->rollback();
            }
            
            error_log("Error in createChecklist: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * به‌روزرسانی چک‌لیست
     * 
     * @param int $id شناسه چک‌لیست
     * @param array $data داده‌های جدید چک‌لیست
     * @return bool نتیجه عملیات
     */
    public function updateChecklist($id, $data) {
        try {
            // شروع تراکنش
            if ($this->db instanceof PDO) {
                $this->db->beginTransaction();
            } else {
                $this->db->begin_transaction();
            }
            
            $query = "
                UPDATE maintenance_checklists 
                SET name = ?, description = ?, maintenance_type_id = ?, is_active = ?, updated_at = NOW()
                WHERE id = ?
            ";
            
            $result = $this->executeQuery($query, [
                $data['name'],
                $data['description'] ?? '',
                $data['maintenance_type_id'],
                $data['is_active'] ?? 1,
                $id
            ]);
            
            if (!$result) {
                // بازگشت تراکنش
                if ($this->db instanceof PDO) {
                    $this->db->rollBack();
                } else {
                    $this->db->rollback();
                }
                return false;
            }
            
            // به‌روزرسانی آیتم‌های چک‌لیست
            if (isset($data['items']) && is_array($data['items'])) {
                // حذف آیتم‌های قبلی
                $deleteQuery = "DELETE FROM maintenance_checklist_items WHERE checklist_id = ?";
                $this->executeQuery($deleteQuery, [$id]);
                
                // افزودن آیتم‌های جدید
                foreach ($data['items'] as $position => $item) {
                    $itemQuery = "
                        INSERT INTO maintenance_checklist_items 
                        (checklist_id, description, item_type, required, position, options, created_at, updated_at)
                        VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
                    ";
                    
                    $itemResult = $this->executeQuery($itemQuery, [
                        $id,
                        $item['description'],
                        $item['item_type'] ?? 'checkbox',
                        $item['required'] ?? 1,
                        $position + 1,
                        $item['options'] ?? null
                    ]);
                    
                    if (!$itemResult) {
                        // بازگشت تراکنش
                        if ($this->db instanceof PDO) {
                            $this->db->rollBack();
                        } else {
                            $this->db->rollback();
                        }
                        return false;
                    }
                }
            }
            
            // تایید تراکنش
            if ($this->db instanceof PDO) {
                $this->db->commit();
            } else {
                $this->db->commit();
            }
            
            return true;
        } catch (Exception $e) {
            // بازگشت تراکنش
            if ($this->db instanceof PDO) {
                $this->db->rollBack();
            } else {
                $this->db->rollback();
            }
            
            error_log("Error in updateChecklist: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * حذف چک‌لیست
     * 
     * @param int $id شناسه چک‌لیست
     * @return bool نتیجه عملیات
     */
    public function deleteChecklist($id) {
        try {
            // شروع تراکنش
            if ($this->db instanceof PDO) {
                $this->db->beginTransaction();
            } else {
                $this->db->begin_transaction();
            }
            
            // حذف آیتم‌های چک‌لیست
            $deleteItemsQuery = "DELETE FROM maintenance_checklist_items WHERE checklist_id = ?";
            $this->executeQuery($deleteItemsQuery, [$id]);
            
            // حذف چک‌لیست
            $deleteChecklistQuery = "DELETE FROM maintenance_checklists WHERE id = ?";
            $result = $this->executeQuery($deleteChecklistQuery, [$id]);
            
            if ($result) {
                // تایید تراکنش
                if ($this->db instanceof PDO) {
                    $this->db->commit();
                } else {
                    $this->db->commit();
                }
                return true;
            } else {
                // بازگشت تراکنش
                if ($this->db instanceof PDO) {
                    $this->db->rollBack();
                } else {
                    $this->db->rollback();
                }
                return false;
            }
        } catch (Exception $e) {
            // بازگشت تراکنش
            if ($this->db instanceof PDO) {
                $this->db->rollBack();
            } else {
                $this->db->rollback();
            }
            
            error_log("Error in deleteChecklist: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * غیرفعال کردن چک‌لیست
     * 
     * @param int $id شناسه چک‌لیست
     * @return bool نتیجه عملیات
     */
    public function deactivateChecklist($id) {
        try {
            $query = "
                UPDATE maintenance_checklists 
                SET is_active = 0, updated_at = NOW()
                WHERE id = ?
            ";
            
            return $this->executeQuery($query, [$id]);
        } catch (Exception $e) {
            error_log("Error in deactivateChecklist: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * فعال کردن چک‌لیست
     * 
     * @param int $id شناسه چک‌لیست
     * @return bool نتیجه عملیات
     */
    public function activateChecklist($id) {
        try {
            $query = "
                UPDATE maintenance_checklists 
                SET is_active = 1, updated_at = NOW()
                WHERE id = ?
            ";
            
            return $this->executeQuery($query, [$id]);
        } catch (Exception $e) {
            error_log("Error in activateChecklist: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * دریافت آیتم‌های چک‌لیست
     * 
     * @param int $checklistId شناسه چک‌لیست
     * @return array آیتم‌های چک‌لیست
     */
    public function getChecklistItems($checklistId) {
        try {
            $query = "
                SELECT * FROM maintenance_checklist_items
                WHERE checklist_id = ?
                ORDER BY position ASC
            ";
            
            return $this->executeQuery($query, [$checklistId]);
        } catch (Exception $e) {
            error_log("Error in getChecklistItems: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * افزودن آیتم به چک‌لیست
     * 
     * @param int $checklistId شناسه چک‌لیست
     * @param array $item داده‌های آیتم
     * @return int|bool شناسه آیتم جدید یا false در صورت خطا
     */
    public function addChecklistItem($checklistId, $item) {
        try {
            // دریافت موقعیت آخرین آیتم
            $positionQuery = "
                SELECT MAX(position) as max_position
                FROM maintenance_checklist_items
                WHERE checklist_id = ?
            ";
            
            $positionResult = $this->executeQuery($positionQuery, [$checklistId]);
            $position = isset($positionResult[0]['max_position']) ? $positionResult[0]['max_position'] + 1 : 1;
            
            $query = "
                INSERT INTO maintenance_checklist_items 
                (checklist_id, description, item_type, required, position, options, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
            ";
            
            $result = $this->executeQuery($query, [
                $checklistId,
                $item['description'],
                $item['item_type'] ?? 'checkbox',
                $item['required'] ?? 1,
                $position,
                $item['options'] ?? null
            ]);
            
            if ($result) {
                return $this->db instanceof PDO ? $this->db->lastInsertId() : $this->db->insert_id;
            }
            
            return false;
        } catch (Exception $e) {
            error_log("Error in addChecklistItem: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * به‌روزرسانی آیتم چک‌لیست
     * 
     * @param int $itemId شناسه آیتم
     * @param array $data داده‌های جدید آیتم
     * @return bool نتیجه عملیات
     */
    public function updateChecklistItem($itemId, $data) {
        try {
            $query = "
                UPDATE maintenance_checklist_items 
                SET description = ?, item_type = ?, required = ?, options = ?, updated_at = NOW()
                WHERE id = ?
            ";
            
            return $this->executeQuery($query, [
                $data['description'],
                $data['item_type'] ?? 'checkbox',
                $data['required'] ?? 1,
                $data['options'] ?? null,
                $itemId
            ]);
        } catch (Exception $e) {
            error_log("Error in updateChecklistItem: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * حذف آیتم چک‌لیست
     * 
     * @param int $itemId شناسه آیتم
     * @return bool نتیجه عملیات
     */
    public function deleteChecklistItem($itemId) {
        try {
            $query = "DELETE FROM maintenance_checklist_items WHERE id = ?";
            return $this->executeQuery($query, [$itemId]);
        } catch (Exception $e) {
            error_log("Error in deleteChecklistItem: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * تغییر ترتیب آیتم‌های چک‌لیست
     * 
     * @param int $checklistId شناسه چک‌لیست
     * @param array $itemOrder آرایه‌ای از شناسه‌های آیتم‌ها به ترتیب جدید
     * @return bool نتیجه عملیات
     */
    public function reorderChecklistItems($checklistId, $itemOrder) {
        try {
            // شروع تراکنش
            if ($this->db instanceof PDO) {
                $this->db->beginTransaction();
            } else {
                $this->db->begin_transaction();
            }
            
            $success = true;
            
            foreach ($itemOrder as $position => $itemId) {
                $query = "
                    UPDATE maintenance_checklist_items 
                    SET position = ?, updated_at = NOW()
                    WHERE id = ? AND checklist_id = ?
                ";
                
                $result = $this->executeQuery($query, [
                    $position + 1,
                    $itemId,
                    $checklistId
                ]);
                
                if (!$result) {
                    $success = false;
                    break;
                }
            }
            
            if ($success) {
                // تایید تراکنش
                if ($this->db instanceof PDO) {
                    $this->db->commit();
                } else {
                    $this->db->commit();
                }
                return true;
            } else {
                // بازگشت تراکنش
                if ($this->db instanceof PDO) {
                    $this->db->rollBack();
                } else {
                    $this->db->rollback();
                }
                return false;
            }
        } catch (Exception $e) {
            // بازگشت تراکنش
            if ($this->db instanceof PDO) {
                $this->db->rollBack();
            } else {
                $this->db->rollback();
            }
            
            error_log("Error in reorderChecklistItems: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * کپی چک‌لیست
     * 
     * @param int $checklistId شناسه چک‌لیست اصلی
     * @param string $newName نام چک‌لیست جدید
     * @return int|bool شناسه چک‌لیست جدید یا false در صورت خطا
     */
    public function duplicateChecklist($checklistId, $newName = null) {
        try {
            // دریافت اطلاعات چک‌لیست اصلی
            $checklist = $this->getChecklistById($checklistId);
            
            if (!$checklist) {
                return false;
            }
            
            // تعیین نام چک‌لیست جدید
            if (!$newName) {
                $newName = $checklist['name'] . ' (کپی)';
            }
            
            // شروع تراکنش
            if ($this->db instanceof PDO) {
                $this->db->beginTransaction();
            } else {
                $this->db->begin_transaction();
            }
            
            // ایجاد چک‌لیست جدید
            $query = "
                INSERT INTO maintenance_checklists 
                (name, description, maintenance_type_id, is_active, created_at, updated_at)
                VALUES (?, ?, ?, ?, NOW(), NOW())
            ";
            
            $result = $this->executeQuery($query, [
                $newName,
                $checklist['description'],
                $checklist['maintenance_type_id'],
                $checklist['is_active']
            ]);
            
            if (!$result) {
                // بازگشت تراکنش
                if ($this->db instanceof PDO) {
                    $this->db->rollBack();
                } else {
                    $this->db->rollback();
                }
                return false;
            }
            
            // دریافت شناسه چک‌لیست جدید
            $newChecklistId = $this->db instanceof PDO ? $this->db->lastInsertId() : $this->db->insert_id;
            
            // کپی آیتم‌های چک‌لیست
            if (!empty($checklist['items'])) {
                foreach ($checklist['items'] as $item) {
                    $itemQuery = "
                        INSERT INTO maintenance_checklist_items 
                        (checklist_id, description, item_type, required, position, options, created_at, updated_at)
                        VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
                    ";
                    
                    $itemResult = $this->executeQuery($itemQuery, [
                        $newChecklistId,
                        $item['description'],
                        $item['item_type'],
                        $item['required'],
                        $item['position'],
                        $item['options']
                    ]);
                    
                    if (!$itemResult) {
                        // بازگشت تراکنش
                        if ($this->db instanceof PDO) {
                            $this->db->rollBack();
                        } else {
                            $this->db->rollback();
                        }
                        return false;
                    }
                }
            }
            
            // تایید تراکنش
            if ($this->db instanceof PDO) {
                $this->db->commit();
            } else {
                $this->db->commit();
            }
            
            return $newChecklistId;
        } catch (Exception $e) {
            // بازگشت تراکنش
            if ($this->db instanceof PDO) {
                $this->db->rollBack();
            } else {
                $this->db->rollback();
            }
            
            error_log("Error in duplicateChecklist: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * دریافت نتایج چک‌لیست برای یک سرویس
     * 
     * @param int $maintenanceLogId شناسه لاگ سرویس
     * @return array نتایج چک‌لیست
     */
    public function getChecklistResults($maintenanceLogId) {
        try {
            $query = "
                SELECT cr.*, ci.description, ci.item_type, ci.required
                FROM maintenance_checklist_results cr
                JOIN maintenance_checklist_items ci ON cr.checklist_item_id = ci.id
                WHERE cr.maintenance_log_id = ?
                ORDER BY ci.position ASC
            ";
            
            return $this->executeQuery($query, [$maintenanceLogId]);
        } catch (Exception $e) {
            error_log("Error in getChecklistResults: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * ثبت نتایج چک‌لیست برای یک سرویس
     * 
     * @param int $maintenanceLogId شناسه لاگ سرویس
     * @param array $results نتایج چک‌لیست
     * @return bool نتیجه عملیات
     */
    public function saveChecklistResults($maintenanceLogId, $results) {
        try {
            // شروع تراکنش
            if ($this->db instanceof PDO) {
                $this->db->beginTransaction();
            } else {
                $this->db->begin_transaction();
            }
            
            // حذف نتایج قبلی
            $deleteQuery = "DELETE FROM maintenance_checklist_results WHERE maintenance_log_id = ?";
            $this->executeQuery($deleteQuery, [$maintenanceLogId]);
            
            // ثبت نتایج جدید
            $success = true;
            
            foreach ($results as $result) {
                $query = "
                    INSERT INTO maintenance_checklist_results 
                    (maintenance_log_id, checklist_item_id, value, notes, created_at, updated_at)
                    VALUES (?, ?, ?, ?, NOW(), NOW())
                ";
                
                $insertResult = $this->executeQuery($query, [
                    $maintenanceLogId,
                    $result['checklist_item_id'],
                    $result['value'] ?? '',
                    $result['notes'] ?? null
                ]);
                
                if (!$insertResult) {
                    $success = false;
                    break;
                }
            }
            
            if ($success) {
                // تایید تراکنش
                if ($this->db instanceof PDO) {
                    $this->db->commit();
                } else {
                    $this->db->commit();
                }
                return true;
            } else {
                // بازگشت تراکنش
                if ($this->db instanceof PDO) {
                    $this->db->rollBack();
                } else {
                    $this->db->rollback();
                }
                return false;
            }
        } catch (Exception $e) {
            // بازگشت تراکنش
            if ($this->db instanceof PDO) {
                $this->db->rollBack();
            } else {
                $this->db->rollback();
            }
            
            error_log("Error in saveChecklistResults: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * دریافت چک‌لیست‌های پیش‌فرض برای یک نوع نگهداری
     * 
     * @param int $maintenanceTypeId شناسه نوع نگهداری
     * @return array چک‌لیست‌های پیش‌فرض
     */
    public function getDefaultChecklistsForMaintenanceType($maintenanceTypeId) {
        try {
            $query = "
                SELECT c.*, COUNT(ci.id) as item_count
                FROM maintenance_checklists c
                LEFT JOIN maintenance_checklist_items ci ON c.id = ci.checklist_id
                WHERE c.maintenance_type_id = ? AND c.is_active = 1 AND c.is_default = 1
                GROUP BY c.id
                ORDER BY c.name ASC
            ";
            
            return $this->executeQuery($query, [$maintenanceTypeId]);
        } catch (Exception $e) {
            error_log("Error in getDefaultChecklistsForMaintenanceType: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * تنظیم چک‌لیست به عنوان پیش‌فرض
     * 
     * @param int $checklistId شناسه چک‌لیست
     * @param bool $isDefault وضعیت پیش‌فرض
     * @return bool نتیجه عملیات
     */
    public function setChecklistAsDefault($checklistId, $isDefault = true) {
        try {
            $query = "
                UPDATE maintenance_checklists 
                SET is_default = ?, updated_at = NOW()
                WHERE id = ?
            ";
            
            return $this->executeQuery($query, [
                $isDefault ? 1 : 0,
                $checklistId
            ]);
        } catch (Exception $e) {
            error_log("Error in setChecklistAsDefault: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * ایمپورت چک‌لیست از فایل JSON
     * 
     * @param string $jsonData داده‌های JSON
     * @return int|bool شناسه چک‌لیست جدید یا false در صورت خطا
     */
    public function importChecklistFromJson($jsonData) {
        try {
            $data = json_decode($jsonData, true);
            
            if (!$data || !isset($data['name']) || !isset($data['maintenance_type_id']) || !isset($data['items'])) {
                return false;
            }
            
            return $this->createChecklist($data);
        } catch (Exception $e) {
            error_log("Error in importChecklistFromJson: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * اکسپورت چک‌لیست به JSON
     * 
     * @param int $checklistId شناسه چک‌لیست
     * @return string|bool داده‌های JSON یا false در صورت خطا
     */
    public function exportChecklistToJson($checklistId) {
        try {
            $checklist = $this->getChecklistById($checklistId);
            
            if (!$checklist) {
                return false;
            }
            
            // حذف فیلدهای اضافی
            unset($checklist['id']);
            unset($checklist['created_at']);
            unset($checklist['updated_at']);
            unset($checklist['maintenance_type_name']);
            unset($checklist['category_name']);
            
            // حذف فیلدهای اضافی از آیتم‌ها
            if (!empty($checklist['items'])) {
                foreach ($checklist['items'] as &$item) {
                    unset($item['id']);
                    unset($item['checklist_id']);
                    unset($item['created_at']);
                    unset($item['updated_at']);
                }
            }
            
            return json_encode($checklist, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log("Error in exportChecklistToJson: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * دریافت آمار استفاده از چک‌لیست
     * 
     * @param int $checklistId شناسه چک‌لیست
     * @return array آمار استفاده
     */
    public function getChecklistUsageStats($checklistId) {
        try {
            // دریافت تعداد کل استفاده از چک‌لیست
            $query = "
                SELECT COUNT(DISTINCT ml.id) as total_usage
                FROM maintenance_logs ml
                JOIN maintenance_checklist_results cr ON ml.id = cr.maintenance_log_id
                JOIN maintenance_checklist_items ci ON cr.checklist_item_id = ci.id
                WHERE ci.checklist_id = ?
            ";
            
            $result = $this->executeQuery($query, [$checklistId]);
            $totalUsage = $result[0]['total_usage'] ?? 0;
            
            // دریافت آخرین استفاده از چک‌لیست
            $lastUsageQuery = "
                SELECT ml.performed_at, a.name as asset_name, a.asset_tag, u.fullname as performed_by_name
                FROM maintenance_logs ml
                JOIN maintenance_checklist_results cr ON ml.id = cr.maintenance_log_id
                JOIN maintenance_checklist_items ci ON cr.checklist_item_id = ci.id
                JOIN assets a ON ml.asset_id = a.id
                LEFT JOIN users u ON ml.user_id = u.id
                WHERE ci.checklist_id = ?
                ORDER BY ml.performed_at DESC
                LIMIT 1
            ";
            
            $lastUsageResult = $this->executeQuery($lastUsageQuery, [$checklistId]);
            $lastUsage = !empty($lastUsageResult) ? $lastUsageResult[0] : null;
            
            return [
                'total_usage' => $totalUsage,
                'last_usage' => $lastUsage
            ];
        } catch (Exception $e) {
            error_log("Error in getChecklistUsageStats: " . $e->getMessage());
            return [
                'total_usage' => 0,
                'last_usage' => null
            ];
        }
    }
    
    /**
     * دریافت چک‌لیست‌های پرکاربرد
     * 
     * @param int $limit تعداد نتایج
     * @return array چک‌لیست‌های پرکاربرد
     */
    public function getMostUsedChecklists($limit = 5) {
        try {
            $query = "
                SELECT c.id, c.name, c.description, mt.name as maintenance_type_name,
                    COUNT(DISTINCT ml.id) as usage_count
                FROM maintenance_checklists c
                JOIN maintenance_checklist_items ci ON c.id = ci.checklist_id
                JOIN maintenance_checklist_results cr ON ci.id = cr.checklist_item_id
                JOIN maintenance_logs ml ON cr.maintenance_log_id = ml.id
                JOIN maintenance_types mt ON c.maintenance_type_id = mt.id
                WHERE c.is_active = 1
                GROUP BY c.id, c.name, c.description, mt.name
                ORDER BY usage_count DESC
                LIMIT ?
            ";
            
            return $this->executeQuery($query, [$limit]);
        } catch (Exception $e) {
            error_log("Error in getMostUsedChecklists: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * جستجوی چک‌لیست‌ها
     * 
     * @param string $searchTerm عبارت جستجو
     * @return array نتایج جستجو
     */
    public function searchChecklists($searchTerm) {
        try {
            $query = "
                SELECT c.*, mt.name as maintenance_type_name, ac.name as category_name,
                    COUNT(ci.id) as item_count
                FROM maintenance_checklists c
                LEFT JOIN maintenance_types mt ON c.maintenance_type_id = mt.id
                LEFT JOIN asset_categories ac ON mt.category = ac.id
                LEFT JOIN maintenance_checklist_items ci ON c.id = ci.checklist_id
                WHERE c.name LIKE ? OR c.description LIKE ? OR mt.name LIKE ?
                GROUP BY c.id, c.name, c.description, mt.name, ac.name
                ORDER BY c.name ASC
            ";
            
            $searchParam = '%' . $searchTerm . '%';
            return $this->executeQuery($query, [$searchParam, $searchParam, $searchParam]);
        } catch (Exception $e) {
            error_log("Error in searchChecklists: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * بررسی وجود چک‌لیست با نام مشخص
     * 
     * @param string $name نام چک‌لیست
     * @param int $maintenanceTypeId شناسه نوع نگهداری
     * @param int $excludeId شناسه چک‌لیست برای استثنا (برای ویرایش)
     * @return bool نتیجه بررسی
     */
    public function checklistExists($name, $maintenanceTypeId, $excludeId = null) {
        try {
            $query = "
                SELECT COUNT(*) as count
                FROM maintenance_checklists
                WHERE name = ? AND maintenance_type_id = ?
            ";
            
            $params = [$name, $maintenanceTypeId];
            
            if ($excludeId) {
                $query .= " AND id != ?";
                $params[] = $excludeId;
            }
            
            $result = $this->executeQuery($query, $params);
            return $result[0]['count'] > 0;
        } catch (Exception $e) {
            error_log("Error in checklistExists: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * دریافت چک‌لیست‌های مرتبط با یک تجهیز
     * 
     * @param int $assetId شناسه تجهیز
     * @return array چک‌لیست‌های مرتبط
     */
    public function getChecklistsForAsset($assetId) {
        try {
            $query = "
                SELECT c.*, mt.name as maintenance_type_name, COUNT(ci.id) as item_count
                FROM maintenance_checklists c
                JOIN maintenance_types mt ON c.maintenance_type_id = mt.id
                LEFT JOIN maintenance_checklist_items ci ON c.id = ci.checklist_id
                JOIN assets a ON a.id = ?
                JOIN asset_models am ON a.model_id = am.id
                WHERE (mt.category = am.category_id OR mt.category IS NULL) AND c.is_active = 1
                GROUP BY c.id, c.name, c.description, mt.name
                ORDER BY c.name ASC
            ";
            
            return $this->executeQuery($query, [$assetId]);
        } catch (Exception $e) {
            error_log("Error in getChecklistsForAsset: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * ایجاد یک نسخه خالی از نتایج چک‌لیست برای یک سرویس
     * 
     * @param int $checklistId شناسه چک‌لیست
     * @param int $maintenanceLogId شناسه لاگ سرویس
     * @return bool نتیجه عملیات
     */
    public function createEmptyChecklistResults($checklistId, $maintenanceLogId) {
        try {
            // دریافت آیتم‌های چک‌لیست
            $items = $this->getChecklistItems($checklistId);
            
            if (empty($items)) {
                return false;
            }
            
            // شروع تراکنش
            if ($this->db instanceof PDO) {
                $this->db->beginTransaction();
            } else {
                $this->db->begin_transaction();
            }
            
            $success = true;
            
            foreach ($items as $item) {
                $query = "
                    INSERT INTO maintenance_checklist_results 
                    (maintenance_log_id, checklist_item_id, value, created_at, updated_at)
                    VALUES (?, ?, '', NOW(), NOW())
                ";
                
                $result = $this->executeQuery($query, [
                    $maintenanceLogId,
                    $item['id']
                ]);
                
                if (!$result) {
                    $success = false;
                    break;
                }
            }
            
            if ($success) {
                // تایید تراکنش
                if ($this->db instanceof PDO) {
                    $this->db->commit();
                } else {
                    $this->db->commit();
                }
                return true;
            } else {
                // بازگشت تراکنش
                if ($this->db instanceof PDO) {
                    $this->db->rollBack();
                } else {
                    $this->db->rollback();
                }
                return false;
            }
        } catch (Exception $e) {
            // بازگشت تراکنش
            if ($this->db instanceof PDO) {
                $this->db->rollBack();
            } else {
                $this->db->rollback();
            }
            
            error_log("Error in createEmptyChecklistResults: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * دریافت الگوهای چک‌لیست برای استفاده مجدد
     * 
     * @return array الگوهای چک‌لیست
     */
    public function getChecklistTemplates() {
        try {
            $query = "
                SELECT c.*, mt.name as maintenance_type_name, ac.name as category_name,
                    COUNT(ci.id) as item_count
                FROM maintenance_checklists c
                LEFT JOIN maintenance_types mt ON c.maintenance_type_id = mt.id
                LEFT JOIN asset_categories ac ON mt.category = ac.id
                LEFT JOIN maintenance_checklist_items ci ON c.id = ci.checklist_id
                WHERE c.is_template = 1
                GROUP BY c.id, c.name, c.description, mt.name, ac.name
                ORDER BY c.name ASC
            ";
            
            return $this->executeQuery($query);
        } catch (Exception $e) {
            error_log("Error in getChecklistTemplates: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * تنظیم چک‌لیست به عنوان الگو
     * 
     * @param int $checklistId شناسه چک‌لیست
     * @param bool $isTemplate وضعیت الگو
     * @return bool نتیجه عملیات
     */
    public function setChecklistAsTemplate($checklistId, $isTemplate = true) {
        try {
            $query = "
                UPDATE maintenance_checklists 
                SET is_template = ?, updated_at = NOW()
                WHERE id = ?
            ";
            
            return $this->executeQuery($query, [
                $isTemplate ? 1 : 0,
                $checklistId
            ]);
        } catch (Exception $e) {
            error_log("Error in setChecklistAsTemplate: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * ایجاد چک‌لیست از الگو
     * 
     * @param int $templateId شناسه الگو
     * @param string $name نام چک‌لیست جدید
     * @param int $maintenanceTypeId شناسه نوع نگهداری (اختیاری)
     * @return int|bool شناسه چک‌لیست جدید یا false در صورت خطا
     */
    public function createChecklistFromTemplate($templateId, $name, $maintenanceTypeId = null) {
        try {
            // دریافت اطلاعات الگو
            $template = $this->getChecklistById($templateId);
            
            if (!$template) {
                return false;
            }
            
            // شروع تراکنش
            if ($this->db instanceof PDO) {
                $this->db->beginTransaction();
            } else {
                $this->db->begin_transaction();
            }
            
            // ایجاد چک‌لیست جدید
            $query = "
                INSERT INTO maintenance_checklists 
                (name, description, maintenance_type_id, is_active, is_template, is_default, created_at, updated_at)
                VALUES (?, ?, ?, ?, 0, 0, NOW(), NOW())
            ";
            
            $result = $this->executeQuery($query, [
                $name,
                $template['description'],
                $maintenanceTypeId ?? $template['maintenance_type_id'],
                1
            ]);
            
            if (!$result) {
                // بازگشت تراکنش
                if ($this->db instanceof PDO) {
                    $this->db->rollBack();
                } else {
                    $this->db->rollback();
                }
                return false;
            }
            
            // دریافت شناسه چک‌لیست جدید
            $newChecklistId = $this->db instanceof PDO ? $this->db->lastInsertId() : $this->db->insert_id;
            
            // کپی آیتم‌های چک‌لیست
            if (!empty($template['items'])) {
                foreach ($template['items'] as $item) {
                    $itemQuery = "
                        INSERT INTO maintenance_checklist_items 
                        (checklist_id, description, item_type, required, position, options, created_at, updated_at)
                        VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
                    ";
                    
                    $itemResult = $this->executeQuery($itemQuery, [
                        $newChecklistId,
                        $item['description'],
                        $item['item_type'],
                        $item['required'],
                        $item['position'],
                        $item['options']
                    ]);
                    
                    if (!$itemResult) {
                        // بازگشت تراکنش
                        if ($this->db instanceof PDO) {
                            $this->db->rollBack();
                        } else {
                            $this->db->rollback();
                        }
                        return false;
                    }
                }
            }
            
            // تایید تراکنش
            if ($this->db instanceof PDO) {
                $this->db->commit();
            } else {
                $this->db->commit();
            }
            
            return $newChecklistId;
        } catch (Exception $e) {
            // بازگشت تراکنش
            if ($this->db instanceof PDO) {
                $this->db->rollBack();
            } else {
                $this->db->rollback();
            }
            
            error_log("Error in createChecklistFromTemplate: " . $e->getMessage());
            return false;
        }
    }
}