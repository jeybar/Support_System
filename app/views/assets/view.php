<div class="container-fluid mt-4">
    <h1 class="h3 mb-2 text-gray-800">جزئیات تجهیز: <?= htmlspecialchars($asset['asset_tag']); ?></h1>
    
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= $_SESSION['success']; ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= $_SESSION['error']; ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">اطلاعات اصلی</h6>
                    <div>
                        <a href="/support_system/assets/edit/<?= $asset['id']; ?>" class="btn btn-primary btn-sm">
                            <i class="fas fa-edit"></i> ویرایش
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <tr>
                                <th width="30%">برچسب تجهیز</th>
                                <td><?= htmlspecialchars($asset['asset_tag']); ?></td>
                            </tr>
                            <tr>
                                <th>دسته‌بندی</th>
                                <td><?= htmlspecialchars($asset['category_name']); ?></td>
                            </tr>
                            <tr>
                                <th>مدل</th>
                                <td><?= htmlspecialchars($asset['model_name']); ?></td>
                            </tr>
                            <tr>
                                <th>سازنده</th>
                                <td><?= htmlspecialchars($asset['manufacturer']); ?></td>
                            </tr>
                            <tr>
                                <th>شماره سریال</th>
                                <td><?= htmlspecialchars($asset['serial_number']); ?></td>
                            </tr>
                            <tr>
                                <th>تاریخ خرید</th>
                                <td><?= !empty($asset['purchase_date']) ? date('Y-m-d', strtotime($asset['purchase_date'])) : 'نامشخص'; ?></td>
                            </tr>
                            <tr>
                                <th>تاریخ انقضای گارانتی</th>
                                <td>
                                    <?php if (!empty($asset['warranty_expiry_date'])): ?>
                                        <?= date('Y-m-d', strtotime($asset['warranty_expiry_date'])); ?>
                                        <?php
                                        $today = new DateTime();
                                        $warranty = new DateTime($asset['warranty_expiry_date']);
                                        $diff = $today->diff($warranty);
                                        if ($today < $warranty) {
                                            echo '<span class="badge badge-success">' . $diff->days . ' روز باقی‌مانده</span>';
                                        } else {
                                            echo '<span class="badge badge-danger">منقضی شده</span>';
                                        }
                                        ?>
                                    <?php else: ?>
                                        نامشخص
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>وضعیت</th>
                                <td>
                                    <?php
                                    $statusText = '';
                                    $statusClass = '';
                                    switch ($asset['status']) {
                                        case 'active':
                                            $statusText = 'فعال';
                                            $statusClass = 'badge-success';
                                            break;
                                        case 'in_repair':
                                            $statusText = 'در حال تعمیر';
                                            $statusClass = 'badge-warning';
                                            break;
                                        case 'retired':
                                            $statusText = 'بازنشسته';
                                            $statusClass = 'badge-secondary';
                                            break;
                                        case 'lost':
                                            $statusText = 'مفقود';
                                            $statusClass = 'badge-danger';
                                            break;
                                    }
                                    ?>
                                    <span class="badge <?= $statusClass; ?>"><?= $statusText; ?></span>
                                </td>
                            </tr>
                            <tr>
                                <th>یادداشت‌ها</th>
                                <td><?= nl2br(htmlspecialchars($asset['notes'] ?? '')); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">وضعیت تخصیص</h6>
                    <div>
                        <?php if (empty($asset['user_id'])): ?>
                            <button type="button" class="btn btn-success btn-sm" data-toggle="modal" data-target="#assignModal">
                                <i class="fas fa-user-plus"></i> تخصیص به کاربر
                            </button>
                        <?php else: ?>
                            <button type="button" class="btn btn-warning btn-sm" data-toggle="modal" data-target="#unassignModal">
                                <i class="fas fa-user-minus"></i> بازگرداندن از کاربر
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (!empty($asset['user_id'])): ?>
                        <div class="alert alert-info">
                            این تجهیز در حال حاضر به <strong><?= htmlspecialchars($asset['assigned_to']); ?></strong> تخصیص داده شده است.
                        </div>
                    <?php else: ?>
                        <div class="alert alert-secondary">
                            این تجهیز در حال حاضر به هیچ کاربری تخصیص داده نشده است.
                        </div>
                    <?php endif; ?>
                    
                    <h6 class="font-weight-bold mt-4">تاریخچه تخصیص</h6>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>کاربر</th>
                                    <th>تاریخ تخصیص</th>
                                    <th>تاریخ بازگشت</th>
                                    <th>یادداشت‌ها</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($assignmentHistory)): ?>
                                    <?php foreach ($assignmentHistory as $assignment): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($assignment['user_name']); ?></td>
                                            <td><?= date('Y-m-d', strtotime($assignment['assigned_date'])); ?></td>
                                            <td>
                                                <?= !empty($assignment['return_date']) ? date('Y-m-d', strtotime($assignment['return_date'])) : 'در حال استفاده'; ?>
                                            </td>
                                            <td><?= nl2br(htmlspecialchars($assignment['notes'] ?? '')); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center">هیچ سابقه تخصیصی یافت نشد.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">مشخصات سخت‌افزاری</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>مشخصه</th>
                                    <th>مقدار</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($specifications)): ?>
                                    <?php foreach ($specifications as $spec): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($spec['spec_name']); ?></td>
                                            <td><?= htmlspecialchars($spec['spec_value']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="2" class="text-center">هیچ مشخصه‌ای ثبت نشده است.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">نرم‌افزارهای نصب شده</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>نرم‌افزار</th>
                                    <th>نسخه</th>
                                    <th>کلید لایسنس</th>
                                    <th>تاریخ انقضا</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($software)): ?>
                                    <?php foreach ($software as $sw): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($sw['software_name']); ?></td>
                                            <td><?= htmlspecialchars($sw['version']); ?></td>
                                            <td><?= htmlspecialchars($sw['license_key']); ?></td>
                                            <td>
                                                <?php if (!empty($sw['expiry_date'])): ?>
                                                    <?= date('Y-m-d', strtotime($sw['expiry_date'])); ?>
                                                    <?php
                                                    $today = new DateTime();
                                                    $expiry = new DateTime($sw['expiry_date']);
                                                    if ($today < $expiry) {
                                                        echo '<span class="badge badge-success">معتبر</span>';
                                                    } else {
                                                        echo '<span class="badge badge-danger">منقضی شده</span>';
                                                    }
                                                    ?>
                                                <?php else: ?>
                                                    نامشخص
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center">هیچ نرم‌افزاری ثبت نشده است.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">برنامه‌های سرویس ادواری</h6>
                    <div>
                        <a href="/support_system/assets/add-maintenance/<?= $asset['id']; ?>" class="btn btn-success btn-sm">
                            <i class="fas fa-plus"></i> افزودن سرویس ادواری
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>نوع سرویس</th>
                                    <th>آخرین سرویس</th>
                                    <th>سرویس بعدی</th>
                                    <th>وضعیت</th>
                                    <th>عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($maintenanceSchedules)): ?>
                                    <?php foreach ($maintenanceSchedules as $schedule): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($schedule['maintenance_type']); ?></td>
                                            <td>
                                                <?= !empty($schedule['last_maintenance_date']) ? date('Y-m-d', strtotime($schedule['last_maintenance_date'])) : 'هنوز انجام نشده'; ?>
                                            </td>
                                            <td><?= date('Y-m-d', strtotime($schedule['next_maintenance_date'])); ?></td>
                                            <td>
                                                <?php
                                                $today = new DateTime();
                                                $nextDate = new DateTime($schedule['next_maintenance_date']);
                                                $diff = $today->diff($nextDate);
                                                
                                                if ($today > $nextDate) {
                                                    echo '<span class="badge badge-danger">تأخیر ' . $diff->days . ' روز</span>';
                                                } elseif ($diff->days <= 7) {
                                                    echo '<span class="badge badge-warning">' . $diff->days . ' روز مانده</span>';
                                                } else {
                                                    echo '<span class="badge badge-success">' . $diff->days . ' روز مانده</span>';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <a href="/support_system/assets/perform-maintenance/<?= $schedule['id']; ?>" class="btn btn-primary btn-sm">
                                                    <i class="fas fa-check"></i> ثبت انجام
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center">هیچ برنامه سرویسی ثبت نشده است.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">سوابق سرویس‌های ادواری</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>نوع سرویس</th>
                                    <th>تاریخ انجام</th>
                                    <th>انجام دهنده</th>
                                    <th>یادداشت‌ها</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($maintenanceLogs)): ?>
                                    <?php foreach ($maintenanceLogs as $log): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($log['maintenance_type']); ?></td>
                                            <td><?= date('Y-m-d', strtotime($log['performed_date'])); ?></td>
                                            <td><?= htmlspecialchars($log['performed_by_name'] ?? 'نامشخص'); ?></td>
                                            <td><?= nl2br(htmlspecialchars($log['notes'] ?? '')); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center">هیچ سابقه سرویسی ثبت نشده است.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- مدال تخصیص تجهیز -->
