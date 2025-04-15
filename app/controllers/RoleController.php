<?php

require_once __DIR__ . '/../models/Role.php';
require_once __DIR__ . '/../models/Permission.php';
require_once __DIR__ . '/../helpers/AccessControl.php';

class RoleController {
    private $roleModel;
    private $permissionModel;

    public function __construct() {
        $this->roleModel = new Role();
        $this->permissionModel = new Permission();
    }

    private function findRoleOrRedirect($id) {
        $role = $this->roleModel->getRoleById($id);
        if (!$role) {
            $this->redirectWithMessage('/support_system/roles', 'نقش مورد نظر یافت نشد.', 'error');
        }
        return $role;
    }

    private function redirectWithMessage($url, $message, $type = 'success') {
        $_SESSION[$type] = $message;
        header("Location: $url");
        exit;
    }

    private function getPostData($fields) {
        $data = [];
        foreach ($fields as $field) {
            $data[$field] = $_POST[$field] ?? '';
        }
        return $data;
    }

    private function requirePermission($permission) {
        AccessControl::requirePermission($permission);
    }

    public function index() {
        AccessControl::requirePermission('manage_roles'); // بررسی دسترسی
    
        // مقداردهی به متغیرهای صفحه‌بندی
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['records_per_page']) ? (int)$_GET['records_per_page'] : 10;
        $offset = ($page - 1) * $limit;
    
        // دریافت پارامترهای مرتب‌سازی
        $sortBy = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'id';
        $order = isset($_GET['order']) ? $_GET['order'] : 'asc';
    
        // ساخت آرایه فیلترها
        $filters = [
            'role_name' => $_GET['role_name'] ?? '',
            'description' => $_GET['description'] ?? '',
            'permission' => $_GET['permission'] ?? ''
        ];
    
        // بررسی وجود فیلتر فعال
        $hasActiveFilter = !empty($filters['role_name']) || !empty($filters['description']) || !empty($filters['permission']);
    
        // دریافت نقش‌ها با اعمال فیلترها
        if ($hasActiveFilter) {
            $roles = $this->roleModel->getFilteredRoles($filters, $limit, $offset, $sortBy, $order);
            $totalCount = $this->roleModel->getFilteredRolesCount($filters);
        } else {
            $roles = $this->roleModel->getAllRolesSorted($sortBy, $order, $limit, $offset);
            $totalCount = $this->roleModel->getTotalRolesCount();
        }
    
        // محاسبه تعداد کل صفحات
        $totalPages = ceil($totalCount / $limit);
    
        // دریافت همه دسترسی‌ها برای فرم فیلتر
        $allPermissions = $this->permissionModel->getAllPermissions();
    
        // دریافت نام دسترسی‌ها برای هر نقش
        foreach ($roles as $key => $role) {
            $rolePermissions = $this->roleModel->getPermissionsByRoleId($role['id']);
            $roles[$key]['permissions'] = [];
            
            foreach ($rolePermissions as $permissionId) {
                foreach ($allPermissions as $permission) {
                    if ($permission['id'] == $permissionId) {
                        $roles[$key]['permissions'][] = $permission['permission_name']; // این خط باید اصلاح شود
                        break;
                    }
                }
            }
        }
    
