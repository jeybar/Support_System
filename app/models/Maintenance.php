<?php
require_once __DIR__ . '/../core/Database.php';

class Maintenance {
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
    
    // دریافت همه انواع سرویس‌های ادواری
    public function getAllMaintenanceTypes() {
        try {
            $query = "SELECT * FROM maintenance_types ORDER BY name";
            return $this->db->query($query);
        } catch (Exception $e) {
            error_log("Error in getAllMaintenanceTypes: " . $e->getMessage());
            return [];
        }
    }

    // دریافت یک نوع سرویس ادواری با شناسه
    public function getMaintenanceTypeById($id) {
        try {
            $query = "SELECT * FROM maintenance_types WHERE id = ?";
            $result = $this->db->query($query, [$id]);
            return $result ? $result[0] : null;
        } catch (Exception $e) {
            error_log("Error in getMaintenanceTypeById: " . $e->getMessage());
            return null;
        }
    }

    // ایجاد یک نوع سرویس ادواری جدید
    public function createMaintenanceType($name, $description, $intervalDays) {
        try {
            $query = "INSERT INTO maintenance_types (name, description, interval_days) VALUES (?, ?, ?)";
            return $this->db->query($query, [$name, $description, $intervalDays]);
        } catch (Exception $e) {
            error_log("Error in createMaintenanceType: " . $e->getMessage());
            return false;
        }
    }

    // به‌روزرسانی یک نوع سرویس ادواری
    public function updateMaintenanceType($id, $name, $description, $intervalDays) {
        try {
            $query = "UPDATE maintenance_types SET name = ?, description = ?, interval_days = ? WHERE id = ?";
            return $this->db->query($query, [$name, $description, $intervalDays, $id]);
        } catch (Exception $e) {
            error_log("Error in updateMaintenanceType: " . $e->getMessage());
            return false;
        }
    }

    // حذف یک نوع سرویس ادواری
    public function deleteMaintenanceType($id) {
        try {
            $query = "DELETE FROM maintenance_types WHERE id = ?";
            return $this->db->query($query, [$id]);
        } catch (Exception $e) {
            error_log("Error in deleteMaintenanceType: " . $e->getMessage());
            return false;
        }
    }

    // دریافت همه برنامه‌های سرویس ادواری
    public function getAllMaintenanceSchedules($page = 1, $limit = 10, $filters = []) {
        try {
            $offset = ($page - 1) * $limit;
            
            $whereClause = "";
            $params = [];
            
            if (!empty($filters)) {
                $conditions = [];
                
                if (!empty($filters['asset_id'])) {
                    $conditions[] = "ms.asset_id = ?";
                    $params[] = $filters['asset_id'];
                }
                
                if (!empty($filters['maintenance_type_id'])) {
                    $conditions[] = "ms.maintenance_type_id = ?";
                    $params[] = $filters['maintenance_type_id'];
                }
                
                if (!empty($filters['due_date_start'])) {
                    $conditions[] = "ms.next_maintenance_date >= ?";
                    $params[] = $filters['due_date_start'];
                }
                
                if (!empty($filters['due_date_end'])) {
                    $conditions[] = "ms.next_maintenance_date <= ?";
                    $params[] = $filters['due_date_end'];
                }
                
                if (!empty($conditions)) {
                    $whereClause = "WHERE " . implode(" AND ", $conditions);
                }
            }
            
            $query = "SELECT ms.*, a.asset_tag, am.name as model_name, mt.name as maintenance_type,
                             u.fullname as assigned_to
                      FROM maintenance_schedules ms
                      JOIN assets a ON ms.asset_id = a.id
                      JOIN asset_models am ON a.model_id = am.id
                      JOIN maintenance_types mt ON ms.maintenance_type_id = mt.id
                      LEFT JOIN asset_assignments aa ON a.id = aa.asset_id AND aa.is_current = 1
                      LEFT JOIN users u ON aa.user_id = u.id
                      $whereClause
                      ORDER BY ms.next_maintenance_date
                      LIMIT ?, ?";
            
            $params[] = $offset;
            $params[] = $limit;
            
            return $this->db->query($query, $params);
        } catch (Exception $e) {
            error_log("Error in getAllMaintenanceSchedules: " . $e->getMessage());
            return [];
        }
    }

    // دریافت تعداد کل برنامه‌های سرویس ادواری
    public function getMaintenanceSchedulesCount($filters = []) {
        try {
            $whereClause = "";
            $params = [];
            
            if (!empty($filters)) {
                $conditions = [];
                
                if (!empty($filters['asset_id'])) {
                    $conditions[] = "ms.asset_id = ?";
                    $params[] = $filters['asset_id'];
                }
                
                if (!empty($filters['maintenance_type_id'])) {
                    $conditions[] = "ms.maintenance_type_id = ?";
                    $params[] = $filters['maintenance_type_id'];
                }
                
                if (!empty($filters['due_date_start'])) {
                    $conditions[] = "ms.next_maintenance_date >= ?";
                    $params[] = $filters['due_date_start'];
                }
                
                if (!empty($filters['due_date_end'])) {
                    $conditions[] = "ms.next_maintenance_date <= ?";
                    $params[] = $filters['due_date_end'];
                }
                
                if (!empty($conditions)) {
                    $whereClause = "WHERE " . implode(" AND ", $conditions);
                }
            }
            
            $query = "SELECT COUNT(*) as total
                      FROM maintenance_schedules ms
                      JOIN assets a ON ms.asset_id = a.id
                      JOIN maintenance_types mt ON ms.maintenance_type_id = mt.id
                      $whereClause";
            
            $result = $this->db->query($query, $params);
            return $result ? $result[0]['total'] : 0;
        } catch (Exception $e) {
            error_log("Error in getMaintenanceSchedulesCount: " . $e->getMessage());
            return 0;
        }
    }

    // دریافت یک برنامه سرویس ادواری با شناسه
    public function getMaintenanceScheduleById($id) {
        try {
            $query = "SELECT ms.*, a.asset_tag, am.name as model_name, mt.name as maintenance_type,
                             u.fullname as assigned_to
                      FROM maintenance_schedules ms
                      JOIN assets a ON ms.asset_id = a.id
                      JOIN asset_models am ON a.model_id = am.id
                      JOIN maintenance_types mt ON ms.maintenance_type_id = mt.id
                      LEFT JOIN asset_assignments aa ON a.id = aa.asset_id AND aa.is_current = 1
                      LEFT JOIN users u ON aa.user_id = u.id
                      WHERE ms.id = ?";
            
            $result = $this->db->query($query, [$id]);
            return $result ? $result[0] : null;
        } catch (Exception $e) {
            error_log("Error in getMaintenanceScheduleById: " . $e->getMessage());
            return null;
        }
    }

