<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ورود به سیستم</title>
    <link href="/assets/css/login.css" rel="stylesheet">
    <!-- لینک به فایل CSS Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- لینک Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <!-- لینک به فایل JavaScript Bootstrap -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>
    <div class="login-container">
        <!-- لوگو و توضیحات -->
        <div class="text-center mb-4">
            <img src="/assets/images/logo.png" alt="لوگو شرکت" class="logo">
            <p class="description">سیستم نگهداری و تعمیرات واحد فناوری اطلاعات<br>شرکت فولاد بوتیای ایرانیان</p>
        </div>

        <!-- فرم ورود -->
        <h1 class="h5 mb-4">ورود به سیستم</h1>
        <form action="/support_system/login/authenticate" method="POST">
            <div class="mb-3">
                <label for="username" class="form-label">نام کاربری</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                    <input type="text" name="username" id="username" class="form-control" placeholder="نام کاربری" required>
                </div>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">رمز عبور</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                    <input type="password" name="password" id="password" class="form-control" placeholder="رمز عبور" required>
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-100">ورود</button>
        </form>

        <!-- لینک فراموشی رمز عبور -->
        <a href="#" class="d-block mt-3">فراموشی رمز عبور؟</a>

        <!-- پیام خطا (در صورت وجود) -->
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger mt-3">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>
    </div>

</body>
</html>