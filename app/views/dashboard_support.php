<?php
$cssLink = 'dashboard.css';
$pageTitle = 'داشبورد پشتیبان';

include 'components/page_header.php';
include 'header.php';

// دریافت اطلاعات پشتیبان واردشده
$support_id = $_SESSION['user_id'];
$support_name = $_SESSION['fullname'];
$support_role = $_SESSION['role_id'];
$support_plant = $_SESSION['plant'];
$support_unit = $_SESSION['unit'];
?>

<div class="container mt-4">
    <div class="row align-items-center mb-3">
        <!-- Breadcrumbs -->
        <div class="col-lg-8 col-md-6 col-sm-12">
            <?php echo generateBreadcrumbs(); ?>
        </div>

        <!-- دکمه‌ها -->
        <div class="col-lg-4 col-md-6 col-sm-12">
            <div class="d-flex justify-content-end flex-wrap align-items-center">
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createTicketModal">
                    <i class="bi bi-plus-circle"></i> ثبت درخواست کار
                </button>
            </div>
        </div>
    </div>
</div>

<main class="container mt-4">
    <div class="container">
        <!-- بخش لیست درخواست کار‌ها -->
        <section class="row mb-4">
            <div class="col-12 d-flex flex-wrap justify-content-center">
                <!-- کارت درخواست کار‌های باز -->
                <div class="stat-card-container">
                    <div class="card stat-card">
                        <div class="card-body text-center">
                            <h5 class="card-title">درخواست کار‌های باز</h5>
                            <p class="card-text display-6 text-primary">
                                <i class="fas fa-envelope-open"></i> <?= htmlspecialchars($dashboardData['openTicketsCount']) ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- کارت درخواست کار‌های در حال بررسی -->
                <div class="stat-card-container">
                    <div class="card stat-card">
                        <div class="card-body text-center">
                            <h5 class="card-title">درخواست کار‌های در حال بررسی</h5>
                            <p class="card-text display-6 text-warning">
                                <i class="fas fa-spinner"></i> <?= htmlspecialchars($dashboardData['inProgressTicketsCount']) ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- کارت درخواست کار‌های معوق -->
                <div class="stat-card-container">
                    <div class="card stat-card">
                        <div class="card-body text-center">
                            <h5 class="card-title">درخواست کار‌های معوق</h5>
                            <p class="card-text display-6 text-danger">
                                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($dashboardData['overdueTicketsCount']) ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- کارت درخواست کار‌های نیازمند پاسخ کاربر -->
                <div class="stat-card-container">
                    <div class="card stat-card">
                        <div class="card-body text-center">
                            <h5 class="card-title">نیازمند پاسخ کاربر</h5>
                            <p class="card-text display-6 text-info">
                                <i class="fas fa-user-clock"></i> <?= htmlspecialchars($userResponseNeededTicketsCount) ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- بخش گزارش‌های عملکرد -->
        <section class="row mb-4">
            <div class="col-12 d-flex flex-wrap justify-content-center">
                <!-- کارت تعداد درخواست کار‌های حل‌شده -->
                <div class="stat-card-container">
                    <div class="card stat-card">
                        <div class="card-body text-center">
                            <h5 class="card-title">درخواست کار‌های حل‌شده</h5>
                            <p class="card-text display-6 text-success">
                                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($dashboardData['resolvedTicketsCount']) ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- کارت میانگین زمان رسیدگی -->
                <div class="stat-card-container">
                    <div class="card stat-card">
                        <div class="card-body text-center">
                            <h5 class="card-title">میانگین زمان رسیدگی (دقیقه)</h5>
                            <p class="card-text display-6 text-info">
                                <i class="fas fa-clock"></i> <?= htmlspecialchars(floor($dashboardData['averageResponseTime'])) ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- کارت تعداد درخواست کار‌های ارجاع‌شده -->
                <div class="stat-card-container">
                    <div class="card stat-card">
                        <div class="card-body text-center">
                            <h5 class="card-title">درخواست کار‌های ارجاع‌شده</h5>
                            <p class="card-text display-6 text-warning">
                                <i class="fas fa-share"></i> <?= htmlspecialchars($dashboardData['referredTicketsCount']) ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <!-- بخش جدید: تجهیز‌های نیازمند سرویس -->
        <section class="row mb-5">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold">تجهیز‌های نیازمند سرویس</h6>
                        <a href="/support_system/maintenance?filter=needed" class="btn btn-sm btn-primary">مشاهده همه</a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>برچسب</th>
                                        <th>نام تجهیز</th>
                                        <th>نوع سرویس</th>
                                        <th>تاریخ سررسید</th>
                                        <th>عملیات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($dashboardData['assetsNeedingMaintenance'])): ?>
                                        <?php foreach ($dashboardData['assetsNeedingMaintenance'] as $asset): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($asset['asset_tag']); ?></td>
                                            <td><?= htmlspecialchars($asset['name']); ?></td>
                                            <td><?= htmlspecialchars($asset['maintenance_type']); ?></td>
                                            <td>
                                                <?php 
                                                $dueDate = strtotime($asset['due_date']);
                                                $today = strtotime('today');
                                                $daysLeft = floor(($dueDate - $today) / (60 * 60 * 24));
                                                
                                                echo date('Y/m/d', $dueDate);
                                                
                                                if ($daysLeft <= 7) {
                                                    echo ' <span class="badge bg-danger text-white">' . $daysLeft . ' روز مانده</span>';
                                                } else {
                                                    echo ' <span class="badge bg-info text-white">' . $daysLeft . ' روز مانده</span>';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <a href="/support_system/maintenance/perform/<?= $asset['id']; ?>" class="btn btn-success btn-sm">
                                                    <i class="fas fa-tools"></i> انجام سرویس
                                                </a>
                                                <a href="/support_system/assets/view/<?= $asset['id']; ?>" class="btn btn-info btn-sm">
                                                    <i class="fas fa-eye"></i> مشاهده تجهیز
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center">هیچ تجهیز نیازمند سرویسی یافت نشد.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- بخش جدید: آمار سرویس‌های ادواری -->
        <section class="row mb-4">
            <div class="col-12">
                <h3 class="mb-3">آمار سرویس‌های ادواری</h3>
                <div class="d-flex flex-wrap justify-content-center">
                    <div class="stat-card-container">
                        <div class="card stat-card">
                            <div class="card-body text-center">
                                <h5 class="card-title">سرویس‌های انجام شده</h5>
                                <p class="card-text display-6 text-success">
                                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($dashboardData['maintenanceStats']['completed'] ?? 0); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="stat-card-container">
                        <div class="card stat-card">
                            <div class="card-body text-center">
                                <h5 class="card-title">سرویس‌های برنامه‌ریزی شده</h5>
                                <p class="card-text display-6 text-primary">
                                    <i class="fas fa-calendar-check"></i> <?= htmlspecialchars($dashboardData['maintenanceStats']['scheduled'] ?? 0); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="stat-card-container">
                        <div class="card stat-card">
                            <div class="card-body text-center">
                                <h5 class="card-title">سرویس‌های تأخیری</h5>
                                <p class="card-text display-6 text-danger">
                                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($dashboardData['maintenanceStats']['overdue'] ?? 0); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
    
    <!-- بخش نمودارها -->
    <section class="row mb-4">
        <!-- نمودار وضعیت درخواست کار‌ها -->
        <div class="col-lg-6 col-md-12 mb-4">
            <h3 class="mb-3">نمودار وضعیت درخواست کار‌ها</h3>
            <div class="chart-container">
                <canvas id="ticketStatusChart" width="400" height="200"></canvas>
            </div>
        </div>

        <!-- نمودار زمانی تعداد درخواست کار‌های حل‌شده -->
        <div class="col-lg-6 col-md-12 mb-4">
            <h3 class="mb-3">نمودار زمانی تعداد درخواست کار‌های حل‌شده</h3>
            <div class="chart-container">
                <canvas id="ticketSolvedTimeChart" width="400" height="200"></canvas>
            </div>
        </div>
    </section>

    <!-- تغییرات اخیر -->
    <section class="row mb-5 text-center">
        <div class="col-12 mx-auto">
            <h2>تغییرات اخیر در وضعیت درخواست‌ها</h2>
            <table class="table table-striped">
                <thead class="table-header-custom">
                    <tr>
                        <th>ردیف</th>
                        <th>عنوان درخواست</th>
                        <th>نام پشتیبان</th> <!-- ستون جدید -->
                        <th>وضعیت قبلی</th>
                        <th>وضعیت جدید</th>
                        <th>زمان تغییر</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($dashboardData['recentStatusChanges'])): ?>
                        <?php foreach ($dashboardData['recentStatusChanges'] as $index => $change): ?>
                            <tr>
                                <td><?= $index + 1; ?></td>
                                <td><?= htmlspecialchars($change['title']); ?></td>
                                <td><?= htmlspecialchars($change['support_name'] ?? 'نامشخص'); ?></td> <!-- نمایش نام پشتیبان -->
                                <td><?= htmlspecialchars($change['old_status']); ?></td>
                                <td><?= htmlspecialchars($change['new_status']); ?></td>
                                <td><?= htmlspecialchars($change['changed_at']); ?></td>
                                <td>
                                    <!-- کلید نمایش درخواست -->
                                    <a href="/support_system/tickets/view/<?= htmlspecialchars($change['ticket_id'] ?? ''); ?>" class="btn btn-info btn-sm">
                                        نمایش درخواست
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center">هیچ تغییری ثبت نشده است.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
    
    <!-- بخش دسترسی سریع -->
    <div class="quick-access-container">
        <div class="quick-access-item">
            <a href="#" class="btn btn-primary quick-access-btn" title="مشاهده درخواست کار">
                <i class="bi bi-eye"></i>
            </a>
        </div>
        <div class="quick-access-item">
            <a href="#" class="btn btn-success quick-access-btn" title="ارجاع درخواست کار">
                <i class="bi bi-arrow-right"></i>
            </a>
        </div>
        <div class="quick-access-item">
            <a href="#" class="btn btn-warning quick-access-btn" title="ثبت اقدام">
                <i class="bi bi-pencil"></i>
            </a>
        </div>
    </div>
</div>
</div>

<!-- مدال برای نمایش پیام -->
<?php include 'components/show_message_modal.php'; ?>

<!-- اسکریپت برای نمایش خودکار مدال -->
<?php if ($successMessage || $errorMessage): ?>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            var messageModal = new bootstrap.Modal(document.getElementById('messageModal'));
            messageModal.show(); // نمایش مدال
        });
    </script>
<?php endif; ?>

<!-- مودال ثبت درخواست کار -->
<?php include 'components/create_ticket_modal.php'; ?>

<!-- لینک فایل جاوااسکریپت مشترک -->
<script src="/assets/js/dashboard_common.js"></script>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<!-- ارسال داده‌های نمودارها به صورت JSON -->
<script id="ticketStatusData" type="application/json">
    <?= json_encode($ticketStatusCounts); ?>
</script>

<script id="ticketCountsByDate" type="application/json">
    <?= json_encode($ticketCountsByDate); ?>
</script>

<?php include 'footer.php'; ?>