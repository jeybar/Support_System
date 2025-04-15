document.addEventListener('DOMContentLoaded', function () {
    // انتخاب عناصر مودال
    const referModal = document.getElementById('referTicketModal');
    if (!referModal) {
        console.error('Modal element not found');
        return;
    }

    const referForm = document.getElementById('referForm');
    const assigneeSelect = document.getElementById('assignee');
    const reasonField = document.getElementById('reason');

    // لاگ برای اشکال‌زدایی
    console.log('Modal elements:', {
        referModal,
        referForm,
        assigneeSelect,
        reasonField
    });

    // اضافه کردن event listener برای ارسال فرم
    if (referForm) {
        referForm.addEventListener('submit', function (e) {
            e.preventDefault(); // جلوگیری از ارسال فرم به صورت پیش‌فرض
            
            console.log('Form submitted');
            console.log('Form action:', this.action);
            console.log('Form method:', this.method);
            console.log('Ticket ID:', this.querySelector('input[name="ticket_id"]').value);
            console.log('Assignee:', assigneeSelect.value);
            console.log('Reason:', reasonField.value);
            
            // بررسی اعتبارسنجی فرم
            if (!assigneeSelect.value || !reasonField.value.trim()) {
                alert('لطفاً تمام فیلدها را پر کنید!');
                return;
            }
            
            // ارسال فرم با استفاده از Fetch API
            fetch(this.action, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams(new FormData(this))
            })
            .then(response => {
                console.log('Response status:', response.status);
                if (!response.ok) {
                    throw new Error('خطا در ارسال درخواست');
                }
                return response.text();
            })
            .then(data => {
                console.log('Response data:', data);
                // بستن مودال
                const bootstrapModal = bootstrap.Modal.getInstance(referModal);
                if (bootstrapModal) bootstrapModal.hide();
                
                // نمایش پیام موفقیت
                alert('درخواست با موفقیت ارجاع داده شد.');
                
                // بارگذاری مجدد صفحه
                window.location.reload();
            })
            .catch(error => {
                console.error('Error:', error);
                alert('خطا در ارجاع درخواست: ' + error.message);
            });
        });
    }

    // دریافت لیست پشتیبان‌ها از سرور
    if (assigneeSelect) {
        console.log('Fetching support staff list...');
        
        // استفاده از مسیر کامل API
        fetch('/support_system/users/getSupportStaff')
            .then(response => {
                console.log('Response status:', response.status);
                if (!response.ok) {
                    throw new Error('خطا در دریافت داده‌ها از سرور');
                }
                return response.json();
            })
            .then(data => {
                console.log('Support staff data received:', data);
                
                // پاک کردن گزینه‌های قبلی
                assigneeSelect.innerHTML = '<option value="">انتخاب کنید</option>';

                // افزودن گزینه‌ها به لیست کشویی
                if (data && data.length > 0) {
                    data.forEach(staff => {
                        const option = document.createElement('option');
                        option.value = staff.id;
                        option.textContent = `${staff.fullname} (${staff.username})`;
                        assigneeSelect.appendChild(option);
                    });
                } else {
                    const option = document.createElement('option');
                    option.value = "";
                    option.textContent = "هیچ پشتیبانی یافت نشد";
                    option.disabled = true;
                    assigneeSelect.appendChild(option);
                }
            })
            .catch(error => {
                console.error('خطا در دریافت لیست پشتیبان‌ها:', error);
                
                // نمایش پیام خطا در لیست کشویی
                assigneeSelect.innerHTML = '<option value="">خطا در دریافت لیست پشتیبان‌ها</option>';
            });
    }
});