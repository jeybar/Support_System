<?php
$cssLink = "users.css";

$pageTitle = 'مدیریت کاربران'; // تنظیم عنوان صفحه

// اضافه کردن فایل header
include __DIR__ . '/../header.php';

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
$users = $users ?? [];

// بررسی و مقداردهی پیش‌فرض برای متغیرهای ضروری
$limit = $limit ?? 10; // مقدار پیش‌فرض: 10 رکورد در هر صفحه
$totalPages = $totalPages ?? 1; // مقدار پیش‌فرض: 1 صفحه
$page = $page ?? 1; // مقدار پیش‌فرض: صفحه اول
$totalCount = $totalCount ?? count($users); // مقدار پیش‌فرض: تعداد کاربران موجود

// تابع کمکی برای ساخت URL با حفظ پارامترهای جستجو
function buildUrl($params = []) {
    // پارامترهای فعلی را دریافت می‌کنیم
    $currentParams = $_GET;
    
    // پارامترهای جدید را اضافه یا جایگزین می‌کنیم
    foreach ($params as $key => $value) {
        $currentParams[$key] = $value;
    }
    
    // ساخت رشته کوئری
    $queryString = http_build_query($currentParams);
    
    // ساخت URL کامل
    $baseUrl = '/support_system/users';
    return $baseUrl . ($queryString ? '?' . $queryString : '');
}

// تابع کمکی برای ساخت لینک‌های مرتب‌سازی
function sortLink($column, $label) {
    $currentSortBy = $_GET['sort_by'] ?? 'id';
    $currentOrder = $_GET['order'] ?? 'asc';
    
    $newOrder = ($currentSortBy === $column && $currentOrder === 'asc') ? 'desc' : 'asc';
    $icon = '';
    
    if ($currentSortBy === $column) {
        $icon = ($currentOrder === 'asc') 
            ? '<i class="fas fa-sort-up ms-1"></i>' 
            : '<i class="fas fa-sort-down ms-1"></i>';
    } else {
        $icon = '<i class="fas fa-sort ms-1 opacity-50"></i>';
    }
    
    return '<a href="' . buildUrl(['sort_by' => $column, 'order' => $newOrder]) . '" class="text-white text-decoration-none">' . $label . ' ' . $icon . '</a>';
}

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
                <!-- دکمه افزودن کاربر جدید -->
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    <i class="fas fa-user-plus"></i> افزودن کاربر جدید
                </button>

                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createTicketModal">
                    <i class="bi bi-plus-circle"></i> ثبت درخواست کار
                </button>                
            </div>
        </div>
    </div>
</div>

