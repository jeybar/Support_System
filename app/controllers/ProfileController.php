<?php

require_once __DIR__ . '/../models/User.php';

class ProfileController {
    private $userModel;

    public function __construct() {
        $this->userModel = new User();
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    // نمایش پروفایل کاربری
    public function index() {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /support_system/login');
            exit;
        }

        $user = $this->userModel->getUserById($_SESSION['user_id']);
        require_once __DIR__ . '/../views/profile.php';
    }

    // به‌روزرسانی اطلاعات کاربری
    public function updateProfile() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = $_POST['email'] ?? '';
            $phone = $_POST['phone'] ?? '';
            $mobile = $_POST['mobile'] ?? '';
            $plant = $_POST['plant'] ?? '';
            $unit = $_POST['unit'] ?? '';
    
            // اعتبارسنجی داده‌ها
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $_SESSION['error'] = 'ایمیل معتبر نیست.';
                header('Location: /support_system/profile');
                exit;
            }
    
            if (!empty($phone) && !preg_match('/^\d{4}$/', $phone)) {
                $_SESSION['error'] = 'شماره تماس داخلی باید دقیقاً 4 رقم باشد.';
                header('Location: /support_system/profile');
                exit;
            }
    
            if (!empty($mobile) && !preg_match('/^[0-9]{10,15}$/', $mobile)) {
                $_SESSION['error'] = 'شماره همراه معتبر نیست.';
                header('Location: /support_system/profile');
                exit;
            }
    
            // به‌روزرسانی اطلاعات در پایگاه داده
            $result = $this->userModel->updateUser($_SESSION['user_id'], $email, $phone, $mobile, $plant, $unit);
    
            if ($result) {
                $_SESSION['success'] = 'اطلاعات با موفقیت به‌روزرسانی شد.';
            } else {
                $_SESSION['error'] = 'خطا در به‌روزرسانی اطلاعات.';
            }
    
            header('Location: /support_system/profile');
            exit;
        }
    }

    public function changePasswordPage() {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /support_system/login');
            exit;
        }

        require_once __DIR__ . '/../views/change_password.php';
    }
    
    //متد تغییر رمز عبور
    public function changePassword() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
    
            // بررسی مطابقت رمز عبور جدید و تکرار آن
            if ($newPassword !== $confirmPassword) {
                $_SESSION['error'] = 'رمز عبور جدید و تکرار آن یکسان نیستند.';
                header('Location: /support_system/change_password');
                exit;
            }
    
            // دریافت اطلاعات کاربر از پایگاه داده
            $user = $this->userModel->getUserById($_SESSION['user_id']);
            if (!password_verify($currentPassword, $user['password'])) {
                $_SESSION['error'] = 'رمز عبور فعلی اشتباه است.';
                header('Location: /support_system/change_password');
                exit;
            }
    
            // هش کردن رمز عبور جدید
            $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
            $result = $this->userModel->updatePassword($_SESSION['user_id'], $hashedPassword);
    
            // بررسی موفقیت‌آمیز بودن تغییر رمز عبور
            if ($result) {
                $_SESSION['success'] = 'رمز عبور با موفقیت تغییر یافت.';
                header('Location: /support_system/dashboard'); // هدایت به داشبورد
                exit;
            } else {
                $_SESSION['error'] = 'خطا در تغییر رمز عبور.';
                header('Location: /support_system/change_password');
                exit;
            }
        }
    }
}