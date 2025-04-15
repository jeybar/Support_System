<?php
require_once __DIR__ . '/../models/MaintenanceType.php';
require_once __DIR__ . '/../models/MaintenanceLog.php';
require_once __DIR__ . '/../models/Asset.php';
require_once __DIR__ . '/../models/AssetCategory.php';
require_once __DIR__ . '/../helpers/AccessControl.php';

class MaintenanceController {
    private $accessControl;
    
    public function __construct() {
        // ایجاد نمونه از کلاس کنترل دسترسی
        $this->accessControl = new AccessControl();
    }
    
    /**
     * نمایش داشبورد نگهداری
     */
    public function dashboard() {
        // بررسی دسترسی کاربر
        if (!$this->accessControl->hasPermission('view_maintenance_dashboard')) {
            $this->redirect('dashboard', ['error' => 'شما دسترسی لازم برای مشاهده داشبورد نگهداری را ندارید.']);
            return;
        }
        
        // دریافت آمار نگهداری
        $maintenanceType = new MaintenanceType();
        $maintenanceSchedule = new MaintenanceSchedule();
        $maintenanceLog = new MaintenanceLog();
        
        $complianceStats = $maintenanceType->getMaintenanceComplianceStats();
        $overdueSchedules = $maintenanceSchedule->getOverdueSchedules(5); // 5 برنامه معوق
        $upcomingSchedules = $maintenanceSchedule->getUpcomingSchedules(5); // 5 برنامه آینده
        $recentLogs = $maintenanceLog->getRecentLogs(5); // 5 سابقه اخیر
        $mostUsedTypes = $maintenanceType->getMostUsedTypes(5); // 5 نوع پرکاربرد
        
        // نمایش صفحه
        $this->view('maintenance/dashboard', [
            'complianceStats' => $complianceStats,
            'overdueSchedules' => $overdueSchedules,
            'upcomingSchedules' => $upcomingSchedules,
            'recentLogs' => $recentLogs,
            'mostUsedTypes' => $mostUsedTypes
        ]);
    }
    
    /**
     * نمایش صفحه انواع نگهداری
     */
    public function types() {
        // بررسی دسترسی کاربر
        if (!$this->accessControl->hasPermission('view_maintenance_types')) {
            $this->redirect('dashboard', ['error' => 'شما دسترسی لازم برای مشاهده انواع نگهداری را ندارید.']);
            return;
        }
        
        // دریافت پارامترهای صفحه‌بندی
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
        
        // دریافت پارامترهای مرتب‌سازی
        $sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'name';
        $order = isset($_GET['order']) ? $_GET['order'] : 'asc';
        
        // دریافت پارامترهای فیلتر
        $filters = [
            'name' => $_GET['name'] ?? '',
            'category' => $_GET['category'] ?? '',
            'is_required' => isset($_GET['is_required']) && $_GET['is_required'] !== '' ? (int)$_GET['is_required'] : '',
            'interval_min' => $_GET['interval_min'] ?? '',
            'interval_max' => $_GET['interval_max'] ?? ''
        ];
        
        // دریافت انواع نگهداری با فیلتر و صفحه‌بندی
        $maintenanceType = new MaintenanceType();
        $result = $maintenanceType->getFilteredTypes($filters, $page, $perPage, $sortBy, $order);
        
        // دریافت دسته‌بندی‌های منحصر به فرد
        $categories = $maintenanceType->getUniqueCategories();
        
        // دریافت آمار نگهداری
        $stats = $maintenanceType->getMaintenanceComplianceStats();
        
        // نمایش صفحه
        $this->view('maintenance/types', [
            'types' => $result['types'],
            'pagination' => $result['pagination'],
            'filters' => $filters,
            'categories' => $categories,
            'stats' => $stats,
            'sortBy' => $sortBy,
            'order' => $order
        ]);
    }
    
