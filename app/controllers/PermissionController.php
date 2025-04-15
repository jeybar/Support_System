<?php

require_once __DIR__ . '/../models/Permission.php';

class PermissionController {
    private $permissionModel;

    public function __construct() {
        $this->permissionModel = new Permission();
    }

    // نمایش لیست دسترسی‌ها
    public function index() {
        $permissions = $this->permissionModel->getAllPermissions();
        require_once __DIR__ . '/../views/permissions/index.php';
    }

    // نمایش فرم ایجاد دسترسی
    public function create() {
        require_once __DIR__ . '/../views/permissions/create.php';
    }

    // ذخیره دسترسی جدید
    public function store() {
        $name = $_POST['name'] ?? '';
        $description = $_POST['description'] ?? '';

        if ($this->permissionModel->permissionExists($name)) {
            $_SESSION['error'] = 'دسترسی با این نام قبلاً ثبت شده است.';
            header('Location: /support_system/permissions/create');
            exit;
        }

        if (empty($name)) {
            $_SESSION['error'] = 'نام دسترسی نمی‌تواند خالی باشد.';
            header('Location: /support_system/permissions/create');
            exit;
        }

        $result = $this->permissionModel->createPermission($name, $description);

        if ($result) {
            $_SESSION['success'] = 'دسترسی جدید با موفقیت ایجاد شد.';
        } else {
            $_SESSION['error'] = 'خطا در ایجاد دسترسی.';
        }

        header('Location: /support_system/permissions');
        exit;
    }

    // حذف دسترسی
    public function delete($id) {
        $result = $this->permissionModel->deletePermission($id);

        if ($result) {
            $_SESSION['success'] = 'دسترسی با موفقیت حذف شد.';
        } else {
            $_SESSION['error'] = 'خطا در حذف دسترسی.';
        }

        header('Location: /support_system/permissions');
        exit;
    }
}