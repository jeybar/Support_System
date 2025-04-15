<?php
error_log("Categories count: " . (is_array($categories) ? count($categories) : 'Not an array'));
error_log("Models count: " . (is_array($models) ? count($models) : 'Not an array'));
error_log("Categories data: " . print_r($categories ?? [], true));
error_log("Models data: " . print_r($models ?? [], true));

// دیباگ: بررسی ساختار داده‌های دریافتی
if (!empty($assets)) {
    error_log("First asset data structure: " . print_r($assets[0], true));
    
    // بررسی کلیدهای موجود در آرایه اولین تجهیز
    $keys = array_keys($assets[0]);
    error_log("Asset keys: " . implode(', ', $keys));
    
    // بررسی مقادیر user_id و status
    error_log("First asset user_id: " . ($assets[0]['user_id'] ?? 'not set'));
    error_log("First asset status: " . ($assets[0]['status'] ?? 'not set'));
    
    // بررسی اطلاعات کاربر مرتبط
    if (!empty($assets[0]['user_id'])) {
        error_log("User info for first asset: username=" . 
            ($assets[0]['username'] ?? 'not set') . ", fullname=" . 
            ($assets[0]['fullname'] ?? 'not set') . ", employee_number=" . 
            ($assets[0]['employee_number'] ?? 'not set'));
    }
}
?>

<?php
/**
 * تابع کمکی برای ترجمه وضعیت تجهیز به فارسی
 * 
 * @param string $status وضعیت تجهیز به انگلیسی
 * @return string وضعیت تجهیز به فارسی
 */
function translateStatus($status) {
    $translations = [
        'available' => 'در دسترس',
        'assigned' => 'تخصیص داده شده',
        'maintenance' => 'در تعمیرات',
        'retired' => 'بازنشسته',
        'lost' => 'گم شده',
        'broken' => 'خراب',
        '' => 'نامشخص'
    ];
    
    return $translations[$status] ?? 'نامشخص';
}
?>

<?php
use Hekmatinasser\Verta\Verta; // افزودن فضای نام Verta

$cssLink = "assets.css"; // لینک css
$pageTitle = "مدیریت تجهیزات سخت‌افزاری"; // مقداردهی به $pageTitle

include __DIR__ . '/../header.php'; 

// بررسی اینکه آیا $assets مقداردهی شده است
if (!isset($assets)) {
    $assets = []; // مقدار پیش‌فرض
}

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
    $baseUrl = '/support_system/assets';
    return $baseUrl . ($queryString ? '?' . $queryString : '');
}

// تابع کمکی برای ساخت لینک‌های مرتب‌سازی با حفظ پارامترهای جستجو
function sortLink($column, $label) {
    $currentSortBy = $_GET['sort_by'] ?? 'created_at';
    $currentOrder = $_GET['order'] ?? 'desc';
    
    $newOrder = ($currentSortBy === $column && $currentOrder === 'asc') ? 'desc' : 'asc';
    $icon = '';
    
    if ($currentSortBy === $column) {
        $icon = ($currentOrder === 'asc') 
            ? '<i class="fas fa-sort-up ms-1"></i>' 
            : '<i class="fas fa-sort-down ms-1"></i>';
    } else {
        $icon = '<i class="fas fa-sort ms-1 opacity-50"></i>';
    }
    
    // استفاده از تابع buildUrl برای حفظ همه پارامترهای جستجو
    $url = buildUrl(['sort_by' => $column, 'order' => $newOrder]);
    
    return '<a href="' . $url . '" class="text-white text-decoration-none">' . $label . ' ' . $icon . '</a>';
}

// تابع کمکی برای ساخت لینک‌های صفحه‌بندی با حفظ پارامترهای جستجو
function paginationLink($pageNum, $label = null) {
    $label = $label ?? $pageNum;
    
    // استفاده از تابع buildUrl برای حفظ همه پارامترهای جستجو
    return buildUrl(['page' => $pageNum]);
}

