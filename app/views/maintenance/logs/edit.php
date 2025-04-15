<?php include_once '../app/views/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <?php include_once '../app/views/components/page_header.php'; ?>
        
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">ویرایش سابقه نگهداری</h5>
                </div>
                <div class="card-body">
                    <?php if (isset($_SESSION['message'])): ?>
                        <div class="alert alert-<?= $_SESSION['message_type'] ?> alert-dismissible fade show" role="alert">
                            <?= $_SESSION['message'] ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
                    <?php endif; ?>
                    
                    <form method="POST" action="/maintenance/logs/update/<?= $maintenanceLog['id'] ?>" enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">تجهیز</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($asset['name']) ?> (<?= htmlspecialchars($asset['asset_tag']) ?>)" readonly>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">نوع نگهداری</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($maintenanceType['name']) ?>" readonly>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="maintenance_date" class="form-label">تاریخ انجام <span class="text-danger">*</span></label>
                                <input type="text" class="form-control datepicker" id="maintenance_date" name="maintenance_date" value="<?= format_date($maintenanceLog['maintenance_date']) ?>" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="technician_id" class="form-label">تکنسین <span class="text-danger">*</span></label>
                                <select class="form-control" id="technician_id" name="technician_id" required>
                                    <option value="">انتخاب تکنسین</option>
                                    <?php foreach ($technicians as $technician): ?>
                                    <option value="<?= $technician['id'] ?>" <?= $technician['id'] == $maintenanceLog['technician_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($technician['name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="status" class="form-label">وضعیت <span class="text-danger">*</span></label>
                            <select class="form-control" id="status" name="status" required>
                                <option value="completed" <?= $maintenanceLog['status'] == 'completed' ? 'selected' : '' ?>>تکمیل شده</option>
                                <option value="incomplete" <?= $maintenanceLog['status'] == 'incomplete' ? 'selected' : '' ?>>ناقص</option>
                                <option value="failed" <?= $maintenanceLog['status'] == 'failed' ? 'selected' : '' ?>>ناموفق</option>
                            </select>
                        </div>
                        
                        <?php if (isset($checklistItems) && !empty($checklistItems)): ?>
                            <div class="mb-3">
                                <label class="form-label">چک‌لیست</label>
                                <div class="card">
                                    <div class="card-body">
                                        <?php foreach ($checklistItems as $index => $item): ?>
                                            <div class="form-check mb-2">
                                                <input class="form-check-input" type="checkbox" name="checklist_items[<?= $index ?>]" id="checklist_item_<?= $index ?>" value="1" <?= isset($completedItems[$index]) && $completedItems[$index] ? 'checked' : '' ?>>
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
                            <textarea class="form-control" id="notes" name="notes" rows="3"><?= htmlspecialchars($maintenanceLog['notes']) ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="cost" class="form-label">هزینه (تومان)</label>
                            <input type="number" class="form-control" id="cost" name="cost" min="0" value="<?= $maintenanceLog['cost'] ?>">
                        </div>
                        
                        <?php if (!empty($attachments)): ?>
                            <div class="mb-3">
                                <label class="form-label">پیوست‌های موجود</label>
                                <div class="row">
                                    <?php foreach ($attachments as $attachment): ?>
                                        <div class="col-md-4 mb-2">
                                            <div class="card">
                                                <div class="card-body">
                                                    <p class="mb-1"><?= htmlspecialchars($attachment['filename']) ?></p>
                                                    <div class="d-flex justify-content-between">
                                                        <a href="/uploads/maintenance/<?= $attachment['filename'] ?>" class="btn btn-sm btn-info" target="_blank">مشاهده</a>
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" name="delete_attachments[]" id="delete_attachment_<?= $attachment['id'] ?>" value="<?= $attachment['id'] ?>">
                                                            <label class="form-check-label" for="delete_attachment_<?= $attachment['id'] ?>">
                                                                حذف
                                                            </label>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label for="attachments" class="form-label">افزودن پیوست‌های جدید</label>
                            <input type="file" class="form-control" id="attachments" name="attachments[]" multiple>
                            <small class="form-text text-muted">می‌توانید چندین فایل را انتخاب کنید</small>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="/maintenance/logs" class="btn btn-secondary">انصراف</a>
                            <button type="submit" class="btn btn-primary">به‌روزرسانی</button>
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
    });
</script>

<?php include_once '../app/views/footer.php'; ?>