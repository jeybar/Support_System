<?php require_once __DIR__ . '/../layouts/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <?php require_once __DIR__ . '/../layouts/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">تجهیز‌های نیازمند توجه</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="/support_system/assets/reports" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-right"></i> بازگشت به گزارش‌ها
                    </a>
                </div>
            </div>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger"><?= $_SESSION['error'] ?></div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success"><?= $_SESSION['success'] ?></div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
            
            <!-- تجهیز‌های با گارانتی رو به اتمام -->
            <div class="card mb-4">
                <div class="card-header bg-warning text-dark">
                    <h5><i class="bi bi-calendar-x"></i> تجهیز‌های با گارانتی رو به اتمام (30 روز آینده)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($expiringWarranty)): ?>
                        <div class="alert alert-info">هیچ تجهیز با گارانتی رو به اتمام در 30 روز آینده وجود ندارد.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>برچسب تجهیز</th>
                                        <th>مدل</th>
                                        <th>دسته‌بندی</th>
                                        <th>تاریخ اتمام گارانتی</th>
                                        <th>روزهای باقی‌مانده</th>
                                        <th>تخصیص داده شده به</th>
                                        <th>عملیات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($expiringWarranty as $asset): ?>
                                        <tr>
                                            <td><?= $asset['asset_tag'] ?></td>
                                            <td><?= $asset['model_name'] ?></td>
                                            <td><?= $asset['category_name'] ?></td>
                                            <td><?= $asset['warranty_expiry_date'] ?></td>
                                            <td>
                                                <span class="badge bg-warning text-dark">
                                                    <?= $asset['days_remaining'] ?> روز
                                                </span>
                                            </td>
                                            <td><?= $asset['assigned_to'] ?? 'تخصیص داده نشده' ?></td>
                                            <td>
                                                <a href="/support_system/assets/show/<?= $asset['id'] ?>" class="btn btn-sm btn-info">
                                                    <i class="bi bi-eye"></i> مشاهده
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-2">
                            <a href="/support_system/assets/expiring_warranty" class="btn btn-outline-warning">
                                <i class="bi bi-list"></i> مشاهده همه
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- تجهیز‌های نیازمند تعمیر -->
            <div class="card mb-4">
                <div class="card-header bg-danger text-white">
                    <h5><i class="bi bi-wrench"></i> تجهیز‌های نیازمند تعمیر</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($inRepair)): ?>
                        <div class="alert alert-info">هیچ تجهیز نیازمند تعمیر وجود ندارد.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>برچسب تجهیز</th>
                                        <th>مدل</th>
                                        <th>دسته‌بندی</th>
                                        <th>تخصیص داده شده به</th>
                                        <th>توضیحات</th>
                                        <th>عملیات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($inRepair as $asset): ?>
                                        <tr>
                                            <td><?= $asset['asset_tag'] ?></td>
                                            <td><?= $asset['model_name'] ?></td>
                                            <td><?= $asset['category_name'] ?></td>
                                            <td><?= $asset['assigned_to'] ?? 'تخصیص داده نشده' ?></td>
                                            <td><?= $asset['notes'] ?></td>
                                            <td>
                                                <a href="/support_system/assets/show/<?= $asset['id'] ?>" class="btn btn-sm btn-info">
                                                    <i class="bi bi-eye"></i> مشاهده
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-2">
                            <a href="/support_system/assets?status=in_repair" class="btn btn-outline-danger">
                                <i class="bi bi-list"></i> مشاهده همه
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- تجهیز‌های با سرویس‌های ادواری معوق -->
            <div class="card mb-4">
                <div class="card-header bg-danger text-white">
                    <h5><i class="bi bi-tools"></i> تجهیز‌های با سرویس‌های ادواری معوق</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($overdueMaintenances)): ?>
                        <div class="alert alert-info">هیچ سرویس ادواری معوقی وجود ندارد.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>برچسب تجهیز</th>
                                        <th>مدل</th>
                                        <th>نوع سرویس</th>
                                        <th>تاریخ سرویس بعدی</th>
                                        <th>روزهای تأخیر</th>
                                        <th>تخصیص داده شده به</th>
                                        <th>عملیات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($overdueMaintenances as $maintenance): ?>
                                        <tr>
                                            <td><?= $maintenance['asset_tag'] ?></td>
                                            <td><?= $maintenance['model_name'] ?></td>
                                            <td><?= $maintenance['maintenance_type_name'] ?></td>
                                            <td><?= $maintenance['next_maintenance_date'] ?></td>
                                            <td>
                                                <span class="badge bg-danger">
                                                    <?= $maintenance['days_overdue'] ?> روز
                                                </span>
                                            </td>
                                            <td><?= $maintenance['assigned_to'] ?? 'تخصیص داده نشده' ?></td>
                                            <td>
                                                <a href="/support_system/assets/show/<?= $maintenance['id'] ?>" class="btn btn-sm btn-info">
                                                    <i class="bi bi-eye"></i> مشاهده
                                                </a>
                                                <a href="/support_system/assets/add_maintenance_log/<?= $maintenance['id'] ?>" class="btn btn-sm btn-success">
                                                    <i class="bi bi-check-lg"></i> ثبت سرویس
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-2">
                            <a href="/support_system/maintenance/overdue" class="btn btn-outline-danger">
                                <i class="bi bi-list"></i> مشاهده همه
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- نرم‌افزارهای با لایسنس رو به اتمام -->
            <div class="card mb-4">
                <div class="card-header bg-warning text-dark">
                    <h5><i class="bi bi-key"></i> نرم‌افزارهای با لایسنس رو به اتمام (30 روز آینده)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($expiringSoftware)): ?>
                        <div class="alert alert-info">هیچ نرم‌افزار با لایسنس رو به اتمام در 30 روز آینده وجود ندارد.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>نام نرم‌افزار</th>
                                        <th>نسخه</th>
                                        <th>برچسب تجهیز</th>
                                        <th>مدل</th>
                                        <th>تاریخ اتمام لایسنس</th>
                                        <th>روزهای باقی‌مانده</th>
                                        <th>عملیات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($expiringSoftware as $software): ?>
                                        <tr>
                                            <td><?= $software['software_name'] ?></td>
                                            <td><?= $software['version'] ?></td>
                                            <td><?= $software['asset_tag'] ?></td>
                                            <td><?= $software['model_name'] ?></td>
                                            <td><?= $software['expiry_date'] ?></td>
                                            <td>
                                                <span class="badge bg-warning text-dark">
                                                    <?= $software['days_remaining'] ?> روز
                                                </span>
                                            </td>
                                            <td>
                                                <a href="/support_system/assets/show/<?= $software['asset_id'] ?>" class="btn btn-sm btn-info">
                                                    <i class="bi bi-eye"></i> مشاهده تجهیز
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>