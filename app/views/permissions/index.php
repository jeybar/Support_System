<?php
$pageTitle = "مدیریت نقش‌ها"; // مقداردهی به $pageTitle

 require_once __DIR__ . '/../header.php'; ?>

<div class="container mt-4">
    <h2>مدیریت دسترسی‌ها</h2>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger">
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <a href="/support_system/permissions/create" class="btn btn-primary mb-3">ایجاد دسترسی جدید</a>

    <table class="table table-bordered">
        <thead>
            <tr>
                <th>شناسه</th>
                <th>نام دسترسی</th>
                <th>توضیحات</th>
                <th>عملیات</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($permissions as $permission): ?>
                <tr>
                    <td><?php echo $permission['id']; ?></td>
                    <td><?php echo htmlspecialchars($permission['name']); ?></td>
                    <td><?php echo htmlspecialchars($permission['description']); ?></td>
                    <td>
                        <form action="/support_system/permissions/delete/<?php echo $permission['id']; ?>" method="POST" style="display:inline;">
                            <button type="submit" class="btn btn-danger btn-sm">حذف</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>