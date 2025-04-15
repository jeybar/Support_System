<?php include_once '../app/views/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <?php include_once '../app/views/components/page_header.php'; ?>
        
        <div class="col-12 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">فیلتر سوابق نگهداری</h5>
                </div>
                <div class="card-body">
                    <form id="filter-form" method="GET" action="/maintenance/logs">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label for="asset_category">دسته‌بندی تجهیز</label>
                                <select class="form-control" id="asset_category" name="asset_category">
                                    <option value="">همه</option>
                                    <?php foreach ($assetCategories as $category): ?>
                                    <option value="<?= $category['id'] ?>" <?= isset($_GET['asset_category']) && $_GET['asset_category'] == $category['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($category['name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="maintenance_type">نوع نگهداری</label>
                                <select class="form-control" id="maintenance_type" name="maintenance_type">
                                    <option value="">همه</option>
                                    <?php foreach ($maintenanceTypes as $type): ?>
                                    <option value="<?= $type['id'] ?>" <?= isset($_GET['maintenance_type']) && $_GET['maintenance_type'] == $type['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($type['name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="status">وضعیت</label>
                                <select class="form-control" id="status" name="status">
                                    <option value="">همه</option>
                                    <option value="completed" <?= isset($_GET['status']) && $_GET['status'] == 'completed' ? 'selected' : '' ?>>تکمیل شده</option>
                                    <option value="incomplete" <?= isset($_GET['status']) && $_GET['status'] == 'incomplete' ? 'selected' : '' ?>>ناقص</option>
                                    <option value="failed" <?= isset($_GET['status']) && $_GET['status'] == 'failed' ? 'selected' : '' ?>>ناموفق</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary">اعمال فیلتر</button>
                                <a href="/maintenance/logs" class="btn btn-secondary ms-2">پاک کردن فیلتر</a>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label for="date_from">از تاریخ</label>
                                <input type="text" class="form-control datepicker" id="date_from" name="date_from" value="<?= isset($_GET['date_from']) ? $_GET['date_from'] : '' ?>">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="date_to">تا تاریخ</label>
                                <input type="text" class="form-control datepicker" id="date_to" name="date_to" value="<?= isset($_GET['date_to']) ? $_GET['date_to'] : '' ?>">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="technician">تکنسین</label>
                                <select class="form-control" id="technician" name="technician">
                                    <option value="">همه</option>
                                    <?php foreach ($technicians as $technician): ?>
                                    <option value="<?= $technician['id'] ?>" <?= isset($_GET['technician']) && $_GET['technician'] == $technician['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($technician['name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">سوابق نگهداری</h5>
                    <a href="/maintenance/logs/create" class="btn btn-primary">ثبت سابقه نگهداری جدید</a>
                </div>
                <div class="card-body">
                    <?php if (isset($_SESSION['message'])): ?>
                        <div class="alert alert-<?= $_SESSION['message_type'] ?> alert-dismissible fade show" role="alert">
                            <?= $_SESSION['message'] ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
                    <?php endif; ?>
                    
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>شناسه</th>
                                    <th>تجهیز</th>
                                    <th>نوع نگهداری</th>
                                    <th>تاریخ انجام</th>
                                    <th>تکنسین</th>
                                    <th>وضعیت</th>
                                    <th>عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($maintenanceLogs)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center">هیچ سابقه نگهداری یافت نشد</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($maintenanceLogs as $log): ?>
                                    <tr>
                                        <td><?= $log['id'] ?></td>
                                        <td>
                                            <a href="/assets/view/<?= $log['asset_id'] ?>">
                                                <?= htmlspecialchars($log['asset_name']) ?>
                                            </a>
                                        </td>
                                        <td><?= htmlspecialchars($log['maintenance_type_name']) ?></td>
                                        <td><?= format_date($log['maintenance_date']) ?></td>
                                        <td><?= htmlspecialchars($log['technician_name']) ?></td>
                                        <td>
                                            <?php if ($log['status'] == 'completed'): ?>
                                                <span class="badge bg-success">تکمیل شده</span>
                                            <?php elseif ($log['status'] == 'incomplete'): ?>
                                                <span class="badge bg-warning">ناقص</span>
                                            <?php elseif ($log['status'] == 'failed'): ?>
                                                <span class="badge bg-danger">ناموفق</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="/maintenance/logs/view/<?= $log['id'] ?>" class="btn btn-sm btn-info">مشاهده</a>
                                            <a href="/maintenance/logs/edit/<?= $log['id'] ?>" class="btn btn-sm btn-warning">ویرایش</a>
                                            <button class="btn btn-sm btn-danger delete-log" data-id="<?= $log['id'] ?>">حذف</button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if (isset($totalPages) && $totalPages > 1): ?>
                    <div class="d-flex justify-content-center mt-4">
                        <nav aria-label="Page navigation">
                            <ul class="pagination">
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?= $currentPage == $i ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?><?= isset($queryString) ? $queryString : '' ?>"><?= $i ?></a>
                                </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        // تنظیم تاریخ شمسی
        $('.datepicker').persianDatepicker({
            format: 'YYYY/MM/DD',
            autoClose: true
        });
        
        // اسکریپت برای حذف سابقه نگهداری
        $('.delete-log').on('click', function() {
            const logId = $(this).data('id');
            if (confirm('آیا از حذف این سابقه نگهداری اطمینان دارید؟')) {
                $.ajax({
                    url: '/maintenance/logs/delete/' + logId,
                    type: 'POST',
                    success: function(response) {
                        const result = JSON.parse(response);
                        if (result.success) {
                            location.reload();
                        } else {
                            alert('خطا در حذف سابقه نگهداری: ' + result.message);
                        }
                    },
                    error: function() {
                        alert('خطا در ارتباط با سرور');
                    }
                });
            }
        });
    });
</script>

<?php include_once '../app/views/footer.php'; ?>