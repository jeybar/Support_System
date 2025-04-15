<?php require_once __DIR__ . '/../header.php'; ?>

<div class="container mt-4">
    <h2>ایجاد دسترسی جدید</h2>

    <form method="POST" action="/support_system/permissions/store">
        <div class="mb-3">
            <label for="name" class="form-label">نام دسترسی</label>
            <input type="text" name="name" id="name" class="form-control" required>
        </div>

        <div class="mb-3">
            <label for="description" class="form-label">توضیحات</label>
            <textarea name="description" id="description" class="form-control"></textarea>
        </div>

        <button type="submit" class="btn btn-success">ذخیره</button>
        <a href="/support_system/permissions" class="btn btn-secondary">بازگشت</a>
    </form>
</div>