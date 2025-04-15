<?php
$cssLink = 'profile.css';
// تنظیم عنوان صفحه
$pageTitle = 'پروفایل کاربری';

// اضافه کردن فایل header
include __DIR__ . '/header.php';

// بررسی وضعیت جلسه (Session)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// بررسی اینکه کاربر وارد شده است یا خیر
if (!isset($_SESSION['user_id'])) {
    header('Location: /support_system/login');
    exit;
}

// اطلاعات کاربر از کنترلر ارسال شده است
$user = $user ?? [];

// تعریف نام‌های نقش‌ها
$roleNames = [
    1 => 'مدیر سیستم',
    2 => 'کاربر عادی',
    3 => 'کارشناس پشتیبانی',
    4 => 'مدیر بخش'
];
?>

<div class="container mt-4">
    <div class="row align-items-center mb-3">
        <!-- Breadcrumbs -->
        <div class="col-lg-8 col-md-6 col-sm-12">
            <?php echo generateBreadcrumbs(); ?>
        </div>

        <!-- دکمه‌ها -->
        <div class="col-lg-4 col-md-6 col-sm-12">
            <div class="d-flex justify-content-end flex-wrap align-items-center">
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createTicketModal">
                    <i class="bi bi-plus-circle"></i> ثبت درخواست کار
                </button>
            </div>
        </div>
    </div>
</div>