    /**
     * نمایش جزئیات یک نوع نگهداری
     */
    public function viewType($id) {
        // بررسی دسترسی کاربر
        if (!$this->accessControl->hasPermission('view_maintenance_types')) {
            $this->redirect('maintenance/types', ['error' => 'شما دسترسی لازم برای مشاهده جزئیات نوع نگهداری را ندارید.']);
            return;
        }
        
        // دریافت اطلاعات نوع نگهداری
        $maintenanceType = new MaintenanceType();
        $type = $maintenanceType->getTypeById($id);
        
        if (!$type) {
            $this->redirect('maintenance/types', ['error' => 'نوع نگهداری مورد نظر یافت نشد.']);
            return;
        }
        
        // دریافت برنامه‌های نگهداری مرتبط
        $schedules = $maintenanceType->getSchedulesByTypeId($id);
        
        // دریافت سوابق نگهداری مرتبط
        $logs = $maintenanceType->getLogsByTypeId($id, 10); // 10 سابقه آخر
        
        // نمایش صفحه
        $this->view('maintenance/view_type', [
            'type' => $type,
            'schedules' => $schedules,
            'logs' => $logs
        ]);
    }
    
    /**
     * نمایش فرم ایجاد نوع نگهداری جدید
     */
    public function createType() {
        // بررسی دسترسی کاربر
        if (!$this->accessControl->hasPermission('create_maintenance_types')) {
            $this->redirect('maintenance/types', ['error' => 'شما دسترسی لازم برای ایجاد نوع نگهداری را ندارید.']);
            return;
        }
        
        // دریافت دسته‌بندی‌های تجهیز برای انتخاب
        $assetCategory = new AssetCategory();
        $categories = $assetCategory->getAllCategories();
        
        // نمایش فرم
        $this->view('maintenance/create_type', [
            'categories' => $categories
        ]);
    }
    
    /**
     * ذخیره نوع نگهداری جدید
     */
    public function storeType() {
        // بررسی دسترسی کاربر
        if (!$this->accessControl->hasPermission('create_maintenance_types')) {
            $this->redirect('maintenance/types', ['error' => 'شما دسترسی لازم برای ایجاد نوع نگهداری را ندارید.']);
            return;
        }
        
        // بررسی روش درخواست
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('maintenance/types');
            return;
        }
        
        // دریافت داده‌های فرم
        $name = $_POST['name'] ?? '';
        $description = $_POST['description'] ?? '';
        $intervalDays = $_POST['interval_days'] ?? 0;
        $checklist = $_POST['checklist'] ?? null;
        $isRequired = isset($_POST['is_required']) ? 1 : 0;
        $category = $_POST['category'] ?? null;
        
        // اعتبارسنجی داده‌ها
        $errors = [];
        
        if (empty($name)) {
            $errors[] = 'نام نوع نگهداری الزامی است.';
        }
        
        if (empty($description)) {
            $errors[] = 'توضیحات نوع نگهداری الزامی است.';
        }
        
        if (empty($intervalDays) || !is_numeric($intervalDays) || $intervalDays < 1) {
            $errors[] = 'فاصله زمانی نگهداری باید عددی بزرگتر از صفر باشد.';
        }
        
        if (!empty($errors)) {
            $this->view('maintenance/create_type', [
                'errors' => $errors,
                'old' => $_POST
            ]);
            return;
        }
        
        // ذخیره نوع نگهداری
        $maintenanceType = new MaintenanceType();
        $additionalData = [
            'checklist' => $checklist,
            'is_required' => $isRequired,
            'category' => $category
        ];
        
        $result = $maintenanceType->createType($name, $description, $intervalDays, $additionalData);
        
