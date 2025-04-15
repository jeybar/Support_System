<?php require_once __DIR__ . '/../layouts/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <?php require_once __DIR__ . '/../layouts/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">آمار و گزارش‌های آماری تجهیز‌ها</h1>
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
            
            <!-- خلاصه آماری -->
            <div class="row">
                <div class="col-md-3 mb-4">
                    <div class="card text-white bg-primary">
                        <div class="card-body">
                            <h5 class="card-title">کل تجهیز‌ها</h5>
                            <p class="card-text display-6"><?= $assignmentStats['total'] ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="card text-white bg-success">
                        <div class="card-body">
                            <h5 class="card-title">تجهیز‌های تخصیص داده شده</h5>
                            <p class="card-text display-6"><?= $assignmentStats['assigned'] ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="card text-white bg-info">
                        <div class="card-body">
                            <h5 class="card-title">تجهیز‌های تخصیص داده نشده</h5>
                            <p class="card-text display-6"><?= $assignmentStats['unassigned'] ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="card text-white bg-warning">
                        <div class="card-body">
                            <h5 class="card-title">تجهیز‌های در حال تعمیر</h5>
                            <p class="card-text display-6"><?= $statusStats['in_repair'] ?? 0 ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <!-- نمودار تجهیز‌ها بر اساس دسته‌بندی -->
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5>تجهیز‌ها بر اساس دسته‌بندی</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="categoryChart" height="300"></canvas>
                        </div>
                    </div>
                </div>
                
                <!-- نمودار تجهیز‌ها بر اساس وضعیت -->
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5>تجهیز‌ها بر اساس وضعیت</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="statusChart" height="300"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <!-- نمودار تجهیز‌ها بر اساس سازنده -->
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5>تجهیز‌ها بر اساس سازنده</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="manufacturerChart" height="300"></canvas>
                        </div>
                    </div>
                </div>
                
                <!-- نمودار سن تجهیز‌ها -->
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5>سن تجهیز‌ها</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="ageChart" height="300"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <!-- نمودار روند خرید تجهیز‌ها -->
                <div class="col-md-12 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5>روند خرید تجهیز‌ها در ماه‌های اخیر</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="acquisitionChart" height="200"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <!-- مدل‌های محبوب -->
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5>مدل‌های محبوب</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>مدل</th>
                                            <th>سازنده</th>
                                            <th>دسته‌بندی</th>
                                            <th>تعداد</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($popularModels as $model): ?>
                                            <tr>
                                                <td><?= $model['name'] ?></td>
                                                <td><?= $model['manufacturer'] ?></td>
                                                <td><?= $model['category_name'] ?></td>
                                                <td><?= $model['asset_count'] ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- کاربران با بیشترین تجهیز‌ها -->
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5>کاربران با بیشترین تجهیز‌ها</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>نام کاربر</th>
                                            <th>تعداد تجهیز‌ها</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($topUsers as $user): ?>
                                            <tr>
                                                <td><?= $user['fullname'] ?></td>
                                                <td><?= $user['asset_count'] ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <!-- بخش‌ها با بیشترین تجهیز‌ها -->
                <div class="col-md-12 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5>بخش‌ها با بیشترین تجهیز‌ها</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>نام بخش</th>
                                            <th>تعداد تجهیز‌ها</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($topDepartments as $department): ?>
                                            <tr>
                                                <td><?= $department['name'] ?></td>
                                                <td><?= $department['asset_count'] ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- اضافه کردن Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // نمودار تجهیز‌ها بر اساس دسته‌بندی
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        new Chart(categoryCtx, {
            type: 'pie',
            data: {
                labels: [
                    <?php foreach ($categoryStats as $category): ?>
                        '<?= $category['name'] ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    data: [
                        <?php foreach ($categoryStats as $category): ?>
                            <?= $category['asset_count'] ?>,
                        <?php endforeach; ?>
                    ],
                    backgroundColor: [
                        '#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', '#858796',
                        '#5a5c69', '#2e59d9', '#17a673', '#2c9faf', '#f6c23e', '#e74a3b'
                    ],
                    hoverBackgroundColor: [
                        '#2e59d9', '#17a673', '#2c9faf', '#f6c23e', '#e74a3b', '#858796',
                        '#5a5c69', '#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b'
                    ],
                    hoverBorderColor: "rgba(234, 236, 244, 1)",
                }],
            },
            options: {
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                    }
                }
            },
        });
        
        // نمودار تجهیز‌ها بر اساس وضعیت
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['فعال', 'در حال تعمیر', 'بازنشسته', 'مفقود شده'],
                datasets: [{
                    data: [
                        <?= $statusStats['active'] ?? 0 ?>,
                        <?= $statusStats['in_repair'] ?? 0 ?>,
                        <?= $statusStats['retired'] ?? 0 ?>,
                        <?= $statusStats['lost'] ?? 0 ?>
                    ],
                    backgroundColor: ['#1cc88a', '#f6c23e', '#858796', '#e74a3b'],
                    hoverBackgroundColor: ['#17a673', '#f6c23e', '#858796', '#e74a3b'],
                    hoverBorderColor: "rgba(234, 236, 244, 1)",
                }],
            },
            options: {
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                    }
                }
            },
        });
        
        // نمودار تجهیز‌ها بر اساس سازنده
        const manufacturerCtx = document.getElementById('manufacturerChart').getContext('2d');
        new Chart(manufacturerCtx, {
            type: 'bar',
            data: {
                labels: [
                    <?php foreach ($manufacturerStats as $manufacturer): ?>
                        '<?= $manufacturer['manufacturer'] ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    label: 'تعداد تجهیز‌ها',
                    data: [
                        <?php foreach ($manufacturerStats as $manufacturer): ?>
                            <?= $manufacturer['asset_count'] ?>,
                        <?php endforeach; ?>
                    ],
                    backgroundColor: '#4e73df',
                    borderColor: '#4e73df',
                    borderWidth: 1
                }]
            },
            options: {
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });
        
        // نمودار سن تجهیز‌ها
        const ageCtx = document.getElementById('ageChart').getContext('2d');
        new Chart(ageCtx, {
            type: 'bar',
            data: {
                labels: ['کمتر از 1 سال', '1 تا 2 سال', '2 تا 3 سال', '3 تا 4 سال', 'بیشتر از 4 سال'],
                datasets: [{
                    label: 'تعداد تجهیز‌ها',
                    data: [
                        <?= $ageStats['less_than_1_year'] ?>,
                        <?= $ageStats['between_1_2_years'] ?>,
                        <?= $ageStats['between_2_3_years'] ?>,
                        <?= $ageStats['between_3_4_years'] ?>,
                        <?= $ageStats['more_than_4_years'] ?>
                    ],
                    backgroundColor: ['#1cc88a', '#4e73df', '#f6c23e', '#e74a3b', '#858796'],
                    borderColor: ['#1cc88a', '#4e73df', '#f6c23e', '#e74a3b', '#858796'],
                    borderWidth: 1
                }]
            },
            options: {
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });
        
        // نمودار روند خرید تجهیز‌ها
        const acquisitionCtx = document.getElementById('acquisitionChart').getContext('2d');
        new Chart(acquisitionCtx, {
            type: 'line',
            data: {
                labels: [
                    <?php foreach ($acquisitionTrend as $month): ?>
                        '<?= $month['month'] ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    label: 'تجهیز‌های خریداری شده',
                    data: [
                        <?php foreach ($acquisitionTrend as $month): ?>
                            <?= $month['count'] ?>,
                        <?php endforeach; ?>
                    ],
                    backgroundColor: 'rgba(78, 115, 223, 0.05)',
                    borderColor: 'rgba(78, 115, 223, 1)',
                    pointRadius: 3,
                    pointBackgroundColor: 'rgba(78, 115, 223, 1)',
                    pointBorderColor: 'rgba(78, 115, 223, 1)',
                    pointHoverRadius: 5,
                    pointHoverBackgroundColor: 'rgba(78, 115, 223, 1)',
                    pointHoverBorderColor: 'rgba(78, 115, 223, 1)',
                    pointHitRadius: 10,
                    pointBorderWidth: 2,
                    fill: true
                }]
            },
            options: {
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    });
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>