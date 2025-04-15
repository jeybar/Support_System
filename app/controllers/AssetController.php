<?php
require_once __DIR__ . '/../models/Asset.php';
require_once __DIR__ . '/../models/AssetCategory.php';
require_once __DIR__ . '/../models/AssetModel.php';
require_once __DIR__ . '/../models/AssetSpecification.php';
require_once __DIR__ . '/../models/AssetAssignment.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Vendor.php';
require_once __DIR__ . '/../helpers/AccessControl.php';
require_once __DIR__ . '/../helpers/QrCodeGenerator.php';

class AssetController {
    private $assetModel;
    private $categoryModel;
    private $modelModel;
    private $specModel;
    private $assignmentModel;
    private $userModel;
    private $vendorModel;
    private $accessControl;
    private $qrGenerator;

    public function __construct() {
        $this->assetModel = new Asset();
        $this->categoryModel = new AssetCategory();
        $this->modelModel = new AssetModel();
        $this->specModel = new AssetSpecification();
        $this->assignmentModel = new AssetAssignment();
        $this->userModel = new User();
        $this->vendorModel = new Vendor();
        $this->accessControl = new AccessControl();
        $this->qrGenerator = new QrCodeGenerator();
        
        // بررسی دسترسی کاربر به این بخش
        if (!$this->accessControl->hasPermission('view_assets')) {
            header('Location: /support_system/dashboard');
            exit;
        }
    }