        if ($result) {
            $this->redirect('maintenance/types', ['success' => 'نوع نگهداری با موفقیت ایجاد شد.']);
        } else {
            $this->view('maintenance/create_type', [
                'errors' => ['خطا در ایجاد نوع نگهداری. ممکن است نام تکراری باشد.'],
                'old' => $_POST
            ]);
        }
    }
    
    /**
     * نمایش فرم ویرایش نوع نگهداری
     */
    public function editType($id) {
        // بررسی دسترسی کاربر
        if (!$this->accessControl->hasPermission('edit_maintenance_types')) {
            $this->redirect('maintenance/types', ['error' => 'شما دسترسی لازم برای ویرایش نوع نگهداری را ندارید.']);
            return;
        }
        
        // دریافت اطلاعات نوع نگهداری
        $maintenanceType = new MaintenanceType();
        $type = $maintenanceType->getTypeById($id);
        
        if (!$type) {
            $this->redirect('maintenance/types', ['error' => 'نوع نگهداری مورد نظر یافت نشد.']);
            return;
        }
        
        // دریافت دسته‌بندی‌های تجهیز برای انتخاب
        $assetCategory = new AssetCategory();
        $categories = $assetCategory->getAllCategories();
        
        // نمایش فرم
        $this->view('maintenance/edit_type', [
            'type' => $type,
            'categories' => $categories
        ]);
    }
    
    /**
     * به‌روزرسانی نوع نگهداری
     */
    public function updateType($id) {
        // بررسی دسترسی کاربر
        if (!$this->accessControl->hasPermission('edit_maintenance_types')) {
            $this->redirect('maintenance/types', ['error' => 'شما دسترسی لازم برای ویرایش نوع نگهداری را ندارید.']);
            return;
        }
        
        // بررسی روش درخواست
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('maintenance/types');
            return;
        }
        
        // دریافت داده‌های فرم
        $name = $_POST['name'] ?? '';
        $description = $_POST['description'] ?? '';
        $intervalDays = $_POST['interval_days'] ?? 0;
        $checklist = $_POST['checklist'] ?? null;
        $isRequired = isset($_POST['is_required']) ? 1 : 0;
        $category = $_POST['category'] ?? null;
        
        // اعتبارسنجی داده‌ها
        $errors = [];
        
        if (empty($name)) {
            $errors[] = 'نام نوع نگهداری الزامی است.';
        }
        
        if (empty($description)) {
            $errors[] = 'توضیحات نوع نگهداری الزامی است.';
        }
        
        if (empty($intervalDays) || !is_numeric($intervalDays) || $intervalDays < 1) {
            $errors[] = 'فاصله زمانی نگهداری باید عددی بزرگتر از صفر باشد.';
        }
        
        if (!empty($errors)) {
            $this->view('maintenance/edit_type', [
                'errors' => $errors,
                'type' => [
                    'id' => $id,
                    'name' => $name,
                    'description' => $description,
                    'interval_days' => $intervalDays,
                    'checklist' => $checklist,
                    'is_required' => $isRequired,
                    'category' => $category
                ]
            ]);
            return;
        }
        
        // به‌روزرسانی نوع نگهداری
        $maintenanceType = new MaintenanceType();
        $additionalData = [
            'checklist' => $checklist,
            'is_required' => $isRequired,
            'category' => $category
        ];
        
        $result = $maintenanceType->updateType($id, $name, $description, $intervalDays, $additionalData);
        
        if ($result) {
            $this->redirect('maintenance/types', ['success' => 'نوع نگهداری با موفقیت به‌روزرسانی شد.']);
        } else {
            $this->view('maintenance/edit_type', [
                'errors' => ['خطا در به‌روزرسانی نوع نگهداری. ممکن است نام تکراری باشد.'],
                'type' => [
                    'id' => $id,
                    'name' => $name,
                    'description' => $description,
                    'interval_days' => $intervalDays,
                    'checklist' => $checklist,
                    'is_required' => $isRequired,
                    'category' => $category
                ]
            ]);
        }
    }
    
    /**
     * حذف نوع نگهداری
     */
    public function deleteType($id) {
        // بررسی دسترسی کاربر
        if (!$this->accessControl->hasPermission('delete_maintenance_types')) {
            $this->redirect('maintenance/types', ['error' => 'شما دسترسی لازم برای حذف نوع نگهداری را ندارید.']);
            return;
        }
        
        // بررسی امکان حذف
        $maintenanceType = new MaintenanceType();
        $checkResult = $maintenanceType->checkTypeDeletePossibility($id);
        
        if (!$checkResult['can_delete']) {
            $this->redirect('maintenance/types', ['error' => $checkResult['message']]);
            return;
        }
        
        // حذف نوع نگهداری
        $result = $maintenanceType->deleteType($id);
        
        if ($result) {
            $this->redirect('maintenance/types', ['success' => 'نوع نگهداری با موفقیت حذف شد.']);
        } else {
            $this->redirect('maintenance/types', ['error' => 'خطا در حذف نوع نگهداری.']);
        }
    }
    
    /**
     * کپی نوع نگهداری
     */
    public function duplicateType($id) {
        // بررسی دسترسی کاربر
        if (!$this->accessControl->hasPermission('create_maintenance_types')) {
            $this->redirect('maintenance/types', ['error' => 'شما دسترسی لازم برای کپی نوع نگهداری را ندارید.']);
            return;
        }
        
        // کپی نوع نگهداری
        $maintenanceType = new MaintenanceType();
        $result = $maintenanceType->duplicateType($id);
        
        if ($result) {
            $this->redirect('maintenance/types', ['success' => 'نوع نگهداری با موفقیت کپی شد.']);
        } else {
            $this->redirect('maintenance/types', ['error' => 'خطا در کپی نوع نگهداری.']);
        }
    }
    
    /**
     * نمایش فرم وارد کردن انواع نگهداری از فایل CSV
     */
    public function showImportForm() {
        // بررسی دسترسی کاربر
        if (!$this->accessControl->hasPermission('create_maintenance_types')) {
            $this->redirect('maintenance/types', ['error' => 'شما دسترسی لازم برای وارد کردن انواع نگهداری را ندارید.']);
            return;
        }
        
        // نمایش فرم
        $this->view('maintenance/import_types');
    }
    
    /**
     * وارد کردن انواع نگهداری از فایل CSV
     */
    public function importTypes() {
        // بررسی دسترسی کاربر
        if (!$this->accessControl->hasPermission('create_maintenance_types')) {
            $this->redirect('maintenance/types', ['error' => 'شما دسترسی لازم برای وارد کردن انواع نگهداری را ندارید.']);
            return;
        }
        
        // بررسی روش درخواست
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('maintenance/import');
            return;
        }
        
        // بررسی آپلود فایل
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            $this->view('maintenance/import_types', [
                'errors' => ['خطا در آپلود فایل.']
            ]);
            return;
        }
        
        // ذخیره فایل موقت
        $tempFile = $_FILES['csv_file']['tmp_name'];
        
        // وارد کردن داده‌ها
        $maintenanceType = new MaintenanceType();
        $result = $maintenanceType->importTypesFromCSV($tempFile);
        
        if ($result['success']) {
            $this->redirect('maintenance/types', ['success' => $result['message']]);
        } else {
            $this->view('maintenance/import_types', [
                'errors' => [$result['message']]
            ]);
        }
    }
    
    /**
     * خروجی گرفتن انواع نگهداری به فایل CSV
     */
    public function exportTypes() {
        // بررسی دسترسی کاربر
        if (!$this->accessControl->hasPermission('view_maintenance_types')) {
            $this->redirect('maintenance/types', ['error' => 'شما دسترسی لازم برای خروجی گرفتن انواع نگهداری را ندارید.']);
            return;
        }
        
        // دریافت فیلترها
        $filters = [
            'name' => $_GET['name'] ?? '',
            'category' => $_GET['category'] ?? '',
            'is_required' => isset($_GET['is_required']) && $_GET['is_required'] !== '' ? (int)$_GET['is_required'] : ''
        ];
        
        // ایجاد نام فایل
        $filename = 'maintenance_types_' . date('Y-m-d') . '.csv';
        $filepath = sys_get_temp_dir() . '/' . $filename;
        
        // خروجی گرفتن داده‌ها
        $maintenanceType = new MaintenanceType();
        $result = $maintenanceType->exportTypesToCSV($filepath, $filters);
        
        if ($result) {
            // تنظیم هدرهای HTTP برای دانلود فایل
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            
            // ارسال فایل
            readfile($filepath);
            
            // حذف فایل موقت
            unlink($filepath);
            exit;
        } else {
            $this->redirect('maintenance/types', ['error' => 'خطا در خروجی گرفتن انواع نگهداری.']);
        }
    }
    
    /**
     * نمایش آمار انواع نگهداری
     */
    public function typeStats() {
        // بررسی دسترسی کاربر
        if (!$this->accessControl->hasPermission('view_maintenance_stats')) {
            $this->redirect('maintenance/types', ['error' => 'شما دسترسی لازم برای مشاهده آمار نگهداری را ندارید.']);
            return;
        }
        
        // دریافت پارامترهای فیلتر
        $days = isset($_GET['days']) ? (int)$_GET['days'] : 30;
        
        // دریافت آمار
        $maintenanceType = new MaintenanceType();
        $statsByType = $maintenanceType->getMaintenanceStatsByType($days);
        $statsByCategory = $maintenanceType->getMaintenanceStatsByAssetCategory($days);
        $trend = $maintenanceType->getMaintenanceTrend(12); // 12 ماه اخیر
        $statsByUser = $maintenanceType->getMaintenanceStatsByUser($days, 10); // 10 کاربر برتر
        $complianceStats = $maintenanceType->getMaintenanceComplianceStats();
        $mostUsedTypes = $maintenanceType->getMostUsedTypes(5);
        $overdueTypes = $maintenanceType->getTypesWithMostOverdueSchedules(5);
        
        // نمایش صفحه
        $this->view('maintenance/type_stats', [
            'statsByType' => $statsByType,
            'statsByCategory' => $statsByCategory,
            'trend' => $trend,
            'statsByUser' => $statsByUser,
            'complianceStats' => $complianceStats,
            'mostUsedTypes' => $mostUsedTypes,
            'overdueTypes' => $overdueTypes,
            'days' => $days
        ]);
    }
    
    /**
     * دریافت انواع نگهداری برای یک تجهیز (API)
     */
    public function getTypesForAsset() {
        // بررسی دسترسی کاربر
        if (!$this->accessControl->hasPermission('view_maintenance_types')) {
            echo json_encode(['error' => 'دسترسی غیرمجاز']);
            return;
        }
        
        // دریافت شناسه تجهیز
        $assetId = $_GET['asset_id'] ?? 0;
        
        if (empty($assetId)) {
            echo json_encode(['error' => 'شناسه تجهیز الزامی است.']);
            return;
        }
        
        // دریافت انواع نگهداری
        $maintenanceType = new MaintenanceType();
        $types = $maintenanceType->getTypesForAsset($assetId);
        
        // ارسال پاسخ
        header('Content-Type: application/json');
        echo json_encode(['types' => $types]);
    }
    
    /**
     * نمایش برنامه‌های نگهداری
     */
    public function schedules() {
        // بررسی دسترسی کاربر
        if (!$this->accessControl->hasPermission('view_maintenance_schedules')) {
            $this->redirect('dashboard', ['error' => 'شما دسترسی لازم برای مشاهده برنامه‌های نگهداری را ندارید.']);
            return;
        }
        
        // دریافت پارامترهای صفحه‌بندی
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
        
        // دریافت پارامترهای مرتب‌سازی
        $sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'next_maintenance_date';
        $order = isset($_GET['order']) ? $_GET['order'] : 'asc';
        
        // دریافت پارامترهای فیلتر
        $filters = [
            'asset_tag' => $_GET['asset_tag'] ?? '',
            'type_id' => $_GET['type_id'] ?? '',
            'status' => $_GET['status'] ?? '',
            'date_from' => $_GET['date_from'] ?? '',
            'date_to' => $_GET['date_to'] ?? ''
        ];
        
        // دریافت برنامه‌های نگهداری با فیلتر و صفحه‌بندی
        $maintenanceSchedule = new MaintenanceSchedule();
        $result = $maintenanceSchedule->getFilteredSchedules($filters, $page, $perPage, $sortBy, $order);
        
        // دریافت انواع نگهداری برای فیلتر
        $maintenanceType = new MaintenanceType();
        $types = $maintenanceType->getAllTypes();
        
        // نمایش صفحه
        $this->view('maintenance/schedules', [
            'schedules' => $result['schedules'],
            'pagination' => $result['pagination'],
            'filters' => $filters,
            'types' => $types,
            'sortBy' => $sortBy,
            'order' => $order
        ]);
    }
    
    /**
     * نمایش سوابق نگهداری
     */
    public function logs() {
        // بررسی دسترسی کاربر
        if (!$this->accessControl->hasPermission('view_maintenance_logs')) {
            $this->redirect('dashboard', ['error' => 'شما دسترسی لازم برای مشاهده سوابق نگهداری را ندارید.']);
            return;
        }
        
        // دریافت پارامترهای صفحه‌بندی
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
        
        // دریافت پارامترهای مرتب‌سازی
        $sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'performed_at';
        $order = isset($_GET['order']) ? $_GET['order'] : 'desc';
        
        // دریافت پارامترهای فیلتر
        $filters = [
            'asset_tag' => $_GET['asset_tag'] ?? '',
            'type_id' => $_GET['type_id'] ?? '',
            'user_id' => $_GET['user_id'] ?? '',
            'date_from' => $_GET['date_from'] ?? '',
            'date_to' => $_GET['date_to'] ?? ''
        ];
        
        // دریافت سوابق نگهداری با فیلتر و صفحه‌بندی
        $maintenanceLog = new MaintenanceLog();
        $result = $maintenanceLog->getFilteredLogs($filters, $page, $perPage, $sortBy, $order);
        
        // دریافت انواع نگهداری برای فیلتر
        $maintenanceType = new MaintenanceType();
        $types = $maintenanceType->getAllTypes();
        
        // نمایش صفحه
        $this->view('maintenance/logs', [
            'logs' => $result['logs'],
            'pagination' => $result['pagination'],
            'filters' => $filters,
            'types' => $types,
            'sortBy' => $sortBy,
            'order' => $order
        ]);
    }
    
    /**
     * هدایت به صفحه دیگر با پیام
     * 
     * @param string $path مسیر هدایت
     * @param array $params پارامترهای اضافی (اختیاری)
     */
    private function redirect($path, $params = []) {
        $url = '/' . $path;
        
        // افزودن پارامترها به URL
        if (!empty($params)) {
            $queryString = http_build_query($params);
            $url .= '?' . $queryString;
        }
        
        // هدایت به URL جدید
        header('Location: ' . $url);
        exit;
    }
    
    /**
     * نمایش یک قالب
     * 
     * @param string $view مسیر قالب
     * @param array $data داده‌های قالب
     */
    private function view($view, $data = []) {
        // استخراج داده‌ها به متغیرهای محلی
        extract($data);
        
        // مسیر کامل قالب
        $viewPath = __DIR__ . '/../views/' . $view . '.php';
        
        // بررسی وجود قالب
        if (file_exists($viewPath)) {
            // شروع بافر خروجی
            ob_start();
            
            // بارگذاری هدر
            include __DIR__ . '/../views/header.php';
            
            // بارگذاری قالب اصلی
            include $viewPath;
            
            // بارگذاری فوتر
            include __DIR__ . '/../views/footer.php';
            
            // ارسال بافر خروجی
            ob_end_flush();
        } else {
            // خطا در صورت عدم وجود قالب
            echo "Error: View '$view' not found.";
        }
    }
}