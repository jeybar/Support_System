document.addEventListener("DOMContentLoaded", function () {
    //  کدهای مربوط به فرم جستجو
    const searchForm = document.getElementById("search-form");
    const resultsDiv = document.getElementById("results");
    const toggleSearchFormButton = document.getElementById("toggle-search-form");
    const closeSearchFormButton = document.getElementById("close-search-form");
    const clearFormButton = document.getElementById("clear-form");
    const searchFormContainer = document.getElementById("search-form-container");
       
    // نمایش یا مخفی کردن فرم جستجو
    toggleSearchFormButton.addEventListener("click", function () {
        const isFormVisible = searchFormContainer.style.display === "block";
        searchFormContainer.style.display = isFormVisible ? "none" : "block";

        // اگر فرم بسته شود، بخش نتایج نیز مخفی شود
        if (isFormVisible && resultsDiv) {
            resultsDiv.style.display = "none";
        }
    });

    // بستن فرم جستجو
    closeSearchFormButton.addEventListener("click", function () {
        searchFormContainer.style.display = "none";
        if (resultsDiv) {
            resultsDiv.style.display = "none";
        }
    });

    // پاک کردن فرم جستجو
    clearFormButton.addEventListener("click", function () {
        // پاک کردن تمام فیلدهای فرم
        document.getElementById("query").value = "";
        document.getElementById("status").value = "";
        document.getElementById("created_by").value = "";
        document.getElementById("created_date").value = "";

        // پاک کردن نتایج جستجو
        if (resultsDiv) {
            resultsDiv.innerHTML = "";
            resultsDiv.style.display = "none";
        }
    });

    // مدیریت ارسال فرم جستجو
    if (searchForm) {
        searchForm.addEventListener("submit", function (event) {
            event.preventDefault();

            // دریافت مقادیر ورودی از فرم
            const query = document.getElementById("query").value.trim();
            const status = document.getElementById("status").value.trim();
            const createdBy = document.getElementById("created_by").value.trim();
            const createdDate = document.getElementById("created_date").value.trim();

            // اگر همه فیلترها خالی هستند، پیام خطا نمایش دهید
            if (!query && !status && !createdBy && !createdDate) {
                resultsDiv.innerHTML = "<p class='text-danger'>لطفاً حداقل یک فیلتر را وارد کنید.</p>";
                resultsDiv.style.display = "block";
                return;
            }

            // نمایش پیام "در حال جستجو..."
            resultsDiv.innerHTML = `
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">در حال جستجو...</span>
                    </div>
                    <p>در حال جستجو...</p>
                </div>
            `;
            resultsDiv.style.display = "block";

            // ساخت پارامترهای جستجو
            const params = new URLSearchParams();
            if (query) params.append("query", query);
            if (status) params.append("status", status);
            if (createdBy) params.append("created_by", createdBy);
            if (createdDate) params.append("created_date", createdDate);

            // ارسال درخواست به API
            fetch(`/support_system/public/index.php?route=search_api&${params.toString()}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error("خطا در دریافت نتایج.");
                    }
                    return response.json();
                })
                .then(data => {
                    // پاک کردن محتوای قبلی
                    resultsDiv.innerHTML = "";

                    // اگر نتیجه‌ای یافت نشد
                    if (data.length === 0) {
                        resultsDiv.innerHTML = "<p class='text-warning'>نتیجه‌ای یافت نشد.</p>";
                        return;
                    }

                    // ساخت جدول نتایج
                    const table = document.createElement("table");
                    table.className = "table table-striped table-bordered";
                    table.innerHTML = `
                        <thead>
                            <tr>
                                <th>عنوان</th>
                                <th>وضعیت</th>
                                <th>اولویت</th>
                                <th>تاریخ ایجاد</th>
                                <th>ایجادکننده</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${data.map(ticket => `
                                <tr>
                                    <td>${ticket.title}</td>
                                    <td>${renderStatus(ticket.status)}</td>
                                    <td>${ticket.priority || "نامشخص"}</td>
                                    <td>${ticket.created_at}</td>
                                    <td>${ticket.created_by}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    `;
                    resultsDiv.appendChild(table);
                })
                .catch(error => {
                    console.error("Error:", error);
                    resultsDiv.innerHTML = "<p class='text-danger'>خطایی رخ داده است. لطفاً دوباره تلاش کنید.</p>";
                });
        });
    }

    // تابع برای ترجمه و نمایش وضعیت
    function renderStatus(status) {
        switch (status) {
            case "open":
                return '<span class="badge bg-success">باز</span>';
            case "closed":
                return '<span class="badge bg-danger">بسته</span>';
            case "in_progress":
                return '<span class="badge bg-warning text-dark">در حال بررسی</span>';
            default:
                return '<span class="badge bg-secondary">نامشخص</span>';
        }
    }

});