        // ارسال داده‌ها به ویو
        require_once __DIR__ . '/../views/roles/index.php';
    }

    public function create() {
        AccessControl::requirePermission('manage_roles'); // بررسی دسترسی
        require_once __DIR__ . '/../views/roles/create.php';
    }

    public function store() {
       
        AccessControl::requirePermission('manage_roles'); // بررسی دسترسی

        $data = $this->getPostData(['name', 'description']);

        // اعتبارسنجی CSRF Token
        if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $this->redirectWithMessage('/support_system/roles', 'توکن CSRF نامعتبر است.', 'error');
        }

        if (empty($data['name'])) {
            $this->redirectWithMessage('/support_system/roles', 'نام نقش نمی‌تواند خالی باشد.', 'error');
        }
        
        if (strlen($data['name']) > 255) {
            $this->redirectWithMessage('/support_system/roles', 'نام نقش نمی‌تواند بیش از 255 کاراکتر باشد.', 'error');
        }
        
        if (strlen($data['description']) > 1000) {
            $this->redirectWithMessage('/support_system/roles', 'توضیحات نمی‌تواند بیش از 1000 کاراکتر باشد.', 'error');
        }

        if ($this->roleModel->roleExists($data['name'])) {
            $this->redirectWithMessage('/support_system/roles', 'نقش با این نام قبلاً ثبت شده است.', 'error');
        }

        $result = $this->roleModel->createRole($data['name'], $data['description']);

        if ($result) {
            $this->redirectWithMessage('/support_system/roles', 'نقش جدید با موفقیت ایجاد شد.');
        } else {
            $this->redirectWithMessage('/support_system/roles', 'خطا در ایجاد نقش.', 'error');
        }
    }

    public function edit($id) {
        AccessControl::requirePermission('manage_roles'); // بررسی دسترسی

        $role = $this->findRoleOrRedirect($id);
        require_once __DIR__ . '/../views/roles/edit.php';
    }

    public function delete($id) {
        AccessControl::requirePermission('manage_roles'); // بررسی دسترسی
    
        // اعتبارسنجی CSRF Token
        if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $this->redirectWithMessage('/support_system/roles', 'توکن CSRF نامعتبر است.', 'error');
        }
    
        // حذف نقش از پایگاه داده
        $result = $this->roleModel->deleteRole($id);
    
        if ($result) {
            $this->redirectWithMessage('/support_system/roles', 'نقش با موفقیت حذف شد.');
        } else {
            $this->redirectWithMessage('/support_system/roles', 'خطا در حذف نقش.', 'error');
        }
    }

    public function assignPermissions($id) {
        AccessControl::requirePermission('manage_roles'); // بررسی دسترسی

        $role = $this->findRoleOrRedirect($id);
        $permissions = $this->permissionModel->getAllPermissions();
        $rolePermissions = $this->roleModel->getPermissionsByRoleId($id);

        require_once __DIR__ . '/../views/roles/assign_permissions.php';
    }

    //بازگرداندن لیست دسترسی‌ها و دسترسی‌های فعلی نقش به صورت JSON
    public function getPermissions($roleId) {
        // تنظیم هدرهای مناسب برای پاسخ JSON
        header('Content-Type: application/json; charset=UTF-8');
        
        try {
            // بررسی وجود نقش
            $role = $this->roleModel->getRoleById($roleId);
            if (!$role) {
                throw new Exception("نقش مورد نظر یافت نشد.");
            }
        
            // دریافت لیست کل دسترسی‌ها
            $allPermissions = $this->permissionModel->getAllPermissions();
            error_log("All permissions: " . json_encode($allPermissions));
            
            // دریافت دسترسی‌های فعلی نقش
            $rolePermissions = $this->roleModel->getPermissionsByRoleId($roleId);
            error_log("Role permissions: " . json_encode($rolePermissions));
            
            // آماده‌سازی داده‌ها برای ارسال به کلاینت
            $permissions = [];
            foreach ($allPermissions as $permission) {
                // بررسی کلیدهای موجود در آرایه
                error_log("Permission: " . json_encode($permission));
                
                // استفاده از name یا permission_name بسته به ساختار جدول
                $permissionName = isset($permission['name']) ? $permission['name'] : 
                                 (isset($permission['permission_name']) ? $permission['permission_name'] : 'بدون نام');
                
                $permissions[] = [
                    'id' => $permission['id'],
                    'permission_name' => $permissionName,
                    'assigned' => in_array($permission['id'], $rolePermissions)
                ];
            }
        
            // بازگرداندن داده‌ها به صورت JSON
            echo json_encode([
                'permissions' => $permissions
            ]);
            exit;
        } catch (Exception $e) {
            // ثبت خطا در فایل لاگ
            error_log("Error in getPermissions: " . $e->getMessage());
            
            // بازگرداندن پیام خطا به صورت JSON
            http_response_code(500);
            echo json_encode([
                'error' => $e->getMessage()
            ]);
            exit;
        }
    }

    // متد آپدیت دسترسی ها
    public function updatePermissions($roleId = null) {
        // تنظیم هدر Content-Type برای پاسخ JSON
        header('Content-Type: application/json; charset=UTF-8');
        
        // اگر roleId از پارامتر URL دریافت نشده، از POST دریافت کنیم
        if ($roleId === null) {
            $roleId = $_POST['role_id'] ?? null;
        }
        
        if (!$roleId) {
            // اگر شناسه نقش موجود نباشد، پیام خطا برگردانیم
            echo json_encode(['success' => false, 'message' => 'شناسه نقش مشخص نشده است.']);
            exit;
        }
        
        try {
            // بررسی وجود نقش
            $role = $this->roleModel->getRoleById($roleId);
            if (!$role) {
                echo json_encode(['success' => false, 'message' => 'نقش مورد نظر یافت نشد.']);
                exit;
            }
            
            // دریافت دسترسی‌های انتخاب شده
            $permissions = $_POST['permissions'] ?? [];
            
            // ثبت لاگ برای اشکال‌زدایی
            error_log("Updating permissions for role ID: " . $roleId);
            error_log("Selected permissions: " . print_r($permissions, true));
            
            // به‌روزرسانی دسترسی‌های نقش
            $result = $this->roleModel->updateRolePermissions($roleId, $permissions);
            
            if ($result) {
                // دریافت نام‌های دسترسی‌های به‌روزرسانی شده
                $updatedPermissionNames = [];
                if (!empty($permissions)) {
                    foreach ($permissions as $permissionId) {
                        $permission = $this->permissionModel->getPermissionById($permissionId);
                        if ($permission) {
                            $updatedPermissionNames[] = $permission['name'] ?? $permission['permission_name'];
                        }
                    }
                }
                
                // بازگرداندن پاسخ موفقیت
                echo json_encode([
                    'success' => true,
                    'message' => 'دسترسی‌های نقش با موفقیت به‌روزرسانی شدند.',
                    'updatedPermissions' => $updatedPermissionNames
                ]);
            } else {
                // بازگرداندن پیام خطا
                echo json_encode([
                    'success' => false,
                    'message' => 'خطا در به‌روزرسانی دسترسی‌های نقش.'
                ]);
            }
        } catch (Exception $e) {
            // ثبت خطا در فایل لاگ
            error_log("Error in updatePermissions: " . $e->getMessage());
            
            // بازگرداندن پیام خطا
            echo json_encode([
                'success' => false,
                'message' => 'خطا: ' . $e->getMessage()
            ]);
        }
        
        exit;
    }
}