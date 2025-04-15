<?php require_once __DIR__ . '/../header.php'; ?>

<div class="container-fluid">
    <!-- عنوان صفحه -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">جزئیات تجهیز: <?= htmlspecialchars($asset['asset_tag']) ?></h1>
        <div>
            <a href="/support_system/assets" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-right"></i> بازگشت به لیست
            </a>
            <a href="/support_system/assets/print_label/<?= $asset['id'] ?>" class="btn btn-info btn-sm" target="_blank">
                <i class="fas fa-print"></i> چاپ برچسب
            </a>
            <?php if ($accessControl->hasPermission('edit_assets')): ?>
            <a href="/support_system/assets/edit/<?= $asset['id'] ?>" class="btn btn-primary btn-sm">
                <i class="fas fa-edit"></i> ویرایش
            </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="row">
        <!-- اطلاعات اصلی تجهیز -->
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">اطلاعات اصلی</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>برچسب تجهیز:</strong> <?= htmlspecialchars($asset['asset_tag']) ?></p>
                            <p><strong>دسته‌بندی:</strong> <?= htmlspecialchars($asset['category_name']) ?></p>
                            <p><strong>مدل:</strong> <?= htmlspecialchars($asset['model_name']) ?></p>
                            <p><strong>سازنده:</strong> <?= htmlspecialchars($asset['manufacturer'] ?? '-') ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>شماره سریال:</strong> <?= htmlspecialchars($asset['serial_number'] ?? '-') ?></p>
                            <p><strong>تاریخ خرید:</strong> <?= $asset['purchase_date'] ? date('Y/m/d', strtotime($asset['purchase_date'])) : '-' ?></p>
                            <p><strong>تاریخ پایان گارانتی:</strong> <?= $asset['warranty_expiry_date'] ? date('Y/m/d', strtotime($asset['warranty_expiry_date'])) : '-' ?></p>
                            <p>
                                <strong>وضعیت:</strong>
                                <?php
                                $statusClass = '';
                                $statusText = '';
                                switch ($asset['status']) {
                                    case 'active':
                                        $statusClass = 'success';
                                        $statusText = 'فعال';
                                        break;
                                    case 'maintenance':
                                        $statusClass = 'warning';
                                        $statusText = 'در حال سرویس';
                                        break;
                                    case 'repair':
                                        $statusClass = 'danger';
                                        $statusText = 'در حال تعمیر';
                                        break;
                                    case 'retired':
                                        $statusClass = 'secondary';
                                        $statusText = 'بازنشسته';
                                        break;
                                    case 'storage':
                                        $statusClass = 'info';
                                        $statusText = 'در انبار';
                                        break;
                                }
                                ?>
                                <span class="badge badge-<?= $statusClass ?>"><?= $statusText ?></span>
                            </p>
                        </div>
                    </div>
                    <?php if (!empty($asset['notes'])): ?>
                    <hr>
                    <div class="row">
                        <div class="col-12">
                            <p><strong>یادداشت‌ها:</strong></p>
                            <p><?= nl2br(htmlspecialchars($asset['notes'])) ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- اطلاعات تخصیص -->
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">تخصیص تجهیز</h6>
                    <?php if ($accessControl->hasPermission('assign_assets')): ?>
                    <a href="/support_system/assets/assign/<?= $asset['id'] ?>" class="btn btn-sm btn-success">
                        <i class="fas fa-user-plus"></i> تخصیص به کاربر
                    </a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (!empty($asset['assigned_to'])): ?>
                    <div class="row">
                        <div class="col-md-12">
                            <p><strong>تخصیص داده شده به:</strong> <?= htmlspecialchars($asset['assigned_to']) ?></p>
                            <?php
                            $currentAssignment = null;
                            foreach ($assignmentHistory as $assignment) {
                                if ($assignment['is_current']) {
                                    $currentAssignment = $assignment;
                                    break;
                                }
                            }
                            ?>
                            <?php if ($currentAssignment): ?>
                            <p><strong>تاریخ تخصیص:</strong> <?= date('Y/m/d', strtotime($currentAssignment['assigned_date'])) ?></p>
                            <?php if (!empty($currentAssignment['notes'])): ?>
                            <p><strong>یادداشت:</strong> <?= nl2br(htmlspecialchars($currentAssignment['notes'])) ?></p>
                            <?php endif; ?>
                            <?php if ($accessControl->hasPermission('assign_assets')): ?>
                            <form method="POST" action="/support_system/assets/unassign" class="mt-3">
                                <input type="hidden" name="assignment_id" value="<?= $currentAssignment['id'] ?>">
                                <input type="hidden" name="asset_id" value="<?= $asset['id'] ?>">
                                <button type="submit" class="btn btn-warning btn-sm" onclick="return confirm('آیا از لغو تخصیص این تجهیز اطمینان دارید؟')">
                                    <i class="fas fa-user-minus"></i> لغو تخصیص
                                </button>
                            </form>
                            <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php else: ?>
                    <p>این تجهیز به هیچ کاربری تخصیص داده نشده است.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- مشخصات سخت‌افزاری -->
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">مشخصات سخت‌افزاری</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($specifications)): ?>
                    <p>هیچ مشخصه‌ای ثبت نشده است.</p>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>نام مشخصه</th>
                                    <th>مقدار</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($specifications as $spec): ?>
                                <tr>
                                    <td><?= htmlspecialchars($spec['spec_name']) ?></td>
                                    <td><?= htmlspecialchars($spec['spec_value']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- نرم‌افزارهای نصب شده -->
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">نرم‌افزارهای نصب شده</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($software)): ?>
                    <p>هیچ نرم‌افزاری ثبت نشده است.</p>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>نام نرم‌افزار</th>
                                    <th>نسخه</th>
                                    <th>کلید لایسنس</th>
                                    <th>تاریخ انقضا</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($software as $sw): ?>
                                <tr>
                                    <td><?= htmlspecialchars($sw['software_name']) ?></td>
                                    <td><?= htmlspecialchars($sw['version'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($sw['license_key'] ?? '-') ?></td>
                                    <td><?= $sw['expiry_date'] ? date('Y/m/d', strtotime($sw['expiry_date'])) : '-' ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- سرویس‌های ادواری -->
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">سرویس‌های ادواری</h6>
                    <?php if ($accessControl->hasPermission('manage_maintenance')): ?>
                    <div>
                        <a href="/support_system/assets/add_maintenance/<?= $asset['id'] ?>" class="btn btn-sm btn-primary">
                            <i class="fas fa-plus"></i> افزودن برنامه سرویس
                        </a>
                        <a href="/support_system/assets/add_maintenance_log/<?= $asset['id'] ?>" class="btn btn-sm btn-success">
                            <i class="fas fa-check"></i> ثبت انجام سرویس
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (empty($maintenanceSchedules)): ?>
                    <p>هیچ برنامه سرویسی ثبت نشده است.</p>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>نوع سرویس</th>
                                    <th>آخرین سرویس</th>
                                    <th>سرویس بعدی</th>
                                    <th>عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($maintenanceSchedules as $schedule): ?>
                                <tr>
                                    <td>
                                        <?= htmlspecialchars($schedule['maintenance_type']) ?>
                                        <small class="d-block text-muted"><?= htmlspecialchars($schedule['description'] ?? '') ?></small>
                                    </td>
                                    <td><?= $schedule['last_maintenance_date'] ? date('Y/m/d', strtotime($schedule['last_maintenance_date'])) : '-' ?></td>
                                    <td>
                                        <?php
                                        $nextDate = strtotime($schedule['next_maintenance_date']);
                                        $today = strtotime(date('Y-m-d'));
                                        $daysLeft = ceil(($nextDate - $today) / (60 * 60 * 24));
                                        
                                        echo date('Y/m/d', $nextDate);
                                        
                                        if ($daysLeft < 0) {
                                            echo ' <span class="badge badge-danger">' . abs($daysLeft) . ' روز گذشته</span>';
                                        } elseif ($daysLeft <= 7) {
                                            echo ' <span class="badge badge-warning">' . $daysLeft . ' روز مانده</span>';
                                        } else {
                                            echo ' <span class="badge badge-success">' . $daysLeft . ' روز مانده</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ($accessControl->hasPermission('manage_maintenance')): ?>
                                        <form method="POST" action="/support_system/assets/delete_maintenance_schedule" class="d-inline">
                                            <input type="hidden" name="schedule_id" value="<?= $schedule['id'] ?>">
                                            <input type="hidden" name="asset_id" value="<?= $asset['id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('آیا از حذف این برنامه سرویس اطمینان دارید؟')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- سوابق سرویس -->
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">سوابق سرویس</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($maintenanceLogs)): ?>
                    <p>هیچ سابقه سرویسی ثبت نشده است.</p>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>نوع سرویس</th>
                                    <th>تاریخ انجام</th>
                                    <th>انجام دهنده</th>
                                    <th>توضیحات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($maintenanceLogs as $log): ?>
                                <tr>
                                    <td><?= htmlspecialchars($log['maintenance_type']) ?></td>
                                    <td><?= date('Y/m/d', strtotime($log['performed_date'])) ?></td>
                                    <td><?= htmlspecialchars($log['performed_by_name'] ?? '-') ?></td>
                                    <td><?= nl2br(htmlspecialchars($log['notes'] ?? '')) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- تاریخچه تخصیص -->
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">تاریخچه تخصیص</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($assignmentHistory)): ?>
                    <p>هیچ سابقه تخصیصی ثبت نشده است.</p>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>کاربر</th>
                                    <th>تاریخ تخصیص</th>
                                    <th>تاریخ بازگشت</th>
                                    <th>وضعیت</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($assignmentHistory as $assignment): ?>
                                <tr>
                                    <td><?= htmlspecialchars($assignment['user_name']) ?></td>
                                    <td><?= date('Y/m/d', strtotime($assignment['assigned_date'])) ?></td>
                                    <td><?= $assignment['return_date'] ? date('Y/m/d', strtotime($assignment['return_date'])) : '-' ?></td>
                                    <td>
                                        <?php if ($assignment['is_current']): ?>
                                        <span class="badge badge-success">فعال</span>
                                        <?php else: ?>
                                        <span class="badge badge-secondary">پایان یافته</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- تغییرات سخت‌افزاری -->
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">تغییرات سخت‌افزاری</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($hardwareChanges)): ?>
                    <p>هیچ تغییر سخت‌افزاری ثبت نشده است.</p>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>قطعه</th>
                                    <th>مقدار قبلی</th>
                                    <th>مقدار جدید</th>
                                    <th>تاریخ تغییر</th>
                                    <th>تغییر دهنده</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($hardwareChanges as $change): ?>
                                <tr>
                                    <td><?= htmlspecialchars($change['component_name']) ?></td>
                                    <td><?= htmlspecialchars($change['old_value'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($change['new_value'] ?? '-') ?></td>
                                    <td><?= date('Y/m/d', strtotime($change['changed_date'])) ?></td>
                                    <td><?= htmlspecialchars($change['changed_by_name'] ?? '-') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../footer.php'; ?>