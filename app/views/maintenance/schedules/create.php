<?php include_once '../app/views/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <?php include_once '../app/views/components/page_header.php'; ?>
        
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">افزودن برنامه نگهداری جدید</h5>
                </div>
                <div class="card-body">
                    <?php if (isset($_SESSION['message'])): ?>
                        <div class="alert alert-<?= $_SESSION['message_type'] ?> alert-dismissible fade show" role="alert">
                            <?= $_SESSION['message'] ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
                    <?php endif; ?>
                    
                    <form method="POST" action="/maintenance/schedules/store">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="asset_id" class="form-label">تجهیز <span class="text-danger">*</span></label>
                                <select class="form-control" id="asset_id" name="asset_id" required>
                                    <option value="">انتخاب تجهیز</option>
                                    <?php foreach ($assets as $asset): ?>
                                    <option value="<?= $asset['id'] ?>"><?= htmlspecialchars($asset['name']) ?> (<?= htmlspecialchars($asset['asset_tag']) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="maintenance_type_id" class="form-label">نوع نگهداری <span class="text-danger">*</span></label>
                                <select class="form-control" id="maintenance_type_id" name="maintenance_type_id" required>
                                    <option value="">انتخاب نوع نگهداری</option>
                                    <?php foreach ($maintenanceTypes as $type): ?>
                                    <option value="<?= $type['id'] ?>" data-interval="<?= $type['interval_days'] ?>"><?= htmlspecialchars($type['name']) ?> (هر <?= $type['interval_days'] ?> روز)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="start_date" class="form-label">تاریخ شروع <span class="text-danger">*</span></label>
                                <input type="text" class="form-control datepicker" id="start_date" name="start_date" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="end_date" class="form-label">تاریخ پایان</label>
                                <input type="text" class="form-control datepicker" id="end_date" name="end_date">
                                <small class="form-text text-muted">در صورت خالی بودن، برنامه نگهداری بدون تاریخ پایان خواهد بود</small>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="notes" class="form-label">یادداشت‌ها</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="notify_user" name="notify_user" value="1" checked>
                                <label class="form-check-label" for="notify_user">
                                    اطلاع‌رسانی به کاربر تجهیز
                                </label>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="/maintenance/schedules" class="btn btn-secondary">انصراف</a>
                            <button type="submit" class="btn btn-primary">ذخیره</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        // تنظیم تاریخ شمسی
        $('.datepicker').persianDatepicker({
            format: 'YYYY/MM/DD',
            autoClose: true
        });
        
        // نمایش انواع نگهداری مرتبط با تجهیز انتخاب شده
        $('#asset_id').on('change', function() {
            const assetId = $(this).val();
            if (assetId) {
                $.ajax({
                    url: '/maintenance/get-types-by-asset/' + assetId,
                    type: 'GET',
                    success: function(response) {
                        const types = JSON.parse(response);
                        let options = '<option value="">انتخاب نوع نگهداری</option>';
                        types.forEach(function(type) {
                            options += `<option value="${type.id}" data-interval="${type.interval_days}">${type.name} (هر ${type.interval_days} روز)</option>`;
                        });
                        $('#maintenance_type_id').html(options);
                    }
                });
            }
        });
    });
</script>

<?php include_once '../app/views/footer.php'; ?>