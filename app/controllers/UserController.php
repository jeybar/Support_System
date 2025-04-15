<?php

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Role.php';
require_once __DIR__ . '/../helpers/AccessControl.php';

class UserController {
    private $userModel; // تعریف متغیر userModel

    public function __construct() {
        $this->userModel = new User(); // مقداردهی userModel
    }

    public function index() {
        AccessControl::requirePermission('manage_users'); // بررسی دسترسی

        $this->listUsers();
    }

    // متد برای بررسی تکمیل بودن پروفایل
    private function checkProfileCompletion() {
        // بررسی اینکه کاربر وارد شده است یا خیر
        if (!isset($_SESSION['user_id'])) {
            header('Location: /support_system/login');
            exit;
        }
    
        // نمونه‌سازی از مدل User
        $userModel = new User();
    
        // بررسی تکمیل بودن پروفایل
        if (!$userModel->isProfileComplete($_SESSION['user_id'])) {
            // تنظیم پیام خطا
            $_SESSION['error'] = "لطفاً پروفایل خود را تکمیل کنید.";
    
            // هدایت به صفحه پروفایل
            header('Location: /support_system/profile');
            exit;
        }
    }

    // متد به‌روزرسانی نقش کاربر
    public function updateUserRole() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $userId = $_POST['user_id'];
            $newRoleId = $_POST['role_id'];

            require_once __DIR__ . '/../models/User.php';
            $userModel = new User();

            // دریافت اطلاعات کاربر
            $user = $userModel->getUserById($userId);
            if ($user['user_type'] === 'کاربر شبکه') {
                die("نقش کاربران شبکه قابل تغییر نیست.");
            }

