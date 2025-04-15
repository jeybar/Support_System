<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config/config.php'; // فایل تنظیمات

try {
    // اتصال به پایگاه داده با PDO
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // دریافت پارامترهای جستجو از درخواست GET
    $query = isset($_GET['query']) ? trim($_GET['query']) : '';
    $status = isset($_GET['status']) ? trim($_GET['status']) : '';
    $createdDate = isset($_GET['created_date']) ? trim($_GET['created_date']) : '';
    $createdBy = isset($_GET['created_by']) ? trim($_GET['created_by']) : '';

    // اگر هیچ پارامتری ارسال نشده باشد، خطا برگردانید
    if (empty($query) && empty($status) && empty($createdDate) && empty($createdBy)) {
        http_response_code(400); // خطای درخواست
        echo json_encode(['error' => 'حداقل یک فیلتر باید وارد شود.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ساخت کوئری جستجو
    $sql = "SELECT 
                tickets.id, 
                tickets.title, 
                tickets.status, 
                tickets.priority, 
                tickets.created_at, 
                users.username AS created_by 
            FROM 
                tickets 
            LEFT JOIN 
                users 
            ON 
                tickets.user_id = users.id 
            WHERE 1=1";

    // آرایه برای پارامترهای کوئری
    $params = [];

    // جستجو بر اساس عنوان
    if (!empty($query)) {
        $sql .= " AND tickets.title LIKE ?";
        $params[] = '%' . $query . '%';
    }

    // فیلتر بر اساس وضعیت
    if (!empty($status)) {
        $sql .= " AND tickets.status = ?";
        $params[] = $status;
    }

    // فیلتر بر اساس تاریخ ایجاد
    if (!empty($createdDate)) {
        $sql .= " AND DATE(tickets.created_at) = ?";
        $params[] = $createdDate;
    }

    // فیلتر بر اساس نام کاربری ایجادکننده
    if (!empty($createdBy)) {
        $sql .= " AND users.username LIKE ?";
        $params[] = '%' . $createdBy . '%';
    }

    // آماده‌سازی و اجرای کوئری
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    // دریافت نتایج
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // بازگرداندن نتایج به صورت JSON
    header('Content-Type: application/json');
    echo json_encode($tickets);

} catch (PDOException $e) {
    // مدیریت خطاهای پایگاه داده
    http_response_code(500); // خطای سرور
    echo json_encode(['error' => 'خطای پایگاه داده: ' . $e->getMessage()]);
    exit;
} catch (Exception $e) {
    // مدیریت سایر خطاها
    http_response_code(500); // خطای سرور
    echo json_encode(['error' => 'خطای سرور: ' . $e->getMessage()]);
    exit;
}