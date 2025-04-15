<?php

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../models/Ticket.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../helpers/AccessControl.php';
require_once __DIR__ . '/../helpers/date_helper.php';

use Hekmatinasser\Verta\Verta;

class TicketController {
    private $ticketModel;
    private $userModel;
    private $db;
    private $accessControl;

    public function __construct() {
        $this->ticketModel = new Ticket();
        $this->userModel = new User();
        $this->db = Database::getInstance()->getConnection();
        $this->accessControl = new AccessControl();
        
        // شروع جلسه اگر شروع نشده است
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * بررسی ورود کاربر
     * @return bool آیا کاربر وارد شده است یا خیر
     */
    private function checkUserLogin() {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /support_system/login');
            exit;
        }
        return true;
    }

    /**
     * جستجوی درخواست‌های کار
     */
    public function search() {
        // بررسی ورود کاربر
        $this->checkUserLogin();
        
        // دریافت پارامترهای جستجو، مرتب‌سازی و صفحه‌بندی از URL
        $filters = [
            'query' => $_GET['query'] ?? '',
            'status' => $_GET['status'] ?? '',
            'priority' => $_GET['priority'] ?? '',
            'requester' => $_GET['requester'] ?? '',
            'employee_id' => $_GET['employee_id'] ?? '',
            'plant' => $_GET['plant'] ?? '',
            'unit' => $_GET['unit'] ?? '',
            'created_date' => $_GET['created_date'] ?? '',
            'assigned_to' => $_GET['assigned_to'] ?? ''
        ];
        
        $sortBy = $_GET['sort'] ?? 'created_at';
        $order = $_GET['order'] ?? 'desc';
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
        
        // ثبت لاگ برای اشکال‌زدایی
        error_log("search method called");
        error_log("GET Params in search: " . print_r($_GET, true));
        error_log("Filters: " . print_r($filters, true));
        
        // اعمال فیلتر بر اساس نقش کاربر
        $userRole = $_SESSION['role_id'];
        
        if ($userRole != 1) { // اگر کاربر مدیر نیست
            if ($userRole == 3) { // پشتیبان
                $filters['assigned_to'] = $_SESSION['user_id'];
            } else { // کاربر عادی
                $filters['user_id'] = $_SESSION['user_id'];
            }
        }
        
        // فراخوانی متد جستجو از مدل Ticket
        $result = $this->ticketModel->searchTickets($filters, $sortBy, $order, $page, $perPage);
        
        // استخراج نتایج
        $tickets = $result['tickets'];
        $totalCount = $result['totalCount'];
        $totalPages = $result['totalPages'];
        $currentPage = $result['currentPage'];
        
        // نمایش صفحه لیست درخواست‌ها
        include __DIR__ . '/../views/tickets/index.php';
    }

    /**
     * نمایش فرم ایجاد درخواست کار جدید
     */
    public function create() {
        // بررسی ورود کاربر
        $this->checkUserLogin();
        
        // دریافت لیست کاربران برای ثبت درخواست برای دیگران (فقط برای مدیر و پشتیبان)
        $users = [];
        if ($_SESSION['role_id'] == 1 || $_SESSION['role_id'] == 3) {
            $users = $this->userModel->getAllUsers();
        }
        
        // نمایش فرم ایجاد درخواست
        include __DIR__ . '/../views/tickets/create.php';
    }

    /**
     * ایجاد درخواست کار جدید
     */
    public function store() {
        // بررسی ورود کاربر
        $this->checkUserLogin();
        
        // بررسی متد درخواست
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error'] = "فرم با متد POST ارسال نشده است.";
            header('Location: /support_system/tickets/create');
            exit;
        }
        
        // اعتبارسنجی داده‌های ورودی
        $title = $_POST['title'] ?? null;
        $problemType = $_POST['problem_type'] ?? null;
        $description = $_POST['description'] ?? null;
        $priority = $_POST['priority'] ?? null;
        
        if (!$title || !$problemType || !$description || !$priority) {
            $_SESSION['error'] = "اطلاعات فرم ناقص است.";
            $_SESSION['form_data'] = $_POST;
            header('Location: /support_system/tickets/create');
            exit;
        }
        
        // شماره پرسنلی کاربر جاری (ثبت‌کننده درخواست)
        $currentUserEmployeeNumber = $_SESSION['username'] ?? 'نامشخص';
        
        // شماره پرسنلی فرد نیازمند خدمت
        $requesterEmployeeNumber = null;
        
        // اطلاعات پیش‌فرض پلنت، واحد و نام کاربر
        $plantName = $_SESSION['plant'] ?? 'نامشخص';
        $unitName = $_SESSION['unit'] ?? 'نامشخص';
        $employeeName = $_SESSION['fullname'] ?? 'نامشخص';
        
        // بررسی مقدار چک‌باکس "ثبت برای دیگران"
        $isForOthers = isset($_POST['registerForOthers']) && $_POST['registerForOthers'] === 'on';
        
        if ($isForOthers) {
            // اگر درخواست برای دیگران باشد
            $requesterEmployeeNumber = $_POST['employee_number'] ?? null;
            
            if (!$requesterEmployeeNumber) {
                $_SESSION['error'] = "شماره پرسنلی فرد نیازمند خدمت وارد نشده است.";
                $_SESSION['form_data'] = $_POST;
                header('Location: /support_system/tickets/create');
                exit;
            }
            
            // واکشی اطلاعات پلنت، واحد و نام فرد نیازمند خدمت از پایگاه داده
            $userInfo = $this->userModel->getUserByUsername($requesterEmployeeNumber);
            
            if ($userInfo) {
                $employeeName = $userInfo['fullname'] ?? 'نامشخص';
                $plantName = $userInfo['plant'] ?? 'نامشخص';
                $unitName = $userInfo['unit'] ?? 'نامشخص';
            } else {
                $_SESSION['error'] = "کاربری با این شماره پرسنلی پیدا نشد.";
                $_SESSION['form_data'] = $_POST;
                header('Location: /support_system/tickets/create');
                exit;
            }
        } else {
            // اگر درخواست برای خود کاربر باشد
            $requesterEmployeeNumber = $currentUserEmployeeNumber;
        }
        
