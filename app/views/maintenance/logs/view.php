<?php include_once '../app/views/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <?php include_once '../app/views/components/page_header.php'; ?>
        
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">جزئیات سابقه نگهداری</h5>
                    <div>
                        <a href="/maintenance/logs/edit/<?= $maintenanceLog['id'] ?>" class="btn btn-warning">ویرایش</a>
                        <a href="/maintenance/logs" class="btn btn-secondary">بازگشت به لیست</a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>اطلاعات سابقه نگهداری</h6>
                            <table class="table table-bordered">
                                <tr>
                                    <th>شناسه</th>
                                    <td><?= $maintenanceLog['id'] ?></td>
                                </tr>
                                <tr>
                                    <th>تجهیز</th>
                                    <td>
                                        <a href="/assets/view/<?= $asset['id'] ?>">
                                            <?= htmlspecialchars($asset['name']) ?> (<?= htmlspecialchars($asset['asset_tag']) ?>)
                                        </a>
                                    </td>
                                </tr>
                                <tr>
                                    <th>نوع نگهداری</th>
                                    <td>
                                        <a href="/maintenance/types/view/<?= $maintenanceType['id'] ?>">
                                            <?= htmlspecialchars($maintenanceType['name']) ?>
                                        </a>
                                    </td>
                                </tr>
                                <tr>
                                    <th>تاریخ انجام</th>
                                    <td><?= format_date($maintenanceLog['maintenance_date']) ?></td>
                                </tr>
                                <tr>
                                    <th>تکنسین</th>
                                    <td><?= htmlspecialchars($technician['name']) ?></td>
                                </tr>
                                <tr>
                                    <th>وضعیت</th>
                                    <td>
                                        <?php if ($maintenanceLog['status'] == 'completed'): ?>
                                            <span class="badge bg-success">تکمیل شده</span>
                                        <?php elseif ($maintenanceLog['status'] == 'incomplete'): ?>
                                            <span class="badge bg-warning">ناقص</span>
                                        <?php elseif ($maintenanceLog['status'] == 'failed'): ?>
                                            <span class="badge bg-danger">ناموفق</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th>هزینه</th>
                                    <td><?= number_format($maintenanceLog['cost']) ?> تومان</td>
                                </tr>
                                <tr>
                                    <th>توضیحات</th>
                                    <td><?= nl2br(htmlspecialchars($maintenanceLog['notes'])) ?></td>
                                </tr>
                                <tr>
                                    <th>تاریخ ثبت</th>
                                    <td><?= format_date($maintenanceLog['created_at']) ?></td>
                                </tr>
                                <tr>
                                    <th>آخرین به‌روزرسانی</th>
                                    <td><?= format_date($maintenanceLog['updated_at']) ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <?php if (isset($checklistItems) && !empty($checklistItems)): ?>
                                <h6>چک‌لیست</h6>
                                <div class="card mb-4">
                                    <div class="card-body">
                                        <ul class="list-group">
                                            <?php foreach ($checklistItems as $index => $item): ?>
                                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                                    <?= htmlspecialchars($item) ?>
                                                    <?php if (isset($completedItems[$index]) && $completedItems[$index]): ?>
                                                        <span class="badge bg-success rounded-pill"><i class="fas fa-check"></i></span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger rounded-pill"><i class="fas fa-times"></i></span>
                                                    <?php endif; ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($attachments)): ?>
                                <h6>پیوست‌ها</h6>
                                <div class="card">
                                    <div class="card-body">
                                        <div class="row">
                                            <?php foreach ($attachments as $attachment): ?>
                                                <div class="col-md-6 mb-2">
                                                    <div class="card">
                                                        <div class="card-body">
                                                            <p class="mb-1"><?= htmlspecialchars($attachment['filename']) ?></p>
                                                            <a href="/uploads/maintenance/<?= $attachment['filename'] ?>" class="btn btn-sm btn-info" target="_blank">مشاهده</a>
                                                            <a href="/uploads/maintenance/<?= $attachment['filename'] ?>" class="btn btn-sm btn-secondary" download>دانلود</a>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if (isset($schedule)): ?>
                        <div class="row mt-4">
                            <div class="col-12">
                                <h6>اطلاعات برنامه نگهداری</h6>
                                <table class="table table-bordered">
                                    <tr>
                                        <th>شناسه برنامه</th>
                                        <td><?= $schedule['id'] ?></td>
                                        <th>تاریخ برنامه‌ریزی شده</th>
                                        <td><?= format_date($schedule['scheduled_date']) ?></td>
                                    </tr>
                                    <tr>
                                        <th>دوره زمانی</th>
                                        <td>هر <?= $maintenanceType['interval_days'] ?> روز</td>
                                        <th>تاریخ نگهداری بعدی</th>
                                        <td><?= format_date($schedule['next_date']) ?></td>
                                    </tr>
                                </table>
                                <div class="mt-2">
                                    <a href="/maintenance/schedules/view/<?= $schedule['id'] ?>" class="btn btn-info">مشاهده جزئیات برنامه نگهداری</a>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../app/views/footer.php'; ?>