    /**
     * نمایش صفحه اصلی مدیریت دارایی‌ها
     */
    public function index() {

        // قبل از هر چیز وضعیت‌ها را اصلاح کن
    $fixResult = $this->assetModel->fixEmptyStatuses();
    if (!empty($fixResult['errors'])) {
        $_SESSION['error'] = 'خطا در اصلاح وضعیت دارایی‌ها';
    }

        // بررسی دسترسی
        if (!$this->accessControl->hasPermission('view_assets')) {
            $_SESSION['error'] = 'شما مجوز مشاهده تجهیزات را ندارید.';
            header('Location: /support_system/dashboard');
            exit;
        }
        
        // اصلاح وضعیت تجهیزات با وضعیت خالی
        $this->assetModel->fixEmptyStatuses();

        // تعریف اطلاعات وضعیت‌ها
$statusInfo = [
    'ready' => [
        'text' => 'آماده استفاده',
        'class' => 'badge bg-success',
        'icon' => 'fas fa-check-circle'
    ],
    'in_use' => [
        'text' => 'در حال استفاده',
        'class' => 'badge bg-primary',
        'icon' => 'fas fa-user-check'
    ],
    'needs_repair' => [
        'text' => 'نیاز به تعمیر',
        'class' => 'badge bg-warning text-dark',
        'icon' => 'fas fa-tools'
    ],
    'out_of_service' => [
        'text' => 'خارج از سرویس',
        'class' => 'badge bg-secondary',
        'icon' => 'fas fa-archive'
    ],
    '' => [
        'text' => 'نامشخص',
        'class' => 'badge bg-info',
        'icon' => 'fas fa-info-circle'
    ]
];

        try {
                        
            // اضافه کردن داده‌های پیش‌فرض اگر نیاز باشد
            $this->addDefaultModels();
            
            // دریافت پارامترهای جستجو و صفحه‌بندی
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
            $sort = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
            $order = isset($_GET['order']) ? strtoupper($_GET['order']) : 'DESC';
            
            // اطمینان از معتبر بودن مقادیر
            $allowedSortFields = ['id', 'asset_tag', 'serial', 'name', 'category_id', 'model_id', 'status', 'assigned_to', 'location', 'created_at', 'updated_at'];
            if (!in_array($sort, $allowedSortFields)) {
                $sort = 'created_at';
            }
            
            $order = in_array($order, ['ASC', 'DESC']) ? $order : 'DESC';
            $page = max(1, $page);
            $perPage = max(5, min(100, $perPage));
            
            // فیلترهای جستجو
            $filters = [
                'asset_tag' => $_GET['asset_tag'] ?? '',
                'category_id' => $_GET['category_id'] ?? '',
                'model_id' => $_GET['model_id'] ?? '',
                'status' => $_GET['status'] ?? '',
                'assigned_to' => $_GET['assigned_to'] ?? '',
                'employee_number' => $_GET['employee_number'] ?? '',
                'serial' => $_GET['serial'] ?? '',
                'location' => $_GET['location'] ?? '',
                'name' => $_GET['name'] ?? '',
            ];
            
            // لاگ برای دیباگ
            error_log("Search filters: " . json_encode($filters));
            error_log("Sort: $sort, Order: $order, Page: $page, PerPage: $perPage");
            
            // جستجوی دارایی‌ها
            $result = $this->assetModel->searchAssets($filters, $sort, $order, $page, $perPage);
            
            // دریافت لیست دسته‌بندی‌ها و مدل‌ها برای فیلتر
            $categories = $this->categoryModel->getAllCategories();
            $models = $this->modelModel->getAllModels();
            
            // لاگ برای دیباگ
            error_log("Categories from controller: " . json_encode($categories));
            error_log("Models from controller: " . json_encode($models));
            
            // دریافت لیست کاربران برای فیلتر
            $users = $this->userModel->getAllUsersForDropdown();
            
            // دریافت لیست مکان‌ها
            $locations = $this->assetModel->getDistinctLocations();
            
            // دریافت لیست وضعیت‌های دارایی
$statuses = [
    'available' => 'در دسترس',
    'assigned' => 'تخصیص داده شده',
    'maintenance' => 'در تعمیرات',
    'retired' => 'بازنشسته',
    'lost' => 'گم شده',
    'broken' => 'خراب',
    '' => 'نامشخص'
];
            
// آماده‌سازی داده‌های مورد نیاز برای نمایش در صفحه
$viewData = [
    'assets' => $result['assets'],
    'total' => $result['total'],
    'page' => $result['page'],
    'perPage' => $result['perPage'],
    'totalPages' => $result['totalPages'],
    'filters' => $filters,
    'sort' => $sort,
    'order' => $order,
    'categories' => $categories,
    'models' => $models,
    'users' => $users,
    'locations' => $locations,
    'statuses' => $statuses,
    'baseUrl' => '/support_system/assets',
    'title' => 'مدیریت دارایی‌های سخت‌افزاری',
    'breadcrumbs' => [
        ['title' => 'داشبورد', 'url' => '/support_system/dashboard'],
        ['title' => 'مدیریت دارایی‌های سخت‌افزاری', 'url' => '']
    ],
    'statusInfo' => $statusInfo  // اضافه کردن متغیر $statusInfo
];
            
            // نمایش صفحه
            $this->view('assets/index', $viewData);
        } catch (Exception $e) {
            error_log("Error in AssetController index: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            $this->view('error/index', [
                'assets' => $assets,
                'statusInfo' => $statusInfo,
                'message' => 'خطا در بارگذاری صفحه مدیریت دارایی‌ها',
                'details' => $e->getMessage(),
                'trace' => DEBUG_MODE ? $e->getTraceAsString() : null
            ]);
        }
    }

    /**
     * اضافه کردن داده‌های پیش‌فرض به جدول مدل‌ها
     */
    private function addDefaultModels() {
        try {
            // بررسی وجود مدل‌ها
            $models = $this->modelModel->getAllModels();
            if (!empty($models)) {
                error_log("Models already exist. Count: " . count($models));
                return true;
            }
            
            // دریافت دسته‌بندی‌ها
            $categories = $this->categoryModel->getAllCategories();
            if (empty($categories)) {
                error_log("No categories found. Cannot add default models.");
                return false;
            }
            
            error_log("Adding default models for " . count($categories) . " categories");
            
            // اضافه کردن مدل‌های پیش‌فرض برای هر دسته‌بندی
            foreach ($categories as $category) {
                $categoryId = $category['id'];
                $categoryName = $category['name'];
                
                // تعیین نام مدل پیش‌فرض بر اساس نام دسته‌بندی
                $defaultModelName = "مدل پیش‌فرض " . $categoryName;
                
                // تعیین مدل‌های خاص برای برخی دسته‌بندی‌ها
                switch (trim(strtolower($categoryName))) {
                    case 'کامپیوتر':
                    case 'کامپیوتر':
                        $models = [
                            ['name' => 'دسکتاپ Dell OptiPlex', 'manufacturer' => 'Dell', 'model_number' => 'DP-001'],
                            ['name' => 'دسکتاپ HP ProDesk', 'manufacturer' => 'HP', 'model_number' => 'DP-002'],
                            ['name' => 'دسکتاپ Lenovo ThinkCentre', 'manufacturer' => 'Lenovo', 'model_number' => 'DP-003']
                        ];
                        break;
                        
                    case 'لپ‌تاپ':
                    case 'لپ تاپ':
                        $models = [
                            ['name' => 'لپ‌تاپ Dell Latitude', 'manufacturer' => 'Dell', 'model_number' => 'LT-001'],
                            ['name' => 'لپ‌تاپ HP EliteBook', 'manufacturer' => 'HP', 'model_number' => 'LT-002'],
                            ['name' => 'لپ‌تاپ Lenovo ThinkPad', 'manufacturer' => 'Lenovo', 'model_number' => 'LT-003']
                        ];
                        break;
                        
                    case 'پرینتر':
                        $models = [
                            ['name' => 'پرینتر HP LaserJet', 'manufacturer' => 'HP', 'model_number' => 'PR-001'],
                            ['name' => 'پرینتر Canon PIXMA', 'manufacturer' => 'Canon', 'model_number' => 'PR-002'],
                            ['name' => 'پرینتر Epson EcoTank', 'manufacturer' => 'Epson', 'model_number' => 'PR-003']
                        ];
                        break;
                        
                    case 'تجهیزات شبکه':
                    case 'شبکه':
                        $models = [
                            ['name' => 'روتر Cisco', 'manufacturer' => 'Cisco', 'model_number' => 'NT-001'],
                            ['name' => 'سوئیچ HP', 'manufacturer' => 'HP', 'model_number' => 'NT-002'],
                            ['name' => 'اکسس پوینت Ubiquiti', 'manufacturer' => 'Ubiquiti', 'model_number' => 'NT-003']
                        ];
                        break;
                        
                    case 'مانیتور':
                    case 'نمایشگر':
                        $models = [
                            ['name' => 'مانیتور Dell UltraSharp', 'manufacturer' => 'Dell', 'model_number' => 'MN-001'],
                            ['name' => 'مانیتور HP EliteDisplay', 'manufacturer' => 'HP', 'model_number' => 'MN-002'],
                            ['name' => 'مانیتور Samsung', 'manufacturer' => 'Samsung', 'model_number' => 'MN-003']
                        ];
                        break;
                        
                    case 'تلفن':
                        $models = [
                            ['name' => 'تلفن Cisco IP', 'manufacturer' => 'Cisco', 'model_number' => 'PH-001'],
                            ['name' => 'تلفن Polycom', 'manufacturer' => 'Polycom', 'model_number' => 'PH-002'],
                            ['name' => 'تلفن Avaya', 'manufacturer' => 'Avaya', 'model_number' => 'PH-003']
                        ];
                        break;
                        
                    case 'سرور':
                        $models = [
                            ['name' => 'سرور Dell PowerEdge', 'manufacturer' => 'Dell', 'model_number' => 'SV-001'],
                            ['name' => 'سرور HP ProLiant', 'manufacturer' => 'HP', 'model_number' => 'SV-002'],
                            ['name' => 'سرور Lenovo ThinkSystem', 'manufacturer' => 'Lenovo', 'model_number' => 'SV-003']
                        ];
                        break;
                        
                    case 'ذخیره‌سازی':
                        $models = [
                            ['name' => 'ذخیره‌ساز Dell EMC', 'manufacturer' => 'Dell EMC', 'model_number' => 'ST-001'],
                            ['name' => 'ذخیره‌ساز HP MSA', 'manufacturer' => 'HP', 'model_number' => 'ST-002'],
                            ['name' => 'ذخیره‌ساز Synology NAS', 'manufacturer' => 'Synology', 'model_number' => 'ST-003']
                        ];
                        break;
                        
                    case 'تجهیزات جانبی':
                        $models = [
                            ['name' => 'موس Logitech', 'manufacturer' => 'Logitech', 'model_number' => 'AC-001'],
                            ['name' => 'کیبورد Microsoft', 'manufacturer' => 'Microsoft', 'model_number' => 'AC-002'],
                            ['name' => 'اسکنر Epson', 'manufacturer' => 'Epson', 'model_number' => 'AC-003']
                        ];
                        break;
                        
                    default:
                        $models = [
                            ['name' => $defaultModelName, 'manufacturer' => 'عمومی', 'model_number' => 'MDL-' . $categoryId]
                        ];
                        break;
                }
                
                // اضافه کردن مدل‌ها به دیتابیس
                foreach ($models as $modelData) {
                    $modelData['category_id'] = $categoryId;
                    $modelId = $this->modelModel->addModel($modelData);
                    
                    if ($modelId) {
                        error_log("Added model '{$modelData['name']}' for category '{$categoryName}'");
                    } else {
                        error_log("Failed to add model '{$modelData['name']}' for category '{$categoryName}'");
                    }
                }
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Error in addDefaultModels: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            return false;
        }
    }

    /**
     * نمایش جزئیات یک تجهیز
     */
    public function show($id) {
        // بررسی دسترسی
        if (!$this->accessControl->hasPermission('view_assets')) {
            header('Location: /support_system/dashboard');
            exit;
        }
        
        // دریافت اطلاعات تجهیز
        $asset = $this->assetModel->getAssetById($id);
        
        if (!$asset) {
            // تجهیز یافت نشد
            $_SESSION['error'] = 'تجهیز مورد نظر یافت نشد.';
            header('Location: /support_system/assets');
            exit;
        }
        
        // دریافت مشخصات تجهیز
        $specifications = $this->specModel->getSpecificationsByAssetId($id);
        
        // دریافت تاریخچه تخصیص تجهیز
        $assignments = $this->assignmentModel->getAssignmentHistoryByAssetId($id);
        
        // دریافت تاریخچه تعمیر و نگهداری
        $maintenanceHistory = $this->assetModel->getMaintenanceHistoryByAssetId($id);
        
        // دریافت تاریخچه تغییرات سخت‌افزاری
        $hardwareChanges = $this->assetModel->getHardwareChangesByAssetId($id);
        
        // دریافت درخواست‌های کار مرتبط
        $tickets = $this->assetModel->getTicketsByAssetId($id);
        
        // تولید کد QR
        $qrCode = $this->qrGenerator->generateAssetQR($asset);
        
        // انتقال داده‌ها به نما
        $data = [
            'asset' => $asset,
            'specifications' => $specifications,
            'assignments' => $assignments,
            'maintenanceHistory' => $maintenanceHistory,
            'hardwareChanges' => $hardwareChanges,
            'tickets' => $tickets,
            'qrCode' => $qrCode
        ];
        
        // نمایش نما
        require_once __DIR__ . '/../views/assets/show.php';
    }

    /**
     * نمایش فرم ایجاد تجهیز جدید یا ارائه داده‌های مودال
     */
    public function create() {
        // بررسی دسترسی
        if (!$this->accessControl->hasPermission('create_assets')) {
            // بررسی نوع درخواست
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                // اگر درخواست AJAX است، پاسخ JSON با خطا برگردان
                header('Content-Type: application/json');
                echo json_encode(['error' => 'شما دسترسی لازم برای این عملیات را ندارید.']);
                exit;
            } else {
                // در غیر این صورت، هدایت به صفحه اصلی
                $_SESSION['error'] = 'شما مجوز ایجاد تجهیز جدید را ندارید.';
                header('Location: /support_system/assets');
                exit;
            }
        }
        
        // دریافت لیست دسته‌بندی‌ها
        $categories = $this->categoryModel->getAllCategories();
        
        // دریافت لیست مدل‌ها
        $models = $this->modelModel->getAllModels();
        
        // گروه‌بندی مدل‌ها بر اساس دسته‌بندی
        $modelsByCategory = [];
        foreach ($models as $model) {
            if (!isset($modelsByCategory[$model['category_id']])) {
                $modelsByCategory[$model['category_id']] = [];
            }
            $modelsByCategory[$model['category_id']][] = $model;
        }
        
        // دریافت لیست کاربران برای تخصیص تجهیز و استخراج plant و unit
        $users = $this->userModel->getAllUsersForDropdown();
        
        // استخراج لیست plant ها و unit ها از کاربران
        $plants = [];
        $units = [];
        foreach ($users as $user) {
            if (!empty($user['plant']) && !in_array($user['plant'], $plants)) {
                $plants[] = $user['plant'];
            }
            if (!empty($user['unit']) && !in_array($user['unit'], $units)) {
                $units[] = $user['unit'];
            }
        }
        
        // مرتب‌سازی لیست‌ها
        sort($plants);
        sort($units);
        
        // بررسی نوع درخواست
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            // اگر درخواست AJAX است، پاسخ JSON برگردان
            header('Content-Type: application/json');
            echo json_encode([
                'categories' => $categories,
                'models' => $models,
                'modelsByCategory' => $modelsByCategory,
                'plants' => $plants,
                'units' => $units,
                'users' => $users
            ]);
            exit;
        } else {
            // اگر درخواست معمولی است، به صفحه اصلی تجهیزات هدایت کنید
            // و پارامتری اضافه کنید که نشان دهد مودال افزودن باید باز شود
            $_SESSION['open_add_modal'] = true;
            header('Location: /support_system/assets');
            exit;
        }
    }

    /**
    * ذخیره تجهیز جدید
    */
    public function store() {
        // بررسی دسترسی
        if (!$this->accessControl->hasPermission('create_assets')) {
            if ($this->isAjaxRequest()) {
                $this->sendJsonResponse(['error' => 'شما مجوز ایجاد تجهیز جدید را ندارید.']);
            } else {
                $_SESSION['error'] = 'شما مجوز ایجاد تجهیز جدید را ندارید.';
                header('Location: /support_system/assets');
                exit;
            }
        }
        
        // بررسی درخواست POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            if ($this->isAjaxRequest()) {
                $this->sendJsonResponse(['error' => 'روش درخواست نامعتبر است.']);
            } else {
                header('Location: /support_system/assets');
                exit;
            }
        }
        
        // لاگ کردن داده‌های POST برای دیباگ
        error_log("POST data in store method: " . print_r($_POST, true));
        
        // اعتبارسنجی داده‌ها
        $errors = [];
        
        if (empty($_POST['name'])) {
            $errors[] = 'نام تجهیز الزامی است.';
        }
        
        if (empty($_POST['asset_tag'])) {
            $errors[] = 'اموال تجهیز الزامی است.';
        }
        
        if (empty($_POST['model_id'])) {
            $errors[] = 'انتخاب مدل الزامی است.';
        }
        
        if (empty($_POST['category_id'])) {
            $errors[] = 'انتخاب دسته‌بندی الزامی است.';
        }
        
        if (!empty($errors)) {
            if ($this->isAjaxRequest()) {
                $this->sendJsonResponse(['errors' => $errors]);
            } else {
                $_SESSION['errors'] = $errors;
                $_SESSION['form_data'] = $_POST;
                header('Location: /support_system/assets');
                exit;
            }
        }
        
        // دریافت اتصال به دیتابیس
        $db = Database::getInstance();
        $pdo = $db->getConnection(); // دریافت اتصال PDO
        
        try {
            // شروع تراکنش با استفاده از PDO
            $pdo->beginTransaction();
            
            // تعیین وضعیت پیش‌فرض
            $status = 'available'; // وضعیت پیش‌فرض: در دسترس
            
            // بررسی و اضافه کردن شناسه کاربر به داده‌های تجهیز
            $userId = null;
            if (!empty($_POST['user_id'])) {
                $userId = $_POST['user_id'];
            } elseif (!empty($_POST['assigned_to'])) {
                $userId = $_POST['assigned_to'];
            }
            
            if ($userId) {
                // اگر تجهیز به کاربری تخصیص داده شده، وضعیت را به "assigned" تغییر می‌دهیم
                $status = 'assigned';
                error_log("User ID found in POST data: " . $userId . ". Setting status to 'assigned'");
            } else {
                // اگر وضعیت در POST ارسال شده باشد، از آن استفاده می‌کنیم
                if (!empty($_POST['status'])) {
                    $status = $_POST['status'];
                }
                error_log("No user ID found in POST data. Status set to: " . $status);
            }
            
            // آماده‌سازی داده‌ها
            $assetData = [
                'name' => $_POST['name'],
                'computer_name' => $_POST['computer_name'] ?? null,
                'asset_tag' => $_POST['asset_tag'],
                'serial_number' => $_POST['serial_number'] ?? '',
                'model_id' => $_POST['model_id'],
                'category_id' => $_POST['category_id'],
                'status' => $status, // استفاده از متغیر وضعیت که تعیین کرده‌ایم
                'purchase_date' => $_POST['purchase_date'] ?? null,
                'plant' => $_POST['plant'] ?? null,
                'unit' => $_POST['unit'] ?? null,
                'location' => $_POST['location'] ?? null,
                'notes' => $_POST['notes'] ?? '',
                'created_by' => $_SESSION['user_id'] ?? null,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            // اگر کاربری انتخاب شده باشد، آن را به داده‌های تجهیز اضافه می‌کنیم
            if ($userId) {
                $assetData['user_id'] = $userId;
            }
            
            // لاگ داده‌های ارسالی برای دیباگ
            error_log("Asset Data after processing: " . json_encode($assetData));
            
            // آپلود تصویر
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/../../public/uploads/assets/';
                
                // اطمینان از وجود دایرکتوری آپلود
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                $fileName = uniqid() . '_' . basename($_FILES['image']['name']);
                $uploadFile = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadFile)) {
                    $assetData['image'] = '/uploads/assets/' . $fileName;
                } else {
                    error_log("Failed to upload image: " . $_FILES['image']['name']);
                }
            }
            
            // ذخیره تجهیز مستقیم با استفاده از PDO
            $assetId = $this->directCreateAsset($pdo, $assetData);
            
            if (!$assetId) {
                throw new Exception("خطا در ثبت تجهیز در پایگاه داده.");
            }
            
            // ذخیره مشخصات تجهیز
            if (isset($_POST['specs']) && is_array($_POST['specs'])) {
                foreach ($_POST['specs'] as $key => $value) {
                    if (!empty($value)) {
                        // ذخیره مشخصات به صورت مستقیم
                        $this->directAddSpecification($pdo, $assetId, $key, $value);
                    }
                }
            }
            
            // تخصیص تجهیز به کاربر (اگر انتخاب شده باشد)
            if ($userId) {
                // آماده‌سازی داده‌های تخصیص
                $assignmentData = [
                    'asset_id' => $assetId,
                    'user_id' => $userId,
                    'assigned_by' => $_SESSION['user_id'] ?? null,
                    'notes' => $_POST['assignment_notes'] ?? 'تخصیص اولیه در زمان ایجاد تجهیز',
                    'assigned_date' => date('Y-m-d H:i:s')
                ];
                
                // اگر تاریخ برگشت مورد انتظار وجود داشته باشد، آن را اضافه کنید
                if (!empty($_POST['expected_return_date'])) {
                    $assignmentData['expected_return_date'] = $_POST['expected_return_date'];
                }
                
                error_log("Assigning asset ID {$assetId} to user ID {$userId}");
                
                // ایجاد تخصیص به صورت مستقیم
                $assignmentId = $this->directCreateAssignment($pdo, $assignmentData);
                
                if (!$assignmentId) {
                    error_log("Failed to assign asset $assetId to user $userId");
                    // ادامه می‌دهیم و فقط لاگ می‌کنیم، تراکنش را برنمی‌گردانیم
                    // زیرا تجهیز با موفقیت ایجاد شده است
                } else {
                    error_log("Successfully assigned asset $assetId to user $userId with assignment ID $assignmentId");
                }
            }
            
            // تایید تراکنش
            $pdo->commit();
            
            // پاسخ موفقیت
            if ($this->isAjaxRequest()) {
                $this->sendJsonResponse([
                    'success' => true,
                    'message' => 'تجهیز با موفقیت ثبت شد.',
                    'asset_id' => $assetId
                ]);
            } else {
                $_SESSION['success'] = 'تجهیز با موفقیت ثبت شد.';
                header('Location: /support_system/assets');
                exit;
            }
            
        } catch (Exception $e) {
            // برگرداندن تراکنش در صورت خطا
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            
            // لاگ خطا برای دیباگ
            error_log("Error in AssetController::store: " . $e->getMessage());
            error_log("Error trace: " . $e->getTraceAsString());
            
            // تشخیص نوع خطا
            $errorMessage = $e->getMessage();
            if (strpos($errorMessage, 'Duplicate entry') !== false && strpos($errorMessage, 'asset_tag') !== false) {
                $userFriendlyError = 'شماره اموال تجهیز تکراری است. لطفاً شماره دیگری وارد کنید.';
            } else if (strpos($errorMessage, 'Integrity constraint violation') !== false) {
                // خطای محدودیت کلید خارجی
                if (strpos($errorMessage, 'FOREIGN KEY (`category_id`)') !== false) {
                    $userFriendlyError = 'دسته‌بندی انتخاب شده معتبر نیست. لطفاً دسته‌بندی دیگری انتخاب کنید.';
                } else if (strpos($errorMessage, 'FOREIGN KEY (`model_id`)') !== false) {
                    $userFriendlyError = 'مدل انتخاب شده معتبر نیست. لطفاً مدل دیگری انتخاب کنید.';
                } else if (strpos($errorMessage, 'FOREIGN KEY (`user_id`)') !== false) {
                    $userFriendlyError = 'کاربر انتخاب شده معتبر نیست. لطفاً کاربر دیگری انتخاب کنید.';
                } else {
                    $userFriendlyError = 'خطای محدودیت ارجاع در پایگاه داده. لطفاً اطلاعات ورودی را بررسی کنید.';
                }
            } else {
                $userFriendlyError = 'خطا در ثبت تجهیز: ' . $e->getMessage();
            }
            
            if ($this->isAjaxRequest()) {
                $this->sendJsonResponse(['error' => $userFriendlyError]);
            } else {
                $_SESSION['error'] = $userFriendlyError;
                $_SESSION['form_data'] = $_POST;
                header('Location: /support_system/assets');
                exit;
            }
        }
    }

    /**
     * ایجاد تجهیز به صورت مستقیم بدون استفاده از متد createAsset مدل Asset
     * 
     * @param PDO $pdo اتصال PDO
     * @param array $data داده‌های تجهیز
     * @return int|bool شناسه تجهیز یا false در صورت خطا
     */
    private function directCreateAsset($pdo, $data) {
        try {
            // بررسی و اطمینان از وجود فیلد status
            if (!isset($data['status']) || empty($data['status'])) {
                $data['status'] = 'available'; // وضعیت پیش‌فرض: در دسترس
                error_log("Status field was empty or not set, setting default status: available");
            }
            
            $columns = [];
            $placeholders = [];
            $values = [];
            
            foreach ($data as $key => $value) {
                if ($key === 'status' || $value !== null) {
                    $columns[] = $key;
                    $placeholders[] = ":$key";
                    $values[":$key"] = $value;
                }
            }
            
            // اطمینان از وجود فیلدهای زمانی
            if (!isset($values[":created_at"])) {
                $columns[] = "created_at";
                $placeholders[] = ":created_at";
                $values[":created_at"] = date('Y-m-d H:i:s');
            }
            
            if (!isset($values[":updated_at"])) {
                $columns[] = "updated_at";
                $placeholders[] = ":updated_at";
                $values[":updated_at"] = date('Y-m-d H:i:s');
            }
            
            // اطمینان از وجود فیلد status
            if (!isset($values[":status"])) {
                $columns[] = "status";
                $placeholders[] = ":status";
                $values[":status"] = "available"; // وضعیت پیش‌فرض: در دسترس
            }
            
            $sql = "INSERT INTO assets (" . implode(', ', $columns) . ") 
                    VALUES (" . implode(', ', $placeholders) . ")";
            
            // لاگ کوئری برای دیباگ
            error_log("SQL Query: " . $sql);
            error_log("Values: " . json_encode($values));
            
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute($values);
            
            if ($result) {
                $assetId = $pdo->lastInsertId();
                error_log("Asset created successfully with ID: " . $assetId . " and status: " . $values[':status']);
                return $assetId;
            }
            
            error_log("Failed to insert asset: " . json_encode($stmt->errorInfo()));
            return false;
        } catch (PDOException $e) {
            error_log("Error in directCreateAsset: " . $e->getMessage());
            throw $e;
        }
    }

    /**
    * اضافه کردن مشخصات تجهیز به صورت مستقیم
    * 
    * @param PDO $pdo اتصال PDO
    * @param int $assetId شناسه تجهیز
    * @param string $key کلید مشخصه
    * @param string $value مقدار مشخصه
    * @return bool نتیجه عملیات
    */
    private function directAddSpecification($pdo, $assetId, $key, $value) {
        try {
            $sql = "INSERT INTO asset_specifications (asset_id, spec_key, spec_value) 
                    VALUES (:asset_id, :spec_key, :spec_value)";
            
            $stmt = $pdo->prepare($sql);
            
            return $stmt->execute([
                ':asset_id' => $assetId,
                ':spec_key' => $key,
                ':spec_value' => $value
            ]);
        } catch (PDOException $e) {
            error_log("Error in directAddSpecification: " . $e->getMessage());
            return false;
        }
    }

    
    /**
     * ایجاد تخصیص تجهیز به کاربر به صورت مستقیم
     * 
     * @param PDO $pdo اتصال PDO
     * @param array $data داده‌های تخصیص
     * @return int|bool شناسه تخصیص یا false در صورت خطا
     */
    private function directCreateAssignment($pdo, $data) {
        try {
            // شروع تراکنش
            if (!$pdo->inTransaction()) {
                $pdo->beginTransaction();
                $localTransaction = true;
            } else {
                $localTransaction = false;
            }
            
            // بررسی آیا تجهیز قبلاً تخصیص داده شده است
            $checkSql = "SELECT id FROM asset_assignments WHERE asset_id = :asset_id AND is_current = 1";
            $checkStmt = $pdo->prepare($checkSql);
            $checkStmt->execute([':asset_id' => $data['asset_id']]);
            $existingAssignment = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            // اگر تخصیص فعلی وجود دارد، آن را پایان دهید
            if ($existingAssignment) {
                $endSql = "UPDATE asset_assignments SET 
                        is_current = 0, 
                        return_date = NOW(), 
                        return_condition = 'normal', 
                        return_notes = 'پایان یافته به دلیل تخصیص جدید',
                        updated_at = NOW()
                        WHERE id = :id";
                $endStmt = $pdo->prepare($endSql);
                $endResult = $endStmt->execute([':id' => $existingAssignment['id']]);
                
                if (!$endResult) {
                    error_log("Failed to end existing assignment: " . json_encode($endStmt->errorInfo()));
                    if ($localTransaction) {
                        $pdo->rollBack();
                    }
                    return false;
                }
            }
            
            // ساخت کوئری بر اساس فیلدهای موجود در داده‌ها
            $fields = ['asset_id', 'user_id', 'is_current'];
            $placeholders = [':asset_id', ':user_id', ':is_current'];
            $values = [
                ':asset_id' => $data['asset_id'],
                ':user_id' => $data['user_id'],
                ':is_current' => 1
            ];
            
            // اضافه کردن فیلدهای اختیاری
            if (isset($data['assigned_by'])) {
                $fields[] = 'assigned_by';
                $placeholders[] = ':assigned_by';
                $values[':assigned_by'] = $data['assigned_by'];
            }
            
            // اضافه کردن تاریخ تخصیص
            $fields[] = 'assigned_date';
            $placeholders[] = ':assigned_date';
            $values[':assigned_date'] = $data['assigned_date'] ?? date('Y-m-d H:i:s');
            
            // اضافه کردن تاریخ بازگشت مورد انتظار اگر وجود داشته باشد
            if (isset($data['expected_return_date']) && !empty($data['expected_return_date'])) {
                $fields[] = 'expected_return_date';
                $placeholders[] = ':expected_return_date';
                $values[':expected_return_date'] = $data['expected_return_date'];
            }
            
            // اضافه کردن توضیحات اگر وجود داشته باشد
            if (isset($data['notes']) && !empty($data['notes'])) {
                $fields[] = 'notes';
                $placeholders[] = ':notes';
                $values[':notes'] = $data['notes'];
            }
            
            // اضافه کردن زمان‌های ایجاد و به‌روزرسانی
            $fields[] = 'created_at';
            $placeholders[] = ':created_at';
            $values[':created_at'] = date('Y-m-d H:i:s');
            
            $fields[] = 'updated_at';
            $placeholders[] = ':updated_at';
            $values[':updated_at'] = date('Y-m-d H:i:s');
            
            // ساخت و اجرای کوئری
            $sql = "INSERT INTO asset_assignments (" . implode(', ', $fields) . ") 
                    VALUES (" . implode(', ', $placeholders) . ")";
            
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute($values);
            
            if (!$result) {
                error_log("Failed to insert assignment: " . json_encode($stmt->errorInfo()));
                if ($localTransaction) {
                    $pdo->rollBack();
                }
                return false;
            }
            
            $assignmentId = $pdo->lastInsertId();
            
            // به‌روزرسانی وضعیت و user_id در جدول assets
            $updateSql = "UPDATE assets SET status = 'assigned', user_id = :user_id, updated_at = NOW() WHERE id = :asset_id";
            $updateStmt = $pdo->prepare($updateSql);
            $updateResult = $updateStmt->execute([
                ':user_id' => $data['user_id'],
                ':asset_id' => $data['asset_id']
            ]);
            
            if (!$updateResult) {
                error_log("Failed to update asset status and user_id: " . json_encode($updateStmt->errorInfo()));
                if ($localTransaction) {
                    $pdo->rollBack();
                }
                return false;
            }
            
            // ثبت تاریخچه تغییر وضعیت
            $statusSql = "SELECT status FROM assets WHERE id = :asset_id";
            $statusStmt = $pdo->prepare($statusSql);
            $statusStmt->execute([':asset_id' => $data['asset_id']]);
            $oldStatus = $statusStmt->fetchColumn();
            
            if ($oldStatus !== 'assigned') {
                $historySql = "INSERT INTO asset_status_history (
                            asset_id, old_status, new_status, reason, changed_by, changed_at
                        ) VALUES (
                            :asset_id, :old_status, 'assigned', :reason, :changed_by, NOW()
                        )";
                
                $historyStmt = $pdo->prepare($historySql);
                $historyResult = $historyStmt->execute([
                    ':asset_id' => $data['asset_id'],
                    ':old_status' => $oldStatus,
                    ':reason' => 'تخصیص به کاربر',
                    ':changed_by' => $data['assigned_by'] ?? null
                ]);
                
                if (!$historyResult) {
                    error_log("Failed to insert status history: " . json_encode($historyStmt->errorInfo()));
                    // ادامه می‌دهیم حتی اگر ثبت تاریخچه با خطا مواجه شود
                }
            }
            
            // تایید تراکنش اگر ما آن را شروع کرده‌ایم
            if ($localTransaction) {
                $pdo->commit();
            }
            
            return $assignmentId;
            
        } catch (PDOException $e) {
            error_log("Error in directCreateAssignment: " . $e->getMessage());
            if ($localTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return false;
        }
    }

    /**
     * به‌روزرسانی وضعیت تجهیز بدون شروع تراکنش جدید
     * 
     * @param int $assetId شناسه تجهیز
     * @param string $status وضعیت جدید
     * @return bool نتیجه عملیات
     */
    private function directUpdateAssetStatus($assetId, $status) {
        try {
            $db = Database::getInstance();
            
            $sql = "UPDATE assets SET status = :status WHERE id = :id";
            $stmt = $db->prepare($sql);
            
            return $stmt->execute([
                ':status' => $status,
                ':id' => $assetId
            ]);
        } catch (PDOException $e) {
            error_log("Error in directUpdateAssetStatus: " . $e->getMessage());
            return false;
        }
    }

    /**
     * بررسی می‌کند که آیا درخواست از نوع AJAX است یا خیر
     * 
     * @return bool
     */
    private function isAjaxRequest() {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * ارسال پاسخ JSON و خروج
     * 
     * @param array $data
     */
    private function sendJsonResponse($data) {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /**
     * نمایش فرم ویرایش تجهیز
     */
    public function edit($id) {
        // بررسی دسترسی
        if (!$this->accessControl->hasPermission('edit_assets')) {
            header('Location: /support_system/assets');
            exit;
        }
        
        // دریافت اطلاعات تجهیز
        $asset = $this->assetModel->getAssetById($id);
        
        if (!$asset) {
            $_SESSION['error'] = 'تجهیز مورد نظر یافت نشد.';
            header('Location: /support_system/assets');
            exit;
        }
        
        // دریافت مشخصات تجهیز
        $specifications = $this->specModel->getSpecificationsByAssetId($id);
        
        // دریافت تخصیص فعلی
        $currentAssignment = $this->assignmentModel->getCurrentAssignment($id);
        
        // دریافت لیست دسته‌بندی‌ها
        $categories = $this->categoryModel->getAllCategories();
        
        // دریافت لیست مدل‌ها
        $models = $this->modelModel->getAllModels();
        
        // گروه‌بندی مدل‌ها بر اساس دسته‌بندی
        $modelsByCategory = [];
        foreach ($models as $model) {
            if (!isset($modelsByCategory[$model['category_id']])) {
                $modelsByCategory[$model['category_id']] = [];
            }
            $modelsByCategory[$model['category_id']][] = $model;
        }
        
        // دریافت لیست کاربران برای تخصیص تجهیز و استخراج plant و unit
        // استفاده از متد جدید getAllUsersForDropdown به جای getAllUsers
        $users = $this->userModel->getAllUsersForDropdown();
        
        // استخراج لیست plant ها و unit ها از کاربران
        $plants = [];
        $units = [];
        foreach ($users as $user) {
            if (!empty($user['plant']) && !in_array($user['plant'], $plants)) {
                $plants[] = $user['plant'];
            }
            if (!empty($user['unit']) && !in_array($user['unit'], $units)) {
                $units[] = $user['unit'];
            }
        }
        
        // مرتب‌سازی لیست‌ها
        sort($plants);
        sort($units);
        
        // بررسی نوع درخواست
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            // اگر درخواست AJAX است، پاسخ JSON برگردان
            header('Content-Type: application/json');
            echo json_encode([
                'asset' => $asset,
                'specifications' => $specifications,
                'currentAssignment' => $currentAssignment,
                'categories' => $categories,
                'models' => $models,
                'modelsByCategory' => $modelsByCategory,
                'plants' => $plants,
                'units' => $units,
                'users' => $users
            ]);
            exit;
        } else {
            // در غیر این صورت، نمایش صفحه ویرایش تجهیز
            $data = [
                'asset' => $asset,
                'specifications' => $specifications,
                'currentAssignment' => $currentAssignment,
                'categories' => $categories,
                'models' => $models,
                'modelsByCategory' => $modelsByCategory,
                'plants' => $plants,
                'units' => $units,
                'users' => $users
            ];
            
            // نمایش نما
            require_once __DIR__ . '/../views/assets/edit.php';
        }
    }

    /**
     * به‌روزرسانی تجهیز
     */
    public function update($id) {
        // بررسی دسترسی
        if (!$this->accessControl->hasPermission('edit_assets')) {
            header('Location: /support_system/assets');
            exit;
        }
        
        // بررسی درخواست POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /support_system/assets/edit/' . $id);
            exit;
        }
        
        // دریافت اطلاعات تجهیز
        $asset = $this->assetModel->getAssetById($id);
        
        if (!$asset) {
            $_SESSION['error'] = 'تجهیز مورد نظر یافت نشد.';
            header('Location: /support_system/assets');
            exit;
        }
        
        // اعتبارسنجی داده‌ها
        $errors = [];
        
        if (empty($_POST['name'])) {
            $errors[] = 'نام تجهیز الزامی است.';
        }
        
        if (empty($_POST['asset_tag'])) {
            $errors[] = 'اموال تجهیز الزامی است.';
        } else {
            // بررسی تکراری نبودن برچسب تجهیز (به جز خود این تجهیز)
            if ($_POST['asset_tag'] !== $asset['asset_tag'] && $this->assetModel->isAssetTagExists($_POST['asset_tag'])) {
                $errors[] = 'اموال تجهیز تکراری است.';
            }
        }
        
        if (empty($_POST['model_id'])) {
            $errors[] = 'انتخاب مدل الزامی است.';
        }
        
        if (!empty($errors)) {
            // بررسی نوع درخواست
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                // اگر درخواست AJAX است، پاسخ JSON با خطا برگردان
                header('Content-Type: application/json');
                echo json_encode(['errors' => $errors]);
                exit;
            } else {
                // در غیر این صورت، هدایت به صفحه ویرایش تجهیز
                $_SESSION['errors'] = $errors;
                $_SESSION['form_data'] = $_POST;
                header('Location: /support_system/assets/edit/' . $id);
                exit;
            }
        }
        
        // آماده‌سازی داده‌ها
        $assetData = [
            'id' => $id,
            'name' => $_POST['name'],
            'asset_tag' => $_POST['asset_tag'],
            'serial_number' => $_POST['serial_number'] ?? '',
            'model_id' => $_POST['model_id'],
            'status' => $_POST['status'] ?? $asset['status'],
            'purchase_date' => $_POST['purchase_date'] ?? null,
            'plant' => $_POST['plant'] ?? null,
            'unit' => $_POST['unit'] ?? null,
            'notes' => $_POST['notes'] ?? '',
            'asset_type' => $_POST['asset_type'] ?? $asset['asset_type'],
            'updated_by' => $_SESSION['user_id']
        ];

        // حذف supplier_id از داده‌های ارسالی (اضافه کردن این خط)
        if (isset($assetData['supplier_id'])) {
            unset($assetData['supplier_id']);
        }

        // لاگ داده‌های ارسالی برای دیباگ
        error_log("Asset Data: " . json_encode($assetData));
        
        // آپلود تصویر
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../../public/uploads/assets/';
            $fileName = uniqid() . '_' . basename($_FILES['image']['name']);
            $uploadFile = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadFile)) {
                $assetData['image'] = '/uploads/assets/' . $fileName;
                
                // حذف تصویر قبلی
                if (!empty($asset['image'])) {
                    $oldImagePath = __DIR__ . '/../../public' . $asset['image'];
                    if (file_exists($oldImagePath)) {
                        unlink($oldImagePath);
                    }
                }
            }
        }
        
        // به‌روزرسانی تجهیز
        $result = $this->assetModel->updateAsset($assetData);
        
        if (!$result) {
            // بررسی نوع درخواست
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                // اگر درخواست AJAX است، پاسخ JSON با خطا برگردان
                header('Content-Type: application/json');
                echo json_encode(['error' => 'خطا در به‌روزرسانی تجهیز. لطفاً دوباره تلاش کنید.']);
                exit;
            } else {
                // در غیر این صورت، هدایت به صفحه ویرایش تجهیز
                $_SESSION['error'] = 'خطا در به‌روزرسانی تجهیز. لطفاً دوباره تلاش کنید.';
                $_SESSION['form_data'] = $_POST;
                header('Location: /support_system/assets/edit/' . $id);
                exit;
            }
        }
        
        // به‌روزرسانی مشخصات تجهیز
        if (isset($_POST['specs']) && is_array($_POST['specs'])) {
            // حذف مشخصات قبلی
            $this->specModel->deleteSpecificationsByAssetId($id);
            
            // افزودن مشخصات جدید
            foreach ($_POST['specs'] as $key => $value) {
                if (!empty($value)) {
                    $this->specModel->addSpecification($id, $key, $value);
                }
            }
        }
        
        // بررسی تغییر تخصیص
        $currentAssignment = $this->assignmentModel->getCurrentAssignment($id);
        
        if (!empty($_POST['assigned_to'])) {
            // اگر تخصیص فعلی وجود ندارد یا کاربر تغییر کرده است
            if (!$currentAssignment || $currentAssignment['user_id'] != $_POST['assigned_to']) {
                // اگر تخصیص فعلی وجود دارد، آن را پایان دهید
                if ($currentAssignment) {
                    $this->assignmentModel->unassignAsset($currentAssignment['id']);
                }
                
                // تخصیص جدید
                $assignmentData = [
                    'asset_id' => $id,
                    'user_id' => $_POST['assigned_to'],
                    'assigned_by' => $_SESSION['user_id'],
                    'notes' => $_POST['assignment_notes'] ?? '',
                    'expected_return_date' => $_POST['expected_return_date'] ?? null
                ];
                
                $this->assignmentModel->assignAsset($assignmentData);
                
                // به‌روزرسانی وضعیت تجهیز
                $this->assetModel->updateAssetStatus($id, 'assigned');
            }
        } elseif ($currentAssignment && isset($_POST['unassign']) && $_POST['unassign'] === '1') {
            // لغو تخصیص فعلی
            $this->assignmentModel->unassignAsset($currentAssignment['id']);
            
            // به‌روزرسانی وضعیت تجهیز
            $this->assetModel->updateAssetStatus($id, 'available');
        }
        
        // بررسی نوع درخواست
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            // اگر درخواست AJAX است، پاسخ JSON با موفقیت برگردان
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'تجهیز با موفقیت به‌روزرسانی شد.'
            ]);
            exit;
        } else {
            // در غیر این صورت، هدایت به صفحه نمایش تجهیز
            $_SESSION['success'] = 'تجهیز با موفقیت به‌روزرسانی شد.';
            header('Location: /support_system/assets/show/' . $id);
            exit;
        }
    }

    /**
     * حذف تجهیز
     */
    public function delete($id) {
        // بررسی دسترسی
        if (!$this->accessControl->hasPermission('delete_assets')) {
            header('Location: /support_system/assets');
            exit;
        }
        
        // دریافت اطلاعات تجهیز
        $asset = $this->assetModel->getAssetById($id);
        
        if (!$asset) {
            $_SESSION['error'] = 'تجهیز مورد نظر یافت نشد.';
            header('Location: /support_system/assets');
            exit;
        }
        
        // بررسی امکان حذف (مثلاً اگر به کاربری تخصیص داده شده باشد)
        $currentAssignment = $this->assignmentModel->getCurrentAssignment($id);
        if ($currentAssignment) {
            $_SESSION['error'] = 'این تجهیز به کاربر تخصیص داده شده است و نمی‌توان آن را حذف کرد.';
            header('Location: /support_system/assets/show/' . $id);
            exit;
        }
        
        // حذف مشخصات تجهیز
        $this->specModel->deleteSpecificationsByAssetId($id);
        
        // حذف تاریخچه تخصیص
        $this->assignmentModel->deleteAssignmentHistoryByAssetId($id);
        
        // حذف تصویر تجهیز
        if (!empty($asset['image'])) {
            $imagePath = __DIR__ . '/../../public' . $asset['image'];
            if (file_exists($imagePath)) {
                unlink($imagePath);
            }
        }
        
        // حذف تجهیز
        $result = $this->assetModel->deleteAsset($id);
        
        if (!$result) {
            $_SESSION['error'] = 'خطا در حذف تجهیز. لطفاً دوباره تلاش کنید.';
            header('Location: /support_system/assets/show/' . $id);
            exit;
        }
        
        $_SESSION['success'] = 'تجهیز با موفقیت حذف شد.';
        header('Location: /support_system/assets');
        exit;
    }

    /**
     * مدیریت مشخصات تجهیز
     */
    public function manageSpecs($id) {
        // بررسی دسترسی
        if (!$this->accessControl->hasPermission('edit_assets')) {
            header('Location: /support_system/assets');
            exit;
        }
        
        // دریافت اطلاعات تجهیز
        $asset = $this->assetModel->getAssetById($id);
        
        if (!$asset) {
            $_SESSION['error'] = 'تجهیز مورد نظر یافت نشد.';
            header('Location: /support_system/assets');
            exit;
        }
        
        // دریافت مشخصات تجهیز
        $specifications = $this->specModel->getSpecificationsByAssetId($id);
        
        // دریافت مشخصات پیش‌فرض مدل
        $modelDefaultSpecs = $this->modelModel->getModelDefaultSpecs($asset['model_id']);
        
        // انتقال داده‌ها به نما
        $data = [
            'asset' => $asset,
            'specifications' => $specifications,
            'modelDefaultSpecs' => $modelDefaultSpecs
        ];
        
        // نمایش نما
        require_once __DIR__ . '/../views/assets/manage_specs.php';
    }

    /**
     * ذخیره مشخصات تجهیز
     */
    public function saveSpecs($id) {
        // بررسی دسترسی
        if (!$this->accessControl->hasPermission('edit_assets')) {
            header('Location: /support_system/assets');
            exit;
        }
        
        // بررسی درخواست POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /support_system/assets/manage_specs/' . $id);
            exit;
        }
        
        // دریافت اطلاعات تجهیز
        $asset = $this->assetModel->getAssetById($id);
        
        if (!$asset) {
            $_SESSION['error'] = 'تجهیز مورد نظر یافت نشد.';
            header('Location: /support_system/assets');
            exit;
        }
        
        // حذف مشخصات قبلی
        $this->specModel->deleteSpecificationsByAssetId($id);
        
        // افزودن مشخصات جدید
        if (isset($_POST['specs']) && is_array($_POST['specs'])) {
            foreach ($_POST['specs'] as $key => $value) {
                if (!empty($value)) {
                    $this->specModel->addSpecification($id, $key, $value);
                }
            }
        }
        
        $_SESSION['success'] = 'مشخصات تجهیز با موفقیت به‌روزرسانی شد.';
        header('Location: /support_system/assets/show/' . $id);
        exit;
    }

    /**
     * تخصیص تجهیز به کاربر
     */
    public function assign($id) {
        // بررسی دسترسی
        if (!$this->accessControl->hasPermission('assign_assets')) {
            header('Location: /support_system/assets');
            exit;
        }
        
        // بررسی درخواست POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /support_system/assets/show/' . $id);
            exit;
        }
        
        // دریافت اطلاعات تجهیز
        $asset = $this->assetModel->getAssetById($id);
        
        if (!$asset) {
            $_SESSION['error'] = 'تجهیز مورد نظر یافت نشد.';
            header('Location: /support_system/assets');
            exit;
        }
        
        // بررسی وضعیت تجهیز
        if ($asset['status'] === 'assigned') {
            // بررسی تخصیص فعلی
            $currentAssignment = $this->assignmentModel->getCurrentAssignment($id);
            if ($currentAssignment) {
                // لغو تخصیص فعلی
                $this->assignmentModel->unassignAsset($currentAssignment['id']);
            }
        }
        
        // اعتبارسنجی داده‌ها
        if (empty($_POST['user_id'])) {
            $_SESSION['error'] = 'انتخاب کاربر الزامی است.';
            header('Location: /support_system/assets/show/' . $id);
            exit;
        }
        
        // آماده‌سازی داده‌ها
        $assignmentData = [
            'asset_id' => $id,
            'user_id' => $_POST['user_id'],
            'assigned_by' => $_SESSION['user_id'],
            'notes' => $_POST['notes'] ?? '',
            'expected_return_date' => $_POST['expected_return_date'] ?? null
        ];
        
        // تخصیص تجهیز
        $result = $this->assignmentModel->assignAsset($assignmentData);
        
        if (!$result) {
            $_SESSION['error'] = 'خطا در تخصیص تجهیز. لطفاً دوباره تلاش کنید.';
            header('Location: /support_system/assets/show/' . $id);
            exit;
        }
        
        // به‌روزرسانی وضعیت تجهیز
        $this->assetModel->updateAssetStatus($id, 'assigned');
        
        $_SESSION['success'] = 'تجهیز با موفقیت به کاربر تخصیص داده شد.';
        header('Location: /support_system/assets/show/' . $id);
        exit;
    }

    /**
     * لغو تخصیص تجهیز
     */
    public function unassign($id) {
        // بررسی دسترسی
        if (!$this->accessControl->hasPermission('assign_assets')) {
            header('Location: /support_system/assets');
            exit;
        }
        
        // دریافت اطلاعات تجهیز
        $asset = $this->assetModel->getAssetById($id);
        
        if (!$asset) {
            $_SESSION['error'] = 'تجهیز مورد نظر یافت نشد.';
            header('Location: /support_system/assets');
            exit;
        }
        
        // بررسی وضعیت تجهیز
        if ($asset['status'] !== 'assigned') {
            $_SESSION['error'] = 'این تجهیز به کاربری تخصیص داده نشده است.';
            header('Location: /support_system/assets/show/' . $id);
            exit;
        }
        
        // بررسی تخصیص فعلی
        $currentAssignment = $this->assignmentModel->getCurrentAssignment($id);
        if (!$currentAssignment) {
            $_SESSION['error'] = 'تخصیص فعلی یافت نشد.';
            header('Location: /support_system/assets/show/' . $id);
            exit;
        }
        
        // لغو تخصیص
        $result = $this->assignmentModel->unassignAsset($currentAssignment['id']);
        
        if (!$result) {
            $_SESSION['error'] = 'خطا در لغو تخصیص تجهیز. لطفاً دوباره تلاش کنید.';
            header('Location: /support_system/assets/show/' . $id);
            exit;
        }
        
        // به‌روزرسانی وضعیت تجهیز
        $this->assetModel->updateAssetStatus($id, 'available');
        
        $_SESSION['success'] = 'تخصیص تجهیز با موفقیت لغو شد.';
        header('Location: /support_system/assets/show/' . $id);
        exit;
    }

    /**
     * نمایش گزارش‌های تجهیز‌ها
     */
    public function reports() {
        // بررسی دسترسی
        if (!$this->accessControl->hasPermission('view_asset_reports')) {
            header('Location: /support_system/assets');
            exit;
        }
        
        // دریافت نوع گزارش
        $reportType = $_GET['type'] ?? 'all';
        
        // دریافت داده‌های گزارش
        $reportData = [];
        
        switch ($reportType) {
            case 'expiring_warranty':
                // تجهیز‌های با گارانتی در حال انقضا
                $days = $_GET['days'] ?? 30;
                $reportData = $this->assetModel->getAssetsWithExpiringWarranty($days);
                break;
                
            case 'by_category':
                // تجهیز‌ها بر اساس دسته‌بندی
                $reportData = $this->assetModel->getAssetCountsByCategory();
                break;
                
            case 'by_status':
                // تجهیز‌ها بر اساس وضعیت
                $reportData = $this->assetModel->getAssetCountsByStatus();
                break;
                
            case 'by_model':
                // تجهیز‌ها بر اساس مدل
                $reportData = $this->assetModel->getAssetCountsByModel();
                break;
                
            case 'by_vendor':
                // تجهیز‌ها بر اساس فروشنده
                $reportData = $this->assetModel->getAssetCountsByVendor();
                break;
                
            case 'by_location':
                // تجهیز‌ها بر اساس مکان
                $reportData = $this->assetModel->getAssetCountsByLocation();
                break;
                
            case 'by_age':
                // تجهیز‌ها بر اساس سن
                $reportData = $this->assetModel->getAssetsByAge();
                break;
                
            case 'maintenance_due':
                // تجهیز‌های نیازمند تعمیر و نگهداری
                $reportData = $this->assetModel->getAssetsWithMaintenanceDue();
                break;
                
            case 'unassigned':
                // تجهیز‌های تخصیص‌نیافته
                $reportData = $this->assetModel->getUnassignedAssets();
                break;
                
            case 'assigned':
                // تجهیز‌های تخصیص‌یافته
                $reportData = $this->assetModel->getAssignedAssets();
                break;
                
            default:
                // همه تجهیز‌ها
                $reportData = $this->assetModel->getAllAssetsForReport();
                break;
        }
        
        // انتقال داده‌ها به نما
        $data = [
            'reportType' => $reportType,
            'reportData' => $reportData
        ];
        
        // نمایش نما
        require_once __DIR__ . '/../views/assets/reports.php';
    }

    /**
     * نمایش آمار تجهیز‌ها
     */
    public function statistics() {
        // بررسی دسترسی
        if (!$this->accessControl->hasPermission('view_asset_statistics')) {
            header('Location: /support_system/assets');
            exit;
        }
        
        // دریافت آمار کلی
        $totalAssets = $this->assetModel->getTotalAssetsCount();
        $assignedAssets = $this->assetModel->getAssetsCountByStatus('assigned');
        $availableAssets = $this->assetModel->getAssetsCountByStatus('available');
        $maintenanceAssets = $this->assetModel->getAssetsCountByStatus('maintenance');
        $retiredAssets = $this->assetModel->getAssetsCountByStatus('retired');
        
        // دریافت آمار بر اساس دسته‌بندی
        $assetsByCategory = $this->assetModel->getAssetCountsByCategory();
        
        // دریافت آمار بر اساس مدل
        $assetsByModel = $this->assetModel->getAssetCountsByModel();
        
        // دریافت آمار بر اساس فروشنده
        $assetsByVendor = $this->assetModel->getAssetCountsByVendor();
        
        // دریافت آمار بر اساس مکان
        $assetsByLocation = $this->assetModel->getAssetCountsByLocation();
        
        // دریافت آمار بر اساس سن
        $assetsByAge = $this->assetModel->getAssetsByAge();
        
        // دریافت آمار گارانتی
        $assetsWithWarranty = $this->assetModel->getAssetsWithWarrantyCount();
        $assetsWithoutWarranty = $totalAssets - $assetsWithWarranty;
        $assetsWithExpiringWarranty = count($this->assetModel->getAssetsWithExpiringWarranty(30));
        
        // انتقال داده‌ها به نما
        $data = [
            'totalAssets' => $totalAssets,
            'assignedAssets' => $assignedAssets,
            'availableAssets' => $availableAssets,
            'maintenanceAssets' => $maintenanceAssets,
            'retiredAssets' => $retiredAssets,
            'assetsByCategory' => $assetsByCategory,
            'assetsByModel' => $assetsByModel,
            'assetsByVendor' => $assetsByVendor,
            'assetsByLocation' => $assetsByLocation,
            'assetsByAge' => $assetsByAge,
            'assetsWithWarranty' => $assetsWithWarranty,
            'assetsWithoutWarranty' => $assetsWithoutWarranty,
            'assetsWithExpiringWarranty' => $assetsWithExpiringWarranty
        ];
        
        // نمایش نما
        require_once __DIR__ . '/../views/assets/statistics.php';
    }

    /**
     * نمایش تجهیز‌های نیازمند توجه
     */
    public function attentionNeeded() {
        // بررسی دسترسی
        if (!$this->accessControl->hasPermission('view_assets')) {
            header('Location: /support_system/dashboard');
            exit;
        }
        
        // تجهیز‌های با گارانتی در حال انقضا
        $expiringWarranty = $this->assetModel->getAssetsWithExpiringWarranty(30);
        
        // تجهیز‌های نیازمند تعمیر و نگهداری
        $maintenanceDue = $this->assetModel->getAssetsWithMaintenanceDue();
        
        // تجهیز‌های با وضعیت تعمیر
        $inMaintenance = $this->assetModel->getAssetsByStatus('maintenance');
        
        // تجهیز‌های با مشکلات گزارش‌شده
        $withIssues = $this->assetModel->getAssetsWithReportedIssues();
        
        // انتقال داده‌ها به نما
        $data = [
            'expiringWarranty' => $expiringWarranty,
            'maintenanceDue' => $maintenanceDue,
            'inMaintenance' => $inMaintenance,
            'withIssues' => $withIssues
        ];
        
        // نمایش نما
        require_once __DIR__ . '/../views/assets/attention_needed.php';
    }

    /**
     * نمایش تجهیز‌های با گارانتی در حال انقضا
     */
    public function expiringWarranty() {
        // بررسی دسترسی
        if (!$this->accessControl->hasPermission('view_assets')) {
            header('Location: /support_system/dashboard');
            exit;
        }
        
        // دریافت تعداد روزهای آینده
        $days = $_GET['days'] ?? 30;
        
        // تجهیز‌های با گارانتی در حال انقضا
        $assets = $this->assetModel->getAssetsWithExpiringWarranty($days);
        
        // انتقال داده‌ها به نما
        $data = [
            'assets' => $assets,
            'days' => $days
        ];
        
        // نمایش نما
        require_once __DIR__ . '/../views/assets/expiring_warranty.php';
    }

    /**
     * دریافت اطلاعات تجهیز به صورت JSON (برای استفاده در AJAX)
     */
    public function getAssetInfo($id) {
        // بررسی دسترسی
        if (!$this->accessControl->hasPermission('view_assets')) {
            echo json_encode(['error' => 'دسترسی غیرمجاز']);
            exit;
        }
        
        // دریافت اطلاعات تجهیز
        $asset = $this->assetModel->getAssetById($id);
        
        if (!$asset) {
            echo json_encode(['error' => 'تجهیز یافت نشد']);
            exit;
        }
        
        // دریافت مشخصات تجهیز
        $specifications = $this->specModel->getSpecificationsByAssetId($id);
        
        // دریافت تخصیص فعلی
        $currentAssignment = $this->assignmentModel->getCurrentAssignment($id);
        
        // آماده‌سازی پاسخ
        $response = [
            'asset' => $asset,
            'specifications' => $specifications,
            'currentAssignment' => $currentAssignment
        ];
        
        // ارسال پاسخ به صورت JSON
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    /**
     * دریافت لیست تجهیز‌ها به صورت JSON (برای استفاده در AJAX)
     */
    public function getAssetsList() {
        // بررسی دسترسی
        if (!$this->accessControl->hasPermission('view_assets')) {
            echo json_encode(['error' => 'دسترسی غیرمجاز']);
            exit;
        }
        
        // دریافت پارامترها
        $search = $_GET['search'] ?? '';
        $status = $_GET['status'] ?? '';
        $category = $_GET['category'] ?? '';
        
        // دریافت لیست تجهیز‌ها
        $assets = $this->assetModel->searchAssets($search, $status, $category);
        
        // ارسال پاسخ به صورت JSON
        header('Content-Type: application/json');
        echo json_encode(['assets' => $assets]);
        exit;
    }

    /**
     * چاپ برچسب QR برای تجهیز
     */
    public function printQrCode($id) {
        // بررسی دسترسی
        if (!$this->accessControl->hasPermission('view_assets')) {
            header('Location: /support_system/assets');
            exit;
        }
        
        // دریافت اطلاعات تجهیز
        $asset = $this->assetModel->getAssetById($id);
        
        if (!$asset) {
            $_SESSION['error'] = 'تجهیز مورد نظر یافت نشد.';
            header('Location: /support_system/assets');
            exit;
        }
        
        // تولید کد QR
        $qrCode = $this->qrGenerator->generateAssetQR($asset);
        
        // انتقال داده‌ها به نما
        $data = [
            'asset' => $asset,
            'qrCode' => $qrCode
        ];
        
        // نمایش نما
        require_once __DIR__ . '/../views/assets/print_qr.php';
    }

    /**
     * ثبت تغییرات سخت‌افزاری
     */
    public function recordHardwareChange($id) {
        // بررسی دسترسی
        if (!$this->accessControl->hasPermission('edit_assets')) {
            header('Location: /support_system/assets');
            exit;
        }
        
        // بررسی درخواست POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /support_system/assets/show/' . $id);
            exit;
        }
        
        // دریافت اطلاعات تجهیز
        $asset = $this->assetModel->getAssetById($id);
        
        if (!$asset) {
            $_SESSION['error'] = 'تجهیز مورد نظر یافت نشد.';
            header('Location: /support_system/assets');
            exit;
        }
        
        // اعتبارسنجی داده‌ها
        if (empty($_POST['component_type']) || empty($_POST['description'])) {
            $_SESSION['error'] = 'نوع قطعه و توضیحات الزامی است.';
            header('Location: /support_system/assets/show/' . $id);
            exit;
        }
        
        // آماده‌سازی داده‌ها
        $changeData = [
            'asset_id' => $id,
            'component_type' => $_POST['component_type'],
            'old_value' => $_POST['old_value'] ?? '',
            'new_value' => $_POST['new_value'] ?? '',
            'description' => $_POST['description'],
            'change_date' => $_POST['change_date'] ?? date('Y-m-d'),
            'changed_by' => $_SESSION['user_id'],
            'cost' => $_POST['cost'] ?? null
        ];
        
        // ثبت تغییر سخت‌افزاری
        $result = $this->assetModel->recordHardwareChange($changeData);
        
        if (!$result) {
            $_SESSION['error'] = 'خطا در ثبت تغییرات سخت‌افزاری. لطفاً دوباره تلاش کنید.';
            header('Location: /support_system/assets/show/' . $id);
            exit;
        }
        
        // به‌روزرسانی مشخصات تجهیز اگر لازم باشد
        if (!empty($_POST['update_specs']) && $_POST['update_specs'] === '1' && !empty($_POST['component_type']) && !empty($_POST['new_value'])) {
            $this->specModel->updateSpecification($id, $_POST['component_type'], $_POST['new_value']);
        }
        
        $_SESSION['success'] = 'تغییرات سخت‌افزاری با موفقیت ثبت شد.';
        header('Location: /support_system/assets/show/' . $id);
        exit;
    }

    /**
     * دریافت مدل‌ها بر اساس دسته‌بندی برای استفاده در AJAX
     */
    public function getModelsByCategoryApi() {
        // بررسی دسترسی
        if (!$this->accessControl->hasPermission('view_assets')) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'دسترسی غیرمجاز']);
            exit;
        }
        
        // دریافت شناسه دسته‌بندی
        $categoryId = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
        
        if ($categoryId <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'شناسه دسته‌بندی نامعتبر است']);
            exit;
        }
        
        try {
            // دریافت مدل‌های مرتبط با دسته‌بندی
            $models = $this->modelModel->getModelsByCategory($categoryId);
            
            // ارسال پاسخ به صورت JSON
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'models' => $models]);
            exit;
        } catch (Exception $e) {
            error_log("Error in getModelsByCategoryApi: " . $e->getMessage());
            header('Content-Type: application/json');
            echo json_encode(['error' => 'خطا در دریافت مدل‌ها']);
            exit;
        }
    }

    /**
     * دریافت مدل‌ها بر اساس دسته‌بندی
     */
    public function getModelsByCategory() {
        // بررسی دسترسی
        if (!$this->accessControl->hasPermission('view_assets')) {
            $this->jsonResponse(['error' => 'دسترسی غیرمجاز'], 403);
            return;
        }
        
        // دریافت شناسه دسته‌بندی از درخواست
        $categoryId = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
        
        // بررسی اعتبار شناسه دسته‌بندی
        if ($categoryId <= 0) {
            $this->jsonResponse(['error' => 'شناسه دسته‌بندی نامعتبر است'], 400);
            return;
        }
        
        try {
            // دریافت مدل‌های مرتبط با دسته‌بندی
            $models = $this->modelModel->getModelsByCategoryId($categoryId);
            
            // ارسال پاسخ
            $this->jsonResponse([
                'models' => $models,
                'success' => true
            ]);
        } catch (Exception $e) {
            error_log("Error in getModelsByCategory: " . $e->getMessage());
            $this->jsonResponse(['error' => 'خطا در دریافت مدل‌ها'], 500);
        }
    }

    /**
     * ارسال پاسخ JSON
     * 
     * @param mixed $data داده‌های مورد نظر برای ارسال
     * @param int $statusCode کد وضعیت HTTP
     */
    private function jsonResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    // متد جدید برای اطمینان از وجود داده‌های پیش‌فرض
    private function ensureDefaultData() {
        // بررسی وجود دسته‌بندی‌ها
        $categories = $this->categoryModel->getAllCategories();
        if (empty($categories)) {
            // اضافه کردن دسته‌بندی پیش‌فرض
            $this->categoryModel->addCategory([
                'name' => 'کامپیوتر',
                'description' => 'کامپیوترهای دسکتاپ و لپ‌تاپ'
            ]);
            
            $this->categoryModel->addCategory([
                'name' => 'پرینتر',
                'description' => 'پرینترها و اسکنرها'
            ]);
            
            $this->categoryModel->addCategory([
                'name' => 'تجهیزات شبکه',
                'description' => 'روترها، سوئیچ‌ها و سایر تجهیزات شبکه'
            ]);
        }
        
        // بررسی وجود مدل‌ها
        $models = $this->modelModel->getAllModels();
        if (empty($models)) {
            // دریافت دسته‌بندی‌ها
            $categories = $this->categoryModel->getAllCategories();
            
            // یافتن شناسه دسته‌بندی کامپیوتر
            $computerCategoryId = null;
            foreach ($categories as $category) {
                if ($category['name'] === 'کامپیوتر') {
                    $computerCategoryId = $category['id'];
                    break;
                }
            }
            
            if ($computerCategoryId) {
                // اضافه کردن مدل پیش‌فرض
                $this->modelModel->addModel([
                    'name' => 'دسکتاپ عمومی',
                    'category_id' => $computerCategoryId,
                    'manufacturer' => 'عمومی',
                    'model_number' => 'PC-001',
                    'description' => 'کامپیوتر دسکتاپ عمومی'
                ]);
            }
        }
    }

    /**
     * دریافت مدل‌ها بر اساس دسته‌بندی
     */
    public function getModels() {
        // بررسی دسترسی
        if (!$this->accessControl->hasPermission('view_assets')) {
            http_response_code(403);
            echo json_encode(['error' => 'دسترسی غیرمجاز']);
            exit;
        }
        
        // دریافت شناسه دسته‌بندی از درخواست
        $categoryId = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
        
        // بررسی اعتبار شناسه دسته‌بندی
        if ($categoryId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'شناسه دسته‌بندی نامعتبر است']);
            exit;
        }
        
        try {
            // دریافت مدل‌های مرتبط با دسته‌بندی
            $models = $this->modelModel->getModelsByCategoryId($categoryId);
            
            // ارسال پاسخ
            header('Content-Type: application/json');
            echo json_encode($models);
        } catch (Exception $e) {
            error_log("Error in getModels: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'خطا در دریافت مدل‌ها']);
        }
        exit;
    }

    /**
     * دریافت داده‌های مودال افزودن تجهیز
     */
    public function getModalData() {
        // بررسی دسترسی
        if (!$this->accessControl->hasPermission('view_assets')) {
            $this->jsonResponse(['error' => 'دسترسی غیرمجاز'], 403);
            return;
        }
        
        try {
            // دریافت لیست دسته‌بندی‌ها
            $categories = $this->categoryModel->getAllCategories();
            
            // دریافت لیست مدل‌ها
            $models = $this->modelModel->getAllModels();
            
            // گروه‌بندی مدل‌ها بر اساس دسته‌بندی
            $modelsByCategory = [];
            foreach ($models as $model) {
                if (!isset($modelsByCategory[$model['category_id']])) {
                    $modelsByCategory[$model['category_id']] = [];
                }
                $modelsByCategory[$model['category_id']][] = $model;
            }
            
            // دریافت لیست کاربران برای تخصیص تجهیز و استخراج plant و unit
            // استفاده از متد جدید getAllUsersForDropdown به جای getAllUsers
            $users = $this->userModel->getAllUsersForDropdown();
            
            // استخراج لیست plant ها و unit ها از کاربران
            $plants = [];
            $units = [];
            foreach ($users as $user) {
                if (!empty($user['plant']) && !in_array($user['plant'], $plants)) {
                    $plants[] = $user['plant'];
                }
                if (!empty($user['unit']) && !in_array($user['unit'], $units)) {
                    $units[] = $user['unit'];
                }
            }
            
            // مرتب‌سازی لیست‌ها
            sort($plants);
            sort($units);
            
            // ارسال پاسخ
            $this->jsonResponse([
                'categories' => $categories,
                'models' => $models,
                'modelsByCategory' => $modelsByCategory,
                'plants' => $plants,
                'units' => $units,
                'users' => $users,
                'success' => true
            ]);
        } catch (Exception $e) {
            error_log("Error in getModalData: " . $e->getMessage());
            $this->jsonResponse(['error' => 'خطا در دریافت داده‌ها'], 500);
        }
    }

    /**
     * افزودن دسته‌بندی جدید از طریق AJAX
     */
    public function addCategory() {
        // بررسی دسترسی
        if (!$this->accessControl->hasPermission('create_assets')) {
            $this->jsonResponse(['success' => false, 'message' => 'دسترسی غیرمجاز'], 403);
            return;
        }
        
        // بررسی درخواست POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['success' => false, 'message' => 'روش درخواست نامعتبر است'], 405);
            return;
        }
        
        // دریافت داده‌ها
        $name = $_POST['name'] ?? '';
        $description = $_POST['description'] ?? '';
        
        if (empty($name)) {
            $this->jsonResponse(['success' => false, 'message' => 'نام دسته‌بندی الزامی است']);
            return;
        }
        
        // افزودن دسته‌بندی جدید
        $categoryId = $this->categoryModel->addCategory([
            'name' => $name,
            'description' => $description
        ]);
        
        if ($categoryId) {
            $this->jsonResponse(['success' => true, 'id' => $categoryId, 'name' => $name]);
        } else {
            $this->jsonResponse(['success' => false, 'message' => 'خطا در افزودن دسته‌بندی']);
        }
    }

    /**
     * افزودن مدل جدید از طریق AJAX
     */
    public function addModel() {
        // بررسی دسترسی
        if (!$this->accessControl->hasPermission('create_assets')) {
            $this->jsonResponse(['success' => false, 'message' => 'دسترسی غیرمجاز'], 403);
            return;
        }
        
        // بررسی درخواست POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['success' => false, 'message' => 'روش درخواست نامعتبر است'], 405);
            return;
        }
        
        // دریافت داده‌ها
        $name = $_POST['name'] ?? '';
        $categoryId = $_POST['category_id'] ?? '';
        $manufacturer = $_POST['manufacturer'] ?? '';
        $modelNumber = $_POST['model_number'] ?? '';
        
        if (empty($name) || empty($categoryId)) {
            $this->jsonResponse(['success' => false, 'message' => 'نام و دسته‌بندی مدل الزامی است']);
            return;
        }
        
        // افزودن مدل جدید
        $modelId = $this->modelModel->addModel([
            'name' => $name,
            'category_id' => $categoryId,
            'manufacturer' => $manufacturer,
            'model_number' => $modelNumber
        ]);
        
        if ($modelId) {
            $this->jsonResponse(['success' => true, 'id' => $modelId, 'name' => $name]);
        } else {
            $this->jsonResponse(['success' => false, 'message' => 'خطا در افزودن مدل']);
        }
    }

    /**
     * نمایش یک قالب با داده‌های مشخص شده
     * 
     * @param string $view مسیر قالب
     * @param array $data داده‌هایی که به قالب منتقل می‌شوند
     */
    private function view($view, $data = []) {
        // استخراج داده‌ها به متغیرهای محلی
        extract($data);
        
        // مسیر کامل فایل قالب
        $viewPath = __DIR__ . '/../views/' . $view . '.php';
        
        // بررسی وجود فایل قالب
        if (file_exists($viewPath)) {
            // شامل کردن فایل قالب
            require_once $viewPath;
        } else {
            // خطا در صورت عدم وجود فایل قالب
            echo "Error: View file not found: $viewPath";
            error_log("View file not found: $viewPath");
        }
    }

    /**
     * جستجوی کاربر با شماره پرسنلی یا نام کاربری
     * 
     * @return json
     */
    public function searchUser(){
        // بررسی درخواست و دریافت مقدار جستجو
        $searchValue = isset($_GET['search_value']) ? trim($_GET['search_value']) : '';

        if (empty($searchValue)) {
            echo json_encode(['success' => false, 'error' => 'لطفاً مقدار جستجو را وارد کنید.']);
            exit;
        }

        try {
            // ایجاد اتصال به پایگاه داده
            $db = new Database();
            $pdo = $db->getConnection();

            // جستجو در جدول کاربران بر اساس شماره پرسنلی یا نام کاربری
            $query = "SELECT id, username, fullname, employee_number, plant, unit 
                    FROM users 
                    WHERE employee_number = :search_value OR username = :search_value 
                    LIMIT 1";
            
            $stmt = $pdo->prepare($query);
            $stmt->bindParam(':search_value', $searchValue, PDO::PARAM_STR);
            $stmt->execute();

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // کاربر یافت شد
                echo json_encode([
                    'success' => true, 
                    'user' => [
                        'id' => $user['id'],
                        'fullname' => $user['fullname'],
                        'username' => $user['username'],
                        'employee_number' => $user['employee_number'],
                        'plant' => $user['plant'],
                        'unit' => $user['unit']
                    ]
                ]);
            } else {
                // کاربر یافت نشد
                echo json_encode([
                    'success' => false, 
                    'error' => 'کاربری با این مشخصات یافت نشد.'
                ]);
            }
        } catch (PDOException $e) {
            // خطا در اتصال به پایگاه داده
            error_log("Database Error: " . $e->getMessage());
            echo json_encode([
                'success' => false, 
                'error' => 'خطا در اتصال به پایگاه داده.'
            ]);
        }
        exit;
    }

    /**
     * جستجوی کاربر با نام کاربری
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

            // نمونه‌سازی از مدل User
            require_once __DIR__ . '/../models/User.php';
            $userModel = new User();

            // دریافت کاربر با نام کاربری
            $user = $userModel->getUserByUsername($username);
            
            if (!$user) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => 'کاربری با این نام کاربری یافت نشد'
                ]);
                exit;
            }

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
                ]
            ];

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
     * جستجوی کاربر با شماره پرسنلی (که همان نام کاربری است)
     */
    public function searchByEmployeeNumber() {
        header('Content-Type: application/json');
        
        try {
            // بررسی وجود پارامتر
            if (!isset($_GET['employee_number']) || empty(trim($_GET['employee_number']))) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'شماره پرسنلی الزامی است'
                ]);
                exit;
            }

            $employeeNumber = trim($_GET['employee_number']);
            error_log("Searching for employee number (username): " . $employeeNumber);

            // استفاده از مدل User برای جستجوی کاربر با نام کاربری
            require_once __DIR__ . '/../models/User.php';
            $userModel = new User();
            
            // جستجو با استفاده از نام کاربری به عنوان شماره پرسنلی
            $user = $userModel->getUserByUsername($employeeNumber);
            
            if (!$user) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => 'کاربری با این شماره پرسنلی یافت نشد'
                ]);
                exit;
            }

            // تعیین محل استقرار (location) بر اساس plant و unit
            $location = '';
            if (!empty($user['plant'])) {
                $location = $user['plant'];
            }
            if (!empty($user['unit'])) {
                $location .= (!empty($location) ? ' - ' : '') . $user['unit'];
            }

            // ساخت پاسخ
            $response = [
                'success' => true,
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'fullname' => $user['fullname'],
                    'email' => $user['email'],
                    'phone' => $user['phone'] ?? '',
                    'mobile' => $user['mobile'] ?? '',
                    'plant' => $user['plant'] ?? '',
                    'unit' => $user['unit'] ?? '',
                    'location' => $location,
                    'is_active' => $user['is_active'],
                    'role_name' => $user['role_name'] ?? ''
                ]
            ];

            echo json_encode($response);
            exit;

        } catch (Exception $e) {
            error_log("Exception in searchByEmployeeNumber: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'خطای سرور در پردازش درخواست'
            ]);
            exit;
        }
    }

    /**
 * جستجوی کاربر با شماره پرسنلی (که همان نام کاربری است)
 */