    // دریافت سرویس‌های ادواری پیش رو
    public function getUpcomingMaintenance($limit = 5) {
        try {
            $today = date('Y-m-d');
            $thirtyDaysLater = date('Y-m-d', strtotime('+30 days'));
            
            $query = "SELECT ms.*, a.asset_tag, am.name as model_name, mt.name as maintenance_type,
                             u.fullname as assigned_to
                      FROM maintenance_schedules ms
                      JOIN assets a ON ms.asset_id = a.id
                      JOIN asset_models am ON a.model_id = am.id
                      JOIN maintenance_types mt ON ms.maintenance_type_id = mt.id
                      LEFT JOIN asset_assignments aa ON a.id = aa.asset_id AND aa.is_current = 1
                      LEFT JOIN users u ON aa.user_id = u.id
                      WHERE ms.next_maintenance_date BETWEEN ? AND ?
                      ORDER BY ms.next_maintenance_date
                      LIMIT ?";
            
            return $this->db->query($query, [$today, $thirtyDaysLater, $limit]);
        } catch (Exception $e) {
            error_log("Error in getUpcomingMaintenance: " . $e->getMessage());
            return [];
        }
    }
    
    // دریافت آمار سرویس‌های ادواری برای یک تکنسین
    public function getMaintenanceStatsByTechnician($technicianId) {
        try {
            $today = date('Y-m-d');
            
            $stats = [
                'completed' => 0,
                'scheduled' => 0,
                'overdue' => 0
            ];
            
            // تعداد سرویس‌های انجام شده
            $query1 = "SELECT COUNT(*) as completed
                       FROM maintenance_logs ml
                       WHERE ml.performed_by = ?";
            $result1 = $this->db->query($query1, [$technicianId]);
            $stats['completed'] = $result1 ? $result1[0]['completed'] : 0;
            
            // تعداد سرویس‌های برنامه‌ریزی شده
            $query2 = "SELECT COUNT(*) as scheduled
                       FROM maintenance_schedules ms
                       WHERE ms.next_maintenance_date >= ?
                       AND ms.technician_id = ?";
            $result2 = $this->db->query($query2, [$today, $technicianId]);
            $stats['scheduled'] = $result2 ? $result2[0]['scheduled'] : 0;
            
            // تعداد سرویس‌های تأخیری
            $query3 = "SELECT COUNT(*) as overdue
                       FROM maintenance_schedules ms
                       WHERE ms.next_maintenance_date < ?
                       AND ms.technician_id = ?";
            $result3 = $this->db->query($query3, [$today, $technicianId]);
            $stats['overdue'] = $result3 ? $result3[0]['overdue'] : 0;
            
            return $stats;
        } catch (Exception $e) {
            error_log("Error in getMaintenanceStatsByTechnician: " . $e->getMessage());
            return [
                'completed' => 0,
                'scheduled' => 0,
                'overdue' => 0
            ];
        }
    }
    
    // دریافت سرویس‌های ادواری مورد نیاز برای یک بخش
    public function getMaintenanceNeededByDepartmentId($departmentId, $limit = 5) {
        try {
            $today = date('Y-m-d');
            $thirtyDaysLater = date('Y-m-d', strtotime('+30 days'));
            
            $query = "SELECT ms.*, a.asset_tag, am.name as model_name, mt.name as maintenance_type,
                             u.fullname as assigned_to
                      FROM maintenance_schedules ms
                      JOIN assets a ON ms.asset_id = a.id
                      JOIN asset_models am ON a.model_id = am.id
                      JOIN maintenance_types mt ON ms.maintenance_type_id = mt.id
                      JOIN asset_assignments aa ON a.id = aa.asset_id AND aa.is_current = 1
                      JOIN users u ON aa.user_id = u.id
                      WHERE u.department_id = ?
                      AND ms.next_maintenance_date BETWEEN ? AND ?
                      ORDER BY ms.next_maintenance_date
                      LIMIT ?";
            
            return $this->db->query($query, [$departmentId, $today, $thirtyDaysLater, $limit]);
        } catch (Exception $e) {
            error_log("Error in getMaintenanceNeededByDepartmentId: " . $e->getMessage());
            return [];
        }
    }
    
    // دریافت سرویس‌های ادواری پیش رو برای تجهیز‌های یک کاربر
    public function getUpcomingMaintenanceByUserId($userId, $limit = 5) {
        try {
            $today = date('Y-m-d');
            $thirtyDaysLater = date('Y-m-d', strtotime('+30 days'));
            
            $query = "SELECT ms.*, a.asset_tag, am.name as model_name, mt.name as maintenance_type
                      FROM maintenance_schedules ms
                      JOIN assets a ON ms.asset_id = a.id
                      JOIN asset_models am ON a.model_id = am.id
                      JOIN maintenance_types mt ON ms.maintenance_type_id = mt.id
                      JOIN asset_assignments aa ON a.id = aa.asset_id AND aa.is_current = 1
                      WHERE aa.user_id = ?
                      AND ms.next_maintenance_date BETWEEN ? AND ?
                      ORDER BY ms.next_maintenance_date
                      LIMIT ?";
            
            return $this->db->query($query, [$userId, $today, $thirtyDaysLater, $limit]);
        } catch (Exception $e) {
            error_log("Error in getUpcomingMaintenanceByUserId: " . $e->getMessage());
            return [];
        }
    }

