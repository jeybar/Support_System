<?php

include 'components/page_header.php';

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
        <!-- گزارش‌های کلی -->
        <section class="row mb-4">
            <div class="col-12 d-flex flex-wrap justify-content-center">
                <div class="stat-card-container">
                    <div class="card stat-card">
                        <div class="card-body text-center">
                            <h5 class="card-title">درخواست کار‌های باز</h5>
                            <p class="card-text display-6 text-primary">
                                <i class="fas fa-envelope-open"></i> <?= (int)($dashboardData['openTicketsCount'] ?? 0); ?>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="stat-card-container">
                    <div class="card stat-card">
                        <div class="card-body text-center">
                            <h5 class="card-title">درخواست کار‌های بسته‌شده</h5>
                            <p class="card-text display-6 text-success">
                                <i class="fas fa-check-circle"></i> <?= (int)($dashboardData['resolvedTicketsCount'] ?? 0); ?>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="stat-card-container">
                    <div class="card stat-card">
                        <div class="card-body text-center">
                            <h5 class="card-title">درخواست کار‌های در حال بررسی</h5>
                            <p class="card-text display-6 text-warning">
                                <i class="fas fa-spinner"></i> <?= (int)($dashboardData['inProgressTicketsCount'] ?? 0); ?>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="stat-card-container">
                    <div class="card stat-card">
                        <div class="card-body text-center">
                            <h5 class="card-title">میانگین زمان رسیدگی (دقیقه)</h5>                            <p class="card-text display-6 text-info">
                                <i class="fas fa-clock"></i> <?= htmlspecialchars((int)$dashboardData['averageResponseTime'] ?? 0); ?>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="stat-card-container">
                    <div class="card stat-card">
                        <div class="card-body text-center">
                            <h5 class="card-title">کل درخواست کار‌های معوق</h5>
                            <p class="card-text display-6 text-danger">
                                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($dashboardData['totalOverdueTickets'] ?? 0); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <!-- نمایش آمار درخواست کار‌های اولویت‌دار -->
        <section class="row mb-4">
            <!-- تعداد درخواست کار‌ها بر اساس اولویت -->
            <div class="col-lg-6 col-md-12 mb-4">
                <h3 class="mb-3">تعداد درخواست کار‌ها بر اساس اولویت</h3>
                <div class="row">
                    <?php
                    $priorities = [
                        'normal' => ['label' => 'عادی', 'color' => 'text-primary', 'icon' => 'fas fa-circle'],
                        'urgent' => ['label' => 'فوری', 'color' => 'text-warning', 'icon' => 'fas fa-exclamation-triangle'],
                        'critical' => ['label' => 'بحرانی', 'color' => 'text-danger', 'icon' => 'fas fa-bomb']
                    ];
                    foreach ($priorities as $key => $priority): ?>
                        <div class="col-md-4 col-sm-6 mb-3">
                            <div class="card stat-card">
                                <div class="card-body text-center">
                                    <h5 class="card-title"><?= $priority['label'] ?></h5>
                                    <p class="card-text display-6 <?= $priority['color'] ?>">
                                        <i class="<?= $priority['icon'] ?>"></i> <?= $ticketsByPriority[$key] ?? 0; ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- وضعیت کاربران -->
            <div class="col-lg-6 col-md-12 mb-4">
                <h3 class="mb-3">وضعیت کاربران</h3>
                <div class="row">
                    <div class="col-md-6 col-sm-12 mb-3">
                        <div class="card stat-card">
                            <div class="card-body text-center">
                                <h5 class="card-title">کاربران فعال</h5>
                                <p class="card-text display-6 text-success">
                                    <i class="fas fa-user-check"></i> <?= $userStatus['active'] ?? 0; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-sm-12 mb-3">
                        <div class="card stat-card">
                            <div class="card-body text-center">
                                <h5 class="card-title">کاربران غیرفعال</h5>
                                <p class="card-text display-6 text-danger">
                                    <i class="fas fa-user-times"></i> <?= $userStatus['inactive'] ?? 0; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        
        <!-- آمار تجهیز‌های سخت‌افزاری -->
        <section class="row mb-4">
            <div class="col-12">
                <h3 class="mb-3">وضعیت تجهیز‌های سخت‌افزاری</h3>
                <div class="d-flex flex-wrap justify-content-center">
                    <div class="stat-card-container">
                        <div class="card stat-card">
                            <div class="card-body text-center">
                                <h5 class="card-title">کل تجهیز‌ها</h5>
                                <p class="card-text display-6 text-primary">
                                    <i class="fas fa-desktop"></i> <?= htmlspecialchars($dashboardData['assetStats']['total'] ?? 0); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="stat-card-container">
                        <div class="card stat-card">
                            <div class="card-body text-center">
                                <h5 class="card-title">تخصیص داده شده</h5>
                                <p class="card-text display-6 text-success">
                                    <i class="fas fa-user-check"></i> <?= htmlspecialchars($dashboardData['assetStats']['assigned'] ?? 0); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="stat-card-container">
                        <div class="card stat-card">
                            <div class="card-body text-center">
                                <h5 class="card-title">بدون تخصیص</h5>
                                <p class="card-text display-6 text-info">
                                    <i class="fas fa-box-open"></i> <?= htmlspecialchars($dashboardData['assetStats']['unassigned'] ?? 0); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="stat-card-container">
                        <div class="card stat-card">
                            <div class="card-body text-center">
                                <h5 class="card-title">نیازمند سرویس</h5>
                                <p class="card-text display-6 text-warning">
                                    <i class="fas fa-tools"></i> <?= htmlspecialchars($dashboardData['assetStats']['maintenance_needed'] ?? 0); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="stat-card-container">
                        <div class="card stat-card">
                            <div class="card-body text-center">
                                <h5 class="card-title">گارانتی رو به اتمام</h5>
                                <p class="card-text display-6 text-danger">
                                    <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($dashboardData['assetStats']['warranty_expiring'] ?? 0); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!--نمودارهای تجهیز‌ها -->
        <section class="row mb-4">
            <!-- نمودار توزیع تجهیز‌ها بر اساس دسته‌بندی -->
            <div class="col-lg-6 col-md-12 mb-4">
                <h3 class="mb-3">توزیع تجهیز‌ها بر اساس دسته‌بندی</h3>
                <div class="chart-container">
                    <canvas id="assetCategoryChart" width="400" height="200"></canvas>
                </div>
            </div>

            <!-- نمودار توزیع تجهیز‌ها بر اساس وضعیت -->
            <div class="col-lg-6 col-md-12 mb-4">
                <h3 class="mb-3">توزیع تجهیز‌ها بر اساس وضعیت</h3>
                <div class="chart-container">
                    <canvas id="assetStatusChart" width="400" height="200"></canvas>
                </div>
            </div>
        </section>

        <!-- تجهیز‌های نیازمند توجه -->
        <section class="row mb-5">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold">تجهیز‌های نیازمند توجه</h6>
                        <a href="/support_system/assets?filter=attention" class="btn btn-sm btn-primary">مشاهده همه</a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>برچسب</th>
                                        <th>نام</th>
                                        <th>دسته‌بندی</th>
                                        <th>وضعیت</th>
                                        <th>تخصیص به</th>
                                        <th>عملیات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($dashboardData['assetsNeedingAttention'])): ?>
                                        <?php foreach ($dashboardData['assetsNeedingAttention'] as $asset): ?>
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
                                                <?php if ($asset['warranty_end_date'] && strtotime($asset['warranty_end_date']) <= strtotime('+30 days')): ?>
                                                    <span class="badge bg-danger text-white">گارانتی رو به اتمام</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($asset['assigned_to'] ?? 'تخصیص نیافته'); ?></td>
                                            <td>
                                                <a href="/support_system/assets/view/<?= $asset['id']; ?>" class="btn btn-info btn-sm">
                                                    <i class="fas fa-eye"></i> مشاهده
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center">هیچ تجهیز نیازمند توجهی یافت نشد.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- بخش جدید: سرویس‌های ادواری پیش رو -->
        <section class="row mb-5">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold">سرویس‌های ادواری پیش رو</h6>
                        <a href="/support_system/maintenance" class="btn btn-sm btn-primary">مشاهده همه</a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>عنوان</th>
                                        <th>تجهیز</th>
                                        <th>نوع سرویس</th>
                                        <th>تاریخ سررسید</th>
                                        <th>عملیات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($dashboardData['upcomingMaintenance'])): ?>
                                        <?php foreach ($dashboardData['upcomingMaintenance'] as $maintenance): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($maintenance['title']); ?></td>
                                            <td><?= htmlspecialchars($maintenance['asset_name']); ?> (<?= htmlspecialchars($maintenance['asset_tag']); ?>)</td>
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
                                            <td>
                                                <a href="/support_system/maintenance/view/<?= $maintenance['id']; ?>" class="btn btn-info btn-sm">
                                                    <i class="fas fa-eye"></i> مشاهده
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center">هیچ سرویس ادواری پیش رویی یافت نشد.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- نمودارها -->
        <section class="row mb-4">
            <!-- نمودار وضعیت درخواست کار‌ها -->
            <div class="col-lg-6 col-md-12 mb-4">
                <h3 class="mb-3">نمودار وضعیت درخواست کار‌ها</h3>
                <div class="chart-container">
                    <canvas id="ticketStatusChart" width="400" height="200"></canvas>
                </div>
            </div>

            <!-- نمودار زمانی تعداد درخواست کار‌ها -->
            <div class="col-lg-6 col-md-12 mb-4">
                <h3 class="mb-3">نمودار زمانی تعداد درخواست کار‌ها</h3>
                <div class="chart-container">
                    <canvas id="ticketTimeChart" width="400" height="200"></canvas>
                </div>
            </div>
        </section>
        <!-- گزارش‌های عملکرد تیم پشتیبانی -->
        <section class="performance-report-section mb-5">
            <div class="section-header">
                <h3>گزارش عملکرد تیم پشتیبانی</h3>
                <div class="filter-toggle-wrapper">
                    <button class="filter-toggle-btn" type="button" data-bs-toggle="collapse" data-bs-target="#filterPanel" aria-expanded="false">
                        <span class="filter-icon"><i class="fas fa-sliders-h"></i></span>
                        <span class="filter-text">فیلتر و گزارش‌گیری</span>
                    </button>
                </div>
            </div>
            
            <!-- پنل فیلتر - طراحی افقی با تب‌ها -->
            <div class="collapse filter-panel-wrapper mb-4" id="filterPanel">
                <div class="filter-panel">
                    <div class="filter-panel-header">
                        <ul class="nav nav-tabs filter-tabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="time-tab" data-bs-toggle="tab" data-bs-target="#time-content" type="button" role="tab" aria-selected="true">
                                    <i class="fas fa-calendar-alt me-1"></i> بازه زمانی
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="support-tab" data-bs-toggle="tab" data-bs-target="#support-content" type="button" role="tab" aria-selected="false">
                                    <i class="fas fa-user-headset me-1"></i> پشتیبان
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="performance-tab" data-bs-toggle="tab" data-bs-target="#performance-content" type="button" role="tab" aria-selected="false">
                                    <i class="fas fa-chart-line me-1"></i> معیارهای عملکرد
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="sort-tab" data-bs-toggle="tab" data-bs-target="#sort-content" type="button" role="tab" aria-selected="false">
                                    <i class="fas fa-sort me-1"></i> مرتب‌سازی
                                </button>
                            </li>
                        </ul>
                        <div class="filter-actions">
                            <button type="button" id="reset-filter" class="btn btn-light btn-sm" title="پاک کردن فیلترها">
                                <i class="fas fa-undo"></i>
                            </button>
                            <button type="button" class="btn btn-light btn-sm" data-bs-toggle="collapse" data-bs-target="#filterPanel" title="بستن">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="filter-panel-body">
                        <form id="support-performance-filter" method="GET" action="/support_system/dashboard/admin">
                            <div class="tab-content">
                                <!-- تب بازه زمانی -->
                                <div class="tab-pane fade show active" id="time-content" role="tabpanel" aria-labelledby="time-tab">
                                    <div class="time-range-options">
                                        <div class="time-presets">
                                            <h6 class="filter-subtitle">بازه‌های زمانی پیش‌فرض</h6>
                                            <div class="time-preset-buttons">
                                                <button type="button" class="time-preset-btn <?= (isset($_GET['time_range']) && $_GET['time_range'] === 'daily') ? 'active' : ''; ?>" data-value="daily">
                                                    <i class="fas fa-calendar-day"></i>
                                                    <span>امروز</span>
                                                </button>
                                                <button type="button" class="time-preset-btn <?= (isset($_GET['time_range']) && $_GET['time_range'] === 'weekly') ? 'active' : ''; ?>" data-value="weekly">
                                                    <i class="fas fa-calendar-week"></i>
                                                    <span>هفته جاری</span>
                                                </button>
                                                <button type="button" class="time-preset-btn <?= (isset($_GET['time_range']) && $_GET['time_range'] === 'monthly') ? 'active' : ''; ?>" data-value="monthly">
                                                    <i class="fas fa-calendar-alt"></i>
                                                    <span>ماه جاری</span>
                                                </button>
                                                <button type="button" class="time-preset-btn <?= (isset($_GET['time_range']) && $_GET['time_range'] === 'quarterly') ? 'active' : ''; ?>" data-value="quarterly">
                                                    <i class="fas fa-calendar-alt"></i>
                                                    <span>سه ماهه اخیر</span>
                                                </button>
                                                <button type="button" class="time-preset-btn <?= (isset($_GET['time_range']) && $_GET['time_range'] === 'biannually') ? 'active' : ''; ?>" data-value="biannually">
                                                    <i class="fas fa-calendar-alt"></i>
                                                    <span>شش ماهه اخیر</span>
                                                </button>
                                                <button type="button" class="time-preset-btn <?= (isset($_GET['time_range']) && $_GET['time_range'] === 'annually') ? 'active' : ''; ?>" data-value="annually">
                                                    <i class="fas fa-calendar-alt"></i>
                                                    <span>یک سال اخیر</span>
                                                </button>
                                            </div>
                                            <input type="hidden" id="time_range" name="time_range" value="<?= htmlspecialchars($_GET['time_range'] ?? ''); ?>">
                                        </div>
                                        
                                        <div class="time-custom mt-4">
                                            <h6 class="filter-subtitle">بازه زمانی دلخواه</h6>
                                            <div class="custom-date-range-picker">
                                                <div class="date-range-inputs">
                                                    <div class="input-group">
                                                        <span class="input-group-text"><i class="fas fa-calendar-minus"></i></span>
                                                        <input type="date" id="start_date" name="start_date" class="form-control" placeholder="تاریخ شروع" 
                                                            value="<?php echo htmlspecialchars($_GET['start_date'] ?? ''); ?>">
                                                    </div>
                                                    <div class="date-range-separator">تا</div>
                                                    <div class="input-group">
                                                        <span class="input-group-text"><i class="fas fa-calendar-plus"></i></span>
                                                        <input type="date" id="end_date" name="end_date" class="form-control" placeholder="تاریخ پایان" 
                                                            value="<?php echo htmlspecialchars($_GET['end_date'] ?? ''); ?>">
                                                    </div>
                                                </div>
                                                <button type="button" class="btn btn-outline-primary btn-sm set-custom-date-btn">
                                                    <i class="fas fa-check me-1"></i> اعمال بازه دلخواه
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- تب پشتیبان -->
                                <div class="tab-pane fade" id="support-content" role="tabpanel" aria-labelledby="support-tab">
                                    <div class="support-filter-options">
                                        <div class="row">
                                            <div class="col-lg-6">
                                                <h6 class="filter-subtitle">انتخاب پشتیبان</h6>
                                                <div class="support-selector">
                                                    <?php if (!empty($supportNames)): ?>
                                                        <?php foreach ($supportNames as $index => $support): ?>
                                                            <div class="support-option">
                                                                <input type="radio" class="btn-check" name="support_name" id="support_<?= $support['id']; ?>" value="<?= htmlspecialchars($support['id']); ?>" 
                                                                    <?= (isset($_GET['support_name']) && $_GET['support_name'] == $support['id']) ? 'checked' : ''; ?>>
                                                                <label class="support-option-label" for="support_<?= $support['id']; ?>">
                                                                    <div class="support-avatar">
                                                                        <img src="<?= !empty($support['avatar']) ? htmlspecialchars($support['avatar']) : '/assets/images/default-avatar.png'; ?>" 
                                                                            alt="<?= htmlspecialchars($support['name']); ?>">
                                                                    </div>
                                                                    <div class="support-info">
                                                                        <div class="support-name"><?= htmlspecialchars($support['name']); ?></div>
                                                                        <div class="support-role"><?= htmlspecialchars($support['role'] ?? 'پشتیبان'); ?></div>
                                                                    </div>
                                                                </label>
                                                            </div>
                                                        <?php endforeach; ?>
                                                        <div class="support-option">
                                                            <input type="radio" class="btn-check" name="support_name" id="support_all" value="" 
                                                                <?= (!isset($_GET['support_name']) || $_GET['support_name'] === '') ? 'checked' : ''; ?>>
                                                            <label class="support-option-label" for="support_all">
                                                                <div class="support-avatar">
                                                                    <i class="fas fa-users"></i>
                                                                </div>
                                                                <div class="support-info">
                                                                    <div class="support-name">همه پشتیبان‌ها</div>
                                                                    <div class="support-role">نمایش همه</div>
                                                                </div>
                                                            </label>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="alert alert-info">هیچ پشتیبانی یافت نشد.</div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="col-lg-6">
                                                <h6 class="filter-subtitle">گروه‌بندی پشتیبان‌ها</h6>
                                                <div class="support-group-options">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="group_by_department" name="group_by_department" value="1"
                                                            <?= (isset($_GET['group_by_department']) && $_GET['group_by_department'] == '1') ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="group_by_department">
                                                            گروه‌بندی بر اساس دپارتمان
                                                        </label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="group_by_role" name="group_by_role" value="1"
                                                            <?= (isset($_GET['group_by_role']) && $_GET['group_by_role'] == '1') ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="group_by_role">
                                                            گروه‌بندی بر اساس نقش
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- تب معیارهای عملکرد -->
                                <div class="tab-pane fade" id="performance-content" role="tabpanel" aria-labelledby="performance-tab">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h6 class="filter-subtitle">معیارهای زمان رسیدگی</h6>
                                            <div class="mb-3">
                                                <label for="resolution_time_range" class="form-label">محدوده زمان رسیدگی (دقیقه):</label>
                                                <div class="dual-range-slider">
                                                    <div class="range-values">
                                                        <span id="min_resolution_time_display">0</span>
                                                        <span>تا</span>
                                                        <span id="max_resolution_time_display">240</span>
                                                        <span>دقیقه</span>
                                                    </div>
                                                    <div class="range-inputs">
                                                        <input type="range" id="min_resolution_time" name="min_resolution_time" min="0" max="240" step="5" 
                                                            value="<?php echo htmlspecialchars($_GET['min_resolution_time'] ?? '0'); ?>" class="range-min">
                                                        <input type="range" id="max_resolution_time" name="max_resolution_time" min="0" max="240" step="5" 
                                                            value="<?php echo htmlspecialchars($_GET['max_resolution_time'] ?? '240'); ?>" class="range-max">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <label for="min_resolved" class="form-label">حداقل تعداد درخواست‌های حل‌شده:</label>
                                                <input type="number" id="min_resolved" name="min_resolved" class="form-control" min="0" 
                                                    value="<?php echo htmlspecialchars($_GET['min_resolved'] ?? ''); ?>" placeholder="تعداد">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <h6 class="filter-subtitle">معیارهای رضایت کاربران</h6>
                                            <div class="mb-3">
                                                <label for="satisfaction_range" class="form-label">محدوده رضایت کاربران (درصد):</label>
                                                <div class="satisfaction-slider">
                                                    <input type="range" id="min_satisfaction" name="min_satisfaction" min="0" max="100" step="5" 
                                                        value="<?php echo htmlspecialchars($_GET['min_satisfaction'] ?? '0'); ?>" class="form-range">
                                                    <div class="satisfaction-value">
                                                        <div class="satisfaction-icon">
                                                            <i class="fas fa-smile"></i>
                                                        </div>
                                                        <div class="satisfaction-percent">
                                                            <span id="satisfaction_display"><?php echo htmlspecialchars($_GET['min_satisfaction'] ?? '0'); ?></span>%
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <label for="max_remaining" class="form-label">حداکثر تعداد درخواست‌های باقی‌مانده:</label>
                                                <input type="number" id="max_remaining" name="max_remaining" class="form-control" min="0" 
                                                    value="<?php echo htmlspecialchars($_GET['max_remaining'] ?? ''); ?>" placeholder="تعداد">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row mt-3">
                                        <div class="col-12">
                                            <h6 class="filter-subtitle">معیارهای نمایش</h6>
                                            <div class="performance-metrics-selector">
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="checkbox" id="show_assigned" name="show_metrics[]" value="assigned" 
                                                        <?= (isset($_GET['show_metrics']) && in_array('assigned', $_GET['show_metrics'])) ? 'checked' : ''; ?> checked>
                                                    <label class="form-check-label" for="show_assigned">درخواست‌های اختصاص‌یافته</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="checkbox" id="show_resolution_time" name="show_metrics[]" value="resolution_time" 
                                                        <?= (isset($_GET['show_metrics']) && in_array('resolution_time', $_GET['show_metrics'])) ? 'checked' : ''; ?> checked>
                                                    <label class="form-check-label" for="show_resolution_time">میانگین زمان رسیدگی</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="checkbox" id="show_resolved" name="show_metrics[]" value="resolved" 
                                                        <?= (isset($_GET['show_metrics']) && in_array('resolved', $_GET['show_metrics'])) ? 'checked' : ''; ?> checked>
                                                    <label class="form-check-label" for="show_resolved">درخواست‌های حل‌شده</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="checkbox" id="show_remaining" name="show_metrics[]" value="remaining" 
                                                        <?= (isset($_GET['show_metrics']) && in_array('remaining', $_GET['show_metrics'])) ? 'checked' : ''; ?> checked>
                                                    <label class="form-check-label" for="show_remaining">درخواست‌های باقی‌مانده</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="checkbox" id="show_satisfaction" name="show_metrics[]" value="satisfaction" 
                                                        <?= (isset($_GET['show_metrics']) && in_array('satisfaction', $_GET['show_metrics'])) ? 'checked' : ''; ?> checked>
                                                    <label class="form-check-label" for="show_satisfaction">میزان رضایت کاربران</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- تب مرتب‌سازی -->
                                <div class="tab-pane fade" id="sort-content" role="tabpanel" aria-labelledby="sort-tab">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h6 class="filter-subtitle">مرتب‌سازی نتایج</h6>
                                            <div class="sort-options">
                                                <div class="sort-option">
                                                    <input type="radio" class="btn-check" name="sort_by" id="sort_assigned_tickets" value="assigned_tickets" 
                                                        <?= (!isset($_GET['sort_by']) || $_GET['sort_by'] === 'assigned_tickets') ? 'checked' : ''; ?>>
                                                    <label class="btn btn-outline-secondary" for="sort_assigned_tickets">
                                                        <i class="fas fa-ticket-alt me-1"></i> تعداد درخواست‌های اختصاص‌یافته
                                                    </label>
                                                </div>
                                                <div class="sort-option">
                                                    <input type="radio" class="btn-check" name="sort_by" id="sort_avg_resolution_time" value="avg_resolution_time" 
                                                        <?= (isset($_GET['sort_by']) && $_GET['sort_by'] === 'avg_resolution_time') ? 'checked' : ''; ?>>
                                                    <label class="btn btn-outline-secondary" for="sort_avg_resolution_time">
                                                        <i class="fas fa-clock me-1"></i> میانگین زمان رسیدگی
                                                    </label>
                                                </div>
                                                <div class="sort-option">
                                                    <input type="radio" class="btn-check" name="sort_by" id="sort_resolved_tickets" value="resolved_tickets" 
                                                        <?= (isset($_GET['sort_by']) && $_GET['sort_by'] === 'resolved_tickets') ? 'checked' : ''; ?>>
                                                    <label class="btn btn-outline-secondary" for="sort_resolved_tickets">
                                                        <i class="fas fa-check-circle me-1"></i> تعداد درخواست‌های حل‌شده
                                                    </label>
                                                </div>
                                                <div class="sort-option">
                                                    <input type="radio" class="btn-check" name="sort_by" id="sort_remaining_tickets" value="remaining_tickets" 
                                                        <?= (isset($_GET['sort_by']) && $_GET['sort_by'] === 'remaining_tickets') ? 'checked' : ''; ?>>
                                                    <label class="btn btn-outline-secondary" for="sort_remaining_tickets">
                                                        <i class="fas fa-tasks me-1"></i> تعداد درخواست‌های باقی‌مانده
                                                    </label>
                                                </div>
                                                <div class="sort-option">
                                                    <input type="radio" class="btn-check" name="sort_by" id="sort_avg_user_satisfaction" value="avg_user_satisfaction" 
                                                        <?= (isset($_GET['sort_by']) && $_GET['sort_by'] === 'avg_user_satisfaction') ? 'checked' : ''; ?>>
                                                    <label class="btn btn-outline-secondary" for="sort_avg_user_satisfaction">
                                                        <i class="fas fa-smile me-1"></i> میزان رضایت کاربران
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <h6 class="filter-subtitle">ترتیب مرتب‌سازی</h6>
                                            <div class="sort-direction">
                                                <div class="sort-direction-option">
                                                    <input type="radio" class="btn-check" name="sort_order" id="sort_desc" value="desc" 
                                                        <?= (!isset($_GET['sort_order']) || $_GET['sort_order'] === 'desc') ? 'checked' : ''; ?>>
                                                    <label class="sort-direction-label" for="sort_desc">
                                                        <div class="sort-icon">
                                                            <i class="fas fa-sort-amount-down"></i>
                                                        </div>
                                                        <div class="sort-info">
                                                            <div class="sort-name">نزولی</div>
                                                            <div class="sort-desc">از بیشترین به کمترین</div>
                                                        </div>
                                                    </label>
                                                </div>
                                                <div class="sort-direction-option">
                                                    <input type="radio" class="btn-check" name="sort_order" id="sort_asc" value="asc" 
                                                        <?= (isset($_GET['sort_order']) && $_GET['sort_order'] === 'asc') ? 'checked' : ''; ?>>
                                                    <label class="sort-direction-label" for="sort_asc">
                                                        <div class="sort-icon">
                                                            <i class="fas fa-sort-amount-up"></i>
                                                        </div>
                                                        <div class="sort-info">
                                                            <div class="sort-name">صعودی</div>
                                                            <div class="sort-desc">از کمترین به بیشترین</div>
                                                        </div>
                                                    </label>
                                                </div>
                                            </div>
                                            
                                            <h6 class="filter-subtitle mt-4">خروجی گزارش</h6>
                                            <div class="export-options">
                                                <button type="button" id="export-excel" class="export-btn">
                                                    <i class="fas fa-file-excel"></i>
                                                    <span>خروجی اکسل</span>
                                                </button>
                                                <button type="button" id="export-pdf" class="export-btn">
                                                    <i class="fas fa-file-pdf"></i>
                                                    <span>خروجی PDF</span>
                                                </button>
                                                <button type="button" id="export-print" class="export-btn">
                                                    <i class="fas fa-print"></i>
                                                    <span>چاپ گزارش</span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="filter-footer">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search me-1"></i> اعمال فیلترها
                                </button>
                                <button type="button" id="reset-all-filters" class="btn btn-outline-secondary">
                                    <i class="fas fa-undo me-1"></i> پاک کردن همه فیلترها
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- نمایش فیلترهای فعال -->
            <?php if (isset($_GET['time_range']) || isset($_GET['support_name']) || isset($_GET['min_satisfaction']) || isset($_GET['min_resolved'])): ?>
                <div class="active-filters-bar mb-4">
                    <div class="active-filters-header">
                        <i class="fas fa-filter"></i>
                        <span>فیلترهای فعال</span>
                    </div>
                    <div class="active-filters-content">
                        <?php if (isset($_GET['time_range']) && $_GET['time_range']): ?>
                            <div class="active-filter">
                                <span class="filter-label">بازه زمانی:</span>
                                <span class="filter-value">
                                    <?php
                                    switch ($_GET['time_range']) {
                                        case 'daily':
                                            echo 'روزانه';
                                            break;
                                        case 'weekly':
                                            echo 'هفتگی';
                                            break;
                                        case 'monthly':
                                            echo 'ماهانه';
                                            break;
                                        case 'quarterly':
                                            echo 'سه ماهه';
                                            break;
                                        case 'biannually':
                                            echo 'شش ماهه';
                                            break;
                                        case 'annually':
                                            echo 'سالانه';
                                            break;
                                        case 'custom':
                                            echo 'بازه دلخواه: ' . (isset($_GET['start_date']) ? $_GET['start_date'] : '---') . ' تا ' . (isset($_GET['end_date']) ? $_GET['end_date'] : '---');
                                            break;
                                        default:
                                            echo 'همه زمان‌ها';
                                    }
                                    ?>
                                </span>
                                <a href="<?= removeQueryParam('time_range'); ?>" class="filter-remove" title="حذف این فیلتر">
                                    <i class="fas fa-times"></i>
                                </a>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($_GET['support_name']) && $_GET['support_name']): ?>
                            <div class="active-filter">
                                <span class="filter-label">پشتیبان:</span>
                                <span class="filter-value">
                                    <?php
                                    foreach ($supportNames as $support) {
                                        if ($support['id'] == $_GET['support_name']) {
                                            echo htmlspecialchars($support['name']);
                                            break;
                                        }
                                    }
                                    ?>
                                </span>
                                <a href="<?= removeQueryParam('support_name'); ?>" class="filter-remove" title="حذف این فیلتر">
                                    <i class="fas fa-times"></i>
                                </a>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($_GET['min_satisfaction']) && $_GET['min_satisfaction'] > 0): ?>
                            <div class="active-filter">
                                <span class="filter-label">حداقل رضایت:</span>
                                <span class="filter-value"><?= htmlspecialchars($_GET['min_satisfaction']); ?>%</span>
                                <a href="<?= removeQueryParam('min_satisfaction'); ?>" class="filter-remove" title="حذف این فیلتر">
                                    <i class="fas fa-times"></i>
                                </a>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($_GET['min_resolved']) && $_GET['min_resolved'] > 0): ?>
                            <div class="active-filter">
                                <span class="filter-label">حداقل درخواست‌های حل‌شده:</span>
                                <span class="filter-value"><?= htmlspecialchars($_GET['min_resolved']); ?></span>
                                <a href="<?= removeQueryParam('min_resolved'); ?>" class="filter-remove" title="حذف این فیلتر">
                                    <i class="fas fa-times"></i>
                                </a>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($_GET['min_resolution_time']) && $_GET['min_resolution_time'] > 0): ?>
                            <div class="active-filter">
                                <span class="filter-label">حداقل زمان رسیدگی:</span>
                                <span class="filter-value"><?= htmlspecialchars($_GET['min_resolution_time']); ?> دقیقه</span>
                                <a href="<?= removeQueryParam('min_resolution_time'); ?>" class="filter-remove" title="حذف این فیلتر">
                                    <i class="fas fa-times"></i>
                                </a>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($_GET['max_resolution_time']) && $_GET['max_resolution_time'] < 240): ?>
                            <div class="active-filter">
                                <span class="filter-label">حداکثر زمان رسیدگی:</span>
                                <span class="filter-value"><?= htmlspecialchars($_GET['max_resolution_time']); ?> دقیقه</span>
                                <a href="<?= removeQueryParam('max_resolution_time'); ?>" class="filter-remove" title="حذف این فیلتر">
                                    <i class="fas fa-times"></i>
                                </a>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($_GET['max_remaining']) && $_GET['max_remaining'] > 0): ?>
                            <div class="active-filter">
                                <span class="filter-label">حداکثر درخواست‌های باقی‌مانده:</span>
                                <span class="filter-value"><?= htmlspecialchars($_GET['max_remaining']); ?></span>
                                <a href="<?= removeQueryParam('max_remaining'); ?>" class="filter-remove" title="حذف این فیلتر">
                                    <i class="fas fa-times"></i>
                                </a>
                            </div>
                        <?php endif; ?>
                        
                        <div class="active-filters-actions">
                            <a href="/support_system/dashboard/admin" class="btn btn-sm btn-outline-danger">
                                <i class="fas fa-times me-1"></i> حذف همه فیلترها
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- نمایش اطلاعات آماری کلی -->
            <div class="stats-summary mb-4">
                <div class="stats-row">
                    <div class="stat-item">
                        <div class="stat-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value"><?= round($performanceSummary['avg_resolution_time'] ?? 0, 2); ?></div>
                            <div class="stat-label">میانگین زمان رسیدگی (دقیقه)</div>
                        </div>
                        <div class="stat-trend <?= ($performanceSummary['resolution_time_trend'] ?? 0) < 0 ? 'trend-down' : 'trend-up'; ?>">
                            <i class="fas fa-<?= ($performanceSummary['resolution_time_trend'] ?? 0) < 0 ? 'arrow-down' : 'arrow-up'; ?>"></i>
                            <span><?= abs(round($performanceSummary['resolution_time_trend'] ?? 0, 1)); ?>%</span>
                        </div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-icon">
                            <i class="fas fa-smile"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value"><?= round($performanceSummary['avg_user_satisfaction'] ?? 0, 2); ?>%</div>
                            <div class="stat-label">میانگین رضایت کاربران</div>
                        </div>
                        <div class="stat-trend <?= ($performanceSummary['satisfaction_trend'] ?? 0) > 0 ? 'trend-up' : 'trend-down'; ?>">
                            <i class="fas fa-<?= ($performanceSummary['satisfaction_trend'] ?? 0) > 0 ? 'arrow-up' : 'arrow-down'; ?>"></i>
                            <span><?= abs(round($performanceSummary['satisfaction_trend'] ?? 0, 1)); ?>%</span>
                        </div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value"><?= $performanceSummary['total_resolved_tickets'] ?? 0; ?></div>
                            <div class="stat-label">درخواست‌های حل‌شده</div>
                        </div>
                        <div class="stat-trend <?= ($performanceSummary['resolved_trend'] ?? 0) > 0 ? 'trend-up' : 'trend-down'; ?>">
                            <i class="fas fa-<?= ($performanceSummary['resolved_trend'] ?? 0) > 0 ? 'arrow-up' : 'arrow-down'; ?>"></i>
                            <span><?= abs(round($performanceSummary['resolved_trend'] ?? 0, 1)); ?>%</span>
                        </div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-icon">
                            <i class="fas fa-tasks"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value"><?= $performanceSummary['total_remaining_tickets'] ?? 0; ?></div>
                            <div class="stat-label">درخواست‌های باقی‌مانده</div>
                        </div>
                        <div class="stat-trend <?= ($performanceSummary['remaining_trend'] ?? 0) < 0 ? 'trend-up' : 'trend-down'; ?>">
                            <i class="fas fa-<?= ($performanceSummary['remaining_trend'] ?? 0) < 0 ? 'arrow-up' : 'arrow-down'; ?>"></i>
                            <span><?= abs(round($performanceSummary['remaining_trend'] ?? 0, 1)); ?>%</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- جدول گزارش عملکرد -->
            <div class="performance-table-container">
                <div class="table-responsive">
                    <table class="table table-striped table-hover general-table text-center fixed-table">
                        <colgroup>
                            <col class="w-20"> <!-- نام پشتیبان -->
                            <col class="w-15"> <!-- تعداد درخواست کار‌های اختصاص‌یافته -->
                            <col class="w-15"> <!-- میانگین زمان رسیدگی -->
                            <col class="w-15"> <!-- تعداد درخواست کار‌های حل‌شده -->
                            <col class="w-15"> <!-- تعداد درخواست کار‌های باقی‌مانده -->
                            <col class="w-20"> <!-- میزان رضایت کاربران -->
                        </colgroup>
                        <thead class="table-dark">
                            <tr>
                                <th>نام پشتیبان</th>
                                <th>تعداد درخواست کار‌های اختصاص‌یافته</th>
                                <th>میانگین زمان رسیدگی (دقیقه)</th>
                                <th>تعداد درخواست کار‌های حل‌شده</th>
                                <th>تعداد درخواست کار‌های باقی‌مانده</th>
                                <th>میزان رضایت کاربران</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($dashboardData['supportPerformance'])): ?>
                                <?php foreach ($dashboardData['supportPerformance'] as $support): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center justify-content-center">
                                                <div class="support-avatar me-2">
                                                    <img src="<?= !empty($support['avatar']) ? htmlspecialchars($support['avatar']) : '/assets/images/default-avatar.png'; ?>" 
                                                        alt="<?= htmlspecialchars($support['support_name']); ?>" class="rounded-circle" width="32" height="32">
                                                </div>
                                                <div><?= htmlspecialchars($support['support_name']); ?></div>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($support['assigned_tickets']); ?></td>
                                        <td>
                                            <span class="badge rounded-pill <?= getResolutionTimeBadgeClass($support['avg_resolution_time'] ?? 0); ?>">
                                                <?= round($support['avg_resolution_time'] ?? 0, 2); ?> دقیقه
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($support['resolved_tickets']); ?></td>
                                        <td><?= htmlspecialchars($support['remaining_tickets']); ?></td>
                                        <td>
                                            <div class="satisfaction-bar">
                                                <div class="progress" style="height: 25px;">
                                                    <div class="progress-bar <?= getSatisfactionBarClass($support['avg_user_satisfaction'] ?? 0); ?>" role="progressbar" 
                                                        style="width: <?= round($support['avg_user_satisfaction'] ?? 0, 2); ?>%;" 
                                                        aria-valuenow="<?= round($support['avg_user_satisfaction'] ?? 0, 2); ?>" 
                                                        aria-valuemin="0" aria-valuemax="100">
                                                        <?= round($support['avg_user_satisfaction'] ?? 0, 2); ?>%
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4">
                                        <div class="empty-state">
                                            <i class="fas fa-search fa-3x text-muted mb-3"></i>
                                            <p>هیچ داده‌ای با فیلترهای انتخاب شده یافت نشد.</p>
                                            <a href="/support_system/dashboard/admin" class="btn btn-sm btn-outline-primary mt-2">نمایش همه داده‌ها</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- نمودار مقایسه‌ای عملکرد پشتیبان‌ها -->
            <div class="charts-container mt-4">
                <div class="chart-tabs">
                    <ul class="nav nav-pills chart-nav mb-3" id="chart-tabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="resolution-time-tab" data-bs-toggle="pill" data-bs-target="#resolution-time-chart" type="button" role="tab" aria-selected="true">
                                <i class="fas fa-clock me-1"></i> زمان رسیدگی
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="satisfaction-tab" data-bs-toggle="pill" data-bs-target="#satisfaction-chart" type="button" role="tab" aria-selected="false">
                                <i class="fas fa-smile me-1"></i> رضایت کاربران
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tickets-tab" data-bs-toggle="pill" data-bs-target="#tickets-chart" type="button" role="tab" aria-selected="false">
                                <i class="fas fa-ticket-alt me-1"></i> درخواست‌ها
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="comparison-tab" data-bs-toggle="pill" data-bs-target="#comparison-chart" type="button" role="tab" aria-selected="false">
                                <i class="fas fa-chart-bar me-1"></i> مقایسه کلی
                            </button>
                        </li>
                    </ul>
                    
                    <div class="tab-content" id="chart-tabContent">
                        <div class="tab-pane fade show active" id="resolution-time-chart" role="tabpanel" aria-labelledby="resolution-time-tab">
                            <div class="chart-container">
                                <div class="chart-header">
                                    <h5><i class="fas fa-clock me-2"></i> مقایسه میانگین زمان رسیدگی پشتیبان‌ها (دقیقه)</h5>
                                    <div class="chart-actions">
                                        <button class="btn btn-sm btn-outline-primary" id="toggle-chart-type-1" title="تغییر نوع نمودار">
                                            <i class="fas fa-exchange-alt"></i> تغییر نوع نمودار
                                        </button>
                                    </div>
                                </div>
                                <div class="chart-body">
                                    <canvas id="resolutionTimeChart" width="400" height="300"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="satisfaction-chart" role="tabpanel" aria-labelledby="satisfaction-tab">
                            <div class="chart-container">
                                <div class="chart-header">
                                    <h5><i class="fas fa-smile me-2"></i> مقایسه میزان رضایت کاربران از پشتیبان‌ها</h5>
                                    <div class="chart-actions">
                                        <button class="btn btn-sm btn-outline-success" id="toggle-chart-type-2" title="تغییر نوع نمودار">
                                            <i class="fas fa-exchange-alt"></i> تغییر نوع نمودار
                                        </button>
                                    </div>
                                </div>
                                <div class="chart-body">
                                    <canvas id="satisfactionChart" width="400" height="300"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="tickets-chart" role="tabpanel" aria-labelledby="tickets-tab">
                            <div class="chart-container">
                                <div class="chart-header">
                                    <h5><i class="fas fa-ticket-alt me-2"></i> مقایسه وضعیت درخواست‌های پشتیبان‌ها</h5>
                                    <div class="chart-actions">
                                        <button class="btn btn-sm btn-outline-info" id="toggle-chart-type-3" title="تغییر نوع نمودار">
                                            <i class="fas fa-exchange-alt"></i> تغییر نوع نمودار
                                        </button>
                                    </div>
                                </div>
                                <div class="chart-body">
                                    <canvas id="ticketsChart" width="400" height="300"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="comparison-chart" role="tabpanel" aria-labelledby="comparison-tab">
                            <div class="chart-container">
                                <div class="chart-header">
                                    <h5><i class="fas fa-chart-bar me-2"></i> مقایسه کلی عملکرد پشتیبان‌ها</h5>
                                    <div class="chart-actions">
                                        <button class="btn btn-sm btn-outline-secondary" id="toggle-chart-type-4" title="تغییر نوع نمودار">
                                            <i class="fas fa-exchange-alt"></i> تغییر نوع نمودار
                                        </button>
                                    </div>
                                </div>
                                <div class="chart-body">
                                    <canvas id="comparisonChart" width="400" height="300"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <?php
        // توابع کمکی برای تعیین کلاس‌های نمایشی
        function getResolutionTimeBadgeClass($time) {
            if ($time <= 30) {
                return 'bg-success';
            } elseif ($time <= 60) {
                return 'bg-info';
            } elseif ($time <= 120) {
                return 'bg-warning';
            } else {
                return 'bg-danger';
            }
        }

        function getSatisfactionBarClass($satisfaction) {
            if ($satisfaction >= 80) {
                return 'bg-success';
            } elseif ($satisfaction >= 60) {
                return 'bg-info';
            } elseif ($satisfaction >= 40) {
                return 'bg-warning';
            } else {
                return 'bg-danger';
            }
        }

        // تابع کمکی برای حذف پارامتر از URL
        function removeQueryParam($param) {
            $params = $_GET;
            unset($params[$param]);
            
            if (empty($params)) {
                return '/support_system/dashboard/admin';
            }
            
            return '/support_system/dashboard/admin?' . http_build_query($params);
        }
        ?>

        <!-- اسکریپت‌های مربوط به فیلتر و نمودارها -->
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // تنظیم بازه زمانی با دکمه‌های پیش‌فرض
            const timePresetBtns = document.querySelectorAll('.time-preset-btn');
            const timeRangeInput = document.getElementById('time_range');
            
            timePresetBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    // حذف کلاس active از همه دکمه‌ها
                    timePresetBtns.forEach(b => b.classList.remove('active'));
                    
                    // اضافه کردن کلاس active به دکمه کلیک شده
                    this.classList.add('active');
                    
                    // تنظیم مقدار input مخفی
                    timeRangeInput.value = this.dataset.value;
                });
            });
            
            // تنظیم بازه زمانی دلخواه
            document.querySelector('.set-custom-date-btn').addEventListener('click', function() {
                const startDate = document.getElementById('start_date').value;
                const endDate = document.getElementById('end_date').value;
                
                if (startDate && endDate) {
                    // حذف کلاس active از همه دکمه‌ها
                    timePresetBtns.forEach(b => b.classList.remove('active'));
                    
                    // تنظیم مقدار input مخفی
                    timeRangeInput.value = 'custom';
                } else {
                    alert('لطفاً تاریخ شروع و پایان را وارد کنید.');
                }
            });
            
            // به‌روزرسانی مقدار اسلایدر رضایت
            const satisfactionRange = document.getElementById('min_satisfaction');
            const satisfactionDisplay = document.getElementById('satisfaction_display');
            
            if (satisfactionRange && satisfactionDisplay) {
                satisfactionRange.addEventListener('input', function() {
                    satisfactionDisplay.textContent = this.value;
                    
                    // تغییر آیکون بر اساس مقدار
                    const satisfactionIcon = document.querySelector('.satisfaction-icon i');
                    if (this.value >= 80) {
                        satisfactionIcon.className = 'fas fa-grin-stars';
                    } else if (this.value >= 60) {
                        satisfactionIcon.className = 'fas fa-smile';
                    } else if (this.value >= 40) {
                        satisfactionIcon.className = 'fas fa-meh';
                    } else if (this.value >= 20) {
                        satisfactionIcon.className = 'fas fa-frown';
                    } else {
                        satisfactionIcon.className = 'fas fa-angry';
                    }
                });
            }
            
            // به‌روزرسانی مقدار اسلایدر زمان رسیدگی
            const minResolutionTime = document.getElementById('min_resolution_time');
            const maxResolutionTime = document.getElementById('max_resolution_time');
            const minResolutionTimeDisplay = document.getElementById('min_resolution_time_display');
            const maxResolutionTimeDisplay = document.getElementById('max_resolution_time_display');
            
            if (minResolutionTime && maxResolutionTime) {
                minResolutionTime.addEventListener('input', function() {
                    if (parseInt(this.value) > parseInt(maxResolutionTime.value)) {
                        this.value = maxResolutionTime.value;
                    }
                    minResolutionTimeDisplay.textContent = this.value;
                });
                
                maxResolutionTime.addEventListener('input', function() {
                    if (parseInt(this.value) < parseInt(minResolutionTime.value)) {
                        this.value = minResolutionTime.value;
                    }
                    maxResolutionTimeDisplay.textContent = this.value;
                });
            }
            
            // دکمه پاک کردن فیلترها
            document.getElementById('reset-filter').addEventListener('click', function() {
                window.location.href = '/support_system/dashboard/admin';
            });
            
            document.getElementById('reset-all-filters').addEventListener('click', function() {
                window.location.href = '/support_system/dashboard/admin';
            });
            
            // دکمه‌های خروجی
            document.getElementById('export-excel').addEventListener('click', function() {
                exportReport('excel');
            });
            
            document.getElementById('export-pdf').addEventListener('click', function() {
                exportReport('pdf');
            });
            
            document.getElementById('export-print').addEventListener('click', function() {
                window.print();
            });
            
            function exportReport(type) {
                // ایجاد یک کپی از فرم فعلی و اضافه کردن پارامتر خروجی
                const form = document.getElementById('support-performance-filter');
                const formData = new FormData(form);
                formData.append('export', type);
                
                // ارسال درخواست AJAX
                fetch('/support_system/dashboard/export', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.blob())
                .then(blob => {
                    // ایجاد لینک دانلود
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.style.display = 'none';
                    a.href = url;
                    a.download = 'support_performance_report.' + (type === 'excel' ? 'xlsx' : 'pdf');
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                })
                .catch(error => console.error('خطا در دریافت فایل:', error));
            }
            
            // رسم نمودارها
            const supportNames = <?= json_encode(array_column($dashboardData['supportPerformance'], 'support_name')); ?>;
            const resolutionTimes = <?= json_encode(array_map(function($item) { return round($item['avg_resolution_time'] ?? 0, 2); }, $dashboardData['supportPerformance'])); ?>;
            const satisfactionRates = <?= json_encode(array_map(function($item) { return round($item['avg_user_satisfaction'] ?? 0, 2); }, $dashboardData['supportPerformance'])); ?>;
            const assignedTickets = <?= json_encode(array_map(function($item) { return $item['assigned_tickets'] ?? 0; }, $dashboardData['supportPerformance'])); ?>;
            const resolvedTickets = <?= json_encode(array_map(function($item) { return $item['resolved_tickets'] ?? 0; }, $dashboardData['supportPerformance'])); ?>;
            const remainingTickets = <?= json_encode(array_map(function($item) { return $item['remaining_tickets'] ?? 0; }, $dashboardData['supportPerformance'])); ?>;
            
            // تنظیمات نمودار زمان رسیدگی
            let resolutionTimeChart;
            let resolutionChartType = 'bar';
            
            function createResolutionTimeChart() {
                const resolutionTimeCtx = document.getElementById('resolutionTimeChart').getContext('2d');
                
                if (resolutionTimeChart) {
                    resolutionTimeChart.destroy();
                }
                
                resolutionTimeChart = new Chart(resolutionTimeCtx, {
                    type: resolutionChartType,
                    data: {
                        labels: supportNames,
                        datasets: [{
                            label: 'میانگین زمان رسیدگی (دقیقه)',
                            data: resolutionTimes,
                            backgroundColor: resolutionChartType === 'bar' ? 
                                'rgba(54, 162, 235, 0.7)' : 
                                generateColors(resolutionTimes.length),
                            borderColor: 'rgba(54, 162, 235, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: resolutionChartType === 'bar' ? {
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'زمان (دقیقه)'
                                }
                            }
                        } : {},
                        plugins: {
                            legend: {
                                position: 'bottom'
                            },
                            title: {
                                display: true,
                                text: 'مقایسه میانگین زمان رسیدگی پشتیبان‌ها'
                            }
                        }
                    }
                });
            }
            
            // تنظیمات نمودار رضایت کاربران
            let satisfactionChart;
            let satisfactionChartType = 'bar';
            
            function createSatisfactionChart() {
                const satisfactionCtx = document.getElementById('satisfactionChart').getContext('2d');
                
                if (satisfactionChart) {
                    satisfactionChart.destroy();
                }
                
                satisfactionChart = new Chart(satisfactionCtx, {
                    type: satisfactionChartType,
                    data: {
                        labels: supportNames,
                        datasets: [{
                            label: 'میزان رضایت کاربران (%)',
                            data: satisfactionRates,
                            backgroundColor: satisfactionChartType === 'bar' ? 
                                'rgba(75, 192, 192, 0.7)' : 
                                generateColors(satisfactionRates.length),
                            borderColor: 'rgba(75, 192, 192, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: satisfactionChartType === 'bar' ? {
                            y: {
                                beginAtZero: true,
                                max: 100,
                                title: {
                                    display: true,
                                    text: 'رضایت (%)'
                                }
                            }
                        } : {},
                        plugins: {
                            legend: {
                                position: 'bottom'
                            },
                            title: {
                                display: true,
                                text: 'مقایسه میزان رضایت کاربران از پشتیبان‌ها'
                            }
                        }
                    }
                });
            }
            
            // تنظیمات نمودار درخواست‌ها
            let ticketsChart;
            let ticketsChartType = 'bar';
            
            function createTicketsChart() {
                const ticketsCtx = document.getElementById('ticketsChart').getContext('2d');
                
                if (ticketsChart) {
                    ticketsChart.destroy();
                }
                
                ticketsChart = new Chart(ticketsCtx, {
                    type: ticketsChartType,
                    data: {
                        labels: supportNames,
                        datasets: [
                            {
                                label: 'درخواست‌های اختصاص‌یافته',
                                data: assignedTickets,
                                backgroundColor: 'rgba(255, 159, 64, 0.7)',
                                borderColor: 'rgba(255, 159, 64, 1)',
                                borderWidth: 1
                            },
                            {
                                label: 'درخواست‌های حل‌شده',
                                data: resolvedTickets,
                                backgroundColor: 'rgba(75, 192, 192, 0.7)',
                                borderColor: 'rgba(75, 192, 192, 1)',
                                borderWidth: 1
                            },
                            {
                                label: 'درخواست‌های باقی‌مانده',
                                data: remainingTickets,
                                backgroundColor: 'rgba(255, 99, 132, 0.7)',
                                borderColor: 'rgba(255, 99, 132, 1)',
                                borderWidth: 1
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'تعداد درخواست‌ها'
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                position: 'bottom'
                            },
                            title: {
                                display: true,
                                text: 'مقایسه وضعیت درخواست‌های پشتیبان‌ها'
                            }
                        }
                    }
                });
            }
            
            // تنظیمات نمودار مقایسه کلی
            let comparisonChart;
            let comparisonChartType = 'radar';
            
            function createComparisonChart() {
                const comparisonCtx = document.getElementById('comparisonChart').getContext('2d');
                
                if (comparisonChart) {
                    comparisonChart.destroy();
                }
                
                // نرمال‌سازی داده‌ها برای نمودار رادار
                const maxResolutionTime = Math.max(...resolutionTimes);
                const normalizedResolutionTimes = resolutionTimes.map(time => 100 - ((time / maxResolutionTime) * 100));
                
                const maxAssigned = Math.max(...assignedTickets);
                const normalizedAssigned = assignedTickets.map(count => (count / maxAssigned) * 100);
                
                const maxResolved = Math.max(...resolvedTickets);
                const normalizedResolved = resolvedTickets.map(count => (count / maxResolved) * 100);
                
                comparisonChart = new Chart(comparisonCtx, {
                    type: comparisonChartType,
                    data: {
                        labels: ['سرعت رسیدگی', 'رضایت کاربران', 'حجم کار', 'کارایی', 'بهره‌وری'],
                        datasets: supportNames.map((name, index) => ({
                            label: name,
                            data: [
                                normalizedResolutionTimes[index], // سرعت رسیدگی (معکوس زمان)
                                satisfactionRates[index], // رضایت کاربران
                                normalizedAssigned[index], // حجم کار
                                normalizedResolved[index], // کارایی
                                resolvedTickets[index] > 0 ? (resolvedTickets[index] / assignedTickets[index]) * 100 : 0 // بهره‌وری
                            ],
                            backgroundColor: `rgba(${Math.floor(Math.random() * 255)}, ${Math.floor(Math.random() * 255)}, ${Math.floor(Math.random() * 255)}, 0.2)`,
                            borderColor: `rgba(${Math.floor(Math.random() * 255)}, ${Math.floor(Math.random() * 255)}, ${Math.floor(Math.random() * 255)}, 1)`,
                            borderWidth: 1
                        }))
                    },
                    options: {
                        responsive: true,
                        scales: {
                            r: {
                                angleLines: {
                                    display: true
                                },
                                suggestedMin: 0,
                                suggestedMax: 100
                            }
                        },
                        plugins: {
                            legend: {
                                position: 'bottom'
                            },
                            title: {
                                display: true,
                                text: 'مقایسه کلی عملکرد پشتیبان‌ها'
                            }
                        }
                    }
                });
            }
            
            // تغییر نوع نمودار
            document.getElementById('toggle-chart-type-1').addEventListener('click', function() {
                resolutionChartType = resolutionChartType === 'bar' ? 'pie' : 'bar';
                createResolutionTimeChart();
            });
            
            document.getElementById('toggle-chart-type-2').addEventListener('click', function() {
                satisfactionChartType = satisfactionChartType === 'bar' ? 'pie' : 'bar';
                createSatisfactionChart();
            });
            
            document.getElementById('toggle-chart-type-3').addEventListener('click', function() {
                ticketsChartType = ticketsChartType === 'bar' ? 'line' : 'bar';
                createTicketsChart();
            });
            
            document.getElementById('toggle-chart-type-4').addEventListener('click', function() {
                comparisonChartType = comparisonChartType === 'radar' ? 'polarArea' : 'radar';
                createComparisonChart();
            });
            
            // تولید رنگ‌های تصادفی
            function generateColors(count) {
                const colors = [];
                const baseColors = [
                    'rgba(255, 99, 132, 0.7)',
                    'rgba(54, 162, 235, 0.7)',
                    'rgba(255, 206, 86, 0.7)',
                    'rgba(75, 192, 192, 0.7)',
                    'rgba(153, 102, 255, 0.7)',
                    'rgba(255, 159, 64, 0.7)',
                    'rgba(199, 199, 199, 0.7)',
                    'rgba(83, 102, 255, 0.7)',
                    'rgba(40, 159, 64, 0.7)',
                    'rgba(210, 199, 199, 0.7)'
                ];
                
                for (let i = 0; i < count; i++) {
                    colors.push(baseColors[i % baseColors.length]);
                }
                
                return colors;
            }
            
            // رسم نمودارها در هنگام بارگذاری صفحه
            createResolutionTimeChart();
            createSatisfactionChart();
            createTicketsChart();
            createComparisonChart();
        });
        </script>
        <section class="row mb-4">
            <!-- کاربران با بیشترین تعداد درخواست کار‌های ثبت‌شده -->
            <div class="col-lg-6 col-md-12 mb-4">
                <h3 class="mb-3">کاربران با بیشترین تعداد درخواست کار‌های ثبت‌شده</h3>
                <div class="table-responsive">
                    <table class="table table-striped table-hover general-table text-center fixed-table">
                        <colgroup>
                            <col class="w-70"> <!-- نام کاربر -->
                            <col class="w-30"> <!-- تعداد درخواست کار‌ها -->
                        </colgroup>
                        <thead class="table-dark">
                            <tr>
                                <th>نام کاربر</th>
                                <th>تعداد درخواست کار‌ها</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($topUsersByTickets)): ?>
                                <?php foreach ($topUsersByTickets as $user): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($user['user_name']); ?></td>
                                        <td><?= htmlspecialchars($user['ticket_count']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="2" class="text-center">هیچ داده‌ای موجود نیست.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- تعداد درخواست کار‌های معوق برای هر کاربر -->
            <div class="col-lg-6 col-md-12 mb-4">
                <h3 class="mb-3">تعداد درخواست کار‌های معوق برای هر کاربر</h3>
                <div class="table-responsive">
                    <table class="table table-striped table-hover general-table text-center fixed-table">
                        <colgroup>
                            <col class="w-70"> <!-- نام کاربر -->
                            <col class="w-30"> <!-- تعداد درخواست کار‌های معوق -->
                        </colgroup>
                        <thead class="table-dark">
                            <tr>
                                <th>نام کاربر</th>
                                <th>تعداد درخواست کار‌های معوق</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($dashboardData['overdueTicketsByUser'])): ?>
                                <?php foreach ($dashboardData['overdueTicketsByUser'] as $user): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($user['user_name']); ?></td>
                                        <td><?= htmlspecialchars($user['overdue_tickets']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="2" class="text-center">هیچ داده‌ای موجود نیست.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
        
        <!-- تغییرات اخیر -->
        <section class="mb-5">
            <h3 class="mb-3 text-center">تغییرات اخیر در وضعیت درخواست‌ها</h3>
            <div class="table-responsive">
                <table class="table table-striped table-hover general-table text-center fixed-table">
                    <colgroup>
                        <col class="w-5">  <!-- ردیف -->
                        <col class="w-25"> <!-- عنوان درخواست -->
                        <col class="w-15"> <!-- نام پشتیبان -->
                        <col class="w-15"> <!-- وضعیت قبلی -->
                        <col class="w-15"> <!-- وضعیت جدید -->
                        <col class="w-15"> <!-- زمان تغییر -->
                        <col class="w-10"> <!-- عملیات -->
                    </colgroup>
                    <thead class="table-dark">
                        <tr>
                            <th>ردیف</th>
                            <th>عنوان درخواست</th>
                            <th>نام پشتیبان</th>
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
                                    <td><?= htmlspecialchars($change['support_name'] ?? 'نامشخص'); ?></td>
                                    <td>
                                        <span class="badge rounded-pill <?= getStatusBadgeClass($change['old_status']); ?>">
                                            <?= getStatusLabel($change['old_status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge rounded-pill <?= getStatusBadgeClass($change['new_status']); ?>">
                                            <?= getStatusLabel($change['new_status']); ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($change['changed_at']); ?></td>
                                    <td>
                                        <a href="/support_system/tickets/view/<?= htmlspecialchars($change['ticket_id'] ?? ''); ?>" class="btn btn-primary btn-sm">مشاهده</a>
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
                
        <!-- دکمه‌های دسترسی سریع: -->
        <div class="quick-access-container">
            <!-- دکمه 1 -->
            <div class="quick-access-item">
                <a href="/dashboard" class="btn btn-primary quick-access-btn" title="داشبورد">
                    <i class="bi bi-house"></i>
                </a>
            </div>
            <!-- دکمه 2 -->
            <div class="quick-access-item">
                <a href="/users" class="btn btn-success quick-access-btn" title="کاربران">
                    <i class="bi bi-person"></i>
                </a>
            </div>
            <!-- دکمه 3 -->
            <div class="quick-access-item">
                <a href="/settings" class="btn btn-warning quick-access-btn" title="تنظیمات">
                    <i class="bi bi-gear"></i>
                </a>
            </div>
            <!-- دکمه 4 -->
            <div class="quick-access-item">
                <a href="/messages" class="btn btn-danger quick-access-btn" title="پیام‌ها">
                    <i class="bi bi-envelope"></i>
                </a>
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

<script src="/assets/js/dashboard_admin.js"></script>
<!-- ارسال داده‌های نمودارها به صورت JSON -->
<script>
    const ticketStatusData = <?= json_encode(array_column($dashboardData['ticketStatusCounts'], 'count', 'status')); ?>;
    const ctx = document.getElementById('ticketStatusChart').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['باز', 'در حال بررسی', 'بسته'],
            datasets: [{
                data: [ticketStatusData['open'] ?? 0, ticketStatusData['in_progress'] ?? 0, ticketStatusData['closed'] ?? 0],
                backgroundColor: ['#0d6efd', '#ffc107', '#198754'], // رنگ‌های هماهنگ با گزارش‌های کلی
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

<script>
    const ticketCountsByDate = <?= json_encode(array_column($dashboardData['ticketCountsByDate'], 'ticket_count', 'ticket_date')); ?>;
    const ctxTime = document.getElementById('ticketTimeChart').getContext('2d');
    new Chart(ctxTime, {
        type: 'line',
        data: {
            labels: Object.keys(ticketCountsByDate),
            datasets: [{
                label: 'تعداد درخواست‌ها',
                data: Object.values(ticketCountsByDate),
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13, 110, 253, 0.2)',
                fill: true,
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

<?php
// تابع کمکی برای تعیین کلاس بج بر اساس وضعیت
function getStatusBadgeClass($status) {
    switch (strtolower($status)) {
        case 'open':
        case 'باز':
            return 'bg-primary';
        case 'in_progress':
        case 'در حال بررسی':
            return 'bg-warning';
        case 'closed':
        case 'بسته':
            return 'bg-success';
        case 'pending':
        case 'معلق':
            return 'bg-info';
        case 'rejected':
        case 'رد شده':
            return 'bg-danger';
        default:
            return 'bg-secondary';
    }
}

// تابع کمکی برای تبدیل وضعیت به فارسی
function getStatusLabel($status) {
    switch (strtolower($status)) {
        case 'open':
            return 'باز';
        case 'in_progress':
            return 'در حال بررسی';
        case 'closed':
            return 'بسته';
        case 'pending':
            return 'معلق';
        case 'rejected':
            return 'رد شده';
        default:
            return $status;
    }
}
?>

<!-- اسکریپت‌های نمودارهای تجهیز‌ها -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // نمودار توزیع تجهیز‌ها بر اساس دسته‌بندی
    const assetCategoryData = <?= json_encode(array_map(function($item) {
        return ['name' => $item['name'], 'count' => $item['count']];
    }, $dashboardData['assetCategoryDistribution'] ?? [])); ?>;
    
    if (assetCategoryData.length > 0) {
        const ctxCategory = document.getElementById('assetCategoryChart').getContext('2d');
        new Chart(ctxCategory, {
            type: 'pie',
            data: {
                labels: assetCategoryData.map(item => item.name),
                datasets: [{
                    data: assetCategoryData.map(item => item.count),
                    backgroundColor: [
                        '#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b',
                        '#5a5c69', '#858796', '#6f42c1', '#20c9a6', '#e83e8c'
                    ],
                    hoverBackgroundColor: [
                        '#2e59d9', '#17a673', '#2c9faf', '#dda20a', '#be2617',
                        '#3a3b45', '#60616f', '#5a35a0', '#169b80', '#c71666'
                    ],
                    hoverBorderColor: "rgba(234, 236, 244, 1)",
                }],
            },
            options: {
                maintainAspectRatio: false,
                tooltips: {
                    backgroundColor: "rgb(255,255,255)",
                    bodyFontColor: "#858796",
                    borderColor: '#dddfeb',
                    borderWidth: 1,
                    xPadding: 15,
                    yPadding: 15,
                    displayColors: false,
                    caretPadding: 10,
                },
                legend: {
                    display: true,
                    position: 'bottom'
                },
                cutoutPercentage: 30,
            },
        });
    }
    
    // نمودار توزیع تجهیز‌ها بر اساس وضعیت
    const assetStatusData = <?= json_encode(array_map(function($item) {
        $statusLabels = [
            'ready' => 'آماده',
            'in_use' => 'در حال استفاده',
            'needs_repair' => 'نیازمند تعمیر',
            'out_of_service' => 'خارج از سرویس'
        ];
        return [
            'name' => $statusLabels[$item['status']] ?? $item['status'],
            'count' => $item['count']
        ];
    }, $dashboardData['assetsByStatus'] ?? [])); ?>;
    
    if (assetStatusData.length > 0) {
        const ctxStatus = document.getElementById('assetStatusChart').getContext('2d');
        new Chart(ctxStatus, {
            type: 'doughnut',
            data: {
                labels: assetStatusData.map(item => item.name),
                datasets: [{
                    data: assetStatusData.map(item => item.count),
                    backgroundColor: [
                        '#1cc88a', '#4e73df', '#f6c23e', '#e74a3b'
                    ],
                    hoverBackgroundColor: [
                        '#17a673', '#2e59d9', '#dda20a', '#be2617'
                    ],
                    hoverBorderColor: "rgba(234, 236, 244, 1)",
                }],
            },
            options: {
                maintainAspectRatio: false,
                tooltips: {
                    backgroundColor: "rgb(255,255,255)",
                    bodyFontColor: "#858796",
                    borderColor: '#dddfeb',
                    borderWidth: 1,
                    xPadding: 15,
                    yPadding: 15,
                    displayColors: false,
                    caretPadding: 10,
                },
                legend: {
                    display: true,
                    position: 'bottom'
                },
                cutoutPercentage: 50,
            },
        });
    }
});
</script>

<?php include 'footer.php'; ?>