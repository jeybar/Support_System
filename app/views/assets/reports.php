<?php require_once __DIR__ . '/../layouts/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <?php require_once __DIR__ . '/../layouts/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">گزارش‌های تجهیز‌ها</h1>
            </div>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger"><?= $_SESSION['error'] ?></div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success"><?= $_SESSION['success'] ?></div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
            
            <div class="row">
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5>گزارش‌های وضعیت</h5>
                        </div>
                        <div class="card-body">
                            <div class="list-group">
                                <a href="/support_system/assets/attention_needed" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                    تجهیز‌های نیازمند توجه
                                    <span class="badge bg-danger rounded-pill">
                                        <i class="bi bi-exclamation-triangle"></i>
                                    </span>
                                </a>
                                <a href="/support_system/assets/expiring_warranty" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                    تجهیز‌های با گارانتی رو به اتمام
                                    <span class="badge bg-warning rounded-pill">
                                        <i class="bi bi-calendar-x"></i>
                                    </span>
                                </a>
                                <a href="/support_system/maintenance/overdue" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                    سرویس‌های ادواری معوق
                                    <span class="badge bg-danger rounded-pill">
                                        <i class="bi bi-tools"></i>
                                    </span>
                                </a>
                                <a href="/support_system/maintenance/upcoming" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                    سرویس‌های ادواری آینده
                                    <span class="badge bg-info rounded-pill">
                                        <i class="bi bi-calendar-check"></i>
                                    </span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5>گزارش‌های آماری</h5>
                        </div>
                        <div class="card-body">
                            <div class="list-group">
                                <a href="/support_system/assets/statistics" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                    آمار و نمودارهای تجهیز‌ها
                                    <span class="badge bg-primary rounded-pill">
                                        <i class="bi bi-bar-chart"></i>
                                    </span>
                                </a>
                                <a href="/support_system/assets?status=active" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                    تجهیز‌های فعال
                                    <span class="badge bg-success rounded-pill">
                                        <i class="bi bi-check-circle"></i>
                                    </span>
                                </a>
                                <a href="/support_system/assets?status=in_repair" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                    تجهیز‌های در حال تعمیر
                                    <span class="badge bg-warning rounded-pill">
                                        <i class="bi bi-wrench"></i>
                                    </span>
                                </a>
                                <a href="/support_system/assets?status=retired" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                    تجهیز‌های بازنشسته
                                    <span class="badge bg-secondary rounded-pill">
                                        <i class="bi bi-archive"></i>
                                    </span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-12 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5>گزارش‌های سفارشی</h5>
                        </div>
                        <div class="card-body">
                            <form action="/support_system/assets" method="GET" class="row g-3">
                                <div class="col-md-3">
                                    <label for="category" class="form-label">دسته‌بندی</label>
                                    <select name="category" id="category" class="form-select">
                                        <option value="">همه دسته‌بندی‌ها</option>
                                        <!-- گزینه‌های دسته‌بندی از دیتابیس بارگذاری می‌شوند -->
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="status" class="form-label">وضعیت</label>
                                    <select name="status" id="status" class="form-select">
                                        <option value="">همه وضعیت‌ها</option>
                                        <option value="active">فعال</option>
                                        <option value="in_repair">در حال تعمیر</option>
                                        <option value="retired">بازنشسته</option>
                                        <option value="lost">مفقود شده</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="search" class="form-label">جستجو</label>
                                    <input type="text" name="search" id="search" class="form-control" placeholder="برچسب تجهیز، شماره سریال، مدل یا کاربر">
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="bi bi-search"></i> جستجو
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // بارگذاری دسته‌بندی‌ها
        fetch('/support_system/asset_categories')
            .then(response => response.json())
            .then(data => {
                const categorySelect = document.getElementById('category');
                data.forEach(category => {
                    const option = document.createElement('option');
                    option.value = category.id;
                    option.textContent = category.name;
                    categorySelect.appendChild(option);
                });
            })
            .catch(error => console.error('Error loading categories:', error));
    });
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>