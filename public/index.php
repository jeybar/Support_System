<?php

// فعال کردن نمایش خطاها
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ثبت لاگ برای اشکال‌زدایی
error_log("=== NEW REQUEST ===");
error_log("Request Method: " . $_SERVER['REQUEST_METHOD']);
error_log("Request URI: " . $_SERVER['REQUEST_URI']);
error_log("POST Data: " . print_r($_POST, true));
error_log("GET Data: " . print_r($_GET, true));
error_log("Session Data: " . (isset($_SESSION) ? print_r($_SESSION, true) : "No Session"));
error_log("=== END REQUEST INFO ===");

// پردازش مسیر درخواست
error_log("=== PROCESSING REQUEST ===");
error_log("Original Request URI: " . $_SERVER['REQUEST_URI']);
error_log("Request Method: " . $_SERVER['REQUEST_METHOD']);


ob_start();

require_once __DIR__ . '/../app/controllers/UserController.php';
require_once __DIR__ . '/../app/controllers/ProfileController.php';
require_once __DIR__ . '/../app/controllers/LoginController.php';
require_once __DIR__ . '/../app/controllers/TicketController.php'; // اضافه کردن کنترلر درخواست کار‌ها
require_once __DIR__ . '/../app/controllers/RoleController.php';
require_once __DIR__ . '/../app/controllers/PermissionController.php';
require_once __DIR__ . '/../app/controllers/DashboardController.php'; // اضافه کردن کنترلر داشبورد
require_once __DIR__ . '/../app/controllers/AssetController.php'; // اضافه کردن کنترلر تجهیز‌ها
require_once __DIR__ . '/../app/controllers/AssetCategoryController.php'; // اضافه کردن کنترلر دسته‌بندی تجهیز‌ها
require_once __DIR__ . '/../app/controllers/AssetModelController.php'; // اضافه کردن کنترلر مدل‌های تجهیز
require_once __DIR__ . '/../app/controllers/MaintenanceController.php'; // اضافه کردن کنترلر سرویس‌های ادواری
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

// مقداردهی کنترلر
$userController = new UserController();

date_default_timezone_set('Asia/Tehran');

// فعال کردن نمایش خطاها
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// شروع جلسه (Session)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// دریافت URL درخواست‌شده
$request = $_SERVER['REQUEST_URI'];

// حذف مسیر اضافی (در صورتی که پروژه در زیرپوشه‌ای قرار دارد)
$base_path = '/support_system';
$request = str_replace($base_path, '', $request);

error_log("Base path: " . $base_path);
error_log("Processed request path: " . $request);


// مدیریت مسیرها

// ثبت لاگ برای اشکال‌زدایی
error_log("Request URI in index.php: " . $request);
error_log("GET Params in index.php: " . print_r($_GET, true));

// مسیر API برای دریافت تجهیز‌های کاربر
if ($request === '/assets/user_assets' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    error_log("=== USER ASSETS API ROUTE MATCHED ===");
    $controller = new AssetController();
    $controller->getUserAssets();
    exit;
}

// مسیر API برای جستجوی کاربر با نام کاربری
if (strpos($request, '/assets/searchUserByUsername') === 0 && isset($_GET['username'])) {
    error_log("=== SEARCH USER BY USERNAME API ROUTE MATCHED ===");
    error_log("Username search term: " . $_GET['username']);
    
    // اطمینان از وجود کلاس AssetController
    if (!class_exists('AssetController')) {
        require_once __DIR__ . '/../app/controllers/AssetController.php';
    }
    
    $controller = new AssetController();
    
    // بررسی وجود متد searchUserByUsername
    if (method_exists($controller, 'searchUserByUsername')) {
        $controller->searchUserByUsername();
    } else {
        error_log("Method searchUserByUsername does not exist in AssetController");
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(['success' => false, 'error' => 'Method not implemented']);
    }
    exit;
}

// مسیر API برای جستجوی کاربر با شماره پرسنلی
if (strpos($request, '/assets/searchUserByEmployeeNumber') === 0 && isset($_GET['employee_number'])) {
    error_log("=== SEARCH USER BY EMPLOYEE NUMBER API ROUTE MATCHED ===");
    error_log("Employee number search term: " . $_GET['employee_number']);
    
    // اطمینان از وجود کلاس AssetController
    if (!class_exists('AssetController')) {
        require_once __DIR__ . '/../app/controllers/AssetController.php';
    }
    
    $controller = new AssetController();
    
    // بررسی وجود متد searchUserByEmployeeNumber
    if (method_exists($controller, 'searchUserByEmployeeNumber')) {
        $controller->searchUserByEmployeeNumber();
    } else {
        error_log("Method searchUserByEmployeeNumber does not exist in AssetController");
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(['success' => false, 'error' => 'Method not implemented']);
    }
    exit;
}

