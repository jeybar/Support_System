<?php

require_once __DIR__ . '/../core/Database.php'; // بارگذاری کلاس Database

class Ticket {
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
     * ایجاد درخواست کار جدید
     * 
     * @param array $data اطلاعات درخواست کار
     * @return int|bool شناسه درخواست کار جدید یا false در صورت خطا
     */
    public function createTicket($data) {
        try {
            // شروع تراکنش
            $this->db->beginTransaction();
            
            // آماده‌سازی کوئری برای درج درخواست کار
            $query = "
                INSERT INTO tickets (
                    title, description, priority, status, user_id, 
                    employee_number, employee_name, requester_employee_number,
                    plant_name, unit_name, problem_type, file_path,
                    created_at, updated_at
                ) VALUES (
                    :title, :description, :priority, :status, :user_id,
                    :employee_number, :employee_name, :requester_employee_number,
                    :plant_name, :unit_name, :problem_type, :file_path,
                    NOW(), NOW()
                )
            ";
            
            $stmt = $this->db->prepare($query);
            
            // بایند کردن پارامترها
            $stmt->bindParam(':title', $data['title'], PDO::PARAM_STR);
            $stmt->bindParam(':description', $data['description'], PDO::PARAM_STR);
            $stmt->bindParam(':priority', $data['priority'], PDO::PARAM_STR);
            $stmt->bindParam(':status', $data['status'], PDO::PARAM_STR);
            $stmt->bindParam(':user_id', $data['user_id'], PDO::PARAM_INT);
            $stmt->bindParam(':employee_number', $data['employee_number'], PDO::PARAM_STR);
            $stmt->bindParam(':employee_name', $data['employee_name'], PDO::PARAM_STR);
            $stmt->bindParam(':requester_employee_number', $data['requester_employee_number'], PDO::PARAM_STR);
            $stmt->bindParam(':plant_name', $data['plant_name'], PDO::PARAM_STR);
            $stmt->bindParam(':unit_name', $data['unit_name'], PDO::PARAM_STR);
            $stmt->bindParam(':problem_type', $data['problem_type'], PDO::PARAM_STR);
            $stmt->bindParam(':file_path', $data['file_path'], PDO::PARAM_STR);
            
            // اجرای کوئری
            $stmt->execute();
            
            // دریافت شناسه درخواست کار جدید
            $ticketId = $this->db->lastInsertId();
            
            // اگر تجهیز‌های سخت‌افزاری انتخاب شده باشند
            if (!empty($data['assets']) && is_array($data['assets'])) {
                $assetQuery = "
                    INSERT INTO ticket_assets (ticket_id, asset_id, created_at)
                    VALUES (:ticket_id, :asset_id, NOW())
                ";
                $assetStmt = $this->db->prepare($assetQuery);
                
                foreach ($data['assets'] as $assetId) {
                    $assetStmt->bindParam(':ticket_id', $ticketId, PDO::PARAM_INT);
                    $assetStmt->bindParam(':asset_id', $assetId, PDO::PARAM_INT);
                    $assetStmt->execute();
                }
            }
            
            // ثبت اولین تغییر وضعیت
            $statusChangeQuery = "
                INSERT INTO ticket_status_changes (ticket_id, old_status, new_status, changed_at, changed_by)
                VALUES (:ticket_id, NULL, :new_status, NOW(), :changed_by)
            ";
            $statusStmt = $this->db->prepare($statusChangeQuery);
            $statusStmt->bindParam(':ticket_id', $ticketId, PDO::PARAM_INT);
            $statusStmt->bindParam(':new_status', $data['status'], PDO::PARAM_STR);
            $statusStmt->bindParam(':changed_by', $data['user_id'], PDO::PARAM_INT);
            $statusStmt->execute();
            
            // تایید تراکنش
            $this->db->commit();
            
            return $ticketId;
        } catch (PDOException $e) {
            // بازگشت تراکنش در صورت خطا
            $this->db->rollBack();
            error_log("Error in createTicket: " . $e->getMessage());
            return false;
        }
    }

    /**
     * به‌روزرسانی درخواست کار
     * 
     * @param int $ticketId شناسه درخواست کار
     * @param array $data اطلاعات جدید درخواست کار
     * @return bool نتیجه عملیات
     */
    public function updateTicket($ticketId, $data) {
        try {
            // شروع تراکنش
            $this->db->beginTransaction();
            
            // دریافت اطلاعات فعلی درخواست کار
            $currentTicket = $this->getTicketById($ticketId);
            if (!$currentTicket) {
                return false;
            }
            
            // آماده‌سازی کوئری برای به‌روزرسانی درخواست کار
            $query = "
                UPDATE tickets 
                SET 
                    title = :title,
                    description = :description,
                    priority = :priority,
                    problem_type = :problem_type,
                    updated_at = NOW()
            ";
            
            // اضافه کردن فیلدهای اختیاری به کوئری
            $params = [
                ':title' => $data['title'],
                ':description' => $data['description'],
                ':priority' => $data['priority'],
                ':problem_type' => $data['problem_type'],
                ':ticket_id' => $ticketId
            ];
            
            if (isset($data['file_path']) && !empty($data['file_path'])) {
                $query .= ", file_path = :file_path";
                $params[':file_path'] = $data['file_path'];
            }
            
            if (isset($data['due_date'])) {
                $query .= ", due_date = :due_date";
                $params[':due_date'] = $data['due_date'];
            }
            
            if (isset($data['status']) && $data['status'] !== $currentTicket['status']) {
                $query .= ", status = :status";
                $params[':status'] = $data['status'];
                
                // ثبت تغییر وضعیت
                $this->logStatusChange($ticketId, $currentTicket['status'], $data['status'], $data['user_id']);
                
                // اگر وضعیت به "closed" تغییر کرده است
                if ($data['status'] === 'closed' && $currentTicket['status'] !== 'closed') {
                    $query .= ", resolved_at = NOW()";
                    
                    // محاسبه زمان صرف‌شده اگر started_at وجود داشته باشد
                    if (!empty($currentTicket['started_at'])) {
                        $startTime = new DateTime($currentTicket['started_at']);
                        $currentTime = new DateTime();
                        $interval = $startTime->diff($currentTime);
                        $elapsedSeconds = $interval->days * 24 * 60 * 60
                                        + $interval->h * 60 * 60
                                        + $interval->i * 60
                                        + $interval->s;
                        
                        // افزودن به زمان صرف‌شده قبلی
                        $elapsedTime = $currentTicket['elapsed_time'] + $elapsedSeconds;
                        $query .= ", elapsed_time = :elapsed_time, started_at = NULL";
                        $params[':elapsed_time'] = $elapsedTime;
                    }
                }
                // اگر وضعیت از "closed" به وضعیت دیگری تغییر کرده است
                elseif ($currentTicket['status'] === 'closed' && $data['status'] !== 'closed') {
                    $query .= ", started_at = NOW(), resolved_at = NULL";
                }
                // اگر وضعیت به "in_progress" تغییر کرده و started_at خالی است
                elseif ($data['status'] === 'in_progress' && empty($currentTicket['started_at'])) {
                    $query .= ", started_at = NOW()";
                }
            }
            
            if (isset($data['assigned_to']) && $data['assigned_to'] !== $currentTicket['assigned_to']) {
                $query .= ", assigned_to = :assigned_to";
                $params[':assigned_to'] = $data['assigned_to'];
                
                // ثبت تغییر پشتیبان
                $this->logAssignmentChange($ticketId, $currentTicket['assigned_to'], $data['assigned_to'], $data['user_id']);
            }
            
            $query .= " WHERE id = :ticket_id";
            
            $stmt = $this->db->prepare($query);
            
            // بایند کردن پارامترها
            foreach ($params as $key => $value) {
                if (is_int($value)) {
                    $stmt->bindValue($key, $value, PDO::PARAM_INT);
                } else {
                    $stmt->bindValue($key, $value, PDO::PARAM_STR);
                }
            }
            
            // اجرای کوئری
            $stmt->execute();
            
            // به‌روزرسانی تجهیز‌های مرتبط
            if (isset($data['assets']) && is_array($data['assets'])) {
                // حذف ارتباط‌های قبلی
                $deleteAssetsQuery = "DELETE FROM ticket_assets WHERE ticket_id = :ticket_id";
                $deleteStmt = $this->db->prepare($deleteAssetsQuery);
                $deleteStmt->bindParam(':ticket_id', $ticketId, PDO::PARAM_INT);
                $deleteStmt->execute();
                
                // افزودن ارتباط‌های جدید
                if (!empty($data['assets'])) {
                    $assetQuery = "
                        INSERT INTO ticket_assets (ticket_id, asset_id, created_at)
                        VALUES (:ticket_id, :asset_id, NOW())
                    ";
                    $assetStmt = $this->db->prepare($assetQuery);
                    
                    foreach ($data['assets'] as $assetId) {
                        $assetStmt->bindParam(':ticket_id', $ticketId, PDO::PARAM_INT);
                        $assetStmt->bindParam(':asset_id', $assetId, PDO::PARAM_INT);
                        $assetStmt->execute();
                    }
                }
            }
            
            // تایید تراکنش
            $this->db->commit();
            
            return true;
        } catch (PDOException $e) {
            // بازگشت تراکنش در صورت خطا
            $this->db->rollBack();
            error_log("Error in updateTicket: " . $e->getMessage());
            return false;
        }
    }

