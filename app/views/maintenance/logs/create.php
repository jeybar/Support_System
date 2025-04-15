<?php include_once '../app/views/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <?php include_once '../app/views/components/page_header.php'; ?>
        
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">ثبت سابقه نگهداری جدید</h5>
                </div>
                <div class="card-body">
                    <?php if (isset($_SESSION['message'])): ?>
                        <div class="alert alert-<?= $_SESSION['message_type'] ?> alert-dismissible fade show" role="alert">
                            <?= $_SESSION['message'] ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
                    <?php endif; ?>
                    
                    <form method="POST" action="/maintenance/logs/store" enctype="multipart/form-data">
                        <?php if (isset($scheduleId)): ?>
                            <input type="hidden" name="schedule_id" value="<?= $scheduleId ?>">
                        <?php endif; ?>
                        
                        <div class="row">
                            <?php if (!isset($scheduleId)): ?>
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
                                        <option value="<?= $type['id'] ?>"><?= htmlspecialchars($type['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php else: ?>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">تجهیز</label>
                                    <input type="text" class="form-control" value="<?= htmlspecialchars($asset['name']) ?> (<?= htmlspecialchars($asset['asset_tag']) ?>)" readonly>
                                    <input type="hidden" name="asset_id" value="<?= $asset['id'] ?>">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">نوع نگهداری</label>
                                    <input type="text" class="form-control" value="<?= htmlspecialchars($maintenanceType['name']) ?>" readonly>
                                    <input type="hidden" name="maintenance_type_id" value="<?= $maintenanceType['id'] ?>">
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="maintenance_date" class="form-label">تاریخ انجام <span class="text-danger">*</span></label>
                                <input type="text" class="form-control datepicker" id="maintenance_date" name="maintenance_date" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="technician_id" class="form-label">تکنسین <span class="text-danger">*</span></label>
                                <select class="form-control" id="technician_id" name="technician_id" required>
                                    <option value="">انتخاب تکنسین</option>
                                    <?php foreach ($technicians as $technician): ?>
                                    <option value="<?= $technician['id'] ?>"><?= htmlspecialchars($technician['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="status" class="form-label">وضعیت <span class="text-danger">*</span></label>
                            <select class="form-control" id="status" name="status" required>
                                <option value="completed">تکمیل شده</option>
                                <option value="incomplete">ناقص</option>
                                <option value="failed">ناموفق</option>
                            </select>
                        </div>
                        
                        <?php if (isset($checklistItems) && !empty($checklistItems)): ?>
                            <div class="mb-3">
                                <label class="form-label">چک‌لیست</label>
                                <div class="card">
                                    <div class="card-body">
                                        <?php foreach ($checklistItems as $index => $item): ?>
                                            <div class="form-check mb-2">
                                                <input class="form-check-input" type="checkbox" name="checklist_items[<?= $index ?>]" id="checklist_item_<?= $index ?>" value="1">
                                                <label class="form-check-label" for="checklist_item_<?= $index ?>">
                                                    <?= htmlspecialchars($item) ?>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label for="notes" class="form-label">توضیحات</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="cost" class="form-label">هزینه (تومان)</label>
                            <input type="number" class="form-control" id="cost" name="cost" min="0">
                        </div>
                        
                        <div class="mb-3">
                            <label for="attachments" class="form-label">پیوست‌ها</label>
                            <input type="file" class="form-control" id="attachments" name="attachments[]" multiple>
                            <small class="form-text text-muted">می‌توانید چندین فایل را انتخاب کنید</small>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="/maintenance/logs" class="btn btn-secondary">انصراف</a>
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
                            options += `<option value="${type.id}">${type.name}</option>`;
                        });
                        $('#maintenance_type_id').html(options);
                    }
                });
            }
        });
    });
</script>

<?php include_once '../app/views/footer.php'; ?>