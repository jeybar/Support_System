<?php

// تنظیمات پایگاه داده
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'support_system');
define('BASE_URL', '/support_system/public/');
define('BASE_PATH', '/support_system/');
define('VIEW_PATH', __DIR__ . '/../app/views/');
// تنظیمات Active Directory (LDAP)
define('LDAP_SERVER', 'ldap://Srv-DC-01.Bisco.local'); // آدرس سرور LDAP
define('LDAP_DOMAIN', 'Bisco.local'); // دامنه Active Directory
define('LDAP_BASE_DN', 'dc=Bisco,dc=local'); // Base DN برای جستجو

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}