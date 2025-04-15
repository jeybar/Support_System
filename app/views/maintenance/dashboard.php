<div class="container-fluid mt-4">
    <h1 class="mb-4">داشبورد سرویس‌های ادواری</h1>
    
    <!-- کارت‌های آمار کلی -->
    <div class="row">
        <div class="col-md-3">
            <div class="card bg-primary text-white mb-4">
                <div class="card-body">
                    <h5 class="card-title">کل سرویس‌ها</h5>
                    <h2 class="card-text"><?= $maintenanceStats['total_count'] ?? 0 ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white mb-4">
                <div class="card-body">
                    <h5 class="card-title">سرویس‌های تکمیل شده</h5>
                    <h2 class="card-text"><?= $maintenanceStats['completed_count'] ?? 0 ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white mb-4">
                <div class="card-body">
                    <h5 class="card-title">سرویس‌های برنامه‌ریزی شده</h5>
                    <h2 class="card-text"><?= $maintenanceStats['scheduled_count'] ?? 0 ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger text-white mb-4">
                <div class="card-body">
                    <h5 class="card-title">سرویس‌های معوق</h5>
                    <h2 class="card-text"><?= $maintenanceStats['overdue_count'] ?? 0 ?></h2>
                </div>
            </div>
        </div>
    </div>
    
    <!-- نمودارها -->
    <div class="row">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-chart-pie me-1"></i>
                    توزیع سرویس‌ها بر اساس دسته‌بندی
                </div>
                <div class="card-body">
                    <canvas id="maintenanceCategoryChart" width="100%" height="50"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-chart-bar me-1"></i>
                    نرخ تکمیل سرویس‌ها
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-center align-items-center h-100">
                        <div class="text-center">
                            <div class="progress-circle" data-percentage="<?= round($maintenanceCompletionRate['completion_rate'] ?? 0) ?>">
                                <span class="progress-circle-left">
                                    <span class="progress-circle-bar"></span>
                                </span>
                                <span class="progress-circle-right">
                                    <span class="progress-circle-bar"></span>
                                </span>
                                <div class="progress-circle-value">
                                    <div>
                                        <?= round($maintenanceCompletionRate['completion_rate'] ?? 0) ?>%<br>
                                        <span>تکمیل شده</span>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-3">
                                <p>از مجموع <?= $maintenanceCompletionRate['total_count'] ?? 0 ?> سرویس برنامه‌ریزی شده، 
                                <?= $maintenanceCompletionRate['completed_count'] ?? 0 ?> مورد تکمیل شده است.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- سرویس‌های آینده -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-calendar me-1"></i>
            سرویس‌های برنامه‌ریزی شده آینده
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="upcomingMaintenanceTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>شناسه</th>
                            <th>تجهیز</th>
                            <th>نوع سرویس</th>
                            <th>تاریخ برنامه‌ریزی شده</th>
                            <th>تکنسین</th>
                            <th>وضعیت</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($upcomingMaintenance as $maintenance): ?>
                        <tr>
                            <td><?= $maintenance['id'] ?></td>
                            <td><?= htmlspecialchars($maintenance['asset_name']) ?> (<?= htmlspecialchars($maintenance['asset_tag']) ?>)</td>
                            <td><?= htmlspecialchars($maintenance['maintenance_type']) ?></td>
                            <td><?= date('Y-m-d', strtotime($maintenance['scheduled_date'])) ?></td>
                            <td><?= htmlspecialchars($maintenance['technician_name'] ?? 'تعیین نشده') ?></td>
                            <td>
                                <span class="badge <?= $maintenance['status'] == 'scheduled' ? 'bg-warning' : ($maintenance['status'] == 'in_progress' ? 'bg-info' : 'bg-success') ?>">
                                    <?= $maintenance['status'] == 'scheduled' ? 'برنامه‌ریزی شده' : ($maintenance['status'] == 'in_progress' ? 'در حال انجام' : 'تکمیل شده') ?>
                                </span>
                            </td>
                            <td>
                                <a href="/support_system/maintenance/show/<?= $maintenance['id'] ?>" class="btn btn-sm btn-info">مشاهده</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
