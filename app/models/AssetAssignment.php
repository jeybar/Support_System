<?php

class AssetAssignment {
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
     * تخصیص تجهیز به کاربر
     * 
     * @param array $data اطلاعات تخصیص
     * @return int|bool شناسه تخصیص جدید یا false در صورت خطا
     */
    public function assignAsset($data) {
        try {
            // شروع تراکنش
            $this->db->beginTransaction();
            
            // غیرفعال کردن تخصیص‌های قبلی برای این تجهیز (اگر وجود داشته باشد)
            $this->deactivatePreviousAssignments($data['asset_id']);
            
            // ایجاد تخصیص جدید
            $query = "
                INSERT INTO asset_assignments (
                    asset_id, user_id, assigned_by, assigned_at, notes, is_current, 
                    expected_return_date, created_at, updated_at
                ) VALUES (
                    :asset_id, :user_id, :assigned_by, NOW(), :notes, 1, 
                    :expected_return_date, NOW(), NOW()
                )
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':asset_id', $data['asset_id'], PDO::PARAM_INT);
            $stmt->bindParam(':user_id', $data['user_id'], PDO::PARAM_INT);
            $stmt->bindParam(':assigned_by', $data['assigned_by'], PDO::PARAM_INT);
            $stmt->bindParam(':notes', $data['notes'], PDO::PARAM_STR);
            $stmt->bindParam(':expected_return_date', $data['expected_return_date'], PDO::PARAM_STR);
            
            $stmt->execute();
            $assignmentId = $this->db->lastInsertId();
            
            // به‌روزرسانی وضعیت تجهیز
            $updateAssetQuery = "
                UPDATE assets 
                SET status = 'assigned', updated_at = NOW() 
                WHERE id = :asset_id
            ";
            
            $updateStmt = $this->db->prepare($updateAssetQuery);
            $updateStmt->bindParam(':asset_id', $data['asset_id'], PDO::PARAM_INT);
            $updateStmt->execute();
            
            // ثبت در تاریخچه
            $this->logAssignment($data['asset_id'], $data['user_id'], $data['assigned_by'], 'assigned');
            
            // تایید تراکنش
            $this->db->commit();
            
            return $assignmentId;
        } catch (PDOException $e) {
            // بازگشت تراکنش در صورت خطا
            $this->db->rollBack();
            error_log("Error in AssetAssignment::assign: " . $e->getMessage());
            return false;
        }
    }

    /**
     * بازگرداندن تجهیز از کاربر
     * 
     * @param int $assignmentId شناسه تخصیص
     * @param int $returnedBy شناسه کاربر بازگرداننده
     * @param string $notes یادداشت‌های بازگشت
     * @param string $condition وضعیت تجهیز هنگام بازگشت
     * @return bool نتیجه عملیات
     */
    public function returnAsset($assignmentId, $returnedBy, $notes = '', $condition = 'good') {
        try {
            // شروع تراکنش
            $this->db->beginTransaction();
            
            // دریافت اطلاعات تخصیص
            $assignment = $this->getById($assignmentId);
            if (!$assignment || !$assignment['is_current']) {
                return false;
            }
            
            // به‌روزرسانی تخصیص
            $query = "
                UPDATE asset_assignments 
                SET 
                    is_current = 0, 
                    returned_at = NOW(), 
                    returned_by = :returned_by, 
                    return_notes = :notes, 
                    return_condition = :condition, 
                    updated_at = NOW() 
                WHERE id = :id
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $assignmentId, PDO::PARAM_INT);
            $stmt->bindParam(':returned_by', $returnedBy, PDO::PARAM_INT);
            $stmt->bindParam(':notes', $notes, PDO::PARAM_STR);
            $stmt->bindParam(':condition', $condition, PDO::PARAM_STR);
            $stmt->execute();
            
            // به‌روزرسانی وضعیت تجهیز
            $updateAssetQuery = "
                UPDATE assets 
                SET status = 'available', updated_at = NOW() 
                WHERE id = :asset_id
            ";
            
            $updateStmt = $this->db->prepare($updateAssetQuery);
            $updateStmt->bindParam(':asset_id', $assignment['asset_id'], PDO::PARAM_INT);
            $updateStmt->execute();
            
            // ثبت در تاریخچه
            $this->logAssignment($assignment['asset_id'], $assignment['user_id'], $returnedBy, 'returned');
            
            // تایید تراکنش
            $this->db->commit();
            
            return true;
        } catch (PDOException $e) {
            // بازگشت تراکنش در صورت خطا
            $this->db->rollBack();
            error_log("Error in AssetAssignment::returnAsset: " . $e->getMessage());
            return false;
        }
    }

    /**
     * غیرفعال کردن تخصیص‌های قبلی برای یک تجهیز
     * 
     * @param int $assetId شناسه تجهیز
     * @return bool نتیجه عملیات
     */
    private function deactivatePreviousAssignments($assetId) {
        try {
            $query = "
                UPDATE asset_assignments 
                SET is_current = 0, updated_at = NOW() 
                WHERE asset_id = :asset_id AND is_current = 1
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':asset_id', $assetId, PDO::PARAM_INT);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error in AssetAssignment::deactivatePreviousAssignments: " . $e->getMessage());
            return false;
        }
    }

    /**
     * ثبت تخصیص در تاریخچه
     * 
     * @param int $assetId شناسه تجهیز
     * @param int $userId شناسه کاربر
     * @param int $actionBy شناسه کاربر انجام‌دهنده عمل
     * @param string $action نوع عمل (assigned یا returned)
     * @return bool نتیجه عملیات
     */
    private function logAssignment($assetId, $userId, $actionBy, $action) {
        try {
            $query = "
                INSERT INTO asset_history (
                    asset_id, user_id, action_by, action, action_date, created_at
                ) VALUES (
                    :asset_id, :user_id, :action_by, :action, NOW(), NOW()
                )
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':asset_id', $assetId, PDO::PARAM_INT);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':action_by', $actionBy, PDO::PARAM_INT);
            $stmt->bindParam(':action', $action, PDO::PARAM_STR);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error in AssetAssignment::logAssignment: " . $e->getMessage());
            return false;
        }
    }

    /**
     * دریافت تخصیص فعلی یک تجهیز
     * 
     * @param int $assetId شناسه تجهیز
     * @return array|bool اطلاعات تخصیص یا false در صورت عدم وجود
     */
    public function getCurrentAssignment($assetId) {
        try {
            $query = "
                SELECT 
                    aa.*, 
                    u.fullname AS user_name, 
                    u.employee_number,
                    u.email AS user_email,
                    a.name AS asset_name,
                    a.asset_tag,
                    a.serial_number,
                    ab.fullname AS assigned_by_name,
                    rb.fullname AS returned_by_name
                FROM 
                    asset_assignments aa
                JOIN 
                    assets a ON aa.asset_id = a.id
                JOIN 
                    users u ON aa.user_id = u.id
                LEFT JOIN 
                    users ab ON aa.assigned_by = ab.id
                LEFT JOIN 
                    users rb ON aa.returned_by = rb.id
                WHERE 
                    aa.asset_id = :asset_id AND aa.is_current = 1
                LIMIT 1
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':asset_id', $assetId, PDO::PARAM_INT);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result : false;
        } catch (PDOException $e) {
            error_log("Error in AssetAssignment::getCurrentAssignment: " . $e->getMessage());
            return false;
        }
    }

    /**
     * دریافت تخصیص با شناسه
     * 
     * @param int $id شناسه تخصیص
     * @return array|bool اطلاعات تخصیص یا false در صورت عدم وجود
     */
    public function getById($id) {
        try {
            $query = "
                SELECT 
                    aa.*, 
                    u.fullname AS user_name, 
                    u.employee_number,
                    u.email AS user_email,
                    a.name AS asset_name,
                    a.asset_tag,
                    a.serial_number,
                    ab.fullname AS assigned_by_name,
                    rb.fullname AS returned_by_name
                FROM 
                    asset_assignments aa
                JOIN 
                    assets a ON aa.asset_id = a.id
                JOIN 
                    users u ON aa.user_id = u.id
                LEFT JOIN 
                    users ab ON aa.assigned_by = ab.id
                LEFT JOIN 
                    users rb ON aa.returned_by = rb.id
                WHERE 
                    aa.id = :id
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result : false;
        } catch (PDOException $e) {
            error_log("Error in AssetAssignment::getById: " . $e->getMessage());
            return false;
        }
    }

    /**
     * دریافت تاریخچه تخصیص‌های یک تجهیز
     * 
     * @param int $assetId شناسه تجهیز
     * @return array تاریخچه تخصیص‌ها
     */
    public function getAssetAssignmentHistory($assetId) {
        try {
            $query = "
                SELECT 
                    aa.*, 
                    u.fullname AS user_name, 
                    u.employee_number,
                    ab.fullname AS assigned_by_name,
                    rb.fullname AS returned_by_name
                FROM 
                    asset_assignments aa
                JOIN 
                    users u ON aa.user_id = u.id
                LEFT JOIN 
                    users ab ON aa.assigned_by = ab.id
                LEFT JOIN 
                    users rb ON aa.returned_by = rb.id
                WHERE 
                    aa.asset_id = :asset_id
                ORDER BY 
                    aa.assigned_at DESC
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':asset_id', $assetId, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in AssetAssignment::getAssetAssignmentHistory: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت تجهیز‌های تخصیص داده شده به یک کاربر
     * 
     * @param int $userId شناسه کاربر
     * @return array لیست تجهیز‌ها
     */
    public function getUserAssets($userId) {
        try {
            $query = "
                SELECT 
                    a.id,
                    a.name,
                    a.asset_tag,
                    a.serial_number,
                    a.status,
                    aa.assigned_at,
                    aa.expected_return_date,
                    aa.id AS assignment_id,
                    m.name AS model_name,
                    c.name AS category_name
                FROM 
                    asset_assignments aa
                JOIN 
                    assets a ON aa.asset_id = a.id
                LEFT JOIN 
                    asset_models m ON a.model_id = m.id
                LEFT JOIN 
                    asset_categories c ON m.category_id = c.id
                WHERE 
                    aa.user_id = :user_id AND aa.is_current = 1
                ORDER BY 
                    aa.assigned_at DESC
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in AssetAssignment::getUserAssets: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت تخصیص‌های فعلی با فیلترهای مختلف
     * 
     * @param array $filters فیلترها
     * @param string $sortBy ستون مرتب‌سازی
     * @param string $order ترتیب مرتب‌سازی
     * @param int $page شماره صفحه
     * @param int $perPage تعداد آیتم در هر صفحه
     * @return array نتایج جستجو
     */
    public function searchAssignments($filters = [], $sortBy = 'assigned_at', $order = 'desc', $page = 1, $perPage = 10) {
        try {
            // پارامترهای پایه برای کوئری
            $params = [];
            $conditions = [];
            
            // ساخت کوئری پایه
            $baseQuery = "
                SELECT 
                    aa.id,
                    aa.asset_id,
                    aa.user_id,
                    aa.assigned_at,
                    aa.expected_return_date,
                    aa.notes,
                    a.name AS asset_name,
                    a.asset_tag,
                    a.serial_number,
                    u.fullname AS user_name,
                    u.employee_number,
                    ab.fullname AS assigned_by_name,
                    m.name AS model_name,
                    c.name AS category_name
                FROM 
                    asset_assignments aa
                JOIN 
                    assets a ON aa.asset_id = a.id
                JOIN 
                    users u ON aa.user_id = u.id
                LEFT JOIN 
                    users ab ON aa.assigned_by = ab.id
                LEFT JOIN 
                    asset_models m ON a.model_id = m.id
                LEFT JOIN 
                    asset_categories c ON m.category_id = c.id
                WHERE 
                    aa.is_current = 1
            ";
            
            // اضافه کردن شرط‌ها بر اساس فیلترها
            
            // نام تجهیز
            if (!empty($filters['asset_name'])) {
                $conditions[] = "a.name LIKE :asset_name";
                $params[':asset_name'] = '%' . $filters['asset_name'] . '%';
            }
            
            // برچسب تجهیز
            if (!empty($filters['asset_tag'])) {
                $conditions[] = "a.asset_tag LIKE :asset_tag";
                $params[':asset_tag'] = '%' . $filters['asset_tag'] . '%';
            }
            
            // شماره سریال
            if (!empty($filters['serial_number'])) {
                $conditions[] = "a.serial_number LIKE :serial_number";
                $params[':serial_number'] = '%' . $filters['serial_number'] . '%';
            }
            
            // نام کاربر
            if (!empty($filters['user_name'])) {
                $conditions[] = "u.fullname LIKE :user_name";
                $params[':user_name'] = '%' . $filters['user_name'] . '%';
            }
            
            // شماره پرسنلی
            if (!empty($filters['employee_number'])) {
                $conditions[] = "u.employee_number LIKE :employee_number";
                $params[':employee_number'] = '%' . $filters['employee_number'] . '%';
            }
            
            // دسته‌بندی تجهیز
            if (!empty($filters['category_id'])) {
                $conditions[] = "c.id = :category_id";
                $params[':category_id'] = $filters['category_id'];
            }
            
            // مدل تجهیز
            if (!empty($filters['model_id'])) {
                $conditions[] = "m.id = :model_id";
                $params[':model_id'] = $filters['model_id'];
            }
            
            // تاریخ تخصیص
            if (!empty($filters['assigned_date'])) {
                $conditions[] = "DATE(aa.assigned_at) = :assigned_date";
                $params[':assigned_date'] = $filters['assigned_date'];
            }
            
            // تاریخ بازگشت مورد انتظار
            if (!empty($filters['expected_return_date'])) {
                $conditions[] = "DATE(aa.expected_return_date) = :expected_return_date";
                $params[':expected_return_date'] = $filters['expected_return_date'];
            }
            
            // شناسه کاربر
            if (!empty($filters['user_id'])) {
                $conditions[] = "aa.user_id = :user_id";
                $params[':user_id'] = $filters['user_id'];
            }
            
            // شناسه تجهیز
            if (!empty($filters['asset_id'])) {
                $conditions[] = "aa.asset_id = :asset_id";
                $params[':asset_id'] = $filters['asset_id'];
            }
            
            // اضافه کردن شرط‌ها به کوئری
            if (!empty($conditions)) {
                $baseQuery .= " AND " . implode(" AND ", $conditions);
            }
            
            // اضافه کردن مرتب‌سازی
            $allowedColumns = ['id', 'assigned_at', 'expected_return_date', 'asset_name', 'asset_tag', 'serial_number', 'user_name', 'employee_number', 'model_name', 'category_name'];
            $sortBy = in_array($sortBy, $allowedColumns) ? $sortBy : 'assigned_at';
            $order = strtolower($order) === 'asc' ? 'ASC' : 'DESC';
            
            // تنظیم ستون مرتب‌سازی بر اساس نام ستون در جدول
            $sortColumn = $sortBy;
            if ($sortBy === 'asset_name') {
                $sortColumn = 'a.name';
            } elseif ($sortBy === 'asset_tag') {
                $sortColumn = 'a.asset_tag';
            } elseif ($sortBy === 'serial_number') {
                $sortColumn = 'a.serial_number';
            } elseif ($sortBy === 'user_name') {
                $sortColumn = 'u.fullname';
            } elseif ($sortBy === 'employee_number') {
                $sortColumn = 'u.employee_number';
            } elseif ($sortBy === 'model_name') {
                $sortColumn = 'm.name';
            } elseif ($sortBy === 'category_name') {
                $sortColumn = 'c.name';
            } else {
                $sortColumn = 'aa.' . $sortBy;
            }
            
            $baseQuery .= " ORDER BY {$sortColumn} {$order}";
            
            // کوئری شمارش کل نتایج
            $countQuery = str_replace("SELECT 
                    aa.id,
                    aa.asset_id,
                    aa.user_id,
                    aa.assigned_at,
                    aa.expected_return_date,
                    aa.notes,
                    a.name AS asset_name,
                    a.asset_tag,
                    a.serial_number,
                    u.fullname AS user_name,
                    u.employee_number,
                    ab.fullname AS assigned_by_name,
                    m.name AS model_name,
                    c.name AS category_name", "SELECT COUNT(*)", $baseQuery);
            $countQuery = preg_replace("/ORDER BY.*$/", "", $countQuery);
            
            // اجرای کوئری شمارش
            $stmt = $this->db->prepare($countQuery);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();
            $totalCount = $stmt->fetchColumn();
            
            // محاسبه تعداد کل صفحات
            $totalPages = ceil($totalCount / $perPage);
            
            // تنظیم صفحه فعلی
            $page = max(1, min($page, $totalPages > 0 ? $totalPages : 1));
            $offset = ($page - 1) * $perPage;
            
            // اضافه کردن محدودیت LIMIT به کوئری اصلی
            $baseQuery .= " LIMIT :offset, :per_page";
            $params[':offset'] = $offset;
            $params[':per_page'] = $perPage;
            
            // اجرای کوئری اصلی
            $stmt = $this->db->prepare($baseQuery);
            foreach ($params as $key => $value) {
                if ($key === ':offset' || $key === ':per_page') {
                    $stmt->bindValue($key, $value, PDO::PARAM_INT);
                } else {
                    $stmt->bindValue($key, $value);
                }
            }
            $stmt->execute();
            $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'assignments' => $assignments,
                'totalCount' => $totalCount,
                'totalPages' => $totalPages,
                'currentPage' => $page,
                'perPage' => $perPage
            ];
        } catch (PDOException $e) {
            error_log("Error in AssetAssignment::searchAssignments: " . $e->getMessage());
            return [
                'assignments' => [],
                'totalCount' => 0,
                'totalPages' => 0,
                'currentPage' => 1,
                'perPage' => $perPage
            ];
        }
    }

    /**
     * دریافت آمار تخصیص‌ها
     * 
     * @return array آمار تخصیص‌ها
     */
    public function getAssignmentStats() {
        try {
            $stats = [];
            
            // تعداد کل تخصیص‌های فعلی
            $totalQuery = "SELECT COUNT(*) FROM asset_assignments WHERE is_current = 1";
            $stmt = $this->db->query($totalQuery);
            $stats['total_current_assignments'] = $stmt->fetchColumn();
            
            // تعداد تجهیز‌های تخصیص داده شده بر اساس دسته‌بندی
            $categoryQuery = "
                SELECT 
                    c.name AS category_name, 
                    COUNT(*) AS count
                FROM 
                    asset_assignments aa
                JOIN 
                    assets a ON aa.asset_id = a.id
                JOIN 
                    asset_models m ON a.model_id = m.id
                JOIN 
                    asset_categories c ON m.category_id = c.id
                WHERE 
                    aa.is_current = 1
                GROUP BY 
                    c.name
                ORDER BY 
                    count DESC
            ";
            $stmt = $this->db->query($categoryQuery);
            $stats['by_category'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // کاربران با بیشترین تعداد تجهیز‌های تخصیص داده شده
            $userQuery = "
                SELECT 
                    u.id AS user_id,
                    u.fullname AS user_name,
                    u.employee_number,
                    COUNT(*) AS asset_count
                FROM 
                    asset_assignments aa
                JOIN 
                    users u ON aa.user_id = u.id
                WHERE 
                    aa.is_current = 1
                GROUP BY 
                    u.id, u.fullname, u.employee_number
                ORDER BY 
                    asset_count DESC
                LIMIT 10
            ";
            $stmt = $this->db->query($userQuery);
            $stats['top_users'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // تعداد تخصیص‌های نزدیک به تاریخ بازگشت (7 روز آینده)
            $upcomingReturnQuery = "
                SELECT COUNT(*) 
                FROM asset_assignments 
                WHERE is_current = 1 
                AND expected_return_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
            ";
            $stmt = $this->db->query($upcomingReturnQuery);
            $stats['upcoming_returns'] = $stmt->fetchColumn();
            
            // تعداد تخصیص‌های معوق (تاریخ بازگشت گذشته)
            $overdueReturnQuery = "
                SELECT COUNT(*) 
                FROM asset_assignments 
                WHERE is_current = 1 
                AND expected_return_date < NOW()
            ";
            $stmt = $this->db->query($overdueReturnQuery);
            $stats['overdue_returns'] = $stmt->fetchColumn();
            
            return $stats;
        } catch (PDOException $e) {
            error_log("Error in AssetAssignment::getAssignmentStats: " . $e->getMessage());
            return [
                'total_current_assignments' => 0,
                'by_category' => [],
                'top_users' => [],
                'upcoming_returns' => 0,
                'overdue_returns' => 0
            ];
        }
    }

    /**
     * دریافت تخصیص‌های نزدیک به تاریخ بازگشت
     * 
     * @param int $days تعداد روزهای آینده
     * @return array لیست تخصیص‌ها
     */
    public function getUpcomingReturns($days = 7) {
        try {
            $query = "
                SELECT 
                    aa.id,
                    aa.asset_id,
                    aa.user_id,
                    aa.assigned_at,
                    aa.expected_return_date,
                    a.name AS asset_name,
                    a.asset_tag,
                    u.fullname AS user_name,
                    u.employee_number,
                    m.name AS model_name,
                    c.name AS category_name
                FROM 
                    asset_assignments aa
                JOIN 
                    assets a ON aa.asset_id = a.id
                JOIN 
                    users u ON aa.user_id = u.id
                LEFT JOIN 
                    asset_models m ON a.model_id = m.id
                LEFT JOIN 
                    asset_categories c ON m.category_id = c.id
                WHERE 
                    aa.is_current = 1 
                    AND aa.expected_return_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL :days DAY)
                ORDER BY 
                    aa.expected_return_date ASC
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':days', $days, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in AssetAssignment::getUpcomingReturns: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت تخصیص‌های معوق (تاریخ بازگشت گذشته)
     * 
     * @return array لیست تخصیص‌ها
     */
    public function getOverdueReturns() {
        try {
            $query = "
                SELECT 
                    aa.id,
                    aa.asset_id,
                    aa.user_id,
                    aa.assigned_at,
                    aa.expected_return_date,
                    a.name AS asset_name,
                    a.asset_tag,
                    u.fullname AS user_name,
                    u.employee_number,
                    m.name AS model_name,
                    c.name AS category_name,
                    DATEDIFF(NOW(), aa.expected_return_date) AS days_overdue
                FROM 
                    asset_assignments aa
                JOIN 
                    assets a ON aa.asset_id = a.id
                JOIN 
                    users u ON aa.user_id = u.id
                LEFT JOIN 
                    asset_models m ON a.model_id = m.id
                LEFT JOIN 
                    asset_categories c ON m.category_id = c.id
                WHERE 
                    aa.is_current = 1 
                    AND aa.expected_return_date < NOW()
                ORDER BY 
                    aa.expected_return_date ASC
            ";
            
            $stmt = $this->db->query($query);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in AssetAssignment::getOverdueReturns: " . $e->getMessage());
            return [];
        }
    }

    /**
     * به‌روزرسانی تاریخ بازگشت مورد انتظار
     * 
     * @param int $assignmentId شناسه تخصیص
     * @param string $newDate تاریخ جدید
     * @return bool نتیجه عملیات
     */
    public function updateExpectedReturnDate($assignmentId, $newDate) {
        try {
            $query = "
                UPDATE asset_assignments 
                SET expected_return_date = :new_date, updated_at = NOW() 
                WHERE id = :id AND is_current = 1
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $assignmentId, PDO::PARAM_INT);
            $stmt->bindParam(':new_date', $newDate, PDO::PARAM_STR);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error in AssetAssignment::updateExpectedReturnDate: " . $e->getMessage());
            return false;
        }
    }

    /**
     * دریافت تاریخچه تخصیص‌های یک کاربر
     * 
     * @param int $userId شناسه کاربر
     * @return array تاریخچه تخصیص‌ها
     */
    public function getUserAssignmentHistory($userId) {
        try {
            $query = "
                SELECT 
                    aa.id,
                    aa.asset_id,
                    aa.assigned_at,
                    aa.returned_at,
                    aa.is_current,
                    a.name AS asset_name,
                    a.asset_tag,
                    a.serial_number,
                    m.name AS model_name,
                    c.name AS category_name,
                    ab.fullname AS assigned_by_name,
                    rb.fullname AS returned_by_name
                FROM 
                    asset_assignments aa
                JOIN 
                    assets a ON aa.asset_id = a.id
                LEFT JOIN 
                    asset_models m ON a.model_id = m.id
                LEFT JOIN 
                    asset_categories c ON m.category_id = c.id
                LEFT JOIN 
                    users ab ON aa.assigned_by = ab.id
                LEFT JOIN 
                    users rb ON aa.returned_by = rb.id
                WHERE 
                    aa.user_id = :user_id
                ORDER BY 
                    aa.assigned_at DESC
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in AssetAssignment::getUserAssignmentHistory: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت دارایی‌های تخصیص داده شده به یک کاربر با شناسه کاربر
     * 
     * @param int $userId شناسه کاربر
     * @return array لیست دارایی‌های تخصیص داده شده
     */
    public function getAssignedAssetsByUserId($userId) {
        try {
            $query = "SELECT a.id, a.asset_tag, a.title, a.model, a.category_id, c.name as category_name, 
                        a.status_id, s.name as status_name, a.serial, a.location_id, l.name as location_name,
                        aa.id as assignment_id, aa.assigned_to, aa.assigned_date, aa.expected_return_date,
                        u.fullname as user_fullname
                    FROM assets a
                    LEFT JOIN asset_assignments aa ON a.id = aa.asset_id AND aa.is_active = 1
                    LEFT JOIN asset_categories c ON a.category_id = c.id
                    LEFT JOIN asset_statuses s ON a.status_id = s.id
                    LEFT JOIN locations l ON a.location_id = l.id
                    LEFT JOIN users u ON aa.assigned_to = u.id
                    WHERE aa.assigned_to = ? AND aa.is_active = 1";
            
            // اجرای کوئری با استفاده از PDO به جای executeQuery
            $stmt = $this->db->prepare($query);
            $stmt->execute([$userId]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return $results;
        } catch (Exception $e) {
            error_log("Error in getAssignedAssetsByUserId: " . $e->getMessage());
            return [];
        }
    }

}