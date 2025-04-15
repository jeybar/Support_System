<?php

require_once __DIR__ . '/../models/Ticket.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Asset.php';
require_once __DIR__ . '/../models/Maintenance.php';
require_once __DIR__ . '/../models/AssetCategory.php'; // اضافه کردن مدل دسته‌بندی دارایی‌ها
require_once __DIR__ . '/../helpers/date_helper.php';
require_once __DIR__ . '/../helpers/AccessControl.php'; // اضافه کردن کنترل دسترسی

class DashboardController
{
    // تعریف ثابت‌های نقش برای خوانایی بیشتر کد
    const ROLE_ADMIN = 1;
    const ROLE_USER = 2;
    const ROLE_SUPPORT = 3;
    const ROLE_MANAGER = 4;
    
    private $userModel;
    private $ticketModel;
    private $assetModel;
    private $maintenanceModel;
    private $accessControl;
    
    /**
     * سازنده کلاس - ایجاد نمونه‌های مدل‌های مورد نیاز
     */
    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $this->userModel = new User();
        $this->ticketModel = new Ticket();
        $this->assetModel = new Asset();
        $this->maintenanceModel = new Maintenance();
        $this->accessControl = new AccessControl();
    }
    
    /**
     * بررسی تکمیل پروفایل کاربر
     */
    private function checkProfileCompletion()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /support_system/login');
            exit;
        }
    
        if (!$this->userModel->isProfileComplete($_SESSION['user_id'])) {
            $_SESSION['error'] = "لطفاً پروفایل خود را تکمیل کنید.";
            header('Location: /support_system/profile');
            exit;
        }
    }
    
    /**
     * بررسی احراز هویت کاربر و دریافت نقش
     * @return int شناسه نقش کاربر
     */
    private function checkAuthentication()
    {
        if (!isset($_SESSION['user_id'])) {
            error_log("DashboardController: کاربر وارد نشده است. هدایت به صفحه ورود.");
            header('Location: /support_system/login');
            exit;
        }

        // بررسی تکمیل بودن پروفایل
        $this->checkProfileCompletion();

        // استفاده از role_id اگر وجود داشته باشد، در غیر این صورت از role استفاده کن
        $role = $_SESSION['role_id'] ?? $_SESSION['role'] ?? null;

        if (!$role || !is_numeric($role)) {
            error_log("DashboardController: نقش کاربر نامشخص است. هدایت به صفحه ورود.");
            header('Location: /support_system/login');
            exit;
        }

        return (int)$role;
    }
    
    /**
     * دریافت داده‌های داشبورد مدیر سیستم
     * @return array داده‌های داشبورد
     */
    private function getAdminDashboardData()
    {
        $dashboardData = [];
        
        // آمار درخواست‌ها
        $dashboardData['openTicketsCount'] = $this->ticketModel->getTicketsCountByStatus('open');
        $dashboardData['inProgressTicketsCount'] = $this->ticketModel->getTicketsCountByStatus('in_progress');
        $dashboardData['resolvedTicketsCount'] = $this->ticketModel->getTicketsCountByStatus('closed');
        $dashboardData['averageResponseTime'] = $this->ticketModel->calculateAverageResponseTime();
        $dashboardData['totalOverdueTickets'] = $this->ticketModel->getOverdueTicketsCount();
        $dashboardData['ticketsByPriority'] = $this->ticketModel->getTicketsByPriority();
        $dashboardData['userStatusReport'] = $this->userModel->getUsersByStatus();
        $dashboardData['supportPerformance'] = $this->ticketModel->getSupportTeamPerformance();
        $dashboardData['topUsersByTickets'] = $this->ticketModel->getTopUsersByTickets();
        $dashboardData['pendingTicketsByUser'] = $this->ticketModel->getPendingTicketsByUser();
        $dashboardData['ticketStatusCounts'] = $this->ticketModel->getTicketStatusCounts();
        $dashboardData['ticketCountsByDate'] = $this->ticketModel->getTicketCountsByDate();
        $dashboardData['overdueTicketsByUser'] = $this->ticketModel->getOverdueTicketsByUser();
        
        // اطلاعات دارایی‌ها برای مدیر
        $dashboardData['assetStats'] = $this->assetModel->getAssetStats();
        $dashboardData['assetCategoryDistribution'] = $this->assetModel->getAssetCategoryDistribution();
        $dashboardData['assetsNeedingAttention'] = $this->assetModel->getAssetsNeedingAttention(5);
        $dashboardData['upcomingMaintenance'] = $this->maintenanceModel->getUpcomingMaintenance(5);
        $dashboardData['assetsByStatus'] = $this->assetModel->getAssetsByStatus();
        $dashboardData['assetsByDepartment'] = $this->assetModel->getAssetsByDepartment();
        
        // اطلاعات تکمیلی دارایی‌ها
        $dashboardData['expiringWarrantyAssets'] = $this->assetModel->getAssetsWithExpiringWarranty(30);
        $dashboardData['recentlyAddedAssets'] = $this->assetModel->getRecentlyAddedAssets(10);
        
        return $dashboardData;
    }
    
    /**
     * دریافت داده‌های داشبورد کاربر عادی
     * @param int $userId شناسه کاربر
     * @return array داده‌های داشبورد
     */
    private function getUserDashboardData($userId)
    {
        $dashboardData = [];
        
        // آمار درخواست‌های کاربر
        $dashboardData['totalTickets'] = $this->ticketModel->getTicketsCountByCreator($userId);
        $dashboardData['openTicketsCount'] = $this->ticketModel->getTicketsCountByStatusAndCreator('open', $userId);
        $dashboardData['inProgressTicketsCount'] = $this->ticketModel->getTicketsCountByStatusAndCreator('in_progress', $userId);
        $dashboardData['resolvedTicketsCount'] = $this->ticketModel->getTicketsCountByStatusAndCreator('closed', $userId);
        $dashboardData['recentTickets'] = $this->ticketModel->getRecentTicketsByCreator($userId);
        $dashboardData['recentStatusChanges'] = $this->ticketModel->getRecentStatusChangesByCreator($userId);
        
        // اطلاعات دارایی‌های کاربر
        $dashboardData['userAssets'] = $this->assetModel->getAssetsByUserId($userId);
        $dashboardData['userAssetsCount'] = $this->assetModel->getAssetsCountByUserId($userId);
        $dashboardData['userUpcomingMaintenance'] = $this->maintenanceModel->getUpcomingMaintenanceByUserId($userId, 5);
        
        // اطلاعات تکمیلی دارایی‌های کاربر
        $dashboardData['userAssetsByCategory'] = $this->assetModel->getUserAssetsByCategory($userId);
        $dashboardData['userAssetsNeedingAttention'] = $this->assetModel->getUserAssetsNeedingAttention($userId);
        
        return $dashboardData;
    }
    
    /**
     * دریافت داده‌های داشبورد پشتیبان
     * @param int $supportId شناسه پشتیبان
     * @return array داده‌های داشبورد
     */
    private function getSupportDashboardData($supportId)
    {
        $dashboardData = [];
        
        // آمار درخواست‌های پشتیبان
        $dashboardData['openTicketsCount'] = $this->ticketModel->getTicketsCountByStatusAndUser('open', $supportId);
        $dashboardData['inProgressTicketsCount'] = $this->ticketModel->getTicketsCountByStatusAndUser('in_progress', $supportId);
        $dashboardData['resolvedTicketsCount'] = $this->ticketModel->getTicketsCountByStatusAndUser('closed', $supportId);
        $dashboardData['recentTickets'] = $this->ticketModel->getRecentTicketsByCreator($supportId);
        $dashboardData['overdueTicketsCount'] = $this->ticketModel->getOverdueTicketsCountByUser($supportId);
        $dashboardData['averageResponseTime'] = $this->ticketModel->calculateAverageResponseTimeByUser($supportId);
        $dashboardData['referredTicketsCount'] = $this->ticketModel->getReferredTicketsCountByUser($supportId);
        
        // اطلاعات دارایی‌ها و سرویس‌ها برای پشتیبان
        $dashboardData['assetsNeedingMaintenance'] = $this->assetModel->getAssetsNeedingMaintenance(5);
        $dashboardData['upcomingMaintenance'] = $this->maintenanceModel->getUpcomingMaintenance(5);
        $dashboardData['maintenanceStats'] = $this->maintenanceModel->getMaintenanceStatsByTechnician($supportId);
        
        // اطلاعات تکمیلی برای پشتیبان
        $dashboardData['ticketsByPriorityForSupport'] = $this->ticketModel->getTicketsByPriorityForUser($supportId);
        $dashboardData['ticketResolutionTime'] = $this->ticketModel->getAverageResolutionTimeByUser($supportId);
        
        return $dashboardData;
    }
    
    /**
     * دریافت داده‌های داشبورد مدیر بخش
     * @param int $userId شناسه مدیر
     * @return array داده‌های داشبورد
     */
    private function getManagerDashboardData($userId)
    {
        $dashboardData = [];
        
        // آمار کلی درخواست‌ها
        $dashboardData['openTicketsCount'] = $this->ticketModel->getTicketsCountByStatus('open');
        $dashboardData['inProgressTicketsCount'] = $this->ticketModel->getTicketsCountByStatus('in_progress');
        $dashboardData['resolvedTicketsCount'] = $this->ticketModel->getTicketsCountByStatus('closed');
        $dashboardData['averageResponseTime'] = $this->ticketModel->calculateAverageResponseTime();
        $dashboardData['totalOverdueTickets'] = $this->ticketModel->getOverdueTicketsCount();
        $dashboardData['ticketsByPriority'] = $this->ticketModel->getTicketsByPriority();
        $dashboardData['userStatusReport'] = $this->userModel->getUsersByStatus();
        
        // اطلاعات دارایی‌های بخش
        $departmentId = $this->userModel->getUserDepartmentId($userId);
        $dashboardData['departmentAssets'] = $this->assetModel->getAssetsByDepartmentId($departmentId);
        $dashboardData['departmentAssetsCount'] = $this->assetModel->getAssetsCountByDepartmentId($departmentId);
        $dashboardData['departmentMaintenanceNeeded'] = $this->maintenanceModel->getMaintenanceNeededByDepartmentId($departmentId, 5);
        
        // اطلاعات تکمیلی بخش
        $dashboardData['departmentAssetsByCategory'] = $this->assetModel->getAssetCategoryDistributionByDepartmentId($departmentId);
        $dashboardData['departmentTicketTrends'] = $this->ticketModel->getTicketTrendsByDepartmentId($departmentId);
        
        return $dashboardData;
    }
    
    /**
     * نمایش داشبورد اصلی بر اساس نقش کاربر
     */
    public function index()
    {
        // بررسی احراز هویت و دریافت نقش کاربر
        $roleInt = $this->checkAuthentication();
        $userId = $_SESSION['user_id'];
        
        error_log("DashboardController: مقدار roleInt: $roleInt");
        
        // دریافت داده‌های داشبورد بر اساس نقش کاربر
        switch ($roleInt) {
            case self::ROLE_ADMIN:
                $dashboardData = $this->getAdminDashboardData();
                $viewFile = 'dashboard_admin.php';
                $pageTitle = 'داشبورد مدیریت';
                break;
                
            case self::ROLE_USER:
                $dashboardData = $this->getUserDashboardData($userId);
                $viewFile = 'dashboard_user.php';
                $pageTitle = 'داشبورد کاربر';
                break;
                
            case self::ROLE_SUPPORT:
                $dashboardData = $this->getSupportDashboardData($userId);
                $viewFile = 'dashboard_support.php';
                $pageTitle = 'داشبورد پشتیبانی';
                break;
                
            case self::ROLE_MANAGER:
                $dashboardData = $this->getManagerDashboardData($userId);
                $viewFile = file_exists(__DIR__ . '/../views/dashboard_manager.php') ? 
                            'dashboard_manager.php' : 'dashboard_admin.php';
                $pageTitle = 'داشبورد مدیر بخش';
                break;
                
            default:
                error_log("DashboardController: نقش کاربر نامشخص است. استفاده از داشبورد پیش‌فرض.");
                $dashboardData = $this->getUserDashboardData($userId);
                $viewFile = 'dashboard_user.php';
                $pageTitle = 'داشبورد';
                break;
        }
        
        // آماده‌سازی داده‌های گزارش وضعیت کاربران برای نمودارها
        if (isset($dashboardData['userStatusReport'])) {
            $userStatus = [
                'active' => 0,
                'inactive' => 0
            ];
            
            foreach ($dashboardData['userStatusReport'] as $status) {
                $userStatus[$status['status']] = $status['count'];
            }
            
            $dashboardData['userStatusForChart'] = $userStatus;
        }
        
        // نمایش نما
        $cssLink = 'dashboard.css';
        require_once __DIR__ . '/../views/header.php';
        require_once __DIR__ . "/../views/$viewFile";
        require_once __DIR__ . '/../views/footer.php';
    }
    
    /**
     * نمایش داشبورد دارایی‌ها
     */
    public function assets()
    {
        // بررسی احراز هویت و دسترسی
        $roleInt = $this->checkAuthentication();
        
        // بررسی دسترسی کاربر به این بخش
        if (!$this->accessControl->hasPermission('view_assets')) {
            $_SESSION['error'] = "شما دسترسی لازم برای مشاهده داشبورد دارایی‌ها را ندارید.";
            header('Location: /support_system/dashboard');
            exit;
        }
        
        // دریافت آمار دارایی‌ها
        $assetStats = $this->assetModel->getAssetStats();
        $assetCategoryDistribution = $this->assetModel->getAssetCategoryDistribution();
        $assetsNeedingAttention = $this->assetModel->getAssetsNeedingAttention(10);
        $expiringWarrantyAssets = $this->assetModel->getAssetsWithExpiringWarranty(30);
        $recentlyAddedAssets = $this->assetModel->getRecentlyAddedAssets(10);
        $assetsByStatus = $this->assetModel->getAssetsByStatus();
        $assetUtilization = $this->assetModel->getAssetUtilization();
        
        // نمایش نما
        $pageTitle = 'داشبورد دارایی‌ها';
        $cssLink = 'dashboard.css';
        require_once __DIR__ . '/../views/header.php';
        require_once __DIR__ . '/../views/assets/dashboard.php';
        require_once __DIR__ . '/../views/footer.php';
    }
    
    /**
     * نمایش داشبورد سرویس‌های ادواری
     */
    public function maintenance()
    {
        // بررسی احراز هویت و دسترسی
        $roleInt = $this->checkAuthentication();
        
        // بررسی دسترسی کاربر به این بخش
        if (!$this->accessControl->hasPermission('view_maintenance')) {
            $_SESSION['error'] = "شما دسترسی لازم برای مشاهده داشبورد سرویس‌های ادواری را ندارید.";
            header('Location: /support_system/dashboard');
            exit;
        }
        
        // دریافت آمار سرویس‌های ادواری
        $upcomingMaintenance = $this->maintenanceModel->getUpcomingMaintenance(10);
        $maintenanceStats = $this->maintenanceModel->getMaintenanceStats();
        $maintenanceByCategory = $this->maintenanceModel->getMaintenanceByCategory();
        $maintenanceCompletionRate = $this->maintenanceModel->getCompletionRate();
        $overdueMaintenance = $this->maintenanceModel->getOverdueMaintenance();
        
        // نمایش نما
        $pageTitle = 'داشبورد سرویس‌های ادواری';
        $cssLink = 'dashboard.css';
        require_once __DIR__ . '/../views/header.php';
        require_once __DIR__ . '/../views/maintenance/dashboard.php';
        require_once __DIR__ . '/../views/footer.php';
    }
    
    /**
     * دریافت داده‌های نمودار به صورت API
     */
    public function getChartData()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // بررسی احراز هویت
        if (!isset($_SESSION['user_id'])) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
        
        $type = $_GET['type'] ?? '';
        $period = $_GET['period'] ?? 'month'; // روز، هفته، ماه، سال
        $userId = $_SESSION['user_id'];
        $role = $_SESSION['role_id'] ?? $_SESSION['role'] ?? null;
        
        if (!$role || !is_numeric($role)) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Invalid role']);
            exit;
        }
        
        $data = [];
        
        switch ($type) {
            case 'tickets_by_status':
                $data = $this->ticketModel->getTicketStatusCountsByPeriod($period);
                break;
                
            case 'tickets_by_priority':
                $data = $this->ticketModel->getTicketsByPriorityAndPeriod($period);
                break;
                
            case 'assets_by_category':
                $data = $this->assetModel->getAssetCategoryDistribution();
                break;
                
            case 'assets_by_status':
                $data = $this->assetModel->getAssetsByStatus();
                break;
                
            case 'maintenance_by_status':
                $data = $this->maintenanceModel->getMaintenanceByStatus();
                break;
                
            case 'user_assets':
                $data = $this->assetModel->getUserAssetsByCategory($userId);
                break;
                
            default:
                $data = ['error' => 'Invalid chart type'];
                break;
        }
        
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}