<?php
require_once __DIR__ . '/../models/AssetCategory.php';
require_once __DIR__ . '/../helpers/AccessControl.php';

class AssetCategoryController {
    private $categoryModel;
    private $accessControl;
    
    public function __construct() {
        $this->categoryModel = new AssetCategory();
        $this->accessControl = new AccessControl();
    }
    
    /**
     * نمایش لیست دسته‌بندی‌ها
     */
    public function index() {
        // بررسی دسترسی کاربر
        if (!$this->accessControl->hasPermission('view_assets')) {
            $_SESSION['error'] = "شما دسترسی لازم برای مشاهده دسته‌بندی‌های تجهیز را ندارید.";
            header('Location: /support_system/dashboard');
            exit;
        }
        
        $categories = $this->categoryModel->getAllCategories();
        
        require_once __DIR__ . '/../views/assets/categories/index.php';
    }
    
    /**
     * نمایش فرم ایجاد دسته‌بندی جدید
     */
    public function create() {
        // بررسی دسترسی کاربر
        if (!$this->accessControl->hasPermission('create_assets')) {
            $_SESSION['error'] = "شما دسترسی لازم برای ایجاد دسته‌بندی تجهیز را ندارید.";
            header('Location: /support_system/asset_categories');
            exit;
        }
        
        require_once __DIR__ . '/../views/assets/categories/create.php';
    }
    
    /**
     * ذخیره دسته‌بندی جدید
     */
    public function store() {
        // بررسی دسترسی کاربر
        if (!$this->accessControl->hasPermission('create_assets')) {
            $_SESSION['error'] = "شما دسترسی لازم برای ایجاد دسته‌بندی تجهیز را ندارید.";
            header('Location: /support_system/asset_categories');
            exit;
        }
        
        // اعتبارسنجی داده‌های ورودی
        if (!isset($_POST['name']) || empty($_POST['name'])) {
            $_SESSION['error'] = "نام دسته‌بندی الزامی است.";
            header('Location: /support_system/asset_categories/create');
            exit;
        }
        
        $name = $_POST['name'];
        $description = $_POST['description'] ?? '';
        
        $result = $this->categoryModel->createCategory($name, $description);
        
        if ($result) {
            $_SESSION['success'] = "دسته‌بندی با موفقیت ایجاد شد.";
        } else {
            $_SESSION['error'] = "خطا در ایجاد دسته‌بندی.";
        }
        
        header('Location: /support_system/asset_categories');
        exit;
    }
    
    /**
     * نمایش فرم ویرایش دسته‌بندی
     */
    public function edit($id) {
        // بررسی دسترسی کاربر
        if (!$this->accessControl->hasPermission('edit_assets')) {
            $_SESSION['error'] = "شما دسترسی لازم برای ویرایش دسته‌بندی تجهیز را ندارید.";
            header('Location: /support_system/asset_categories');
            exit;
        }
        
        $category = $this->categoryModel->getCategoryById($id);
        
        if (!$category) {
            $_SESSION['error'] = "دسته‌بندی مورد نظر یافت نشد.";
            header('Location: /support_system/asset_categories');
            exit;
        }
        
        require_once __DIR__ . '/../views/assets/categories/edit.php';
    }
    
    /**
     * به‌روزرسانی دسته‌بندی
     */
    public function update($id) {
        // بررسی دسترسی کاربر
        if (!$this->accessControl->hasPermission('edit_assets')) {
            $_SESSION['error'] = "شما دسترسی لازم برای ویرایش دسته‌بندی تجهیز را ندارید.";
            header('Location: /support_system/asset_categories');
            exit;
        }
        
        // اعتبارسنجی داده‌های ورودی
        if (!isset($_POST['name']) || empty($_POST['name'])) {
            $_SESSION['error'] = "نام دسته‌بندی الزامی است.";
            header('Location: /support_system/asset_categories/edit/' . $id);
            exit;
        }
        
        $name = $_POST['name'];
        $description = $_POST['description'] ?? '';
        
        $result = $this->categoryModel->updateCategory($id, $name, $description);
        
        if ($result) {
            $_SESSION['success'] = "دسته‌بندی با موفقیت به‌روزرسانی شد.";
        } else {
            $_SESSION['error'] = "خطا در به‌روزرسانی دسته‌بندی.";
        }
        
        header('Location: /support_system/asset_categories');
        exit;
    }
    
    // حذف دسته‌بندی
    public function delete($id) {
        // بررسی دسترسی کاربر
        if (!$this->accessControl->hasPermission('delete_assets')) {
            $_SESSION['error'] = "شما دسترسی لازم برای حذف دسته‌بندی تجهیز را ندارید.";
            header('Location: /support_system/asset_categories');
            exit;
        }
        
        $result = $this->categoryModel->deleteCategory($id);
        
        if ($result) {
            $_SESSION['success'] = "دسته‌بندی با موفقیت حذف شد.";
        } else {
            $_SESSION['error'] = "خطا در حذف دسته‌بندی. ممکن است این دسته‌بندی دارای مدل‌هایی باشد.";
        }
        
        header('Location: /support_system/asset_categories');
        exit;
    }
}