<?php

function generateBreadcrumbs() {
    // گرفتن URL فعلی
    $currentUrl = $_SERVER['REQUEST_URI'];

    // حذف پارامترهای GET از URL
    $currentUrl = strtok($currentUrl, '?');

    // جدا کردن مسیرها
    $parts = explode('/', trim($currentUrl, '/'));

    // مسیرهایی که باید ترجمه شوند
    $translations = [
        'dashboard' => 'داشبورد',
        'tickets' => 'درخواست کار‌ها',
        'roles' => 'مدیریت نقش‌ها',
        'profile' => 'پروفایل',
        'settings' => 'تنظیمات',
        'public' => 'عمومی',
        'users' => 'مدیریت کاربران',
        'assets' => 'تجهیزات سخت افزاری',
        'view' => 'جزئیات درخواست' // ترجمه برای "view"
    ];

    // مسیرهایی که باید حذف شوند
    $exclude = ['support_system'];

    // لینک پایه (Base URL)
    $baseUrl = '/';

    // شروع Breadcrumbs
    $breadcrumbs = '<nav aria-label="breadcrumb" dir="rtl"><ol class="breadcrumb">';

    // اضافه کردن گزینه خانه به صورت دستی
    $breadcrumbs .= '<li class="breadcrumb-item"><a href="http://localhost/support_system/dashboard">خانه</a></li>';

    // ایجاد لینک‌ها برای مسیرهای باقی‌مانده
    foreach ($parts as $key => $part) {
        // حذف مسیرهای غیرضروری
        if (in_array($part, $exclude)) {
            continue;
        }

        // حذف شماره درخواست (اگر عدد باشد)
        if (is_numeric($part)) {
            continue;
        }

        // ترجمه نام مسیر
        $label = isset($translations[$part]) ? $translations[$part] : ucfirst(urldecode($part));

        // ساخت لینک برای هر بخش
        $baseUrl .= $part . '/';

        // مدیریت مسیرهای خاص (مانند /tickets/view/)
        if ($part === 'view' && isset($parts[$key + 1]) && is_numeric($parts[$key + 1])) {
            // لینک "view" به صورت غیرفعال نمایش داده شود
            $breadcrumbs .= '<li class="breadcrumb-item active" aria-current="page">' . $label . '</li>';
            break; // توقف ساخت لینک‌های بیشتر
        }

        // اگر آخرین بخش است (صفحه فعلی)
        if ($key === array_key_last($parts)) {
            // لینک آخر به‌صورت غیرفعال و بدون تگ <a>
            $breadcrumbs .= '<li class="breadcrumb-item active" aria-current="page">' . $label . '</li>';
        } else {
            // سایر لینک‌ها به‌صورت قابل کلیک
            $breadcrumbs .= '<li class="breadcrumb-item"><a href="' . $baseUrl . '">' . $label . '</a></li>';
        }
    }

    // پایان Breadcrumbs
    $breadcrumbs .= '</ol></nav>';

    return $breadcrumbs;
}