// مسیرهای مربوط به دسته‌بندی‌های تجهیز
if ($request === '/asset_categories') {
    $controller = new AssetCategoryController();
    $controller->index();
    exit;
}

if ($request === '/asset_categories/create') {
    $controller = new AssetCategoryController();
    $controller->create();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $request === '/asset_categories/store') {
    $controller = new AssetCategoryController();
    $controller->store();
    exit;
}

if (preg_match('/^\/asset_categories\/edit\/(\d+)$/', $request, $matches)) {
    $controller = new AssetCategoryController();
    $controller->edit($matches[1]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && preg_match('/^\/asset_categories\/update\/(\d+)$/', $request, $matches)) {
    $controller = new AssetCategoryController();
    $controller->update($matches[1]);
    exit;
}

if (preg_match('/^\/asset_categories\/delete\/(\d+)$/', $request, $matches)) {
    $controller = new AssetCategoryController();
    $controller->delete($matches[1]);
    exit;
}

// مسیرهای مربوط به مدل‌های تجهیز
if ($request === '/asset_models') {
    $controller = new AssetModelController();
    $controller->index();
    exit;
}

if ($request === '/asset_models/create') {
    $controller = new AssetModelController();
    $controller->create();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $request === '/asset_models/store') {
    $controller = new AssetModelController();
    $controller->store();
    exit;
}

if (preg_match('/^\/asset_models\/edit\/(\d+)$/', $request, $matches)) {
    $controller = new AssetModelController();
    $controller->edit($matches[1]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && preg_match('/^\/asset_models\/update\/(\d+)$/', $request, $matches)) {
    $controller = new AssetModelController();
    $controller->update($matches[1]);
    exit;
}

if (preg_match('/^\/asset_models\/delete\/(\d+)$/', $request, $matches)) {
    $controller = new AssetModelController();
    $controller->delete($matches[1]);
    exit;
}

if ($request === '/asset_models/get_by_category') {
    $controller = new AssetModelController();
    $controller->getModelsByCategory();
    exit;
}

// مسیرهای مربوط به تجهیز‌ها
if ($request === '/assets') {
    $controller = new AssetController();
    $controller->index();
    exit;
}

if ($request === '/assets/create') {
    $controller = new AssetController();
    $controller->create();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $request === '/assets/store') {
    $controller = new AssetController();
    $controller->store();
    exit;
}

if (preg_match('/^\/assets\/show\/(\d+)$/', $request, $matches)) {
    $controller = new AssetController();
    $controller->show($matches[1]);
    exit;
}

if (preg_match('/^\/assets\/edit\/(\d+)$/', $request, $matches)) {
    $controller = new AssetController();
    $controller->edit($matches[1]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && preg_match('/^\/assets\/update\/(\d+)$/', $request, $matches)) {
    $controller = new AssetController();
    $controller->update($matches[1]);
    exit;
}

if (preg_match('/^\/assets\/delete\/(\d+)$/', $request, $matches)) {
    $controller = new AssetController();
    $controller->delete($matches[1]);
    exit;
}

if (preg_match('/^\/assets\/assign\/(\d+)$/', $request, $matches)) {
    $controller = new AssetController();
    $controller->assign($matches[1]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && preg_match('/^\/assets\/save_assignment\/(\d+)$/', $request, $matches)) {
    $controller = new AssetController();
    $controller->saveAssignment($matches[1]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $request === '/assets/unassign') {
    $controller = new AssetController();
    $controller->unassign();
    exit;
}

if (preg_match('/^\/assets\/add_maintenance\/(\d+)$/', $request, $matches)) {
    $controller = new AssetController();
    $controller->addMaintenanceSchedule($matches[1]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && preg_match('/^\/assets\/save_maintenance_schedule\/(\d+)$/', $request, $matches)) {
    $controller = new AssetController();
    $controller->saveMaintenanceSchedule($matches[1]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $request === '/assets/delete_maintenance_schedule') {
    $controller = new AssetController();
    $controller->deleteMaintenanceSchedule();
    exit;
}

if (preg_match('/^\/assets\/add_maintenance_log\/(\d+)$/', $request, $matches)) {
    $controller = new AssetController();
    $controller->addMaintenanceLog($matches[1]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $request === '/assets/save_maintenance_log') {
    $controller = new AssetController();
    $controller->saveMaintenanceLog();
    exit;
}

if (preg_match('/^\/assets\/print_label\/(\d+)$/', $request, $matches)) {
    $controller = new AssetController();
    $controller->printLabel($matches[1]);
    exit;
}

// مسیر API برای دریافت مدل‌ها بر اساس دسته‌بندی با استفاده از پارامتر کوئری استرینگ
if (strpos($request, '/api/models') === 0) {
    error_log("API Route matched for models: " . $request);
    
    // دریافت شناسه دسته‌بندی از پارامتر کوئری استرینگ
    $categoryId = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
    error_log("Category ID: " . $categoryId);
    
    if ($categoryId <= 0) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'شناسه دسته‌بندی نامعتبر است']);
        exit;
    }
    
    try {
        // ایجاد نمونه از مدل
        require_once __DIR__ . '/../app/models/AssetModel.php';
        $db = new Database();
        $modelModel = new AssetModel();
        
        // دریافت مدل‌های مرتبط با دسته‌بندی
        $query = "SELECT id, name FROM asset_models WHERE category_id = ? ORDER BY name";
        $models = $db->query($query, [$categoryId]);
        
        // ثبت لاگ برای اشکال‌زدایی
        error_log("Models found: " . json_encode($models));
        
        // ارسال پاسخ به صورت JSON
        header('Content-Type: application/json');
        echo json_encode($models);
        exit;
    } catch (Exception $e) {
        error_log("Error in API models: " . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode(['error' => 'خطا در دریافت مدل‌ها: ' . $e->getMessage()]);
        exit;
    }
}

// مسیرهای مربوط به انواع سرویس‌های ادواری
if ($request === '/maintenance_types') {
    $controller = new MaintenanceController();
    $controller->index();
    exit;
}

if ($request === '/maintenance_types/create') {
    $controller = new MaintenanceController();
    $controller->create();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $request === '/maintenance_types/store') {
    $controller = new MaintenanceController();
    $controller->store();
    exit;
}

if (preg_match('/^\/maintenance_types\/edit\/(\d+)$/', $request, $matches)) {
    $controller = new MaintenanceController();
    $controller->edit($matches[1]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && preg_match('/^\/maintenance_types\/update\/(\d+)$/', $request, $matches)) {
    $controller = new MaintenanceController();
    $controller->update($matches[1]);
    exit;
}

if (preg_match('/^\/maintenance_types\/delete\/(\d+)$/', $request, $matches)) {
    $controller = new MaintenanceController();
    $controller->delete($matches[1]);
    exit;
}

// مسیرهای مربوط به سرویس‌های ادواری
if ($request === '/maintenance') {
    $controller = new MaintenanceController();
    $controller->listSchedules();
    exit;
}

if ($request === '/maintenance/upcoming') {
    $controller = new MaintenanceController();
    $controller->upcomingMaintenance();
    exit;
}

if ($request === '/maintenance/overdue') {
    $controller = new MaintenanceController();
    $controller->overdueMaintenance();
    exit;
}

// مسیرهای جدید برای مدیریت مشخصات سخت‌افزاری
if (preg_match('/^\/assets\/specs\/(\d+)$/', $request, $matches)) {
    $controller = new AssetController();
    $controller->manageSpecs($matches[1]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $request === '/assets/save_specs') {
    $controller = new AssetController();
    $controller->saveSpecs();
    exit;
}

// مسیر API برای دریافت مشخصات پیش‌فرض مدل
if ($request === '/asset_models/default_specs' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $controller = new AssetModelController();
    $controller->getDefaultSpecs();
    exit;
}

// مسیر برای گزارش‌گیری تجهیز‌ها
if ($request === '/assets/reports') {
    $controller = new AssetController();
    $controller->reports();
    exit;
}

// مسیر برای گزارش تجهیز‌های نیازمند توجه
if ($request === '/assets/attention_needed') {
    $controller = new AssetController();
    $controller->attentionNeeded();
    exit;
}

// مسیر برای گزارش تجهیز‌های با گارانتی رو به اتمام
if ($request === '/assets/expiring_warranty') {
    $controller = new AssetController();
    $controller->expiringWarranty();
    exit;
}

// مسیر برای گزارش آماری تجهیز‌ها
if ($request === '/assets/statistics') {
    $controller = new AssetController();
    $controller->statistics();
    exit;
}

// مسیر ارجاع درخواست
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $request === '/tickets/refer') {
    error_log("=== REFER ROUTE MATCHED ===");
    require_once __DIR__ . '/../app/controllers/TicketController.php';
    $controller = new TicketController();
    $controller->refer();
    exit;
}

// مسیر به‌روزرسانی وضعیت درخواست کار - این شرط باید قبل از شرط عمومی '/tickets' قرار گیرد
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($request === '/tickets/update_status')) {
    error_log("=== UPDATE STATUS ROUTE MATCHED ===");
    error_log("Request: " . $request);
    error_log("REQUEST_URI: " . $_SERVER['REQUEST_URI']);
    error_log("POST Data: " . print_r($_POST, true));
    
    $controller = new TicketController();
    try {
        $controller->updateStatus();
    } catch (Exception $e) {
        error_log("Exception in updateStatus: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        $_SESSION['error'] = "خطا در به‌روزرسانی وضعیت: " . $e->getMessage();
        header('Location: /support_system/tickets');
    }
    exit;
}

// مسیر داشبورد
if ($request === '/dashboard' || $request === '/support_system/dashboard') {
    $controller = new DashboardController();
    $controller->index();
    exit;
}

// مسیر جزئیات درخواست کار‌ها
if (preg_match('/^\/tickets\/view\/(\d+)$/', $request, $matches)) {
    $ticketId = $matches[1]; // استخراج شناسه درخواست کار از URL
    error_log("Viewing ticket with ID: " . $ticketId);
    $ticketController = new TicketController();
    $ticketController->viewTicket($ticketId);
    exit;
}

// مسیر ویرایش درخواست کار‌ها
if (preg_match('/^\/tickets\/edit\/(\d+)$/', $request, $matches)) {
    $ticketId = $matches[1];
    error_log("Editing ticket with ID: " . $ticketId);
    $ticketController = new TicketController();
    $ticketController->editTicket($ticketId);
    exit;
}

// مسیر به‌روزرسانی درخواست کار‌ها (فقط برای درخواست‌های POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && preg_match('/^\/tickets\/update\/(\d+)$/', $request, $matches)) {
    $ticketId = $matches[1];
    $ticketController = new TicketController();
    $ticketController->updateTicket($ticketId);
    exit;
}

// مسیر ثبت درخواست (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $request === '/tickets/create') {
     // فراخوانی کنترلر و متد مربوطه
     require_once __DIR__ . '/../app/controllers/TicketController.php';
    $ticketController = new TicketController();
    $ticketController->createTicket();
    exit;
}

// مسیر جستجوی کاربر بر اساس شماره پرسنلی (API)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $request === '/tickets/getUserByEmployeeNumber') {
    $ticketController = new TicketController();
    $ticketController->getUserByEmployeeNumber();
    exit;
}

// مسیر به‌روزرسانی زمان صرف‌شده
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($request === '/tickets/update_elapsed_time')) {
    error_log("=== UPDATE ELAPSED TIME ROUTE MATCHED ===");
    error_log("Request: " . $request);
    error_log("POST Data: " . print_r($_POST, true));
    
    $controller = new TicketController();
    try {
        $controller->updateElapsedTime();
    } catch (Exception $e) {
        error_log("Exception in updateElapsedTime: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'خطا در به‌روزرسانی زمان صرف‌شده: ' . $e->getMessage()]);
    }
    exit;
}

// مسیر دانلود فایل پیوست
if (preg_match('/^\/tickets\/download\/(\d+)$/', $request, $matches)) {
    $ticketId = $matches[1];
    error_log("=== DOWNLOAD ATTACHMENT ROUTE MATCHED ===");
    error_log("Ticket ID: " . $ticketId);
    
    $controller = new TicketController();
    try {
        $controller->downloadAttachment($ticketId);
    } catch (Exception $e) {
        error_log("Exception in downloadAttachment: " . $e->getMessage());
        http_response_code(500);
        echo "خطا در دانلود فایل: " . $e->getMessage();
    }
    exit;
}

// مسیر پاسخ به درخواست کار
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_SERVER['REQUEST_URI'] === '/support_system/tickets/reply' || $request === '/tickets/reply')) {
    error_log("=== REPLY ROUTE MATCHED ===");
    error_log("REQUEST_URI: " . $_SERVER['REQUEST_URI']);
    error_log("Processed request: " . $request);
    error_log("POST Data: " . print_r($_POST, true));
    
    $controller = new TicketController();
    try {
        $controller->reply();
    } catch (Exception $e) {
        error_log("Exception in reply method: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        $_SESSION['error'] = "خطا در ارسال پاسخ: " . $e->getMessage();
        
        // اگر شناسه درخواست کار موجود است، به صفحه جزئیات درخواست برگردید
        if (isset($_POST['ticket_id'])) {
            header('Location: /support_system/tickets/view/' . $_POST['ticket_id']);
        } else {
            header('Location: /support_system/tickets');
        }
    }
    exit;
}

// مسیر درخواست‌های tickets
if (strpos($request, '/tickets') === 0) {
    $ticketController = new TicketController();
    
    // بررسی پارامترهای GET
    if (isset($_GET['sort_by']) && isset($_GET['order'])) {
        error_log("Sort parameters found: sort_by=" . $_GET['sort_by'] . ", order=" . $_GET['order']);
    }
    
    error_log("listTickets method called");
    error_log("GET Params in listTickets: " . print_r($_GET, true));
    $ticketController->listTickets();
    exit;
}

// مسیر جستجو
$parsedUrl = parse_url($request);
$path = $parsedUrl['path']; // فقط مسیر (بدون پارامترهای کوئری)

if ($path === '/users/search') {
    $controller = new UserController();
    $controller->search();
    exit;
}

// مسیر جستجو API
if (strpos($request, '/search_api.php') !== false) {
    require_once __DIR__ . '/../app/controllers/search_api.php';
    exit;
}

if (isset($_GET['route']) && $_GET['route'] === 'search_api') {
    require_once __DIR__ . '/../app/controllers/search_api.php';
    exit;
}

//تغییر وضعیت کاربران
if (preg_match('/^\/users\/toggle_status\/(\d+)$/', $request, $matches)) {
    $controller = new UserController();
    $controller->toggleStatus($matches[1]);
    exit;
}

//مسیر مربوط به جستجو
if ($request === '/users/search') {
    $controller = new UserController();
    $controller->search();
    exit;
}

// مسیر جستجوی درخواست کار‌ها
if ($request === '/support_system/tickets/search') {
    $ticketController = new TicketController();
    $ticketController->search();
    exit;
}

// مدیریت مسیر دریافت اطلاعات کاربر برای مودال
if (preg_match('/^\/users\/getUserDetails\/(\d+)$/', $request, $matches)) {
    $userId = $matches[1]; // استخراج شناسه کاربر از URL
    $controller = new UserController();
    $controller->getUserDetails($userId);
    exit;
}

if ($request === '/users/updateUserDetails' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $controller = new UserController();
    $controller->updateUserDetails();
    exit;
}

// تخصیص دسترسی‌ها به نقش
if (preg_match('/^\/roles\/assign_permissions\/(\d+)$/', $request, $matches)) {
    $controller = new RoleController();
    $controller->assignPermissions($matches[1]);
    exit;
}

if (preg_match('/^\/roles\/update_permissions\/(\d+)$/', $request, $matches)) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $controller = new RoleController();
        $controller->updatePermissions($matches[1]);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_SERVER['REQUEST_URI'] === '/support_system/roles/update_permissions') {
    $roleController = new RoleController();
    $roleController->updatePermissions($_POST['role_id']);
    exit;
}

// دریافت دسترسی‌های نقش (برای Ajax)
if (preg_match('/^\/roles\/permissions\/(\d+)$/', $request, $matches)) {
    $roleId = $matches[1];
    $controller = new RoleController();
    $controller->getPermissions($roleId);
    exit;
}

// تخصیص دسترسی‌ها به نقش - مسیر جدید
if (preg_match('/^\/roles\/assign-permissions\/(\d+)$/', $request, $matches)) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $controller = new RoleController();
        $controller->updatePermissions($matches[1]);
        exit;
    }
}