<main class="container mt-4">
    <div class="d-flex flex-column">
        <!-- نمایش پیام‌های موفقیت یا خطا -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <!-- فرم فیلتر و جستجو -->
        <div id="search-form-container" class="mb-4">    
                <div class="card-body">
                    <form id="search-form" method="GET" action="/support_system/users" class="mb-0">
                        <div class="row g-3">
                            <!-- نام کاربری -->
                            <div class="col-md-3 col-sm-6">
                                <label for="username" class="form-label">نام کاربری:</label>
                                <input type="text" id="username" name="username" class="form-control" placeholder="نام کاربری" 
                                    value="<?php echo htmlspecialchars($_GET['username'] ?? ''); ?>">
                            </div>

                            <!-- نام کامل -->
                            <div class="col-md-3 col-sm-6">
                                <label for="fullname" class="form-label">نام کامل:</label>
                                <input type="text" id="fullname" name="fullname" class="form-control" placeholder="نام کامل" 
                                    value="<?php echo htmlspecialchars($_GET['fullname'] ?? ''); ?>">
                            </div>

                            <!-- نقش -->
                            <div class="col-md-3 col-sm-6">
                                <label for="role" class="form-label">نقش:</label>
                                <select id="role" name="role" class="form-select">
                                    <option value="">همه نقش‌ها</option>
                                    <option value="1" <?php echo (isset($_GET['role']) && $_GET['role'] === '1') ? 'selected' : ''; ?>>مدیر</option>
                                    <option value="2" <?php echo (isset($_GET['role']) && $_GET['role'] === '2') ? 'selected' : ''; ?>>کاربر</option>
                                    <option value="3" <?php echo (isset($_GET['role']) && $_GET['role'] === '3') ? 'selected' : ''; ?>>پشتیبان</option>
                                </select>
                            </div>

                            <!-- نوع کاربر -->
                            <div class="col-md-3 col-sm-6">
                                <label for="user_type" class="form-label">نوع کاربر:</label>
                                <select id="user_type" name="user_type" class="form-select">
                                    <option value="">همه کاربران</option>
                                    <option value="local" <?php echo (isset($_GET['user_type']) && $_GET['user_type'] === 'local') ? 'selected' : ''; ?>>کاربران محلی</option>
                                    <option value="network" <?php echo (isset($_GET['user_type']) && $_GET['user_type'] === 'network') ? 'selected' : ''; ?>>کاربران شبکه</option>
                                </select>
                            </div>

                            <!-- وضعیت -->
                            <div class="col-md-3 col-sm-6">
                                <label for="status" class="form-label">وضعیت:</label>
                                <select id="status" name="status" class="form-select">
                                    <option value="">همه وضعیت‌ها</option>
                                    <option value="1" <?php echo (isset($_GET['status']) && $_GET['status'] === '1') ? 'selected' : ''; ?>>فعال</option>
                                    <option value="0" <?php echo (isset($_GET['status']) && $_GET['status'] === '0') ? 'selected' : ''; ?>>غیرفعال</option>
                                </select>
                            </div>

                            <!-- تاریخ ایجاد -->
                            <div class="col-md-3 col-sm-6">
                                <label for="created_date" class="form-label">تاریخ ایجاد:</label>
                                <input type="date" id="created_date" name="created_date" class="form-control"
                                    value="<?php echo htmlspecialchars($_GET['created_date'] ?? ''); ?>">
                            </div>

                            <!-- دکمه‌ها -->
                            <div class="col-md-3 col-sm-6">
                                <label class="form-label">&nbsp;</label> <!-- برچسب خالی برای هم‌ترازی -->
                                <div class="d-flex justify-content-start">
                                    <button type="submit" class="btn btn-success ms-2">اعمال جستجو</button>
                                    <a href="/support_system/users" class="btn btn-warning">پاک کردن</a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
        </div>

        <!-- جدول کاربران -->
        <div class="user-table-container">
            <div class="table-responsive">
                <table class="table table-striped table-hover general-table text-center fixed-table">
                    <colgroup>
                        <col class="w-8">  <!-- شناسه -->
                        <col class="w-15"> <!-- نام کاربری -->
                        <col class="w-20"> <!-- نام کامل -->
                        <col class="w-10"> <!-- نقش -->
                        <col class="w-10"> <!-- نوع کاربر -->
                        <col class="w-10"> <!-- وضعیت -->
                        <col class="w-20"> <!-- عملیات -->
                    </colgroup>
                    <thead class="table-dark">
                        <tr>
                            <th><?php echo sortLink('id', 'شناسه'); ?></th>
                            <th><?php echo sortLink('username', 'نام کاربری'); ?></th>
                            <th><?php echo sortLink('fullname', 'نام کامل'); ?></th>
                            <th><?php echo sortLink('role_name', 'نقش'); ?></th>
                            <th><?php echo sortLink('user_type', 'نوع کاربر'); ?></th>
                            <th><?php echo sortLink('is_active', 'وضعیت'); ?></th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($users)): ?>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo $user['id']; ?></td>
                                    <td><?php echo htmlspecialchars($user['username'] ?: ''); ?></td>
                                    <td><?php echo htmlspecialchars($user['fullname'] ?: 'بدون نام'); ?></td>
                                    <?php
                                    $roleTranslations = [
                                        'Admin' => 'مدیر',
                                        'User' => 'کاربر',
                                        'support_staff' => 'پشتیبان',
                                    ];
                                    ?>
                                    <td><?php echo $roleTranslations[$user['role_name']] ?? $user['role_name']; ?></td>
                                    <td><?php echo $user['user_type'] === 'network' ? 'شبکه' : 'لوکال'; ?></td>
                                    <td><?php echo $user['is_active'] ? 'فعال' : 'غیرفعال'; ?></td>
                                    <td>
                                        <!-- دکمه ویرایش -->
                                        <a href="#" 
                                            class="btn btn-primary btn-sm edit-user-btn" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#editUserModal" 
                                            data-id="<?php echo $user['id']; ?>" 
                                            data-username="<?php echo htmlspecialchars($user['username']); ?>" 
                                            data-fullname="<?php echo htmlspecialchars($user['fullname']); ?>" 
                                            data-role="<?php echo $user['role_name']; ?>" 
                                            data-user_type="<?php echo $user['user_type']; ?>" 
                                            data-is_active="<?php echo $user['is_active']; ?>">
                                            ویرایش
                                        </a>

                                        <!-- دکمه حذف -->
                                        <form method="POST" action="/support_system/users/delete/<?php echo $user['id']; ?>" style="display:inline;" onsubmit="return confirm('آیا از حذف این کاربر مطمئن هستید؟');">
                                            <button type="submit" class="btn btn-danger btn-sm">حذف</button>
                                        </form>

                                        <!-- دکمه فعال/غیرفعال کردن -->
                                        <form method="POST" action="/support_system/users/toggle_status/<?php echo $user['id']; ?>" style="display:inline;">
                                            <input type="hidden" name="is_active" value="<?php echo $user['is_active'] ? 0 : 1; ?>">
                                            
                                            <!-- ارسال پارامترهای فیلتر و جستجو -->
                                            <?php foreach ($_GET as $key => $value): ?>
                                                <input type="hidden" name="<?php echo htmlspecialchars($key); ?>" value="<?php echo htmlspecialchars($value); ?>">
                                            <?php endforeach; ?>

                                            <button type="submit" class="btn btn-sm <?php echo $user['is_active'] ? 'btn-warning' : 'btn-success'; ?>">
                                                <?php echo $user['is_active'] ? 'غیرفعال کردن' : 'فعال کردن'; ?>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted">هیچ کاربری یافت نشد.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- کانتینر صفحه‌بندی -->
        <div class="pagination-container">
            <!-- دکمه‌های صفحه‌بندی و اطلاعات تعداد نمایش در یک ردیف -->
            <div class="pagination-row">
                <!-- اطلاعات تعداد نمایش (سمت راست) -->
                <div class="pagination-info">
                    نمایش <?php echo ($totalCount > 0) ? (($page - 1) * $limit + 1) : 0; ?> تا <?php echo min($page * $limit, $totalCount); ?> از <?php echo $totalCount; ?> مورد
                </div>

                <!-- دکمه‌های صفحه‌بندی (وسط) -->
                <div class="pagination">
                    <?php if ($totalPages > 1): ?>
                        <ul>
                            <?php if ($page > 1): ?>
                                <li><a href="<?php echo buildUrl(['page' => 1]); ?>">«</a></li>
                                <li><a href="<?php echo buildUrl(['page' => $page - 1]); ?>">قبلی</a></li>
                            <?php endif; ?>
                            
                            <?php
                            // نمایش حداکثر 5 شماره صفحه
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $startPage + 4);
                            
                            if ($endPage - $startPage < 4) {
                                $startPage = max(1, $endPage - 4);
                            }
                            
                            for ($i = $startPage; $i <= $endPage; $i++):
                            ?>
                                <li <?php echo ($i == $page) ? 'class="active"' : ''; ?>>
                                    <a href="<?php echo buildUrl(['page' => $i]); ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <li><a href="<?php echo buildUrl(['page' => $page + 1]); ?>">بعدی</a></li>
                                <li><a href="<?php echo buildUrl(['page' => $totalPages]); ?>">»</a></li>
                            <?php endif; ?>
                        </ul>
                    <?php endif; ?>
                </div>
                
                <!-- انتخاب تعداد سطرهای جدول (سمت چپ) -->
                <div class="per-page-selector">
                    <form id="per-page-form" method="GET" action="/support_system/users">
                        <div class="input-group">
                            <label class="input-group-text" for="records_per_page">تعداد در صفحه:</label>
                            <select class="form-select" id="records_per_page" name="records_per_page" onchange="this.form.submit()">
                                <?php
                                $perPageOptions = [5, 10, 20, 50];
                                foreach ($perPageOptions as $option) {
                                    $selected = ($option == $limit) ? 'selected' : '';
                                    echo "<option value=\"$option\" $selected>$option</option>";
                                }
                                ?>
                            </select>
                            
                            <!-- حفظ پارامترهای جستجو در فرم تغییر تعداد آیتم در صفحه -->
                            <?php foreach ($_GET as $key => $value): ?>
                                <?php if ($key != 'records_per_page' && $key != 'page'): ?>
                                    <input type="hidden" name="<?php echo htmlspecialchars($key); ?>" value="<?php echo htmlspecialchars($value); ?>">
                                <?php endif; ?>
                            <?php endforeach; ?>
                            
                            <!-- بازنشانی شماره صفحه به 1 -->
                            <input type="hidden" name="page" value="1">
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- مودال ویرایش اطلاعات کاربر -->
    <div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm"> <!-- استفاده از کلاس modal-sm برای کوچک کردن مدال -->
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editUserModalLabel">ویرایش اطلاعات کاربر</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن"></button>
                </div>
                <div class="modal-body">
                    <form id="editUserForm" method="POST" action="/support_system/users/updateUserDetails">
                        <!-- شناسه کاربر (مخفی) -->
                        <input type="hidden" name="user_id" id="user_id">

                        <!-- نام کاربری -->
                        <div class="mb-3">
                            <label for="edit_username" class="form-label">نام کاربری:</label>
                            <input type="text" class="form-control" id="edit_username" name="username" readonly>
                        </div>

                        <!-- نام کامل -->
                        <div class="mb-3">
                            <label for="edit_fullname" class="form-label">نام کامل:</label>
                            <input type="text" class="form-control" id="edit_fullname" name="fullname">
                        </div>

                        <!-- نقش -->
                        <div class="mb-3">
                            <label for="edit_role" class="form-label">نقش:</label>
                            <select class="form-select" id="edit_role" name="role_id">
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?php echo $role['id']; ?>">
                                        <?php echo htmlspecialchars($role['role_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- نوع کاربر -->
                        <div class="mb-3">
                            <label for="edit_user_type" class="form-label">نوع کاربر:</label>
                            <select class="form-select" id="edit_user_type" name="user_type">
                                <option value="local">محلی</option>
                                <option value="network">شبکه</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button>
                    <button type="submit" form="editUserForm" class="btn btn-primary">ذخیره تغییرات</button>
                </div>
            </div>
        </div>
    </div>

    <!-- مودال افزودن کاربر جدید -->
    <div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addUserModalLabel">افزودن کاربر جدید</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن"></button>
                </div>
                <div class="modal-body">
                    <form id="addUserForm" method="POST" action="/support_system/users/add">
                        <div class="mb-3">
                            <label for="add_username" class="form-label">نام کاربری:</label>
                            <input type="text" class="form-control" id="add_username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="add_password" class="form-label">رمز عبور:</label>
                            <input type="password" class="form-control" id="add_password" name="password" required>
                        </div>
                        <div class="mb-3">
                            <label for="add_fullname" class="form-label">نام کامل:</label>
                            <input type="text" class="form-control" id="add_fullname" name="fullname" required>
                        </div>
                        <div class="mb-3">
                            <label for="add_role" class="form-label">نقش:</label>
                            <select class="form-select" id="add_role" name="role_id" required>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?php echo $role['id']; ?>">
                                        <?php echo htmlspecialchars($role['role_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <!-- چک‌باکس تغییر رمز ورود فقط برای کاربران محلی -->
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="force_password_change" name="force_password_change">
                            <label class="form-check-label" for="force_password_change">
                                تغییر رمز ورود در اولین ورود
                            </label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button>
                    <button type="submit" form="addUserForm" class="btn btn-primary">افزودن</button>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- اسکریپت برای مدیریت مودال‌ها -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // تنظیم مقادیر فرم ویرایش کاربر
    const editUserBtns = document.querySelectorAll('.edit-user-btn');
    editUserBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const userId = this.getAttribute('data-id');
            const username = this.getAttribute('data-username');
            const fullname = this.getAttribute('data-fullname');
            const role = this.getAttribute('data-role');
            const userType = this.getAttribute('data-user_type');
            
            document.getElementById('user_id').value = userId;
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_fullname').value = fullname;
            
            // تنظیم نقش کاربر
            const roleSelect = document.getElementById('edit_role');
            for (let i = 0; i < roleSelect.options.length; i++) {
                if (roleSelect.options[i].text === role) {
                    roleSelect.selectedIndex = i;
                    break;
                }
            }
            
            // تنظیم نوع کاربر
            const userTypeSelect = document.getElementById('edit_user_type');
            for (let i = 0; i < userTypeSelect.options.length; i++) {
                if (userTypeSelect.options[i].value === userType) {
                    userTypeSelect.selectedIndex = i;
                    break;
                }
            }
        });
    });
});
</script>

<!-- اضافه کردن فایل footer -->
<?php include __DIR__ . '/../footer.php'; ?>