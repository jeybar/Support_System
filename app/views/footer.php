<!-- مودال ثبت درخواست -->
<?php include 'components/create_ticket_modal.php'; ?>
<!--<script src="/assets/js/search.js"></script> -->

</main>
    <footer class="footer text-center py-3 mt-5">
        <p class="mb-0">© 2025 سیستم نگهداری و تعمیرات  | طراحی‌شده توسط تیم فناوری اطلاعات</p>

        <div id="loading" style="display: none;">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">در حال بارگذاری...</span>
            </div>
        </div>

    </footer>
    <link rel="stylesheet" href="/assets/css/footer.css"> <!-- اضافه کردن فایل CSS فوتر -->

    <script src="https://cdn.jsdelivr.net/npm/cart.js"></script>
    <script src="/assets/js/dashboard-modir-charts.js"></script>
    <script src="/assets/js/employeeNumberInput.js"></script>
    <script src="/assets/js/tickets-creat.js"></script>


    <!-- فایل های جاوااسکریپت مربوط به صفحه -->
    <script>
    // ارسال مقادیر پلنت و واحد به جاوااسکریپت
        const userPlant = "<?php echo $_SESSION['plant'] ?? ''; ?>";
        const userUnit = "<?php echo $_SESSION['unit'] ?? ''; ?>";
    </script>
  
    <!--  پیغام اطمینان از حذف لیست دسترسی‌ها و اطلاعات نقش در مودال -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.querySelectorAll('form[action*="/roles/delete"]').forEach(form => {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                Swal.fire({
                    title: 'آیا مطمئن هستید؟',
                    text: 'این عملیات قابل بازگشت نیست!',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'بله، حذف کن!',
                    cancelButtonText: 'لغو'
                }).then((result) => {
                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
            });
        });
    </script>

    <!--  بارگذاری لیست دسترسی‌ها و اطلاعات نقش در مودال -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.assign-permissions-btn').forEach(button => {
                button.addEventListener('click', function () {
                    const roleId = this.getAttribute('data-role-id');
                    const roleName = this.getAttribute('data-role-name');
                    
                    // تنظیم عنوان مودال
                    document.getElementById('assignPermissionsModalLabel').textContent = `تخصیص دسترسی‌ها به نقش: ${roleName}`;
                    
                    // تنظیم مقدار مخفی نقش
                    document.getElementById('modal-role-id').value = roleId;

                    // ارسال درخواست Ajax برای دریافت لیست دسترسی‌ها
                    fetch(`/support_system/roles/get_permissions/${roleId}`)
                        .then(response => response.json())
                        .then(data => {
                            const permissionsList = document.getElementById('permissions-list');
                            permissionsList.innerHTML = ''; // پاک کردن محتوای قبلی

                            // ایجاد چک‌باکس‌ها برای دسترسی‌ها
                            data.permissions.forEach(permission => {
                                const isChecked = data.rolePermissions.includes(permission.id) ? 'checked' : '';
                                permissionsList.innerHTML += `
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" name="permissions[]" value="${permission.id}" ${isChecked}>
                                        <label class="form-check-label">${permission.name}</label>
                                    </div>
                                `;
                            });
                        })
                        .catch(error => {
                            console.error('خطا در دریافت دسترسی‌ها:', error);
                        });
                });
            });
        });

        // اطمینان از اجرای کد پس از بارگذاری کامل DOM
        document.addEventListener('DOMContentLoaded', function () {

            // کد برای تخریب کامل مودال
            const modalElements = document.querySelectorAll('.modal');

            modalElements.forEach(modal => {
                modal.addEventListener('hidden.bs.modal', function () {
                    // حذف Backdrop باقی‌مانده
                    const backdrop = document.querySelector('.modal-backdrop');
                    if (backdrop) {
                        backdrop.remove();
                    }
                    // حذف کلاس‌های اضافی از <body>
                    document.body.classList.remove('modal-open');
                    document.body.style.overflow = '';
                });
            });

            // انتخاب فرم assignPermissionsForm
            const assignPermissionsForm = document.getElementById('assignPermissionsForm');

            // بررسی وجود فرم قبل از اضافه کردن Event Listener
            if (assignPermissionsForm) {
                assignPermissionsForm.addEventListener('submit', function (e) {
                    e.preventDefault(); // جلوگیری از ارسال پیش‌فرض فرم

                    const form = e.target; // فرم ارسال‌شده
                    const formData = new FormData(form); // جمع‌آوری داده‌های فرم

                    // ارسال درخواست Ajax
                    fetch('/support_system/roles/update_permissions', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // نمایش پیام موفقیت
                            Swal.fire({
                                title: 'موفقیت!',
                                text: data.message,
                                icon: 'success',
                                confirmButtonText: 'باشه'
                            });

                            // به‌روزرسانی ستون دسترسی‌ها در جدول نقش‌ها
                            const roleId = formData.get('role_id'); // شناسه نقش
                            const updatedPermissions = data.updatedPermissions; // دسترسی‌های به‌روزرسانی‌شده

                            // پیدا کردن سطر مربوط به نقش در جدول
                            const roleRow = document.querySelector(`tr[data-role-id="${roleId}"]`);
                            if (roleRow) {
                                // به‌روزرسانی ستون دسترسی‌ها
                                const permissionsCell = roleRow.querySelector('.permissions-cell');
                                if (permissionsCell) {
                                    permissionsCell.textContent = updatedPermissions.join(', ');
                                }
                            }

                            // بستن مودال
                            const modal = bootstrap.Modal.getInstance(document.getElementById('assignPermissionsModal'));
                            modal.hide();
                        } else {
                            // نمایش پیام خطا یا عدم تغییرات
                            Swal.fire({
                                title: 'خطا!',
                                text: data.message || 'خطا در به‌روزرسانی دسترسی‌ها.',
                                icon: 'error',
                                confirmButtonText: 'باشه'
                            });
                        }
                    })
                    .catch(error => {
                        console.error('خطا در ارسال درخواست:', error);
                        Swal.fire({
                            title: 'خطا!',
                            text: 'مشکلی در ارسال درخواست رخ داده است. لطفاً دوباره تلاش کنید.',
                            icon: 'error',
                            confirmButtonText: 'باشه'
                        });
                    });
                });
            } else {
                console.warn('فرم assignPermissionsForm در این صفحه وجود ندارد.');
            }
        });
    </script>

    <script>
        AOS.init();

        document.addEventListener('DOMContentLoaded', () => {
            const loading = document.getElementById('loading');
            loading.style.display = 'block';
            setTimeout(() => {
                loading.style.display = 'none';
            }, 1000); // شبیه‌سازی تأخیر بارگذاری
        });

        const toggleButton = document.getElementById('darkModeToggle');
        if (toggleButton) {
            toggleButton.addEventListener('click', () => {
                document.body.classList.toggle('dark-mode');
            });
        }
    </script>

    <!--  ویرایش کاربر-->
    <script>
        // اطمینان از اجرای کد جاوااسکریپت پس از بارگذاری کامل DOM
        document.addEventListener('DOMContentLoaded', function () {
            const editButtons = document.querySelectorAll('.edit-user-btn');

            let initialFormData = {}; // برای ذخیره اطلاعات اولیه فرم

            // اضافه کردن Event Listener به دکمه‌های ویرایش
            editButtons.forEach(button => {
                button.addEventListener('click', function () {
                    const userId = this.getAttribute('data-id'); // دریافت شناسه کاربر از دکمه

                    // ارسال درخواست AJAX برای دریافت اطلاعات کاربر
                    fetch(`/users/getUserDetails/${userId}`)
                        .then(response => {
                            if (!response.ok) {
                                throw new Error(`HTTP error! status: ${response.status}`);
                            }
                            return response.json();
                        })
                        .then(data => {
                            if (data.error) {
                                alert(data.error);
                            } else {
                                // پر کردن فیلدهای فرم با اطلاعات کاربر
                                document.getElementById('user_id').value = data.id;
                                document.getElementById('username').value = data.username;
                                document.getElementById('fullname').value = data.fullname;
                                //document.getElementById('role').value = data.role_id;
                                //document.getElementById('user_type').value = data.user_type;

                                // انتخاب نقش فعلی کاربر
                                const roleSelect = document.getElementById('role');
                                Array.from(roleSelect.options).forEach(option => {
                                    option.selected = option.value === data.role_id.toString();
                                });

                                // انتخاب نوع کاربر فعلی
                                const userTypeSelect = document.getElementById('user_type');
                                Array.from(userTypeSelect.options).forEach(option => {
                                    option.selected = option.value === data.user_type;
                                });

                                // ذخیره اطلاعات اولیه فرم برای مقایسه تغییرات
                                initialFormData = {
                                    username: data.username,
                                    fullname: data.fullname,
                                    role_id: data.role_id.toString(),
                                    user_type: data.user_type,
                                };

                                // نمایش مودال
                                const editUserModal = new bootstrap.Modal(document.getElementById('editUserModal'));
                                editUserModal.show();
                                }
                            })
                    
                        .catch(error => {
                            console.error('Error:', error);
                            alert('مشکلی در دریافت اطلاعات کاربر رخ داده است. لطفاً دوباره تلاش کنید.');
                        });
                });
            });

            // بررسی تغییرات فرم قبل از ارسال
            const editUserForm = document.getElementById('editUserForm');
            if (editUserForm) { // بررسی وجود عنصر
                editUserForm.addEventListener('submit', function (event) {
                    event.preventDefault(); // جلوگیری از ارسال فرم به صورت پیش‌فرض

                    // دریافت مقادیر فعلی فرم
                    const currentFormData = {
                        username: document.getElementById('username').value,
                        fullname: document.getElementById('fullname').value,
                        role_id: document.getElementById('role').value,
                        user_type: document.getElementById('user_type').value,
                    };

                    // مقایسه اطلاعات اولیه و فعلی
                    const isFormChanged = Object.keys(initialFormData).some(key => initialFormData[key] !== currentFormData[key]);

                    if (!isFormChanged) {
                        alert('اطلاعاتی برای تغییر وجود ندارد.');
                        return; // جلوگیری از ارسال فرم
                    }

                    // اگر تغییرات وجود داشته باشد، فرم ارسال می‌شود
                    editUserForm.submit();
                });
            } else {
                console.error('فرم ویرایش اطلاعات کاربر پیدا نشد!');
            }
        });

        // انتخاب تمامی دکمه‌های ویرایش
        const editButtons = document.querySelectorAll('.edit-role-btn');
        if (editButtons.length > 0) {
            // اضافه کردن Event Listener به دکمه‌ها
            editButtons.forEach(button => {
                button.addEventListener('click', function () {
                    // دریافت اطلاعات نقش از دکمه
                    const roleId = this.getAttribute('data-id');
                    const roleName = this.getAttribute('data-name'); // استفاده از role_name
                    const roleDescription = this.getAttribute('data-description');

                    // پر کردن اطلاعات در فرم مودال
                    document.getElementById('edit-role-id').value = roleId;
                    document.getElementById('edit-role-name').value = roleName; // استفاده از role_name
                    document.getElementById('edit-role-description').value = roleDescription;

                    if (roleIdInput && roleNameInput && roleDescriptionInput) {
                        roleIdInput.value = roleId;
                        roleNameInput.value = roleName;
                        roleDescriptionInput.value = roleDescription;
                    } else {
                        console.error('عناصر مودال یافت نشدند.');
                    }
                    
                });
            
            })
        };
    </script>
</body>
</html>