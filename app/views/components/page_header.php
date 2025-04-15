<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// بررسی وجود پیغام موفقیت یا خطا
$successMessage = $_SESSION['success'] ?? null;
$errorMessage = $_SESSION['error'] ?? null;

// حذف پیغام‌ها از نشست پس از دریافت
unset($_SESSION['success']);
unset($_SESSION['error']);

// بررسی اینکه کاربر وارد شده است یا خیر
if (!isset($_SESSION['user_id'])) {
    if ($_SERVER['REQUEST_URI'] !== '/support_system/login') {
        header('Location: /support_system/login');
        exit;
    }
}