        // مدیریت آپلود فایل
        $filePath = null;
        if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../../public/uploads/tickets/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $fileName = time() . '_' . basename($_FILES['file']['name']);
            $uploadFile = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['file']['tmp_name'], $uploadFile)) {
                $filePath = $uploadFile;
            } else {
                $_SESSION['error'] = "خطا در آپلود فایل. لطفاً دوباره تلاش کنید.";
                $_SESSION['form_data'] = $_POST;
                header('Location: /support_system/tickets/create');
                exit;
            }
        } else if (isset($_FILES['file']['error']) && $_FILES['file']['error'] !== UPLOAD_ERR_NO_FILE) {
            $_SESSION['error'] = "خطا در آپلود فایل: " . $this->getFileUploadErrorMessage($_FILES['file']['error']);
            $_SESSION['form_data'] = $_POST;
            header('Location: /support_system/tickets/create');
            exit;
        }
        
        // محاسبه زمان ضرب‌الاجل (due_date) برای درخواست کار
        $dueDate = date('Y-m-d H:i:s', strtotime('+48 hours'));
        
        // آماده‌سازی داده‌ها برای ذخیره
        $ticketData = [
            'user_id' => $_SESSION['user_id'],
            'employee_number' => $currentUserEmployeeNumber,
            'requester_employee_number' => $requesterEmployeeNumber,
            'employee_name' => $employeeName,
            'plant_name' => $plantName,
            'unit_name' => $unitName,
            'problem_type' => $problemType,
            'description' => $description,
            'file_path' => $filePath,
            'priority' => $priority,
            'title' => $title,
            'status' => 'open',
            'due_date' => $dueDate
        ];
        
        // ذخیره درخواست کار
        $ticketId = $this->ticketModel->createTicket($ticketData);
        
        if ($ticketId) {
            $_SESSION['success'] = "درخواست کار با موفقیت ثبت شد.";
            header('Location: /support_system/tickets/view/' . $ticketId);
        } else {
            $_SESSION['error'] = "خطا در ثبت درخواست کار. لطفاً دوباره تلاش کنید.";
            $_SESSION['form_data'] = $_POST;
            header('Location: /support_system/tickets/create');
        }
        exit;
    }

    /**
     * نمایش جزئیات درخواست کار
     * @param int $id شناسه درخواست کار
     */
    public function view($id) {
        // بررسی ورود کاربر
        $this->checkUserLogin();
        
        // تبدیل شناسه به عدد صحیح
        $id = (int)$id;
        
        // دریافت اطلاعات درخواست کار
        $ticket = $this->ticketModel->getTicketById($id);
        
        if (!$ticket) {
            $_SESSION['error'] = "درخواست کار مورد نظر یافت نشد.";
            header('Location: /support_system/tickets');
            exit;
        }
        
        // بررسی دسترسی کاربر به این درخواست
        $userRole = $_SESSION['role_id'];
        $userId = $_SESSION['user_id'];
        
        if ($userRole != 1 && $userRole != 3 && $ticket['user_id'] != $userId) {
            $_SESSION['error'] = "شما دسترسی به این درخواست کار را ندارید.";
            header('Location: /support_system/tickets');
            exit;
        }
        
        // تغییر وضعیت درخواست کار به "در حال بررسی" (در صورت باز بودن)
        if ($ticket['status'] === 'open' && ($userRole == 1 || $userRole == 3 || $ticket['assigned_to'] == $userId)) {
            $this->ticketModel->updateTicketStatus($id, 'in_progress', $ticket['status'], $ticket['started_at'], $ticket['elapsed_time'] ?? 0);
            
            // بارگذاری مجدد اطلاعات درخواست
            $ticket = $this->ticketModel->getTicketById($id);
        }
        
        // دریافت پاسخ‌های مرتبط با درخواست کار
        $replies = $this->ticketModel->getTicketReplies($id);
        
        // دریافت فایل‌های پیوست‌شده به درخواست
        $attachments = $this->ticketModel->getAttachmentsByTicketId($id);
        
        // دریافت تاریخچه تغییرات وضعیت
        $statusHistory = $this->ticketModel->getTicketStatusHistory($id);
        
        // دریافت لیست کاربران پشتیبان برای ارجاع
        $supportUsers = [];
        if ($userRole == 1 || $userRole == 3) {
            $supportUsers = $this->userModel->getUsersByRole(3); // نقش پشتیبان
        }
        
        // محاسبه زمان صرف‌شده برای رسیدگی
        $elapsedSeconds = $ticket['elapsed_time'] ?? 0;
        if (!empty($ticket['started_at']) && $ticket['status'] !== 'closed' && $ticket['status'] !== 'resolved') {
            $startTime = new DateTime($ticket['started_at']);
            $currentTime = new DateTime();
            $interval = $startTime->diff($currentTime);
            
            // افزودن زمان جاری به زمان ذخیره‌شده
            $elapsedSeconds += $interval->days * 24 * 60 * 60;
            $elapsedSeconds += $interval->h * 60 * 60;
            $elapsedSeconds += $interval->i * 60;
            $elapsedSeconds += $interval->s;
        }
        
        // تبدیل تاریخ‌های میلادی به شمسی
        $ticket['created_at_jalali'] = (new Verta($ticket['created_at']))->format('Y/m/d H:i:s');
        $ticket['updated_at_jalali'] = (new Verta($ticket['updated_at']))->format('Y/m/d H:i:s');
        
        if (!empty($ticket['due_date'])) {
            $ticket['due_date_jalali'] = (new Verta($ticket['due_date']))->format('Y/m/d H:i:s');
        }
        
        if (!empty($ticket['started_at'])) {
            $ticket['started_at_jalali'] = (new Verta($ticket['started_at']))->format('Y/m/d H:i:s');
        }
        
        if (!empty($ticket['resolved_at'])) {
            $ticket['resolved_at_jalali'] = (new Verta($ticket['resolved_at']))->format('Y/m/d H:i:s');
        }
        
        // تبدیل تاریخ‌های پاسخ‌ها به شمسی
        foreach ($replies as &$reply) {
            $reply['created_at_jalali'] = (new Verta($reply['created_at']))->format('Y/m/d H:i:s');
        }
        
        // نمایش صفحه جزئیات درخواست
        include __DIR__ . '/../views/view_ticket.php';
    }

    /**
     * نمایش فرم ویرایش درخواست کار
     * @param int $id شناسه درخواست کار
     */
    public function edit($id) {
        // بررسی ورود کاربر
        $this->checkUserLogin();
        
        // تبدیل شناسه به عدد صحیح
        $id = (int)$id;
        
        // دریافت اطلاعات درخواست کار
        $ticket = $this->ticketModel->getTicketById($id);
        
        if (!$ticket) {
            $_SESSION['error'] = "درخواست کار مورد نظر یافت نشد.";
            header('Location: /support_system/tickets');
            exit;
        }
        
        // بررسی دسترسی کاربر به ویرایش این درخواست
        $userRole = $_SESSION['role_id'];
        $userId = $_SESSION['user_id'];
        
        if ($userRole != 1 && $ticket['user_id'] != $userId) {
            $_SESSION['error'] = "شما دسترسی به ویرایش این درخواست کار را ندارید.";
            header('Location: /support_system/tickets');
            exit;
        }
        
        // نمایش فرم ویرایش درخواست
        include __DIR__ . '/../views/tickets/edit.php';
    }

    /**
     * به‌روزرسانی درخواست کار
     * @param int $id شناسه درخواست کار
     */
    public function update($id) {
        // بررسی ورود کاربر
        $this->checkUserLogin();
        
        // بررسی متد درخواست
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error'] = "فرم با متد POST ارسال نشده است.";
            header('Location: /support_system/tickets/edit/' . $id);
            exit;
        }
        
        // تبدیل شناسه به عدد صحیح
        $id = (int)$id;
        
        // دریافت اطلاعات درخواست کار
        $ticket = $this->ticketModel->getTicketById($id);
        
        if (!$ticket) {
            $_SESSION['error'] = "درخواست کار مورد نظر یافت نشد.";
            header('Location: /support_system/tickets');
            exit;
        }
        
        // بررسی دسترسی کاربر به ویرایش این درخواست
        $userRole = $_SESSION['role_id'];
        $userId = $_SESSION['user_id'];
        
        if ($userRole != 1 && $ticket['user_id'] != $userId) {
            $_SESSION['error'] = "شما دسترسی به ویرایش این درخواست کار را ندارید.";
            header('Location: /support_system/tickets');
            exit;
        }
        
        // اعتبارسنجی داده‌های ورودی
        $title = $_POST['title'] ?? null;
        $problemType = $_POST['problem_type'] ?? null;
        $description = $_POST['description'] ?? null;
        $priority = $_POST['priority'] ?? null;
        
        if (!$title || !$problemType || !$description || !$priority) {
            $_SESSION['error'] = "اطلاعات فرم ناقص است.";
            $_SESSION['form_data'] = $_POST;
            header('Location: /support_system/tickets/edit/' . $id);
            exit;
        }
        
        // مدیریت آپلود فایل
        $filePath = $ticket['file_path']; // حفظ فایل قبلی به صورت پیش‌فرض
        
        if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../../public/uploads/tickets/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $fileName = time() . '_' . basename($_FILES['file']['name']);
            $uploadFile = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['file']['tmp_name'], $uploadFile)) {
                // حذف فایل قبلی اگر وجود داشته باشد
                if (!empty($ticket['file_path']) && file_exists($ticket['file_path'])) {
                    unlink($ticket['file_path']);
                }
                
                $filePath = $uploadFile;
            } else {
                $_SESSION['error'] = "خطا در آپلود فایل. لطفاً دوباره تلاش کنید.";
                $_SESSION['form_data'] = $_POST;
                header('Location: /support_system/tickets/edit/' . $id);
                exit;
            }
        } else if (isset($_FILES['file']['error']) && $_FILES['file']['error'] !== UPLOAD_ERR_NO_FILE) {
            $_SESSION['error'] = "خطا در آپلود فایل: " . $this->getFileUploadErrorMessage($_FILES['file']['error']);
            $_SESSION['form_data'] = $_POST;
            header('Location: /support_system/tickets/edit/' . $id);
            exit;
        }
        
        // آماده‌سازی داده‌ها برای به‌روزرسانی
        $ticketData = [
            'title' => $title,
            'problem_type' => $problemType,
            'description' => $description,
            'priority' => $priority,
            'file_path' => $filePath
        ];
        
        // به‌روزرسانی درخواست کار
        $result = $this->ticketModel->updateTicket($id, $ticketData);
        
        if ($result) {
            $_SESSION['success'] = "درخواست کار با موفقیت به‌روزرسانی شد.";
            header('Location: /support_system/tickets/view/' . $id);
        } else {
            $_SESSION['error'] = "خطا در به‌روزرسانی درخواست کار. لطفاً دوباره تلاش کنید.";
            $_SESSION['form_data'] = $_POST;
            header('Location: /support_system/tickets/edit/' . $id);
        }
        exit;
    }

    /**
     * افزودن پاسخ به درخواست کار
     */
    public function reply() {
        // بررسی ورود کاربر
        $this->checkUserLogin();
        
        // ثبت لاگ برای اشکال‌زدایی
        error_log("=== REPLY METHOD CALLED IN TICKETCONTROLLER ===");
        error_log("POST Data: " . print_r($_POST, true));
        error_log("Session Data: " . print_r($_SESSION, true));
        
        // بررسی داده‌های ورودی
        if (!isset($_POST['ticket_id']) || empty($_POST['ticket_id'])) {
            $_SESSION['error'] = 'شناسه درخواست کار ارسال نشده است.';
            header('Location: /support_system/tickets');
            exit;
        }
        
        if (!isset($_POST['content']) || empty($_POST['content'])) {
            $_SESSION['error'] = 'متن پاسخ ارسال نشده است.';
            header('Location: /support_system/tickets/view/' . $_POST['ticket_id']);
            exit;
        }
        
        // دریافت داده‌های ورودی
        $ticketId = intval($_POST['ticket_id']);
        $userId = $_SESSION['user_id'];
        $content = $_POST['content'];
        $isPrivate = isset($_POST['is_private']) ? 1 : 0;
        
        // بررسی وجود درخواست کار
        $ticket = $this->ticketModel->getTicketById($ticketId);
        
        if (!$ticket) {
            $_SESSION['error'] = 'درخواست کار مورد نظر پیدا نشد.';
            header('Location: /support_system/tickets');
            exit;
        }
        
        // بررسی دسترسی کاربر به افزودن پاسخ به این درخواست
        $userRole = $_SESSION['role_id'];
        
        if ($userRole != 1 && $userRole != 3 && $ticket['user_id'] != $userId && $ticket['assigned_to'] != $userId) {
            $_SESSION['error'] = 'شما دسترسی به افزودن پاسخ به این درخواست کار را ندارید.';
            header('Location: /support_system/tickets');
            exit;
        }
        
        // مدیریت آپلود فایل
        $filePath = null;
        
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../../public/uploads/tickets/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $fileName = time() . '_' . basename($_FILES['attachment']['name']);
            $uploadFile = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['attachment']['tmp_name'], $uploadFile)) {
                $filePath = $uploadFile;
            } else {
                $_SESSION['error'] = "خطا در آپلود فایل. لطفاً دوباره تلاش کنید.";
                header('Location: /support_system/tickets/view/' . $ticketId);
                exit;
            }
        }
        
        try {
            // شروع تراکنش
            $this->db->beginTransaction();
            
            // افزودن پاسخ جدید
            $replyId = $this->ticketModel->addReply($ticketId, $userId, $content, $isPrivate, $filePath);
            
            if (!$replyId) {
                throw new Exception('خطا در ثبت پاسخ.');
            }
            
            // به‌روزرسانی زمان آخرین به‌روزرسانی درخواست کار
            $updateResult = $this->ticketModel->updateTicketUpdatedAt($ticketId);
            
            if (!$updateResult) {
                throw new Exception('خطا در به‌روزرسانی درخواست کار.');
            }
            
            // اگر درخواست بسته شده بود، آن را به حالت در حال بررسی تغییر دهید
            if ($ticket['status'] === 'closed' || $ticket['status'] === 'resolved') {
                $oldStatus = $ticket['status'];
                $newStatus = 'in_progress';
                
                // به‌روزرسانی وضعیت درخواست کار
                $statusResult = $this->ticketModel->updateTicketStatus($ticketId, $newStatus, $oldStatus, $ticket['started_at'], $ticket['elapsed_time'] ?? 0);
                
                if (!$statusResult) {
                    throw new Exception('خطا در به‌روزرسانی وضعیت درخواست کار.');
                }
            }
            
            // تایید تراکنش
            $this->db->commit();
            
            $_SESSION['success'] = 'پاسخ با موفقیت ارسال شد.';
            header('Location: /support_system/tickets/view/' . $ticketId);
            exit;
        } catch (Exception $e) {
            // بازگشت تراکنش در صورت خطا
            $this->db->rollBack();
            
            error_log("Error in reply method: " . $e->getMessage());
            $_SESSION['error'] = 'خطا در ارسال پاسخ: ' . $e->getMessage();
            header('Location: /support_system/tickets/view/' . $ticketId);
            exit;
        }
    }

    /**
     * تغییر وضعیت درخواست کار
     */
    public function updateStatus() {
        // بررسی ورود کاربر
        $this->checkUserLogin();
        
        // ثبت لاگ برای اشکال‌زدایی
        error_log("=== updateStatus METHOD CALLED ===");
        error_log("POST Data: " . print_r($_POST, true));
        
        // دریافت داده‌های ارسالی
        $ticketId = isset($_POST['ticket_id']) ? (int)$_POST['ticket_id'] : 0;
        $newStatus = isset($_POST['status']) ? $_POST['status'] : '';
        $elapsedTime = isset($_POST['elapsed_time']) ? (int)$_POST['elapsed_time'] : null;
        
        // اعتبارسنجی داده‌ها
        if (empty($ticketId) || empty($newStatus)) {
            $_SESSION['error'] = "اطلاعات ناقص است.";
            header('Location: /support_system/tickets');
            exit;
        }
        
        // اعتبارسنجی وضعیت
        $validStatuses = ['open', 'in_progress', 'resolved', 'closed', 'waiting'];
        if (!in_array($newStatus, $validStatuses)) {
            $_SESSION['error'] = "وضعیت نامعتبر است.";
            header('Location: /support_system/tickets/view/' . $ticketId);
            exit;
        }
        
        // دریافت اطلاعات درخواست کار
        $ticket = $this->ticketModel->getTicketById($ticketId);
        
        if (!$ticket) {
            $_SESSION['error'] = "درخواست کار مورد نظر یافت نشد.";
            header('Location: /support_system/tickets');
            exit;
        }
        
        // بررسی دسترسی کاربر به تغییر وضعیت این درخواست
        $userRole = $_SESSION['role_id'];
        $userId = $_SESSION['user_id'];
        
        if ($userRole != 1 && $userRole != 3 && $ticket['assigned_to'] != $userId) {
            $_SESSION['error'] = "شما دسترسی به تغییر وضعیت این درخواست کار را ندارید.";
            header('Location: /support_system/tickets/view/' . $ticketId);
            exit;
        }
        
        try {
            // تغییر وضعیت درخواست کار
            $result = $this->ticketModel->updateTicketStatus($ticketId, $newStatus, $ticket['status'], $ticket['started_at'], $elapsedTime !== null ? $elapsedTime : $ticket['elapsed_time']);
            
            if (!$result) {
                throw new Exception('خطا در به‌روزرسانی وضعیت درخواست کار.');
            }
            
            // افزودن پاسخ اگر وجود داشته باشد
            if (!empty($_POST['message'])) {
                $replyId = $this->ticketModel->addReply($ticketId, $userId, $_POST['message'], isset($_POST['is_private']) ? 1 : 0);
                
                if (!$replyId) {
                    throw new Exception('خطا در ثبت پاسخ.');
                }
            }
            
            $_SESSION['success'] = "وضعیت درخواست با موفقیت به‌روزرسانی شد.";
            header('Location: /support_system/tickets/view/' . $ticketId);
            exit;
        } catch (Exception $e) {
            error_log("Error in updateStatus method: " . $e->getMessage());
            $_SESSION['error'] = 'خطا در به‌روزرسانی وضعیت درخواست: ' . $e->getMessage();
            header('Location: /support_system/tickets/view/' . $ticketId);
            exit;
        }
    }

    /**
     * به‌روزرسانی زمان صرف‌شده برای رسیدگی به درخواست
     */
    public function updateElapsedTime() {
        // بررسی ورود کاربر
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'لطفاً ابتدا وارد سیستم شوید.']);
            exit;
        }
        
        // دریافت داده‌های ارسالی
        $ticketId = isset($_POST['ticket_id']) ? (int)$_POST['ticket_id'] : 0;
        $elapsedTime = isset($_POST['elapsed_time']) ? (int)$_POST['elapsed_time'] : 0;
        
        // اعتبارسنجی داده‌ها
        if (empty($ticketId) || $elapsedTime < 0) {
            http_response_code(400);
            echo json_encode(['error' => 'اطلاعات نامعتبر است.']);
            exit;
        }
        
        try {
            // به‌روزرسانی زمان صرف‌شده
            $result = $this->ticketModel->updateElapsedTime($ticketId, $elapsedTime);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'زمان صرف‌شده با موفقیت به‌روزرسانی شد.']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'خطا در به‌روزرسانی زمان صرف‌شده.']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'خطا: ' . $e->getMessage()]);
        }
        
        exit;
    }

    /**
     * ارجاع درخواست به پشتیبان
     */
    public function refer() {
        // بررسی ورود کاربر
        $this->checkUserLogin();
        
        // بررسی داده‌های ورودی
        if (!isset($_POST['ticket_id']) || empty($_POST['ticket_id'])) {
            $_SESSION['error'] = 'شناسه درخواست کار ارسال نشده است.';
            header('Location: /support_system/tickets');
            exit;
        }
        
        if (!isset($_POST['assignee']) || empty($_POST['assignee'])) {
            $_SESSION['error'] = 'شناسه کاربر مقصد ارسال نشده است.';
            header('Location: /support_system/tickets/view/' . $_POST['ticket_id']);
            exit;
        }
        
        // دریافت داده‌های ورودی
        $ticketId = intval($_POST['ticket_id']);
        $assigneeId = intval($_POST['assignee']);
        $reason = isset($_POST['reason']) ? $_POST['reason'] : '';
        $userId = $_SESSION['user_id'];
        
        // بررسی دسترسی کاربر به ارجاع درخواست
        $userRole = $_SESSION['role_id'];
        
        if ($userRole != 1 && $userRole != 3) {
            $_SESSION['error'] = 'شما دسترسی به ارجاع درخواست کار را ندارید.';
            header('Location: /support_system/tickets/view/' . $ticketId);
            exit;
        }
        
        // بررسی وجود درخواست کار
        $ticket = $this->ticketModel->getTicketById($ticketId);
        
        if (!$ticket) {
            $_SESSION['error'] = 'درخواست کار مورد نظر پیدا نشد.';
            header('Location: /support_system/tickets');
            exit;
        }
        
        // بررسی وجود کاربر پشتیبان
        $supportUser = $this->userModel->getUserById($assigneeId);
        
        if (!$supportUser) {
            $_SESSION['error'] = 'کاربر پشتیبان مورد نظر پیدا نشد.';
            header('Location: /support_system/tickets/view/' . $ticketId);
            exit;
        }
        
        try {
            // ارجاع درخواست کار به پشتیبان
            $result = $this->ticketModel->assignTicket($ticketId, $assigneeId, $reason, $userId);
            
            if ($result) {
                $_SESSION['success'] = 'درخواست کار با موفقیت به ' . $supportUser['fullname'] . ' ارجاع داده شد.';
            } else {
                $_SESSION['error'] = 'خطا در ارجاع درخواست کار.';
            }
            
            header('Location: /support_system/tickets/view/' . $ticketId);
            exit;
        } catch (Exception $e) {
            $_SESSION['error'] = 'خطا در ارجاع درخواست کار: ' . $e->getMessage();
            header('Location: /support_system/tickets/view/' . $ticketId);
            exit;
        }
    }

    /**
     * دانلود فایل پیوست
     * @param int $ticketId شناسه درخواست کار
     */
    public function downloadAttachment($ticketId) {
        // بررسی ورود کاربر
        $this->checkUserLogin();
        
        // تبدیل شناسه به عدد صحیح
        $ticketId = (int)$ticketId;
        
        // اعتبارسنجی شناسه
        if ($ticketId <= 0) {
            http_response_code(400);
            die("شناسه درخواست نامعتبر است.");
        }
        
        try {
            // دریافت فایل‌های پیوست‌شده به درخواست
            $attachments = $this->ticketModel->getAttachmentsByTicketId($ticketId);
            
            // بررسی وجود فایل پیوست
            if (empty($attachments)) {
                http_response_code(404);
                die("هیچ فایلی برای این درخواست پیدا نشد.");
            }
            
            // دریافت اولین فایل پیوست (در حال حاضر فقط یک فایل پشتیبانی می‌شود)
            $attachment = $attachments[0];
            $filePath = $attachment['file_path'];
            
            // بررسی وجود فایل در مسیر
            if (!file_exists($filePath)) {
                http_response_code(404);
                die("فایل مورد نظر در سرور پیدا نشد.");
            }
            
            // تعیین نوع MIME فایل
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $filePath);
            finfo_close($finfo);
            
            // تنظیم هدرهای مناسب برای دانلود فایل
            header('Content-Description: File Transfer');
            header('Content-Type: ' . $mimeType);
            header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($filePath));
            
            // ارسال فایل به مرورگر
            readfile($filePath);
            exit;
        } catch (Exception $e) {
            http_response_code(500);
            die("خطا در دانلود فایل: " . $e->getMessage());
        }
    }

    /**
     * دریافت اطلاعات کاربر بر اساس شماره پرسنلی
     */
    public function getUserByEmployeeNumber() {
        // بررسی اینکه آیا شماره پرسنلی ارسال شده است
        if (!isset($_POST['employee_number']) || empty($_POST['employee_number'])) {
            echo json_encode(['error' => 'نام کاربری ارسال نشده است.']);
            exit;
        }
        
        // دریافت نام کاربری از درخواست
        $username = $_POST['employee_number'];
        
        try {
            // جستجوی کاربر بر اساس نام کاربری
            $user = $this->userModel->getUserByUsername($username);
            
            if ($user) {
                // ارسال اطلاعات کاربر به صورت JSON
                echo json_encode([
                    'fullname' => $user['fullname'],
                    'plant' => $user['plant'],
                    'unit' => $user['unit']
                ]);
            } else {
                // اگر کاربر پیدا نشد
                echo json_encode(['error' => 'کاربری با این نام کاربری یافت نشد.']);
            }
        } catch (Exception $e) {
            // در صورت بروز خطا
            echo json_encode(['error' => 'خطا در جستجوی کاربر: ' . $e->getMessage()]);
        }
        
        exit;
    }

    /**
     * ثبت امتیاز برای درخواست کار
     */
    public function rateTicket() {
        // بررسی ورود کاربر
        $this->checkUserLogin();
        
        // بررسی داده‌های ورودی
        if (!isset($_POST['ticket_id']) || empty($_POST['ticket_id'])) {
            $_SESSION['error'] = 'شناسه درخواست کار ارسال نشده است.';
            header('Location: /support_system/tickets');
            exit;
        }
        
        if (!isset($_POST['rating']) || !in_array($_POST['rating'], [1, 2, 3, 4, 5])) {
            $_SESSION['error'] = 'امتیاز نامعتبر است.';
            header('Location: /support_system/tickets/view/' . $_POST['ticket_id']);
            exit;
        }
        
        // دریافت داده‌های ورودی
        $ticketId = intval($_POST['ticket_id']);
        $rating = intval($_POST['rating']);
        $comment = isset($_POST['comment']) ? $_POST['comment'] : '';
        $userId = $_SESSION['user_id'];
        
        // بررسی وجود درخواست کار
        $ticket = $this->ticketModel->getTicketById($ticketId);
        
        if (!$ticket) {
            $_SESSION['error'] = 'درخواست کار مورد نظر پیدا نشد.';
            header('Location: /support_system/tickets');
            exit;
        }
        
        // بررسی دسترسی کاربر به ثبت امتیاز برای این درخواست
        if ($ticket['user_id'] != $userId) {
            $_SESSION['error'] = 'فقط ایجادکننده درخواست می‌تواند امتیاز ثبت کند.';
            header('Location: /support_system/tickets/view/' . $ticketId);
            exit;
        }
        
        // بررسی وضعیت درخواست
        if ($ticket['status'] !== 'closed' && $ticket['status'] !== 'resolved') {
            $_SESSION['error'] = 'فقط برای درخواست‌های بسته‌شده یا حل‌شده می‌توان امتیاز ثبت کرد.';
            header('Location: /support_system/tickets/view/' . $ticketId);
            exit;
        }
        
        try {
            // ثبت امتیاز
            $result = $this->ticketModel->rateTicket($ticketId, $rating, $comment);
            
            if ($result) {
                $_SESSION['success'] = 'امتیاز شما با موفقیت ثبت شد.';
            } else {
                $_SESSION['error'] = 'خطا در ثبت امتیاز.';
            }
            
            header('Location: /support_system/tickets/view/' . $ticketId);
            exit;
        } catch (Exception $e) {
            $_SESSION['error'] = 'خطا در ثبت امتیاز: ' . $e->getMessage();
            header('Location: /support_system/tickets/view/' . $ticketId);
            exit;
        }
    }

    /**
     * گزارش‌های درخواست‌های کار
     */
    public function reports() {
        // بررسی ورود کاربر
        $this->checkUserLogin();
        
        // بررسی دسترسی کاربر به گزارش‌ها
        $userRole = $_SESSION['role_id'];
        
        if ($userRole != 1) {
            $_SESSION['error'] = 'شما دسترسی به این بخش را ندارید.';
            header('Location: /support_system/dashboard');
            exit;
        }
        
        // دریافت نوع گزارش
        $reportType = isset($_GET['type']) ? $_GET['type'] : 'all';
        
        // دریافت پارامترهای فیلتر
        $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
        $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
        
        // دریافت داده‌های گزارش
        $reportData = [];
        
        switch ($reportType) {
            case 'by_status':
                $reportData = $this->ticketModel->getTicketsByStatus($startDate, $endDate);
                break;
                
            case 'by_priority':
                $reportData = $this->ticketModel->getTicketsByPriority($startDate, $endDate);
                break;
                
            case 'by_problem_type':
                $reportData = $this->ticketModel->getTicketsByProblemType($startDate, $endDate);
                break;
                
            case 'by_plant':
                $reportData = $this->ticketModel->getTicketsByPlant($startDate, $endDate);
                break;
                
            case 'by_unit':
                $reportData = $this->ticketModel->getTicketsByUnit($startDate, $endDate);
                break;
                
            case 'by_support':
                $reportData = $this->ticketModel->getTicketsBySupport($startDate, $endDate);
                break;
                
            case 'response_time':
                $reportData = $this->ticketModel->getAverageResponseTime($startDate, $endDate);
                break;
                
            case 'resolution_time':
                $reportData = $this->ticketModel->getAverageResolutionTime($startDate, $endDate);
                break;
                
            default:
                $reportData = $this->ticketModel->getAllTicketsReport($startDate, $endDate);
                break;
        }
        
        // نمایش صفحه گزارش‌ها
        include __DIR__ . '/../views/tickets/reports.php';
    }

    /**
     * ترجمه کدهای خطای آپلود فایل
     * @param int $errorCode کد خطا
     * @return string پیام خطا
     */
    private function getFileUploadErrorMessage($errorCode) {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'اندازه فایل از حد مجاز در تنظیمات سرور بیشتر است.',
            UPLOAD_ERR_FORM_SIZE => 'اندازه فایل از حد مجاز در فرم بیشتر است.',
            UPLOAD_ERR_PARTIAL => 'فایل به صورت ناقص آپلود شد.',
            UPLOAD_ERR_NO_FILE => 'هیچ فایلی انتخاب نشده است.',
            UPLOAD_ERR_NO_TMP_DIR => 'پوشه موقت برای آپلود فایل وجود ندارد.',
            UPLOAD_ERR_CANT_WRITE => 'خطا در نوشتن فایل روی دیسک.',
            UPLOAD_ERR_EXTENSION => 'آپلود فایل به دلیل یک افزونه PHP متوقف شد.'
        ];
        
        return $errors[$errorCode] ?? 'خطای ناشناخته در آپلود فایل.';
    }

    /**
     * نمایش آمار و گزارش‌های داشبورد
     */
    public function dashboard() {
        // بررسی ورود کاربر
        $this->checkUserLogin();
        
        $userId = $_SESSION['user_id'];
        $userRole = $_SESSION['role_id'];
        
        // آمار مختلف بر اساس نقش کاربر
        if ($userRole == 1) { // مدیر
            // آمار کلی درخواست‌ها
            $openTickets = $this->ticketModel->getTicketsCountByStatus('open');
            $inProgressTickets = $this->ticketModel->getTicketsCountByStatus('in_progress');
            $waitingTickets = $this->ticketModel->getTicketsCountByStatus('waiting');
            $resolvedTickets = $this->ticketModel->getTicketsCountByStatus('resolved');
            $closedTickets = $this->ticketModel->getTicketsCountByStatus('closed');
            
            // آمار درخواست‌ها بر اساس اولویت
            $highPriorityTickets = $this->ticketModel->getTicketsCountByPriority('high');
            $mediumPriorityTickets = $this->ticketModel->getTicketsCountByPriority('medium');
            $lowPriorityTickets = $this->ticketModel->getTicketsCountByPriority('low');
            
            // آمار درخواست‌های معوق
            $overdueTickets = $this->ticketModel->getOverdueTicketsCount();
            
            // میانگین زمان پاسخگویی
            $averageResponseTime = $this->ticketModel->getAverageResponseTime();
            
            // میانگین زمان حل مشکل
            $averageResolutionTime = $this->ticketModel->getAverageResolutionTime();
            
            // درخواست‌های اخیر
            $recentTickets = $this->ticketModel->getRecentTickets(10);
            
            // نمایش داشبورد مدیر
            include __DIR__ . '/../views/dashboard_admin.php';
        } elseif ($userRole == 3) { // پشتیبان
            // آمار درخواست‌های پشتیبان
            $openTickets = $this->ticketModel->getTicketsCountByStatusAndSupport('open', $userId);
            $inProgressTickets = $this->ticketModel->getTicketsCountByStatusAndSupport('in_progress', $userId);
            $waitingTickets = $this->ticketModel->getTicketsCountByStatusAndSupport('waiting', $userId);
            $resolvedTickets = $this->ticketModel->getTicketsCountByStatusAndSupport('resolved', $userId);
            $closedTickets = $this->ticketModel->getTicketsCountByStatusAndSupport('closed', $userId);
            
            // آمار درخواست‌های معوق پشتیبان
            $overdueTickets = $this->ticketModel->getOverdueTicketsCountBySupport($userId);
            
            // درخواست‌های اخیر پشتیبان
            $recentTickets = $this->ticketModel->getRecentTicketsBySupport($userId, 10);
            
            // نمایش داشبورد پشتیبان
            include __DIR__ . '/../views/dashboard_support.php';
        } else { // کاربر عادی
            // آمار درخواست‌های کاربر
            $openTickets = $this->ticketModel->getTicketsCountByStatusAndUser('open', $userId);
            $inProgressTickets = $this->ticketModel->getTicketsCountByStatusAndUser('in_progress', $userId);
            $waitingTickets = $this->ticketModel->getTicketsCountByStatusAndUser('waiting', $userId);
            $resolvedTickets = $this->ticketModel->getTicketsCountByStatusAndUser('resolved', $userId);
            $closedTickets = $this->ticketModel->getTicketsCountByStatusAndUser('closed', $userId);
            
            // درخواست‌های اخیر کاربر
            $recentTickets = $this->ticketModel->getRecentTicketsByUser($userId, 10);
            
            // نمایش داشبورد کاربر
            include __DIR__ . '/../views/dashboard_user.php';
        }
    }

    /**
     * حذف درخواست کار
     * @param int $id شناسه درخواست کار
     */
    public function delete($id) {
        // بررسی ورود کاربر
        $this->checkUserLogin();
        
        // تبدیل شناسه به عدد صحیح
        $id = (int)$id;
        
        // بررسی دسترسی کاربر به حذف درخواست
        $userRole = $_SESSION['role_id'];
        
        if ($userRole != 1) {
            $_SESSION['error'] = 'شما دسترسی به حذف درخواست کار را ندارید.';
            header('Location: /support_system/tickets');
            exit;
        }
        
        // دریافت اطلاعات درخواست کار
        $ticket = $this->ticketModel->getTicketById($id);
        
        if (!$ticket) {
            $_SESSION['error'] = 'درخواست کار مورد نظر یافت نشد.';
            header('Location: /support_system/tickets');
            exit;
        }
        
        try {
            // حذف درخواست کار
            $result = $this->ticketModel->deleteTicket($id);
            
            if ($result) {
                $_SESSION['success'] = 'درخواست کار با موفقیت حذف شد.';
            } else {
                $_SESSION['error'] = 'خطا در حذف درخواست کار.';
            }
            
            header('Location: /support_system/tickets');
            exit;
        } catch (Exception $e) {
            $_SESSION['error'] = 'خطا در حذف درخواست کار: ' . $e->getMessage();
            header('Location: /support_system/tickets');
            exit;
        }
    }

    /**
     * نمایش تاریخچه تغییرات درخواست کار
     * @param int $id شناسه درخواست کار
     */
    public function history($id) {
        // بررسی ورود کاربر
        $this->checkUserLogin();
        
        // تبدیل شناسه به عدد صحیح
        $id = (int)$id;
        
        // دریافت اطلاعات درخواست کار
        $ticket = $this->ticketModel->getTicketById($id);
        
        if (!$ticket) {
            $_SESSION['error'] = 'درخواست کار مورد نظر یافت نشد.';
            header('Location: /support_system/tickets');
            exit;
        }
        
        // بررسی دسترسی کاربر به مشاهده تاریخچه این درخواست
        $userRole = $_SESSION['role_id'];
        $userId = $_SESSION['user_id'];
        
        if ($userRole != 1 && $userRole != 3 && $ticket['user_id'] != $userId) {
            $_SESSION['error'] = 'شما دسترسی به مشاهده تاریخچه این درخواست کار را ندارید.';
            header('Location: /support_system/tickets');
            exit;
        }
        
        // دریافت تاریخچه تغییرات وضعیت
        $statusHistory = $this->ticketModel->getTicketStatusHistory($id);
        
        // دریافت تاریخچه ارجاعات
        $assignmentHistory = $this->ticketModel->getTicketAssignmentHistory($id);
        
        // دریافت تاریخچه پاسخ‌ها
        $replyHistory = $this->ticketModel->getTicketReplies($id);
        
        // تبدیل تاریخ‌های میلادی به شمسی
        foreach ($statusHistory as &$status) {
            $status['changed_at_jalali'] = (new Verta($status['changed_at']))->format('Y/m/d H:i:s');
        }
        
        foreach ($assignmentHistory as &$assignment) {
            $assignment['assigned_at_jalali'] = (new Verta($assignment['assigned_at']))->format('Y/m/d H:i:s');
        }
        
        foreach ($replyHistory as &$reply) {
            $reply['created_at_jalali'] = (new Verta($reply['created_at']))->format('Y/m/d H:i:s');
        }
        
        // نمایش صفحه تاریخچه
        include __DIR__ . '/../views/tickets/history.php';
    }

    /**
     * دریافت لیست درخواست‌های کار
     */
    public function listTickets() {
        // بررسی ورود کاربر
        $this->checkUserLogin();
        
        // ریدایرکت به متد search
        $this->search();
    }
}