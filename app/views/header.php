<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . '/../helpers/breadcrumbs.php'; // مسیر فایل حاوی تابع
require_once __DIR__ . '/../helpers/AccessControl.php'; // اضافه کردن کلاس AccessControl
$accessControl = new AccessControl(); // ایجاد نمونه از کلاس AccessControl
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'سیستم نگهداری و تعمیرات'; ?></title>
    <!-- لینک CSS Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <!-- لینک JS Bootstrap و Popper.js -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- لینک Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <!-- لینک CSS سفارشی -->
    <link rel="stylesheet" href="/assets/css/global.css">
    <?php echo '<link rel="stylesheet" href="/assets/css/' . $cssLink . '">'; ?>
    <link href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>
    <!-- بارگذاری jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

</head>
<body>
    <header class="bg-primary text-white p-3">
        <div class="container">
            <h1 class="h4 mb-0 text-center"><?php echo $pageTitle ?? 'سیستم نگهداری و تعمیرات'; ?></h1>
        </div>
    </header>
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
    <div class="container">
        <a class="navbar-brand text-primary" href="/support_system/dashboard">سیستم نگهداری و تعمیرات</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="تغییر ناوبری">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link text-dark" href="/support_system/dashboard">داشبورد</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-dark" href="/support_system/tickets">درخواست کار‌ها</a>
                </li>

                <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle text-dark" href="#" id="assetsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    مدیریت کاربران
                </a>
                    <ul class="dropdown-menu" aria-labelledby="assetsDropdown">
                        <li><a class="dropdown-item" href="/support_system/users">کاربران</a></li>
                        <li><a class="dropdown-item" href="/support_system/roles">مدیریت نقش‌ها</a></li>
                    </ul>
                </li>
                
                <!-- منوی مدیریت -->
                <?php if ($accessControl->hasPermission('view_assets')): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle text-dark" href="#" id="assetsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        مدیریت تجهیز‌ها
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="assetsDropdown">
                        <li><a class="dropdown-item" href="/support_system/assets">لیست تجهیز‌ها</a></li>
                        <li><a class="dropdown-item" href="/support_system/assets/dashboard">داشبورد تجهیز‌ها</a></li>
                        <?php if ($accessControl->hasPermission('view_asset_categories')): ?>
                        <li><a class="dropdown-item" href="/support_system/asset_categories">دسته‌بندی‌ها</a></li>
                        <?php endif; ?>
                        <?php if ($accessControl->hasPermission('view_asset_models')): ?>
                        <li><a class="dropdown-item" href="/support_system/asset_models">مدل‌ها</a></li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li><h6 class="dropdown-header">مدیریت سخت‌افزار</h6></li>
                        <li><a class="dropdown-item" href="/support_system/hardware">لیست تجهیزات سخت‌افزاری</a></li>
                        <li><a class="dropdown-item" href="/support_system/hardware/assignments">تخصیص تجهیزات</a></li>
                        <li><a class="dropdown-item" href="/support_system/hardware/components">قطعات سخت‌افزاری</a></li>
                        <?php if ($accessControl->hasPermission('manage_maintenance')): ?>
                        <li><hr class="dropdown-divider"></li>
                        <li><h6 class="dropdown-header">سرویس‌های ادواری</h6></li>
                        <li><a class="dropdown-item" href="/support_system/maintenance">همه سرویس‌ها</a></li>
                        <li><a class="dropdown-item" href="/support_system/maintenance/upcoming">سرویس‌های پیش رو</a></li>
                        <li><a class="dropdown-item" href="/support_system/maintenance/overdue">سرویس‌های معوق</a></li>
                        <li><a class="dropdown-item" href="/support_system/maintenance_types">انواع سرویس</a></li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li><h6 class="dropdown-header">گزارش‌ها</h6></li>
                        <li><a class="dropdown-item" href="/support_system/assets/reports">گزارش‌های تجهیز‌ها</a></li>
                        <li><a class="dropdown-item" href="/support_system/hardware/reports">گزارش‌های سخت‌افزار</a></li>
                    </ul>
                </li>
                <?php endif; ?>

                <!-- منوی گزارش‌ها -->
                <?php if ($accessControl->hasPermission('view_reports')): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle text-dark" href="#" id="reportsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        گزارش‌ها
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="reportsDropdown">
                        <li><a class="dropdown-item" href="/support_system/reports/tickets">گزارش درخواست‌های کار</a></li>
                        <li><a class="dropdown-item" href="/support_system/reports/assets">گزارش تجهیز‌ها</a></li>
                        <li><a class="dropdown-item" href="/support_system/reports/maintenance">گزارش سرویس‌های ادواری</a></li>
                        <li><a class="dropdown-item" href="/support_system/reports/hardware">گزارش تجهیزات سخت‌افزاری</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="/support_system/reports/performance">گزارش عملکرد تیم پشتیبانی</a></li>
                    </ul>
                </li>
                <?php endif; ?>

                <!-- منوی تنظیمات -->
                <?php if ($accessControl->hasPermission('manage_settings')): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle text-dark" href="#" id="settingsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        تنظیمات
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="settingsDropdown">
                        <li><a class="dropdown-item" href="/support_system/settings/general">تنظیمات عمومی</a></li>
                        <li><a class="dropdown-item" href="/support_system/settings/notifications">تنظیمات اعلان‌ها</a></li>
                        <li><a class="dropdown-item" href="/support_system/settings/email">تنظیمات ایمیل</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="/support_system/logs">گزارش‌های سیستم</a></li>
                    </ul>
                </li>
                <?php endif; ?>
            </ul>

            <?php
                // بررسی وضعیت نشست (Session)
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }

                // دریافت اطلاعات کاربر از نشست
                $fullname = $_SESSION['fullname'] ?? 'کاربر';
                $username = $_SESSION['username'] ?? 'نامشخص';
                
                // تعیین پیام خوش‌آمدگویی بر اساس زمان روز
                $hour = date('H');
                if ($hour >= 5 && $hour < 12) {
                    $greeting = "صبح بخیر";
                } elseif ($hour >= 12 && $hour < 17) {
                    $greeting = "ظهر بخیر";
                } elseif ($hour >= 17 && $hour < 21) {
                    $greeting = "عصر بخیر";
                } else {
                    $greeting = "شب بخیر";
                }
            ?>

            <nav class="navbar navbar-expand-lg navbar-light bg-light">
                <div class="container">
                    <div class="collapse navbar-collapse" id="navbarNav">
                        <div class="welcome-message me-3 d-none d-lg-block">
                            <span class="text-muted"><i class="fas fa-hand-sparkles text-warning"></i> <?php echo $greeting; ?>،</span>
                        </div>
                        <ul class="navbar-nav ms-auto">
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-user-circle me-1"></i> <?php echo htmlspecialchars($fullname); ?>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                    <li><a class="dropdown-item" href="/support_system/profile">
                                        <i class="fas fa-id-card me-1"></i> پروفایل
                                    </a></li>
                                    <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#changePasswordModal">
                                        <i class="fas fa-key me-1"></i> تغییر رمز
                                    </a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-danger" href="/support_system/logout">
                                        <i class="fas fa-sign-out-alt me-1"></i> خروج
                                    </a></li>
                                </ul>
                            </li>
                        </ul>
                    </div>
                </div>
            </nav>
            <!-- کلید حالت تاریک با آیکون -->
            <button id="darkModeToggle" class="btn btn-sm btn-outline-secondary rounded-circle ms-2" title="تغییر حالت روشنایی">
                <i class="fas fa-moon"></i>
            </button>
        </div>
    </div>
