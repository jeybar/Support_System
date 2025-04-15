<?php require_once __DIR__ . '/../layouts/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <?php require_once __DIR__ . '/../layouts/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">تجهیز‌های با گارانتی رو به اتمام</h1>
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
            
            <div class="card mb-4">
                <div class="card-header">
                    <div class="row">
                        <div class="col-md-6">
                            <h5><i class="bi bi-calendar-x"></i> تجهیز‌های با گارانتی رو به اتمام</h5>
                        </div>
                        <div class="col-md-6">
                            <form action="/support_system/assets/expiring_warranty" method="GET" class="d-flex justify-content-end">
                                <div class="input-group" style="max-width: 300px;">
                                    <select name="days" class="form-select">
                                        <option value="30" <?= (isset($_GET['days']) && $_GET['days'] == 30) ? 'selected' : '' ?>>30 روز آینده</option>
                                        <option value="60" <?= (isset($_GET['days']) && $_GET['days'] == 60) ? 'selected' : '' ?>>60 روز آینده</option>
                                        <option value="90" <?= (isset($_GET['days']) && $_GET['days'] == 90) ? 'selected' : '' ?>>90 روز آینده</option>
                                        <option value="180" <?= (isset($_GET['days']) && $_GET['days'] == 180) ? 'selected' : '' ?>>6 ماه آینده</option>
                                        <option value="365" <?= (isset($_GET['days']) && $_GET['days'] == 365) ? 'selected' : '' ?>>1 سال آینده</option>
                                    </select>
                                    <button type="submit" class="btn btn-primary">اعمال</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($expiringWarranty)): ?>
                        <div class="alert alert-info">هیچ تجهیز با گارانتی رو به اتمام در بازه زمانی انتخاب شده وجود ندارد.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>برچسب تجهیز</th>
                                        <th>مدل</th>
                                        <th>دسته‌بندی</th>
                                        <th>شماره سریال</th>
                                        <th>تاریخ خرید</th>
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
                                            <td><?= $asset['serial_number'] ?></td>
                                            <td><?= $asset['purchase_date'] ?></td>
                                            <td><?= $asset['warranty_expiry_date'] ?></td>
                                            <td>
                                                <?php if ($asset['days_remaining'] <= 30): ?>
                                                    <span class="badge bg-danger"><?= $asset['days_remaining'] ?> روز</span>
                                                <?php elseif ($asset['days_remaining'] <= 90): ?>
                                                    <span class="badge bg-warning text-dark"><?= $asset['days_remaining'] ?> روز</span>
                                                <?php else: ?>
                                                    <span class="badge bg-info"><?= $asset['days_remaining'] ?> روز</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= $asset['assigned_to'] ?? 'تخصیص داده نشده' ?></td>
                                            <td>
                                                <a href="/support_system/assets/show/<?= $asset['id'] ?>" class="btn btn-sm btn-info">
                                                    <i class="bi bi-eye"></i> مشاهده
                                                </a>
                                                <a href="/support_system/assets/edit/<?= $asset['id'] ?>" class="btn btn-sm btn-primary">
                                                    <i class="bi bi-pencil"></i> ویرایش
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