<?php

$cssLink = 'dashboard.css';
$pageTitle = 'داشبورد کاربر';

include 'components/page_header.php';
include 'header.php';

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
    <!-- گزارش‌های کلی -->
    <section class="row mb-5">
        <div class="col-12 d-flex flex-wrap justify-content-center">
            <div class="stat-card-container">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <h5 class="card-title">تعداد کل درخواست‌ها</h5>
                        <p class="card-text display-6 text-info">
                            <i class="fas fa-list-alt"></i>
                            <?= htmlspecialchars($dashboardData['totalTickets'] ?? 0) ?>
                        </p>
                    </div>
                </div>
            </div>
            <div class="stat-card-container">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <h5 class="card-title">درخواست‌های باز</h5>
                        <p class="card-text display-6 text-primary">
                            <i class="fas fa-envelope-open"></i>
                            <?= htmlspecialchars($dashboardData['openTicketsCount'] ?? 0) ?>
                        </p>
                    </div>
                </div>
            </div>
            <div class="stat-card-container">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <h5 class="card-title">درخواست‌های بسته‌شده</h5>
                        <p class="card-text display-6 text-success">
                            <i class="fas fa-check-circle"></i>
                            <?= htmlspecialchars($dashboardData['resolvedTicketsCount'] ?? 0) ?>
                        </p>
                    </div>
                </div>
            </div>
            <div class="stat-card-container">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <h5 class="card-title">درخواست‌های در حال بررسی</h5>
                        <p class="card-text display-6 text-warning">
                            <i class="fas fa-spinner"></i>
                            <?= htmlspecialchars($dashboardData['inProgressTicketsCount'] ?? 0) ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- بخش جدید: تجهیز‌های تخصیص داده شده به کاربر -->
    <section class="row mb-5">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold">تجهیز‌های تخصیص داده شده به شما</h6>
                    <a href="/support_system/assets/my-assets" class="btn btn-sm btn-primary">مشاهده همه</a>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-12 text-center">
                            <div class="stat-card-container mx-auto">
                                <div class="card stat-card">
                                    <div class="card-body text-center">
                                        <h5 class="card-title">تعداد کل تجهیز‌های شما</h5>
                                        <p class="card-text display-6 text-primary">
                                            <i class="fas fa-desktop"></i> <?= htmlspecialchars($dashboardData['userAssetsCount'] ?? 0); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>برچسب</th>
                                    <th>نام</th>
                                    <th>دسته‌بندی</th>
                                    <th>وضعیت</th>
                                    <th>عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($dashboardData['userAssets'])): ?>
                                    <?php foreach ($dashboardData['userAssets'] as $asset): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($asset['asset_tag']); ?></td>
                                        <td><?= htmlspecialchars($asset['name']); ?></td>
                                        <td><?= htmlspecialchars($asset['category_name']); ?></td>
                                        <td>
                                            <?php 
                                            $statusClass = '';
                                            $statusText = '';
                                            switch ($asset['status']) {
                                                case 'ready':
                                                    $statusClass = 'bg-success text-white';
                                                    $statusText = 'آماده';
                                                    break;
                                                case 'in_use':
                                                    $statusClass = 'bg-primary text-white';
                                                    $statusText = 'در حال استفاده';
                                                    break;
                                                case 'needs_repair':
                                                    $statusClass = 'bg-warning text-dark';
                                                    $statusText = 'نیازمند تعمیر';
                                                    break;
                                                case 'out_of_service':
                                                    $statusClass = 'bg-danger text-white';
                                                    $statusText = 'خارج از سرویس';
                                                    break;
                                            }
                                            ?>
                                            <span class="badge <?= $statusClass; ?>"><?= $statusText; ?></span>
                                            <?php if ($asset['warranty_end_date'] && strtotime($asset['warranty_end_date']) <= strtotime('+30 days') && strtotime($asset['warranty_end_date']) >= strtotime('now')): ?>
                                                <span class="badge bg-danger text-white">گارانتی رو به اتمام</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="/support_system/assets/view/<?= $asset['id']; ?>" class="btn btn-info btn-sm">
                                                <i class="fas fa-eye"></i> مشاهده
                                            </a>
                                            <a href="/support_system/tickets/create?asset_id=<?= $asset['id']; ?>" class="btn btn-warning btn-sm">
                                                <i class="fas fa-ticket-alt"></i> ثبت درخواست
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center">هیچ تجهیز به شما تخصیص داده نشده است.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- بخش جدید: سرویس‌های ادواری پیش رو برای تجهیز‌های شما -->
    <section class="row mb-5">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold">سرویس‌های ادواری پیش رو برای تجهیز‌های شما</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>عنوان سرویس</th>
                                    <th>تجهیز</th>
                                    <th>نوع سرویس</th>
                                    <th>تاریخ سررسید</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($dashboardData['userUpcomingMaintenance'])): ?>
                                    <?php foreach ($dashboardData['userUpcomingMaintenance'] as $maintenance): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($maintenance['title']); ?></td>
                                        <td><?= htmlspecialchars($maintenance['asset_name']); ?></td>
                                        <td><?= htmlspecialchars($maintenance['maintenance_type']); ?></td>
                                        <td>
                                            <?php 
                                            $dueDate = strtotime($maintenance['due_date']);
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
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center">هیچ سرویس ادواری پیش رویی برای تجهیز‌های شما یافت نشد.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- نمودار وضعیت درخواست‌ها -->
    <section class="row mb-5 text-center"> <!-- وسط‌چین کردن -->
        <div class="col-lg-6 col-md-12 mx-auto"> <!-- وسط‌چین کردن بخش -->
            <h3 class="mb-3">نمودار وضعیت درخواست‌ها</h3>
            <div class="chart-container">
                <canvas id="ticketStatusChart" width="400" height="200"></canvas>
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
</main>

<!-- دکمه‌های دسترسی سریع -->
<div class="quick-access-container">
    <div class="quick-access-item">
        <a href="/support_system/tickets/create" class="btn btn-primary quick-access-btn" title="ثبت درخواست">
            <i class="bi bi-plus-circle"></i>
        </a>
    </div>
    <div class="quick-access-item">
        <a href="/support_system/tickets" class="btn btn-success quick-access-btn" title="مشاهده درخواست‌ها">
            <i class="bi bi-list-ul"></i>
        </a>
    </div>
    <div class="quick-access-item">
        <a href="/support_system/profile" class="btn btn-warning quick-access-btn" title="پروفایل">
            <i class="bi bi-person"></i>
        </a>
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

<!-- مودال ثبت درخواست -->
<?php include 'components/create_ticket_modal.php'; ?>

<!-- اسکریپت‌ها -->
<script>
    const ticketStatusData = <?= json_encode([
        'open' => $dashboardData['openTicketsCount'] ?? 0,
        'in_progress' => $dashboardData['inProgressTicketsCount'] ?? 0,
        'closed' => $dashboardData['resolvedTicketsCount'] ?? 0,
    ]) ?>;

    const ctx = document.getElementById('ticketStatusChart').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['باز', 'در حال بررسی', 'بسته'],
            datasets: [{
                data: Object.values(ticketStatusData),
                backgroundColor: [
                    '#0d6efd', // آبی (text-primary) برای درخواست‌های باز
                    '#ffc107', // زرد (text-warning) برای درخواست‌های در حال بررسی
                    '#198754', // سبز (text-success) برای درخواست‌های بسته‌شده
                ],
            }],
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom',
                },
            },
        },
    });
</script>

<?php include 'footer.php'; ?>