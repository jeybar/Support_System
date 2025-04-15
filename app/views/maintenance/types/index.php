<?php include_once '../app/views/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <?php include_once '../app/views/components/page_header.php'; ?>
        
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">انواع نگهداری</h5>
                    <a href="/maintenance/types/create" class="btn btn-primary">افزودن نوع نگهداری جدید</a>
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
                                    <th>نام</th>
                                    <th>توضیحات</th>
                                    <th>دوره زمانی (روز)</th>
                                    <th>تعداد تجهیز‌ها</th>
                                    <th>عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($maintenanceTypes)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center">هیچ نوع نگهداری یافت نشد</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($maintenanceTypes as $type): ?>
                                    <tr>
                                        <td><?= $type['id'] ?></td>
                                        <td><?= htmlspecialchars($type['name']) ?></td>
                                        <td><?= htmlspecialchars($type['description']) ?></td>
                                        <td><?= $type['interval_days'] ?></td>
                                        <td><?= $type['asset_count'] ?></td>
                                        <td>
                                            <a href="/maintenance/types/view/<?= $type['id'] ?>" class="btn btn-sm btn-info">مشاهده</a>
                                            <a href="/maintenance/types/edit/<?= $type['id'] ?>" class="btn btn-sm btn-warning">ویرایش</a>
                                            <button class="btn btn-sm btn-danger delete-type" data-id="<?= $type['id'] ?>">حذف</button>
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
    // اسکریپت برای حذف نوع نگهداری
    $('.delete-type').on('click', function() {
        const typeId = $(this).data('id');
        if (confirm('آیا از حذف این نوع نگهداری اطمینان دارید؟')) {
            $.ajax({
                url: '/maintenance/types/delete/' + typeId,
                type: 'POST',
                success: function(response) {
                    const result = JSON.parse(response);
                    if (result.success) {
                        location.reload();
                    } else {
                        alert('خطا در حذف نوع نگهداری: ' + result.message);
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