<?php
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Role.php'; // برای دریافت دسترسی‌ها
require_once 'C:/xampp/htdocs/support_system/config/config.php';

class LoginController {
    private $userModel;

    public function __construct() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function index($error_message = null) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // بررسی وجود نشست (Session)
        if (isset($_SESSION['user_id'])) {
            error_log("کاربر وارد شده است. هدایت به داشبورد.");
            header('Location: /support_system/dashboard');
            exit;
        }

        // ارسال پیام خطا (در صورت وجود) به صفحه ورود
        require_once __DIR__ . '/../views/login.php';
    }

    public function authenticate(){
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // دریافت اطلاعات فرم
        $username = isset($_POST['username']) ? trim($_POST['username']) : '';
        $password = isset($_POST['password']) ? trim($_POST['password']) : '';

        // بررسی نام کاربری و رمز عبور در پایگاه داده
        $userModel = new User();
        $user = $userModel->findByUsername($username);

        if ($user && password_verify($password, $user['password'])) {
            // ایجاد نشست (Session)
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['fullname'] = $user['fullname'];
            $_SESSION['role_id'] = $user['role_id']; // استفاده از role_id به جای role

            // دریافت اطلاعات پلنت و واحد از پایگاه داده (در صورت وجود)
            $_SESSION['plant'] = $user['plant'] ?? 'نامشخص';
            $_SESSION['unit'] = $user['unit'] ?? 'نامشخص';

            // بررسی اینکه آیا کاربر ادمین سیستم است
            $_SESSION['is_admin'] = ($user['role_id'] == 1); // فرض بر این است که نقش با شناسه 1، ادمین سیستم است

            // بارگذاری دسترسی‌های کاربر در نشست با استفاده از AccessControl
            require_once __DIR__ . '/../helpers/AccessControl.php';
            AccessControl::loadUserPermissions($user['id']);

            // لاگ برای دیباگ
            error_log("LoginController: ورود موفقیت‌آمیز. user_id: " . $_SESSION['user_id'] . ", role_id: " . $_SESSION['role_id']);

            // هدایت به داشبورد
            header('Location: /support_system/dashboard');
            exit;
        }

        // بررسی کاربر شبکه (LDAP)
        $ldapConnection = ldap_connect(LDAP_SERVER);
        if (!$ldapConnection) {
            $error_message = "عدم توانایی در اتصال به سرور LDAP.";
            error_log("LoginController: خطا در اتصال به LDAP.");
            $this->index($error_message);
            return;
        }

        ldap_set_option($ldapConnection, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($ldapConnection, LDAP_OPT_REFERRALS, 0);

        $ldapBind = @ldap_bind($ldapConnection, "$username@" . LDAP_DOMAIN, $password);
        if (!$ldapBind) {
            $error_message = "نام کاربری یا رمز عبور اشتباه است.";
            error_log("LoginController: ورود ناموفق از طریق LDAP.");
            $this->index($error_message);
            return;
        }

        // جستجوی اطلاعات کاربر در LDAP
        $search = ldap_search($ldapConnection, LDAP_BASE_DN, "(sAMAccountName=$username)");
        $entries = ldap_get_entries($ldapConnection, $search);

        if ($entries['count'] > 0) {
            $ldapUser = $entries[0];
            $email = $ldapUser['mail'][0] ?? '';
            $fullname = $ldapUser['cn'][0] ?? '';
            $ldapGroups = $ldapUser['memberOf'] ?? [];

            if (empty($email)) {
                $email = $username . "@example.com";
            }

            $plant = $ldapUser['physicalDeliveryOfficeName'][0] ?? 'نامشخص';
            $unit = $ldapUser['department'][0] ?? 'نامشخص';

            $roleId = 3; // نقش پیش‌فرض
            foreach ($ldapGroups as $group) {
                if (strpos($group, 'Admins') !== false) {
                    $roleId = 1;
                    break;
                } elseif (strpos($group, 'Support') !== false) {
                    $roleId = 2;
                    break;
                }
            }

            // ذخیره یا به‌روزرسانی کاربر در پایگاه داده
            if (!$user) {
                $result = $userModel->createUser($username, $password, $email, $fullname, $roleId, null, null, $plant, $unit, 'network');
                if ($result) {
                    $user = $userModel->findByUsername($username);
                } else {
                    $error_message = "خطا در ایجاد کاربر جدید.";
                    error_log("LoginController: خطا در ایجاد کاربر جدید.");
                    $this->index($error_message);
                    return;
                }
            } else {
                if ($user['user_type'] !== 'network') {
                    $userModel->updateUserType($user['id'], 'network');
                }
            }

            // مقداردهی نشست
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['fullname'] = $user['fullname'];
            $_SESSION['role_id'] = $user['role_id']; // استفاده از role_id به جای role
            $_SESSION['plant'] = $plant;
            $_SESSION['unit'] = $unit;

            // بررسی اینکه آیا کاربر ادمین سیستم است
            $_SESSION['is_admin'] = ($user['role_id'] == 1); // فرض بر این است که نقش با شناسه 1، ادمین سیستم است

            // بارگذاری دسترسی‌های کاربر در نشست با استفاده از AccessControl
            require_once __DIR__ . '/../helpers/AccessControl.php';
            AccessControl::loadUserPermissions($user['id']);

            // لاگ برای دیباگ
            error_log("LoginController: ورود موفقیت‌آمیز از طریق LDAP. user_id: " . $_SESSION['user_id'] . ", role_id: " . $_SESSION['role_id']);

            header('Location: /support_system/dashboard');
            exit;
        }

        $error_message = "کاربر در Active Directory یافت نشد.";
        error_log("LoginController: کاربر در LDAP یافت نشد.");
        $this->index($error_message);
    }

    // متد ورود کاربر
    public function login() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // بررسی درخواست POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = $_POST['username'];
            $password = $_POST['password'];

            // جستجوی کاربر در پایگاه داده
            $userModel = new User();
            $user = $userModel->findByUsername($username);

            if ($user) {
                // بررسی رمز عبور برای کاربران لوکال
                if ($user['user_type'] === 'local' && password_verify($password, $user['password'])) {
                    // ایجاد نشست
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['fullname'] = $user['fullname'];
                    $_SESSION['role'] = $user['role_id']; // استفاده از role_id به جای role

                    // دریافت اطلاعات پلنت و واحد از پایگاه داده (در صورت وجود)
                    $_SESSION['plant'] = $user['plant'] ?? 'نامشخص';
                    $_SESSION['unit'] = $user['unit'] ?? 'نامشخص';

                    // بررسی اینکه آیا کاربر ادمین سیستم است
                    $_SESSION['is_admin'] = ($user['role_id'] == 1); // فرض بر این است که نقش با شناسه 1، ادمین سیستم است

                    // بارگذاری دسترسی‌های کاربر در نشست با استفاده از AccessControl
                    require_once __DIR__ . '/../helpers/AccessControl.php';
                    AccessControl::loadUserPermissions($user['id']);

                    // هدایت به داشبورد
                    header('Location: /support_system/dashboard');
                    exit;
                }

                // بررسی ورود کاربران شبکه
                if ($user['user_type'] === 'network') {
                    // ایجاد نشست برای کاربر شبکه
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['fullname'] = $user['fullname'];
                    $_SESSION['role_id'] = $user['role_id']; // استفاده از role_id به جای role

                    // دریافت اطلاعات پلنت و واحد از پایگاه داده (در صورت وجود)
                    $_SESSION['plant'] = $user['plant'] ?? 'نامشخص';
                    $_SESSION['unit'] = $user['unit'] ?? 'نامشخص';

                    // بررسی اینکه آیا کاربر ادمین سیستم است
                    $_SESSION['is_admin'] = ($user['role_id'] == 1); // فرض بر این است که نقش با شناسه 1، ادمین سیستم است

                    // بارگذاری دسترسی‌های کاربر در نشست با استفاده از AccessControl
                    require_once __DIR__ . '/../helpers/AccessControl.php';
                    AccessControl::loadUserPermissions($user['id']);

                    // هدایت به داشبورد
                    header('Location: /support_system/dashboard');
                    exit;
                }
            } else {
                // اگر کاربر پیدا نشد، فرض بر این است که کاربر شبکه است
                $roleId = 3; // نقش پیش‌فرض برای کاربران شبکه
                $email = $username . "@domain.com"; // ایجاد ایمیل فرضی
                $fullname = "کاربر شبکه"; // نام کامل پیش‌فرض

                // ذخیره کاربر شبکه در پایگاه داده
                $result = $userModel->createUser($username, $password, $email, $fullname, $roleId, null, null, 'نامشخص', 'نامشخص', 'network');

                if ($result) {
                    // بازیابی اطلاعات کاربر جدید
                    $user = $userModel->findByUsername($username);

                    // ایجاد نشست
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['fullname'] = $user['fullname'];
                    $_SESSION['role_id'] = $user['role_id']; // استفاده از role_id به جای role
                    $_SESSION['plant'] = 'نامشخص';
                    $_SESSION['unit'] = 'نامشخص';

                    // بررسی اینکه آیا کاربر ادمین سیستم است
                    $_SESSION['is_admin'] = ($user['role_id'] == 1); // فرض بر این است که نقش با شناسه 1، ادمین سیستم است

                    // بارگذاری دسترسی‌های کاربر در نشست با استفاده از AccessControl
                    require_once __DIR__ . '/../helpers/AccessControl.php';
                    AccessControl::loadUserPermissions($user['id']);

                    // هدایت به داشبورد
                    header('Location: /support_system/dashboard');
                    exit;
                } else {
                    // خطا در ذخیره کاربر
                    $_SESSION['error'] = "خطا در ایجاد کاربر جدید.";
                    header('Location: /login');
                    exit;
                }
            }

            // اگر رمز عبور اشتباه باشد
            $_SESSION['error'] = "نام کاربری یا رمز عبور اشتباه است.";
            header('Location: /login');
            exit;
        }
    }

    // متد خروج کاربر
    public function logout() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_destroy();
        header('Location: /login');
        exit;
    }
}