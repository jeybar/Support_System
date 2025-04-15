<?php
require_once __DIR__ . '/../core/Database.php';

class MaintenanceReport {
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
     * گزارش برنامه‌های نگهداری آینده
     * 
     * @param array $filters فیلترها (دسته‌بندی، نوع نگهداری، بازه زمانی)
     * @param int $page شماره صفحه
     * @param int $perPage تعداد آیتم در هر صفحه
     * @return array برنامه‌های نگهداری آینده و اطلاعات صفحه‌بندی
     */
    public function getUpcomingMaintenanceReport($filters = [], $page = 1, $perPage = 10) {
        try {
            $whereConditions = ["ms.next_date >= CURDATE()", "ms.status = 'active'"];
            $params = [];
            
            if (!empty($filters['category_id'])) {
                $whereConditions[] = "ac.id = ?";
                $params[] = $filters['category_id'];
            }
            
            if (!empty($filters['maintenance_type_id'])) {
                $whereConditions[] = "mt.id = ?";
                $params[] = $filters['maintenance_type_id'];
            }
            
            if (!empty($filters['asset_id'])) {
                $whereConditions[] = "a.id = ?";
                $params[] = $filters['asset_id'];
            }
            
            if (!empty($filters['user_id'])) {
                $whereConditions[] = "au.user_id = ?";
                $params[] = $filters['user_id'];
            }
            
            if (!empty($filters['plant'])) {
                $whereConditions[] = "a.plant = ?";
                $params[] = $filters['plant'];
            }
            
            if (!empty($filters['department'])) {
                $whereConditions[] = "a.department = ?";
                $params[] = $filters['department'];
            }
            
            if (!empty($filters['date_from'])) {
                $whereConditions[] = "ms.next_date >= ?";
                $params[] = $filters['date_from'];
            }
            
            if (!empty($filters['date_to'])) {
                $whereConditions[] = "ms.next_date <= ?";
                $params[] = $filters['date_to'];
            }
            
            if (!empty($filters['priority'])) {
                $whereConditions[] = "mt.priority = ?";
                $params[] = $filters['priority'];
            }
            
            if (!empty($filters['technician_id'])) {
                $whereConditions[] = "ms.technician_id = ?";
                $params[] = $filters['technician_id'];
            }
            
            $whereClause = "WHERE " . implode(" AND ", $whereConditions);
            $offset = ($page - 1) * $perPage;
            
            $query = "
                SELECT ms.*, a.name as asset_name, a.asset_tag, mt.name as maintenance_type_name, 
                    ac.name as category_name, u.fullname as technician_name,
                    DATEDIFF(ms.next_date, CURDATE()) as days_remaining
                FROM maintenance_schedules ms
                JOIN assets a ON ms.asset_id = a.id
                JOIN maintenance_types mt ON ms.maintenance_type_id = mt.id
                JOIN asset_categories ac ON a.category_id = ac.id
                LEFT JOIN users u ON ms.technician_id = u.id
                LEFT JOIN asset_users au ON a.id = au.asset_id
                $whereClause
                ORDER BY ms.next_date ASC
                LIMIT $perPage OFFSET $offset
            ";
            
            $schedules = $this->executeQuery($query, $params);
            
            // دریافت تعداد کل رکوردها
            $countQuery = "
                SELECT COUNT(*) as total
                FROM maintenance_schedules ms
                JOIN assets a ON ms.asset_id = a.id
                JOIN maintenance_types mt ON ms.maintenance_type_id = mt.id
                JOIN asset_categories ac ON a.category_id = ac.id
                LEFT JOIN users u ON ms.technician_id = u.id
                LEFT JOIN asset_users au ON a.id = au.asset_id
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
            error_log("Error in getUpcomingMaintenanceReport: " . $e->getMessage());
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
     * گزارش سرویس‌های انجام‌شده
     * 
     * @param array $filters فیلترها (دسته‌بندی، نوع نگهداری، بازه زمانی)
     * @param int $page شماره صفحه
     * @param int $perPage تعداد آیتم در هر صفحه
     * @return array سرویس‌های انجام‌شده و اطلاعات صفحه‌بندی
     */
    public function getCompletedMaintenanceReport($filters = [], $page = 1, $perPage = 10) {
        try {
            $whereConditions = ["ml.status = 'completed'"];
            $params = [];
            
            if (!empty($filters['category_id'])) {
                $whereConditions[] = "ac.id = ?";
                $params[] = $filters['category_id'];
            }
            
            if (!empty($filters['maintenance_type_id'])) {
                $whereConditions[] = "mt.id = ?";
                $params[] = $filters['maintenance_type_id'];
            }
            
            if (!empty($filters['asset_id'])) {
                $whereConditions[] = "a.id = ?";
                $params[] = $filters['asset_id'];
            }
            
            if (!empty($filters['user_id'])) {
                $whereConditions[] = "au.user_id = ?";
                $params[] = $filters['user_id'];
            }
            
            if (!empty($filters['plant'])) {
                $whereConditions[] = "a.plant = ?";
                $params[] = $filters['plant'];
            }
            
            if (!empty($filters['department'])) {
                $whereConditions[] = "a.department = ?";
                $params[] = $filters['department'];
            }
            
            if (!empty($filters['date_from'])) {
                $whereConditions[] = "ml.performed_at >= ?";
                $params[] = $filters['date_from'];
            }
            
            if (!empty($filters['date_to'])) {
                $whereConditions[] = "ml.performed_at <= ?";
                $params[] = $filters['date_to'];
            }
            
            if (!empty($filters['technician_id'])) {
                $whereConditions[] = "ml.user_id = ?";
                $params[] = $filters['technician_id'];
            }
            
            $whereClause = "WHERE " . implode(" AND ", $whereConditions);
            $offset = ($page - 1) * $perPage;
            
            $query = "
                SELECT ml.*, a.name as asset_name, a.asset_tag, mt.name as maintenance_type_name, 
                    ac.name as category_name, u.fullname as technician_name
                FROM maintenance_logs ml
                JOIN assets a ON ml.asset_id = a.id
                JOIN maintenance_types mt ON ml.maintenance_type_id = mt.id
                JOIN asset_categories ac ON a.category_id = ac.id
                LEFT JOIN users u ON ml.user_id = u.id
                LEFT JOIN asset_users au ON a.id = au.asset_id
                $whereClause
                ORDER BY ml.performed_at DESC
                LIMIT $perPage OFFSET $offset
            ";
            
            $logs = $this->executeQuery($query, $params);
            
            // دریافت تعداد کل رکوردها
            $countQuery = "
                SELECT COUNT(*) as total
                FROM maintenance_logs ml
                JOIN assets a ON ml.asset_id = a.id
                JOIN maintenance_types mt ON ml.maintenance_type_id = mt.id
                JOIN asset_categories ac ON a.category_id = ac.id
                LEFT JOIN users u ON ml.user_id = u.id
                LEFT JOIN asset_users au ON a.id = au.asset_id
                $whereClause
            ";
            
            $countResult = $this->executeQuery($countQuery, $params);
            $totalCount = $countResult[0]['total'];
            $totalPages = ceil($totalCount / $perPage);
            
            return [
                'logs' => $logs,
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
            error_log("Error in getCompletedMaintenanceReport: " . $e->getMessage());
            return [
                'logs' => [],
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
     * گزارش سرویس‌های معوق
     * 
     * @param array $filters فیلترها (دسته‌بندی، نوع نگهداری، بازه زمانی)
     * @param int $page شماره صفحه
     * @param int $perPage تعداد آیتم در هر صفحه
     * @return array سرویس‌های معوق و اطلاعات صفحه‌بندی
     */
    public function getOverdueMaintenanceReport($filters = [], $page = 1, $perPage = 10) {
        try {
            $whereConditions = ["ms.next_date < CURDATE()", "ms.status = 'active'"];
            $params = [];
            
            if (!empty($filters['category_id'])) {
                $whereConditions[] = "ac.id = ?";
                $params[] = $filters['category_id'];
            }
            
            if (!empty($filters['maintenance_type_id'])) {
                $whereConditions[] = "mt.id = ?";
                $params[] = $filters['maintenance_type_id'];
            }
            
            if (!empty($filters['asset_id'])) {
                $whereConditions[] = "a.id = ?";
                $params[] = $filters['asset_id'];
            }
            
            if (!empty($filters['user_id'])) {
                $whereConditions[] = "au.user_id = ?";
                $params[] = $filters['user_id'];
            }
            
            if (!empty($filters['plant'])) {
                $whereConditions[] = "a.plant = ?";
                $params[] = $filters['plant'];
            }
            
            if (!empty($filters['department'])) {
                $whereConditions[] = "a.department = ?";
                $params[] = $filters['department'];
            }
            
            if (!empty($filters['days_overdue'])) {
                $whereConditions[] = "DATEDIFF(CURDATE(), ms.next_date) >= ?";
                $params[] = $filters['days_overdue'];
            }
            
            if (!empty($filters['priority'])) {
                $whereConditions[] = "mt.priority = ?";
                $params[] = $filters['priority'];
            }
            
            if (!empty($filters['technician_id'])) {
                $whereConditions[] = "ms.technician_id = ?";
                $params[] = $filters['technician_id'];
            }
            
            $whereClause = "WHERE " . implode(" AND ", $whereConditions);
            $offset = ($page - 1) * $perPage;
            
            $query = "
                SELECT ms.*, a.name as asset_name, a.asset_tag, mt.name as maintenance_type_name, 
                    ac.name as category_name, u.fullname as technician_name,
                    DATEDIFF(CURDATE(), ms.next_date) as days_overdue
                FROM maintenance_schedules ms
                JOIN assets a ON ms.asset_id = a.id
                JOIN maintenance_types mt ON ms.maintenance_type_id = mt.id
                JOIN asset_categories ac ON a.category_id = ac.id
                LEFT JOIN users u ON ms.technician_id = u.id
                LEFT JOIN asset_users au ON a.id = au.asset_id
                $whereClause
                ORDER BY days_overdue DESC
                LIMIT $perPage OFFSET $offset
            ";
            
            $schedules = $this->executeQuery($query, $params);
            
            // دریافت تعداد کل رکوردها
            $countQuery = "
                SELECT COUNT(*) as total
                FROM maintenance_schedules ms
                JOIN assets a ON ms.asset_id = a.id
                JOIN maintenance_types mt ON ms.maintenance_type_id = mt.id
                JOIN asset_categories ac ON a.category_id = ac.id
                LEFT JOIN users u ON ms.technician_id = u.id
                LEFT JOIN asset_users au ON a.id = au.asset_id
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
            error_log("Error in getOverdueMaintenanceReport: " . $e->getMessage());
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
     * گزارش خلاصه وضعیت نگهداری و تعمیرات
     * 
     * @param array $filters فیلترها (دسته‌بندی، نوع نگهداری، بازه زمانی)
     * @return array خلاصه وضعیت
     */
    public function getMaintenanceSummaryReport($filters = []) {
        try {
            $whereConditionsUpcoming = ["ms.next_date >= CURDATE()", "ms.status = 'active'"];
            $whereConditionsOverdue = ["ms.next_date < CURDATE()", "ms.status = 'active'"];
            $whereConditionsCompleted = ["ml.status = 'completed'"];
            $params = [];
            $paramsUpcoming = [];
            $paramsOverdue = [];
            $paramsCompleted = [];
            
            if (!empty($filters['category_id'])) {
                $whereConditionsUpcoming[] = "ac.id = ?";
                $whereConditionsOverdue[] = "ac.id = ?";
                $whereConditionsCompleted[] = "ac.id = ?";
                $paramsUpcoming[] = $filters['category_id'];
                $paramsOverdue[] = $filters['category_id'];
                $paramsCompleted[] = $filters['category_id'];
            }
            
            if (!empty($filters['plant'])) {
                $whereConditionsUpcoming[] = "a.plant = ?";
                $whereConditionsOverdue[] = "a.plant = ?";
                $whereConditionsCompleted[] = "a.plant = ?";
                $paramsUpcoming[] = $filters['plant'];
                $paramsOverdue[] = $filters['plant'];
                $paramsCompleted[] = $filters['plant'];
            }
            
            if (!empty($filters['department'])) {
                $whereConditionsUpcoming[] = "a.department = ?";
                $whereConditionsOverdue[] = "a.department = ?";
                $whereConditionsCompleted[] = "a.department = ?";
                $paramsUpcoming[] = $filters['department'];
                $paramsOverdue[] = $filters['department'];
                $paramsCompleted[] = $filters['department'];
            }
            
            if (!empty($filters['date_from'])) {
                $whereConditionsCompleted[] = "ml.performed_at >= ?";
                $paramsCompleted[] = $filters['date_from'];
            }
            
            if (!empty($filters['date_to'])) {
                $whereConditionsCompleted[] = "ml.performed_at <= ?";
                $paramsCompleted[] = $filters['date_to'];
            }
            
            $whereClauseUpcoming = "WHERE " . implode(" AND ", $whereConditionsUpcoming);
            $whereClauseOverdue = "WHERE " . implode(" AND ", $whereConditionsOverdue);
            $whereClauseCompleted = "WHERE " . implode(" AND ", $whereConditionsCompleted);
            
            // تعداد سرویس‌های آینده
            $upcomingQuery = "
                SELECT COUNT(*) as total,
                    COUNT(CASE WHEN DATEDIFF(ms.next_date, CURDATE()) <= 7 THEN 1 END) as due_this_week,
                    COUNT(CASE WHEN DATEDIFF(ms.next_date, CURDATE()) <= 30 THEN 1 END) as due_this_month
                FROM maintenance_schedules ms
                JOIN assets a ON ms.asset_id = a.id
                JOIN maintenance_types mt ON ms.maintenance_type_id = mt.id
                JOIN asset_categories ac ON a.category_id = ac.id
                $whereClauseUpcoming
            ";
            
            $upcomingResult = $this->executeQuery($upcomingQuery, $paramsUpcoming);
            $upcoming = $upcomingResult[0];
            
            // تعداد سرویس‌های معوق
            $overdueQuery = "
                SELECT COUNT(*) as total,
                    COUNT(CASE WHEN DATEDIFF(CURDATE(), ms.next_date) <= 7 THEN 1 END) as overdue_within_week,
                    COUNT(CASE WHEN DATEDIFF(CURDATE(), ms.next_date) <= 30 THEN 1 END) as overdue_within_month,
                    COUNT(CASE WHEN DATEDIFF(CURDATE(), ms.next_date) > 30 THEN 1 END) as overdue_more_than_month
                FROM maintenance_schedules ms
                JOIN assets a ON ms.asset_id = a.id
                JOIN maintenance_types mt ON ms.maintenance_type_id = mt.id
                JOIN asset_categories ac ON a.category_id = ac.id
                $whereClauseOverdue
            ";
            
            $overdueResult = $this->executeQuery($overdueQuery, $paramsOverdue);
            $overdue = $overdueResult[0];
            
            // تعداد سرویس‌های انجام‌شده
            $completedQuery = "
                SELECT COUNT(*) as total,
                    COUNT(CASE WHEN DATEDIFF(CURDATE(), ml.performed_at) <= 7 THEN 1 END) as completed_this_week,
                    COUNT(CASE WHEN DATEDIFF(CURDATE(), ml.performed_at) <= 30 THEN 1 END) as completed_this_month,
                    COUNT(CASE WHEN DATEDIFF(CURDATE(), ml.performed_at) <= 90 THEN 1 END) as completed_this_quarter,
                    COUNT(CASE WHEN DATEDIFF(CURDATE(), ml.performed_at) <= 365 THEN 1 END) as completed_this_year
                FROM maintenance_logs ml
                JOIN assets a ON ml.asset_id = a.id
                JOIN maintenance_types mt ON ml.maintenance_type_id = mt.id
                JOIN asset_categories ac ON a.category_id = ac.id
                $whereClauseCompleted
            ";
            
            $completedResult = $this->executeQuery($completedQuery, $paramsCompleted);
            $completed = $completedResult[0];
            
            // تعداد تجهیز‌های نیازمند سرویس
            $assetsNeedingMaintenanceQuery = "
                SELECT COUNT(DISTINCT a.id) as total
                FROM maintenance_schedules ms
                JOIN assets a ON ms.asset_id = a.id
                JOIN maintenance_types mt ON ms.maintenance_type_id = mt.id
                JOIN asset_categories ac ON a.category_id = ac.id
                $whereClauseUpcoming
            ";
            
            $assetsNeedingMaintenanceResult = $this->executeQuery($assetsNeedingMaintenanceQuery, $paramsUpcoming);
            $assetsNeedingMaintenance = $assetsNeedingMaintenanceResult[0]['total'];
            
            // تعداد تجهیز‌های با سرویس معوق
            $assetsWithOverdueMaintenanceQuery = "
                SELECT COUNT(DISTINCT a.id) as total
                FROM maintenance_schedules ms
                JOIN assets a ON ms.asset_id = a.id
                JOIN maintenance_types mt ON ms.maintenance_type_id = mt.id
                JOIN asset_categories ac ON a.category_id = ac.id
                $whereClauseOverdue
            ";
            
            $assetsWithOverdueMaintenanceResult = $this->executeQuery($assetsWithOverdueMaintenanceQuery, $paramsOverdue);
            $assetsWithOverdueMaintenance = $assetsWithOverdueMaintenanceResult[0]['total'];
            
            return [
                'upcoming' => $upcoming,
                'overdue' => $overdue,
                'completed' => $completed,
                'assets_needing_maintenance' => $assetsNeedingMaintenance,
                'assets_with_overdue_maintenance' => $assetsWithOverdueMaintenance
            ];
        } catch (Exception $e) {
            error_log("Error in getMaintenanceSummaryReport: " . $e->getMessage());
            return [
                'upcoming' => [
                    'total' => 0,
                    'due_this_week' => 0,
                    'due_this_month' => 0
                ],
                'overdue' => [
                    'total' => 0,
                    'overdue_within_week' => 0,
                    'overdue_within_month' => 0,
                    'overdue_more_than_month' => 0
                ],
                'completed' => [
                    'total' => 0,
                    'completed_this_week' => 0,
                    'completed_this_month' => 0,
                    'completed_this_quarter' => 0,
                    'completed_this_year' => 0
                ],
                'assets_needing_maintenance' => 0,
                'assets_with_overdue_maintenance' => 0
            ];
        }
    }
    
    /**
     * گزارش عملکرد تکنسین‌ها
     * 
     * @param array $filters فیلترها (بازه زمانی، تکنسین)
     * @param int $page شماره صفحه
     * @param int $perPage تعداد آیتم در هر صفحه
     * @return array عملکرد تکنسین‌ها و اطلاعات صفحه‌بندی
     */
    public function getTechnicianPerformanceReport($filters = [], $page = 1, $perPage = 10) {
        try {
            $whereConditions = ["ml.status = 'completed'"];
            $params = [];
            
            if (!empty($filters['date_from'])) {
                $whereConditions[] = "ml.performed_at >= ?";
                $params[] = $filters['date_from'];
            }
            
            if (!empty($filters['date_to'])) {
                $whereConditions[] = "ml.performed_at <= ?";
                $params[] = $filters['date_to'];
            }
            
            if (!empty($filters['technician_id'])) {
                $whereConditions[] = "ml.user_id = ?";
                $params[] = $filters['technician_id'];
            }
            
            $whereClause = "WHERE " . implode(" AND ", $whereConditions);
            $offset = ($page - 1) * $perPage;
            
            $query = "
                SELECT u.id, u.fullname, u.username, u.email,
                    COUNT(ml.id) as total_maintenance,
                    AVG(TIMESTAMPDIFF(MINUTE, ml.started_at, ml.completed_at)) as avg_duration_minutes,
                    COUNT(CASE WHEN ml.is_on_time = 1 THEN 1 END) as on_time_count,
                    COUNT(CASE WHEN ml.is_on_time = 0 THEN 1 END) as late_count,
                    (COUNT(CASE WHEN ml.is_on_time = 1 THEN 1 END) / COUNT(ml.id)) * 100 as on_time_percentage
                FROM maintenance_logs ml
                JOIN users u ON ml.user_id = u.id
                $whereClause
                GROUP BY u.id, u.fullname, u.username, u.email
                ORDER BY total_maintenance DESC
                LIMIT $perPage OFFSET $offset
            ";
            
            $technicians = $this->executeQuery($query, $params);
            
            // دریافت تعداد کل رکوردها
            $countQuery = "
                SELECT COUNT(DISTINCT u.id) as total
                FROM maintenance_logs ml
                JOIN users u ON ml.user_id = u.id
                $whereClause
            ";
            
            $countResult = $this->executeQuery($countQuery, $params);
            $totalCount = $countResult[0]['total'];
            $totalPages = ceil($totalCount / $perPage);
            
            return [
                'technicians' => $technicians,
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
            error_log("Error in getTechnicianPerformanceReport: " . $e->getMessage());
            return [
                'technicians' => [],
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
     * گزارش جزئیات عملکرد یک تکنسین
     * 
     * @param int $technicianId شناسه تکنسین
     * @param array $filters فیلترها (بازه زمانی)
     * @param int $page شماره صفحه
     * @param int $perPage تعداد آیتم در هر صفحه
     * @return array جزئیات عملکرد تکنسین و اطلاعات صفحه‌بندی
     */
    public function getTechnicianDetailReport($technicianId, $filters = [], $page = 1, $perPage = 10) {
        try {
            $whereConditions = ["ml.user_id = ?", "ml.status = 'completed'"];
            $params = [$technicianId];
            
            if (!empty($filters['date_from'])) {
                $whereConditions[] = "ml.performed_at >= ?";
                $params[] = $filters['date_from'];
            }
            
            if (!empty($filters['date_to'])) {
                $whereConditions[] = "ml.performed_at <= ?";
                $params[] = $filters['date_to'];
            }
            
            if (!empty($filters['maintenance_type_id'])) {
                $whereConditions[] = "ml.maintenance_type_id = ?";
                $params[] = $filters['maintenance_type_id'];
            }
            
            if (!empty($filters['asset_id'])) {
                $whereConditions[] = "ml.asset_id = ?";
                $params[] = $filters['asset_id'];
            }
            
            $whereClause = "WHERE " . implode(" AND ", $whereConditions);
            $offset = ($page - 1) * $perPage;
            
            // اطلاعات کلی تکنسین
            $technicianQuery = "SELECT id, fullname, username, email FROM users WHERE id = ?";
            $technicianResult = $this->executeQuery($technicianQuery, [$technicianId]);
            $technician = !empty($technicianResult) ? $technicianResult[0] : null;
            
            if (!$technician) {
                return [
                    'technician' => null,
                    'summary' => [],
                    'logs' => [],
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
            
            // خلاصه عملکرد
            $summaryQuery = "
                SELECT 
                    COUNT(ml.id) as total_maintenance,
                    AVG(TIMESTAMPDIFF(MINUTE, ml.started_at, ml.completed_at)) as avg_duration_minutes,
                    COUNT(CASE WHEN ml.is_on_time = 1 THEN 1 END) as on_time_count,
                    COUNT(CASE WHEN ml.is_on_time = 0 THEN 1 END) as late_count,
                    (COUNT(CASE WHEN ml.is_on_time = 1 THEN 1 END) / COUNT(ml.id)) * 100 as on_time_percentage,
                    MAX(ml.performed_at) as last_maintenance_date
                FROM maintenance_logs ml
                $whereClause
            ";
            
            $summaryResult = $this->executeQuery($summaryQuery, $params);
            $summary = !empty($summaryResult) ? $summaryResult[0] : [];
            
            // جزئیات سرویس‌های انجام‌شده
            $logsQuery = "
                SELECT ml.*, a.name as asset_name, a.asset_tag, mt.name as maintenance_type_name,
                    TIMESTAMPDIFF(MINUTE, ml.started_at, ml.completed_at) as duration_minutes
                FROM maintenance_logs ml
                JOIN assets a ON ml.asset_id = a.id
                JOIN maintenance_types mt ON ml.maintenance_type_id = mt.id
                $whereClause
                ORDER BY ml.performed_at DESC
                LIMIT $perPage OFFSET $offset
            ";
            
            $logs = $this->executeQuery($logsQuery, $params);
            
            // دریافت تعداد کل رکوردها
            $countQuery = "
                SELECT COUNT(*) as total
                FROM maintenance_logs ml
                $whereClause
            ";
            
            $countResult = $this->executeQuery($countQuery, $params);
            $totalCount = $countResult[0]['total'];
            $totalPages = ceil($totalCount / $perPage);
            
            return [
                'technician' => $technician,
                'summary' => $summary,
                'logs' => $logs,
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
            error_log("Error in getTechnicianDetailReport: " . $e->getMessage());
            return [
                'technician' => null,
                'summary' => [],
                'logs' => [],
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
     * گزارش تاریخچه نگهداری تجهیز
     * 
     * @param int $assetId شناسه تجهیز
     * @param array $filters فیلترها (بازه زمانی، نوع نگهداری)
     * @param int $page شماره صفحه
     * @param int $perPage تعداد آیتم در هر صفحه
     * @return array تاریخچه نگهداری تجهیز و اطلاعات صفحه‌بندی
     */
    public function getAssetMaintenanceHistoryReport($assetId, $filters = [], $page = 1, $perPage = 10) {
        try {
            $whereConditions = ["ml.asset_id = ?"];
            $params = [$assetId];
            
            if (!empty($filters['date_from'])) {
                $whereConditions[] = "ml.performed_at >= ?";
                $params[] = $filters['date_from'];
            }
            
            if (!empty($filters['date_to'])) {
                $whereConditions[] = "ml.performed_at <= ?";
                $params[] = $filters['date_to'];
            }
            
            if (!empty($filters['maintenance_type_id'])) {
                $whereConditions[] = "ml.maintenance_type_id = ?";
                $params[] = $filters['maintenance_type_id'];
            }
            
            if (!empty($filters['status'])) {
                $whereConditions[] = "ml.status = ?";
                $params[] = $filters['status'];
            }
            
            $whereClause = "WHERE " . implode(" AND ", $whereConditions);
            $offset = ($page - 1) * $perPage;
            
            // اطلاعات تجهیز
            $assetQuery = "
                SELECT a.*, ac.name as category_name, am.name as model_name
                FROM assets a
                LEFT JOIN asset_categories ac ON a.category_id = ac.id
                LEFT JOIN asset_models am ON a.model_id = am.id
                WHERE a.id = ?
            ";
            
            $assetResult = $this->executeQuery($assetQuery, [$assetId]);
            $asset = !empty($assetResult) ? $assetResult[0] : null;
            
            if (!$asset) {
                return [
                    'asset' => null,
                    'summary' => [],
                    'logs' => [],
                    'upcoming_schedules' => [],
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
            
            // خلاصه نگهداری
            $summaryQuery = "
                SELECT 
                    COUNT(*) as total_maintenance,
                    COUNT(CASE WHEN ml.status = 'completed' THEN 1 END) as completed_count,
                    COUNT(CASE WHEN ml.status = 'pending' THEN 1 END) as pending_count,
                    COUNT(CASE WHEN ml.status = 'in_progress' THEN 1 END) as in_progress_count,
                    MAX(ml.performed_at) as last_maintenance_date
                FROM maintenance_logs ml
                WHERE ml.asset_id = ?
            ";
            
            $summaryResult = $this->executeQuery($summaryQuery, [$assetId]);
            $summary = !empty($summaryResult) ? $summaryResult[0] : [];
            
            // تاریخچه سرویس‌ها
            $logsQuery = "
                SELECT ml.*, mt.name as maintenance_type_name, u.fullname as technician_name
                FROM maintenance_logs ml
                JOIN maintenance_types mt ON ml.maintenance_type_id = mt.id
                LEFT JOIN users u ON ml.user_id = u.id
                $whereClause
                ORDER BY ml.performed_at DESC
                LIMIT $perPage OFFSET $offset
            ";
            
            $logs = $this->executeQuery($logsQuery, $params);
            
            // برنامه‌های نگهداری آینده
            $upcomingQuery = "
                SELECT ms.*, mt.name as maintenance_type_name, u.fullname as technician_name,
                    DATEDIFF(ms.next_date, CURDATE()) as days_remaining
                FROM maintenance_schedules ms
                JOIN maintenance_types mt ON ms.maintenance_type_id = mt.id
                LEFT JOIN users u ON ms.technician_id = u.id
                WHERE ms.asset_id = ? AND ms.next_date >= CURDATE() AND ms.status = 'active'
                ORDER BY ms.next_date ASC
                LIMIT 5
            ";
            
            $upcomingSchedules = $this->executeQuery($upcomingQuery, [$assetId]);
            
            // دریافت تعداد کل رکوردها
            $countQuery = "
                SELECT COUNT(*) as total
                FROM maintenance_logs ml
                $whereClause
            ";
            
            $countResult = $this->executeQuery($countQuery, $params);
            $totalCount = $countResult[0]['total'];
            $totalPages = ceil($totalCount / $perPage);
            
            return [
                'asset' => $asset,
                'summary' => $summary,
                'logs' => $logs,
                'upcoming_schedules' => $upcomingSchedules,
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
            error_log("Error in getAssetMaintenanceHistoryReport: " . $e->getMessage());
            return [
                'asset' => null,
                'summary' => [],
                'logs' => [],
                'upcoming_schedules' => [],
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
     * گزارش تجهیز‌های با بیشترین نیاز به نگهداری
     * 
     * @param array $filters فیلترها (دسته‌بندی، بازه زمانی)
     * @param int $limit تعداد نتایج
     * @return array تجهیز‌های با بیشترین نیاز به نگهداری
     */
    public function getAssetsMostNeedingMaintenanceReport($filters = [], $limit = 10) {
        try {
            $whereConditions = [];
            $params = [];
            
            if (!empty($filters['category_id'])) {
                $whereConditions[] = "a.category_id = ?";
                $params[] = $filters['category_id'];
            }
            
            if (!empty($filters['plant'])) {
                $whereConditions[] = "a.plant = ?";
                $params[] = $filters['plant'];
            }
            
            if (!empty($filters['department'])) {
                $whereConditions[] = "a.department = ?";
                $params[] = $filters['department'];
            }
            
            $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";
            
            $query = "
                SELECT a.id, a.name, a.asset_tag, ac.name as category_name,
                    COUNT(ms.id) as total_overdue_schedules,
                    MAX(DATEDIFF(CURDATE(), ms.next_date)) as max_days_overdue,
                    COUNT(DISTINCT mt.id) as distinct_maintenance_types
                FROM assets a
                JOIN asset_categories ac ON a.category_id = ac.id
                JOIN maintenance_schedules ms ON a.id = ms.asset_id
                JOIN maintenance_types mt ON ms.maintenance_type_id = mt.id
                $whereClause
                AND ms.next_date < CURDATE() AND ms.status = 'active'
                GROUP BY a.id, a.name, a.asset_tag, ac.name
                ORDER BY total_overdue_schedules DESC, max_days_overdue DESC
                LIMIT ?
            ";
            
            $params[] = $limit;
            
            return $this->executeQuery($query, $params);
        } catch (Exception $e) {
            error_log("Error in getAssetsMostNeedingMaintenanceReport: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * گزارش انواع نگهداری با بیشترین تاخیر
     * 
     * @param array $filters فیلترها (دسته‌بندی، بازه زمانی)
     * @param int $limit تعداد نتایج
     * @return array انواع نگهداری با بیشترین تاخیر
     */
    public function getMaintenanceTypesMostOverdueReport($filters = [], $limit = 10) {
        try {
            $whereConditions = [];
            $params = [];
            
            if (!empty($filters['category_id'])) {
                $whereConditions[] = "ac.id = ?";
                $params[] = $filters['category_id'];
            }
            
            if (!empty($filters['plant'])) {
                $whereConditions[] = "a.plant = ?";
                $params[] = $filters['plant'];
            }
            
            if (!empty($filters['department'])) {
                $whereConditions[] = "a.department = ?";
                $params[] = $filters['department'];
            }
            
            $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";
            
            $query = "
                SELECT mt.id, mt.name, mt.description, mt.priority,
                    COUNT(ms.id) as total_overdue_schedules,
                    AVG(DATEDIFF(CURDATE(), ms.next_date)) as avg_days_overdue,
                    MAX(DATEDIFF(CURDATE(), ms.next_date)) as max_days_overdue,
                    COUNT(DISTINCT ms.asset_id) as affected_assets
                FROM maintenance_types mt
                JOIN maintenance_schedules ms ON mt.id = ms.maintenance_type_id
                JOIN assets a ON ms.asset_id = a.id
                JOIN asset_categories ac ON a.category_id = ac.id
                $whereClause
                AND ms.next_date < CURDATE() AND ms.status = 'active'
                GROUP BY mt.id, mt.name, mt.description, mt.priority
                ORDER BY total_overdue_schedules DESC, avg_days_overdue DESC
                LIMIT ?
            ";
            
            $params[] = $limit;
            
            return $this->executeQuery($query, $params);
        } catch (Exception $e) {
            error_log("Error in getMaintenanceTypesMostOverdueReport: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * گزارش روند انجام نگهداری در طول زمان
     * 
     * @param array $filters فیلترها (بازه زمانی، نوع نگهداری)
     * @param string $interval فاصله زمانی (daily, weekly, monthly)
     * @return array روند انجام نگهداری
     */
    public function getMaintenanceTrendReport($filters = [], $interval = 'monthly') {
        try {
            $whereConditions = ["ml.status = 'completed'"];
            $params = [];
            
            if (!empty($filters['date_from'])) {
                $whereConditions[] = "ml.performed_at >= ?";
                $params[] = $filters['date_from'];
            } else {
                // پیش‌فرض: یک سال گذشته
                $whereConditions[] = "ml.performed_at >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
            }
            
            if (!empty($filters['date_to'])) {
                $whereConditions[] = "ml.performed_at <= ?";
                $params[] = $filters['date_to'];
            }
            
            if (!empty($filters['maintenance_type_id'])) {
                $whereConditions[] = "ml.maintenance_type_id = ?";
                $params[] = $filters['maintenance_type_id'];
            }
            
            if (!empty($filters['category_id'])) {
                $whereConditions[] = "ac.id = ?";
                $params[] = $filters['category_id'];
            }
            
            $whereClause = "WHERE " . implode(" AND ", $whereConditions);
            
            $dateFormat = '';
            $groupBy = '';
            
            switch ($interval) {
                case 'daily':
                    $dateFormat = '%Y-%m-%d';
                    $groupBy = 'DATE(ml.performed_at)';
                    break;
                case 'weekly':
                    $dateFormat = '%Y-%u';
                    $groupBy = 'YEAR(ml.performed_at), WEEK(ml.performed_at)';
                    break;
                case 'monthly':
                default:
                    $dateFormat = '%Y-%m';
                    $groupBy = 'YEAR(ml.performed_at), MONTH(ml.performed_at)';
                    break;
            }
            
            $query = "
                SELECT 
                    DATE_FORMAT(ml.performed_at, '$dateFormat') as period,
                    COUNT(*) as maintenance_count,
                    COUNT(DISTINCT ml.asset_id) as assets_maintained
                FROM maintenance_logs ml
                JOIN assets a ON ml.asset_id = a.id
                JOIN asset_categories ac ON a.category_id = ac.id
                $whereClause
                GROUP BY $groupBy
                ORDER BY ml.performed_at ASC
            ";
            
            return $this->executeQuery($query, $params);
        } catch (Exception $e) {
            error_log("Error in getMaintenanceTrendReport: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * گزارش توزیع انواع نگهداری
     * 
     * @param array $filters فیلترها (بازه زمانی، دسته‌بندی)
     * @return array توزیع انواع نگهداری
     */
    public function getMaintenanceTypeDistributionReport($filters = []) {
        try {
            $whereConditions = ["ml.status = 'completed'"];
            $params = [];
            
            if (!empty($filters['date_from'])) {
                $whereConditions[] = "ml.performed_at >= ?";
                $params[] = $filters['date_from'];
            }
            
            if (!empty($filters['date_to'])) {
                $whereConditions[] = "ml.performed_at <= ?";
                $params[] = $filters['date_to'];
            }
            
            if (!empty($filters['category_id'])) {
                $whereConditions[] = "ac.id = ?";
                $params[] = $filters['category_id'];
            }
            
            $whereClause = "WHERE " . implode(" AND ", $whereConditions);
            
            $query = "
                SELECT 
                    mt.id, mt.name, mt.description, mt.priority,
                    COUNT(*) as maintenance_count,
                    COUNT(DISTINCT ml.asset_id) as assets_maintained,
                    (COUNT(*) / (SELECT COUNT(*) FROM maintenance_logs ml2 $whereClause)) * 100 as percentage
                FROM maintenance_logs ml
                JOIN maintenance_types mt ON ml.maintenance_type_id = mt.id
                JOIN assets a ON ml.asset_id = a.id
                JOIN asset_categories ac ON a.category_id = ac.id
                $whereClause
                GROUP BY mt.id, mt.name, mt.description, mt.priority
                ORDER BY maintenance_count DESC
            ";
            
            return $this->executeQuery($query, $params);
        } catch (Exception $e) {
            error_log("Error in getMaintenanceTypeDistributionReport: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * گزارش توزیع دسته‌بندی تجهیز‌های نگهداری‌شده
     * 
     * @param array $filters فیلترها (بازه زمانی، نوع نگهداری)
     * @return array توزیع دسته‌بندی تجهیز‌ها
     */
    public function getAssetCategoryDistributionReport($filters = []) {
        try {
            $whereConditions = ["ml.status = 'completed'"];
            $params = [];
            
            if (!empty($filters['date_from'])) {
                $whereConditions[] = "ml.performed_at >= ?";
                $params[] = $filters['date_from'];
            }
            
            if (!empty($filters['date_to'])) {
                $whereConditions[] = "ml.performed_at <= ?";
                $params[] = $filters['date_to'];
            }
            
            if (!empty($filters['maintenance_type_id'])) {
                $whereConditions[] = "ml.maintenance_type_id = ?";
                $params[] = $filters['maintenance_type_id'];
            }
            
            $whereClause = "WHERE " . implode(" AND ", $whereConditions);
            
            $query = "
                SELECT 
                    ac.id, ac.name, ac.description,
                    COUNT(*) as maintenance_count,
                    COUNT(DISTINCT ml.asset_id) as assets_maintained,
                    (COUNT(*) / (SELECT COUNT(*) FROM maintenance_logs ml2 $whereClause)) * 100 as percentage
                FROM maintenance_logs ml
                JOIN assets a ON ml.asset_id = a.id
                JOIN asset_categories ac ON a.category_id = ac.id
                $whereClause
                GROUP BY ac.id, ac.name, ac.description
                ORDER BY maintenance_count DESC
            ";
            
            return $this->executeQuery($query, $params);
        } catch (Exception $e) {
            error_log("Error in getAssetCategoryDistributionReport: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * گزارش توزیع واحدها و دپارتمان‌های تجهیز‌های نگهداری‌شده
     * 
     * @param array $filters فیلترها (بازه زمانی، نوع نگهداری، دسته‌بندی)
     * @param string $groupBy گروه‌بندی (plant یا department)
     * @return array توزیع واحدها یا دپارتمان‌ها
     */
    public function getDepartmentDistributionReport($filters = [], $groupBy = 'department') {
        try {
            $whereConditions = ["ml.status = 'completed'"];
            $params = [];
            
            if (!empty($filters['date_from'])) {
                $whereConditions[] = "ml.performed_at >= ?";
                $params[] = $filters['date_from'];
            }
            
            if (!empty($filters['date_to'])) {
                $whereConditions[] = "ml.performed_at <= ?";
                $params[] = $filters['date_to'];
            }
            
            if (!empty($filters['maintenance_type_id'])) {
                $whereConditions[] = "ml.maintenance_type_id = ?";
                $params[] = $filters['maintenance_type_id'];
            }
            
            if (!empty($filters['category_id'])) {
                $whereConditions[] = "ac.id = ?";
                $params[] = $filters['category_id'];
            }
            
            $whereClause = "WHERE " . implode(" AND ", $whereConditions);
            
            $groupField = $groupBy === 'plant' ? 'a.plant' : 'a.department';
            
            $query = "
                SELECT 
                    $groupField as name,
                    COUNT(*) as maintenance_count,
                    COUNT(DISTINCT ml.asset_id) as assets_maintained,
                    (COUNT(*) / (SELECT COUNT(*) FROM maintenance_logs ml2 $whereClause)) * 100 as percentage
                FROM maintenance_logs ml
                JOIN assets a ON ml.asset_id = a.id
                JOIN asset_categories ac ON a.category_id = ac.id
                $whereClause
                GROUP BY $groupField
                ORDER BY maintenance_count DESC
            ";
            
            return $this->executeQuery($query, $params);
        } catch (Exception $e) {
            error_log("Error in getDepartmentDistributionReport: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * گزارش مقایسه برنامه‌ریزی و انجام واقعی نگهداری
     * 
     * @param array $filters فیلترها (بازه زمانی، نوع نگهداری، دسته‌بندی)
     * @return array مقایسه برنامه‌ریزی و انجام واقعی
     */
    public function getPlannedVsActualReport($filters = []) {
        try {
            $whereConditions = [];
            $params = [];
            
            if (!empty($filters['date_from'])) {
                $whereConditions[] = "date_period >= ?";
                $params[] = $filters['date_from'];
            } else {
                // پیش‌فرض: 6 ماه گذشته
                $whereConditions[] = "date_period >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 6 MONTH), '%Y-%m')";
            }
            
            if (!empty($filters['date_to'])) {
                $whereConditions[] = "date_period <= ?";
                $params[] = $filters['date_to'];
            }
            
            if (!empty($filters['maintenance_type_id'])) {
                $whereConditions[] = "maintenance_type_id = ?";
                $params[] = $filters['maintenance_type_id'];
            }
            
            if (!empty($filters['category_id'])) {
                $whereConditions[] = "category_id = ?";
                $params[] = $filters['category_id'];
            }
            
            $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";
            
            $query = "
                WITH date_ranges AS (
                    SELECT 
                        DATE_FORMAT(ms.next_date, '%Y-%m') as date_period,
                        ms.maintenance_type_id,
                        a.category_id,
                        COUNT(*) as planned_count
                    FROM maintenance_schedules ms
                    JOIN assets a ON ms.asset_id = a.id
                    GROUP BY DATE_FORMAT(ms.next_date, '%Y-%m'), ms.maintenance_type_id, a.category_id
                    
                    UNION ALL
                    
                    SELECT 
                        DATE_FORMAT(ml.performed_at, '%Y-%m') as date_period,
                        ml.maintenance_type_id,
                        a.category_id,
                        0 as planned_count
                    FROM maintenance_logs ml
                    JOIN assets a ON ml.asset_id = a.id
                    WHERE ml.status = 'completed'
                    GROUP BY DATE_FORMAT(ml.performed_at, '%Y-%m'), ml.maintenance_type_id, a.category_id
                ),
                
                planned AS (
                    SELECT 
                        date_period,
                        maintenance_type_id,
                        category_id,
                        SUM(planned_count) as planned_count
                    FROM date_ranges
                    GROUP BY date_period, maintenance_type_id, category_id
                ),
                
                actual AS (
                    SELECT 
                        DATE_FORMAT(ml.performed_at, '%Y-%m') as date_period,
                        ml.maintenance_type_id,
                        a.category_id,
                        COUNT(*) as actual_count
                    FROM maintenance_logs ml
                    JOIN assets a ON ml.asset_id = a.id
                    WHERE ml.status = 'completed'
                    GROUP BY DATE_FORMAT(ml.performed_at, '%Y-%m'), ml.maintenance_type_id, a.category_id
                )
                
                SELECT 
                    p.date_period,
                    mt.name as maintenance_type_name,
                    ac.name as category_name,
                    p.planned_count,
                    COALESCE(a.actual_count, 0) as actual_count,
                    CASE 
                        WHEN p.planned_count > 0 THEN (COALESCE(a.actual_count, 0) / p.planned_count) * 100
                        ELSE 0
                    END as completion_percentage
                FROM planned p
                LEFT JOIN actual a ON p.date_period = a.date_period 
                    AND p.maintenance_type_id = a.maintenance_type_id 
                    AND p.category_id = a.category_id
                JOIN maintenance_types mt ON p.maintenance_type_id = mt.id
                JOIN asset_categories ac ON p.category_id = ac.id
                $whereClause
                ORDER BY p.date_period ASC, mt.name ASC
            ";
            
            return $this->executeQuery($query, $params);
        } catch (Exception $e) {
            error_log("Error in getPlannedVsActualReport: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * گزارش میانگین زمان بین خرابی‌ها (MTBF)
     * 
     * @param array $filters فیلترها (دسته‌بندی، نوع تجهیز، بازه زمانی)
     * @param int $limit تعداد نتایج
     * @return array میانگین زمان بین خرابی‌ها
     */
    public function getMTBFReport($filters = [], $limit = 10) {
        try {
            $whereConditions = ["ml.maintenance_type_id IN (SELECT id FROM maintenance_types WHERE is_repair = 1)"];
            $params = [];
            
            if (!empty($filters['category_id'])) {
                $whereConditions[] = "a.category_id = ?";
                $params[] = $filters['category_id'];
            }
            
            if (!empty($filters['model_id'])) {
                $whereConditions[] = "a.model_id = ?";
                $params[] = $filters['model_id'];
            }
            
            if (!empty($filters['date_from'])) {
                $whereConditions[] = "ml.performed_at >= ?";
                $params[] = $filters['date_from'];
            }
            
            if (!empty($filters['date_to'])) {
                $whereConditions[] = "ml.performed_at <= ?";
                $params[] = $filters['date_to'];
            }
            
            $whereClause = "WHERE " . implode(" AND ", $whereConditions);
            
            $query = "
                WITH repair_logs AS (
                    SELECT 
                        ml.asset_id,
                        ml.performed_at,
                        a.name as asset_name,
                        a.asset_tag,
                        ac.name as category_name,
                        am.name as model_name,
                        LAG(ml.performed_at) OVER (PARTITION BY ml.asset_id ORDER BY ml.performed_at) as prev_repair_date
                    FROM maintenance_logs ml
                    JOIN assets a ON ml.asset_id = a.id
                    JOIN asset_categories ac ON a.category_id = ac.id
                    JOIN asset_models am ON a.model_id = am.id
                    $whereClause
                )
                
                SELECT 
                    asset_id,
                    asset_name,
                    asset_tag,
                    category_name,
                    model_name,
                    COUNT(*) as repair_count,
                    AVG(TIMESTAMPDIFF(DAY, prev_repair_date, performed_at)) as avg_days_between_repairs
                FROM repair_logs
                WHERE prev_repair_date IS NOT NULL
                GROUP BY asset_id, asset_name, asset_tag, category_name, model_name
                HAVING repair_count > 1
                ORDER BY avg_days_between_repairs ASC
                LIMIT ?
            ";
            
            $params[] = $limit;
            
            return $this->executeQuery($query, $params);
        } catch (Exception $e) {
            error_log("Error in getMTBFReport: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * گزارش میانگین زمان تعمیر (MTTR)
     * 
     * @param array $filters فیلترها (دسته‌بندی، نوع تجهیز، بازه زمانی)
     * @param int $limit تعداد نتایج
     * @return array میانگین زمان تعمیر
     */
    public function getMTTRReport($filters = [], $limit = 10) {
        try {
            $whereConditions = ["ml.maintenance_type_id IN (SELECT id FROM maintenance_types WHERE is_repair = 1)"];
            $params = [];
            
            if (!empty($filters['category_id'])) {
                $whereConditions[] = "a.category_id = ?";
                $params[] = $filters['category_id'];
            }
            
            if (!empty($filters['model_id'])) {
                $whereConditions[] = "a.model_id = ?";
                $params[] = $filters['model_id'];
            }
            
            if (!empty($filters['date_from'])) {
                $whereConditions[] = "ml.performed_at >= ?";
                $params[] = $filters['date_from'];
            }
            
            if (!empty($filters['date_to'])) {
                $whereConditions[] = "ml.performed_at <= ?";
                $params[] = $filters['date_to'];
            }
            
            $whereClause = "WHERE " . implode(" AND ", $whereConditions) . " AND ml.started_at IS NOT NULL AND ml.completed_at IS NOT NULL";
            
            $query = "
                SELECT 
                    a.id as asset_id,
                    a.name as asset_name,
                    a.asset_tag,
                    ac.name as category_name,
                    am.name as model_name,
                    COUNT(*) as repair_count,
                    AVG(TIMESTAMPDIFF(MINUTE, ml.started_at, ml.completed_at)) as avg_repair_time_minutes
                FROM maintenance_logs ml
                JOIN assets a ON ml.asset_id = a.id
                JOIN asset_categories ac ON a.category_id = ac.id
                JOIN asset_models am ON a.model_id = am.id
                $whereClause
                GROUP BY a.id, a.name, a.asset_tag, ac.name, am.name
                HAVING repair_count > 0
                ORDER BY avg_repair_time_minutes DESC
                LIMIT ?
            ";
            
            $params[] = $limit;
            
            return $this->executeQuery($query, $params);
        } catch (Exception $e) {
            error_log("Error in getMTTRReport: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * گزارش قابلیت اطمینان تجهیز‌ها
     * 
     * @param array $filters فیلترها (دسته‌بندی، نوع تجهیز، بازه زمانی)
     * @param int $limit تعداد نتایج
     * @return array قابلیت اطمینان تجهیز‌ها
     */
    public function getAssetReliabilityReport($filters = [], $limit = 10) {
        try {
            $whereConditions = [];
            $params = [];
            
            if (!empty($filters['category_id'])) {
                $whereConditions[] = "a.category_id = ?";
                $params[] = $filters['category_id'];
            }
            
            if (!empty($filters['model_id'])) {
                $whereConditions[] = "a.model_id = ?";
                $params[] = $filters['model_id'];
            }
            
            if (!empty($filters['date_from'])) {
                $whereConditions[] = "ml.performed_at >= ?";
                $params[] = $filters['date_from'];
            } else {
                // پیش‌فرض: یک سال گذشته
                $whereConditions[] = "ml.performed_at >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
            }
            
            if (!empty($filters['date_to'])) {
                $whereConditions[] = "ml.performed_at <= ?";
                $params[] = $filters['date_to'];
            }
            
            $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";
            
            $query = "
                WITH asset_stats AS (
                    SELECT 
                        a.id as asset_id,
                        a.name as asset_name,
                        a.asset_tag,
                        ac.name as category_name,
                        am.name as model_name,
                        COUNT(CASE WHEN mt.is_repair = 1 THEN 1 END) as repair_count,
                        COUNT(CASE WHEN mt.is_repair = 0 THEN 1 END) as maintenance_count,
                        DATEDIFF(MAX(ml.performed_at), MIN(ml.performed_at)) as days_in_service
                    FROM assets a
                    JOIN asset_categories ac ON a.category_id = ac.id
                    JOIN asset_models am ON a.model_id = am.id
                    LEFT JOIN maintenance_logs ml ON a.id = ml.asset_id
                    LEFT JOIN maintenance_types mt ON ml.maintenance_type_id = mt.id
                    $whereClause
                    GROUP BY a.id, a.name, a.asset_tag, ac.name, am.name
                    HAVING days_in_service > 0
                )
                
                SELECT 
                    asset_id,
                    asset_name,
                    asset_tag,
                    category_name,
                    model_name,
                    repair_count,
                    maintenance_count,
                    days_in_service,
                    (days_in_service / (repair_count + 1)) as days_between_failures,
                    (1 - (repair_count / (days_in_service / 365))) as annual_reliability
                FROM asset_stats
                ORDER BY annual_reliability DESC
                LIMIT ?
            ";
            
            $params[] = $limit;
            
            return $this->executeQuery($query, $params);
        } catch (Exception $e) {
            error_log("Error in getAssetReliabilityReport: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * گزارش هزینه‌های نگهداری و تعمیرات
     * 
     * @param array $filters فیلترها (دسته‌بندی، نوع نگهداری، بازه زمانی)
     * @return array هزینه‌های نگهداری و تعمیرات
     */
    public function getMaintenanceCostReport($filters = []) {
        try {
            $whereConditions = ["ml.status = 'completed'"];
            $params = [];
            
            if (!empty($filters['date_from'])) {
                $whereConditions[] = "ml.performed_at >= ?";
                $params[] = $filters['date_from'];
            }
            
            if (!empty($filters['date_to'])) {
                $whereConditions[] = "ml.performed_at <= ?";
                $params[] = $filters['date_to'];
            }
            
            if (!empty($filters['maintenance_type_id'])) {
                $whereConditions[] = "ml.maintenance_type_id = ?";
                $params[] = $filters['maintenance_type_id'];
            }
            
            if (!empty($filters['category_id'])) {
                $whereConditions[] = "ac.id = ?";
                $params[] = $filters['category_id'];
            }
            
            if (!empty($filters['is_repair'])) {
                $whereConditions[] = "mt.is_repair = ?";
                $params[] = $filters['is_repair'];
            }
            
            $whereClause = "WHERE " . implode(" AND ", $whereConditions);
            
            $query = "
                SELECT 
                    DATE_FORMAT(ml.performed_at, '%Y-%m') as period,
                    SUM(ml.parts_cost) as parts_cost,
                    SUM(ml.labor_cost) as labor_cost,
                    SUM(ml.other_cost) as other_cost,
                    SUM(ml.parts_cost + ml.labor_cost + ml.other_cost) as total_cost,
                    COUNT(*) as maintenance_count
                FROM maintenance_logs ml
                JOIN assets a ON ml.asset_id = a.id
                JOIN maintenance_types mt ON ml.maintenance_type_id = mt.id
                JOIN asset_categories ac ON a.category_id = ac.id
                $whereClause
                GROUP BY DATE_FORMAT(ml.performed_at, '%Y-%m')
                ORDER BY period ASC
            ";
            
            $costByPeriod = $this->executeQuery($query, $params);
            
            // هزینه بر اساس نوع نگهداری
            $costByTypeQuery = "
                SELECT 
                    mt.id, mt.name, mt.is_repair,
                    SUM(ml.parts_cost + ml.labor_cost + ml.other_cost) as total_cost,
                    COUNT(*) as maintenance_count,
                    AVG(ml.parts_cost + ml.labor_cost + ml.other_cost) as avg_cost
                FROM maintenance_logs ml
                JOIN assets a ON ml.asset_id = a.id
                JOIN maintenance_types mt ON ml.maintenance_type_id = mt.id
                JOIN asset_categories ac ON a.category_id = ac.id
                $whereClause
                GROUP BY mt.id, mt.name, mt.is_repair
                ORDER BY total_cost DESC
            ";
            
            $costByType = $this->executeQuery($costByTypeQuery, $params);
            
            // هزینه بر اساس دسته‌بندی تجهیز
            $costByCategoryQuery = "
                SELECT 
                    ac.id, ac.name,
                    SUM(ml.parts_cost + ml.labor_cost + ml.other_cost) as total_cost,
                    COUNT(*) as maintenance_count,
                    AVG(ml.parts_cost + ml.labor_cost + ml.other_cost) as avg_cost
                FROM maintenance_logs ml
                JOIN assets a ON ml.asset_id = a.id
                JOIN maintenance_types mt ON ml.maintenance_type_id = mt.id
                JOIN asset_categories ac ON a.category_id = ac.id
                $whereClause
                GROUP BY ac.id, ac.name
                ORDER BY total_cost DESC
            ";
            
            $costByCategory = $this->executeQuery($costByCategoryQuery, $params);
            
            // هزینه بر اساس تجهیز
            $costByAssetQuery = "
                SELECT 
                    a.id, a.name, a.asset_tag,
                    SUM(ml.parts_cost + ml.labor_cost + ml.other_cost) as total_cost,
                    COUNT(*) as maintenance_count,
                    AVG(ml.parts_cost + ml.labor_cost + ml.other_cost) as avg_cost
                FROM maintenance_logs ml
                JOIN assets a ON ml.asset_id = a.id
                JOIN maintenance_types mt ON ml.maintenance_type_id = mt.id
                JOIN asset_categories ac ON a.category_id = ac.id
                $whereClause
                GROUP BY a.id, a.name, a.asset_tag
                ORDER BY total_cost DESC
                LIMIT 10
            ";
            
            $costByAsset = $this->executeQuery($costByAssetQuery, $params);
            
            // جمع کل هزینه‌ها
            $totalCostQuery = "
                SELECT 
                    SUM(ml.parts_cost) as total_parts_cost,
                    SUM(ml.labor_cost) as total_labor_cost,
                    SUM(ml.other_cost) as total_other_cost,
                    SUM(ml.parts_cost + ml.labor_cost + ml.other_cost) as grand_total_cost,
                    COUNT(*) as total_maintenance_count
                FROM maintenance_logs ml
                JOIN assets a ON ml.asset_id = a.id
                JOIN maintenance_types mt ON ml.maintenance_type_id = mt.id
                JOIN asset_categories ac ON a.category_id = ac.id
                $whereClause
            ";
            
            $totalCost = $this->executeQuery($totalCostQuery, $params);
            
            return [
                'cost_by_period' => $costByPeriod,
                'cost_by_type' => $costByType,
                'cost_by_category' => $costByCategory,
                'cost_by_asset' => $costByAsset,
                'total_cost' => $totalCost[0]
            ];
        } catch (Exception $e) {
            error_log("Error in getMaintenanceCostReport: " . $e->getMessage());
            return [
                'cost_by_period' => [],
                'cost_by_type' => [],
                'cost_by_category' => [],
                'cost_by_asset' => [],
                'total_cost' => [
                    'total_parts_cost' => 0,
                    'total_labor_cost' => 0,
                    'total_other_cost' => 0,
                    'grand_total_cost' => 0,
                    'total_maintenance_count' => 0
                ]
            ];
        }
    }
    
    /**
     * گزارش کارایی برنامه نگهداری و تعمیرات
     * 
     * @param array $filters فیلترها (بازه زمانی، دسته‌بندی)
     * @return array کارایی برنامه نگهداری و تعمیرات
     */
    public function getMaintenanceEffectivenessReport($filters = []) {
        try {
            $whereConditions = [];
            $params = [];
            
            if (!empty($filters['date_from'])) {
                $whereConditions[] = "ml.performed_at >= ?";
                $params[] = $filters['date_from'];
            } else {
                // پیش‌فرض: یک سال گذشته
                $whereConditions[] = "ml.performed_at >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
            }
            
            if (!empty($filters['date_to'])) {
                $whereConditions[] = "ml.performed_at <= ?";
                $params[] = $filters['date_to'];
            }
            
            if (!empty($filters['category_id'])) {
                $whereConditions[] = "ac.id = ?";
                $params[] = $filters['category_id'];
            }
            
            $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";
            
            // نرخ تکمیل برنامه‌های نگهداری
            $completionRateQuery = "
                WITH planned_maintenance AS (
                    SELECT 
                        DATE_FORMAT(ms.next_date, '%Y-%m') as period,
                        COUNT(*) as planned_count
                    FROM maintenance_schedules ms
                    JOIN assets a ON ms.asset_id = a.id
                    JOIN asset_categories ac ON a.category_id = ac.id
                    $whereClause
                    AND ms.next_date <= CURDATE()
                    GROUP BY DATE_FORMAT(ms.next_date, '%Y-%m')
                ),
                
                completed_maintenance AS (
                    SELECT 
                        DATE_FORMAT(ml.performed_at, '%Y-%m') as period,
                        COUNT(*) as completed_count
                    FROM maintenance_logs ml
                    JOIN assets a ON ml.asset_id = a.id
                    JOIN asset_categories ac ON a.category_id = ac.id
                    $whereClause
                    AND ml.status = 'completed'
                    GROUP BY DATE_FORMAT(ml.performed_at, '%Y-%m')
                )
                
                SELECT 
                    p.period,
                    p.planned_count,
                    COALESCE(c.completed_count, 0) as completed_count,
                    CASE 
                        WHEN p.planned_count > 0 THEN (COALESCE(c.completed_count, 0) / p.planned_count) * 100
                        ELSE 0
                    END as completion_percentage
                FROM planned_maintenance p
                LEFT JOIN completed_maintenance c ON p.period = c.period
                ORDER BY p.period ASC
            ";
            
            $completionRate = $this->executeQuery($completionRateQuery, $params);
            
            // نرخ نگهداری پیشگیرانه در برابر نگهداری اصلاحی
            $preventiveVsCorrectiveQuery = "
                SELECT 
                    DATE_FORMAT(ml.performed_at, '%Y-%m') as period,
                    COUNT(CASE WHEN mt.is_repair = 0 THEN 1 END) as preventive_count,
                    COUNT(CASE WHEN mt.is_repair = 1 THEN 1 END) as corrective_count,
                    CASE 
                        WHEN COUNT(*) > 0 THEN (COUNT(CASE WHEN mt.is_repair = 0 THEN 1 END) / COUNT(*)) * 100
                        ELSE 0
                    END as preventive_percentage
                FROM maintenance_logs ml
                JOIN assets a ON ml.asset_id = a.id
                JOIN maintenance_types mt ON ml.maintenance_type_id = mt.id
                JOIN asset_categories ac ON a.category_id = ac.id
                $whereClause
                GROUP BY DATE_FORMAT(ml.performed_at, '%Y-%m')
                ORDER BY period ASC
            ";
            
            $preventiveVsCorrective = $this->executeQuery($preventiveVsCorrectiveQuery, $params);
            
            // نرخ تعمیرات اضطراری
            $emergencyRateQuery = "
                SELECT 
                    DATE_FORMAT(ml.performed_at, '%Y-%m') as period,
                    COUNT(CASE WHEN ml.is_emergency = 1 THEN 1 END) as emergency_count,
                    COUNT(*) as total_count,
                    CASE 
                        WHEN COUNT(*) > 0 THEN (COUNT(CASE WHEN ml.is_emergency = 1 THEN 1 END) / COUNT(*)) * 100
                        ELSE 0
                    END as emergency_percentage
                FROM maintenance_logs ml
                JOIN assets a ON ml.asset_id = a.id
                JOIN asset_categories ac ON a.category_id = ac.id
                $whereClause
                GROUP BY DATE_FORMAT(ml.performed_at, '%Y-%m')
                ORDER BY period ASC
            ";
            
            $emergencyRate = $this->executeQuery($emergencyRateQuery, $params);
            
            // میانگین زمان بین خرابی‌ها (MTBF) در طول زمان
            $mtbfTrendQuery = "
                WITH repair_logs AS (
                    SELECT 
                        ml.asset_id,
                        ml.performed_at,
                        DATE_FORMAT(ml.performed_at, '%Y-%m') as period,
                        LAG(ml.performed_at) OVER (PARTITION BY ml.asset_id ORDER BY ml.performed_at) as prev_repair_date
                    FROM maintenance_logs ml
                    JOIN assets a ON ml.asset_id = a.id
                    JOIN maintenance_types mt ON ml.maintenance_type_id = mt.id
                    JOIN asset_categories ac ON a.category_id = ac.id
                    $whereClause
                    AND mt.is_repair = 1
                )
                
                SELECT 
                    period,
                    AVG(TIMESTAMPDIFF(DAY, prev_repair_date, performed_at)) as avg_days_between_repairs
                FROM repair_logs
                WHERE prev_repair_date IS NOT NULL
                GROUP BY period
                ORDER BY period ASC
            ";
            
            $mtbfTrend = $this->executeQuery($mtbfTrendQuery, $params);
            
            // میانگین زمان تعمیر (MTTR) در طول زمان
            $mttrTrendQuery = "
                SELECT 
                    DATE_FORMAT(ml.performed_at, '%Y-%m') as period,
                    AVG(TIMESTAMPDIFF(MINUTE, ml.started_at, ml.completed_at)) as avg_repair_time_minutes
                FROM maintenance_logs ml
                JOIN assets a ON ml.asset_id = a.id
                JOIN maintenance_types mt ON ml.maintenance_type_id = mt.id
                JOIN asset_categories ac ON a.category_id = ac.id
                $whereClause
                AND mt.is_repair = 1
                AND ml.started_at IS NOT NULL AND ml.completed_at IS NOT NULL
                GROUP BY DATE_FORMAT(ml.performed_at, '%Y-%m')
                ORDER BY period ASC
            ";
            
            $mttrTrend = $this->executeQuery($mttrTrendQuery, $params);
            
            return [
                'completion_rate' => $completionRate,
                'preventive_vs_corrective' => $preventiveVsCorrective,
                'emergency_rate' => $emergencyRate,
                'mtbf_trend' => $mtbfTrend,
                'mttr_trend' => $mttrTrend
            ];
        } catch (Exception $e) {
            error_log("Error in getMaintenanceEffectivenessReport: " . $e->getMessage());
            return [
                'completion_rate' => [],
                'preventive_vs_corrective' => [],
                'emergency_rate' => [],
                'mtbf_trend' => [],
                'mttr_trend' => []
            ];
        }
    }
    
    /**
     * دریافت داده‌های گزارش برای داشبورد
     * 
     * @param array $filters فیلترها (بازه زمانی، دسته‌بندی)
     * @return array داده‌های داشبورد
     */
    public function getDashboardData($filters = []) {
        try {
            // خلاصه وضعیت نگهداری و تعمیرات
            $summary = $this->getMaintenanceSummaryReport($filters);
            
            // تجهیز‌های با بیشترین نیاز به نگهداری
            $assetsNeedingMaintenance = $this->getAssetsMostNeedingMaintenanceReport($filters, 5);
            
            // انواع نگهداری با بیشترین تاخیر
            $maintenanceTypesMostOverdue = $this->getMaintenanceTypesMostOverdueReport($filters, 5);
            
            // روند انجام نگهداری در طول زمان
            $maintenanceTrend = $this->getMaintenanceTrendReport($filters, 'monthly');
            
            // توزیع انواع نگهداری
            $maintenanceTypeDistribution = $this->getMaintenanceTypeDistributionReport($filters);
            
            // نرخ نگهداری پیشگیرانه در برابر نگهداری اصلاحی
            $preventiveVsCorrective = $this->getDepartmentDistributionReport($filters, 'department');
            
            // برنامه‌های نگهداری آینده
            $upcomingMaintenance = $this->getUpcomingMaintenanceReport($filters, 1, 5)['schedules'];
            
            // سرویس‌های معوق
            $overdueMaintenance = $this->getOverdueMaintenanceReport($filters, 1, 5)['schedules'];
            
            return [
                'summary' => $summary,
                'assets_needing_maintenance' => $assetsNeedingMaintenance,
                'maintenance_types_most_overdue' => $maintenanceTypesMostOverdue,
                'maintenance_trend' => $maintenanceTrend,
                'maintenance_type_distribution' => $maintenanceTypeDistribution,
                'department_distribution' => $preventiveVsCorrective,
                'upcoming_maintenance' => $upcomingMaintenance,
                'overdue_maintenance' => $overdueMaintenance
            ];
        } catch (Exception $e) {
            error_log("Error in getDashboardData: " . $e->getMessage());
            return [
                'summary' => [],
                'assets_needing_maintenance' => [],
                'maintenance_types_most_overdue' => [],
                'maintenance_trend' => [],
                'maintenance_type_distribution' => [],
                'department_distribution' => [],
                'upcoming_maintenance' => [],
                'overdue_maintenance' => []
            ];
        }
    }
    
    /**
     * ایجاد و ذخیره گزارش
     * 
     * @param string $reportType نوع گزارش
     * @param array $filters فیلترها
     * @param int $userId شناسه کاربر
     * @return int|bool شناسه گزارش یا false در صورت خطا
     */
    public function saveReport($reportType, $filters, $userId) {
        try {
            $query = "
                INSERT INTO maintenance_reports 
                (report_type, filters, user_id, created_at)
                VALUES (?, ?, ?, NOW())
            ";
            
            $result = $this->executeQuery($query, [
                $reportType,
                json_encode($filters),
                $userId
            ]);
            
            if ($result) {
                return $this->db instanceof PDO ? $this->db->lastInsertId() : $this->db->insert_id;
            }
            
            return false;
        } catch (Exception $e) {
            error_log("Error in saveReport: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * دریافت گزارش‌های ذخیره‌شده
     * 
     * @param int $userId شناسه کاربر
     * @param int $page شماره صفحه
     * @param int $perPage تعداد آیتم در هر صفحه
     * @return array گزارش‌های ذخیره‌شده و اطلاعات صفحه‌بندی
     */
    public function getSavedReports($userId, $page = 1, $perPage = 10) {
        try {
            $offset = ($page - 1) * $perPage;
            
            $query = "
                SELECT mr.*, u.fullname as user_name
                FROM maintenance_reports mr
                JOIN users u ON mr.user_id = u.id
                WHERE mr.user_id = ?
                ORDER BY mr.created_at DESC
                LIMIT $perPage OFFSET $offset
            ";
            
            $reports = $this->executeQuery($query, [$userId]);
            
            // دریافت تعداد کل رکوردها
            $countQuery = "
                SELECT COUNT(*) as total
                FROM maintenance_reports
                WHERE user_id = ?
            ";
            
            $countResult = $this->executeQuery($countQuery, [$userId]);
            $totalCount = $countResult[0]['total'];
            $totalPages = ceil($totalCount / $perPage);
            
            return [
                'reports' => $reports,
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
            error_log("Error in getSavedReports: " . $e->getMessage());
            return [
                'reports' => [],
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
     * دریافت گزارش ذخیره‌شده
     * 
     * @param int $reportId شناسه گزارش
     * @return array|null گزارش ذخیره‌شده
     */
    public function getSavedReport($reportId) {
        try {
            $query = "
                SELECT mr.*, u.fullname as user_name
                FROM maintenance_reports mr
                JOIN users u ON mr.user_id = u.id
                WHERE mr.id = ?
            ";
            
            $result = $this->executeQuery($query, [$reportId]);
            
            if (!empty($result)) {
                $report = $result[0];
                $report['filters'] = json_decode($report['filters'], true);
                return $report;
            }
            
            return null;
        } catch (Exception $e) {
            error_log("Error in getSavedReport: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * حذف گزارش ذخیره‌شده
     * 
     * @param int $reportId شناسه گزارش
     * @param int $userId شناسه کاربر
     * @return bool نتیجه عملیات
     */
    public function deleteSavedReport($reportId, $userId) {
        try {
            $query = "DELETE FROM maintenance_reports WHERE id = ? AND user_id = ?";
            return $this->executeQuery($query, [$reportId, $userId]);
        } catch (Exception $e) {
            error_log("Error in deleteSavedReport: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * به‌روزرسانی گزارش ذخیره‌شده
     * 
     * @param int $reportId شناسه گزارش
     * @param array $filters فیلترهای جدید
     * @param int $userId شناسه کاربر
     * @return bool نتیجه عملیات
     */
    public function updateSavedReport($reportId, $filters, $userId) {
        try {
            $query = "
                UPDATE maintenance_reports
                SET filters = ?, updated_at = NOW()
                WHERE id = ? AND user_id = ?
            ";
            
            return $this->executeQuery($query, [
                json_encode($filters),
                $reportId,
                $userId
            ]);
        } catch (Exception $e) {
            error_log("Error in updateSavedReport: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * ایجاد گزارش برنامه‌ریزی‌شده
     * 
     * @param string $reportType نوع گزارش
     * @param array $filters فیلترها
     * @param string $schedule زمان‌بندی (daily, weekly, monthly)
     * @param array $recipients گیرندگان گزارش
     * @param int $userId شناسه کاربر
     * @return int|bool شناسه گزارش یا false در صورت خطا
     */
    public function createScheduledReport($reportType, $filters, $schedule, $recipients, $userId) {
        try {
            $query = "
                INSERT INTO maintenance_scheduled_reports 
                (report_type, filters, schedule, recipients, user_id, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ";
            
            $result = $this->executeQuery($query, [
                $reportType,
                json_encode($filters),
                $schedule,
                json_encode($recipients),
                $userId
            ]);
            
            if ($result) {
                return $this->db instanceof PDO ? $this->db->lastInsertId() : $this->db->insert_id;
            }
            
            return false;
        } catch (Exception $e) {
            error_log("Error in createScheduledReport: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * دریافت گزارش‌های برنامه‌ریزی‌شده
     * 
     * @param int $userId شناسه کاربر
     * @param int $page شماره صفحه
     * @param int $perPage تعداد آیتم در هر صفحه
     * @return array گزارش‌های برنامه‌ریزی‌شده و اطلاعات صفحه‌بندی
     */
    public function getScheduledReports($userId, $page = 1, $perPage = 10) {
        try {
            $offset = ($page - 1) * $perPage;
            
            $query = "
                SELECT sr.*, u.fullname as user_name
                FROM maintenance_scheduled_reports sr
                JOIN users u ON sr.user_id = u.id
                WHERE sr.user_id = ?
                ORDER BY sr.created_at DESC
                LIMIT $perPage OFFSET $offset
            ";
            
            $reports = $this->executeQuery($query, [$userId]);
            
            // پردازش داده‌های JSON
            foreach ($reports as &$report) {
                $report['filters'] = json_decode($report['filters'], true);
                $report['recipients'] = json_decode($report['recipients'], true);
            }
            
            // دریافت تعداد کل رکوردها
            $countQuery = "
                SELECT COUNT(*) as total
                FROM maintenance_scheduled_reports
                WHERE user_id = ?
            ";
            
            $countResult = $this->executeQuery($countQuery, [$userId]);
            $totalCount = $countResult[0]['total'];
            $totalPages = ceil($totalCount / $perPage);
            
            return [
                'reports' => $reports,
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
            error_log("Error in getScheduledReports: " . $e->getMessage());
            return [
                'reports' => [],
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
     * به‌روزرسانی گزارش برنامه‌ریزی‌شده
     * 
     * @param int $reportId شناسه گزارش
     * @param array $data داده‌های جدید
     * @param int $userId شناسه کاربر
     * @return bool نتیجه عملیات
     */
    public function updateScheduledReport($reportId, $data, $userId) {
        try {
            $updateFields = [];
            $params = [];
            
            if (isset($data['filters'])) {
                $updateFields[] = "filters = ?";
                $params[] = json_encode($data['filters']);
            }
            
            if (isset($data['schedule'])) {
                $updateFields[] = "schedule = ?";
                $params[] = $data['schedule'];
            }
            
            if (isset($data['recipients'])) {
                $updateFields[] = "recipients = ?";
                $params[] = json_encode($data['recipients']);
            }
            
            if (isset($data['is_active'])) {
                $updateFields[] = "is_active = ?";
                $params[] = $data['is_active'];
            }
            
            if (empty($updateFields)) {
                return false;
            }
            
            $updateFields[] = "updated_at = NOW()";
            $params[] = $reportId;
            $params[] = $userId;
            
            $query = "
                UPDATE maintenance_scheduled_reports
                SET " . implode(", ", $updateFields) . "
                WHERE id = ? AND user_id = ?
            ";
            
            return $this->executeQuery($query, $params);
        } catch (Exception $e) {
            error_log("Error in updateScheduledReport: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * حذف گزارش برنامه‌ریزی‌شده
     * 
     * @param int $reportId شناسه گزارش
     * @param int $userId شناسه کاربر
     * @return bool نتیجه عملیات
     */
    public function deleteScheduledReport($reportId, $userId) {
        try {
            $query = "DELETE FROM maintenance_scheduled_reports WHERE id = ? AND user_id = ?";
            return $this->executeQuery($query, [$reportId, $userId]);
        } catch (Exception $e) {
            error_log("Error in deleteScheduledReport: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * دریافت گزارش‌های برنامه‌ریزی‌شده برای اجرا
     * 
     * @param string $schedule زمان‌بندی (daily, weekly, monthly)
     * @return array گزارش‌های برنامه‌ریزی‌شده
     */
    public function getScheduledReportsToRun($schedule) {
        try {
            $query = "
                SELECT sr.*, u.fullname as user_name, u.email as user_email
                FROM maintenance_scheduled_reports sr
                JOIN users u ON sr.user_id = u.id
                WHERE sr.schedule = ? AND sr.is_active = 1
            ";
            
            $reports = $this->executeQuery($query, [$schedule]);
            
            // پردازش داده‌های JSON
            foreach ($reports as &$report) {
                $report['filters'] = json_decode($report['filters'], true);
                $report['recipients'] = json_decode($report['recipients'], true);
            }
            
            return $reports;
        } catch (Exception $e) {
            error_log("Error in getScheduledReportsToRun: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * ثبت اجرای گزارش برنامه‌ریزی‌شده
     * 
     * @param int $reportId شناسه گزارش
     * @return bool نتیجه عملیات
     */
    public function logScheduledReportExecution($reportId) {
        try {
            $query = "
                UPDATE maintenance_scheduled_reports
                SET last_run = NOW()
                WHERE id = ?
            ";
            
            return $this->executeQuery($query, [$reportId]);
        } catch (Exception $e) {
            error_log("Error in logScheduledReportExecution: " . $e->getMessage());
            return false;
        }
    }
}