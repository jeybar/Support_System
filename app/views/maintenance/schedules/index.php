<?php include_once '../app/views/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <?php include_once '../app/views/components/page_header.php'; ?>
        
        <div class="col-12 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">فیلتر برنامه‌های نگهداری</h5>
                </div>
                <div class="card-body">
                    <form id="filter-form" method="GET" action="/maintenance/schedules">
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
                                    <option value="upcoming" <?= isset($_GET['status']) && $_GET['status'] == 'upcoming' ? 'selected' : '' ?>>پیش رو</option>
                                    <option value="overdue" <?= isset($_GET['status']) && $_GET['status'] == 'overdue' ? 'selected' : '' ?>>معوق</option>
                                    <option value="completed" <?= isset($_GET['status']) && $_GET['status'] == 'completed' ? 'selected' : '' ?>>تکمیل شده</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary">اعمال فیلتر</button>
                                <a href="/maintenance/schedules" class="btn btn-secondary ms-2">پاک کردن فیلتر</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">برنامه‌های نگهداری</h5>
                    <a href="/maintenance/schedules/create" class="btn btn-primary">افزودن برنامه نگهداری جدید</a>
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
                                    <th>تاریخ برنامه‌ریزی شده</th>
                                    <th>وضعیت</th>
                                    <th>آخرین نگهداری</th>
                                    <th>عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($schedules)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center">هیچ برنامه نگهداری یافت نشد</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($schedules as $schedule): ?>
                                    <tr class="<?= $schedule['status'] == 'overdue' ? 'table-danger' : ($schedule['status'] == 'upcoming' && $schedule['days_remaining'] <= 7 ? 'table-warning' : '') ?>">
                                        <td><?= $schedule['id'] ?></td>
                                        <td>
                                            <a href="/assets/view/<?= $schedule['asset_id'] ?>">
                                                <?= htmlspecialchars($schedule['asset_name']) ?>
                                            </a>
                                        </td>
                                        <td><?= htmlspecialchars($schedule['maintenance_type_name']) ?></td>
                                        <td><?= format_date($schedule['scheduled_date']) ?></td>
                                        <td>
                                            <?php if ($schedule['status'] == 'overdue'): ?>
                                                <span class="badge bg-danger">معوق</span>
                                            <?php elseif ($schedule['status'] == 'upcoming'): ?>
                                                <span class="badge bg-info">پیش رو (<?= $schedule['days_remaining'] ?> روز)</span>
                                            <?php elseif ($schedule['status'] == 'completed'): ?>
                                                <span class="badge bg-success">تکمیل شده</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?= $schedule['last_maintenance_date'] ? format_date($schedule['last_maintenance_date']) : 'ندارد' ?>
                                        </td>
                                        <td>
                                            <a href="/maintenance/schedules/view/<?= $schedule['id'] ?>" class="btn btn-sm btn-info">مشاهده</a>
                                            <a href="/maintenance/schedules/edit/<?= $schedule['id'] ?>" class="btn btn-sm btn-warning">ویرایش</a>
                                            <?php if ($schedule['status'] != 'completed'): ?>
                                                <a href="/maintenance/logs/create?schedule_id=<?= $schedule['id'] ?>" class="btn btn-sm btn-success">ثبت انجام</a>
                                            <?php endif; ?>
                                            <button class="btn btn-sm btn-danger delete-schedule" data-id="<?= $schedule['id'] ?>">حذف</button>
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
    // اسکریپت برای حذف برنامه نگهداری
    $('.delete-schedule').on('click', function() {
        const scheduleId = $(this).data('id');
        if (confirm('آیا از حذف این برنامه نگهداری اطمینان دارید؟')) {
            $.ajax({
                url: '/maintenance/schedules/delete/' + scheduleId,
                type: 'POST',
                success: function(response) {
                    const result = JSON.parse(response);
                    if (result.success) {
                        location.reload();
                    } else {
                        alert('خطا در حذف برنامه نگهداری: ' + result.message);
                    }
                },
                error: function() {
                    alert('خطا در ارتباط با سرور');
                }
            });
        }
    });
</script>

<?php include_once '../app/views/footer.php'; ?>