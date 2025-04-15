// نمایش و مخفی کردن فرم جستجو
document.addEventListener("DOMContentLoaded", function () {
    const toggleSearchFormButton = document.getElementById("toggle-search-form");
    const searchFormContainer = document.getElementById("search-form-container");
    const closeSearchFormButton = document.getElementById("close-search-form");
    const clearFormButton = document.getElementById("clear-form");

    if (toggleSearchFormButton && searchFormContainer) {
        toggleSearchFormButton.addEventListener("click", function () {
            searchFormContainer.style.display = searchFormContainer.style.display === "none" ? "block" : "none";
        });
    }

    if (closeSearchFormButton) {
        closeSearchFormButton.addEventListener("click", function () {
            searchFormContainer.style.display = "none";
        });
    }

    if (clearFormButton) {
        clearFormButton.addEventListener("click", function () {
            document.getElementById("search-form").reset();
        });
    }
});

// نمودار وضعیت درخواست کار‌ها
if (document.getElementById("ticketStatusChart")) {
    const ctx1 = document.getElementById("ticketStatusChart").getContext("2d");
    new Chart(ctx1, {
        type: "pie",
        data: {
            labels: ["باز", "بسته", "در حال بررسی"],
            datasets: [
                {
                    data: [openTicketsCount, closedTicketsCount, inProgressTicketsCount],
                    backgroundColor: ["#007bff", "#28a745", "#ffc107"],
                },
            ],
        },
    });
}

// نمودار روند درخواست کار‌ها
if (document.getElementById("ticketTrendChart")) {
    const ctx2 = document.getElementById("ticketTrendChart").getContext("2d");
    new Chart(ctx2, {
        type: "line",
        data: {
            labels: ticketTrendLabels,
            datasets: [
                {
                    label: "تعداد درخواست کار‌ها",
                    data: ticketTrendData,
                    borderColor: "#007bff",
                    fill: false,
                },
            ],
        },
    });
}