</nav>

<!-- مودال تغییر رمز عبور -->
<div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="changePasswordModalLabel">تغییر رمز عبور</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن"></button>
            </div>
            <div class="modal-body">
                <form id="changePasswordForm" method="POST" action="/support_system/change_password">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <div class="mb-3">
                        <label for="current_password" class="form-label">رمز عبور فعلی:</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                            <button class="btn btn-outline-secondary toggle-password" type="button" data-target="current_password">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="new_password" class="form-label">رمز عبور جدید:</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                            <button class="btn btn-outline-secondary toggle-password" type="button" data-target="new_password">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <div class="form-text">
                            رمز عبور باید حداقل ۸ کاراکتر و شامل حروف بزرگ، کوچک، اعداد و علائم خاص باشد.
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">تکرار رمز عبور جدید:</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            <button class="btn btn-outline-secondary toggle-password" type="button" data-target="confirm_password">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div id="password-strength" class="mb-3">
                        <div class="progress" style="height: 5px;">
                            <div id="password-strength-bar" class="progress-bar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <small id="password-strength-text" class="form-text">قدرت رمز عبور: ضعیف</small>
                    </div>
                    
                    <div id="password-match-message" class="alert alert-danger d-none">
                        رمز عبور و تکرار آن مطابقت ندارند.
                    </div>
                    
                    <div id="password-change-result"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                <button type="button" class="btn btn-primary" id="submitChangePassword">ذخیره تغییرات</button>
            </div>
        </div>
    </div>
</div>

