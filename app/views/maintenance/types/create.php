<?php include_once '../app/views/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <?php include_once '../app/views/components/page_header.php'; ?>
        
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">افزودن نوع نگهداری جدید</h5>
                </div>
                <div class="card-body">
                    <?php if (isset($_SESSION['message'])): ?>
                        <div class="alert alert-<?= $_SESSION['message_type'] ?> alert-dismissible fade show" role="alert">
                            <?= $_SESSION['message'] ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
                    <?php endif; ?>
                    
                    <form method="POST" action="/maintenance/types/store">
                        <div class="mb-3">
                            <label for="name" class="form-label">نام نوع نگهداری <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">توضیحات</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="interval_days" class="form-label">دوره زمانی (روز) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="interval_days" name="interval_days" min="1" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="checklist" class="form-label">چک‌لیست (هر مورد در یک خط)</label>
                            <textarea class="form-control" id="checklist" name="checklist" rows="5"></textarea>
                            <small class="form-text text-muted">موارد چک‌لیست را هر کدام در یک خط وارد کنید</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="asset_categories" class="form-label">دسته‌بندی‌های تجهیز مرتبط</label>
                            <select class="form-control" id="asset_categories" name="asset_categories[]" multiple>
                                <?php foreach ($assetCategories as $category): ?>
                                <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted">برای انتخاب چند مورد، کلید Ctrl را نگه دارید</small>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="/maintenance/types" class="btn btn-secondary">انصراف</a>
                            <button type="submit" class="btn btn-primary">ذخیره</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../app/views/footer.php'; ?>