// اسکریپت‌های مربوط به نمودارها
document.addEventListener('DOMContentLoaded', function() {
    // نمودار توزیع دسته‌بندی
    var categoryCtx = document.getElementById('maintenanceCategoryChart');
    var categoryData = <?= json_encode($maintenanceByCategory) ?>;
    var categoryLabels = categoryData.map(item => item.category_name);
    var categoryValues = categoryData.map(item => item.count);
    
    new Chart(categoryCtx, {
        type: 'pie',
        data: {
            labels: categoryLabels,
            datasets: [{
                data: categoryValues,
                backgroundColor: [
                    '#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b',
                    '#5a5c69', '#858796', '#6f42c1', '#20c9a6', '#fd7e14'
                ]
            }]
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
                caretPadding: 10
            },
            legend: {
                display: true,
                position: 'bottom'
            },
            cutoutPercentage: 0
        }
    });
    
    // نمایش دایره پیشرفت
    var circles = document.querySelectorAll('.progress-circle');
    circles.forEach(function(circle) {
        var percentage = circle.getAttribute('data-percentage');
        var degrees = percentage * 3.6;
        var rightTransform = 'rotate(' + Math.min(degrees, 180) + 'deg)';
        var leftTransform = 'rotate(' + Math.max(0, degrees - 180) + 'deg)';
        
        circle.querySelector('.progress-circle-right .progress-circle-bar').style.transform = rightTransform;
        circle.querySelector('.progress-circle-left .progress-circle-bar').style.transform = leftTransform;
        
        // تغییر رنگ بر اساس درصد
        var color = '#e74a3b'; // قرمز برای کمتر از 50%
        if (percentage >= 50 && percentage < 75) {
            color = '#f6c23e'; // زرد برای بین 50% تا 75%
        } else if (percentage >= 75) {
            color = '#1cc88a'; // سبز برای بیشتر از 75%
        }
        
        circle.querySelector('.progress-circle-right .progress-circle-bar').style.borderColor = color;
        circle.querySelector('.progress-circle-left .progress-circle-bar').style.borderColor = color;
    });
});
</script>

<style>
/* استایل دایره پیشرفت */
.progress-circle {
    position: relative;
    height: 200px;
    width: 200px;
    border-radius: 50%;
    background-color: #f0f0f0;
}

.progress-circle-bar {
    position: absolute;
    height: 100%;
    width: 100%;
    background: #f0f0f0;
    border-radius: 50%;
}

.progress-circle-left, .progress-circle-right {
    position: absolute;
    height: 100%;
    width: 100%;
    top: 0;
    left: 0;
    border-radius: 50%;
    overflow: hidden;
}

.progress-circle-left {
    transform: rotate(0deg);
}

.progress-circle-right {
    transform: rotate(180deg);
}

.progress-circle-left .progress-circle-bar {
    left: 100%;
    border-top-right-radius: 0;
    border-bottom-right-radius: 0;
    border-right: 0;
    transform-origin: center left;
    animation: loading-1 1.8s linear forwards;
    border: 10px solid #1cc88a;
    border-left: 0;
}

.progress-circle-right .progress-circle-bar {
    left: -100%;
    border-top-left-radius: 0;
    border-bottom-left-radius: 0;
    border-left: 0;
    transform-origin: center right;
    animation: loading-2 1.8s linear forwards;
    border: 10px solid #1cc88a;
    border-right: 0;
}

.progress-circle-value {
    position: absolute;
    top: 0;
    left: 0;
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100%;
    width: 100%;
    font-size: 24px;
    font-weight: bold;
}

.progress-circle-value div {
    margin-top: -5px;
}

.progress-circle-value span {
    font-size: 14px;
    font-weight: normal;
}
</style>