<div class="modal fade" id="assignModal" tabindex="-1" role="dialog" aria-labelledby="assignModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="assignModalLabel">تخصیص تجهیز به کاربر</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="/support_system/assets/assign/<?= $asset['id']; ?>" method="POST">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="user_id">کاربر</label>
                        <select name="user_id" id="user_id" class="form-control" required>
                            <option value="">انتخاب کنید...</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?= $user['id']; ?>"><?= htmlspecialchars($user['fullname']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="notes">یادداشت‌ها</label>
                        <textarea name="notes" id="notes" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">انصراف</button>
                    <button type="submit" class="btn btn-success">تخصیص</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- مدال بازگرداندن تجهیز -->
<?php if (!empty($assignmentHistory)): ?>
    <?php
    $currentAssignment = null;
    foreach ($assignmentHistory as $assignment) {
        if (empty($assignment['return_date'])) {
            $currentAssignment = $assignment;
            break;
        }
    }
    ?>
    <?php if ($currentAssignment): ?>
        <div class="modal fade" id="unassignModal" tabindex="-1" role="dialog" aria-labelledby="unassignModalLabel" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="unassignModalLabel">بازگرداندن تجهیز از کاربر</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <form action="/support_system/assets/unassign" method="POST">
                        <div class="modal-body">
                            <p>آیا از بازگرداندن این تجهیز از کاربر <strong><?= htmlspecialchars($asset['assigned_to']); ?></strong> اطمینان دارید؟</p>
                            <input type="hidden" name="assignment_id" value="<?= $currentAssignment['id']; ?>">
                            <input type="hidden" name="asset_id" value="<?= $asset['id']; ?>">
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">انصراف</button>
                            <button type="submit" class="btn btn-warning">بازگرداندن</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>