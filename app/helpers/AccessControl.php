<?php

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Permission.php';

class AccessControl {
    private static $userModel = null;
    private static $permissionModel = null;
    private static $permissionsCache = [];

    // مقداردهی اولیه مدل‌ها
    private static function init() {
        if (self::$userModel === null) {
            self::$userModel = new User();
        }
        if (self::$permissionModel === null) {
            self::$permissionModel = new Permission();
        }
    }

    //  بررسی دسترسی کاربر به یک عملیات خاص
    public static function hasPermission($permissionName) {
        // بررسی وجود جلسه کاربری
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        
        // اگر کاربر ادمین سیستم است، همه دسترسی‌ها را دارد
        if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true) {
            return true;
        }
        
        // استفاده از کش برای بهبود عملکرد
        $userId = $_SESSION['user_id'];
        $cacheKey = $userId . '_' . $permissionName;
        
        if (isset(self::$permissionsCache[$cacheKey])) {
            return self::$permissionsCache[$cacheKey];
        }
        
        self::init();
        $hasPermission = self::$userModel->hasPermission($userId, $permissionName);
        
        // ذخیره نتیجه در کش
        self::$permissionsCache[$cacheKey] = $hasPermission;
        
        return $hasPermission;
    }
    
    // بررسی دسترسی کاربر و هدایت به صفحه خطا در صورت عدم دسترسی
    public static function requirePermission($permissionName) {
        // بررسی وجود جلسه کاربری
        if (!isset($_SESSION['user_id'])) {
            $_SESSION['error'] = 'لطفاً ابتدا وارد سیستم شوید.';
            header('Location: /support_system/login');
            exit;
        }
        
        // بررسی دسترسی کاربر
        if (!self::hasPermission($permissionName)) {
            $_SESSION['error'] = 'شما دسترسی لازم برای انجام این عملیات را ندارید.';
            header('Location: /support_system/dashboard');
            exit;
        }
    }
    
    //  بررسی اینکه آیا کاربر مجاز به مشاهده عناصر رابط کاربری است
    public static function canView($permissionName) {
        return self::hasPermission($permissionName);
    }
    
    // دریافت تمام دسترسی‌های کاربر جاری
    public static function getUserPermissions() {
        if (!isset($_SESSION['user_id'])) {
            return [];
        }
        
        self::init();
        return self::$userModel->getUserPermissions($_SESSION['user_id']);
    }
    
    // بارگذاری دسترسی‌های کاربر در نشست
    public static function loadUserPermissions($userId) {
        self::init();
        $permissions = self::$userModel->getUserPermissions($userId);
        $_SESSION['permissions'] = $permissions;
    }
    
    // پاکسازی کش دسترسی‌ها
    public static function clearCache() {
        self::$permissionsCache = [];
    }
}