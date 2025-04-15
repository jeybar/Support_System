<?php
error_log("Request URI in tickets view: " . $_SERVER['REQUEST_URI']);
error_log("GET Params in tickets view: " . print_r($_GET, true));
?>
<?php

use Hekmatinasser\Verta\Verta; // افزودن فضای نام Verta

$cssLink = "tickets.css"; // لینک css
$pageTitle = "درخواست کار ها"; // مقداردهی به $pageTitle

include __DIR__ . '/../header.php'; 

// بررسی اینکه آیا $results مقداردهی شده است
if (!isset($tickets)) {
    $tickets = []; // مقدار پیش‌فرض
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
    $baseUrl = '/support_system/tickets';
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

<main class="container mt-4">
    
    <div id="search-form-container" class="mb-4">
        <form id="search-form" method="GET" action="/support_system/tickets">
            <div class="row g-3">
                <!-- عنوان درخواست کار -->
                <div class="col-md-3 col-sm-6">
                    <label for="query" class="form-label">عنوان درخواست کار:</label>
                    <input type="text" id="query" name="query" class="form-control" placeholder="عنوان درخواست کار" 
                        value="<?php echo htmlspecialchars($_GET['query'] ?? ''); ?>">
                </div>

                <!-- وضعیت -->
                <div class="col-md-3 col-sm-6">
                    <label for="status" class="form-label">وضعیت:</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">همه وضعیت‌ها</option>
                        <option value="open" <?php echo (isset($_GET['status']) && $_GET['status'] === 'open') ? 'selected' : ''; ?>>باز</option>
                        <option value="closed" <?php echo (isset($_GET['status']) && $_GET['status'] === 'closed') ? 'selected' : ''; ?>>بسته</option>
                        <option value="in_progress" <?php echo (isset($_GET['status']) && $_GET['status'] === 'in_progress') ? 'selected' : ''; ?>>در حال بررسی</option>
                    </select>
                </div>

                <!-- درخواست‌دهنده -->
                <div class="col-md-3 col-sm-6">
                    <label for="requester" class="form-label">درخواست‌دهنده:</label>
                    <input type="text" id="requester" name="requester" class="form-control" placeholder="درخواست‌دهنده"
                        value="<?php echo htmlspecialchars($_GET['requester'] ?? ''); ?>">
                </div>

                <!-- شماره پرسنلی -->
                <div class="col-md-3 col-sm-6">
                    <label for="employee_id" class="form-label">شماره پرسنلی:</label>
                    <input type="text" id="employee_id" name="employee_id" class="form-control" placeholder="شماره پرسنلی"
                        value="<?php echo htmlspecialchars($_GET['employee_id'] ?? ''); ?>">
                </div>

                <!-- پلنت -->
                <div class="col-md-3 col-sm-6">
                    <label for="plant" class="form-label">پلنت:</label>
                    <input type="text" id="plant" name="plant" class="form-control" placeholder="پلنت"
                        value="<?php echo htmlspecialchars($_GET['plant'] ?? ''); ?>">
                </div>

                <!-- واحد -->
                <div class="col-md-3 col-sm-6">
                    <label for="unit" class="form-label">واحد:</label>
                    <input type="text" id="unit" name="unit" class="form-control" placeholder="واحد"
                        value="<?php echo htmlspecialchars($_GET['unit'] ?? ''); ?>">
                </div>

                <!-- تاریخ ایجاد -->
                <div class="col-md-3 col-sm-6">
                    <label for="created_date" class="form-label">تاریخ ایجاد:</label>
                    <input type="date" id="created_date" name="created_date" class="form-control"
                        value="<?php echo htmlspecialchars($_GET['created_date'] ?? ''); ?>">
                </div>

                <!-- دکمه‌ها در ستون چهارم ردیف دوم -->
                <div class="col-md-3 col-sm-6">
                    <label class="form-label">&nbsp;</label> <!-- برچسب خالی برای هم‌ترازی -->
                    <div class="d-flex justify-content-start">
                        <button type="submit" class="btn btn-success ms-2">اعمال جستجو</button>
                        <a href="/support_system/tickets" class="btn btn-warning">پاک کردن</a>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <?php
    // بررسی اطلاعات درخواست
    $requestUri = $_SERVER['REQUEST_URI'];
    $getParams = $_GET;

    // ثبت اطلاعات در لاگ آپاچی
    error_log("Request URI: $requestUri");
    error_log("GET Params: " . print_r($getParams, true));
    ?>
    <!-- جدول نمایش درخواست‌ها -->
    <div class="tickets-table-container">
        <div class="table-responsive">
            <table class="table table-striped table-hover general-table text-center fixed-table">
            <colgroup>
            <col class="w-5">  <!-- شماره درخواست -->
            <col class="w-15"> <!-- عنوان -->
            <col class="w-8">  <!-- وضعیت -->
            <col class="w-8">  <!-- اولویت -->
            <col class="w-10"> <!-- تاریخ ایجاد -->
            <col class="w-10"> <!-- تاریخ سررسید -->
            <col class="w-12"> <!-- نام درخواست‌دهنده -->
            <col class="w-8">  <!-- شماره پرسنلی -->
            <col class="w-8">  <!-- پلنت -->
            <col class="w-8">  <!-- واحد -->
            <col class="w-8">  <!-- عملیات -->
        </colgroup>
                <thead class="table-dark">
                    <tr>
                        <th><?php echo sortLink('id', 'شماره درخواست'); ?></th>
                        <th><?php echo sortLink('title', 'عنوان'); ?></th>
                        <th><?php echo sortLink('status', 'وضعیت'); ?></th>
                        <th><?php echo sortLink('priority', 'اولویت'); ?></th>
                        <th><?php echo sortLink('created_at', 'تاریخ ایجاد'); ?></th>
                        <th><?php echo sortLink('due_date', 'تاریخ سررسید'); ?></th>
                        <th><?php echo sortLink('requester_name', 'نام درخواست‌دهنده'); ?></th>
                        <th><?php echo sortLink('requester_id', 'شماره پرسنلی'); ?></th>
                        <th><?php echo sortLink('requester_plant', 'پلنت'); ?></th>
                        <th><?php echo sortLink('requester_unit', 'واحد'); ?></th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($tickets)): ?>
                        <?php foreach ($tickets as $ticket): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($ticket['id']); ?></td>
                                <td><?php echo htmlspecialchars($ticket['title']); ?></td>
                                <td>
                                    <?php 
                                    // نمایش وضعیت به فارسی
                                    switch ($ticket['status']) {
                                        case 'open':
                                            echo 'باز';
                                            break;
                                        case 'closed':
                                            echo 'بسته';
                                            break;
                                        case 'in_progress':
                                            echo 'در حال بررسی';
                                            break;
                                        default:
                                            echo 'نامشخص';
                                            break;
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    // نمایش اولویت به فارسی
                                    switch ($ticket['priority']) {
                                        case 'normal':
                                            echo 'عادی';
                                            break;
                                        case 'urgent':
                                            echo 'فوری';
                                            break;
                                        case 'critical':
                                            echo 'بحرانی';
                                            break;
                                        default:
                                            echo 'نامشخص';
                                            break;
                                    }
                                    ?>
                                </td>
                                <td><?php echo (new Verta($ticket['created_at']))->format('Y/m/d H:i'); ?></td>
                                <td><?php echo (new Verta($ticket['due_date']))->format('Y/m/d H:i'); ?></td>
                                <td><?php echo htmlspecialchars($ticket['requester_name'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($ticket['requester_id'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($ticket['requester_plant'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($ticket['requester_unit'] ?? ''); ?></td>
                                <td>
                                    <a href="/support_system/tickets/view/<?php echo $ticket['id']; ?>" class="btn btn-primary btn-sm">مشاهده</a>
                                    <?php if ($_SESSION['role_id'] === 'admin' || $_SESSION['role_id'] === 'support'): ?>
                                        <a href="/support_system/tickets/edit/<?php echo $ticket['id']; ?>" class="btn btn-warning btn-sm">ویرایش</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" class="text-center">هیچ نتیجه‌ای یافت نشد.</td>
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
            نمایش <?php echo ($totalCount > 0) ? (($page - 1) * $perPage + 1) : 0; ?> تا <?php echo min($page * $perPage, $totalCount); ?> از <?php echo $totalCount; ?> مورد
        </div>

        <!-- دکمه‌های صفحه‌بندی (وسط) -->
        <div class="pagination">
            <?php if ($totalPages > 1): ?>
                <ul>
                    <?php if ($page > 1): ?>
                        <li><a href="<?php echo paginationLink(1); ?>">«</a></li>
                        <li><a href="<?php echo paginationLink($page - 1); ?>">قبلی</a></li>
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
                        <li><a href="<?php echo paginationLink($page + 1); ?>">بعدی</a></li>
                        <li><a href="<?php echo paginationLink($totalPages); ?>">»</a></li>
                    <?php endif; ?>
                </ul>
            <?php endif; ?>
        </div>
        
        <!-- انتخاب تعداد سطرهای جدول (سمت چپ) -->
        <div class="per-page-selector">
            <form id="per-page-form" method="GET" action="/support_system/tickets">
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

<!-- اسکریپت پاک کردن فرم -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // دکمه پاک کردن فرم
        document.getElementById('clear-form').addEventListener('click', function() {
            // پاک کردن همه فیلدهای فرم
            document.getElementById('query').value = '';
            document.getElementById('status').selectedIndex = 0;
            document.getElementById('requester').value = '';
            document.getElementById('employee_id').value = '';
            document.getElementById('plant').value = '';
            document.getElementById('unit').value = '';
            document.getElementById('created_date').value = '';
            
            // ارسال فرم با مقادیر پاک شده
            document.getElementById('search-form').submit();
        });
    });
</script>

<?php include __DIR__ . '/../footer.php'; ?>