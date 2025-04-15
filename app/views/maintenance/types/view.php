<?php include_once '../app/views/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <?php include_once '../app/views/components/page_header.php'; ?>
        
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">جزئیات نوع نگهداری</h5>
                    <div>
                        <a href="/maintenance/types/edit/<?= $maintenanceType['id'] ?>" class="btn btn-warning">ویرایش</a>
                        <a href="/maintenance/types" class="btn btn-secondary">بازگشت به لیست</a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>اطلاعات اصلی</h6>
                            <table class="table table-bordered">
                                <tr>
                                    <th>شناسه</th>
                                    <td><?= $maintenanceType['id'] ?></td>
                                </tr>
                                <tr>
                                    <th>نام</th>
                                    <td><?= htmlspecialchars($maintenanceType['name']) ?></td>
                                </tr>
                                <tr>
                                    <th>توضیحات</th>
                                    <td><?= htmlspecialchars($maintenanceType['description']) ?></td>
                                </tr>
                                <tr>
                                    <th>دوره زمانی (روز)</th>
                                    <td><?= $maintenanceType['interval_days'] ?></td>
                                </tr>
                                <tr>
                                    <th>تعداد تجهیز‌های مرتبط</th>
                                    <td><?= $assetCount ?></td>
                                </tr>
                                <tr>
                                    <th>تعداد برنامه‌های نگهداری</th>
                                    <td><?= $scheduleCount ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6>چک‌لیست نگهداری</h6>
                            <?php if (empty($checklistItems)): ?>
                                <p>چک‌لیستی تعریف نشده است</p>
                            <?php else: ?>
                                <ol class="list-group list-group-numbered">
                                    <?php foreach ($checklistItems as $item): ?>
                                    <li class="list-group-item"><?= htmlspecialchars($item) ?></li>
                                    <?php endforeach; ?>
                                </ol>
                            <?php endif; ?>
                            
                            <h6 class="mt-4">دسته‌بندی‌های تجهیز مرتبط</h6>
                            <?php if (empty($relatedCategories)): ?>
                                <p>هیچ دسته‌بندی مرتبطی تعریف نشده است</p>
                            <?php else: ?>
                                <ul class="list-group">
                                    <?php foreach ($relatedCategories as $category): ?>
                                    <li class="list-group-item"><?= htmlspecialchars($category['name']) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="row mt-4">
                        <div class="col-12">
                            <h6>برنامه‌های نگهداری</h6>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>شناسه</th>
                                            <th>تجهیز</th>
                                            <th>تاریخ برنامه‌ریزی شده</th>
                                            <th>وضعیت</th>
                                            <th>آخرین نگهداری</th>
                                            <th>عملیات</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($schedules)): ?>
                                            <tr>
                                                <td colspan="6" class="text-center">هیچ برنامه نگهداری یافت نشد</td>
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
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../app/views/footer.php'; ?>