    /**
     * حذف درخواست کار
     * 
     * @param int $ticketId شناسه درخواست کار
     * @return bool نتیجه عملیات
     */
    public function deleteTicket($ticketId) {
        try {
            // شروع تراکنش
            $this->db->beginTransaction();
            
            // حذف ارتباط‌های تجهیز‌ها
            $deleteAssetsQuery = "DELETE FROM ticket_assets WHERE ticket_id = :ticket_id";
            $deleteAssetsStmt = $this->db->prepare($deleteAssetsQuery);
            $deleteAssetsStmt->bindParam(':ticket_id', $ticketId, PDO::PARAM_INT);
            $deleteAssetsStmt->execute();
            
            // حذف تغییرات وضعیت
            $deleteStatusQuery = "DELETE FROM ticket_status_changes WHERE ticket_id = :ticket_id";
            $deleteStatusStmt = $this->db->prepare($deleteStatusQuery);
            $deleteStatusStmt->bindParam(':ticket_id', $ticketId, PDO::PARAM_INT);
            $deleteStatusStmt->execute();
            
            // حذف پاسخ‌ها
            $deleteRepliesQuery = "DELETE FROM ticket_replies WHERE ticket_id = :ticket_id";
            $deleteRepliesStmt = $this->db->prepare($deleteRepliesQuery);
            $deleteRepliesStmt->bindParam(':ticket_id', $ticketId, PDO::PARAM_INT);
            $deleteRepliesStmt->execute();
            
            // حذف امتیازدهی‌ها
            $deleteRatingsQuery = "DELETE FROM ratings WHERE ticket_id = :ticket_id";
            $deleteRatingsStmt = $this->db->prepare($deleteRatingsQuery);
            $deleteRatingsStmt->bindParam(':ticket_id', $ticketId, PDO::PARAM_INT);
            $deleteRatingsStmt->execute();
            
            // حذف درخواست کار
            $deleteTicketQuery = "DELETE FROM tickets WHERE id = :ticket_id";
            $deleteTicketStmt = $this->db->prepare($deleteTicketQuery);
            $deleteTicketStmt->bindParam(':ticket_id', $ticketId, PDO::PARAM_INT);
            $deleteTicketStmt->execute();
            
            // تایید تراکنش
            $this->db->commit();
            
            return true;
        } catch (PDOException $e) {
            // بازگشت تراکنش در صورت خطا
            $this->db->rollBack();
            error_log("Error in deleteTicket: " . $e->getMessage());
            return false;
        }
    }