<!-- اسکریپت مربوط به تغییر رمز عبور -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // نمایش/مخفی کردن رمز عبور
    const togglePasswordButtons = document.querySelectorAll('.toggle-password');
    togglePasswordButtons.forEach(button => {
        button.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const input = document.getElementById(targetId);
            const icon = this.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        });
    });
    
    // بررسی قدرت رمز عبور
    const newPasswordInput = document.getElementById('new_password');
    const passwordStrengthBar = document.getElementById('password-strength-bar');
    const passwordStrengthText = document.getElementById('password-strength-text');
    
    newPasswordInput.addEventListener('input', function() {
        const password = this.value;
        let strength = 0;
        
        // معیارهای قدرت رمز عبور
        if (password.length >= 8) strength += 20;
        if (password.match(/[a-z]+/)) strength += 20;
        if (password.match(/[A-Z]+/)) strength += 20;
        if (password.match(/[0-9]+/)) strength += 20;
        if (password.match(/[^a-zA-Z0-9]+/)) strength += 20;
        
        // تنظیم نوار پیشرفت و متن
        passwordStrengthBar.style.width = strength + '%';
        
        if (strength < 40) {
            passwordStrengthBar.className = 'progress-bar bg-danger';
            passwordStrengthText.textContent = 'قدرت رمز عبور: ضعیف';
        } else if (strength < 80) {
            passwordStrengthBar.className = 'progress-bar bg-warning';
            passwordStrengthText.textContent = 'قدرت رمز عبور: متوسط';
        } else {
            passwordStrengthBar.className = 'progress-bar bg-success';
            passwordStrengthText.textContent = 'قدرت رمز عبور: قوی';
        }
    });
    
    // بررسی مطابقت رمز عبور
    const confirmPasswordInput = document.getElementById('confirm_password');
    const passwordMatchMessage = document.getElementById('password-match-message');
    
    function checkPasswordMatch() {
        const newPassword = newPasswordInput.value;
        const confirmPassword = confirmPasswordInput.value;
        
        if (confirmPassword && newPassword !== confirmPassword) {
            passwordMatchMessage.classList.remove('d-none');
            return false;
        } else {
            passwordMatchMessage.classList.add('d-none');
            return true;
        }
    }
    
    confirmPasswordInput.addEventListener('input', checkPasswordMatch);
    newPasswordInput.addEventListener('input', function() {
        if (confirmPasswordInput.value) {
            checkPasswordMatch();
        }
    });
    
    // ارسال فرم با AJAX
    const submitButton = document.getElementById('submitChangePassword');
    const changePasswordForm = document.getElementById('changePasswordForm');
    const resultContainer = document.getElementById('password-change-result');
    
    submitButton.addEventListener('click', function() {
        // بررسی اعتبارسنجی فرم
        if (!changePasswordForm.checkValidity() || !checkPasswordMatch()) {
            changePasswordForm.reportValidity();
            return;
        }
        
        // ارسال فرم با AJAX
        const formData = new FormData(changePasswordForm);
        
        fetch('/support_system/change_password', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                resultContainer.innerHTML = `<div class="alert alert-success">${data.message}</div>`;
                // پاک کردن فرم
                changePasswordForm.reset();
                // بستن مودال بعد از 2 ثانیه
                setTimeout(() => {
                    const modal = bootstrap.Modal.getInstance(document.getElementById('changePasswordModal'));
                    modal.hide();
                    resultContainer.innerHTML = '';
                }, 2000);
            } else {
                resultContainer.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
            }
        })
        .catch(error => {
            resultContainer.innerHTML = '<div class="alert alert-danger">خطا در ارتباط با سرور. لطفا دوباره تلاش کنید.</div>';
            console.error('Error:', error);
        });
    });
    
    // پاک کردن پیام‌ها هنگام بستن مودال
    const changePasswordModal = document.getElementById('changePasswordModal');
    changePasswordModal.addEventListener('hidden.bs.modal', function () {
        resultContainer.innerHTML = '';
        passwordMatchMessage.classList.add('d-none');
        changePasswordForm.reset();
    });
    
    // حالت تاریک
    const darkModeToggle = document.getElementById('darkModeToggle');
    const body = document.body;
    const darkModeIcon = darkModeToggle.querySelector('i');

    // بررسی وضعیت حالت تاریک در localStorage
    const isDarkMode = localStorage.getItem('darkMode') === 'true';

    // اعمال حالت تاریک در صورت نیاز
    if (isDarkMode) {
        body.classList.add('dark-mode');
        darkModeIcon.classList.remove('fa-moon');
        darkModeIcon.classList.add('fa-sun');
        darkModeToggle.setAttribute('title', 'تغییر به حالت روشن');
    }

    // تغییر حالت با کلیک روی دکمه
    darkModeToggle.addEventListener('click', function() {
        body.classList.toggle('dark-mode');
        const isDark = body.classList.contains('dark-mode');
        localStorage.setItem('darkMode', isDark);
        
        if (isDark) {
            darkModeIcon.classList.remove('fa-moon');
            darkModeIcon.classList.add('fa-sun');
            darkModeToggle.setAttribute('title', 'تغییر به حالت روشن');
        } else {
            darkModeIcon.classList.remove('fa-sun');
            darkModeIcon.classList.add('fa-moon');
            darkModeToggle.setAttribute('title', 'تغییر به حالت تاریک');
        }
    });
});
</script>