// مقداردهی متغیرهای مورد نیاز با مقادیر پیش‌فرض
$page = $currentPage ?? 1;
$perPage = $_GET['per_page'] ?? 10;
$totalCount = $totalCount ?? 0;
$totalPages = $totalPages ?? 1;
?>

<!-- SECTION: Header and Breadcrumbs -->
<section class="header-section container mt-4">
    <div class="row align-items-center mb-3">
        <!-- Breadcrumbs -->
        <div class="col-lg-8 col-md-6 col-sm-12">
            <?php echo generateBreadcrumbs(); ?>
        </div>

        <!-- دکمه‌ها -->
        <div class="col-lg-4 col-md-6 col-sm-12">
            <div class="d-flex justify-content-end flex-wrap align-items-center">
                <?php if ($accessControl->hasPermission('create_assets')): ?>
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createAssetModal">
                    <i class="bi bi-plus-circle"></i> افزودن تجهیز جدید
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<main class="container mt-4">
    <!-- فرم جستجو -->
    <div id="search-form-container" class="mb-4">
        <form id="search-form" method="GET" action="/support_system/assets">
            <div class="row g-3">
                <!-- عنوان تجهیز -->
                <div class="col-md-3 col-sm-6">
                    <label for="query" class="form-label">نوع تجهیز:</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-laptop"></i></span>
                        <input type="text" id="query" name="query" class="form-control" placeholder="عنوان تجهیز" 
                            value="<?php echo htmlspecialchars($_GET['query'] ?? ''); ?>">
                    </div>
                </div>

                <!-- مدل -->
                <div class="col-md-3 col-sm-6">
                    <label for="model" class="form-label">مدل:</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-laptop-code"></i></span>
                        <select id="model" name="model" class="form-select">
                            <option value="">همه مدل‌ها</option>
                            <?php foreach ($models as $model): ?>
                                <option value="<?php echo $model['id']; ?>" 
                                    <?php echo (isset($_GET['model']) && $_GET['model'] == $model['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($model['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- دسته‌بندی -->
                <div class="col-md-3 col-sm-6">
                    <label for="category" class="form-label">دسته‌بندی:</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-folder"></i></span>
                        <select id="category" name="category" class="form-select">
                            <option value="">همه دسته‌بندی‌ها</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>" 
                                    <?php echo (isset($_GET['category']) && $_GET['category'] == $category['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- شماره پرسنلی -->
                <div class="col-md-3 col-sm-6">
                    <label for="search_employee_number" class="form-label">شماره پرسنلی:</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                        <input type="text" id="search_employee_number" name="employee_number" class="form-control" placeholder="شماره پرسنلی"
                            value="<?php echo htmlspecialchars($_GET['employee_number'] ?? ''); ?>">
                    </div>
                </div>

                <!-- برچسب تجهیز -->
                <div class="col-md-3 col-sm-6">
                    <label for="search_asset_tag" class="form-label">اموال تجهیز:</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-tag"></i></span>
                        <input type="text" id="search_asset_tag" name="asset_tag" class="form-control" placeholder="برچسب تجهیز"
                            value="<?php echo htmlspecialchars($_GET['asset_tag'] ?? ''); ?>">
                    </div>
                </div>

                <!-- شماره سریال -->
                <div class="col-md-3 col-sm-6">
                    <label for="search_serial_number" class="form-label">شماره سریال:</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-barcode"></i></span>
                        <input type="text" id="search_serial_number" name="serial_number" class="form-control" placeholder="شماره سریال"
                            value="<?php echo htmlspecialchars($_GET['serial_number'] ?? ''); ?>">
                    </div>
                </div>

                <!-- محل استقرار -->
                <div class="col-md-3 col-sm-6">
                    <label for="search_location" class="form-label">محل استقرار:</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-map-marker-alt"></i></span>
                        <input type="text" id="search_location" name="location" class="form-control" placeholder="محل استقرار"
                            value="<?php echo htmlspecialchars($_GET['location'] ?? ''); ?>">
                    </div>
                </div>

                <!-- وضعیت -->
                <div class="col-md-3 col-sm-6">
                    <label for="search_status" class="form-label">وضعیت:</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-info-circle"></i></span>
                        <select id="search_status" name="status" class="form-select">
                            <option value="">همه وضعیت‌ها</option>
                            <option value="ready" <?php echo (isset($_GET['status']) && $_GET['status'] === 'ready') ? 'selected' : ''; ?>>آماده استفاده</option>
                            <option value="in_use" <?php echo (isset($_GET['status']) && $_GET['status'] === 'in_use') ? 'selected' : ''; ?>>در حال استفاده</option>
                            <option value="needs_repair" <?php echo (isset($_GET['status']) && $_GET['status'] === 'needs_repair') ? 'selected' : ''; ?>>نیاز به تعمیر</option>
                            <option value="out_of_service" <?php echo (isset($_GET['status']) && $_GET['status'] === 'out_of_service') ? 'selected' : ''; ?>>خارج از سرویس</option>
                        </select>
                    </div>
                </div>

                <!-- دکمه‌ها -->
                <div class="col-md-3 col-sm-6">
                    <label class="form-label">&nbsp;</label> <!-- برچسب خالی برای هم‌ترازی -->
                    <div class="d-flex justify-content-start">
                        <button type="submit" class="btn btn-success ms-2">
                            <i class="fas fa-search me-1"></i> اعمال جستجو
                        </button>
                        <a href="/support_system/assets" class="btn btn-warning" id="clear-form">
                            <i class="fas fa-eraser me-1"></i> پاک کردن
                        </a>
                    </div>
                </div>
            </div>
        </form>  
    </div>

    <!-- SECTION: Alerts -->
    <section class="alerts-section">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i> <?= $_SESSION['success']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i> <?= $_SESSION['error']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
    </section>

    <!-- SECTION: Assets Table -->
    <section class="assets-table-section mb-4">
        <div class="card shadow">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover general-table text-center fixed-table mb-0">
                        <colgroup>
                            <col class="w-4">  <!-- شناسه -->
                            <col class="w-10"> <!-- عنوان تجهیز -->
                            <col class="w-8"> <!-- مدل -->
                            <col class="w-6"> <!-- دسته‌بندی -->
                            <col class="w-8"> <!-- تخصیص یافته به -->
                            <col class="w-7"> <!-- برچسب تجهیز -->
                            <col class="w-7"> <!-- شماره سریال -->
                            <col class="w-7"> <!-- محل استقرار -->
                            <col class="w-7"> <!-- وضعیت -->
                            <col class="w-10"> <!-- عملیات -->
                        </colgroup>
                        <thead class="table-dark">
                            <tr>
                                <th><?php echo sortLink('id', 'شناسه'); ?></th>
                                <th><?php echo sortLink('name', 'عنوان تجهیز'); ?></th>
                                <th><?php echo sortLink('model_name', 'مدل'); ?></th>
                                <th><?php echo sortLink('category_name', 'دسته‌بندی'); ?></th>
                                <th><?php echo sortLink('assigned_to', 'تخصیص یافته به'); ?></th>
                                <th><?php echo sortLink('asset_tag', 'برچسب تجهیز'); ?></th>
                                <th><?php echo sortLink('serial_number', 'شماره سریال'); ?></th>
                                <th><?php echo sortLink('location', 'محل استقرار'); ?></th>
                                <th><?php echo sortLink('status', 'وضعیت'); ?></th>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($assets)): ?>
                                <?php foreach ($assets as $asset): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($asset['id']); ?></td>
                                        <td><?php echo htmlspecialchars($asset['name'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($asset['model_name'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($asset['category_name'] ?? ''); ?></td>
                                        
                                        <!-- تخصیص یافته به -->
                                        <td>
                                            <?php if (!empty($asset['user_id'])): ?>
                                                <div>
                                                    <i class="fas fa-user me-1"></i>
                                                    <?php 
                                                    // نمایش نام کاربر اگر موجود باشد
                                                    if (!empty($asset['fullname'])) {
                                                        echo htmlspecialchars($asset['fullname']);
                                                    } elseif (!empty($asset['username'])) {
                                                        echo htmlspecialchars($asset['username']);
                                                    } else {
                                                        echo 'کاربر ' . $asset['user_id'];
                                                    }
                                                    ?>
                                                </div>
                                                <?php if (!empty($asset['username']) || !empty($asset['employee_number'])): ?>
                                                    <div class="small text-muted">
                                                        <i class="fas fa-id-card me-1"></i>
                                                        <?php echo htmlspecialchars(!empty($asset['employee_number']) ? $asset['employee_number'] : $asset['username']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted">
                                                    <i class="fas fa-user-slash me-1"></i>
                                                    تخصیص نیافته
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        
                                        <td><?php echo htmlspecialchars($asset['asset_tag'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($asset['serial_number'] ?? ''); ?></td>
                                        
                                        <!-- محل استقرار -->
                                        <td>
                                            <?php if (!empty($asset['location'])): ?>
                                                <i class="fas fa-map-marker-alt me-1"></i>
                                                <?php echo htmlspecialchars($asset['location']); ?>
                                            <?php else: ?>
                                                <span class="text-muted">
                                                    <i class="fas fa-question-circle me-1"></i>
                                                    نامشخص
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        
                                        <!-- وضعیت -->
<td>
    <?php 
    $status = trim($asset['status'] ?? ''); // حذف فاصله‌های اضافی
    $statusData = $statusInfo[$status] ?? $statusInfo['']; // استفاده از حالت پیش‌فرض اگر وضعیت نامعتبر بود
    ?>
    <span class="<?= htmlspecialchars($statusData['class']) ?>">
        <i class="<?= htmlspecialchars($statusData['icon']) ?>"></i>
        <?= htmlspecialchars($statusData['text']) ?>
    </span>
</td>
                                        
                                        <!-- عملیات -->
                                        <td>
                                            <div class="btn-group" role="group">
                                                <!-- دکمه مشاهده -->
                                                <a href="/support_system/assets/view/<?php echo $asset['id']; ?>" 
                                                class="btn btn-sm btn-info" data-bs-toggle="tooltip" title="مشاهده جزئیات">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                
                                                <?php if ($accessControl->hasPermission('edit_assets')): ?>
                                                <!-- دکمه ویرایش -->
                                                <a href="/support_system/assets/edit/<?php echo $asset['id']; ?>" 
                                                class="btn btn-sm btn-warning" data-bs-toggle="tooltip" title="ویرایش تجهیز">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <?php endif; ?>
                                                
                                                <?php if ($accessControl->hasPermission('assign_assets')): ?>
                                                <!-- دکمه تخصیص -->
                                                <a href="/support_system/assets/assign/<?php echo $asset['id']; ?>" 
                                                class="btn btn-sm btn-success" data-bs-toggle="tooltip" title="تخصیص به کاربر">
                                                    <i class="fas fa-user-plus"></i>
                                                </a>
                                                <?php endif; ?>
                                                
                                                <?php if ($accessControl->hasPermission('generate_qrcode')): ?>
                                                <!-- دکمه تولید QR کد -->
                                                <a href="/support_system/assets/qrcode/<?php echo $asset['id']; ?>" 
                                                class="btn btn-sm btn-primary" data-bs-toggle="tooltip" title="تولید QR کد">
                                                    <i class="fas fa-qrcode"></i>
                                                </a>
                                                <?php endif; ?>
                                                
                                                <?php if ($accessControl->hasPermission('delete_assets')): ?>
                                                <!-- دکمه حذف -->
                                                <button type="button" class="btn btn-sm btn-danger delete-btn" 
                                                        data-id="<?php echo $asset['id']; ?>" data-bs-toggle="tooltip" title="حذف تجهیز">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="10" class="text-center py-4">
                                        <div class="alert alert-info mb-0">
                                            <i class="fas fa-info-circle me-2"></i>
                                            هیچ تجهیز سخت‌افزاری یافت نشد.
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>

    <!-- SECTION: Pagination -->
    <section class="pagination-section">
        <div class="pagination-container">
            <!-- دکمه‌های صفحه‌بندی و اطلاعات تعداد نمایش در یک ردیف -->
            <div class="pagination-row">
                <!-- اطلاعات تعداد نمایش (سمت راست) -->
                <div class="pagination-info">
                    نمایش <?php echo ($totalCount > 0) ? (($page - 1) * $perPage + 1) : 0; ?> تا <?php echo min($page * $perPage, $totalCount); ?> از <?php echo $totalCount; ?> مورد
                </div>

                <!-- دکمه‌های صفحه‌بندی (وسط) -->
                <div class="pagination">
                    <?php if ($totalPages > 1): ?>
                        <ul>
                            <?php if ($page > 1): ?>
                                <li><a href="<?php echo paginationLink(1); ?>" aria-label="صفحه اول"><i class="fas fa-angle-double-right"></i></a></li>
                                <li><a href="<?php echo paginationLink($page - 1); ?>" aria-label="صفحه قبلی"><i class="fas fa-angle-right"></i> قبلی</a></li>
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
                                    <a href="<?php echo paginationLink($i); ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <li><a href="<?php echo paginationLink($page + 1); ?>" aria-label="صفحه بعدی">بعدی <i class="fas fa-angle-left"></i></a></li>
                                <li><a href="<?php echo paginationLink($totalPages); ?>" aria-label="صفحه آخر"><i class="fas fa-angle-double-left"></i></a></li>
                            <?php endif; ?>
                        </ul>
                    <?php endif; ?>
                </div>
                
                <!-- انتخاب تعداد سطرهای جدول (سمت چپ) -->
                <div class="per-page-selector">
                    <form id="per-page-form" method="GET" action="/support_system/assets">
                        <div class="input-group">
                            <label class="input-group-text" for="per-page">تعداد در صفحه:</label>
                            <select class="form-select" id="per-page" name="per_page" onchange="this.form.submit()">
                                <?php
                                $perPageOptions = [10, 25, 50, 100];
                                foreach ($perPageOptions as $option) {
                                    $selected = ($option == $perPage) ? 'selected' : '';
                                    echo "<option value=\"$option\" $selected>$option</option>";
                                }
                                ?>
                            </select>
                            
                            <!-- حفظ پارامترهای جستجو در فرم تغییر تعداد آیتم در صفحه -->
                            <?php foreach ($_GET as $key => $value): ?>
                                <?php if ($key != 'per_page' && $key != 'page'): ?>
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
    </section>

<!-- SECTION: Modals -->
<section class="modals-section">
    <!-- مودال تأیید حذف -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteModalLabel">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        تأیید حذف تجهیز
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>آیا از حذف این تجهیز اطمینان دارید؟</p>
                    <p class="text-danger"><strong>هشدار:</strong> این عمل غیرقابل بازگشت است.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i> انصراف
                    </button>
                    <form id="deleteForm" method="POST" action="">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash me-1"></i> حذف
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- مودال افزودن تجهیز جدید -->
    <div class="modal fade" id="createAssetModal" tabindex="-1" aria-labelledby="createAssetModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="createAssetModalLabel">
                        <i class="fas fa-plus-circle me-2"></i>
                        افزودن تجهیز جدید
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- اضافه کردن این بخش برای نمایش خطاها -->
                    <div id="modal-error-container" class="mb-3" style="display: none;">
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <span id="modal-error-message"></span>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    </div>
                    
                    <form id="create-asset-form" method="POST" action="/support_system/assets/store" enctype="multipart/form-data">
                        <div class="row g-3">
                            <!-- اطلاعات اصلی -->
                            <div class="col-12 mb-2">
                                <h6 class="border-bottom pb-2">
                                    <i class="fas fa-info-circle me-2"></i>اطلاعات اصلی
                                </h6>
                            </div>

                            <!-- عنوان تجهیز (به صورت لیست کشویی) -->
                            <div class="col-md-6">
                                <label for="asset_type" class="form-label">نوع تجهیز <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-laptop"></i></span>
                                    <select id="asset_type" name="name" class="form-select" required>
                                        <option value="">انتخاب نوع تجهیز...</option>
                                        <option value="کامپیوتر اداری">کامپیوتر اداری</option>
                                        <option value="پروژکتور">کامپیوتر دستگاه</option>
                                        <option value="لپ تاپ">لپ تاپ</option>
                                        <option value="پرینتر">پرینتر</option>
                                        <option value="اسکنر">اسکنر</option>
                                        <option value="مانیتور">مانیتور</option>
                                        <option value="تلفن">تلفن</option>
                                        <option value="موبایل">موبایل</option>
                                        <option value="تبلت">تبلت</option>
                                        <option value="سرور">سرور</option>
                                        <option value="سوییچ شبکه">سوییچ شبکه</option>
                                        <option value="روتر">روتر</option>
                                        <option value="مودم">مودم</option>
                                        <option value="دوربین">دوربین</option>
                                        <option value="پروژکتور">پروژکتور</option>
                                        <option value="سایر">سایر</option>
                                    </select>
                                </div>
                            </div>

                            <!-- نام کامپیوتر (ابتدا مخفی) -->
                            <div class="col-md-6" id="computer_name_container" style="display: none;">
                                <label for="computer_name" class="form-label">نام کامپیوتر <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-desktop"></i></span>
                                    <input type="text" id="computer_name" name="computer_name" class="form-control" placeholder="مثال: PC-ACCOUNTING-01">
                                </div>
                                <div class="form-text">نام کامپیوتر در شبکه را وارد کنید</div>
                            </div>

                            <!-- دسته‌بندی -->
                            <div class="col-md-6">
                                <label for="category_id" class="form-label">دسته‌بندی <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-folder"></i></span>
                                    <select id="category_id" name="category_id" class="form-select" required>
                                        <option value="">انتخاب دسته‌بندی...</option>
                                        <?php if (isset($categories) && is_array($categories)): ?>
                                            <?php foreach ($categories as $category): ?>
                                                <option value="<?php echo $category['id']; ?>">
                                                    <?php echo htmlspecialchars($category['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <option value="" disabled>دسته‌بندی‌ها در دسترس نیستند</option>
                                        <?php endif; ?>
                                    </select>
                                    <button type="button" class="btn btn-outline-secondary" id="add-category-btn" title="افزودن دسته‌بندی جدید">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- مدل -->
                            <div class="col-md-6">
                                <label for="model_id" class="form-label">مدل <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-laptop-code"></i></span>
                                    <select id="model_id" name="model_id" class="form-select" required>
                                        <option value="">ابتدا دسته‌بندی را انتخاب کنید</option>
                                    </select>
                                    <button type="button" class="btn btn-outline-secondary" id="add-model-btn" title="افزودن مدل جدید">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- وضعیت -->
                            <div class="col-md-6">
                                <label for="status_add" class="form-label">وضعیت <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-info-circle"></i></span>
                                    <select id="status_add" name="status" class="form-select" required>
                                        <option value="ready">آماده استفاده</option>
                                        <option value="in_use">در حال استفاده</option>
                                        <option value="needs_repair">نیاز به تعمیر</option>
                                        <option value="out_of_service">خارج از سرویس</option>
                                    </select>
                                </div>
                            </div>

                            <!-- برچسب اموال تجهیز -->
                            <div class="col-md-6">
                                <label for="asset_tag_add" class="form-label">اموال تجهیز <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-tag"></i></span>
                                    <input type="text" id="asset_tag_add" name="asset_tag" class="form-control" required>
                                </div>
                            </div>

                            <!-- شماره سریال -->
                            <div class="col-md-6">
                                <label for="serial_number_add" class="form-label">شماره سریال</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-barcode"></i></span>
                                    <input type="text" id="serial_number_add" name="serial_number" class="form-control">
                                </div>
                            </div>

                            <!-- اطلاعات تخصیص -->
                            <div class="col-12 mt-3 mb-2">
                                <h6 class="border-bottom pb-2">
                                    <i class="fas fa-map-marker-alt me-2"></i>اطلاعات تخصیص
                                </h6>
                            </div>

                            <!-- تخصیص به کاربر -->
                            <div class="col-md-6">
                                <label for="employee_number_add" class="form-label">شماره پرسنلی</label>
                                <div class="input-group mb-2">
                                    <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                    <input type="text" id="employee_number_add" name="employee_number" class="form-control" placeholder="شماره پرسنلی">
                                    <button type="button" id="search_employee" class="btn btn-primary">جستجو</button>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label for="employee_name_add" class="form-label">نام کاربر</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" id="employee_name_add" class="form-control" readonly>
                                    <input type="hidden" id="assigned_to_add" name="assigned_to">
                                    <!-- اضافه کردن فیلد مخفی برای ذخیره شناسه کاربر -->
                                    <input type="hidden" id="user_id_add" name="user_id">
                                </div>
                            </div>
                                                            
                            <!-- فیلد محل استقرار -->
                            <div class="col-md-6 mb-3">
                                <label for="location_add" class="form-label">محل استقرار</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-building"></i></span>
                                    <input type="text" class="form-control" id="location_add" name="location">
                                </div>
                            </div>

                            <!-- اطلاعات خرید -->
                            <div class="col-12 mt-3 mb-2">
                                <h6 class="border-bottom pb-2">
                                    <i class="fas fa-shopping-cart me-2"></i>اطلاعات خرید
                                </h6>
                            </div>

                            <!-- تاریخ خرید -->
                            <div class="col-md-6">
                                <label for="purchase_date" class="form-label">تاریخ خرید</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                                    <input type="date" id="purchase_date" name="purchase_date" class="form-control">
                                </div>
                            </div>

                            <!-- توضیحات -->
                            <div class="col-12 mt-3">
                                <label for="notes" class="form-label">توضیحات</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-sticky-note"></i></span>
                                    <textarea id="notes" name="notes" class="form-control" rows="3"></textarea>
                                </div>
                            </div>

                            <!-- تصویر تجهیز -->
                            <div class="col-12 mt-3">
                                <label for="asset_image" class="form-label">تصویر تجهیز</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-image"></i></span>
                                    <input type="file" id="asset_image" name="asset_image" class="form-control" accept="image/*">
                                </div>
                                <div class="form-text">فرمت‌های مجاز: JPG، PNG، GIF (حداکثر 2MB)</div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i> انصراف
                    </button>
                    <button type="button" id="submit-asset-form" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> ذخیره
                    </button>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- انتقال داده‌های مدل از PHP به JavaScript -->
<script>
    // ساخت آرایه مدل‌ها بر اساس دسته‌بندی
    window.modelsByCategoryData = <?php 
        // ساخت آرایه مدل‌ها بر اساس دسته‌بندی
        $modelsByCategoryArray = [];
        foreach ($models as $model) {
            $categoryId = $model['category_id'];
            if (!isset($modelsByCategoryArray[$categoryId])) {
                $modelsByCategoryArray[$categoryId] = [];
            }
            $modelsByCategoryArray[$categoryId][] = [
                'id' => $model['id'],
                'name' => $model['name']
            ];
        }
        echo json_encode($modelsByCategoryArray);
    ?>;
    console.log('Models by category data loaded:', window.modelsByCategoryData);
</script>

<script src="../assets/js/assets.js"></script>

<?php include __DIR__ . '/../footer.php'; ?>