    // ثبت انجام سرویس ادواری
    public function recordMaintenance($scheduleId, $performedDate, $performedBy, $notes) {
        try {
            // ثبت انجام سرویس
            $query1 = "INSERT INTO maintenance_logs (maintenance_schedule_id, performed_date, performed_by, notes)
                       VALUES (?, ?, ?, ?)";
            $this->db->query($query1, [$scheduleId, $performedDate, $performedBy, $notes]);
            
            // دریافت اطلاعات برنامه سرویس و نوع آن
            $query2 = "SELECT ms.*, mt.interval_days
                       FROM maintenance_schedules ms
                       JOIN maintenance_types mt ON ms.maintenance_type_id = mt.id
                       WHERE ms.id = ?";
            $schedule = $this->db->query($query2, [$scheduleId]);
            
            if ($schedule) {
                // محاسبه تاریخ سرویس بعدی
                $intervalDays = $schedule[0]['interval_days'];
                $nextMaintenanceDate = date('Y-m-d', strtotime($performedDate . ' + ' . $intervalDays . ' days'));
                
                // به‌روزرسانی برنامه سرویس
                $query3 = "UPDATE maintenance_schedules
                           SET last_maintenance_date = ?,
                               next_maintenance_date = ?
                           WHERE id = ?";
                return $this->db->query($query3, [$performedDate, $nextMaintenanceDate, $scheduleId]);
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Error in recordMaintenance: " . $e->getMessage());
            return false;
        }
    }

    // دریافت تاریخچه سرویس‌های انجام شده برای یک تجهیز
    public function getMaintenanceHistoryByAssetId($assetId) {
        try {
            $query = "SELECT ml.*, mt.name as maintenance_type, u.fullname as performed_by_name
                      FROM maintenance_logs ml
                      JOIN maintenance_schedules ms ON ml.maintenance_schedule_id = ms.id
                      JOIN maintenance_types mt ON ms.maintenance_type_id = mt.id
                      LEFT JOIN users u ON ml.performed_by = u.id
                      WHERE ms.asset_id = ?
                      ORDER BY ml.performed_date DESC";
            
            return $this->db->query($query, [$assetId]);
        } catch (Exception $e) {
            error_log("Error in getMaintenanceHistoryByAssetId: " . $e->getMessage());
            return [];
        }
    }

    // دریافت تاریخچه سرویس‌های انجام شده توسط یک تکنسین
    public function getMaintenanceHistoryByTechnicianId($technicianId) {
        try {
            $query = "SELECT ml.*, mt.name as maintenance_type, a.asset_tag, am.name as model_name
                      FROM maintenance_logs ml
                      JOIN maintenance_schedules ms ON ml.maintenance_schedule_id = ms.id
                      JOIN maintenance_types mt ON ms.maintenance_type_id = mt.id
                      JOIN assets a ON ms.asset_id = a.id
                      JOIN asset_models am ON a.model_id = am.id
                      WHERE ml.performed_by = ?
                      ORDER BY ml.performed_date DESC";
            
            return $this->db->query($query, [$technicianId]);
        } catch (Exception $e) {
            error_log("Error in getMaintenanceHistoryByTechnicianId: " . $e->getMessage());
            return [];
        }
    }
    
    // ایجاد برنامه سرویس ادواری جدید
    public function createMaintenanceSchedule($assetId, $maintenanceTypeId, $lastMaintenanceDate, $nextMaintenanceDate, $technicianId = null) {
        try {
            $query = "INSERT INTO maintenance_schedules (asset_id, maintenance_type_id, last_maintenance_date, next_maintenance_date, technician_id)
                      VALUES (?, ?, ?, ?, ?)";
            
            return $this->db->query($query, [$assetId, $maintenanceTypeId, $lastMaintenanceDate, $nextMaintenanceDate, $technicianId]);
        } catch (Exception $e) {
            error_log("Error in createMaintenanceSchedule: " . $e->getMessage());
            return false;
        }
    }
    
    // به‌روزرسانی برنامه سرویس ادواری
    public function updateMaintenanceSchedule($id, $maintenanceTypeId, $lastMaintenanceDate, $nextMaintenanceDate, $technicianId = null) {
        try {
            $query = "UPDATE maintenance_schedules
                      SET maintenance_type_id = ?,
                          last_maintenance_date = ?,
                          next_maintenance_date = ?,
                          technician_id = ?
                      WHERE id = ?";
            
            return $this->db->query($query, [$maintenanceTypeId, $lastMaintenanceDate, $nextMaintenanceDate, $technicianId, $id]);
        } catch (Exception $e) {
            error_log("Error in updateMaintenanceSchedule: " . $e->getMessage());
            return false;
        }
    }
    
    // حذف برنامه سرویس ادواری
    public function deleteMaintenanceSchedule($id) {
        try {
            $query = "DELETE FROM maintenance_schedules WHERE id = ?";
            return $this->db->query($query, [$id]);
        } catch (Exception $e) {
            error_log("Error in deleteMaintenanceSchedule: " . $e->getMessage());
            return false;
        }
    }

    /**
     * دریافت آمار کلی سرویس‌های ادواری برای داشبورد
     * @return array آمار کلی سرویس‌های ادواری
     */
    public function getMaintenanceStats() {
        try {
            $today = date('Y-m-d');
            $stats = [
                'total_schedules' => 0,
                'overdue' => 0,
                'due_today' => 0,
                'due_this_week' => 0,
                'due_this_month' => 0,
                'completed_this_month' => 0,
                'completed_total' => 0
            ];
            
            // تعداد کل برنامه‌های سرویس ادواری
            $query = "SELECT COUNT(*) as count FROM maintenance_schedules";
            $result = $this->db->query($query);
            $stats['total_schedules'] = $result[0]['count'] ?? 0;
            
            // تعداد سرویس‌های معوق (تاریخ آنها گذشته است)
            $query = "SELECT COUNT(*) as count FROM maintenance_schedules WHERE next_maintenance_date < ?";
            $result = $this->db->query($query, [$today]);
            $stats['overdue'] = $result[0]['count'] ?? 0;
            
            // تعداد سرویس‌های مورد نیاز امروز
            $query = "SELECT COUNT(*) as count FROM maintenance_schedules WHERE next_maintenance_date = ?";
            $result = $this->db->query($query, [$today]);
            $stats['due_today'] = $result[0]['count'] ?? 0;
            
            // تعداد سرویس‌های مورد نیاز این هفته
            $weekLater = date('Y-m-d', strtotime('+7 days'));
            $query = "SELECT COUNT(*) as count FROM maintenance_schedules 
                    WHERE next_maintenance_date BETWEEN ? AND ?";
            $result = $this->db->query($query, [$today, $weekLater]);
            $stats['due_this_week'] = $result[0]['count'] ?? 0;
            
            // تعداد سرویس‌های مورد نیاز این ماه
            $monthLater = date('Y-m-d', strtotime('+30 days'));
            $query = "SELECT COUNT(*) as count FROM maintenance_schedules 
                    WHERE next_maintenance_date BETWEEN ? AND ?";
            $result = $this->db->query($query, [$today, $monthLater]);
            $stats['due_this_month'] = $result[0]['count'] ?? 0;
            
            // تعداد سرویس‌های انجام شده در ماه جاری
            $firstDayOfMonth = date('Y-m-01');
            $query = "SELECT COUNT(*) as count FROM maintenance_logs 
                    WHERE performed_date BETWEEN ? AND ?";
            $result = $this->db->query($query, [$firstDayOfMonth, $today]);
            $stats['completed_this_month'] = $result[0]['count'] ?? 0;
            
            // تعداد کل سرویس‌های انجام شده
            $query = "SELECT COUNT(*) as count FROM maintenance_logs";
            $result = $this->db->query($query);
            $stats['completed_total'] = $result[0]['count'] ?? 0;
            
            return $stats;
        } catch (Exception $e) {
            error_log("Error in getMaintenanceStats: " . $e->getMessage());
            return [
                'total_schedules' => 0,
                'overdue' => 0,
                'due_today' => 0,
                'due_this_week' => 0,
                'due_this_month' => 0,
                'completed_this_month' => 0,
                'completed_total' => 0
            ];
        }
    }

    /**
     * دریافت سرویس‌های ادواری معوق با اطلاعات کامل
     * @param int $limit تعداد رکوردها
     * @return array سرویس‌های ادواری معوق
     */
    public function getOverdueMaintenance($limit = 10) {
        try {
            $today = date('Y-m-d');
            
            $query = "SELECT ms.*, a.asset_tag, a.name as asset_name, am.name as model_name, 
                            mt.name as maintenance_type, u.fullname as assigned_to,
                            DATEDIFF(?, ms.next_maintenance_date) as days_overdue
                    FROM maintenance_schedules ms
                    JOIN assets a ON ms.asset_id = a.id
                    JOIN asset_models am ON a.model_id = am.id
                    JOIN maintenance_types mt ON ms.maintenance_type_id = mt.id
                    LEFT JOIN asset_assignments aa ON a.id = aa.asset_id AND aa.is_current = 1
                    LEFT JOIN users u ON aa.user_id = u.id
                    WHERE ms.next_maintenance_date < ?
                    ORDER BY ms.next_maintenance_date
                    LIMIT ?";
            
            return $this->db->query($query, [$today, $today, $limit]);
        } catch (Exception $e) {
            error_log("Error in getOverdueMaintenance: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت آمار سرویس‌های ادواری بر اساس نوع سرویس
     * @return array آمار سرویس‌های ادواری بر اساس نوع
     */
    public function getMaintenanceStatsByType() {
        try {
            $today = date('Y-m-d');
            
            $query = "SELECT mt.id, mt.name, mt.interval_days,
                            COUNT(ms.id) as schedule_count,
                            SUM(CASE WHEN ms.next_maintenance_date < ? THEN 1 ELSE 0 END) as overdue_count,
                            SUM(CASE WHEN ms.next_maintenance_date = ? THEN 1 ELSE 0 END) as due_today_count,
                            SUM(CASE WHEN ms.next_maintenance_date BETWEEN ? AND DATE_ADD(?, INTERVAL 7 DAY) THEN 1 ELSE 0 END) as due_this_week_count
                    FROM maintenance_types mt
                    LEFT JOIN maintenance_schedules ms ON mt.id = ms.maintenance_type_id
                    GROUP BY mt.id, mt.name, mt.interval_days
                    ORDER BY schedule_count DESC";
            
            return $this->db->query($query, [$today, $today, $today, $today]);
        } catch (Exception $e) {
            error_log("Error in getMaintenanceStatsByType: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت آمار سرویس‌های ادواری بر اساس بخش
     * @return array آمار سرویس‌های ادواری بر اساس بخش
     */
    public function getMaintenanceStatsByDepartment() {
        try {
            $today = date('Y-m-d');
            
            $query = "SELECT d.id, d.name,
                            COUNT(ms.id) as schedule_count,
                            SUM(CASE WHEN ms.next_maintenance_date < ? THEN 1 ELSE 0 END) as overdue_count
                    FROM departments d
                    JOIN users u ON u.department_id = d.id
                    JOIN asset_assignments aa ON aa.user_id = u.id AND aa.is_current = 1
                    JOIN assets a ON a.id = aa.asset_id
                    JOIN maintenance_schedules ms ON ms.asset_id = a.id
                    GROUP BY d.id, d.name
                    ORDER BY overdue_count DESC, schedule_count DESC";
            
            return $this->db->query($query, [$today]);
        } catch (Exception $e) {
            error_log("Error in getMaintenanceStatsByDepartment: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت آمار سرویس‌های ادواری بر اساس تکنسین
     * @return array آمار سرویس‌های ادواری بر اساس تکنسین
     */
    public function getMaintenanceStatsByAllTechnicians() {
        try {
            $today = date('Y-m-d');
            
            $query = "SELECT u.id, u.fullname,
                            COUNT(ms.id) as assigned_count,
                            SUM(CASE WHEN ms.next_maintenance_date < ? THEN 1 ELSE 0 END) as overdue_count,
                            COUNT(ml.id) as completed_count
                    FROM users u
                    LEFT JOIN maintenance_schedules ms ON ms.technician_id = u.id
                    LEFT JOIN maintenance_logs ml ON ml.performed_by = u.id
                    WHERE u.role = 'technician' OR u.id IN (SELECT DISTINCT technician_id FROM maintenance_schedules WHERE technician_id IS NOT NULL)
                    GROUP BY u.id, u.fullname
                    ORDER BY assigned_count DESC";
            
            return $this->db->query($query, [$today]);
        } catch (Exception $e) {
            error_log("Error in getMaintenanceStatsByAllTechnicians: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت روند انجام سرویس‌های ادواری در طول زمان
     * @param string $period بازه زمانی (month یا year)
     * @param int $limit تعداد رکوردها
     * @return array روند انجام سرویس‌های ادواری
     */
    public function getMaintenanceTrend($period = 'month', $limit = 12) {
        try {
            $format = ($period == 'month') ? '%Y-%m' : '%Y';
            
            $query = "SELECT DATE_FORMAT(performed_date, '$format') as period,
                            COUNT(*) as maintenance_count
                    FROM maintenance_logs
                    GROUP BY period
                    ORDER BY period DESC
                    LIMIT ?";
            
            return $this->db->query($query, [$limit]);
        } catch (Exception $e) {
            error_log("Error in getMaintenanceTrend: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت میانگین زمان بین سرویس‌های ادواری
     * @return array میانگین زمان بین سرویس‌های ادواری
     */
    public function getAverageMaintenanceInterval() {
        try {
            $query = "SELECT mt.name as maintenance_type,
                            AVG(DATEDIFF(ml2.performed_date, ml1.performed_date)) as avg_days_between_maintenance
                    FROM maintenance_logs ml1
                    JOIN maintenance_logs ml2 ON ml1.schedule_id = ml2.schedule_id AND ml1.id < ml2.id
                    JOIN maintenance_schedules ms ON ml1.schedule_id = ms.id
                    JOIN maintenance_types mt ON ms.maintenance_type_id = mt.id
                    WHERE NOT EXISTS (
                        SELECT 1 FROM maintenance_logs ml3
                        WHERE ml3.schedule_id = ml1.schedule_id
                        AND ml3.id > ml1.id AND ml3.id < ml2.id
                    )
                    GROUP BY mt.name
                    ORDER BY avg_days_between_maintenance DESC";
            
            return $this->db->query($query);
        } catch (Exception $e) {
            error_log("Error in getAverageMaintenanceInterval: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت تجهیز‌های با بیشترین تعداد سرویس
     * @param int $limit تعداد رکوردها
     * @return array تجهیز‌های با بیشترین تعداد سرویس
     */
    public function getAssetsByMaintenanceFrequency($limit = 10) {
        try {
            $query = "SELECT a.id, a.asset_tag, a.name, am.name as model_name,
                            COUNT(ml.id) as maintenance_count
                    FROM assets a
                    JOIN asset_models am ON a.model_id = am.id
                    JOIN maintenance_schedules ms ON ms.asset_id = a.id
                    JOIN maintenance_logs ml ON ml.schedule_id = ms.id
                    GROUP BY a.id, a.asset_tag, a.name, am.name
                    ORDER BY maintenance_count DESC
                    LIMIT ?";
            
            return $this->db->query($query, [$limit]);
        } catch (Exception $e) {
            error_log("Error in getAssetsByMaintenanceFrequency: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت آمار هزینه‌های سرویس‌های ادواری
     * @param string $period بازه زمانی (month یا year)
     * @param int $limit تعداد رکوردها
     * @return array آمار هزینه‌های سرویس‌های ادواری
     */
    public function getMaintenanceCostTrend($period = 'month', $limit = 12) {
        try {
            $format = ($period == 'month') ? '%Y-%m' : '%Y';
            
            $query = "SELECT DATE_FORMAT(ml.performed_date, '$format') as period,
                            COUNT(ml.id) as maintenance_count,
                            SUM(ml.cost) as total_cost,
                            AVG(ml.cost) as avg_cost
                    FROM maintenance_logs ml
                    WHERE ml.cost IS NOT NULL
                    GROUP BY period
                    ORDER BY period DESC
                    LIMIT ?";
            
            return $this->db->query($query, [$limit]);
        } catch (Exception $e) {
            error_log("Error in getMaintenanceCostTrend: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت آمار هزینه‌های سرویس‌های ادواری بر اساس نوع سرویس
     * @return array آمار هزینه‌های سرویس‌های ادواری بر اساس نوع سرویس
     */
    public function getMaintenanceCostByType() {
        try {
            $query = "SELECT mt.name as maintenance_type,
                            COUNT(ml.id) as maintenance_count,
                            SUM(ml.cost) as total_cost,
                            AVG(ml.cost) as avg_cost,
                            MIN(ml.cost) as min_cost,
                            MAX(ml.cost) as max_cost
                    FROM maintenance_types mt
                    JOIN maintenance_schedules ms ON mt.id = ms.maintenance_type_id
                    JOIN maintenance_logs ml ON ms.id = ml.schedule_id
                    WHERE ml.cost IS NOT NULL
                    GROUP BY mt.name
                    ORDER BY total_cost DESC";
            
            return $this->db->query($query);
        } catch (Exception $e) {
            error_log("Error in getMaintenanceCostByType: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت آمار سرویس‌های ادواری بر اساس دسته‌بندی تجهیز
     * @return array آمار سرویس‌های ادواری بر اساس دسته‌بندی تجهیز
     */
    public function getMaintenanceStatsByAssetCategory() {
        try {
            $today = date('Y-m-d');
            
            $query = "SELECT ac.name as category_name,
                            COUNT(ms.id) as schedule_count,
                            SUM(CASE WHEN ms.next_maintenance_date < ? THEN 1 ELSE 0 END) as overdue_count,
                            COUNT(ml.id) as completed_count
                    FROM asset_categories ac
                    JOIN asset_models am ON am.category_id = ac.id
                    JOIN assets a ON a.model_id = am.id
                    LEFT JOIN maintenance_schedules ms ON ms.asset_id = a.id
                    LEFT JOIN maintenance_logs ml ON ml.schedule_id = ms.id
                    GROUP BY ac.name
                    ORDER BY schedule_count DESC";
            
            return $this->db->query($query, [$today]);
        } catch (Exception $e) {
            error_log("Error in getMaintenanceStatsByAssetCategory: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت سرویس‌های ادواری برنامه‌ریزی شده برای یک بازه زمانی
     * @param string $startDate تاریخ شروع
     * @param string $endDate تاریخ پایان
     * @return array سرویس‌های ادواری برنامه‌ریزی شده
     */
    public function getScheduledMaintenanceByDateRange($startDate, $endDate) {
        try {
            $query = "SELECT ms.*, a.asset_tag, a.name as asset_name, am.name as model_name,
                            mt.name as maintenance_type, u.fullname as assigned_to,
                            t.fullname as technician_name
                    FROM maintenance_schedules ms
                    JOIN assets a ON ms.asset_id = a.id
                    JOIN asset_models am ON a.model_id = am.id
                    JOIN maintenance_types mt ON ms.maintenance_type_id = mt.id
                    LEFT JOIN asset_assignments aa ON a.id = aa.asset_id AND aa.is_current = 1
                    LEFT JOIN users u ON aa.user_id = u.id
                    LEFT JOIN users t ON ms.technician_id = t.id
                    WHERE ms.next_maintenance_date BETWEEN ? AND ?
                    ORDER BY ms.next_maintenance_date";
            
            return $this->db->query($query, [$startDate, $endDate]);
        } catch (Exception $e) {
            error_log("Error in getScheduledMaintenanceByDateRange: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت سرویس‌های ادواری انجام شده در یک بازه زمانی
     * @param string $startDate تاریخ شروع
     * @param string $endDate تاریخ پایان
     * @return array سرویس‌های ادواری انجام شده
     */
    public function getCompletedMaintenanceByDateRange($startDate, $endDate) {
        try {
            $query = "SELECT ml.*, ms.asset_id, a.asset_tag, a.name as asset_name,
                            am.name as model_name, mt.name as maintenance_type,
                            u.fullname as performed_by_name
                    FROM maintenance_logs ml
                    JOIN maintenance_schedules ms ON ml.schedule_id = ms.id
                    JOIN assets a ON ms.asset_id = a.id
                    JOIN asset_models am ON a.model_id = am.id
                    JOIN maintenance_types mt ON ms.maintenance_type_id = mt.id
                    JOIN users u ON ml.performed_by = u.id
                    WHERE ml.performed_date BETWEEN ? AND ?
                    ORDER BY ml.performed_date DESC";
            
            return $this->db->query($query, [$startDate, $endDate]);
        } catch (Exception $e) {
            error_log("Error in getCompletedMaintenanceByDateRange: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت آمار زمان صرف شده برای سرویس‌های ادواری
     * @return array آمار زمان صرف شده
     */
    public function getMaintenanceTimeStats() {
        try {
            $query = "SELECT mt.name as maintenance_type,
                            COUNT(ml.id) as maintenance_count,
                            AVG(ml.time_spent) as avg_time_spent,
                            MIN(ml.time_spent) as min_time_spent,
                            MAX(ml.time_spent) as max_time_spent
                    FROM maintenance_types mt
                    JOIN maintenance_schedules ms ON mt.id = ms.maintenance_type_id
                    JOIN maintenance_logs ml ON ms.id = ml.schedule_id
                    WHERE ml.time_spent IS NOT NULL
                    GROUP BY mt.name
                    ORDER BY avg_time_spent DESC";
            
            return $this->db->query($query);
        } catch (Exception $e) {
            error_log("Error in getMaintenanceTimeStats: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت آمار رعایت برنامه زمانبندی سرویس‌های ادواری
     * @return array آمار رعایت برنامه زمانبندی
     */
    public function getMaintenanceComplianceStats() {
        try {
            $query = "SELECT 
                    COUNT(*) as total_count,
                    SUM(CASE WHEN DATEDIFF(ml.performed_date, ms.next_maintenance_date) <= 0 THEN 1 ELSE 0 END) as on_time_count,
                    SUM(CASE WHEN DATEDIFF(ml.performed_date, ms.next_maintenance_date) > 0 THEN 1 ELSE 0 END) as late_count,
                    ROUND((SUM(CASE WHEN DATEDIFF(ml.performed_date, ms.next_maintenance_date) <= 0 THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as compliance_rate
                    FROM maintenance_logs ml
                    JOIN maintenance_schedules ms ON ml.schedule_id = ms.id";
            
            return $this->db->query($query)[0] ?? [
                'total_count' => 0,
                'on_time_count' => 0,
                'late_count' => 0,
                'compliance_rate' => 0
            ];
        } catch (Exception $e) {
            error_log("Error in getMaintenanceComplianceStats: " . $e->getMessage());
            return [
                'total_count' => 0,
                'on_time_count' => 0,
                'late_count' => 0,
                'compliance_rate' => 0
            ];
        }
    }

    /**
     * دریافت آمار رعایت برنامه زمانبندی بر اساس بخش
     * @return array آمار رعایت برنامه زمانبندی بر اساس بخش
     */
    public function getMaintenanceComplianceByDepartment() {
        try {
            $query = "SELECT d.name as department_name,
                            COUNT(ml.id) as total_count,
                            SUM(CASE WHEN DATEDIFF(ml.performed_date, ms.next_maintenance_date) <= 0 THEN 1 ELSE 0 END) as on_time_count,
                            ROUND((SUM(CASE WHEN DATEDIFF(ml.performed_date, ms.next_maintenance_date) <= 0 THEN 1 ELSE 0 END) / COUNT(ml.id)) * 100, 2) as compliance_rate
                    FROM departments d
                    JOIN users u ON u.department_id = d.id
                    JOIN asset_assignments aa ON aa.user_id = u.id AND aa.is_current = 1
                    JOIN assets a ON a.id = aa.asset_id
                    JOIN maintenance_schedules ms ON ms.asset_id = a.id
                    JOIN maintenance_logs ml ON ml.schedule_id = ms.id
                    GROUP BY d.name
                    ORDER BY compliance_rate DESC";
            
            return $this->db->query($query);
        } catch (Exception $e) {
            error_log("Error in getMaintenanceComplianceByDepartment: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت آمار سرویس‌های ادواری بر اساس اولویت
     * @return array آمار سرویس‌های ادواری بر اساس اولویت
     */
    public function getMaintenanceStatsByPriority() {
        try {
            $today = date('Y-m-d');
            
            $query = "SELECT ms.priority,
                            COUNT(ms.id) as schedule_count,
                            SUM(CASE WHEN ms.next_maintenance_date < ? THEN 1 ELSE 0 END) as overdue_count
                    FROM maintenance_schedules ms
                    GROUP BY ms.priority
                    ORDER BY FIELD(ms.priority, 'high', 'medium', 'low')";
            
            return $this->db->query($query, [$today]);
        } catch (Exception $e) {
            error_log("Error in getMaintenanceStatsByPriority: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت تقویم سرویس‌های ادواری برای ماه جاری
     * @return array تقویم سرویس‌های ادواری
     */
    public function getCurrentMonthMaintenanceCalendar() {
        try {
            $firstDayOfMonth = date('Y-m-01');
            $lastDayOfMonth = date('Y-m-t');
            
            $query = "SELECT ms.next_maintenance_date as date,
                            COUNT(*) as maintenance_count
                    FROM maintenance_schedules ms
                    WHERE ms.next_maintenance_date BETWEEN ? AND ?
                    GROUP BY ms.next_maintenance_date
                    ORDER BY ms.next_maintenance_date";
            
            return $this->db->query($query, [$firstDayOfMonth, $lastDayOfMonth]);
        } catch (Exception $e) {
            error_log("Error in getCurrentMonthMaintenanceCalendar: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت سرویس‌های ادواری برای یک روز خاص
     * @param string $date تاریخ مورد نظر
     * @return array سرویس‌های ادواری برای روز مورد نظر
     */
    public function getMaintenanceSchedulesByDate($date) {
        try {
            $query = "SELECT ms.*, a.asset_tag, a.name as asset_name, am.name as model_name,
                            mt.name as maintenance_type, u.fullname as assigned_to,
                            t.fullname as technician_name
                    FROM maintenance_schedules ms
                    JOIN assets a ON ms.asset_id = a.id
                    JOIN asset_models am ON a.model_id = am.id
                    JOIN maintenance_types mt ON ms.maintenance_type_id = mt.id
                    LEFT JOIN asset_assignments aa ON a.id = aa.asset_id AND aa.is_current = 1
                    LEFT JOIN users u ON aa.user_id = u.id
                    LEFT JOIN users t ON ms.technician_id = t.id
                    WHERE ms.next_maintenance_date = ?
                    ORDER BY ms.priority DESC";
            
            return $this->db->query($query, [$date]);
        } catch (Exception $e) {
            error_log("Error in getMaintenanceSchedulesByDate: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت آمار سرویس‌های ادواری برای یک کاربر
     * @param int $userId شناسه کاربر
     * @return array آمار سرویس‌های ادواری کاربر
     */
    public function getUserMaintenanceStats($userId) {
        try {
            $today = date('Y-m-d');
            $thirtyDaysLater = date('Y-m-d', strtotime('+30 days'));
            
            $stats = [
                'total_assets' => 0,
                'assets_needing_maintenance' => 0,
                'overdue_maintenance' => 0,
                'upcoming_maintenance' => 0
            ];
            
            // تعداد کل تجهیز‌های کاربر
            $query1 = "SELECT COUNT(*) as count
                    FROM asset_assignments aa
                    WHERE aa.user_id = ? AND aa.is_current = 1";
            $result1 = $this->db->query($query1, [$userId]);
            $stats['total_assets'] = $result1[0]['count'] ?? 0;
            
            // تعداد تجهیز‌های نیازمند سرویس
            $query2 = "SELECT COUNT(DISTINCT a.id) as count
                    FROM assets a
                    JOIN asset_assignments aa ON a.id = aa.asset_id AND aa.is_current = 1
                    JOIN maintenance_schedules ms ON ms.asset_id = a.id
                    WHERE aa.user_id = ?";
            $result2 = $this->db->query($query2, [$userId]);
            $stats['assets_needing_maintenance'] = $result2[0]['count'] ?? 0;
            
            // تعداد سرویس‌های معوق
            $query3 = "SELECT COUNT(*) as count
                    FROM maintenance_schedules ms
                    JOIN assets a ON ms.asset_id = a.id
                    JOIN asset_assignments aa ON a.id = aa.asset_id AND aa.is_current = 1
                    WHERE aa.user_id = ? AND ms.next_maintenance_date < ?";
            $result3 = $this->db->query($query3, [$userId, $today]);
            $stats['overdue_maintenance'] = $result3[0]['count'] ?? 0;
            
            // تعداد سرویس‌های پیش رو در 30 روز آینده
            $query4 = "SELECT COUNT(*) as count
                    FROM maintenance_schedules ms
                    JOIN assets a ON ms.asset_id = a.id
                    JOIN asset_assignments aa ON a.id = aa.asset_id AND aa.is_current = 1
                    WHERE aa.user_id = ? AND ms.next_maintenance_date BETWEEN ? AND ?";
            $result4 = $this->db->query($query4, [$userId, $today, $thirtyDaysLater]);
            $stats['upcoming_maintenance'] = $result4[0]['count'] ?? 0;
            
            return $stats;
        } catch (Exception $e) {
            error_log("Error in getUserMaintenanceStats: " . $e->getMessage());
            return [
                'total_assets' => 0,
                'assets_needing_maintenance' => 0,
                'overdue_maintenance' => 0,
                'upcoming_maintenance' => 0
            ];
        }
    }

    /**
     * دریافت سرویس‌های ادواری برای یک تکنسین در یک بازه زمانی
     * @param int $technicianId شناسه تکنسین
     * @param string $startDate تاریخ شروع
     * @param string $endDate تاریخ پایان
     * @return array سرویس‌های ادواری تکنسین
     */
    public function getTechnicianScheduleByDateRange($technicianId, $startDate, $endDate) {
        try {
            $query = "SELECT ms.*, a.asset_tag, a.name as asset_name, am.name as model_name,
                            mt.name as maintenance_type, u.fullname as assigned_to
                    FROM maintenance_schedules ms
                    JOIN assets a ON ms.asset_id = a.id
                    JOIN asset_models am ON a.model_id = am.id
                    JOIN maintenance_types mt ON ms.maintenance_type_id = mt.id
                    LEFT JOIN asset_assignments aa ON a.id = aa.asset_id AND aa.is_current = 1
                    LEFT JOIN users u ON aa.user_id = u.id
                    WHERE ms.technician_id = ? AND ms.next_maintenance_date BETWEEN ? AND ?
                    ORDER BY ms.next_maintenance_date";
            
            return $this->db->query($query, [$technicianId, $startDate, $endDate]);
        } catch (Exception $e) {
            error_log("Error in getTechnicianScheduleByDateRange: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت آمار بار کاری تکنسین‌ها
     * @return array آمار بار کاری تکنسین‌ها
     */
    public function getTechnicianWorkloadStats() {
        try {
            $today = date('Y-m-d');
            $thirtyDaysLater = date('Y-m-d', strtotime('+30 days'));
            
            $query = "SELECT u.id, u.fullname,
                            COUNT(ms.id) as total_scheduled,
                            SUM(CASE WHEN ms.next_maintenance_date < ? THEN 1 ELSE 0 END) as overdue,
                            SUM(CASE WHEN ms.next_maintenance_date BETWEEN ? AND ? THEN 1 ELSE 0 END) as upcoming,
                            (SELECT COUNT(*) FROM maintenance_logs ml WHERE ml.performed_by = u.id) as completed
                    FROM users u
                    LEFT JOIN maintenance_schedules ms ON ms.technician_id = u.id
                    WHERE u.role = 'technician' OR u.id IN (SELECT DISTINCT technician_id FROM maintenance_schedules WHERE technician_id IS NOT NULL)
                    GROUP BY u.id, u.fullname
                    ORDER BY total_scheduled DESC";
            
            return $this->db->query($query, [$today, $today, $thirtyDaysLater]);
        } catch (Exception $e) {
            error_log("Error in getTechnicianWorkloadStats: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت آمار زمان پاسخگویی به درخواست‌های سرویس
     * @return array آمار زمان پاسخگویی
     */
    public function getMaintenanceResponseTimeStats() {
        try {
            $query = "SELECT mt.name as maintenance_type,
                            AVG(TIMESTAMPDIFF(HOUR, t.created_at, ml.performed_date)) as avg_response_time_hours,
                            MIN(TIMESTAMPDIFF(HOUR, t.created_at, ml.performed_date)) as min_response_time_hours,
                            MAX(TIMESTAMPDIFF(HOUR, t.created_at, ml.performed_date)) as max_response_time_hours
                    FROM tickets t
                    JOIN maintenance_logs ml ON t.id = ml.ticket_id
                    JOIN maintenance_schedules ms ON ml.schedule_id = ms.id
                    JOIN maintenance_types mt ON ms.maintenance_type_id = mt.id
                    WHERE t.category = 'maintenance' AND ml.performed_date IS NOT NULL
                    GROUP BY mt.name
                    ORDER BY avg_response_time_hours";
            
            return $this->db->query($query);
        } catch (Exception $e) {
            error_log("Error in getMaintenanceResponseTimeStats: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت تعداد سرویس‌های ادواری انجام شده بر اساس ماه
     * @param int $year سال مورد نظر
     * @return array تعداد سرویس‌های ادواری انجام شده بر اساس ماه
     */
    public function getMaintenanceCountByMonth($year = null) {
        try {
            if ($year === null) {
                $year = date('Y');
            }
            
            $query = "SELECT MONTH(performed_date) as month,
                            COUNT(*) as maintenance_count
                    FROM maintenance_logs
                    WHERE YEAR(performed_date) = ?
                    GROUP BY month
                    ORDER BY month";
            
            return $this->db->query($query, [$year]);
        } catch (Exception $e) {
            error_log("Error in getMaintenanceCountByMonth: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت سرویس‌های ادواری که نیاز به تخصیص تکنسین دارند
     * @return array سرویس‌های ادواری بدون تکنسین
     */
    public function getUnassignedMaintenanceSchedules() {
        try {
            $today = date('Y-m-d');
            $thirtyDaysLater = date('Y-m-d', strtotime('+30 days'));
            
            $query = "SELECT ms.*, a.asset_tag, a.name as asset_name, am.name as model_name,
                            mt.name as maintenance_type, u.fullname as assigned_to
                    FROM maintenance_schedules ms
                    JOIN assets a ON ms.asset_id = a.id
                    JOIN asset_models am ON a.model_id = am.id
                    JOIN maintenance_types mt ON ms.maintenance_type_id = mt.id
                    LEFT JOIN asset_assignments aa ON a.id = aa.asset_id AND aa.is_current = 1
                    LEFT JOIN users u ON aa.user_id = u.id
                    WHERE ms.technician_id IS NULL
                    AND ms.next_maintenance_date BETWEEN ? AND ?
                    ORDER BY ms.next_maintenance_date";
            
            return $this->db->query($query, [$today, $thirtyDaysLater]);
        } catch (Exception $e) {
            error_log("Error in getUnassignedMaintenanceSchedules: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت آمار سرویس‌های ادواری بر اساس مکان
     * @return array آمار سرویس‌های ادواری بر اساس مکان
     */
    public function getMaintenanceStatsByLocation() {
        try {
            $today = date('Y-m-d');
            
            $query = "SELECT l.name as location_name,
                            COUNT(ms.id) as schedule_count,
                            SUM(CASE WHEN ms.next_maintenance_date < ? THEN 1 ELSE 0 END) as overdue_count
                    FROM locations l
                    JOIN assets a ON a.location_id = l.id
                    JOIN maintenance_schedules ms ON ms.asset_id = a.id
                    GROUP BY l.name
                    ORDER BY schedule_count DESC";
            
            return $this->db->query($query, [$today]);
        } catch (Exception $e) {
            error_log("Error in getMaintenanceStatsByLocation: " . $e->getMessage());
            return [];
        }
    }

    /**
     * ثبت هزینه سرویس ادواری
     * @param int $logId شناسه سابقه سرویس
     * @param float $cost هزینه سرویس
     * @param string $notes توضیحات
     * @return bool نتیجه عملیات
     */
    public function recordMaintenanceCost($logId, $cost, $notes = '') {
        try {
            $query = "UPDATE maintenance_logs
                    SET cost = ?, cost_notes = ?
                    WHERE id = ?";
            
            return $this->db->query($query, [$cost, $notes, $logId]);
        } catch (Exception $e) {
            error_log("Error in recordMaintenanceCost: " . $e->getMessage());
            return false;
        }
    }

    /**
     * ثبت زمان صرف شده برای سرویس ادواری
     * @param int $logId شناسه سابقه سرویس
     * @param int $timeSpent زمان صرف شده (دقیقه)
     * @return bool نتیجه عملیات
     */
    public function recordMaintenanceTime($logId, $timeSpent) {
        try {
            $query = "UPDATE maintenance_logs
                    SET time_spent = ?
                    WHERE id = ?";
            
            return $this->db->query($query, [$timeSpent, $logId]);
        } catch (Exception $e) {
            error_log("Error in recordMaintenanceTime: " . $e->getMessage());
            return false;
        }
    }

    /**
     * ارتباط سرویس ادواری با درخواست کار
     * @param int $scheduleId شناسه برنامه سرویس
     * @param int $ticketId شناسه درخواست کار
     * @return bool نتیجه عملیات
     */
    public function linkMaintenanceToTicket($scheduleId, $ticketId) {
        try {
            $query = "UPDATE maintenance_schedules
                    SET ticket_id = ?
                    WHERE id = ?";
            
            return $this->db->query($query, [$ticketId, $scheduleId]);
        } catch (Exception $e) {
            error_log("Error in linkMaintenanceToTicket: " . $e->getMessage());
            return false;
        }
    }

    /**
     * دریافت سرویس‌های ادواری مرتبط با یک درخواست کار
     * @param int $ticketId شناسه درخواست کار
     * @return array سرویس‌های ادواری مرتبط
     */
    public function getMaintenanceSchedulesByTicket($ticketId) {
        try {
            $query = "SELECT ms.*, a.asset_tag, a.name as asset_name, am.name as model_name,
                            mt.name as maintenance_type, u.fullname as assigned_to,
                            t.fullname as technician_name
                    FROM maintenance_schedules ms
                    JOIN assets a ON ms.asset_id = a.id
                    JOIN asset_models am ON a.model_id = am.id
                    JOIN maintenance_types mt ON ms.maintenance_type_id = mt.id
                    LEFT JOIN asset_assignments aa ON a.id = aa.asset_id AND aa.is_current = 1
                    LEFT JOIN users u ON aa.user_id = u.id
                    LEFT JOIN users t ON ms.technician_id = t.id
                    WHERE ms.ticket_id = ?";
            
            return $this->db->query($query, [$ticketId]);
        } catch (Exception $e) {
            error_log("Error in getMaintenanceSchedulesByTicket: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت آمار سرویس‌های ادواری برای داشبورد مدیر
     * @return array آمار سرویس‌های ادواری برای داشبورد مدیر
     */
    public function getMaintenanceDashboardStats() {
        try {
            $today = date('Y-m-d');
            $thirtyDaysLater = date('Y-m-d', strtotime('+30 days'));
            $firstDayOfMonth = date('Y-m-01');
            
            $stats = [
                'total_schedules' => 0,
                'overdue' => 0,
                'due_today' => 0,
                'due_this_week' => 0,
                'due_this_month' => 0,
                'completed_this_month' => 0,
                'compliance_rate' => 0,
                'avg_response_time' => 0,
                'maintenance_cost_this_month' => 0
            ];
            
            // تعداد کل برنامه‌های سرویس ادواری
            $query1 = "SELECT COUNT(*) as count FROM maintenance_schedules";
            $result1 = $this->db->query($query1);
            $stats['total_schedules'] = $result1[0]['count'] ?? 0;
            
            // تعداد سرویس‌های معوق
            $query2 = "SELECT COUNT(*) as count FROM maintenance_schedules WHERE next_maintenance_date < ?";
            $result2 = $this->db->query($query2, [$today]);
            $stats['overdue'] = $result2[0]['count'] ?? 0;
            
            // تعداد سرویس‌های مورد نیاز امروز
            $query3 = "SELECT COUNT(*) as count FROM maintenance_schedules WHERE next_maintenance_date = ?";
            $result3 = $this->db->query($query3, [$today]);
            $stats['due_today'] = $result3[0]['count'] ?? 0;
            
            // تعداد سرویس‌های مورد نیاز این هفته
            $weekLater = date('Y-m-d', strtotime('+7 days'));
            $query4 = "SELECT COUNT(*) as count FROM maintenance_schedules 
                    WHERE next_maintenance_date BETWEEN ? AND ?";
            $result4 = $this->db->query($query4, [$today, $weekLater]);
            $stats['due_this_week'] = $result4[0]['count'] ?? 0;
            
            // تعداد سرویس‌های مورد نیاز این ماه
            $query5 = "SELECT COUNT(*) as count FROM maintenance_schedules 
                    WHERE next_maintenance_date BETWEEN ? AND ?";
            $result5 = $this->db->query($query5, [$today, $thirtyDaysLater]);
            $stats['due_this_month'] = $result5[0]['count'] ?? 0;
            
            // تعداد سرویس‌های انجام شده در ماه جاری
            $query6 = "SELECT COUNT(*) as count FROM maintenance_logs 
                    WHERE performed_date BETWEEN ? AND ?";
            $result6 = $this->db->query($query6, [$firstDayOfMonth, $today]);
            $stats['completed_this_month'] = $result6[0]['count'] ?? 0;
            
            // نرخ رعایت برنامه زمانبندی
            $query7 = "SELECT 
                    COUNT(*) as total_count,
                    SUM(CASE WHEN DATEDIFF(ml.performed_date, ms.next_maintenance_date) <= 0 THEN 1 ELSE 0 END) as on_time_count,
                    ROUND((SUM(CASE WHEN DATEDIFF(ml.performed_date, ms.next_maintenance_date) <= 0 THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as compliance_rate
                    FROM maintenance_logs ml
                    JOIN maintenance_schedules ms ON ml.schedule_id = ms.id";
            $result7 = $this->db->query($query7);
            $stats['compliance_rate'] = $result7[0]['compliance_rate'] ?? 0;
            
            // میانگین زمان پاسخگویی
            $query8 = "SELECT AVG(TIMESTAMPDIFF(HOUR, t.created_at, ml.performed_date)) as avg_response_time
                    FROM tickets t
                    JOIN maintenance_logs ml ON t.id = ml.ticket_id
                    WHERE t.category = 'maintenance' AND ml.performed_date IS NOT NULL";
            $result8 = $this->db->query($query8);
            $stats['avg_response_time'] = $result8[0]['avg_response_time'] ?? 0;
            
            // هزینه سرویس‌های ادواری در ماه جاری
            $query9 = "SELECT SUM(cost) as total_cost
                    FROM maintenance_logs
                    WHERE performed_date BETWEEN ? AND ?
                    AND cost IS NOT NULL";
            $result9 = $this->db->query($query9, [$firstDayOfMonth, $today]);
            $stats['maintenance_cost_this_month'] = $result9[0]['total_cost'] ?? 0;
            
            return $stats;
        } catch (Exception $e) {
            error_log("Error in getMaintenanceDashboardStats: " . $e->getMessage());
            return [
                'total_schedules' => 0,
                'overdue' => 0,
                'due_today' => 0,
                'due_this_week' => 0,
                'due_this_month' => 0,
                'completed_this_month' => 0,
                'compliance_rate' => 0,
                'avg_response_time' => 0,
                'maintenance_cost_this_month' => 0
            ];
        }
    }
}