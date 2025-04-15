<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">برنامه نگهداری</h5>
        <a href="/maintenance/schedules/create?asset_id=<?= $asset['id'] ?>" class="btn btn-sm btn-primary">افزودن برنامه نگهداری</a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>نوع نگهداری</th>
                        <th>تاریخ بعدی</th>
                        <th>وضعیت</th>
                        <th>آخرین نگهداری</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($maintenanceSchedules)): ?>
                        <tr>
                            <td colspan="5" class="text-center">هیچ برنامه نگهداری یافت نشد</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($maintenanceSchedules as $schedule): ?>
                        <tr class="<?= $schedule['status'] == 'overdue' ? 'table-danger' : ($schedule['status'] == 'upcoming' && $schedule['days_remaining'] <= 7 ? 'table-warning' : '') ?>">
                            <td><?= htmlspecialchars($schedule['maintenance_type_name']) ?></td>
                            <td><?= format_date($schedule['next_date']) ?></td>
                            <td>
                                <?php if ($schedule['status'] == 'overdue'): ?>
                                    <span class="badge bg-danger">معوق (<?= $schedule['days_overdue'] ?> روز)</span>
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
                                <?php if ($schedule['status'] != 'completed'): ?>
                                    <a href="/maintenance/logs/create?schedule_id=<?= $schedule['id'] ?>" class="btn btn-sm btn-success">ثبت انجام</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>