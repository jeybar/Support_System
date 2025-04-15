document.addEventListener("DOMContentLoaded", function () {
    // مدیریت نمایش و مخفی کردن فرم جستجو
    const toggleSearchFormButton = document.getElementById("toggle-search-form");
    const searchFormContainer = document.getElementById("search-form-container");
    const closeSearchFormButton = document.getElementById("close-search-form");
    const clearFormButton = document.getElementById("clear-form");

    if (toggleSearchFormButton && searchFormContainer) {
        toggleSearchFormButton.addEventListener("click", function () {
            searchFormContainer.style.display =
                searchFormContainer.style.display === "none" ? "block" : "none";
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