// مسیر دریافت لیست پشتیبان‌ها (API)
if ($request === '/users/getSupportStaff' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $controller = new UserController();
    $controller->getSupportStaff();
    exit;
}

// بررسی تکمیل بودن پروفایل در همه صفحات به جز صفحه پروفایل و ورود
if (isset($_SESSION['user_id']) && !in_array($request, ['/profile', '/profile/update', '/login', '/logout'])) {
    $userModel = new User();

    if (!$userModel->isProfileComplete($_SESSION['user_id'])) {
        $_SESSION['error'] = "لطفاً پروفایل خود را تکمیل کنید.";
        header('Location: /profile');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

    if ($path === '/support_system/login') {
        // درخواست لاگین
        $controller = new AuthController();
        $controller->login();
        exit;
    }

    if ($path === '/support_system/roles/store') {
        // درخواست ایجاد نقش جدید
        $controller = new RoleController();
        $controller->store();
        exit;
    }

    if ($path === '/support_system/roles/delete') {
        // حذف نقش
        $controller = new RoleController();
        $controller->delete($_POST['role_id']);
        exit;
    }
}

// مسیریابی (Routing)
switch ($request) {
      case '/login':
        $controller = new LoginController();
        $controller->index();
        break;

    case '/login/authenticate':
        $controller = new LoginController();
        $controller->authenticate();
        break;

    case '/logout':
        $controller = new LoginController();
        $controller->logout();
        break;

    case '/profile':
        $controller = new ProfileController();
        $controller->index();
        break;

    case '/profile/update':
        $controller = new ProfileController();
        $controller->updateProfile();
        break;

    case '/users/add':
        $controller = new UserController();
        $controller->addUser();
        break;

    case '/change_password':
        $controller = new ProfileController();
        $controller->changePasswordPage();
        break;

    case '/change_password/submit':
        $controller = new ProfileController();
        $controller->changePassword();
        break;

    case (strpos($request, '/roles') === 0):
        $controller = new RoleController();
        $controller->index();
        break;
        
    case '/roles/create':
        $controller = new RoleController();
        $controller->create();
        break;
        
    case (preg_match('/^\/roles\/delete\/(\d+)$/', $request, $matches) ? true : false):
        $controller = new RoleController();
        $controller->delete($matches[1]);
        break;

    // مدیریت دسترسی‌ها
    case '/permissions':
        $controller = new PermissionController();
        $controller->index();
        break;

    case '/permissions/create':
        $controller = new PermissionController();
        $controller->create();
        break;

    case '/permissions/store':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller = new PermissionController();
            $controller->store();
        }
        break;

    case (preg_match('/^\/permissions\/delete\/(\d+)$/', $request, $matches) ? true : false):
        $controller = new PermissionController();
        $controller->delete($matches[1]);
        break;

    // ویرایش نقش
    case (preg_match('/^\/roles\/edit\/(\d+)$/', $request, $matches) ? true : false):
        $controller = new RoleController();
        $controller->edit($matches[1]);
        break;

    case (preg_match('/^\/roles\/update\/(\d+)$/', $request, $matches) ? true : false):
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller = new RoleController();
            $controller->update($matches[1]);
        }
        break;

    // مدیریت کاربران
    case '/users':
    case '/support_system/users': // اضافه کردن مسیر کامل
        $controller = new UserController();
        $controller->listUsers();
        break;

    case '/users/create':
        $controller = new UserController();
        $controller->create();
        break;

    case '/users/store':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller = new UserController();
            $controller->store();
        }
        break;

    case (preg_match('/^\/users\/edit\/(\d+)$/', $request, $matches) ? true : false):
        $controller = new UserController();
        $controller->edit($matches[1]);
        break;

    case (preg_match('/^\/users\/update\/(\d+)$/', $request, $matches) ? true : false):
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller = new UserController();
            $controller->update($matches[1]);
        }
        break;

    case (preg_match('/^\/users\/delete\/(\d+)$/', $request, $matches) ? true : false):
        $controller = new UserController();
        $controller->delete($matches[1]);
        break;

    // پشتیبانی از پارامترهای GET در مسیر /users
    default:
        if (strpos($request, '/roles') === 0) {
            $controller = new RoleController();
            $controller->index();
            
        } else if (strpos($request, '/users') === 0) {
            $controller = new UserController();
            $controller->listUsers();
        } else {
            http_response_code(404);
            echo "صفحه مورد نظر یافت نشد.";
        }
    break;
}

ob_end_flush();