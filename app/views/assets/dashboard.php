<div class="container-fluid mt-4">
    <h1 class="mb-4">داشبورد تجهیز‌ها</h1>
    
    <!-- کارت‌های آمار کلی -->
    <div class="row">
        <div class="col-md-3">
            <div class="card bg-primary text-white mb-4">
                <div class="card-body">
                    <h5 class="card-title">کل تجهیز‌ها</h5>
                    <h2 class="card-text"><?= $assetStats['total_count'] ?? 0 ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white mb-4">
                <div class="card-body">
                    <h5 class="card-title">تجهیز‌های در حال استفاده</h5>
                    <h2 class="card-text"><?= $assetUtilization['utilized_count'] ?? 0 ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white mb-4">
                <div class="card-body">
                    <h5 class="card-title">تجهیز‌های آماده</h5>
                    <h2 class="card-text"><?= $assetUtilization['available_count'] ?? 0 ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger text-white mb-4">
                <div class="card-body">
                    <h5 class="card-title">تجهیز‌های نیازمند تعمیر</h5>
                    <h2 class="card-text"><?= $assetUtilization['unavailable_count'] ?? 0 ?></h2>
                </div>
            </div>
        </div>
    </div>
    
    <!-- نمودارها و جداول -->
    <div class="row">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-chart-pie me-1"></i>
                    توزیع تجهیز‌ها بر اساس دسته‌بندی
                </div>
                <div class="card-body">
                    <canvas id="assetCategoryChart" width="100%" height="50"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-chart-bar me-1"></i>
                    وضعیت تجهیز‌ها
                </div>
                <div class="card-body">
                    <canvas id="assetStatusChart" width="100%" height="50"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <!-- تجهیز‌های نیازمند توجه -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-exclamation-triangle me-1"></i>
            تجهیز‌های نیازمند توجه
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="assetsNeedingAttentionTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>شناسه</th>
                            <th>نام</th>
                            <th>برچسب</th>
                            <th>دسته‌بندی</th>
                            <th>مدل</th>
                            <th>وضعیت</th>
                            <th>دلیل</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($assetsNeedingAttention as $asset): ?>
                        <tr>
                            <td><?= $asset['id'] ?></td>
                            <td><?= htmlspecialchars($asset['name']) ?></td>
                            <td><?= htmlspecialchars($asset['asset_tag']) ?></td>
                            <td><?= htmlspecialchars($asset['category_name']) ?></td>
                            <td><?= htmlspecialchars($asset['model_name']) ?></td>
                            <td>
                                <span class="badge <?= $asset['status'] == 'needs_repair' ? 'bg-danger' : 'bg-warning' ?>">
                                    <?= $asset['status'] == 'needs_repair' ? 'نیازمند تعمیر' : 'در حال استفاده' ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($asset['status'] == 'needs_repair'): ?>
                                    نیاز به تعمیر
                                <?php else: ?>
                                    <?= date('Y-m-d', strtotime($asset['warranty_expiry'])) ?> - انقضای گارانتی
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="/support_system/assets/show/<?= $asset['id'] ?>" class="btn btn-sm btn-info">مشاهده</a>
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
    var categoryCtx = document.getElementById('assetCategoryChart');
    var categoryData = <?= json_encode($assetCategoryDistribution) ?>;
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
    
    // نمودار وضعیت تجهیز‌ها
    var statusCtx = document.getElementById('assetStatusChart');
    var statusData = <?= json_encode($assetsByStatus) ?>;
    var statusLabels = statusData.map(item => {
        switch(item.status) {
            case 'available': return 'آماده';
            case 'assigned': return 'تخصیص داده شده';
            case 'in_use': return 'در حال استفاده';
            case 'needs_repair': return 'نیازمند تعمیر';
            case 'retired': return 'بازنشسته';
            case 'lost': return 'گم شده';
            default: return item.status;
        }
    });
    var statusValues = statusData.map(item => item.count);
    
    new Chart(statusCtx, {
        type: 'bar',
        data: {
            labels: statusLabels,
            datasets: [{
                label: 'تعداد تجهیز‌ها',
                data: statusValues,
                backgroundColor: [
                    '#1cc88a', '#4e73df', '#36b9cc', '#e74a3b', '#5a5c69', '#f6c23e'
                ]
            }]
        },
        options: {
            maintainAspectRatio: false,
            scales: {
                xAxes: [{
                    gridLines: {
                        display: false
                    },
                    ticks: {
                        maxTicksLimit: 6
                    }
                }],
                yAxes: [{
                    ticks: {
                        min: 0,
                        maxTicksLimit: 5,
                        beginAtZero: true
                    }
                }]
            },
            legend: {
                display: false
            }
        }
    });
});
</script>