<main class="container mt-2">
    <div class="row">
        <!-- ستون اصلی اطلاعات کاربر -->
        <div class="col-lg-6 col-md-12">
            <!-- کارت پروفایل -->
            <div class="card profile-card mb-3">
                <div class="card-header bg-primary text-white py-2">
                    <h5 class="text-center mb-0">اطلاعات کاربری</h5>
                </div>
                <div class="card-body p-3">
                    <!-- تصویر پروفایل و اطلاعات اصلی -->
                    <div class="row">
                        <div class="col-md-4 text-center mb-3">
                            <div class="profile-image-container mx-auto">
                                <img src="<?php echo $user['profile_image'] ?? '/assets/images/default-avatar.png'; ?>" alt="تصویر پروفایل" class="rounded-circle profile-image">
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary mt-2" data-bs-toggle="modal" data-bs-target="#profileImageModal">
                                <i class="fas fa-camera"></i> تغییر تصویر
                            </button>
                        </div>
                        <div class="col-md-8">
                            <!-- پیام‌های موفقیت و خطا -->
                            <?php if (isset($_SESSION['success'])): ?>
                                <div class="alert alert-success py-2 mb-2">
                                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                                </div>
                            <?php endif; ?>

                            <?php if (isset($_SESSION['error'])): ?>
                                <div class="alert alert-danger py-2 mb-2">
                                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                                </div>
                            <?php endif; ?>

                            <!-- اطلاعات اصلی کاربر -->
                            <div class="mb-2">
                                <strong><i class="fas fa-user"></i> نام کامل:</strong>
                                <p class="user-info"><?php echo htmlspecialchars($user['fullname'] ?? 'نامشخص'); ?></p>
                            </div>
                            <div class="mb-2">
                                <strong><i class="fas fa-user-tag"></i> نقش:</strong>
                                <p class="user-info"><?php echo htmlspecialchars($roleNames[$user['role_id']] ?? 'نامشخص'); ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- سایر اطلاعات کاربر -->
                    <div class="row mt-2">
                        <div class="col-md-6">
                            <div class="mb-2">
                                <strong><i class="fas fa-envelope"></i> ایمیل:</strong>
                                <p class="user-info"><?php echo htmlspecialchars($user['email'] ?? 'نامشخص'); ?></p>
                            </div>
                            <div class="mb-2">
                                <strong><i class="fas fa-phone"></i> تماس داخلی:</strong>
                                <p class="user-info"><?php echo htmlspecialchars($user['phone'] ?? 'نامشخص'); ?></p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-2">
                                <strong><i class="fas fa-mobile-alt"></i> شماره همراه:</strong>
                                <p class="user-info"><?php echo htmlspecialchars($user['mobile'] ?? 'نامشخص'); ?></p>
                            </div>
                            <div class="mb-2">
                                <strong><i class="fas fa-building"></i> پلنت:</strong>
                                <p class="user-info"><?php echo htmlspecialchars($user['plant'] ?? 'نامشخص'); ?></p>
                            </div>
                            <div class="mb-2">
                                <strong><i class="fas fa-sitemap"></i> واحد:</strong>
                                <p class="user-info"><?php echo htmlspecialchars($user['unit'] ?? 'نامشخص'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- فرم ویرایش اطلاعات -->
            <div class="card mb-3">
                <div class="card-header bg-secondary text-white py-2">
                    <h5 class="text-center mb-0">ویرایش اطلاعات</h5>
                </div>
                <div class="card-body p-3">
                    <form method="POST" action="/support_system/profile/update">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-2">
                                    <label for="email" class="form-label small">ایمیل:</label>
                                    <input type="email" class="form-control form-control-sm" name="email" id="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                                    <div class="form-text text-muted small">آدرس ایمیل معتبر وارد کنید</div>
                                </div>
                                <div class="mb-2">
                                    <label for="phone" class="form-label small">شماره تماس داخلی:</label>
                                    <input type="text" class="form-control form-control-sm" name="phone" id="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" pattern="\d{4}" title="شماره تماس داخلی باید دقیقاً 4 رقم باشد" required>
                                </div>
                                <div class="mb-2">
                                    <label for="mobile" class="form-label small">شماره همراه:</label>
                                    <input type="text" class="form-control form-control-sm" name="mobile" id="mobile" value="<?php echo htmlspecialchars($user['mobile'] ?? ''); ?>" pattern="09\d{9}" title="شماره همراه باید با 09 شروع شود و 11 رقم باشد">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-2">
                                    <label for="plant" class="form-label small">پلنت:</label>
                                    <select class="form-select form-select-sm" name="plant" id="plant" required>
                                        <option value="کارخانه گندله‌سازی" <?php echo ($user['plant'] === 'کارخانه گندله‌سازی') ? 'selected' : ''; ?>>کارخانه گندله‌سازی</option>
                                        <option value="کارخانه فولادسازی" <?php echo ($user['plant'] === 'کارخانه فولادسازی') ? 'selected' : ''; ?>>کارخانه فولادسازی</option>
                                        <option value="کارخانه کنسانتره" <?php echo ($user['plant'] === 'کارخانه کنسانتره') ? 'selected' : ''; ?>>کارخانه کنسانتره</option>
                                        <option value="نیروگاه" <?php echo ($user['plant'] === 'نیروگاه') ? 'selected' : ''; ?>>نیروگاه</option>
                                        <option value="دفتر کرمان" <?php echo ($user['plant'] === 'دفتر کرمان') ? 'selected' : ''; ?>>دفتر کرمان</option>
                                        <option value="دفتر تهران" <?php echo ($user['plant'] === 'دفتر تهران') ? 'selected' : ''; ?>>دفتر تهران</option>
                                        <option value="دفتر اصفهان" <?php echo ($user['plant'] === 'دفتر اصفهان') ? 'selected' : ''; ?>>دفتر اصفهان</option>
                                    </select>
                                </div>
                                <div class="mb-2">
                                    <label for="unit" class="form-label small">واحد:</label>
                                    <select class="form-select form-select-sm" name="unit" id="unit" required>
                                        <option value="">انتخاب کنید</option>
                                        <option value="فناوری اطلاعات" <?php echo ($user['unit'] === 'فناوری اطلاعات') ? 'selected' : ''; ?>>فناوری اطلاعات</option>
                                        <option value="منابع انسانی" <?php echo ($user['unit'] === 'منابع انسانی') ? 'selected' : ''; ?>>منابع انسانی</option>
                                        <option value="مالی" <?php echo ($user['unit'] === 'مالی') ? 'selected' : ''; ?>>مالی</option>
                                        <option value="تولید" <?php echo ($user['unit'] === 'تولید') ? 'selected' : ''; ?>>تولید</option>
                                        <option value="تعمیرات" <?php echo ($user['unit'] === 'تعمیرات') ? 'selected' : ''; ?>>تعمیرات</option>
                                        <option value="بازرگانی" <?php echo ($user['unit'] === 'بازرگانی') ? 'selected' : ''; ?>>بازرگانی</option>
                                        <option value="حراست" <?php echo ($user['unit'] === 'حراست') ? 'selected' : ''; ?>>حراست</option>
                                        <option value="HSE" <?php echo ($user['unit'] === 'HSE') ? 'selected' : ''; ?>>HSE</option>
                                    </select>
                                </div>
                                <div class="d-grid mt-3">
                                    <button type="submit" class="btn btn-primary btn-sm">ذخیره تغییرات</button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- ستون تنظیمات و تاریخچه -->
        <div class="col-lg-6 col-md-12">
            <!-- تنظیمات امنیتی -->
            <div class="card mb-3">
                <div class="card-header bg-danger text-white py-2">
                    <h5 class="text-center mb-0">تنظیمات امنیتی</h5>
                </div>
                <div class="card-body p-3">
                    <form method="POST" action="/support_system/profile/update-security" class="mb-2">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <div class="mb-2">
                            <div class="checkbox-wrapper">
                                <label class="form-check-label small" for="two_factor_auth">فعال‌سازی تأیید دو مرحله‌ای</label>
                                <input type="checkbox" class="form-check-input" name="two_factor_auth" id="two_factor_auth" <?php echo ($user['two_factor_auth'] ?? false) ? 'checked' : ''; ?>>
                            </div>
                            <div class="form-text text-muted small">با فعال‌سازی، کد تأیید به شماره همراه شما ارسال می‌شود.</div>
                        </div>
                        
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <div class="mb-2">
                                    <label class="form-label small">آخرین ورود:</label>
                                    <p class="text-muted small mb-0"><?php echo htmlspecialchars($user['last_login'] ?? 'نامشخص'); ?></p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-2">
                                    <label class="form-label small">IP آخرین ورود:</label>
                                    <p class="text-muted small mb-0"><?php echo htmlspecialchars($user['last_login_ip'] ?? 'نامشخص'); ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mt-2">
                            <div class="col-md-6">
                                <button type="submit" class="btn btn-primary btn-sm w-100">ذخیره تنظیمات</button>
                            </div>
                            <div class="col-md-6">
                                <button type="button" class="btn btn-danger btn-sm w-100" data-bs-toggle="modal" data-bs-target="#logoutAllModal">
                                    خروج از تمام دستگاه‌ها
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- تنظیمات اعلان‌ها -->
            <div class="card mb-3">
                <div class="card-header bg-success text-white py-2">
                    <h5 class="text-center mb-0">تنظیمات اعلان‌ها</h5>
                </div>
                <div class="card-body p-3">
                    <form method="POST" action="/support_system/profile/update-notifications">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="checkbox-wrapper mb-2">
                                    <label class="form-check-label small" for="email_notifications">دریافت اعلان‌ها از طریق ایمیل</label>
                                    <input type="checkbox" class="form-check-input" name="email_notifications" id="email_notifications" <?php echo ($user['email_notifications'] ?? true) ? 'checked' : ''; ?>>
                                </div>
                                <div class="checkbox-wrapper mb-2">
                                    <label class="form-check-label small" for="sms_notifications">دریافت اعلان‌ها از طریق پیامک</label>
                                    <input type="checkbox" class="form-check-input" name="sms_notifications" id="sms_notifications" <?php echo ($user['sms_notifications'] ?? false) ? 'checked' : ''; ?>>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small mb-1">نوع اعلان‌ها:</label>
                                <div class="checkbox-wrapper mb-1">
                                    <label class="form-check-label small" for="new_ticket">درخواست کار جدید</label>
                                    <input class="form-check-input" type="checkbox" name="notification_types[]" value="new_ticket" id="new_ticket" <?php echo (in_array('new_ticket', $user['notification_types'] ?? [])) ? 'checked' : ''; ?>>
                                </div>
                                <div class="checkbox-wrapper mb-1">
                                    <label class="form-check-label small" for="ticket_status">تغییر وضعیت درخواست</label>
                                    <input class="form-check-input" type="checkbox" name="notification_types[]" value="ticket_status" id="ticket_status" <?php echo (in_array('ticket_status', $user['notification_types'] ?? [])) ? 'checked' : ''; ?>>
                                </div>
                                <div class="checkbox-wrapper mb-1">
                                    <label class="form-check-label small" for="ticket_comment">نظر جدید در درخواست</label>
                                    <input class="form-check-input" type="checkbox" name="notification_types[]" value="ticket_comment" id="ticket_comment" <?php echo (in_array('ticket_comment', $user['notification_types'] ?? [])) ? 'checked' : ''; ?>>
                                </div>
                                <div class="checkbox-wrapper mb-1">
                                    <label class="form-check-label small" for="system_update">به‌روزرسانی سیستم</label>
                                    <input class="form-check-input" type="checkbox" name="notification_types[]" value="system_update" id="system_update" <?php echo (in_array('system_update', $user['notification_types'] ?? [])) ? 'checked' : ''; ?>>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-grid mt-2">
                            <button type="submit" class="btn btn-success btn-sm">ذخیره تنظیمات اعلان‌ها</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- تاریخچه فعالیت‌ها -->
            <div class="card mb-3">
                <div class="card-header bg-info text-white py-2">
                    <h5 class="text-center mb-0">تاریخچه فعالیت‌ها</h5>
                </div>
                <div class="card-body p-3">
                    <?php if (!empty($userActivities)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped">
                                <thead>
                                    <tr>
                                        <th class="small">تاریخ</th>
                                        <th class="small">عملیات</th>
                                        <th class="small">جزئیات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($userActivities as $activity): ?>
                                        <tr>
                                            <td class="small"><?php echo htmlspecialchars($activity['date']); ?></td>
                                            <td class="small"><?php echo htmlspecialchars($activity['action']); ?></td>
                                            <td class="small"><?php echo htmlspecialchars($activity['details']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-center text-muted small">هیچ فعالیتی ثبت نشده است.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- مدال آپلود تصویر پروفایل -->
<div class="modal fade" id="profileImageModal" tabindex="-1" aria-labelledby="profileImageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title" id="profileImageModalLabel">تغییر تصویر پروفایل</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="/support_system/profile/update-image" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <div class="mb-3">
                        <label for="profile_image" class="form-label small">انتخاب تصویر جدید:</label>
                        <input type="file" class="form-control form-control-sm" id="profile_image" name="profile_image" accept="image/*" required>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-sm">آپلود تصویر</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- مدال خروج از تمام دستگاه‌ها -->
<div class="modal fade" id="logoutAllModal" tabindex="-1" aria-labelledby="logoutAllModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title" id="logoutAllModalLabel">خروج از تمام دستگاه‌ها</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="small">آیا مطمئن هستید که می‌خواهید از تمام دستگاه‌ها خارج شوید؟</p>
                <p class="text-muted small">این عمل باعث می‌شود که از تمام دستگاه‌هایی که با حساب کاربری خود وارد شده‌اید، خارج شوید.</p>
            </div>
            <div class="modal-footer py-1">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">انصراف</button>
                <form method="POST" action="/support_system/profile/logout-all">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <button type="submit" class="btn btn-danger btn-sm">خروج از تمام دستگاه‌ها</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- اسکریپت‌های اضافی -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // اعتبارسنجی شماره همراه
    const mobileInput = document.getElementById('mobile');
    if (mobileInput) {
        mobileInput.addEventListener('input', function() {
            const value = this.value;
            if (value && !value.match(/^09\d{9}$/)) {
                this.setCustomValidity('شماره همراه باید با 09 شروع شود و 11 رقم باشد');
            } else {
                this.setCustomValidity('');
            }
        });
    }
    
});
</script>

<!-- اضافه کردن فایل footer -->
<?php include 'footer.php'; ?>