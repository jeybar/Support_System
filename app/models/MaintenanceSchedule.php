<?php
require_once __DIR__ . '/../core/Database.php';

class MaintenanceSchedule {
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
     * دریافت تمام برنامه‌های سرویس
     */
    public function getAllSchedules($filters = [], $page = 1, $perPage = 10) {
        try {
            $whereConditions = [];
            $params = [];
            
            if (!empty($filters['asset_id'])) {
                $whereConditions[] = "ms.asset_id = ?";
                $params[] = $filters['asset_id'];
            }
            
            if (!empty($filters['maintenance_type_id'])) {
                $whereConditions[] = "ms.maintenance_type_id = ?";
                $params[] = $filters['maintenance_type_id'];
            }
            
            if (!empty($filters['technician_id'])) {
                $whereConditions[] = "ms.technician_id = ?";
                $params[] = $filters['technician_id'];
            }
            
            if (!empty($filters['next_maintenance_from'])) {
                $whereConditions[] = "ms.next_maintenance_date >= ?";
                $params[] = $filters['next_maintenance_from'];
            }
            
            if (!empty($filters['next_maintenance_to'])) {
                $whereConditions[] = "ms.next_maintenance_date <= ?";
                $params[] = $filters['next_maintenance_to'];
            }
            
            if (isset($filters['status']) && $filters['status'] !== '') {
                if ($filters['status'] === 'overdue') {
                    $whereConditions[] = "ms.next_maintenance_date < CURDATE()";
                } elseif ($filters['status'] === 'upcoming') {
                    $whereConditions[] = "ms.next_maintenance_date >= CURDATE() AND ms.next_maintenance_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
                } elseif ($filters['status'] === 'future') {
                    $whereConditions[] = "ms.next_maintenance_date > DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
                }
            }
            
            if (!empty($filters['asset_tag'])) {
                $whereConditions[] = "a.asset_tag LIKE ?";
                $params[] = '%' . $filters['asset_tag'] . '%';
            }
            
            $whereClause = '';
            if (!empty($whereConditions)) {
                $whereClause = "WHERE " . implode(" AND ", $whereConditions);
            }
            
            $offset = ($page - 1) * $perPage;
            
            $query = "
                SELECT ms.*, a.name as asset_name, a.asset_tag, mt.name as maintenance_type_name, 
                       u.fullname as technician_name
                FROM maintenance_schedules ms
                JOIN assets a ON ms.asset_id = a.id
                JOIN maintenance_types mt ON ms.maintenance_type_id = mt.id
                LEFT JOIN users u ON ms.technician_id = u.id
                $whereClause
                ORDER BY ms.next_maintenance_date ASC
                LIMIT $perPage OFFSET $offset
            ";
            
            $schedules = $this->executeQuery($query, $params);
            
            // دریافت تعداد کل رکوردها
            $countQuery = "
                SELECT COUNT(*) as total
                FROM maintenance_schedules ms
                JOIN assets a ON ms.asset_id = a.id
                JOIN maintenance_types mt ON ms.maintenance_type_id = mt.id
                LEFT JOIN users u ON ms.technician_id = u.id
                $whereClause
            ";
            
            $countResult = $this->executeQuery($countQuery, $params);
            $totalCount = $countResult[0]['total'];
            $totalPages = ceil($totalCount / $perPage);
            
            return [
                'schedules' => $schedules,
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
            error_log("Error in getAllSchedules: " . $e->getMessage());
            return [
                'schedules' => [],
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
     * دریافت برنامه سرویس با شناسه مشخص
     */
    public function getScheduleById($id) {
        try {
            $query = "
                SELECT ms.*, a.name as asset_name, a.asset_tag, mt.name as maintenance_type_name, 
                       u.fullname as technician_name
                FROM maintenance_schedules ms
                JOIN assets a ON ms.asset_id = a.id
                JOIN maintenance_types mt ON ms.maintenance_type_id = mt.id
                LEFT JOIN users u ON ms.technician_id = u.id
                WHERE ms.id = ?
            ";
            
            $result = $this->executeQuery($query, [$id]);
            return $result ? $result[0] : null;
        } catch (Exception $e) {
            error_log("Error in getScheduleById: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * دریافت برنامه‌های سرویس یک تجهیز
     */
    public function getSchedulesByAssetId($assetId) {
        try {
            $query = "
                SELECT ms.*, mt.name as maintenance_type_name, u.fullname as technician_name
                FROM maintenance_schedules ms
                JOIN maintenance_types mt ON ms.maintenance_type_id = mt.id
                LEFT JOIN users u ON ms.technician_id = u.id
                WHERE ms.asset_id = ?
                ORDER BY ms.next_maintenance_date ASC
            ";
            
            return $this->executeQuery($query, [$assetId]);
        } catch (Exception $e) {
            error_log("Error in getSchedulesByAssetId: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * دریافت برنامه‌های سرویس یک نوع نگهداری
     */
    public function getSchedulesByTypeId($typeId) {
        try {
            $query = "
                SELECT ms.*, a.name as asset_name, a.asset_tag, u.fullname as technician_name
                FROM maintenance_schedules ms
                JOIN assets a ON ms.asset_id = a.id
                LEFT JOIN users u ON ms.technician_id = u.id
                WHERE ms.maintenance_type_id = ?
                ORDER BY ms.next_maintenance_date ASC
            ";
            
            return $this->executeQuery($query, [$typeId]);
        } catch (Exception $e) {
            error_log("Error in getSchedulesByTypeId: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * ایجاد برنامه سرویس جدید
     */
    public function createSchedule($data) {
        try {
            $query = "
                INSERT INTO maintenance_schedules 
                (asset_id, maintenance_type_id, last_maintenance_date, next_maintenance_date, technician_id, notes, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
            ";
            
            $result = $this->executeQuery($query, [
                $data['asset_id'],
                $data['maintenance_type_id'],
                $data['last_maintenance_date'] ?? null,
                $data['next_maintenance_date'],
                $data['technician_id'] ?? null,
                $data['notes'] ?? null
            ]);
            
            if ($result) {
                return $this->db instanceof PDO ? $this->db->lastInsertId() : $this->db->insert_id;
            }
            
            return false;
        } catch (Exception $e) {
            error_log("Error in createSchedule: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * به‌روزرسانی برنامه سرویس
     */
    public function updateSchedule($id, $data) {
        try {
            $query = "
                UPDATE maintenance_schedules 
                SET asset_id = ?, maintenance_type_id = ?, last_maintenance_date = ?, 
                    next_maintenance_date = ?, technician_id = ?, notes = ?, updated_at = NOW()
                WHERE id = ?
            ";
            
            return $this->executeQuery($query, [
                $data['asset_id'],
                $data['maintenance_type_id'],
                $data['last_maintenance_date'] ?? null,
                $data['next_maintenance_date'],
                $data['technician_id'] ?? null,
                $data['notes'] ?? null,
                $id
            ]);
        } catch (Exception $e) {
            error_log("Error in updateSchedule: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * حذف برنامه سرویس
     */
    public function deleteSchedule($id) {
        try {
            $query = "DELETE FROM maintenance_schedules WHERE id = ?";
            return $this->executeQuery($query, [$id]);
        } catch (Exception $e) {
            error_log("Error in deleteSchedule: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * ثبت انجام سرویس
     */
    public function recordMaintenance($scheduleId, $data) {
        try {
            // دریافت اطلاعات برنامه سرویس
            $schedule = $this->getScheduleById($scheduleId);
            
            if (!$schedule) {
                return false;
            }
            
            // شروع تراکنش
            if ($this->db instanceof PDO) {
                $this->db->beginTransaction();
            } else {
                $this->db->begin_transaction();
            }
            
            // ثبت لاگ انجام سرویس
            $query = "
                INSERT INTO maintenance_logs 
                (asset_id, maintenance_type_id, user_id, notes, performed_at, cost, completion_time, status, checklist_results, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ";
            
            $logResult = $this->executeQuery($query, [
                $schedule['asset_id'],
                $schedule['maintenance_type_id'],
                $data['performed_by'],
                $data['notes'] ?? '',
                $data['performed_date'],
                $data['cost'] ?? 0,
                $data['completion_time'] ?? 0,
                $data['status'] ?? 'completed',
                $data['checklist_results'] ?? null
            ]);
            
            if (!$logResult) {
                // بازگشت تراکنش در صورت خطا
                if ($this->db instanceof PDO) {
                    $this->db->rollBack();
                } else {
                    $this->db->rollback();
                }
                return false;
            }
            
            // به‌روزرسانی تاریخ آخرین سرویس و تاریخ سرویس بعدی
            $query = "
                UPDATE maintenance_schedules 
                SET last_maintenance_date = ?, next_maintenance_date = ?, updated_at = NOW()
                WHERE id = ?
            ";
            
            $updateResult = $this->executeQuery($query, [
                $data['performed_date'],
                $data['next_maintenance_date'],
                $scheduleId
            ]);
            
            if (!$updateResult) {
                // بازگشت تراکنش در صورت خطا
                if ($this->db instanceof PDO) {
                    $this->db->rollBack();
                } else {
                    $this->db->rollback();
                }
                return false;
            }
            
            // تایید تراکنش
            if ($this->db instanceof PDO) {
                $this->db->commit();
            } else {
                $this->db->commit();
            }
            
            return true;
        } catch (Exception $e) {
            // بازگشت تراکنش در صورت خطا
            if ($this->db instanceof PDO) {
                $this->db->rollBack();
            } else {
                $this->db->rollback();
            }
            
            error_log("Error in recordMaintenance: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * دریافت سرویس‌های نزدیک
     */
    public function getUpcomingSchedules($limit = 5) {
        try {
            $query = "
                SELECT ms.*, a.name as asset_name, a.asset_tag, mt.name as maintenance_type_name, 
                       u.fullname as technician_name
                FROM maintenance_schedules ms
                JOIN assets a ON ms.asset_id = a.id
                JOIN maintenance_types mt ON ms.maintenance_type_id = mt.id
                LEFT JOIN users u ON ms.technician_id = u.id
                WHERE ms.next_maintenance_date >= CURDATE()
                ORDER BY ms.next_maintenance_date ASC
                LIMIT ?
            ";
            
            return $this->executeQuery($query, [$limit]);
        } catch (Exception $e) {
            error_log("Error in getUpcomingSchedules: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * دریافت سرویس‌های معوق
     */
    public function getOverdueSchedules($limit = 5) {
        try {
            $query = "
                SELECT ms.*, a.name as asset_name, a.asset_tag, mt.name as maintenance_type_name, 
                       u.fullname as technician_name,
                       DATEDIFF(CURDATE(), ms.next_maintenance_date) as days_overdue
                FROM maintenance_schedules ms
                JOIN assets a ON ms.asset_id = a.id
                JOIN maintenance_types mt ON ms.maintenance_type_id = mt.id
                LEFT JOIN users u ON ms.technician_id = u.id
                WHERE ms.next_maintenance_date < CURDATE()
                ORDER BY ms.next_maintenance_date ASC
                LIMIT ?
            ";
            
            return $this->executeQuery($query, [$limit]);
        } catch (Exception $e) {
            error_log("Error in getOverdueSchedules: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * دریافت تاریخچه سرویس‌های انجام‌شده
     */
    public function getMaintenanceHistory($scheduleId) {
        try {
            $query = "
                SELECT ml.*, u.fullname as performed_by_name
                FROM maintenance_logs ml
                LEFT JOIN users u ON ml.user_id = u.id
                JOIN maintenance_schedules ms ON ml.asset_id = ms.asset_id AND ml.maintenance_type_id = ms.maintenance_type_id
                WHERE ms.id = ?
                ORDER BY ml.performed_at DESC
            ";
            
            return $this->executeQuery($query, [$scheduleId]);
        } catch (Exception $e) {
            error_log("Error in getMaintenanceHistory: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * دریافت برنامه‌های سرویس با فیلتر و صفحه‌بندی
     */
    public function getFilteredSchedules($filters, $page = 1, $perPage = 10, $sortBy = 'next_maintenance_date', $order = 'asc') {
        try {
            // اعتبارسنجی ستون مرتب‌سازی
            $validSortColumns = ['id', 'next_maintenance_date', 'last_maintenance_date', 'created_at', 'updated_at'];
            if (!in_array($sortBy, $validSortColumns)) {
                $sortBy = 'next_maintenance_date'; // مقدار پیش‌فرض
            }

            // اعتبارسنجی ترتیب مرتب‌سازی
            $order = strtolower($order) === 'desc' ? 'DESC' : 'ASC';

            // ساخت شرط WHERE
            $whereConditions = [];
            $params = [];

            if (!empty($filters['asset_tag'])) {
                $whereConditions[] = "a.asset_tag LIKE ?";
                $params[] = '%' . $filters['asset_tag'] . '%';
            }

            if (!empty($filters['type_id'])) {
                $whereConditions[] = "ms.maintenance_type_id = ?";
                $params[] = $filters['type_id'];
            }

            if (!empty($filters['status'])) {
                if ($filters['status'] === 'overdue') {
                    $whereConditions[] = "ms.next_maintenance_date < CURDATE()";
                } elseif ($filters['status'] === 'upcoming') {
                    $whereConditions[] = "ms.next_maintenance_date >= CURDATE() AND ms.next_maintenance_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
                } elseif ($filters['status'] === 'future') {
                    $whereConditions[] = "ms.next_maintenance_date > DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
                }
            }

            if (!empty($filters['date_from'])) {
                $whereConditions[] = "ms.next_maintenance_date >= ?";
                $params[] = $filters['date_from'];
            }

            if (!empty($filters['date_to'])) {
                $whereConditions[] = "ms.next_maintenance_date <= ?";
                $params[] = $filters['date_to'];
            }

            // ساخت بخش WHERE کوئری
            $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

            // محاسبه تعداد کل رکوردها
            $countQuery = "
                SELECT COUNT(*) as total 
                FROM maintenance_schedules ms
                JOIN assets a ON ms.asset_id = a.id
                JOIN maintenance_types mt ON ms.maintenance_type_id = mt.id
                LEFT JOIN users u ON ms.technician_id = u.id
                $whereClause
            ";
            
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
            $query = "
                SELECT ms.*, a.name as asset_name, a.asset_tag, mt.name as maintenance_type_name, 
                       u.fullname as technician_name,
                       CASE 
                           WHEN ms.next_maintenance_date < CURDATE() THEN 'overdue'
                           WHEN ms.next_maintenance_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'upcoming'
                           ELSE 'future'
                       END as status,
                       DATEDIFF(ms.next_maintenance_date, CURDATE()) as days_remaining
                FROM maintenance_schedules ms
                JOIN assets a ON ms.asset_id = a.id
                JOIN maintenance_types mt ON ms.maintenance_type_id = mt.id
                LEFT JOIN users u ON ms.technician_id = u.id
                $whereClause
                ORDER BY ms.$sortBy $order
                LIMIT $perPage OFFSET $offset
            ";
            
            $schedules = $this->executeQuery($query, $params);
            
            return [
                'schedules' => $schedules,
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
            error_log("Error in getFilteredSchedules: " . $e->getMessage());
            return [
                'schedules' => [],
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
     * دریافت آمار برنامه‌های نگهداری
     */
    public function getSchedulesStats() {
        try {
            $query = "
                SELECT 
                    COUNT(*) as total_schedules,
                    SUM(CASE WHEN next_maintenance_date < CURDATE() THEN 1 ELSE 0 END) as overdue_count,
                    SUM(CASE WHEN next_maintenance_date >= CURDATE() AND next_maintenance_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as upcoming_count,
                    SUM(CASE WHEN next_maintenance_date > DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as future_count,
                    COUNT(DISTINCT asset_id) as unique_assets,
                    COUNT(DISTINCT maintenance_type_id) as unique_types
                FROM maintenance_schedules
            ";
            
            $result = $this->executeQuery($query);
            return $result ? $result[0] : [
                'total_schedules' => 0,
                'overdue_count' => 0,
                'upcoming_count' => 0,
                'future_count' => 0,
                'unique_assets' => 0,
                'unique_types' => 0
            ];
        } catch (Exception $e) {
            error_log("Error in getSchedulesStats: " . $e->getMessage());
            return [
                'total_schedules' => 0,
                'overdue_count' => 0,
                'upcoming_count' => 0,
                'future_count' => 0,
                'unique_assets' => 0,
                'unique_types' => 0
            ];
        }
    }
    
    /**
     * بررسی وجود برنامه نگهداری برای تجهیز و نوع نگهداری
     */
    public function checkScheduleExists($assetId, $typeId) {
        try {
            $query = "
                SELECT COUNT(*) as count
                FROM maintenance_schedules
                WHERE asset_id = ? AND maintenance_type_id = ?
            ";
            
            $result = $this->executeQuery($query, [$assetId, $typeId]);
            return $result[0]['count'] > 0;
        } catch (Exception $e) {
            error_log("Error in checkScheduleExists: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * ایمپورت برنامه‌های نگهداری از فایل CSV
     */
    public function importSchedulesFromCSV($filePath) {
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
            $requiredColumns = ['asset_id', 'maintenance_type_id', 'next_maintenance_date'];
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
            $optionalColumns = ['last_maintenance_date', 'technician_id', 'notes'];
            foreach ($optionalColumns as $column) {
                $index = array_search($column, $header);
                $columnIndexes[$column] = $index !== false ? $index : null;
            }
            
            // شروع تراکنش
            if ($this->db instanceof PDO) {
                $this->db->beginTransaction();
            } else {
                $this->db->begin_transaction();
            }
            
            $imported = 0;
            $failed = 0;
            
            // پردازش داده‌ها
            while (($data = fgetcsv($file)) !== false) {
                $assetId = $data[$columnIndexes['asset_id']] ?? '';
                $typeId = $data[$columnIndexes['maintenance_type_id']] ?? '';
                $nextDate = $data[$columnIndexes['next_maintenance_date']] ?? '';
                
                if (empty($assetId) || empty($typeId) || empty($nextDate)) {
                    $failed++;
                    continue;
                }
                
                $scheduleData = [
                    'asset_id' => $assetId,
                    'maintenance_type_id' => $typeId,
                    'next_maintenance_date' => $nextDate
                ];
                
                if ($columnIndexes['last_maintenance_date'] !== null && isset($data[$columnIndexes['last_maintenance_date']])) {
                    $scheduleData['last_maintenance_date'] = $data[$columnIndexes['last_maintenance_date']];
                }
                
                if ($columnIndexes['technician_id'] !== null && isset($data[$columnIndexes['technician_id']])) {
                    $scheduleData['technician_id'] = $data[$columnIndexes['technician_id']];
                }
                
                if ($columnIndexes['notes'] !== null && isset($data[$columnIndexes['notes']])) {
                    $scheduleData['notes'] = $data[$columnIndexes['notes']];
                }
                
                // بررسی وجود برنامه نگهداری
                if ($this->checkScheduleExists($assetId, $typeId)) {
                    // به‌روزرسانی برنامه موجود
                    $query = "
                        UPDATE maintenance_schedules
                        SET next_maintenance_date = ?,
                            last_maintenance_date = ?,
                            technician_id = ?,
                            notes = ?,
                            updated_at = NOW()
                        WHERE asset_id = ? AND maintenance_type_id = ?
                    ";
                    
                    $result = $this->executeQuery($query, [
                        $scheduleData['next_maintenance_date'],
                        $scheduleData['last_maintenance_date'] ?? null,
                        $scheduleData['technician_id'] ?? null,
                        $scheduleData['notes'] ?? null,
                        $assetId,
                        $typeId
                    ]);
                } else {
                    // ایجاد برنامه جدید
                    $result = $this->createSchedule($scheduleData);
                }
                
                if ($result) {
                    $imported++;
                } else {
                    $failed++;
                }
            }
            
            fclose($file);
            
            if ($imported > 0) {
                // تایید تراکنش
                if ($this->db instanceof PDO) {
                    $this->db->commit();
                } else {
                    $this->db->commit();
                }
                
                return [
                    'success' => true,
                    'message' => "$imported برنامه نگهداری با موفقیت وارد شد. $failed مورد ناموفق بود.",
                    'imported' => $imported,
                    'failed' => $failed
                ];
            } else {
                // بازگشت تراکنش
                if ($this->db instanceof PDO) {
                    $this->db->rollBack();
                } else {
                    $this->db->rollback();
                }
                
                return [
                    'success' => false,
                    'message' => "هیچ برنامه نگهداری وارد نشد. $failed مورد ناموفق بود.",
                    'imported' => 0,
                    'failed' => $failed
                ];
            }
        } catch (Exception $e) {
            if (isset($file)) {
                fclose($file);
            }
            
            // بازگشت تراکنش
            if ($this->db instanceof PDO) {
                $this->db->rollBack();
            } else {
                $this->db->rollback();
            }
            
            error_log("Error in importSchedulesFromCSV: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در وارد کردن داده‌ها: ' . $e->getMessage(),
                'imported' => 0,
                'failed' => 0
            ];
        }
    }
    
    /**
     * اکسپورت برنامه‌های نگهداری به فایل CSV
     */
    public function exportSchedulesToCSV($filePath, $filters = []) {
        try {
            // دریافت برنامه‌های نگهداری با فیلتر
            $result = $this->getAllSchedules($filters, 1, 10000); // تعداد زیاد برای دریافت همه رکوردها
            $schedules = $result['schedules'];
            
            if (empty($schedules)) {
                return false;
            }
            
            // ایجاد فایل CSV
            $file = fopen($filePath, 'w');
            if (!$file) {
                return false;
            }
            
            // نوشتن سطر هدر
            fputcsv($file, [
                'id', 'asset_id', 'asset_tag', 'asset_name', 'maintenance_type_id', 'maintenance_type_name',
                'last_maintenance_date', 'next_maintenance_date', 'technician_id', 'technician_name',
                'notes', 'status', 'days_remaining', 'created_at', 'updated_at'
            ]);
            
            // نوشتن داده‌ها
            foreach ($schedules as $schedule) {
                // محاسبه وضعیت و روزهای باقی‌مانده
                $status = '';
                $daysRemaining = 0;
                
                if (strtotime($schedule['next_maintenance_date']) < time()) {
                    $status = 'overdue';
                    $daysRemaining = floor((time() - strtotime($schedule['next_maintenance_date'])) / 86400) * -1;
                } else {
                    $daysRemaining = floor((strtotime($schedule['next_maintenance_date']) - time()) / 86400);
                    if ($daysRemaining <= 30) {
                        $status = 'upcoming';
                    } else {
                        $status = 'future';
                    }
                }
                
                fputcsv($file, [
                    $schedule['id'],
                    $schedule['asset_id'],
                    $schedule['asset_tag'],
                    $schedule['asset_name'],
                    $schedule['maintenance_type_id'],
                    $schedule['maintenance_type_name'],
                    $schedule['last_maintenance_date'],
                    $schedule['next_maintenance_date'],
                    $schedule['technician_id'],
                    $schedule['technician_name'],
                    $schedule['notes'] ?? '',
                    $status,
                    $daysRemaining,
                    $schedule['created_at'] ?? '',
                    $schedule['updated_at'] ?? ''
                ]);
            }
            
            fclose($file);
            return true;
        } catch (Exception $e) {
            if (isset($file)) {
                fclose($file);
            }
            error_log("Error in exportSchedulesToCSV: " . $e->getMessage());
            return false;
        }
    }

    /**
     * ایجاد برنامه‌های نگهداری خودکار برای یک تجهیز جدید
     * 
     * @param int $assetId شناسه تجهیز
     * @return array نتیجه عملیات
     */
    public function createAutoSchedulesForAsset($assetId) {
        try {
            // دریافت اطلاعات تجهیز
            $query = "SELECT a.id, a.model_id, am.category_id 
                    FROM assets a 
                    JOIN asset_models am ON a.model_id = am.id 
                    WHERE a.id = ?";
            $assetInfo = $this->executeQuery($query, [$assetId]);
            
            if (empty($assetInfo)) {
                return [
                    'success' => false,
                    'message' => 'تجهیز مورد نظر یافت نشد.',
                    'created' => 0
                ];
            }
            
            $categoryId = $assetInfo[0]['category_id'];
            
            // دریافت انواع نگهداری اجباری یا مرتبط با این دسته‌بندی
            $query = "SELECT * FROM maintenance_types 
                    WHERE is_required = 1 OR category = ? OR category IS NULL";
            $maintenanceTypes = $this->executeQuery($query, [$categoryId]);
            
            if (empty($maintenanceTypes)) {
                return [
                    'success' => false,
                    'message' => 'هیچ نوع نگهداری مناسبی برای این تجهیز یافت نشد.',
                    'created' => 0
                ];
            }
            
            // شروع تراکنش
            if ($this->db instanceof PDO) {
                $this->db->beginTransaction();
            } else {
                $this->db->begin_transaction();
            }
            
            $created = 0;
            $today = date('Y-m-d');
            
            foreach ($maintenanceTypes as $type) {
                // بررسی وجود برنامه نگهداری
                if (!$this->checkScheduleExists($assetId, $type['id'])) {
                    // محاسبه تاریخ نگهداری بعدی
                    $nextDate = date('Y-m-d', strtotime("+{$type['interval_days']} days"));
                    
                    $scheduleData = [
                        'asset_id' => $assetId,
                        'maintenance_type_id' => $type['id'],
                        'next_maintenance_date' => $nextDate,
                        'notes' => 'برنامه نگهداری خودکار ایجاد شده در ' . $today
                    ];
                    
                    $result = $this->createSchedule($scheduleData);
                    
                    if ($result) {
                        $created++;
                    }
                }
            }
            
            if ($created > 0) {
                // تایید تراکنش
                if ($this->db instanceof PDO) {
                    $this->db->commit();
                } else {
                    $this->db->commit();
                }
                
                return [
                    'success' => true,
                    'message' => "$created برنامه نگهداری با موفقیت ایجاد شد.",
                    'created' => $created
                ];
            } else {
                // بازگشت تراکنش
                if ($this->db instanceof PDO) {
                    $this->db->rollBack();
                } else {
                    $this->db->rollback();
                }
                
                return [
                    'success' => false,
                    'message' => 'هیچ برنامه نگهداری جدیدی ایجاد نشد.',
                    'created' => 0
                ];
            }
        } catch (Exception $e) {
            // بازگشت تراکنش در صورت خطا
            if ($this->db instanceof PDO) {
                $this->db->rollBack();
            } else {
                $this->db->rollback();
            }
            
            error_log("Error in createAutoSchedulesForAsset: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در ایجاد برنامه‌های نگهداری: ' . $e->getMessage(),
                'created' => 0
            ];
        }
    }

    /**
     * به‌روزرسانی تاریخ نگهداری بعدی بر اساس فاصله زمانی نوع نگهداری
     * 
     * @param int $scheduleId شناسه برنامه نگهداری
     * @param string|null $fromDate تاریخ مبنا (اختیاری)
     * @return bool نتیجه عملیات
     */
    public function updateNextMaintenanceDate($scheduleId, $fromDate = null) {
        try {
            // دریافت اطلاعات برنامه نگهداری و نوع نگهداری
            $query = "
                SELECT ms.*, mt.interval_days
                FROM maintenance_schedules ms
                JOIN maintenance_types mt ON ms.maintenance_type_id = mt.id
                WHERE ms.id = ?
            ";
            
            $result = $this->executeQuery($query, [$scheduleId]);
            
            if (empty($result)) {
                return false;
            }
            
            $schedule = $result[0];
            $intervalDays = $schedule['interval_days'];
            
            // تعیین تاریخ مبنا
            $baseDate = $fromDate ?? $schedule['last_maintenance_date'] ?? date('Y-m-d');
            
            // محاسبه تاریخ نگهداری بعدی
            $nextDate = date('Y-m-d', strtotime("$baseDate +$intervalDays days"));
            
            // به‌روزرسانی برنامه نگهداری
            $updateQuery = "
                UPDATE maintenance_schedules
                SET next_maintenance_date = ?, updated_at = NOW()
                WHERE id = ?
            ";
            
            return $this->executeQuery($updateQuery, [$nextDate, $scheduleId]);
        } catch (Exception $e) {
            error_log("Error in updateNextMaintenanceDate: " . $e->getMessage());
            return false;
        }
    }

    /**
     * دریافت برنامه‌های نگهداری بر اساس تکنسین
     * 
     * @param int $technicianId شناسه تکنسین
     * @param array $filters فیلترها (اختیاری)
     * @return array برنامه‌های نگهداری
     */
    public function getSchedulesByTechnician($technicianId, $filters = []) {
        try {
            $whereConditions = ["ms.technician_id = ?"];
            $params = [$technicianId];
            
            if (!empty($filters['status'])) {
                if ($filters['status'] === 'overdue') {
                    $whereConditions[] = "ms.next_maintenance_date < CURDATE()";
                } elseif ($filters['status'] === 'upcoming') {
                    $whereConditions[] = "ms.next_maintenance_date >= CURDATE() AND ms.next_maintenance_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
                } elseif ($filters['status'] === 'future') {
                    $whereConditions[] = "ms.next_maintenance_date > DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
                }
            }
            
            if (!empty($filters['date_from'])) {
                $whereConditions[] = "ms.next_maintenance_date >= ?";
                $params[] = $filters['date_from'];
            }
            
            if (!empty($filters['date_to'])) {
                $whereConditions[] = "ms.next_maintenance_date <= ?";
                $params[] = $filters['date_to'];
            }
            
            $whereClause = "WHERE " . implode(" AND ", $whereConditions);
            
            $query = "
                SELECT ms.*, a.name as asset_name, a.asset_tag, mt.name as maintenance_type_name,
                    CASE 
                        WHEN ms.next_maintenance_date < CURDATE() THEN 'overdue'
                        WHEN ms.next_maintenance_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'upcoming'
                        ELSE 'future'
                    END as status,
                    DATEDIFF(ms.next_maintenance_date, CURDATE()) as days_remaining
                FROM maintenance_schedules ms
                JOIN assets a ON ms.asset_id = a.id
                JOIN maintenance_types mt ON ms.maintenance_type_id = mt.id
                $whereClause
                ORDER BY ms.next_maintenance_date ASC
            ";
            
            return $this->executeQuery($query, $params);
        } catch (Exception $e) {
            error_log("Error in getSchedulesByTechnician: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت برنامه‌های نگهداری امروز
     * 
     * @return array برنامه‌های نگهداری امروز
     */
    public function getTodaySchedules() {
        try {
            $query = "
                SELECT ms.*, a.name as asset_name, a.asset_tag, mt.name as maintenance_type_name, 
                    u.fullname as technician_name
                FROM maintenance_schedules ms
                JOIN assets a ON ms.asset_id = a.id
                JOIN maintenance_types mt ON ms.maintenance_type_id = mt.id
                LEFT JOIN users u ON ms.technician_id = u.id
                WHERE DATE(ms.next_maintenance_date) = CURDATE()
                ORDER BY ms.next_maintenance_date ASC
            ";
            
            return $this->executeQuery($query);
        } catch (Exception $e) {
            error_log("Error in getTodaySchedules: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت برنامه‌های نگهداری این هفته
     * 
     * @return array برنامه‌های نگهداری این هفته
     */
    public function getThisWeekSchedules() {
        try {
            $query = "
                SELECT ms.*, a.name as asset_name, a.asset_tag, mt.name as maintenance_type_name, 
                    u.fullname as technician_name,
                    DATEDIFF(ms.next_maintenance_date, CURDATE()) as days_remaining
                FROM maintenance_schedules ms
                JOIN assets a ON ms.asset_id = a.id
                JOIN maintenance_types mt ON ms.maintenance_type_id = mt.id
                LEFT JOIN users u ON ms.technician_id = u.id
                WHERE ms.next_maintenance_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                ORDER BY ms.next_maintenance_date ASC
            ";
            
            return $this->executeQuery($query);
        } catch (Exception $e) {
            error_log("Error in getThisWeekSchedules: " . $e->getMessage());
            return [];
        }
    }

    /**
     * تخصیص تکنسین به چندین برنامه نگهداری
     * 
     * @param array $scheduleIds آرایه‌ای از شناسه‌های برنامه نگهداری
     * @param int $technicianId شناسه تکنسین
     * @return array نتیجه عملیات
     */
    public function assignTechnicianToBulkSchedules($scheduleIds, $technicianId) {
        try {
            if (empty($scheduleIds) || !is_array($scheduleIds)) {
                return [
                    'success' => false,
                    'message' => 'هیچ برنامه نگهداری انتخاب نشده است.',
                    'updated' => 0
                ];
            }
            
            // شروع تراکنش
            if ($this->db instanceof PDO) {
                $this->db->beginTransaction();
            } else {
                $this->db->begin_transaction();
            }
            
            $updated = 0;
            
            foreach ($scheduleIds as $scheduleId) {
                $query = "
                    UPDATE maintenance_schedules
                    SET technician_id = ?, updated_at = NOW()
                    WHERE id = ?
                ";
                
                $result = $this->executeQuery($query, [$technicianId, $scheduleId]);
                
                if ($result) {
                    $updated++;
                }
            }
            
            if ($updated > 0) {
                // تایید تراکنش
                if ($this->db instanceof PDO) {
                    $this->db->commit();
                } else {
                    $this->db->commit();
                }
                
                return [
                    'success' => true,
                    'message' => "تکنسین به $updated برنامه نگهداری با موفقیت تخصیص داده شد.",
                    'updated' => $updated
                ];
            } else {
                // بازگشت تراکنش
                if ($this->db instanceof PDO) {
                    $this->db->rollBack();
                } else {
                    $this->db->rollback();
                }
                
                return [
                    'success' => false,
                    'message' => 'هیچ برنامه نگهداری به‌روزرسانی نشد.',
                    'updated' => 0
                ];
            }
        } catch (Exception $e) {
            // بازگشت تراکنش در صورت خطا
            if ($this->db instanceof PDO) {
                $this->db->rollBack();
            } else {
                $this->db->rollback();
            }
            
            error_log("Error in assignTechnicianToBulkSchedules: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در تخصیص تکنسین: ' . $e->getMessage(),
                'updated' => 0
            ];
        }
    }

    /**
     * به‌روزرسانی تاریخ نگهداری بعدی برای چندین برنامه نگهداری
     * 
     * @param array $scheduleIds آرایه‌ای از شناسه‌های برنامه نگهداری
     * @param string $nextDate تاریخ نگهداری بعدی
     * @return array نتیجه عملیات
     */
    public function updateBulkNextMaintenanceDate($scheduleIds, $nextDate) {
        try {
            if (empty($scheduleIds) || !is_array($scheduleIds) || empty($nextDate)) {
                return [
                    'success' => false,
                    'message' => 'پارامترهای ورودی نامعتبر هستند.',
                    'updated' => 0
                ];
            }
            
            // شروع تراکنش
            if ($this->db instanceof PDO) {
                $this->db->beginTransaction();
            } else {
                $this->db->begin_transaction();
            }
            
            $updated = 0;
            
            foreach ($scheduleIds as $scheduleId) {
                $query = "
                    UPDATE maintenance_schedules
                    SET next_maintenance_date = ?, updated_at = NOW()
                    WHERE id = ?
                ";
                
                $result = $this->executeQuery($query, [$nextDate, $scheduleId]);
                
                if ($result) {
                    $updated++;
                }
            }
            
            if ($updated > 0) {
                // تایید تراکنش
                if ($this->db instanceof PDO) {
                    $this->db->commit();
                } else {
                    $this->db->commit();
                }
                
                return [
                    'success' => true,
                    'message' => "تاریخ نگهداری بعدی برای $updated برنامه نگهداری با موفقیت به‌روزرسانی شد.",
                    'updated' => $updated
                ];
            } else {
                // بازگشت تراکنش
                if ($this->db instanceof PDO) {
                    $this->db->rollBack();
                } else {
                    $this->db->rollback();
                }
                
                return [
                    'success' => false,
                    'message' => 'هیچ برنامه نگهداری به‌روزرسانی نشد.',
                    'updated' => 0
                ];
            }
        } catch (Exception $e) {
            // بازگشت تراکنش در صورت خطا
            if ($this->db instanceof PDO) {
                $this->db->rollBack();
            } else {
                $this->db->rollback();
            }
            
            error_log("Error in updateBulkNextMaintenanceDate: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در به‌روزرسانی تاریخ نگهداری بعدی: ' . $e->getMessage(),
                'updated' => 0
            ];
        }
    }

    /**
     * دریافت آمار نگهداری بر اساس تجهیز
     * 
     * @param int $assetId شناسه تجهیز
     * @return array آمار نگهداری
     */
    public function getMaintenanceStatsByAsset($assetId) {
        try {
            $query = "
                SELECT 
                    COUNT(ms.id) as total_schedules,
                    SUM(CASE WHEN ms.next_maintenance_date < CURDATE() THEN 1 ELSE 0 END) as overdue_count,
                    SUM(CASE WHEN ms.next_maintenance_date >= CURDATE() AND ms.next_maintenance_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as upcoming_count,
                    (SELECT COUNT(*) FROM maintenance_logs ml WHERE ml.asset_id = ?) as completed_count,
                    (SELECT MAX(ml.performed_at) FROM maintenance_logs ml WHERE ml.asset_id = ?) as last_maintenance_date,
                    (SELECT MIN(ms2.next_maintenance_date) FROM maintenance_schedules ms2 WHERE ms2.asset_id = ? AND ms2.next_maintenance_date >= CURDATE()) as next_maintenance_date
                FROM maintenance_schedules ms
                WHERE ms.asset_id = ?
            ";
            
            $result = $this->executeQuery($query, [$assetId, $assetId, $assetId, $assetId]);
            return $result ? $result[0] : [
                'total_schedules' => 0,
                'overdue_count' => 0,
                'upcoming_count' => 0,
                'completed_count' => 0,
                'last_maintenance_date' => null,
                'next_maintenance_date' => null
            ];
        } catch (Exception $e) {
            error_log("Error in getMaintenanceStatsByAsset: " . $e->getMessage());
            return [
                'total_schedules' => 0,
                'overdue_count' => 0,
                'upcoming_count' => 0,
                'completed_count' => 0,
                'last_maintenance_date' => null,
                'next_maintenance_date' => null
            ];
        }
    }

    /**
     * دریافت تجهیز‌های نیازمند نگهداری
     * 
     * @param int $limit محدودیت تعداد نتایج
     * @return array تجهیز‌های نیازمند نگهداری
     */
    public function getAssetsNeedingMaintenance($limit = 10) {
        try {
            $query = "
                SELECT a.id, a.name, a.asset_tag, ac.name as category_name,
                    COUNT(ms.id) as overdue_schedules,
                    MIN(ms.next_maintenance_date) as earliest_maintenance_date,
                    DATEDIFF(CURDATE(), MIN(ms.next_maintenance_date)) as days_overdue
                FROM assets a
                JOIN asset_models am ON a.model_id = am.id
                JOIN asset_categories ac ON am.category_id = ac.id
                JOIN maintenance_schedules ms ON a.id = ms.asset_id
                WHERE ms.next_maintenance_date < CURDATE()
                GROUP BY a.id, a.name, a.asset_tag, ac.name
                ORDER BY days_overdue DESC
                LIMIT ?
            ";
            
            return $this->executeQuery($query, [$limit]);
        } catch (Exception $e) {
            error_log("Error in getAssetsNeedingMaintenance: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت تقویم نگهداری برای یک بازه زمانی
     * 
     * @param string $startDate تاریخ شروع
     * @param string $endDate تاریخ پایان
     * @return array تقویم نگهداری
     */
    public function getMaintenanceCalendar($startDate, $endDate) {
        try {
            $query = "
                SELECT ms.id, ms.next_maintenance_date as date, a.name as asset_name, a.asset_tag,
                    mt.name as maintenance_type, u.fullname as technician_name,
                    CASE 
                        WHEN ms.next_maintenance_date < CURDATE() THEN 'overdue'
                        WHEN ms.next_maintenance_date = CURDATE() THEN 'today'
                        WHEN ms.next_maintenance_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 'this-week'
                        WHEN ms.next_maintenance_date BETWEEN DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'upcoming'
                        ELSE 'future'
                    END as status
                FROM maintenance_schedules ms
                JOIN assets a ON ms.asset_id = a.id
                JOIN maintenance_types mt ON ms.maintenance_type_id = mt.id
                LEFT JOIN users u ON ms.technician_id = u.id
                WHERE ms.next_maintenance_date BETWEEN ? AND ?
                ORDER BY ms.next_maintenance_date ASC
            ";
            
            return $this->executeQuery($query, [$startDate, $endDate]);
        } catch (Exception $e) {
            error_log("Error in getMaintenanceCalendar: " . $e->getMessage());
            return [];
        }
    }

    /**
     * بررسی و به‌روزرسانی برنامه‌های نگهداری معوق
     * 
     * @return array نتیجه عملیات
     */
    public function checkAndUpdateOverdueSchedules() {
        try {
            // دریافت برنامه‌های معوق
            $query = "
                SELECT ms.id, ms.asset_id, ms.maintenance_type_id, ms.next_maintenance_date,
                    a.name as asset_name, a.asset_tag, mt.name as maintenance_type_name,
                    DATEDIFF(CURDATE(), ms.next_maintenance_date) as days_overdue
                FROM maintenance_schedules ms
                JOIN assets a ON ms.asset_id = a.id
                JOIN maintenance_types mt ON ms.maintenance_type_id = mt.id
                WHERE ms.next_maintenance_date < CURDATE()
                ORDER BY days_overdue DESC
            ";
            
            $overdueSchedules = $this->executeQuery($query);
            
            if (empty($overdueSchedules)) {
                return [
                    'success' => true,
                    'message' => 'هیچ برنامه نگهداری معوقی یافت نشد.',
                    'overdue_count' => 0,
                    'notified' => 0
                ];
            }
            
            $overdue_count = count($overdueSchedules);
            $notified = 0;
            
            // در اینجا می‌توانید کد اعلان یا ارسال ایمیل را اضافه کنید
            // به عنوان مثال، ثبت در جدول اعلان‌ها یا ارسال ایمیل به مدیران و تکنسین‌ها
            
            return [
                'success' => true,
                'message' => "$overdue_count برنامه نگهداری معوق یافت شد و $notified اعلان ارسال شد.",
                'overdue_count' => $overdue_count,
                'notified' => $notified,
                'overdue_schedules' => $overdueSchedules
            ];
        } catch (Exception $e) {
            error_log("Error in checkAndUpdateOverdueSchedules: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در بررسی برنامه‌های نگهداری معوق: ' . $e->getMessage(),
                'overdue_count' => 0,
                'notified' => 0
            ];
        }
    }

    /**
     * دریافت آمار کلی برنامه‌های نگهداری برای داشبورد
     * 
     * @return array آمار کلی
     */
    public function getDashboardStats() {
        try {
            $query = "
                SELECT 
                    (SELECT COUNT(*) FROM maintenance_schedules) as total_schedules,
                    (SELECT COUNT(*) FROM maintenance_schedules WHERE next_maintenance_date < CURDATE()) as overdue_schedules,
                    (SELECT COUNT(*) FROM maintenance_schedules WHERE next_maintenance_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)) as due_this_week,
                    (SELECT COUNT(*) FROM maintenance_schedules WHERE next_maintenance_date BETWEEN DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)) as due_this_month,
                    (SELECT COUNT(*) FROM maintenance_logs WHERE performed_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) as completed_last_month,
                    (SELECT COUNT(DISTINCT asset_id) FROM maintenance_schedules) as assets_with_schedules,
                    (SELECT COUNT(DISTINCT maintenance_type_id) FROM maintenance_schedules) as maintenance_types_used
            ";
            
            $result = $this->executeQuery($query);
            return $result ? $result[0] : [
                'total_schedules' => 0,
                'overdue_schedules' => 0,
                'due_this_week' => 0,
                'due_this_month' => 0,
                'completed_last_month' => 0,
                'assets_with_schedules' => 0,
                'maintenance_types_used' => 0
            ];
        } catch (Exception $e) {
            error_log("Error in getDashboardStats: " . $e->getMessage());
            return [
                'total_schedules' => 0,
                'overdue_schedules' => 0,
                'due_this_week' => 0,
                'due_this_month' => 0,
                'completed_last_month' => 0,
                'assets_with_schedules' => 0,
                'maintenance_types_used' => 0
            ];
        }
    }
}