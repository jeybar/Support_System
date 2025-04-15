<?php include_once '../app/views/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <?php include_once '../app/views/components/page_header.php'; ?>
        
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">جزئیات برنامه نگهداری</h5>
                    <div>
                        <a href="/maintenance/schedules/edit/<?= $schedule['id'] ?>" class="btn btn-warning">ویرایش</a>
                        <?php if ($schedule['status'] != 'completed'): ?>
                            <a href="/maintenance/logs/create?schedule_id=<?= $schedule['id'] ?>" class="btn btn-success">ثبت انجام</a>
                        <?php endif; ?>
                        <a href="/maintenance/schedules" class="btn btn-secondary">بازگشت به لیست</a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>اطلاعات برنامه نگهداری</h6>
                            <table class="table table-bordered">
                                <tr>
                                    <th>شناسه</th>
                                    <td><?= $schedule['id'] ?></td>
                                </tr>
                                <tr>
                                    <th>تجهیز</th>
                                    <td>
                                        <a href="/assets/view/<?= $schedule['asset_id'] ?>">
                                            <?= htmlspecialchars($asset['name']) ?> (<?= htmlspecialchars($asset['asset_tag']) ?>)
                                        </a>
                                    </td>
                                </tr>
                                <tr>
                                    <th>نوع نگهداری</th>
                                    <td>
                                        <a href="/maintenance/types/view/<?= $schedule['maintenance_type_id'] ?>">
                                            <?= htmlspecialchars($maintenanceType['name']) ?>
                                        </a>
                                    </td>
                                </tr>
                                <tr>
                                    <th>دوره زمانی</th>
                                    <td>هر <?= $maintenanceType['interval_days'] ?> روز</td>
                                </tr>
                                <tr>
                                    <th>تاریخ شروع</th>
                                    <td><?= format_date($schedule['start_date']) ?></td>
                                </tr>
                                <tr>
                                    <th>تاریخ پایان</th>
                                    <td><?= $schedule['end_date'] ? format_date($schedule['end_date']) : 'بدون تاریخ پایان' ?></td>
                                </tr>
                                <tr>
                                    <th>تاریخ نگهداری بعدی</th>
                                    <td><?= format_date($schedule['next_date']) ?></td>
                                </tr>
                                <tr>
                                    <th>وضعیت</th>
                                    <td>
                                        <?php if ($schedule['status'] == 'overdue'): ?>
                                            <span class="badge bg-danger">معوق</span>
                                        <?php elseif ($schedule['status'] == 'upcoming'): ?>
                                            <span class="badge bg-info">پیش رو (<?= $schedule['days_remaining'] ?> روز)</span>
                                        <?php elseif ($schedule['status'] == 'completed'): ?>
                                            <span class="badge bg-success">تکمیل شده</span>
                                        <?php elseif ($schedule['status'] == 'inactive'): ?>
                                            <span class="badge bg-secondary">غیرفعال</span>
                                        <?php else: ?>
                                            <span class="badge bg-primary">فعال</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th>اطلاع‌رسانی به کاربر</th>
                                    <td><?= $schedule['notify_user'] ? 'بله' : 'خیر' ?></td>
                                </tr>
                                <tr>
                                    <th>یادداشت‌ها</th>
                                    <td><?= htmlspecialchars($schedule['notes']) ?></td>
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
                            
                            <h6 class="mt-4">اطلاعات تجهیز</h6>
                            <table class="table table-bordered">
                                <tr>
                                    <th>نام</th>
                                    <td><?= htmlspecialchars($asset['name']) ?></td>
                                </tr>
                                <tr>
                                    <th>برچسب تجهیز</th>
                                    <td><?= htmlspecialchars($asset['asset_tag']) ?></td>
                                </tr>
                                <tr>
                                    <th>دسته‌بندی</th>
                                    <td><?= htmlspecialchars($asset['category_name']) ?></td>
                                </tr>
                                <tr>
                                    <th>وضعیت</th>
                                    <td><?= htmlspecialchars($asset['status']) ?></td>
                                </tr>
                                <tr>
                                    <th>کاربر</th>
                                    <td><?= $asset['user_id'] ? htmlspecialchars($asset['user_name']) : 'تخصیص داده نشده' ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <div class="row mt-4">
                        <div class="col-12">
                            <h6>تاریخچه نگهداری</h6>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>شناسه</th>
                                            <th>تاریخ انجام</th>
                                            <th>انجام‌دهنده</th>
                                            <th>وضعیت</th>
                                            <th>توضیحات</th>
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
                                                <td><?= $log['id'] ?></td>
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
                                                <td><?= htmlspecialchars(substr($log['notes'], 0, 50)) ?><?= strlen($log['notes']) > 50 ? '...' : '' ?></td>
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
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../app/views/footer.php'; ?>