document.addEventListener("DOMContentLoaded", function () {
    // انتخاب فیلد شماره پرسنلی
    const employeeNumberInput = document.getElementById("employee_number");

    // تعریف تایمر برای debounce
    let debounceTimer;

    // افزودن رویداد برای تغییر مقدار فیلد شماره پرسنلی
    employeeNumberInput.addEventListener("input", function () {
        const employeeNumber = this.value;

        // اگر شماره پرسنلی خالی است، فیلدها را پاک کنید
        if (!employeeNumber) {
            document.getElementById("employee_name").value = "";
            document.getElementById("plant_name").value = "";
            document.getElementById("unit_name").value = "";
            return;
        }

        // حذف تایمر قبلی (در صورت وجود)
        clearTimeout(debounceTimer);

        // تنظیم تایمر جدید برای ارسال درخواست پس از 500 میلی‌ثانیه
        debounceTimer = setTimeout(() => {
            // ارسال درخواست Ajax به سرور
            fetch("/tickets/getUserByEmployeeNumber", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded",
                },
                body: `employee_number=${encodeURIComponent(employeeNumber)}`,
            })
                .then((response) => response.json())
                .then((data) => {
                    if (data.error) {
                        // اگر خطایی وجود داشت، فیلدها را پاک کنید
                        document.getElementById("employee_name").value = "";
                        document.getElementById("plant_name").value = "";
                        document.getElementById("unit_name").value = "";
                        alert(data.error); // نمایش پیام خطا
                    } else {
                        // پر کردن فیلدها با اطلاعات کاربر
                        document.getElementById("employee_name").value = data.fullname || "";
                        document.getElementById("plant_name").value = data.plant || "";
                        document.getElementById("unit_name").value = data.unit || "";
                    }
                })
                .catch((error) => {
                    console.error("خطا در ارسال درخواست:", error);
                    alert("خطا در دریافت اطلاعات کاربر.");
                });
        }, 1000); // زمان debounce (500 میلی‌ثانیه)
    });
});

document.getElementById('registerForOthers').addEventListener('change', function () {
    const otherUserFields = document.getElementById('otherUserFields');
    otherUserFields.style.display = this.checked ? 'block' : 'none';
});