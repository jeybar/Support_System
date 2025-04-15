<?php
// تبدیل وضعیت درخواست به فارسی
function translateStatus($status) {
    $translations = [
        'open' => 'باز',
        'in_progress' => 'در حال بررسی',
        'resolved' => 'حل شده',
        'closed' => 'بسته شده',
        'pending' => 'در انتظار',
        'referred' => 'ارجاع شده',
        'waiting' => 'در انتظار پاسخ کاربر'
    ];
    
    return $translations[$status] ?? $status;
}

// تبدیل اولویت درخواست به فارسی
function translatePriority($priority) {
    $translations = [
        'normal' => 'عادی',
        'urgent' => 'فوری',
        'critical' => 'بحرانی',
        'low' => 'کم',
        'medium' => 'متوسط',
        'high' => 'زیاد'
    ];
    
    return $translations[$priority] ?? $priority;
}

// تبدیل نوع مشکل به فارسی
function translateProblemType($problemType) {
    $translations = [
        'hardware' => 'سخت‌افزار',
        'software' => 'نرم‌افزار',
        'network' => 'شبکه',
        'other' => 'سایر'
    ];
    
    return $translations[$problemType] ?? $problemType;
}
?>