<?php
require_once __DIR__ . '/../core/Database.php';

class MaintenanceLog {
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
     * دریافت تمام سوابق نگهداری
     * 
     * @return array لیست تمام سوابق نگهداری
     */
    public function getAllLogs() {
        try {
            $query = "SELECT ml.*, a.asset_tag, mt.name as type_name, u.fullname as performed_by
                      FROM maintenance_logs ml
                      JOIN assets a ON ml.asset_id = a.id
                      JOIN maintenance_types mt ON ml.maintenance_type_id = mt.id
                      LEFT JOIN users u ON ml.user_id = u.id
                      ORDER BY ml.performed_at DESC";
            return $this->executeQuery($query);
        } catch (Exception $e) {
            error_log("Error in getAllLogs: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * دریافت سوابق نگهداری با صفحه‌بندی
     * 
     * @param int $page شماره صفحه
     * @param int $perPage تعداد آیتم در هر صفحه
     * @return array آرایه‌ای شامل سوابق نگهداری و اطلاعات صفحه‌بندی
     */
    public function getLogsPaginated($page = 1, $perPage = 10) {
        try {
            // محاسبه تعداد کل رکوردها
            $countQuery = "SELECT COUNT(*) as total FROM maintenance_logs";
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
            
            $query = "SELECT ml.*, a.asset_tag, mt.name as type_name, u.fullname as performed_by
                      FROM maintenance_logs ml
                      JOIN assets a ON ml.asset_id = a.id
                      JOIN maintenance_types mt ON ml.maintenance_type_id = mt.id
                      LEFT JOIN users u ON ml.user_id = u.id
                      ORDER BY ml.performed_at DESC
                      LIMIT $perPage OFFSET $offset";
            
            $logs = $this->executeQuery($query);
            
            return [
                'logs' => $logs,
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
            error_log("Error in getLogsPaginated: " . $e->getMessage());
            return [
                'logs' => [],
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
     * دریافت یک سابقه نگهداری با شناسه مشخص
     * 
     * @param int $id شناسه سابقه نگهداری
     * @return array|null سابقه نگهداری یا null در صورت عدم وجود
     */
    public function getLogById($id) {
        try {
            $query = "SELECT ml.*, a.asset_tag, mt.name as type_name, u.fullname as performed_by
                      FROM maintenance_logs ml
                      JOIN assets a ON ml.asset_id = a.id
                      JOIN maintenance_types mt ON ml.maintenance_type_id = mt.id
                      LEFT JOIN users u ON ml.user_id = u.id
                      WHERE ml.id = ?";
            $result = $this->executeQuery($query, [$id]);
            return $result ? $result[0] : null;
        } catch (Exception $e) {
            error_log("Error in getLogById: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * دریافت سوابق نگهداری برای یک تجهیز
     * 
     * @param int $assetId شناسه تجهیز
     * @return array لیست سوابق نگهداری
     */
    public function getLogsByAssetId($assetId) {
        try {
            $query = "SELECT ml.*, mt.name as type_name, u.fullname as performed_by
                      FROM maintenance_logs ml
                      JOIN maintenance_types mt ON ml.maintenance_type_id = mt.id
                      LEFT JOIN users u ON ml.user_id = u.id
                      WHERE ml.asset_id = ?
                      ORDER BY ml.performed_at DESC";
            return $this->executeQuery($query, [$assetId]);
        } catch (Exception $e) {
            error_log("Error in getLogsByAssetId: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * دریافت سوابق نگهداری اخیر
     * 
     * @param int $limit تعداد سوابق
     * @return array لیست سوابق نگهداری
     */
    public function getRecentLogs($limit = 5) {
        try {
            $limit = (int)$limit;
            $query = "SELECT ml.*, a.asset_tag, mt.name as type_name, u.fullname as performed_by
                      FROM maintenance_logs ml
                      JOIN assets a ON ml.asset_id = a.id
                      JOIN maintenance_types mt ON ml.maintenance_type_id = mt.id
                      LEFT JOIN users u ON ml.user_id = u.id
                      ORDER BY ml.performed_at DESC
                      LIMIT $limit";
            return $this->executeQuery($query);
        } catch (Exception $e) {
            error_log("Error in getRecentLogs: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * ایجاد سابقه نگهداری جدید
     * 
     * @param int $assetId شناسه تجهیز
     * @param int $typeId شناسه نوع نگهداری
     * @param int $userId شناسه کاربر
     * @param string $notes یادداشت‌ها
     * @param array $additionalData داده‌های اضافی (اختیاری)
     * @return int|bool شناسه سابقه نگهداری جدید یا false در صورت خطا
     */
    public function createLog($assetId, $typeId, $userId, $notes, $additionalData = []) {
        try {
            // استخراج داده‌های اضافی
            $performedAt = $additionalData['performed_at'] ?? date('Y-m-d H:i:s');
            $cost = $additionalData['cost'] ?? 0;
            $completionTime = $additionalData['completion_time'] ?? 0;
            $status = $additionalData['status'] ?? 'completed';
            $checklistResults = $additionalData['checklist_results'] ?? null;
            
            $query = "INSERT INTO maintenance_logs (
                        asset_id, maintenance_type_id, user_id, notes, performed_at, 
                        cost, completion_time, status, checklist_results, created_at, updated_at
                      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
            
            $result = $this->executeQuery($query, [
                $assetId, $typeId, $userId, $notes, $performedAt,
                $cost, $completionTime, $status, $checklistResults
            ]);
            
            if ($result) {
                // به‌روزرسانی برنامه نگهداری مرتبط
                $this->updateRelatedSchedule($assetId, $typeId, $performedAt);
                
                return $this->db->lastInsertId();
            }
            
            return false;
        } catch (Exception $e) {
            error_log("Error in createLog: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * به‌روزرسانی برنامه نگهداری مرتبط
     * 
     * @param int $assetId شناسه تجهیز
     * @param int $typeId شناسه نوع نگهداری
     * @param string $performedAt تاریخ انجام
     * @return bool نتیجه عملیات
     */
    private function updateRelatedSchedule($assetId, $typeId, $performedAt) {
        try {
            // دریافت اطلاعات نوع نگهداری
            $query = "SELECT interval_days FROM maintenance_types WHERE id = ?";
            $typeResult = $this->executeQuery($query, [$typeId]);
            
            if (!$typeResult) {
                return false;
            }
            
            $intervalDays = $typeResult[0]['interval_days'] ?? 0;
            
            // بررسی وجود برنامه نگهداری
            $query = "SELECT id FROM maintenance_schedules 
                      WHERE asset_id = ? AND maintenance_type_id = ?";
            $scheduleResult = $this->executeQuery($query, [$assetId, $typeId]);
            
            if ($scheduleResult) {
                // به‌روزرسانی برنامه موجود
                $nextDate = date('Y-m-d', strtotime($performedAt . " + $intervalDays days"));
                $query = "UPDATE maintenance_schedules 
                          SET last_maintenance_date = ?, next_maintenance_date = ?, updated_at = NOW()
                          WHERE asset_id = ? AND maintenance_type_id = ?";
                return $this->executeQuery($query, [$performedAt, $nextDate, $assetId, $typeId]);
            } else {
                // ایجاد برنامه جدید
                $nextDate = date('Y-m-d', strtotime($performedAt . " + $intervalDays days"));
                $query = "INSERT INTO maintenance_schedules 
                          (asset_id, maintenance_type_id, last_maintenance_date, next_maintenance_date, created_at, updated_at)
                          VALUES (?, ?, ?, ?, NOW(), NOW())";
                return $this->executeQuery($query, [$assetId, $typeId, $performedAt, $nextDate]);
            }
        } catch (Exception $e) {
            error_log("Error in updateRelatedSchedule: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * به‌روزرسانی سابقه نگهداری
     * 
     * @param int $id شناسه سابقه نگهداری
     * @param array $data داده‌های به‌روزرسانی
     * @return bool نتیجه عملیات
     */
    public function updateLog($id, $data) {
        try {
            // ساخت پارامترها و بخش‌های کوئری برای فیلدهای به‌روزرسانی
            $updateFields = [];
            $params = [];
            
            if (isset($data['notes'])) {
                $updateFields[] = "notes = ?";
                $params[] = $data['notes'];
            }
            
            if (isset($data['performed_at'])) {
                $updateFields[] = "performed_at = ?";
                $params[] = $data['performed_at'];
            }
            
            if (isset($data['cost'])) {
                $updateFields[] = "cost = ?";
                $params[] = $data['cost'];
            }
            
            if (isset($data['completion_time'])) {
                $updateFields[] = "completion_time = ?";
                $params[] = $data['completion_time'];
            }
            
            if (isset($data['status'])) {
                $updateFields[] = "status = ?";
                $params[] = $data['status'];
            }
            
            if (isset($data['checklist_results'])) {
                $updateFields[] = "checklist_results = ?";
                $params[] = $data['checklist_results'];
            }
            
            if (isset($data['user_id'])) {
                $updateFields[] = "user_id = ?";
                $params[] = $data['user_id'];
            }
            
            if (empty($updateFields)) {
                return false;
            }
            
            $updateFields[] = "updated_at = NOW()";
            
            // افزودن شناسه به پارامترها
            $params[] = $id;
            
            $query = "UPDATE maintenance_logs SET " . implode(", ", $updateFields) . " WHERE id = ?";
            return $this->executeQuery($query, $params);
        } catch (Exception $e) {
            error_log("Error in updateLog: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * حذف سابقه نگهداری
     * 
     * @param int $id شناسه سابقه نگهداری
     * @return bool نتیجه عملیات
     */
    public function deleteLog($id) {
        try {
            $query = "DELETE FROM maintenance_logs WHERE id = ?";
            return $this->executeQuery($query, [$id]);
        } catch (Exception $e) {
            error_log("Error in deleteLog: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * دریافت سوابق نگهداری با فیلتر و صفحه‌بندی
     * 
     * @param array $filters فیلترها
     * @param int $page شماره صفحه
     * @param int $perPage تعداد آیتم در هر صفحه
     * @param string $sortBy ستون مرتب‌سازی
     * @param string $order ترتیب مرتب‌سازی
     * @return array آرایه‌ای شامل سوابق نگهداری و اطلاعات صفحه‌بندی
     */
    public function getFilteredLogs($filters, $page = 1, $perPage = 10, $sortBy = 'performed_at', $order = 'desc') {
        try {
            // اعتبارسنجی ستون مرتب‌سازی
            $validSortColumns = ['id', 'performed_at', 'cost', 'completion_time', 'status', 'created_at', 'updated_at'];
            if (!in_array($sortBy, $validSortColumns)) {
                $sortBy = 'performed_at'; // مقدار پیش‌فرض
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
                $whereConditions[] = "ml.maintenance_type_id = ?";
                $params[] = $filters['type_id'];
            }

            if (!empty($filters['user_id'])) {
                $whereConditions[] = "ml.user_id = ?";
                $params[] = $filters['user_id'];
            }

            if (!empty($filters['date_from'])) {
                $whereConditions[] = "ml.performed_at >= ?";
                $params[] = $filters['date_from'] . ' 00:00:00';
            }

            if (!empty($filters['date_to'])) {
                $whereConditions[] = "ml.performed_at <= ?";
                $params[] = $filters['date_to'] . ' 23:59:59';
            }

            if (!empty($filters['status'])) {
                $whereConditions[] = "ml.status = ?";
                $params[] = $filters['status'];
            }

            // ساخت بخش WHERE کوئری
            $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

            // محاسبه تعداد کل رکوردها
            $countQuery = "SELECT COUNT(*) as total 
                          FROM maintenance_logs ml
                          JOIN assets a ON ml.asset_id = a.id
                          JOIN maintenance_types mt ON ml.maintenance_type_id = mt.id
                          LEFT JOIN users u ON ml.user_id = u.id
                          $whereClause";
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
            $query = "SELECT ml.*, a.asset_tag, mt.name as type_name, u.fullname as performed_by
                      FROM maintenance_logs ml
                      JOIN assets a ON ml.asset_id = a.id
                      JOIN maintenance_types mt ON ml.maintenance_type_id = mt.id
                      LEFT JOIN users u ON ml.user_id = u.id
                      $whereClause
                      ORDER BY ml.$sortBy $order
                      LIMIT $perPage OFFSET $offset";
            
            $logs = $this->executeQuery($query, $params);
            
            return [
                'logs' => $logs,
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
            error_log("Error in getFilteredLogs: " . $e->getMessage());
            return [
                'logs' => [],
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
     * دریافت آمار سوابق نگهداری
     * 
     * @param int|null $days تعداد روزهای اخیر (اختیاری)
     * @return array آمار سوابق نگهداری
     */
    public function getLogsStats($days = null) {
        try {
            $dateCondition = "";
            $params = [];
            
            if ($days !== null) {
                $dateCondition = "WHERE ml.performed_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)";
                $params[] = $days;
            }
            
            $query = "SELECT 
                        COUNT(*) as total_logs,
                        SUM(ml.cost) as total_cost,
                        AVG(ml.completion_time) as avg_completion_time,
                        COUNT(DISTINCT ml.asset_id) as unique_assets,
                        COUNT(DISTINCT ml.maintenance_type_id) as unique_types,
                        COUNT(DISTINCT ml.user_id) as unique_users
                      FROM maintenance_logs ml
                      $dateCondition";
            
            $result = $this->executeQuery($query, $params);
            return $result ? $result[0] : [
                'total_logs' => 0,
                'total_cost' => 0,
                'avg_completion_time' => 0,
                'unique_assets' => 0,
                'unique_types' => 0,
                'unique_users' => 0
            ];
        } catch (Exception $e) {
            error_log("Error in getLogsStats: " . $e->getMessage());
            return [
                'total_logs' => 0,
                'total_cost' => 0,
                'avg_completion_time' => 0,
                'unique_assets' => 0,
                'unique_types' => 0,
                'unique_users' => 0
            ];
        }
    }
    
    /**
     * دریافت روند سوابق نگهداری در طول زمان
     * 
     * @param int $months تعداد ماه‌های اخیر
     * @return array روند سوابق نگهداری
     */
    public function getLogsTrend($months = 12) {
        try {
            $months = (int)$months;
            $query = "SELECT 
                        DATE_FORMAT(performed_at, '%Y-%m') as month,
                        COUNT(*) as log_count,
                        SUM(cost) as total_cost,
                        AVG(completion_time) as avg_completion_time
                      FROM maintenance_logs
                      WHERE performed_at >= DATE_SUB(CURDATE(), INTERVAL $months MONTH)
                      GROUP BY DATE_FORMAT(performed_at, '%Y-%m')
                      ORDER BY month";
            return $this->executeQuery($query);
        } catch (Exception $e) {
            error_log("Error in getLogsTrend: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * دریافت آخرین سابقه نگهداری برای یک تجهیز و نوع نگهداری
     * 
     * @param int $assetId شناسه تجهیز
     * @param int $typeId شناسه نوع نگهداری
     * @return array|null آخرین سابقه نگهداری یا null در صورت عدم وجود
     */
    public function getLastLogForAssetAndType($assetId, $typeId) {
        try {
            $query = "SELECT * FROM maintenance_logs
                      WHERE asset_id = ? AND maintenance_type_id = ?
                      ORDER BY performed_at DESC
                      LIMIT 1";
            $result = $this->executeQuery($query, [$assetId, $typeId]);
            return $result ? $result[0] : null;
        } catch (Exception $e) {
            error_log("Error in getLastLogForAssetAndType: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * دریافت سوابق نگهداری برای چندین تجهیز
     * 
     * @param array $assetIds آرایه‌ای از شناسه‌های تجهیز
     * @return array لیست سوابق نگهداری
     */
    public function getLogsByAssetIds($assetIds) {
        try {
            if (empty($assetIds)) {
                return [];
            }
            
            // ساخت پارامترهای IN
            $placeholders = implode(',', array_fill(0, count($assetIds), '?'));
            
            $query = "SELECT ml.*, a.asset_tag, mt.name as type_name, u.fullname as performed_by
                      FROM maintenance_logs ml
                      JOIN assets a ON ml.asset_id = a.id
                      JOIN maintenance_types mt ON ml.maintenance_type_id = mt.id
                      LEFT JOIN users u ON ml.user_id = u.id
                      WHERE ml.asset_id IN ($placeholders)
                      ORDER BY ml.performed_at DESC";
            
            return $this->executeQuery($query, $assetIds);
        } catch (Exception $e) {
            error_log("Error in getLogsByAssetIds: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * ایمپورت سوابق نگهداری از فایل CSV
     * 
     * @param string $filePath مسیر فایل CSV
     * @return array نتیجه عملیات (تعداد رکوردهای موفق و ناموفق)
     */
    public function importLogsFromCSV($filePath) {
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
            $requiredColumns = ['asset_id', 'maintenance_type_id', 'user_id', 'performed_at'];
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
            $optionalColumns = ['notes', 'cost', 'completion_time', 'status', 'checklist_results'];
            foreach ($optionalColumns as $column) {
                $index = array_search($column, $header);
                $columnIndexes[$column] = $index !== false ? $index : null;
            }
            
            // شروع تراکنش
            if (method_exists($this->db, 'beginTransaction')) {
                $this->db->beginTransaction();
            }
            
            $imported = 0;
            $failed = 0;
            
            // پردازش داده‌ها
            while (($data = fgetcsv($file)) !== false) {
                $assetId = $data[$columnIndexes['asset_id']] ?? '';
                $typeId = $data[$columnIndexes['maintenance_type_id']] ?? '';
                $userId = $data[$columnIndexes['user_id']] ?? '';
                $performedAt = $data[$columnIndexes['performed_at']] ?? '';
                
                if (empty($assetId) || empty($typeId) || empty($userId) || empty($performedAt)) {
                    $failed++;
                    continue;
                }
                
                $notes = $columnIndexes['notes'] !== null ? ($data[$columnIndexes['notes']] ?? '') : '';
                
                $additionalData = [
                    'performed_at' => $performedAt
                ];
                
                if ($columnIndexes['cost'] !== null && isset($data[$columnIndexes['cost']])) {
                    $additionalData['cost'] = $data[$columnIndexes['cost']];
                }
                
                if ($columnIndexes['completion_time'] !== null && isset($data[$columnIndexes['completion_time']])) {
                    $additionalData['completion_time'] = $data[$columnIndexes['completion_time']];
                }
                
                if ($columnIndexes['status'] !== null && isset($data[$columnIndexes['status']])) {
                    $additionalData['status'] = $data[$columnIndexes['status']];
                }
                
                if ($columnIndexes['checklist_results'] !== null && isset($data[$columnIndexes['checklist_results']])) {
                    $additionalData['checklist_results'] = $data[$columnIndexes['checklist_results']];
                }
                
                $result = $this->createLog($assetId, $typeId, $userId, $notes, $additionalData);
                
                if ($result) {
                    $imported++;
                } else {
                    $failed++;
                }
            }
            
            fclose($file);
            
            if ($imported > 0) {
                // تایید تراکنش
                if (method_exists($this->db, 'commit')) {
                    $this->db->commit();
                }
                
                return [
                    'success' => true,
                    'message' => "$imported سابقه نگهداری با موفقیت وارد شد. $failed مورد ناموفق بود.",
                    'imported' => $imported,
                    'failed' => $failed
                ];
            } else {
                // بازگشت تراکنش
                if (method_exists($this->db, 'rollback')) {
                    $this->db->rollback();
                }
                
                return [
                    'success' => false,
                    'message' => "هیچ سابقه نگهداری وارد نشد. $failed مورد ناموفق بود.",
                    'imported' => 0,
                    'failed' => $failed
                ];
            }
        } catch (Exception $e) {
            if (isset($file)) {
                fclose($file);
            }
            
            // بازگشت تراکنش
            if (method_exists($this->db, 'rollback')) {
                $this->db->rollback();
            }
            
            error_log("Error in importLogsFromCSV: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در وارد کردن داده‌ها: ' . $e->getMessage(),
                'imported' => 0,
                'failed' => 0
            ];
        }
    }
    
    /**
     * اکسپورت سوابق نگهداری به فایل CSV
     * 
     * @param string $filePath مسیر فایل CSV
     * @param array $filters فیلترها (اختیاری)
     * @return bool نتیجه عملیات
     */
    public function exportLogsToCSV($filePath, $filters = []) {
        try {
            // ساخت شرط WHERE
            $whereConditions = [];
            $params = [];

            if (!empty($filters['asset_tag'])) {
                $whereConditions[] = "a.asset_tag LIKE ?";
                $params[] = '%' . $filters['asset_tag'] . '%';
            }

            if (!empty($filters['type_id'])) {
                $whereConditions[] = "ml.maintenance_type_id = ?";
                $params[] = $filters['type_id'];
            }

            if (!empty($filters['user_id'])) {
                $whereConditions[] = "ml.user_id = ?";
                $params[] = $filters['user_id'];
            }

            if (!empty($filters['date_from'])) {
                $whereConditions[] = "ml.performed_at >= ?";
                $params[] = $filters['date_from'] . ' 00:00:00';
            }

            if (!empty($filters['date_to'])) {
                $whereConditions[] = "ml.performed_at <= ?";
                $params[] = $filters['date_to'] . ' 23:59:59';
            }

            // ساخت بخش WHERE کوئری
            $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

            $query = "SELECT ml.*, a.asset_tag, mt.name as type_name, u.fullname as performed_by
                      FROM maintenance_logs ml
                      JOIN assets a ON ml.asset_id = a.id
                      JOIN maintenance_types mt ON ml.maintenance_type_id = mt.id
                      LEFT JOIN users u ON ml.user_id = u.id
                      $whereClause
                      ORDER BY ml.performed_at DESC";
            
            $logs = $this->executeQuery($query, $params);
            
            if (empty($logs)) {
                return false;
            }
            
            // ایجاد فایل CSV
            $file = fopen($filePath, 'w');
            if (!$file) {
                return false;
            }
            
            // نوشتن سطر هدر
            fputcsv($file, [
                'id', 'asset_id', 'asset_tag', 'maintenance_type_id', 'type_name',
                'user_id', 'performed_by', 'notes', 'performed_at', 'cost',
                'completion_time', 'status', 'checklist_results', 'created_at', 'updated_at'
            ]);
            
            // نوشتن داده‌ها
            foreach ($logs as $log) {
                fputcsv($file, [
                    $log['id'],
                    $log['asset_id'],
                    $log['asset_tag'],
                    $log['maintenance_type_id'],
                    $log['type_name'],
                    $log['user_id'],
                    $log['performed_by'],
                    $log['notes'],
                    $log['performed_at'],
                    $log['cost'],
                    $log['completion_time'],
                    $log['status'],
                    $log['checklist_results'],
                    $log['created_at'],
                    $log['updated_at']
                ]);
            }
            
            fclose($file);
            return true;
        } catch (Exception $e) {
            if (isset($file)) {
                fclose($file);
            }
            error_log("Error in exportLogsToCSV: " . $e->getMessage());
            return false;
        }
    }

    /**
     * دریافت آمار هزینه‌های نگهداری به تفکیک نوع نگهداری
     * 
     * @param int|null $days تعداد روزهای اخیر (اختیاری)
     * @return array آمار هزینه‌های نگهداری
     */
    public function getCostStatsByType($days = null) {
        try {
            $dateCondition = "";
            $params = [];
            
            if ($days !== null) {
                $dateCondition = "WHERE ml.performed_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)";
                $params[] = $days;
            }
            
            $query = "SELECT 
                        mt.id, mt.name as type_name,
                        COUNT(ml.id) as log_count,
                        SUM(ml.cost) as total_cost,
                        AVG(ml.cost) as avg_cost,
                        AVG(ml.completion_time) as avg_completion_time
                    FROM maintenance_logs ml
                    JOIN maintenance_types mt ON ml.maintenance_type_id = mt.id
                    $dateCondition
                    GROUP BY mt.id, mt.name
                    ORDER BY total_cost DESC";
            
            return $this->executeQuery($query, $params);
        } catch (Exception $e) {
            error_log("Error in getCostStatsByType: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت آمار هزینه‌های نگهداری به تفکیک تجهیز
     * 
     * @param int|null $days تعداد روزهای اخیر (اختیاری)
     * @return array آمار هزینه‌های نگهداری
     */
    public function getCostStatsByAsset($days = null) {
        try {
            $dateCondition = "";
            $params = [];
            
            if ($days !== null) {
                $dateCondition = "WHERE ml.performed_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)";
                $params[] = $days;
            }
            
            $query = "SELECT 
                        a.id, a.name as asset_name, a.asset_tag,
                        COUNT(ml.id) as log_count,
                        SUM(ml.cost) as total_cost,
                        AVG(ml.cost) as avg_cost,
                        AVG(ml.completion_time) as avg_completion_time
                    FROM maintenance_logs ml
                    JOIN assets a ON ml.asset_id = a.id
                    $dateCondition
                    GROUP BY a.id, a.name, a.asset_tag
                    ORDER BY total_cost DESC";
            
            return $this->executeQuery($query, $params);
        } catch (Exception $e) {
            error_log("Error in getCostStatsByAsset: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت آمار عملکرد تکنسین‌ها
     * 
     * @param int|null $days تعداد روزهای اخیر (اختیاری)
     * @return array آمار عملکرد تکنسین‌ها
     */
    public function getTechnicianPerformanceStats($days = null) {
        try {
            $dateCondition = "";
            $params = [];
            
            if ($days !== null) {
                $dateCondition = "WHERE ml.performed_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)";
                $params[] = $days;
            }
            
            $query = "SELECT 
                        u.id, u.fullname as technician_name,
                        COUNT(ml.id) as log_count,
                        SUM(ml.cost) as total_cost,
                        AVG(ml.completion_time) as avg_completion_time,
                        COUNT(DISTINCT ml.asset_id) as unique_assets,
                        COUNT(DISTINCT ml.maintenance_type_id) as unique_types
                    FROM maintenance_logs ml
                    JOIN users u ON ml.user_id = u.id
                    $dateCondition
                    GROUP BY u.id, u.fullname
                    ORDER BY log_count DESC";
            
            return $this->executeQuery($query, $params);
        } catch (Exception $e) {
            error_log("Error in getTechnicianPerformanceStats: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت لاگ‌های نگهداری برای یک بازه زمانی خاص
     * 
     * @param string $startDate تاریخ شروع
     * @param string $endDate تاریخ پایان
     * @return array لیست لاگ‌های نگهداری
     */
    public function getLogsByDateRange($startDate, $endDate) {
        try {
            $query = "SELECT ml.*, a.asset_tag, mt.name as type_name, u.fullname as performed_by
                    FROM maintenance_logs ml
                    JOIN assets a ON ml.asset_id = a.id
                    JOIN maintenance_types mt ON ml.maintenance_type_id = mt.id
                    LEFT JOIN users u ON ml.user_id = u.id
                    WHERE ml.performed_at BETWEEN ? AND ?
                    ORDER BY ml.performed_at DESC";
            
            return $this->executeQuery($query, [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        } catch (Exception $e) {
            error_log("Error in getLogsByDateRange: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت آمار وضعیت نگهداری
     * 
     * @param int|null $days تعداد روزهای اخیر (اختیاری)
     * @return array آمار وضعیت نگهداری
     */
    public function getStatusStats($days = null) {
        try {
            $dateCondition = "";
            $params = [];
            
            if ($days !== null) {
                $dateCondition = "WHERE ml.performed_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)";
                $params[] = $days;
            }
            
            $query = "SELECT 
                        ml.status,
                        COUNT(ml.id) as log_count,
                        SUM(ml.cost) as total_cost,
                        AVG(ml.completion_time) as avg_completion_time
                    FROM maintenance_logs ml
                    $dateCondition
                    GROUP BY ml.status
                    ORDER BY log_count DESC";
            
            return $this->executeQuery($query, $params);
        } catch (Exception $e) {
            error_log("Error in getStatusStats: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت آمار نگهداری به تفکیک ماه
     * 
     * @param int $year سال مورد نظر
     * @return array آمار نگهداری ماهانه
     */
    public function getMonthlyStats($year) {
        try {
            $query = "SELECT 
                        MONTH(performed_at) as month,
                        COUNT(id) as log_count,
                        SUM(cost) as total_cost,
                        AVG(completion_time) as avg_completion_time
                    FROM maintenance_logs
                    WHERE YEAR(performed_at) = ?
                    GROUP BY MONTH(performed_at)
                    ORDER BY month";
            
            return $this->executeQuery($query, [$year]);
        } catch (Exception $e) {
            error_log("Error in getMonthlyStats: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت لاگ‌های نگهداری با وضعیت خاص
     * 
     * @param string $status وضعیت مورد نظر
     * @param int $limit محدودیت تعداد نتایج
     * @return array لیست لاگ‌های نگهداری
     */
    public function getLogsByStatus($status, $limit = 10) {
        try {
            $query = "SELECT ml.*, a.asset_tag, mt.name as type_name, u.fullname as performed_by
                    FROM maintenance_logs ml
                    JOIN assets a ON ml.asset_id = a.id
                    JOIN maintenance_types mt ON ml.maintenance_type_id = mt.id
                    LEFT JOIN users u ON ml.user_id = u.id
                    WHERE ml.status = ?
                    ORDER BY ml.performed_at DESC
                    LIMIT ?";
            
            return $this->executeQuery($query, [$status, $limit]);
        } catch (Exception $e) {
            error_log("Error in getLogsByStatus: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت آمار زمان تکمیل به تفکیک نوع نگهداری
     * 
     * @return array آمار زمان تکمیل
     */
    public function getCompletionTimeByType() {
        try {
            $query = "SELECT 
                        mt.id, mt.name as type_name,
                        COUNT(ml.id) as log_count,
                        AVG(ml.completion_time) as avg_completion_time,
                        MIN(ml.completion_time) as min_completion_time,
                        MAX(ml.completion_time) as max_completion_time
                    FROM maintenance_logs ml
                    JOIN maintenance_types mt ON ml.maintenance_type_id = mt.id
                    GROUP BY mt.id, mt.name
                    ORDER BY avg_completion_time DESC";
            
            return $this->executeQuery($query);
        } catch (Exception $e) {
            error_log("Error in getCompletionTimeByType: " . $e->getMessage());
            return [];
        }
    }

    /**
     * ثبت چندین سابقه نگهداری به صورت گروهی
     * 
     * @param array $logsData آرایه‌ای از داده‌های سوابق نگهداری
     * @return array نتیجه عملیات
     */
    public function createBulkLogs($logsData) {
        try {
            if (empty($logsData) || !is_array($logsData)) {
                return [
                    'success' => false,
                    'message' => 'داده‌های ورودی نامعتبر هستند.',
                    'created' => 0
                ];
            }
            
            // شروع تراکنش
            if (method_exists($this->db, 'beginTransaction')) {
                $this->db->beginTransaction();
            } elseif (method_exists($this->db, 'begin_transaction')) {
                $this->db->begin_transaction();
            }
            
            $created = 0;
            $failed = 0;
            
            foreach ($logsData as $logData) {
                if (empty($logData['asset_id']) || empty($logData['maintenance_type_id']) || 
                    empty($logData['user_id'])) {
                    $failed++;
                    continue;
                }
                
                $assetId = $logData['asset_id'];
                $typeId = $logData['maintenance_type_id'];
                $userId = $logData['user_id'];
                $notes = $logData['notes'] ?? '';
                
                $additionalData = [
                    'performed_at' => $logData['performed_at'] ?? date('Y-m-d H:i:s'),
                    'cost' => $logData['cost'] ?? 0,
                    'completion_time' => $logData['completion_time'] ?? 0,
                    'status' => $logData['status'] ?? 'completed',
                    'checklist_results' => $logData['checklist_results'] ?? null
                ];
                
                $result = $this->createLog($assetId, $typeId, $userId, $notes, $additionalData);
                
                if ($result) {
                    $created++;
                } else {
                    $failed++;
                }
            }
            
            if ($created > 0) {
                // تایید تراکنش
                if (method_exists($this->db, 'commit')) {
                    $this->db->commit();
                }
                
                return [
                    'success' => true,
                    'message' => "$created سابقه نگهداری با موفقیت ثبت شد. $failed مورد ناموفق بود.",
                    'created' => $created,
                    'failed' => $failed
                ];
            } else {
                // بازگشت تراکنش
                if (method_exists($this->db, 'rollback')) {
                    $this->db->rollback();
                }
                
                return [
                    'success' => false,
                    'message' => "هیچ سابقه نگهداری ثبت نشد. $failed مورد ناموفق بود.",
                    'created' => 0,
                    'failed' => $failed
                ];
            }
        } catch (Exception $e) {
            // بازگشت تراکنش در صورت خطا
            if (method_exists($this->db, 'rollback')) {
                $this->db->rollback();
            }
            
            error_log("Error in createBulkLogs: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در ثبت سوابق نگهداری: ' . $e->getMessage(),
                'created' => 0,
                'failed' => count($logsData)
            ];
        }
    }

    /**
     * دریافت سوابق نگهداری برای داشبورد
     * 
     * @return array اطلاعات داشبورد
     */
    public function getDashboardData() {
        try {
            $result = [
                'recent_logs' => $this->getRecentLogs(5),
                'today_count' => 0,
                'week_count' => 0,
                'month_count' => 0,
                'total_cost' => 0,
                'avg_completion_time' => 0,
                'status_stats' => [],
                'type_stats' => []
            ];
            
            // تعداد سوابق امروز
            $query = "SELECT COUNT(*) as count FROM maintenance_logs WHERE DATE(performed_at) = CURDATE()";
            $todayResult = $this->executeQuery($query);
            $result['today_count'] = $todayResult[0]['count'] ?? 0;
            
            // تعداد سوابق هفته جاری
            $query = "SELECT COUNT(*) as count FROM maintenance_logs WHERE performed_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
            $weekResult = $this->executeQuery($query);
            $result['week_count'] = $weekResult[0]['count'] ?? 0;
            
            // تعداد سوابق ماه جاری
            $query = "SELECT COUNT(*) as count FROM maintenance_logs WHERE performed_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
            $monthResult = $this->executeQuery($query);
            $result['month_count'] = $monthResult[0]['count'] ?? 0;
            
            // کل هزینه و میانگین زمان تکمیل
            $query = "SELECT SUM(cost) as total_cost, AVG(completion_time) as avg_completion_time FROM maintenance_logs";
            $statsResult = $this->executeQuery($query);
            $result['total_cost'] = $statsResult[0]['total_cost'] ?? 0;
            $result['avg_completion_time'] = $statsResult[0]['avg_completion_time'] ?? 0;
            
            // آمار وضعیت
            $result['status_stats'] = $this->getStatusStats();
            
            // آمار نوع نگهداری
            $result['type_stats'] = $this->getCostStatsByType();
            
            return $result;
        } catch (Exception $e) {
            error_log("Error in getDashboardData: " . $e->getMessage());
            return [
                'recent_logs' => [],
                'today_count' => 0,
                'week_count' => 0,
                'month_count' => 0,
                'total_cost' => 0,
                'avg_completion_time' => 0,
                'status_stats' => [],
                'type_stats' => []
            ];
        }
    }

    /**
     * دریافت سوابق نگهداری برای یک تکنسین
     * 
     * @param int $technicianId شناسه تکنسین
     * @param array $filters فیلترها (اختیاری)
     * @return array سوابق نگهداری
     */
    public function getLogsByTechnician($technicianId, $filters = []) {
        try {
            $whereConditions = ["ml.user_id = ?"];
            $params = [$technicianId];
            
            if (!empty($filters['date_from'])) {
                $whereConditions[] = "ml.performed_at >= ?";
                $params[] = $filters['date_from'] . ' 00:00:00';
            }
            
            if (!empty($filters['date_to'])) {
                $whereConditions[] = "ml.performed_at <= ?";
                $params[] = $filters['date_to'] . ' 23:59:59';
            }
            
            if (!empty($filters['status'])) {
                $whereConditions[] = "ml.status = ?";
                $params[] = $filters['status'];
            }
            
            if (!empty($filters['type_id'])) {
                $whereConditions[] = "ml.maintenance_type_id = ?";
                $params[] = $filters['type_id'];
            }
            
            $whereClause = "WHERE " . implode(" AND ", $whereConditions);
            
            $query = "SELECT ml.*, a.asset_tag, mt.name as type_name
                    FROM maintenance_logs ml
                    JOIN assets a ON ml.asset_id = a.id
                    JOIN maintenance_types mt ON ml.maintenance_type_id = mt.id
                    $whereClause
                    ORDER BY ml.performed_at DESC";
            
            return $this->executeQuery($query, $params);
        } catch (Exception $e) {
            error_log("Error in getLogsByTechnician: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت میانگین زمان تکمیل برای هر تجهیز
     * 
     * @return array میانگین زمان تکمیل
     */
    public function getAvgCompletionTimeByAsset() {
        try {
            $query = "SELECT 
                        a.id, a.name as asset_name, a.asset_tag,
                        COUNT(ml.id) as log_count,
                        AVG(ml.completion_time) as avg_completion_time
                    FROM maintenance_logs ml
                    JOIN assets a ON ml.asset_id = a.id
                    GROUP BY a.id, a.name, a.asset_tag
                    HAVING log_count > 0
                    ORDER BY avg_completion_time DESC";
            
            return $this->executeQuery($query);
        } catch (Exception $e) {
            error_log("Error in getAvgCompletionTimeByAsset: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت سوابق نگهداری با چک‌لیست
     * 
     * @param int $limit محدودیت تعداد نتایج
     * @return array سوابق نگهداری
     */
    public function getLogsWithChecklists($limit = 10) {
        try {
            $query = "SELECT ml.*, a.asset_tag, mt.name as type_name, u.fullname as performed_by
                    FROM maintenance_logs ml
                    JOIN assets a ON ml.asset_id = a.id
                    JOIN maintenance_types mt ON ml.maintenance_type_id = mt.id
                    LEFT JOIN users u ON ml.user_id = u.id
                    WHERE ml.checklist_results IS NOT NULL AND ml.checklist_results != ''
                    ORDER BY ml.performed_at DESC
                    LIMIT ?";
            
            return $this->executeQuery($query, [$limit]);
        } catch (Exception $e) {
            error_log("Error in getLogsWithChecklists: " . $e->getMessage());
            return [];
        }
    }

    /**
     * حذف چندین سابقه نگهداری به صورت گروهی
     * 
     * @param array $logIds آرایه‌ای از شناسه‌های سوابق نگهداری
     * @return array نتیجه عملیات
     */
    public function deleteBulkLogs($logIds) {
        try {
            if (empty($logIds) || !is_array($logIds)) {
                return [
                    'success' => false,
                    'message' => 'هیچ سابقه نگهداری انتخاب نشده است.',
                    'deleted' => 0
                ];
            }
            
            // شروع تراکنش
            if (method_exists($this->db, 'beginTransaction')) {
                $this->db->beginTransaction();
            } elseif (method_exists($this->db, 'begin_transaction')) {
                $this->db->begin_transaction();
            }
            
            $deleted = 0;
            
            foreach ($logIds as $logId) {
                $query = "DELETE FROM maintenance_logs WHERE id = ?";
                $result = $this->executeQuery($query, [$logId]);
                
                if ($result) {
                    $deleted++;
                }
            }
            
            if ($deleted > 0) {
                // تایید تراکنش
                if (method_exists($this->db, 'commit')) {
                    $this->db->commit();
                }
                
                return [
                    'success' => true,
                    'message' => "$deleted سابقه نگهداری با موفقیت حذف شد.",
                    'deleted' => $deleted
                ];
            } else {
                // بازگشت تراکنش
                if (method_exists($this->db, 'rollback')) {
                    $this->db->rollback();
                }
                
                return [
                    'success' => false,
                    'message' => 'هیچ سابقه نگهداری حذف نشد.',
                    'deleted' => 0
                ];
            }
        } catch (Exception $e) {
            // بازگشت تراکنش در صورت خطا
            if (method_exists($this->db, 'rollback')) {
                $this->db->rollback();
            }
            
            error_log("Error in deleteBulkLogs: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در حذف سوابق نگهداری: ' . $e->getMessage(),
                'deleted' => 0
            ];
        }
    }

    /**
     * دریافت سوابق نگهداری با هزینه بالا
     * 
     * @param float $threshold آستانه هزینه
     * @param int $limit محدودیت تعداد نتایج
     * @return array سوابق نگهداری
     */
    public function getHighCostLogs($threshold = 1000, $limit = 10) {
        try {
            $query = "SELECT ml.*, a.asset_tag, mt.name as type_name, u.fullname as performed_by
                    FROM maintenance_logs ml
                    JOIN assets a ON ml.asset_id = a.id
                    JOIN maintenance_types mt ON ml.maintenance_type_id = mt.id
                    LEFT JOIN users u ON ml.user_id = u.id
                    WHERE ml.cost >= ?
                    ORDER BY ml.cost DESC
                    LIMIT ?";
            
            return $this->executeQuery($query, [$threshold, $limit]);
        } catch (Exception $e) {
            error_log("Error in getHighCostLogs: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت سوابق نگهداری با زمان تکمیل بالا
     * 
     * @param int $threshold آستانه زمان تکمیل (دقیقه)
     * @param int $limit محدودیت تعداد نتایج
     * @return array سوابق نگهداری
     */
    public function getHighCompletionTimeLogs($threshold = 120, $limit = 10) {
        try {
            $query = "SELECT ml.*, a.asset_tag, mt.name as type_name, u.fullname as performed_by
                    FROM maintenance_logs ml
                    JOIN assets a ON ml.asset_id = a.id
                    JOIN maintenance_types mt ON ml.maintenance_type_id = mt.id
                    LEFT JOIN users u ON ml.user_id = u.id
                    WHERE ml.completion_time >= ?
                    ORDER BY ml.completion_time DESC
                    LIMIT ?";
            
            return $this->executeQuery($query, [$threshold, $limit]);
        } catch (Exception $e) {
            error_log("Error in getHighCompletionTimeLogs: " . $e->getMessage());
            return [];
        }
    }
}