            // تغییر نقش کاربر لوکال
            $result = $userModel->updateRole($userId, $newRoleId);
            if ($result) {
                $_SESSION['success'] = "نقش کاربر با موفقیت تغییر یافت.";
                header('Location: /users');
                exit;
            } else {
                $_SESSION['error'] = "خطا در تغییر نقش کاربر.";
                header('Location: /users');
                exit;
            }
        }
    }

    // متد لیست کاربران
    public function listUsers() {
        // بررسی تکمیل بودن پروفایل
        $this->checkProfileCompletion();

        // بررسی دسترسی
        AccessControl::requirePermission('manage_users');

        // دریافت مقدار page از پارامتر GET
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

        // دریافت مقدار records_per_page از پارامتر GET (پیش‌فرض: 10)
        $limit = isset($_GET['records_per_page']) ? (int)$_GET['records_per_page'] : 10;

        // محاسبه offset برای صفحه‌بندی
        $offset = ($page - 1) * $limit;

        // دریافت پارامترهای مرتب‌سازی
        $sortBy = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'username'; // پیش‌فرض: مرتب‌سازی بر اساس نام کاربری
        $order = isset($_GET['order']) ? $_GET['order'] : 'asc'; // پیش‌فرض: صعودی

        // ساخت آرایه فیلترها
        $filters = [
            'username' => $_GET['username'] ?? '',
            'fullname' => $_GET['fullname'] ?? '',
            'role' => $_GET['role'] ?? '',
            'user_type' => $_GET['user_type'] ?? '',
            'status' => $_GET['status'] ?? '',
            'created_date' => $_GET['created_date'] ?? ''
        ];

        // بررسی اینکه آیا جستجو فعال است
        $isSearchActive = false;
        foreach ($filters as $value) {
            if (!empty($value) || $value === '0') {
                $isSearchActive = true;
                break;
            }
        }

        // دریافت کاربران با اعمال فیلترها
        $users = $this->userModel->getFilteredUsers($filters, $limit, $offset, $sortBy, $order);
        
        // دریافت تعداد کل کاربران با همان فیلترها
        $totalCount = $this->userModel->getFilteredUserCount($filters);

  
        
        $totalPages = ceil($totalCount / $limit);

        // دریافت لیست نقش‌ها از مدل Role
        $roleModel = new Role();
        $roles = $roleModel->getAllRolesWithoutLimit(); // دریافت لیست کامل نقش‌ها

        // ارسال داده‌ها به ویو
        require_once __DIR__ . '/../views/users/index.php';
    }

    // متد افزودن کاربر جدید
    public function addUser() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // پردازش فرم و افزودن کاربر
            $username = htmlspecialchars(trim($_POST['username'] ?? ''));
            $password = trim($_POST['password'] ?? '');
            $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
            $fullname = htmlspecialchars(trim($_POST['fullname'] ?? ''));
            $roleId = (int) ($_POST['role_id'] ?? 0);
            $phone = $_POST['phone'] ?? '';
            $mobile = $_POST['mobile'] ?? '';
            $plant = $_POST['plant'] ?? '';
            $unit = $_POST['unit'] ?? '';
            $userType = $_POST['user_type'] ?? 'local'; // نوع کاربر (پیش‌فرض: local)
    
            // مقدار پیش‌فرض برای تغییر رمز ورود در اولین ورود
            $forcePasswordChange = 0;
    
            // اگر کاربر محلی باشد، مقدار چک‌باکس تغییر رمز ورود اعمال می‌شود
            if ($userType === 'local') {
                $forcePasswordChange = isset($_POST['force_password_change']) ? 1 : 0;
            }
    
            // نمونه‌سازی از مدل User
            $userModel = new User();
    
            // فراخوانی متد createUser از مدل User
            $result = $userModel->createUser($username, $password, $email, $fullname, $roleId, $phone, $mobile, $plant, $unit, $userType, $forcePasswordChange);
    
            if ($result) {
                $_SESSION['success'] = "کاربر جدید با موفقیت ایجاد شد.";
            } else {
                $_SESSION['error'] = "خطا در ایجاد کاربر.";
            }
            header('Location: /users');
            exit;
    
        } else {
            // اگر درخواست GET باشد، به صفحه اصلی بازگردید
            header('Location: /users');
            exit;
        }
    }

    // متد جستجو
    public function search() {
        // بررسی اینکه آیا حداقل یک فیلتر فعال است
        $filters = [
            'username' => $_GET['username'] ?? '',
            'fullname' => $_GET['fullname'] ?? '',
            'role' => $_GET['role'] ?? '',
            'user_type' => $_GET['user_type'] ?? '',
            'status' => $_GET['status'] ?? '',
            'created_date' => $_GET['created_date'] ?? ''
        ];
    
        $hasActiveFilter = false;
        foreach ($filters as $value) {
            if (!empty($value) || $value === '0') {
                $hasActiveFilter = true;
                break;
            }
        }
    
        if (!$hasActiveFilter) {
            // اگر هیچ فیلتری فعال نباشد، پیام خطا نمایش داده می‌شود
            $_SESSION['error'] = "لطفاً حداقل یک معیار جستجو را وارد کنید.";
            header('Location: /support_system/users');
            exit;
        }
    
        // فراخوانی متد listUsers برای نمایش نتایج
        $this->listUsers();
    }
    

    //اضافه کردن متد فعال/غیرفعال کردن کاربر
    public function toggleStatus($id) {
        $isActive = $_POST['is_active'] ?? 0;
    
        // تغییر وضعیت کاربر
        $result = $this->userModel->toggleUserStatus($id, $isActive);
    
        // بررسی نتیجه عملیات
        if ($result) {
            $_SESSION['success'] = 'وضعیت کاربر با موفقیت تغییر یافت.';
        } else {
            $_SESSION['error'] = 'خطا در تغییر وضعیت کاربر.';
        }
    
        // بازگرداندن کاربر به صفحه قبلی همراه با فیلترها و تنظیمات
        $queryParams = [
            'user_type' => $_POST['user_type'] ?? '',
            'keyword' => $_POST['keyword'] ?? '',
            'sort_by' => $_POST['sort_by'] ?? 'id',
            'order' => $_POST['order'] ?? 'asc',
            'records_per_page' => $_POST['records_per_page'] ?? 10,
            'page' => $_POST['page'] ?? 1,
        ];
    
        // ساخت URL با پارامترهای GET
        $redirectUrl = '/users?' . http_build_query($queryParams);
    
        // هدایت به URL ساخته‌شده
        header("Location: $redirectUrl");
        exit;
    }

    //متد edit
    public function edit($id) {
        $userModel = new User();
        $roleModel = new Role();
    
        // بازیابی اطلاعات کاربر بر اساس شناسه
        $user = $userModel->getUserById($id);
        if (!$user) {
            header('HTTP/1.0 404 Not Found');
            echo 'کاربر مورد نظر پیدا نشد.';
            exit;
        }
    
        // دریافت تمامی نقش‌ها
        $roles = $roleModel->getAllRolesWithoutLimit();
    
        // ارسال اطلاعات کاربر و نقش‌ها به ویو
        include __DIR__ . '/../views/users/edit.php';
    }

    // ارسال اطلاعات کاربر به صورت JSON (برای استفاده در مودال)
    public function getUser($id) {
        // نمونه‌سازی از مدل User
        $userModel = new User();
    
        // بازیابی اطلاعات کاربر بر اساس شناسه
        $user = $userModel->getUserById($id);
    
        if (!$user) {
            // اگر کاربر پیدا نشد
            header('HTTP/1.0 404 Not Found');
            echo json_encode(['error' => 'کاربر مورد نظر پیدا نشد.']);
            exit;
        }
    
        // ارسال اطلاعات کاربر به صورت JSON
        header('Content-Type: application/json');
        echo json_encode($user);
        exit;
    }

    //ذخیره اطلاعات ویرایش‌شده کاربر
    public function updateUserDetails() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $userId = $_POST['user_id'];
            $username = $_POST['username'];
            $fullname = $_POST['fullname'];
            $roleId = $_POST['role_id'];
            $userType = $_POST['user_type'];
    
            // نمونه‌سازی از مدل User
            $userModel = new User();
    
            // دریافت اطلاعات فعلی کاربر از پایگاه داده
            $currentUser = $userModel->getUserById($userId);
    
            // بررسی تغییرات
            $isChanged = false;
            if ($currentUser['username'] !== $username) $isChanged = true;
            if ($currentUser['fullname'] !== $fullname) $isChanged = true;
            if ($currentUser['role_id'] != $roleId) $isChanged = true;
            if ($currentUser['user_type'] !== $userType) $isChanged = true;
    
            if (!$isChanged) {
                $_SESSION['error'] = 'اطلاعاتی برای تغییر وجود ندارد.';
                header('Location: /users');
                exit;
            }
    
            // به‌روزرسانی اطلاعات کاربر
            $result = $userModel->updateUserDetails($userId, [
                'username' => $username,
                'fullname' => $fullname,
                'role_id' => $roleId,
                'user_type' => $userType,
                'is_active' => $currentUser['is_active'], // حفظ مقدار فعلی is_active
            ]);
    
            if ($result) {
                $_SESSION['success'] = 'اطلاعات کاربر با موفقیت به‌روزرسانی شد.';
            } else {
                $_SESSION['error'] = 'خطا در به‌روزرسانی اطلاعات کاربر.';
            }
    
            header('Location: /users');
            exit;
        }
    }

    public function getUserDetails($id) {
        // نمونه‌سازی از مدل User
        $userModel = new User();
    
        // دریافت اطلاعات کاربر با استفاده از متد getUserById
        $user = $userModel->getUserById($id);
    
        if (!$user) {
            // اگر کاربر پیدا نشد
            header('HTTP/1.0 404 Not Found');
            echo json_encode(['error' => 'کاربر مورد نظر پیدا نشد.']);
            exit;
        }
    
        // ارسال اطلاعات کاربر به صورت JSON
        header('Content-Type: application/json');
        echo json_encode([
            'id' => $user['id'],
            'username' => $user['username'],
            'fullname' => $user['fullname'],
            'role_id' => $user['role_id'],
            'user_type' => $user['user_type']
        ]);
        exit;
    }

    //متد برای حذف کاربر 
    public function delete($id) {
        AccessControl::requirePermission('manage_users'); // بررسی دسترسی

        // بررسی وجود شناسه کاربر
        if (empty($id)) {
            $_SESSION['error'] = "شناسه کاربر نامعتبر است.";
            header('Location: /users');
            exit;
        }
    
        // حذف کاربر
        $result = $this->userModel->deleteUser($id);
    
        if ($result) {
            $_SESSION['success'] = "کاربر با موفقیت حذف شد.";
        } else {
            $_SESSION['error'] = "خطا در حذف کاربر.";
        }
    
        // هدایت به صفحه لیست کاربران
        header('Location: /users');
        exit;
    }

    // دریافت کاربران با نقش پشتیبان
    public function getSupportStaff() {
        error_log("=== getSupportStaff method called ===");
        
        try {
            // فرض بر این است که شناسه نقش پشتیبان 3 است
            $supportStaff = $this->userModel->getUsersByRoleId(3);
            
            error_log("Support staff found: " . count($supportStaff));
            error_log("Support staff data: " . print_r($supportStaff, true));
            
            header('Content-Type: application/json');
            echo json_encode($supportStaff);
        } catch (Exception $e) {
            error_log("Error in getSupportStaff: " . $e->getMessage());
            
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(['error' => 'خطا در دریافت لیست پشتیبان‌ها: ' . $e->getMessage()]);
        }
        
        exit;
    }

    /**
     * جستجوی کاربر با نام کاربری (شماره پرسنلی)
     * این متد یک API است که اطلاعات کاربر را بر اساس نام کاربری برمی‌گرداند
     */
    public function searchUserByUsername() {
        header('Content-Type: application/json');
        
        try {
            // بررسی وجود پارامتر
            if (!isset($_GET['username']) || empty(trim($_GET['username']))) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'نام کاربری الزامی است'
                ]);
                exit;
            }

            $username = trim($_GET['username']);
            error_log("Searching for username: " . $username);

            // دریافت کاربر با نام کاربری
            $user = $this->userModel->getUserByUsername($username);
            
            if (!$user) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => 'کاربری با این نام کاربری یافت نشد'
                ]);
                exit;
            }

            // دریافت اطلاعات تجهیزات اختصاص داده شده به کاربر (اگر نیاز است)
            $assignedAssets = []; // این قسمت را می‌توانید با کد واقعی جایگزین کنید

            // ساخت پاسخ
            $response = [
                'success' => true,
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'fullname' => $user['fullname'],
                    'email' => $user['email'],
                    'phone' => $user['phone'],
                    'mobile' => $user['mobile'],
                    'plant' => $user['plant'],
                    'unit' => $user['unit'],
                    'is_active' => $user['is_active'],
                    'role_name' => $user['role_name'] ?? null
                ],
                'assignedAssets' => $assignedAssets
            ];

            error_log("User found: " . print_r($response, true));
            echo json_encode($response);
            exit;

        } catch (Exception $e) {
            error_log("Exception in searchUserByUsername: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'خطای سرور در پردازش درخواست'
            ]);
            exit;
        }
    }

    /**
     * ارسال پاسخ JSON
     * 
     * @param array $data داده‌های پاسخ
     * @param int $statusCode کد وضعیت HTTP
     */
    private function sendJsonResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

}