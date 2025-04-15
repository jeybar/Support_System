<?php 
$cssLink = "role.css"; // لینک css
$pageTitle = "مدیریت نقش‌ها"; // مقداردهی به $pageTitle

require_once __DIR__ . '/../header.php'; 

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
    $baseUrl = '/support_system/roles';
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

// بررسی و مقداردهی پیش‌فرض برای متغیرهای ضروری
$limit = $limit ?? 10; // مقدار پیش‌فرض: 10 رکورد در هر صفحه
$totalPages = $totalPages ?? 1; // مقدار پیش‌فرض: 1 صفحه
$page = $page ?? 1; // مقدار پیش‌فرض: صفحه اول
$totalCount = $totalCount ?? count($roles); // مقدار پیش‌فرض: تعداد نقش‌های موجود
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
                <button class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#createRoleModal">
                    <i class="fas fa-plus-circle"></i> ایجاد نقش جدید
                </button>
                
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createTicketModal">
                    <i class="bi bi-plus-circle"></i> ثبت درخواست کار
                </button>
            </div>
        </div>
    </div>
</div>

<main class="container mt-4">
    
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

    <?php if (isset($_SESSION['info'])): ?>
        <div class="alert alert-info">
            <?php echo $_SESSION['info']; unset($_SESSION['info']); ?>
        </div>
    <?php endif; ?>

    <!-- فرم فیلتر و جستجو -->
    <div id="search-form-container" class="mb-4">    
        <div class="card-body">
            <form id="search-form" method="GET" action="/support_system/roles" class="mb-0">
                <div class="row g-3">
                    <!-- نام نقش -->
                    <div class="col-md-3 col-sm-6">
                        <label for="role_name" class="form-label">نام نقش:</label>
                        <input type="text" id="role_name" name="role_name" class="form-control" placeholder="نام نقش" 
                            value="<?php echo htmlspecialchars($_GET['role_name'] ?? ''); ?>">
                    </div>

                    <!-- توضیحات -->
                    <div class="col-md-3 col-sm-6">
                        <label for="description" class="form-label">توضیحات:</label>
                        <input type="text" id="description" name="description" class="form-control" placeholder="توضیحات" 
                            value="<?php echo htmlspecialchars($_GET['description'] ?? ''); ?>">
                    </div>

                    <!-- دسترسی‌ها -->
                    <div class="col-md-3 col-sm-6">
                        <label for="permission" class="form-label">دسترسی:</label>
                        <select id="permission" name="permission" class="form-select">
                            <option value="">همه دسترسی‌ها</option>
                            <?php if (isset($allPermissions) && is_array($allPermissions)): ?>
                                <?php foreach ($allPermissions as $permission): ?>
                                    <option value="<?php echo $permission['id']; ?>" <?php echo (isset($_GET['permission']) && $_GET['permission'] == $permission['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($permission['permission_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <!-- دکمه‌ها -->
                    <div class="col-md-3 col-sm-6">
                        <label class="form-label">&nbsp;</label> <!-- برچسب خالی برای هم‌ترازی -->
                        <div class="d-flex justify-content-start">
                            <button type="submit" class="btn btn-success ms-2">اعمال جستجو</button>
                            <a href="/support_system/roles" class="btn btn-warning">پاک کردن</a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- جدول نقش‌ها -->
    <div class="roles-table-container">
        <div class="table-responsive">
            <table class="table table-striped table-hover general-table text-center fixed-table">
                <colgroup>
                    <col class="w-5">  <!-- شناسه -->
                    <col class="w-15"> <!-- نام نقش -->
                    <col class="w-40"> <!-- دسترسی‌ها -->
                    <col class="w-20"> <!-- توضیحات -->
                    <col class="w-20"> <!-- عملیات -->
                </colgroup>
                <thead class="table-dark">
                    <tr>
                        <th><?php echo sortLink('id', 'شناسه'); ?></th>
                        <th><?php echo sortLink('role_name', 'نام نقش'); ?></th>
                        <th>دسترسی‌ها</th>
                        <th><?php echo sortLink('description', 'توضیحات'); ?></th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($roles)): ?>
                        <?php foreach ($roles as $role): ?>
                            <tr data-role-id="<?php echo $role['id']; ?>">
                                <td><?php echo $role['id']; ?></td>
                                <td><?php echo htmlspecialchars($role['role_name']); ?></td>
                                <td class="permissions-cell text-start" style="max-width: 300px;">
                                    <div class="permissions-list">
                                        <?php 
                                        if (!empty($role['permissions'])) {
                                            echo '<ul class="mb-0 ps-3 text-start">';
                                            foreach ($role['permissions'] as $permission) {
                                                echo '<li>' . htmlspecialchars($permission) . '</li>';
                                            }
                                            echo '</ul>';
                                        } else {
                                            echo '<span class="text-muted">بدون دسترسی</span>';
                                        }
                                        ?>
                                    </div>
                                </td>
                                <td class="text-truncate" style="max-width: 250px;"><?php echo htmlspecialchars($role['description'] ?? ''); ?></td>
                                <td>
                                    <!-- دکمه ویرایش -->
                                    <button 
                                        class="btn btn-primary btn-sm edit-role-btn" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#editRoleModal" 
                                        data-id="<?php echo $role['id']; ?>" 
                                        data-name="<?php echo htmlspecialchars($role['role_name']); ?>" 
                                        data-description="<?php echo htmlspecialchars($role['description'] ?? ''); ?>">
                                        <i class="fas fa-edit"></i> ویرایش
                                    </button>

                                    <!-- دکمه حذف -->
                                    <form action="/support_system/roles/delete" method="POST" style="display:inline;" onsubmit="return confirm('آیا از حذف این نقش اطمینان دارید؟');">
                                        <input type="hidden" name="role_id" value="<?php echo $role['id']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">
                                            <i class="fas fa-trash"></i> حذف
                                        </button>
                                    </form>

                                    <!-- دکمه تخصیص دسترسی -->
                                    <button 
                                        class="btn btn-warning btn-sm assign-permissions-btn" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#assignPermissionsModal" 
                                        data-role-id="<?php echo $role['id']; ?>" 
                                        data-role-name="<?php echo htmlspecialchars($role['role_name']); ?>">
                                        <i class="fas fa-key"></i> دسترسی‌ها
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted">هیچ نقشی یافت نشد.</td>
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
                <form id="per-page-form" method="GET" action="/support_system/roles">
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

    <!-- مودال ویرایش نقش -->
    <div class="modal fade" id="editRoleModal" tabindex="-1" aria-labelledby="editRoleModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editRoleModalLabel">ویرایش نقش</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن"></button>
                </div>
                <form method="POST" action="/support_system/roles/update">
                    <div class="modal-body">
                        <!-- فیلد مخفی برای شناسه نقش -->
                        <input type="hidden" name="id" id="edit-role-id">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                        <div class="mb-3">
                            <label for="edit-role-name" class="form-label">نام نقش:</label>
                            <input type="text" class="form-control" id="edit-role-name" name="role_name" required>
                        </div>

                        <div class="mb-3">
                            <label for="edit-role-description" class="form-label">توضیحات:</label>
                            <textarea class="form-control" id="edit-role-description" name="description"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button>
                        <button type="submit" class="btn btn-primary">ذخیره تغییرات</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- مودال تعریف نقش جدید -->
    <div class="modal fade" id="createRoleModal" tabindex="-1" aria-labelledby="createRoleModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="createRoleModalLabel">ایجاد نقش جدید</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن"></button>
                </div>
                <form method="POST" action="/support_system/roles/store">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="role_name" class="form-label">نام نقش:</label>
                            <input type="text" class="form-control" id="role_name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="role_description" class="form-label">توضیحات:</label>
                            <textarea class="form-control" id="role_description" name="description"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">لغو</button>
                        <button type="submit" class="btn btn-primary">ذخیره</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- مودال تخصیص دسترسی ها  -->
    <div class="modal fade" id="assignPermissionsModal" tabindex="-1" aria-labelledby="assignPermissionsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="assignPermissionsModalLabel">تخصیص دسترسی‌ها</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن"></button>
                </div>
                <form id="assignPermissionsForm" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="role_id" id="modal-role-id">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <div id="permissions-list">
                            <!-- لیست دسترسی‌ها به صورت داینامیک اینجا بارگذاری می‌شود -->
                            <div class="text-center">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">در حال بارگذاری...</span>
                                </div>
                                <p>در حال بارگذاری دسترسی‌ها...</p>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">لغو</button>
                        <button type="submit" class="btn btn-success">ذخیره</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>

<!-- اسکریپت‌های مربوط به مدیریت نقش‌ها -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // تنظیم مقادیر فرم ویرایش نقش
    const editRoleBtns = document.querySelectorAll('.edit-role-btn');
    editRoleBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const name = this.getAttribute('data-name');
            const description = this.getAttribute('data-description');
            
            document.getElementById('edit-role-id').value = id;
            document.getElementById('edit-role-name').value = name;
            document.getElementById('edit-role-description').value = description;
        });
    });

    // تنظیم مقادیر فرم تخصیص دسترسی‌ها
    const assignPermissionsBtns = document.querySelectorAll('.assign-permissions-btn');
    assignPermissionsBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const roleId = this.getAttribute('data-role-id');
            const roleName = this.getAttribute('data-role-name');
            
            document.getElementById('modal-role-id').value = roleId;
            document.getElementById('assignPermissionsModalLabel').textContent = `تخصیص دسترسی‌ها به نقش: ${roleName}`;
            
            // نمایش لودینگ
            const permissionsList = document.getElementById('permissions-list');
            permissionsList.innerHTML = `
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">در حال بارگذاری...</span>
                    </div>
                    <p>در حال بارگذاری دسترسی‌ها...</p>
                </div>
            `;
            
            // دریافت دسترسی‌های نقش از سرور
            fetch(`/support_system/roles/permissions/${roleId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.text().then(text => {
                        try {
                            console.log("Raw response:", text); // برای عیب‌یابی
                            return JSON.parse(text);
                        } catch (e) {
                            console.error("Invalid JSON response:", text);
                            throw new Error("پاسخ نامعتبر از سرور");
                        }
                    });
                })
                .then(data => {
                    console.log("Received data:", data); // برای عیب‌یابی
                    permissionsList.innerHTML = '';
                    
                    if (data.error) {
                        permissionsList.innerHTML = `<p class="text-center text-danger">خطا: ${data.error}</p>`;
                        return;
                    }
                    
                    if (data.permissions && data.permissions.length > 0) {
                        // اضافه کردن دکمه انتخاب/عدم انتخاب همه
                        const selectAllDiv = document.createElement('div');
                        selectAllDiv.className = 'mb-3';
                        
                        const selectAllCheck = document.createElement('div');
                        selectAllCheck.className = 'form-check';
                        
                        const selectAllInput = document.createElement('input');
                        selectAllInput.type = 'checkbox';
                        selectAllInput.className = 'form-check-input';
                        selectAllInput.id = 'select-all-permissions';
                        
                        const selectAllLabel = document.createElement('label');
                        selectAllLabel.className = 'form-check-label fw-bold';
                        selectAllLabel.htmlFor = 'select-all-permissions';
                        selectAllLabel.textContent = 'انتخاب/عدم انتخاب همه';
                        
                        selectAllCheck.appendChild(selectAllInput);
                        selectAllCheck.appendChild(selectAllLabel);
                        selectAllDiv.appendChild(selectAllCheck);
                        permissionsList.appendChild(selectAllDiv);
                        
                        // خط جداکننده
                        const hr = document.createElement('hr');
                        permissionsList.appendChild(hr);
                        
                        // نمایش دسترسی‌ها
                        const container = document.createElement('div');
                        container.className = 'row';
                        
                        data.permissions.forEach(permission => {
                            const col = document.createElement('div');
                            col.className = 'col-md-4 mb-2';
                            
                            const checkboxDiv = document.createElement('div');
                            checkboxDiv.className = 'form-check';
                            
                            const checkbox = document.createElement('input');
                            checkbox.type = 'checkbox';
                            checkbox.className = 'form-check-input';
                            checkbox.id = `permission_${permission.id}`;
                            checkbox.name = 'permissions[]';
                            checkbox.value = permission.id;
                            checkbox.checked = permission.assigned;
                            
                            const label = document.createElement('label');
                            label.className = 'form-check-label';
                            label.htmlFor = `permission_${permission.id}`;
                            label.textContent = permission.permission_name;
                            
                            checkboxDiv.appendChild(checkbox);
                            checkboxDiv.appendChild(label);
                            col.appendChild(checkboxDiv);
                            container.appendChild(col);
                        });
                        
                        permissionsList.appendChild(container);
                        
                        // بررسی وضعیت دکمه انتخاب همه
                        const allChecked = data.permissions.every(p => p.assigned);
                        document.getElementById('select-all-permissions').checked = allChecked;
                        
                        // اضافه کردن رویداد برای دکمه انتخاب همه
                        document.getElementById('select-all-permissions').addEventListener('change', function() {
                            const checkboxes = document.querySelectorAll('#permissions-list input[type="checkbox"]:not(#select-all-permissions)');
                            checkboxes.forEach(checkbox => {
                                checkbox.checked = this.checked;
                            });
                        });
                    } else {
                        permissionsList.innerHTML = '<p class="text-center text-muted">هیچ دسترسی تعریف نشده است.</p>';
                    }
                    
                    // تنظیم آدرس فرم
                    document.getElementById('assignPermissionsForm').action = `/support_system/roles/update_permissions/${roleId}`;                })
                .catch(error => {
                    console.error('Error fetching permissions:', error);
                    permissionsList.innerHTML = `<p class="text-center text-danger">خطا در دریافت دسترسی‌ها: ${error.message}</p>`;
                });
        });
    });

    // انتخاب/عدم انتخاب همه دسترسی‌ها
    document.addEventListener('click', function(e) {
        if (e.target && e.target.id === 'select-all-permissions') {
            const checkboxes = document.querySelectorAll('#permissions-list input[type="checkbox"]');
            checkboxes.forEach(checkbox => {
                checkbox.checked = e.target.checked;
            });
        }
    });
});
</script>

<?php include __DIR__ . '/../footer.php'; ?>