public function searchUserByEmployeeNumber() {
    header('Content-Type: application/json');
    
    try {
        // بررسی وجود پارامتر
        if (!isset($_GET['employee_number']) || empty(trim($_GET['employee_number']))) {
            error_log("Employee number parameter is missing or empty");
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'شماره پرسنلی الزامی است'
            ]);
            exit;
        }

        $employeeNumber = trim($_GET['employee_number']);
        error_log("Searching for employee number (username): " . $employeeNumber);

        // استفاده از مدل User برای جستجوی کاربر با نام کاربری
        require_once __DIR__ . '/../models/User.php';
        $userModel = new User();
        
        // جستجو با استفاده از نام کاربری به عنوان شماره پرسنلی
        $user = $userModel->getUserByUsername($employeeNumber);
        
        // لاگ کردن نتیجه جستجو
        error_log("User search result: " . ($user ? "User found with ID: {$user['id']}" : "No user found"));
        
        if (!$user) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => 'کاربری با این شماره پرسنلی یافت نشد'
            ]);
            exit;
        }

        // تعیین محل استقرار (location) بر اساس plant و unit
        $location = '';
        if (!empty($user['plant'])) {
            $location = $user['plant'];
        }
        if (!empty($user['unit'])) {
            $location .= (!empty($location) ? ' - ' : '') . $user['unit'];
        }

        // اضافه کردن شماره پرسنلی به پاسخ
        // در این سیستم، نام کاربری به عنوان شماره پرسنلی استفاده می‌شود
        $employee_number = $user['username'];

        // ساخت پاسخ
        $response = [
            'success' => true,
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'fullname' => $user['fullname'],
                'email' => $user['email'],
                'phone' => $user['phone'] ?? '',
                'mobile' => $user['mobile'] ?? '',
                'plant' => $user['plant'] ?? '',
                'unit' => $user['unit'] ?? '',
                'location' => $location,
                'employee_number' => $employee_number,
                'is_active' => $user['is_active'],
                'role_name' => $user['role_name'] ?? ''
            ]
        ];

        // لاگ کردن پاسخ نهایی برای دیباگ
        error_log("User search response: User ID: {$user['id']}, Name: {$user['fullname']}");

        echo json_encode($response);
        exit;

    } catch (Exception $e) {
        error_log("Exception in searchUserByEmployeeNumber: " . $e->getMessage());
        error_log("Error trace: " . $e->getTraceAsString());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'خطای سرور در پردازش درخواست'
        ]);
        exit;
    }
}

/**
 * اصلاح وضعیت تجهیزات با وضعیت خالی
 */
public function fixAssetStatuses() {
    // بررسی دسترسی
    if (!$this->accessControl->hasPermission('manage_assets')) {
        $_SESSION['error'] = 'شما مجوز مدیریت تجهیزات را ندارید.';
        header('Location: /support_system/assets');
        exit;
    }
    
    $count = $this->assetModel->fixEmptyStatuses();
    
    if ($count === false) {
        $_SESSION['error'] = 'خطا در اصلاح وضعیت تجهیزات.';
    } else {
        $_SESSION['success'] = "وضعیت $count تجهیز با موفقیت اصلاح شد.";
    }
    
    header('Location: /support_system/assets');
    exit;
}

}