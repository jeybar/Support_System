<?php require_once __DIR__ . '/../layouts/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <?php require_once __DIR__ . '/../layouts/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">مدیریت مشخصات سخت‌افزاری</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="/support_system/assets/show/<?= $asset['id'] ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-right"></i> بازگشت به جزئیات تجهیز
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
                            <h5>اطلاعات تجهیز</h5>
                        </div>
                        <div class="col-md-6 text-end">
                            <span class="badge bg-info"><?= $asset['asset_tag'] ?></span>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>مدل:</strong> <?= $asset['model_name'] ?></p>
                            <p><strong>دسته‌بندی:</strong> <?= $asset['category_name'] ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>شماره سریال:</strong> <?= $asset['serial_number'] ?></p>
                            <p><strong>وضعیت:</strong> <?= $asset['status'] ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <div class="row">
                        <div class="col-md-6">
                            <h5>مشخصات سخت‌افزاری</h5>
                        </div>
                        <div class="col-md-6 text-end">
                            <?php if (!empty($defaultSpecs)): ?>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="loadDefaultSpecs">
                                    <i class="bi bi-lightning-charge"></i> بارگذاری مشخصات پیش‌فرض
                                </button>
                            <?php endif; ?>
                            <button type="button" class="btn btn-sm btn-outline-success" id="addSpecRow">
                                <i class="bi bi-plus-lg"></i> افزودن مشخصه
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <form action="/support_system/assets/save_specs" method="POST">
                        <input type="hidden" name="asset_id" value="<?= $asset['id'] ?>">
                        <input type="hidden" name="deleted_specs" id="deletedSpecs" value="">
                        
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover" id="specsTable">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 40%">نام مشخصه</th>
                                        <th style="width: 50%">مقدار</th>
                                        <th style="width: 10%">عملیات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($specifications)): ?>
                                        <tr id="noSpecsRow">
                                            <td colspan="3" class="text-center">هیچ مشخصه‌ای ثبت نشده است.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($specifications as $spec): ?>
                                            <tr data-spec-id="<?= $spec['id'] ?>">
                                                <td>
                                                    <input type="hidden" name="spec_id[]" value="<?= $spec['id'] ?>">
                                                    <input type="text" class="form-control" name="spec_name[]" value="<?= $spec['spec_name'] ?>" required>
                                                </td>
                                                <td>
                                                    <input type="text" class="form-control" name="spec_value[]" value="<?= $spec['spec_value'] ?>" required>
                                                </td>
                                                <td class="text-center">
                                                    <button type="button" class="btn btn-sm btn-danger delete-spec">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="mt-3 text-center">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> ذخیره مشخصات
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <?php if (!empty($defaultSpecs)): ?>
                <div class="card mt-4">
                    <div class="card-header">
                        <h5>مشخصات پیش‌فرض مدل</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>نام مشخصه</th>
                                        <th>مقدار پیش‌فرض</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($defaultSpecs as $spec): ?>
                                        <tr>
                                            <td><?= $spec['spec_name'] ?></td>
                                            <td><?= $spec['spec_value'] ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const specsTable = document.getElementById('specsTable');
        const noSpecsRow = document.getElementById('noSpecsRow');
        const deletedSpecs = document.getElementById('deletedSpecs');
        const deletedSpecsArray = [];
        
        // افزودن ردیف جدید
        document.getElementById('addSpecRow').addEventListener('click', function() {
            if (noSpecsRow) {
                noSpecsRow.remove();
            }
            
            const newRow = document.createElement('tr');
            newRow.innerHTML = `
                <td>
                    <input type="hidden" name="spec_id[]" value="">
                    <input type="text" class="form-control" name="spec_name[]" required>
                </td>
                <td>
                    <input type="text" class="form-control" name="spec_value[]" required>
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-danger delete-spec">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            `;
            
            specsTable.querySelector('tbody').appendChild(newRow);
            
            // اضافه کردن رویداد حذف به دکمه جدید
            newRow.querySelector('.delete-spec').addEventListener('click', handleDeleteSpec);
        });
        
        // حذف ردیف
        function handleDeleteSpec() {
            const row = this.closest('tr');
            const specId = row.dataset.specId;
            
            if (specId) {
                deletedSpecsArray.push(specId);
                deletedSpecs.value = deletedSpecsArray.join(',');
            }
            
            row.remove();
            
            // اگر هیچ ردیفی باقی نماند، پیام "هیچ مشخصه‌ای ثبت نشده است" را نمایش دهید
            if (specsTable.querySelector('tbody').children.length === 0) {
                const noSpecsRow = document.createElement('tr');
                noSpecsRow.id = 'noSpecsRow';
                noSpecsRow.innerHTML = '<td colspan="3" class="text-center">هیچ مشخصه‌ای ثبت نشده است.</td>';
                specsTable.querySelector('tbody').appendChild(noSpecsRow);
            }
        }
        
        // اضافه کردن رویداد حذف به همه دکمه‌های حذف موجود
        document.querySelectorAll('.delete-spec').forEach(button => {
            button.addEventListener('click', handleDeleteSpec);
        });
        
        // بارگذاری مشخصات پیش‌فرض
        if (document.getElementById('loadDefaultSpecs')) {
            document.getElementById('loadDefaultSpecs').addEventListener('click', function() {
                if (confirm('آیا مطمئن هستید که می‌خواهید مشخصات پیش‌فرض را بارگذاری کنید؟ این کار مشخصات موجود را جایگزین می‌کند.')) {
                    // حذف همه ردیف‌های موجود
                    const rows = specsTable.querySelectorAll('tbody tr:not(#noSpecsRow)');
                    rows.forEach(row => {
                        const specId = row.dataset.specId;
                        if (specId) {
                            deletedSpecsArray.push(specId);
                        }
                        row.remove();
                    });
                    
                    deletedSpecs.value = deletedSpecsArray.join(',');
                    
                    // اضافه کردن مشخصات پیش‌فرض
                    <?php foreach ($defaultSpecs as $spec): ?>
                        const newRow = document.createElement('tr');
                        newRow.innerHTML = `
                            <td>
                                <input type="hidden" name="spec_id[]" value="">
                                <input type="text" class="form-control" name="spec_name[]" value="<?= $spec['spec_name'] ?>" required>
                            </td>
                            <td>
                                <input type="text" class="form-control" name="spec_value[]" value="<?= $spec['spec_value'] ?>" required>
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-danger delete-spec">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        `;
                        
                        specsTable.querySelector('tbody').appendChild(newRow);
                        
                        // اضافه کردن رویداد حذف به دکمه جدید
                        newRow.querySelector('.delete-spec').addEventListener('click', handleDeleteSpec);
                    <?php endforeach; ?>
                    
                    // حذف پیام "هیچ مشخصه‌ای ثبت نشده است" اگر وجود داشته باشد
                    if (document.getElementById('noSpecsRow')) {
                        document.getElementById('noSpecsRow').remove();
                    }
                }
            });
        }
    });
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>