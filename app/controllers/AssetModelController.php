<?php
require_once __DIR__ . '/../models/AssetModel.php';
require_once __DIR__ . '/../models/AssetCategory.php';
require_once __DIR__ . '/../helpers/AccessControl.php';

class AssetModelController {
    private $modelModel;
    private $categoryModel;
    private $accessControl;
    
    public function __construct() {
        $this->modelModel = new AssetModel();
        $this->categoryModel = new AssetCategory();
        $this->accessControl = new AccessControl();
    }
    
    /**
     * نمایش لیست مدل‌ها
     */
    public function index() {
        // بررسی دسترسی کاربر
        if (!$this->accessControl->hasPermission('view_assets')) {
            $_SESSION['error'] = "شما دسترسی لازم برای مشاهده مدل‌های تجهیز را ندارید.";
            header('Location: /support_system/dashboard');
            exit;
        }
        
        // دریافت پارامترهای جستجو
        $searchTerm = $_GET['search'] ?? '';
        $categoryId = $_GET['category'] ?? '';
        
        // دریافت مدل‌ها با اعمال فیلترها
        if (!empty($searchTerm) || !empty($categoryId)) {
            $models = $this->modelModel->searchModels($searchTerm, $categoryId);
        } else {
            $models = $this->modelModel->getAllModels();
        }
        
        // دریافت دسته‌بندی‌ها برای فیلتر
        $categories = $this->categoryModel->getAllCategories();
        
        require_once __DIR__ . '/../views/assets/models/index.php';
    }
    
   /**
     * نمایش فرم ایجاد مدل جدید
     */
    public function create() {
        // بررسی دسترسی کاربر
        if (!$this->accessControl->hasPermission('create_assets')) {
            $_SESSION['error'] = "شما دسترسی لازم برای ایجاد مدل تجهیز را ندارید.";
            header('Location: /support_system/asset_models');
            exit;
        }
        
        $categories = $this->categoryModel->getAllCategories();
        
        require_once __DIR__ . '/../views/assets/models/create.php';
    }
}