    /**
     * جستجوی درخواست کار‌ها بر اساس معیارهای مختلف
     * 
     * @param array $filters فیلترهای جستجو
     * @param string $sortBy ستون مرتب‌سازی
     * @param string $order ترتیب مرتب‌سازی
     * @param int $page شماره صفحه
     * @param int $perPage تعداد آیتم در هر صفحه
     * @return array نتایج جستجو
     */
    public function searchTickets($filters = [], $sortBy = 'created_at', $order = 'desc', $page = 1, $perPage = 10) {
        try {
            // پارامترهای پایه برای کوئری
            $params = [];
            $conditions = [];
            
            // ساخت کوئری پایه
            $baseQuery = "
                SELECT 
                    t.id, 
                    t.title, 
                    t.status, 
                    t.priority, 
                    t.created_at, 
                    t.due_date, 
                    t.employee_name AS requester_name, 
                    t.requester_employee_number AS requester_id, 
                    t.plant_name AS requester_plant, 
                    t.unit_name AS requester_unit,
                    t.problem_type,
                    t.resolved_at,
                    u.fullname AS support_name
                FROM tickets t
                LEFT JOIN users u ON t.assigned_to = u.id
                WHERE 1=1
            ";
            
            // اضافه کردن شرط‌ها بر اساس فیلترها
            
            // عنوان درخواست کار
            if (!empty($filters['query'])) {
                $conditions[] = "(t.title LIKE :query OR t.description LIKE :query)";
                $params[':query'] = '%' . $filters['query'] . '%';
            }
            
            // وضعیت
            if (!empty($filters['status'])) {
                $conditions[] = "t.status = :status";
                $params[':status'] = $filters['status'];
            }
            
            // اولویت
            if (!empty($filters['priority'])) {
                $conditions[] = "t.priority = :priority";
                $params[':priority'] = $filters['priority'];
            }
            
            // نوع مشکل
            if (!empty($filters['problem_type'])) {
                $conditions[] = "t.problem_type = :problem_type";
                $params[':problem_type'] = $filters['problem_type'];
            }
            
            // درخواست‌دهنده (نام)
            if (!empty($filters['requester'])) {
                $conditions[] = "t.employee_name LIKE :requester";
                $params[':requester'] = '%' . $filters['requester'] . '%';
            }
            
            // شماره پرسنلی
            if (!empty($filters['employee_id'])) {
                $conditions[] = "t.requester_employee_number LIKE :employee_id";
                $params[':employee_id'] = '%' . $filters['employee_id'] . '%';
            }
            
            // پلنت
            if (!empty($filters['plant'])) {
                $conditions[] = "t.plant_name LIKE :plant";
                $params[':plant'] = '%' . $filters['plant'] . '%';
            }
            
            // واحد
            if (!empty($filters['unit'])) {
                $conditions[] = "t.unit_name LIKE :unit";
                $params[':unit'] = '%' . $filters['unit'] . '%';
            }
            
            // تاریخ ایجاد
            if (!empty($filters['created_date'])) {
                $conditions[] = "DATE(t.created_at) = :created_date";
                $params[':created_date'] = $filters['created_date'];
            }
            
            // تاریخ سررسید
            if (!empty($filters['due_date'])) {
                $conditions[] = "DATE(t.due_date) = :due_date";
                $params[':due_date'] = $filters['due_date'];
            }
            
            // پشتیبان اختصاص داده شده
            if (!empty($filters['assigned_to'])) {
                $conditions[] = "t.assigned_to = :assigned_to";
                $params[':assigned_to'] = $filters['assigned_to'];
            }
            
            // کاربر ایجادکننده
            if (!empty($filters['user_id'])) {
                $conditions[] = "t.user_id = :user_id";
                $params[':user_id'] = $filters['user_id'];
            }
            
            // فیلتر درخواست‌های معوق
            if (isset($filters['overdue']) && $filters['overdue']) {
                $conditions[] = "t.due_date < NOW() AND t.status IN ('open', 'in_progress')";
            }
            
            // فیلتر بر اساس تجهیز
            if (!empty($filters['asset_id'])) {
                $baseQuery = "
                    SELECT 
                        t.id, 
                        t.title, 
                        t.status, 
                        t.priority, 
                        t.created_at, 
                        t.due_date, 
                        t.employee_name AS requester_name, 
                        t.requester_employee_number AS requester_id, 
                        t.plant_name AS requester_plant, 
                        t.unit_name AS requester_unit,
                        t.problem_type,
                        t.resolved_at,
                        u.fullname AS support_name
                    FROM tickets t
                    LEFT JOIN users u ON t.assigned_to = u.id
                    JOIN ticket_assets ta ON t.id = ta.ticket_id
                    WHERE 1=1
                ";
                $conditions[] = "ta.asset_id = :asset_id";
                $params[':asset_id'] = $filters['asset_id'];
            }
            
            // اضافه کردن شرط‌ها به کوئری
            if (!empty($conditions)) {
                $baseQuery .= " AND " . implode(" AND ", $conditions);
            }
            
            // اضافه کردن مرتب‌سازی
            $allowedColumns = ['id', 'title', 'status', 'priority', 'created_at', 'due_date', 'requester_name', 'requester_id', 'requester_plant', 'requester_unit', 'resolved_at', 'support_name'];
            $sortBy = in_array($sortBy, $allowedColumns) ? $sortBy : 'created_at';
            $order = strtolower($order) === 'asc' ? 'ASC' : 'DESC';
            
            // تنظیم ستون مرتب‌سازی بر اساس نام ستون در جدول
            $sortColumn = $sortBy;
            if ($sortBy === 'requester_name') {
                $sortColumn = 't.employee_name';
            } elseif ($sortBy === 'requester_id') {
                $sortColumn = 't.requester_employee_number';
            } elseif ($sortBy === 'requester_plant') {
                $sortColumn = 't.plant_name';
            } elseif ($sortBy === 'requester_unit') {
                $sortColumn = 't.unit_name';
            } elseif ($sortBy === 'support_name') {
                $sortColumn = 'u.fullname';
            } else {
                $sortColumn = 't.' . $sortBy;
            }
            
            $baseQuery .= " ORDER BY {$sortColumn} {$order}";
            
            // کوئری شمارش کل نتایج
            $countQuery = str_replace("SELECT 
                    t.id, 
                    t.title, 
                    t.status, 
                    t.priority, 
                    t.created_at, 
                    t.due_date, 
                    t.employee_name AS requester_name, 
                    t.requester_employee_number AS requester_id, 
                    t.plant_name AS requester_plant, 
                    t.unit_name AS requester_unit,
                    t.problem_type,
                    t.resolved_at,
                    u.fullname AS support_name", "SELECT COUNT(DISTINCT t.id)", $baseQuery);
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
            $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // محاسبه زمان پاسخگویی برای هر درخواست
            foreach ($tickets as &$ticket) {
                if (!empty($ticket['resolved_at']) && !empty($ticket['created_at'])) {
                    $created = new DateTime($ticket['created_at']);
                    $resolved = new DateTime($ticket['resolved_at']);
                    $interval = $created->diff($resolved);
                    
                    $ticket['response_time'] = [
                        'days' => $interval->days,
                        'hours' => $interval->h,
                        'minutes' => $interval->i
                    ];
                } else {
                    $ticket['response_time'] = null;
                }
            }
            
            return [
                'tickets' => $tickets,
                'totalCount' => $totalCount,
                'totalPages' => $totalPages,
                'currentPage' => $page,
                'perPage' => $perPage
            ];
        } catch (PDOException $e) {
            error_log("Error in searchTickets: " . $e->getMessage());
            return [
                'tickets' => [],
                'totalCount' => 0,
                'totalPages' => 0,
                'currentPage' => 1,
                'perPage' => $perPage
            ];
        }
    }

    /**
     * دریافت تعداد درخواست کار‌ها بر اساس وضعیت
     * 
     * @param string $status وضعیت درخواست کار
     * @return int تعداد درخواست کار‌ها
     */
    public function getTicketsByStatus($status) {
        try {
            $query = "SELECT COUNT(*) as ticket_count FROM tickets WHERE status = :status";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':status', $status, PDO::PARAM_STR);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['ticket_count'] ?? 0;
        } catch (PDOException $e) {
            error_log("Error in getTicketsByStatus: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * دریافت درخواست کار‌های اخیر
     * 
     * @param int $limit تعداد درخواست کار‌ها
     * @param int|null $userId شناسه کاربر (اختیاری)
     * @return array درخواست کار‌های اخیر
     */
    public function getRecentTickets($limit = 5, $userId = null) {
        try {
            $query = "
                SELECT 
                    t.id, 
                    t.title, 
                    t.status, 
                    t.priority, 
                    t.created_at, 
                    t.employee_name AS requester_name,
                    u.fullname AS support_name
                FROM tickets t
                LEFT JOIN users u ON t.assigned_to = u.id
            ";
            
            $params = [];
            
            if ($userId !== null) {
                $query .= " WHERE t.user_id = :user_id";
                $params[':user_id'] = $userId;
            }
            
            $query .= " ORDER BY t.created_at DESC LIMIT :limit";
            $params[':limit'] = $limit;
            
            $stmt = $this->db->prepare($query);
            
            foreach ($params as $key => $value) {
                if ($key === ':limit' || $key === ':user_id') {
                    $stmt->bindValue($key, $value, PDO::PARAM_INT);
                } else {
                    $stmt->bindValue($key, $value);
                }
            }
            
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getRecentTickets: " . $e->getMessage());
            return [];
        }
    }

    /**
     * محاسبه میانگین زمان پاسخ‌دهی
     * 
     * @return float میانگین زمان پاسخ‌دهی (دقیقه)
     */
    public function calculateAverageResponseTime() {
        try {
            // کوئری برای محاسبه میانگین زمان پاسخ‌دهی
            $query = "SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, resolved_at)) AS average_response_time
                    FROM tickets
                    WHERE resolved_at IS NOT NULL"; // فقط درخواست کار‌های حل‌شده

            $stmt = $this->db->query($query);

            if ($stmt) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                return $row['average_response_time'] ?? 0;
            }

            return 0;
        } catch (PDOException $e) {
            error_log("Error in calculateAverageResponseTime: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * عملکرد تیم پشتیبان
     * 
     * @return array اطلاعات عملکرد تیم پشتیبان
     */
    public function getSupportTeamPerformance() {
        try {
            $query = "SELECT 
                        u.id AS support_id,
                        u.fullname AS support_name,
                        COUNT(t.id) AS assigned_tickets,
                        AVG(TIMESTAMPDIFF(MINUTE, t.created_at, t.resolved_at)) AS avg_resolution_time,
                        SUM(CASE WHEN t.status = 'closed' THEN 1 ELSE 0 END) AS resolved_tickets,
                        SUM(CASE WHEN t.status IN ('open', 'in_progress', 'waiting') THEN 1 ELSE 0 END) AS remaining_tickets,
                        AVG(r.rating) AS avg_user_satisfaction
                    FROM 
                        users u
                    LEFT JOIN 
                        tickets t ON u.id = t.assigned_to
                    LEFT JOIN 
                        ratings r ON t.id = r.ticket_id
                    WHERE 
                        u.role_id = 3
                    GROUP BY 
                        u.id, u.fullname
                    ORDER BY 
                        assigned_tickets DESC";

            $stmt = $this->db->prepare($query);
            $stmt->execute();

            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // تبدیل مقادیر NULL به صفر و فرمت‌بندی اعداد اعشاری
            foreach ($results as &$result) {
                $result['avg_resolution_time'] = $result['avg_resolution_time'] ? round($result['avg_resolution_time'], 2) : 0;
                $result['avg_user_satisfaction'] = $result['avg_user_satisfaction'] ? round($result['avg_user_satisfaction'], 1) : 0;
                $result['assigned_tickets'] = (int)$result['assigned_tickets'];
                $result['resolved_tickets'] = (int)$result['resolved_tickets'];
                $result['remaining_tickets'] = (int)$result['remaining_tickets'];
            }
            
            return $results;
        } catch (PDOException $e) {
            error_log("Error in getSupportTeamPerformance: " . $e->getMessage());
            return [];
        }
    }

    /**
     * تعداد کل درخواست کار‌های معوق
     * 
     * @return int تعداد درخواست کار‌های معوق
     */
    public function getOverdueTicketsCount() {
        try {
            $query = "SELECT COUNT(*) AS overdue_tickets 
                    FROM tickets 
                    WHERE due_date < NOW() AND status IN ('open', 'in_progress')";
            $stmt = $this->db->query($query);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['overdue_tickets'] ?? 0;
        } catch (PDOException $e) {
            error_log("Error in getOverdueTicketsCount: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * تعداد درخواست کار‌های اولویت‌دار (عادی، فوری، بحرانی)
     * 
     * @return array تعداد درخواست کار‌ها بر اساس اولویت
     */
    public function getTicketsByPriority() {
        try {
            $query = "SELECT priority, COUNT(*) AS count 
                    FROM tickets 
                    GROUP BY priority";
            $stmt = $this->db->query($query);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // تبدیل نتایج به آرایه‌ای با کلیدهای priority
            $ticketsByPriority = [
                'normal' => 0,
                'urgent' => 0,
                'critical' => 0
            ];
            foreach ($results as $row) {
                $ticketsByPriority[$row['priority']] = (int)$row['count'];
            }

            return $ticketsByPriority;
        } catch (PDOException $e) {
            error_log("Error in getTicketsByPriority: " . $e->getMessage());
            return [
                'normal' => 0,
                'urgent' => 0,
                'critical' => 0
            ];
        }
    }

    /**
     * کاربران با بیشترین تعداد درخواست کار‌های ثبت‌شده
     * 
     * @param int $limit تعداد نتایج
     * @return array لیست کاربران
     */
    public function getTopUsersByTickets($limit = 5) {
        try {
            $query = "SELECT 
                        t.employee_name AS user_name, 
                        COUNT(t.id) AS ticket_count,
                        t.plant_name,
                        t.unit_name
                    FROM 
                        tickets t
                    GROUP BY 
                        t.employee_name, t.plant_name, t.unit_name
                    ORDER BY 
                        ticket_count DESC
                    LIMIT :limit";

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getTopUsersByTickets: " . $e->getMessage());
            return [];
        }
    }

    /**
     * تعداد درخواست کار‌های معوق برای هر کاربر
     * 
     * @return array لیست کاربران با درخواست کار‌های معوق
     */
    public function getOverdueTicketsByUser() {
        try {
            $query = "SELECT 
                        t.employee_name AS user_name, 
                        COUNT(t.id) AS overdue_tickets,
                        t.plant_name,
                        t.unit_name
                    FROM 
                        tickets t
                    WHERE 
                        t.status IN ('open', 'in_progress') AND t.due_date < NOW()
                    GROUP BY 
                        t.employee_name, t.plant_name, t.unit_name
                    ORDER BY
                        overdue_tickets DESC";

            $stmt = $this->db->query($query);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getOverdueTicketsByUser: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت اطلاعات یک درخواست کار با شناسه
     * 
     * @param int $ticketId شناسه درخواست کار
     * @return array|bool اطلاعات درخواست کار یا false در صورت خطا
     */
    public function getTicketById($ticketId) {
        try {
            // کوئری دریافت اطلاعات درخواست کار
            $query = "
                SELECT 
                    t.*,
                    u.fullname AS support_name
                FROM 
                    tickets t
                LEFT JOIN 
                    users u ON t.assigned_to = u.id
                WHERE 
                    t.id = :ticket_id
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':ticket_id', $ticketId, PDO::PARAM_INT);
            $stmt->execute();
            
            $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$ticket) {
                return false;
            }
            
            // دریافت تجهیز‌های مرتبط با درخواست کار
            $assetsQuery = "
                SELECT 
                    a.id,
                    a.name,
                    a.asset_tag,
                    a.serial_number,
                    m.name AS model_name,
                    c.name AS category_name
                FROM 
                    ticket_assets ta
                JOIN 
                    assets a ON ta.asset_id = a.id
                LEFT JOIN 
                    asset_models m ON a.model_id = m.id
                LEFT JOIN 
                    asset_categories c ON m.category_id = c.id
                WHERE 
                    ta.ticket_id = :ticket_id
            ";
            
            $assetsStmt = $this->db->prepare($assetsQuery);
            $assetsStmt->bindParam(':ticket_id', $ticketId, PDO::PARAM_INT);
            $assetsStmt->execute();
            
            $ticket['assets'] = $assetsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // دریافت تاریخچه تغییرات وضعیت
            $statusHistoryQuery = "
                SELECT 
                    tsc.id,
                    tsc.old_status,
                    tsc.new_status,
                    tsc.changed_at,
                    tsc.changed_by,
                    u.fullname AS changed_by_name
                FROM 
                    ticket_status_changes tsc
                LEFT JOIN 
                    users u ON tsc.changed_by = u.id
                WHERE 
                    tsc.ticket_id = :ticket_id
                ORDER BY 
                    tsc.changed_at DESC
            ";
            
            $statusHistoryStmt = $this->db->prepare($statusHistoryQuery);
            $statusHistoryStmt->bindParam(':ticket_id', $ticketId, PDO::PARAM_INT);
            $statusHistoryStmt->execute();
            
            $ticket['status_history'] = $statusHistoryStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // دریافت پاسخ‌های درخواست کار
            $repliesQuery = "
                SELECT 
                    tr.id,
                    tr.content,
                    tr.created_at,
                    tr.user_id,
                    u.fullname AS user_name,
                    tr.is_private
                FROM 
                    ticket_replies tr
                LEFT JOIN 
                    users u ON tr.user_id = u.id
                WHERE 
                    tr.ticket_id = :ticket_id
                ORDER BY 
                    tr.created_at ASC
            ";
            
            $repliesStmt = $this->db->prepare($repliesQuery);
            $repliesStmt->bindParam(':ticket_id', $ticketId, PDO::PARAM_INT);
            $repliesStmt->execute();
            
            $ticket['replies'] = $repliesStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // دریافت امتیاز کاربر
            $ratingQuery = "
                SELECT 
                    r.rating,
                    r.comment,
                    r.created_at
                FROM 
                    ratings r
                WHERE 
                    r.ticket_id = :ticket_id
                ORDER BY 
                    r.created_at DESC
                LIMIT 1
            ";
            
            $ratingStmt = $this->db->prepare($ratingQuery);
            $ratingStmt->bindParam(':ticket_id', $ticketId, PDO::PARAM_INT);
            $ratingStmt->execute();
            
            $rating = $ratingStmt->fetch(PDO::FETCH_ASSOC);
            $ticket['rating'] = $rating ? $rating : null;
            
            // محاسبه زمان پاسخگویی
            if (!empty($ticket['resolved_at']) && !empty($ticket['created_at'])) {
                $created = new DateTime($ticket['created_at']);
                $resolved = new DateTime($ticket['resolved_at']);
                $interval = $created->diff($resolved);
                
                $ticket['response_time'] = [
                    'days' => $interval->days,
                    'hours' => $interval->h,
                    'minutes' => $interval->i,
                    'formatted' => $interval->format('%a روز، %h ساعت و %i دقیقه')
                ];
            } else {
                $ticket['response_time'] = null;
            }
            
            return $ticket;
        } catch (PDOException $e) {
            error_log("Error in getTicketById: " . $e->getMessage());
            return false;
        }
    }

    /**
     * افزودن پاسخ به درخواست کار
     * 
     * @param array $data اطلاعات پاسخ
     * @return int|bool شناسه پاسخ جدید یا false در صورت خطا
     */
    public function addReply($data) {
        try {
            // آماده‌سازی کوئری برای درج پاسخ
            $query = "
                INSERT INTO ticket_replies (
                    ticket_id, user_id, content, is_private, created_at
                ) VALUES (
                    :ticket_id, :user_id, :content, :is_private, NOW()
                )
            ";
            
            $stmt = $this->db->prepare($query);
            
            // بایند کردن پارامترها
            $stmt->bindParam(':ticket_id', $data['ticket_id'], PDO::PARAM_INT);
            $stmt->bindParam(':user_id', $data['user_id'], PDO::PARAM_INT);
            $stmt->bindParam(':content', $data['content'], PDO::PARAM_STR);
            $stmt->bindParam(':is_private', $data['is_private'], PDO::PARAM_BOOL);
            
            // اجرای کوئری
            $stmt->execute();
            
            // به‌روزرسانی زمان آخرین به‌روزرسانی درخواست کار
            $updateTicketQuery = "
                UPDATE tickets 
                SET updated_at = NOW() 
                WHERE id = :ticket_id
            ";
            
            $updateStmt = $this->db->prepare($updateTicketQuery);
            $updateStmt->bindParam(':ticket_id', $data['ticket_id'], PDO::PARAM_INT);
            $updateStmt->execute();
            
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log("Error in addReply: " . $e->getMessage());
            return false;
        }
    }

    /**
     * ثبت امتیاز برای درخواست کار
     * 
     * @param array $data اطلاعات امتیاز
     * @return bool نتیجه عملیات
     */
    public function rateTicket($data) {
        try {
            // بررسی وجود امتیاز قبلی
            $checkQuery = "
                SELECT id FROM ratings 
                WHERE ticket_id = :ticket_id
            ";
            
            $checkStmt = $this->db->prepare($checkQuery);
            $checkStmt->bindParam(':ticket_id', $data['ticket_id'], PDO::PARAM_INT);
            $checkStmt->execute();
            
            $existingRating = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existingRating) {
                // به‌روزرسانی امتیاز موجود
                $query = "
                    UPDATE ratings 
                    SET 
                        rating = :rating,
                        comment = :comment,
                        created_at = NOW()
                    WHERE 
                        ticket_id = :ticket_id
                ";
            } else {
                // درج امتیاز جدید
                $query = "
                    INSERT INTO ratings (
                        ticket_id, rating, comment, created_at
                    ) VALUES (
                        :ticket_id, :rating, :comment, NOW()
                    )
                ";
            }
            
            $stmt = $this->db->prepare($query);
            
            // بایند کردن پارامترها
            $stmt->bindParam(':ticket_id', $data['ticket_id'], PDO::PARAM_INT);
            $stmt->bindParam(':rating', $data['rating'], PDO::PARAM_INT);
            $stmt->bindParam(':comment', $data['comment'], PDO::PARAM_STR);
            
            // اجرای کوئری
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error in rateTicket: " . $e->getMessage());
            return false;
        }
    }

    /**
     * ثبت تغییر وضعیت درخواست کار
     * 
     * @param int $ticketId شناسه درخواست کار
     * @param string $oldStatus وضعیت قبلی
     * @param string $newStatus وضعیت جدید
     * @param int $userId شناسه کاربر تغییردهنده
     * @return bool نتیجه عملیات
     */
    public function logStatusChange($ticketId, $oldStatus, $newStatus, $userId) {
        try {
            $query = "
                INSERT INTO ticket_status_changes (
                    ticket_id, old_status, new_status, changed_at, changed_by
                ) VALUES (
                    :ticket_id, :old_status, :new_status, NOW(), :changed_by
                )
            ";
            
            $stmt = $this->db->prepare($query);
            
            $stmt->bindParam(':ticket_id', $ticketId, PDO::PARAM_INT);
            $stmt->bindParam(':old_status', $oldStatus, PDO::PARAM_STR);
            $stmt->bindParam(':new_status', $newStatus, PDO::PARAM_STR);
            $stmt->bindParam(':changed_by', $userId, PDO::PARAM_INT);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error in logStatusChange: " . $e->getMessage());
            return false;
        }
    }

    /**
     * ثبت تغییر پشتیبان درخواست کار
     * 
     * @param int $ticketId شناسه درخواست کار
     * @param int|null $oldAssignee شناسه پشتیبان قبلی
     * @param int|null $newAssignee شناسه پشتیبان جدید
     * @param int $userId شناسه کاربر تغییردهنده
     * @return bool نتیجه عملیات
     */
    public function logAssignmentChange($ticketId, $oldAssignee, $newAssignee, $userId) {
        try {
            $query = "
                INSERT INTO ticket_assignment_changes (
                    ticket_id, old_assignee, new_assignee, changed_at, changed_by
                ) VALUES (
                    :ticket_id, :old_assignee, :new_assignee, NOW(), :changed_by
                )
            ";
            
            $stmt = $this->db->prepare($query);
            
            $stmt->bindParam(':ticket_id', $ticketId, PDO::PARAM_INT);
            $stmt->bindParam(':old_assignee', $oldAssignee, PDO::PARAM_INT);
            $stmt->bindParam(':new_assignee', $newAssignee, PDO::PARAM_INT);
            $stmt->bindParam(':changed_by', $userId, PDO::PARAM_INT);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error in logAssignmentChange: " . $e->getMessage());
            return false;
        }
    }

    /**
     * دریافت آمار درخواست کار‌ها برای داشبورد
     * 
     * @return array آمار درخواست کار‌ها
     */
    public function getDashboardStats() {
        try {
            $stats = [];
            
            // تعداد کل درخواست کار‌ها
            $totalQuery = "SELECT COUNT(*) FROM tickets";
            $stmt = $this->db->query($totalQuery);
            $stats['total_tickets'] = $stmt->fetchColumn();
            
            // تعداد درخواست کار‌ها بر اساس وضعیت
            $statusQuery = "SELECT status, COUNT(*) as count FROM tickets GROUP BY status";
            $stmt = $this->db->query($statusQuery);
            $stats['by_status'] = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $stats['by_status'][$row['status']] = (int)$row['count'];
            }
            
            // تعداد درخواست کار‌های باز
            $openQuery = "SELECT COUNT(*) FROM tickets WHERE status IN ('open', 'in_progress', 'waiting')";
            $stmt = $this->db->query($openQuery);
            $stats['open_tickets'] = $stmt->fetchColumn();
            
            // تعداد درخواست کار‌های بسته شده
            $closedQuery = "SELECT COUNT(*) FROM tickets WHERE status = 'closed'";
            $stmt = $this->db->query($closedQuery);
            $stats['closed_tickets'] = $stmt->fetchColumn();
            
            // تعداد درخواست کار‌های معوق
            $overdueQuery = "SELECT COUNT(*) FROM tickets WHERE due_date < NOW() AND status IN ('open', 'in_progress')";
            $stmt = $this->db->query($overdueQuery);
            $stats['overdue_tickets'] = $stmt->fetchColumn();
            
            // میانگین زمان پاسخ‌دهی
            $avgResponseQuery = "SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, resolved_at)) FROM tickets WHERE resolved_at IS NOT NULL";
            $stmt = $this->db->query($avgResponseQuery);
            $stats['avg_response_time'] = round($stmt->fetchColumn() ?? 0, 2);
            
            // تعداد درخواست کار‌ها بر اساس اولویت
            $priorityQuery = "SELECT priority, COUNT(*) as count FROM tickets GROUP BY priority";
            $stmt = $this->db->query($priorityQuery);
            $stats['by_priority'] = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $stats['by_priority'][$row['priority']] = (int)$row['count'];
            }
            
            // تعداد درخواست کار‌ها بر اساس نوع مشکل
            $problemTypeQuery = "SELECT problem_type, COUNT(*) as count FROM tickets GROUP BY problem_type ORDER BY count DESC LIMIT 5";
            $stmt = $this->db->query($problemTypeQuery);
            $stats['by_problem_type'] = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $stats['by_problem_type'][$row['problem_type']] = (int)$row['count'];
            }
            
            // میانگین امتیاز رضایت کاربران
            $avgRatingQuery = "SELECT AVG(rating) FROM ratings";
            $stmt = $this->db->query($avgRatingQuery);
            $stats['avg_user_satisfaction'] = round($stmt->fetchColumn() ?? 0, 1);
            
            return $stats;
        } catch (PDOException $e) {
            error_log("Error in getDashboardStats: " . $e->getMessage());
            return [
                'total_tickets' => 0,
                'by_status' => [],
                'open_tickets' => 0,
                'closed_tickets' => 0,
                'overdue_tickets' => 0,
                'avg_response_time' => 0,
                'by_priority' => [],
                'by_problem_type' => [],
                'avg_user_satisfaction' => 0
            ];
        }
    }

    /**
     * دریافت درخواست‌های کار مرتبط با یک تجهیز
     * 
     * @param int $assetId شناسه تجهیز
     * @return array لیست درخواست‌های کار
     */
    public function getTicketsByAssetId($assetId) {
        try {
            $query = "
                SELECT 
                    t.id, 
                    t.title, 
                    t.status, 
                    t.priority, 
                    t.created_at, 
                    t.resolved_at,
                    t.employee_name AS requester_name,
                    u.fullname AS support_name
                FROM 
                    tickets t
                JOIN 
                    ticket_assets ta ON t.id = ta.ticket_id
                LEFT JOIN 
                    users u ON t.assigned_to = u.id
                WHERE 
                    ta.asset_id = :asset_id
                ORDER BY 
                    t.created_at DESC
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':asset_id', $assetId, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getTicketsByAssetId: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت تجهیز‌های مرتبط با یک درخواست کار
     * 
     * @param int $ticketId شناسه درخواست کار
     * @return array لیست تجهیز‌ها
     */
    public function getAssetsByTicketId($ticketId) {
        try {
            $query = "
                SELECT 
                    a.id,
                    a.name,
                    a.asset_tag,
                    a.serial_number,
                    a.status,
                    m.name AS model_name,
                    c.name AS category_name
                FROM 
                    assets a
                JOIN 
                    ticket_assets ta ON a.id = ta.asset_id
                LEFT JOIN 
                    asset_models m ON a.model_id = m.id
                LEFT JOIN 
                    asset_categories c ON m.category_id = c.id
                WHERE 
                    ta.ticket_id = :ticket_id
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':ticket_id', $ticketId, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getAssetsByTicketId: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت تجهیز‌های کاربر برای انتخاب در فرم درخواست کار
     * 
     * @param string $employeeNumber شماره پرسنلی کاربر
     * @return array لیست تجهیز‌های کاربر
     */
    public function getUserAssetsForTicket($employeeNumber) {
        try {
            $query = "
                SELECT 
                    a.id,
                    a.name,
                    a.asset_tag,
                    a.serial_number,
                    m.name AS model_name,
                    c.name AS category_name
                FROM 
                    assets a
                JOIN 
                    asset_assignments aa ON a.id = aa.asset_id AND aa.is_current = 1
                JOIN 
                    users u ON aa.user_id = u.id
                LEFT JOIN 
                    asset_models m ON a.model_id = m.id
                LEFT JOIN 
                    asset_categories c ON m.category_id = c.id
                WHERE 
                    u.employee_number = :employee_number
                ORDER BY 
                    c.name, a.name
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':employee_number', $employeeNumber, PDO::PARAM_STR);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getUserAssetsForTicket: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت آمار درخواست‌های کار بر اساس تجهیز‌ها
     * 
     * @return array آمار درخواست‌های کار بر اساس تجهیز‌ها
     */
    public function getTicketStatsByAssetCategory() {
        try {
            $query = "
                SELECT 
                    c.name AS category_name,
                    COUNT(DISTINCT t.id) AS ticket_count
                FROM 
                    tickets t
                JOIN 
                    ticket_assets ta ON t.id = ta.ticket_id
                JOIN 
                    assets a ON ta.asset_id = a.id
                JOIN 
                    asset_models m ON a.model_id = m.id
                JOIN 
                    asset_categories c ON m.category_id = c.id
                GROUP BY 
                    c.name
                ORDER BY 
                    ticket_count DESC
            ";
            
            $stmt = $this->db->query($query);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getTicketStatsByAssetCategory: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت تجهیز‌های با بیشترین تعداد درخواست کار
     * 
     * @param int $limit تعداد نتایج
     * @return array لیست تجهیز‌ها
     */
    public function getAssetsWithMostTickets($limit = 10) {
        try {
            $query = "
                SELECT 
                    a.id,
                    a.name,
                    a.asset_tag,
                    m.name AS model_name,
                    c.name AS category_name,
                    COUNT(DISTINCT t.id) AS ticket_count,
                    (
                        SELECT u.fullname
                        FROM users u
                        JOIN asset_assignments aa ON u.id = aa.user_id
                        WHERE aa.asset_id = a.id AND aa.is_current = 1
                        LIMIT 1
                    ) AS assigned_to
                FROM 
                    assets a
                JOIN 
                    ticket_assets ta ON a.id = ta.asset_id
                JOIN 
                    tickets t ON ta.ticket_id = t.id
                LEFT JOIN 
                    asset_models m ON a.model_id = m.id
                LEFT JOIN 
                    asset_categories c ON m.category_id = c.id
                GROUP BY 
                    a.id, a.name, a.asset_tag, m.name, c.name
                ORDER BY 
                    ticket_count DESC
                LIMIT :limit
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getAssetsWithMostTickets: " . $e->getMessage());
            return [];
        }
    }

    /**
     * ایجاد برنامه تعمیر و نگهداری بر اساس درخواست کار
     * 
     * @param int $ticketId شناسه درخواست کار
     * @param array $data اطلاعات برنامه تعمیر و نگهداری
     * @return int|bool شناسه برنامه تعمیر و نگهداری یا false در صورت خطا
     */
    public function createMaintenanceScheduleFromTicket($ticketId, $data) {
        try {
            // شروع تراکنش
            $this->db->beginTransaction();
            
            // دریافت تجهیز‌های مرتبط با درخواست کار
            $assets = $this->getAssetsByTicketId($ticketId);
            
            if (empty($assets)) {
                // بازگشت تراکنش در صورت عدم وجود تجهیز
                $this->db->rollBack();
                return false;
            }
            
            $scheduleIds = [];
            
            foreach ($assets as $asset) {
                // ایجاد برنامه تعمیر و نگهداری برای هر تجهیز
                $scheduleQuery = "
                    INSERT INTO maintenance_schedules (
                        asset_id, maintenance_type_id, frequency_days, 
                        next_maintenance_date, technician_id, notes, 
                        created_at, updated_at, ticket_id
                    ) VALUES (
                        :asset_id, :maintenance_type_id, :frequency_days, 
                        :next_maintenance_date, :technician_id, :notes, 
                        NOW(), NOW(), :ticket_id
                    )
                ";
                
                $scheduleStmt = $this->db->prepare($scheduleQuery);
                $scheduleStmt->bindParam(':asset_id', $asset['id'], PDO::PARAM_INT);
                $scheduleStmt->bindParam(':maintenance_type_id', $data['maintenance_type_id'], PDO::PARAM_INT);
                $scheduleStmt->bindParam(':frequency_days', $data['frequency_days'], PDO::PARAM_INT);
                $scheduleStmt->bindParam(':next_maintenance_date', $data['next_maintenance_date'], PDO::PARAM_STR);
                $scheduleStmt->bindParam(':technician_id', $data['technician_id'], PDO::PARAM_INT);
                $scheduleStmt->bindParam(':notes', $data['notes'], PDO::PARAM_STR);
                $scheduleStmt->bindParam(':ticket_id', $ticketId, PDO::PARAM_INT);
                $scheduleStmt->execute();
                
                $scheduleIds[] = $this->db->lastInsertId();
            }
            
            // به‌روزرسانی درخواست کار
            $updateTicketQuery = "
                UPDATE tickets
                SET has_maintenance_schedule = 1, updated_at = NOW()
                WHERE id = :ticket_id
            ";
            
            $updateTicketStmt = $this->db->prepare($updateTicketQuery);
            $updateTicketStmt->bindParam(':ticket_id', $ticketId, PDO::PARAM_INT);
            $updateTicketStmt->execute();
            
            // تایید تراکنش
            $this->db->commit();
            
            return $scheduleIds;
        } catch (PDOException $e) {
            // بازگشت تراکنش در صورت خطا
            $this->db->rollBack();
            error_log("Error in createMaintenanceScheduleFromTicket: " . $e->getMessage());
            return false;
        }
    }

    /**
     * دریافت نوع‌های مشکل برای فرم درخواست کار
     * 
     * @return array لیست نوع‌های مشکل
     */
    public function getProblemTypes() {
        try {
            $query = "
                SELECT DISTINCT problem_type 
                FROM tickets 
                WHERE problem_type IS NOT NULL AND problem_type != ''
                ORDER BY problem_type
            ";
            
            $stmt = $this->db->query($query);
            
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            error_log("Error in getProblemTypes: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت تعداد درخواست‌ها بر اساس وضعیت
     * 
     * @return array آمار درخواست‌ها بر اساس وضعیت
     */
    public function getTicketsCountByStatus() {
        try {
            // بررسی نوع اتصال به دیتابیس و اجرای کوئری مناسب
            if ($this->db instanceof PDO) {
                // اجرا با PDO
                $query = "
                    SELECT 
                        status,
                        COUNT(*) as count
                    FROM 
                        tickets
                    GROUP BY 
                        status
                ";
                
                $stmt = $this->db->query($query);
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // تبدیل نتایج به آرایه با کلید وضعیت
                $statusCounts = [];
                foreach ($results as $row) {
                    $statusCounts[$row['status']] = (int)$row['count'];
                }
                
                // اطمینان از وجود تمام وضعیت‌ها در آرایه نتایج
                $allStatuses = ['new', 'in_progress', 'on_hold', 'resolved', 'closed', 'reopened', 'canceled'];
                foreach ($allStatuses as $status) {
                    if (!isset($statusCounts[$status])) {
                        $statusCounts[$status] = 0;
                    }
                }
                
                return $statusCounts;
                
            } elseif ($this->db instanceof mysqli) {
                // اجرا با mysqli
                $query = "
                    SELECT 
                        status,
                        COUNT(*) as count
                    FROM 
                        tickets
                    GROUP BY 
                        status
                ";
                
                $result = $this->db->query($query);
                
                if (!$result) {
                    throw new Exception("خطا در اجرای کوئری: " . $this->db->error);
                }
                
                $statusCounts = [];
                while ($row = $result->fetch_assoc()) {
                    $statusCounts[$row['status']] = (int)$row['count'];
                }
                
                // اطمینان از وجود تمام وضعیت‌ها در آرایه نتایج
                $allStatuses = ['new', 'in_progress', 'on_hold', 'resolved', 'closed', 'reopened', 'canceled'];
                foreach ($allStatuses as $status) {
                    if (!isset($statusCounts[$status])) {
                        $statusCounts[$status] = 0;
                    }
                }
                
                return $statusCounts;
            } else {
                throw new Exception("نوع اتصال به دیتابیس نامعتبر است.");
            }
        } catch (Exception $e) {
            error_log("Error in getTicketsCountByStatus: " . $e->getMessage());
            return [
                'new' => 0,
                'in_progress' => 0,
                'on_hold' => 0,
                'resolved' => 0,
                'closed' => 0,
                'reopened' => 0,
                'canceled' => 0
            ];
        }
    }

    /**
     * دریافت تعداد درخواست‌های در انتظار برای هر کاربر
     * 
     * @param int $limit محدودیت تعداد نتایج (اختیاری)
     * @return array لیست کاربران با تعداد درخواست‌های در انتظار
     */
    public function getPendingTicketsByUser($limit = 10) {
        try {
            // بررسی نوع اتصال به دیتابیس
            if ($this->db instanceof PDO) {
                // اجرا با PDO
                $query = "
                    SELECT 
                        u.id AS user_id,
                        u.fullname AS user_name,
                        u.username,
                        COUNT(t.id) AS pending_tickets
                    FROM 
                        users u
                    JOIN 
                        tickets t ON u.id = t.assigned_to
                    WHERE 
                        t.status IN ('new', 'in_progress', 'reopened')
                    GROUP BY 
                        u.id, u.fullname, u.username
                    ORDER BY 
                        pending_tickets DESC
                    LIMIT :limit
                ";
                
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
                $stmt->execute();
                
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
                
            } elseif ($this->db instanceof mysqli) {
                // اجرا با mysqli
                $query = "
                    SELECT 
                        u.id AS user_id,
                        u.fullname AS user_name,
                        u.username,
                        COUNT(t.id) AS pending_tickets
                    FROM 
                        users u
                    JOIN 
                        tickets t ON u.id = t.assigned_to
                    WHERE 
                        t.status IN ('new', 'in_progress', 'reopened')
                    GROUP BY 
                        u.id, u.fullname, u.username
                    ORDER BY 
                        pending_tickets DESC
                    LIMIT ?
                ";
                
                $stmt = $this->db->prepare($query);
                $stmt->bind_param("i", $limit);
                $stmt->execute();
                
                $result = $stmt->get_result();
                return $result->fetch_all(MYSQLI_ASSOC);
            } else {
                throw new Exception("نوع اتصال به دیتابیس نامعتبر است.");
            }
        } catch (Exception $e) {
            error_log("Error in getPendingTicketsByUser: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت آمار درخواست‌ها بر اساس وضعیت
     * 
     * @param array $filters فیلترهای اختیاری (مانند بازه زمانی)
     * @return array آمار درخواست‌ها بر اساس وضعیت
     */
    public function getTicketStatusCounts($filters = []) {
        try {
            // ساخت شرط WHERE بر اساس فیلترها
            $whereConditions = [];
            $params = [];
            $types = '';
            
            if (!empty($filters['start_date'])) {
                $whereConditions[] = "created_at >= ?";
                $params[] = $filters['start_date'];
                $types .= 's';
            }
            
            if (!empty($filters['end_date'])) {
                $whereConditions[] = "created_at <= ?";
                $params[] = $filters['end_date'];
                $types .= 's';
            }
            
            if (!empty($filters['category_id'])) {
                $whereConditions[] = "category_id = ?";
                $params[] = $filters['category_id'];
                $types .= 'i';
            }
            
            if (!empty($filters['priority'])) {
                $whereConditions[] = "priority = ?";
                $params[] = $filters['priority'];
                $types .= 's';
            }
            
            // ساخت بخش WHERE کوئری
            $whereClause = '';
            if (!empty($whereConditions)) {
                $whereClause = "WHERE " . implode(" AND ", $whereConditions);
            }
            
            // بررسی نوع اتصال به دیتابیس
            if ($this->db instanceof PDO) {
                // اجرا با PDO
                $query = "
                    SELECT 
                        status,
                        COUNT(*) as count
                    FROM 
                        tickets
                    $whereClause
                    GROUP BY 
                        status
                ";
                
                $stmt = $this->db->prepare($query);
                
                // اضافه کردن پارامترها
                if (!empty($params)) {
                    for ($i = 0; $i < count($params); $i++) {
                        $stmt->bindParam($i + 1, $params[$i], is_int($params[$i]) ? PDO::PARAM_INT : PDO::PARAM_STR);
                    }
                }
                
                $stmt->execute();
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // تبدیل نتایج به آرایه با کلید وضعیت
                $statusCounts = [];
                foreach ($results as $row) {
                    $statusCounts[$row['status']] = (int)$row['count'];
                }
                
                // اطمینان از وجود تمام وضعیت‌ها در آرایه نتایج
                $allStatuses = ['new', 'in_progress', 'on_hold', 'resolved', 'closed', 'reopened', 'canceled'];
                foreach ($allStatuses as $status) {
                    if (!isset($statusCounts[$status])) {
                        $statusCounts[$status] = 0;
                    }
                }
                
                return $statusCounts;
                
            } elseif ($this->db instanceof mysqli) {
                // اجرا با mysqli
                $query = "
                    SELECT 
                        status,
                        COUNT(*) as count
                    FROM 
                        tickets
                    $whereClause
                    GROUP BY 
                        status
                ";
                
                if (!empty($params)) {
                    $stmt = $this->db->prepare($query);
                    $stmt->bind_param($types, ...$params);
                    $stmt->execute();
                    $result = $stmt->get_result();
                } else {
                    $result = $this->db->query($query);
                }
                
                if (!$result) {
                    throw new Exception("خطا در اجرای کوئری: " . $this->db->error);
                }
                
                $statusCounts = [];
                while ($row = $result->fetch_assoc()) {
                    $statusCounts[$row['status']] = (int)$row['count'];
                }
                
                // اطمینان از وجود تمام وضعیت‌ها در آرایه نتایج
                $allStatuses = ['new', 'in_progress', 'on_hold', 'resolved', 'closed', 'reopened', 'canceled'];
                foreach ($allStatuses as $status) {
                    if (!isset($statusCounts[$status])) {
                        $statusCounts[$status] = 0;
                    }
                }
                
                return $statusCounts;
            } else {
                throw new Exception("نوع اتصال به دیتابیس نامعتبر است.");
            }
        } catch (Exception $e) {
            error_log("Error in getTicketStatusCounts: " . $e->getMessage());
            return [
                'new' => 0,
                'in_progress' => 0,
                'on_hold' => 0,
                'resolved' => 0,
                'closed' => 0,
                'reopened' => 0,
                'canceled' => 0
            ];
        }
    }

    /**
     * دریافت آمار درخواست‌ها بر اساس تاریخ
     * 
     * @param int $days تعداد روزهای اخیر (پیش‌فرض: 30)
     * @return array آمار درخواست‌ها بر اساس تاریخ
     */
    public function getTicketCountsByDate($days = 30) {
        try {
            // بررسی نوع اتصال به دیتابیس
            if ($this->db instanceof PDO) {
                // اجرا با PDO
                $query = "
                    SELECT 
                        DATE(created_at) as date,
                        COUNT(*) as count
                    FROM 
                        tickets
                    WHERE 
                        created_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
                    GROUP BY 
                        DATE(created_at)
                    ORDER BY 
                        date
                ";
                
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(':days', $days, PDO::PARAM_INT);
                $stmt->execute();
                
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // ساخت آرایه با تمام روزها (حتی روزهایی که درخواستی ندارند)
                $ticketCounts = [];
                $startDate = new DateTime(date('Y-m-d', strtotime("-$days days")));
                $endDate = new DateTime(date('Y-m-d'));
                $interval = new DateInterval('P1D'); // یک روز
                $dateRange = new DatePeriod($startDate, $interval, $endDate);
                
                // پر کردن آرایه با صفر برای تمام روزها
                foreach ($dateRange as $date) {
                    $dateStr = $date->format('Y-m-d');
                    $ticketCounts[$dateStr] = 0;
                }
                
                // اضافه کردن امروز
                $today = date('Y-m-d');
                $ticketCounts[$today] = 0;
                
                // پر کردن آرایه با مقادیر واقعی
                foreach ($results as $row) {
                    $ticketCounts[$row['date']] = (int)$row['count'];
                }
                
                // تبدیل به فرمت مناسب برای نمودار
                $formattedResults = [];
                foreach ($ticketCounts as $date => $count) {
                    $formattedResults[] = [
                        'date' => $date,
                        'count' => $count
                    ];
                }
                
                return $formattedResults;
                
            } elseif ($this->db instanceof mysqli) {
                // اجرا با mysqli
                $query = "
                    SELECT 
                        DATE(created_at) as date,
                        COUNT(*) as count
                    FROM 
                        tickets
                    WHERE 
                        created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                    GROUP BY 
                        DATE(created_at)
                    ORDER BY 
                        date
                ";
                
                $stmt = $this->db->prepare($query);
                $stmt->bind_param("i", $days);
                $stmt->execute();
                
                $result = $stmt->get_result();
                $results = [];
                while ($row = $result->fetch_assoc()) {
                    $results[] = $row;
                }
                
                // ساخت آرایه با تمام روزها (حتی روزهایی که درخواستی ندارند)
                $ticketCounts = [];
                $startDate = new DateTime(date('Y-m-d', strtotime("-$days days")));
                $endDate = new DateTime(date('Y-m-d'));
                $interval = new DateInterval('P1D'); // یک روز
                $dateRange = new DatePeriod($startDate, $interval, $endDate);
                
                // پر کردن آرایه با صفر برای تمام روزها
                foreach ($dateRange as $date) {
                    $dateStr = $date->format('Y-m-d');
                    $ticketCounts[$dateStr] = 0;
                }
                
                // اضافه کردن امروز
                $today = date('Y-m-d');
                $ticketCounts[$today] = 0;
                
                // پر کردن آرایه با مقادیر واقعی
                foreach ($results as $row) {
                    $ticketCounts[$row['date']] = (int)$row['count'];
                }
                
                // تبدیل به فرمت مناسب برای نمودار
                $formattedResults = [];
                foreach ($ticketCounts as $date => $count) {
                    $formattedResults[] = [
                        'date' => $date,
                        'count' => $count
                    ];
                }
                
                return $formattedResults;
            } else {
                throw new Exception("نوع اتصال به دیتابیس نامعتبر است.");
            }
        } catch (Exception $e) {
            error_log("Error in getTicketCountsByDate: " . $e->getMessage());
            return [];
        }
    }
}