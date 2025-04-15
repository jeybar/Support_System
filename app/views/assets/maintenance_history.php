<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">تاریخچه نگهداری</h5>
        <a href="/maintenance/logs/create?asset_id=<?= $asset['id'] ?>" class="btn btn-sm btn-primary">ثبت نگهداری جدید</a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>نوع نگهداری</th>
                        <th>تاریخ انجام</th>
                        <th>تکنسین</th>
                        <th>وضعیت</th>
                        <th>هزینه</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($maintenanceLogs)): ?>
                        <tr>
                            <td colspan="6" class="text-center">هیچ سابقه نگهداری یافت نشد</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($maintenanceLogs as $log): ?>
                        <tr>
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
                            <td><?= number_format($log['cost']) ?> تومان</td>
                            <td>
                                <a href="/maintenance/logs/view/<?= $log['id'] ?>" class="btn btn-sm btn-info">مشاهده</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>