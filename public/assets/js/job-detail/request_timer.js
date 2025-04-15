// متغیرهای گلوبال
let timerInterval;
let timerSeconds = 0;
let lastSavedTime = 0;
let ticketId = null;
let currentStatus = '';

// بررسی اینکه مقدار elapsedSeconds و ticketStatus از PHP ارسال شده باشند
if (typeof elapsedSeconds !== 'undefined' && typeof ticketStatus !== 'undefined') {
    console.log("Timer initialization:");
    console.log("- Initial elapsed seconds:", elapsedSeconds);
    console.log("- Ticket status:", ticketStatus);
    
    // مقدار اولیه مدت زمان (ثانیه) از PHP
    timerSeconds = parseInt(elapsedSeconds, 10);
    lastSavedTime = timerSeconds;
    currentStatus = ticketStatus;
    
    // دریافت شناسه درخواست
    const ticketIdElement = document.querySelector('input[name="ticket_id"]');
    if (ticketIdElement) {
        ticketId = ticketIdElement.value;
        console.log("- Ticket ID:", ticketId);
    } else {
        console.error("Ticket ID element not found!");
    }

    // پیدا کردن عنصر تایمر
    const timerElement = document.getElementById('timer');
    if (!timerElement) {
        console.error("Timer element not found! Make sure an element with ID 'timer' exists.");
    } else {
        // نمایش اولیه تایمر
        updateTimerDisplay(timerSeconds);
        
        // بررسی وضعیت درخواست برای شروع یا توقف تایمر
        if (currentStatus === 'closed' || currentStatus === 'resolved') {
            console.log("Ticket is closed/resolved. Static timer will be displayed.");
            // تایمر متوقف است و فقط مقدار ثابت نمایش داده می‌شود
        } else {
            console.log("Ticket is open/in_progress. Live timer is starting...");
            
            // اجرای تایمر هر ثانیه
            timerInterval = setInterval(updateTimer, 1000);

            // مدیریت خطای احتمالی
            window.addEventListener('beforeunload', () => {
                clearInterval(timerInterval); // توقف تایمر هنگام خروج از صفحه
                saveElapsedTime(); // ذخیره آخرین زمان قبل از خروج
            });
        }
        
        // به‌روزرسانی فیلد مخفی زمان سپری شده قبل از ارسال فرم
        document.addEventListener('DOMContentLoaded', function() {
            const statusForm = document.getElementById('updateStatusForm');
            if (statusForm) {
                statusForm.addEventListener('submit', function(e) {
                    // به‌روزرسانی فیلد مخفی با مقدار فعلی تایمر
                    const elapsedTimeInput = document.getElementById('elapsed_time_input');
                    if (elapsedTimeInput) {
                        elapsedTimeInput.value = timerSeconds;
                        console.log("Form submitted with elapsed time:", timerSeconds);
                        
                        // ذخیره آخرین زمان قبل از ارسال فرم
                        saveElapsedTime();
                    } else {
                        console.error("Elapsed time input field not found!");
                    }
                });
            }
        });
    }
} else {
    console.error("elapsedSeconds or ticketStatus values are not provided from PHP.");
}

/**
 * تابع برای محاسبه و نمایش زمان
 * @param {number} seconds - تعداد ثانیه‌های سپری‌شده
 */
function updateTimerDisplay(seconds) {
    // محاسبه روز، ساعت، دقیقه، ثانیه
    const days = Math.floor(seconds / (24 * 60 * 60));
    const hours = Math.floor((seconds % (24 * 60 * 60)) / (60 * 60));
    const minutes = Math.floor((seconds % (60 * 60)) / 60);
    const secs = seconds % 60;

    // پیدا کردن تمام آیتم‌های تایمر (روز، ساعت، دقیقه، ثانیه)
    const timerItems = document.querySelectorAll('#timer .timer-item .timer-value');
    if (timerItems.length === 4) {
        timerItems[0].textContent = days.toString().padStart(2, '0'); // روز
        timerItems[1].textContent = hours.toString().padStart(2, '0'); // ساعت
        timerItems[2].textContent = minutes.toString().padStart(2, '0'); // دقیقه
        timerItems[3].textContent = secs.toString().padStart(2, '0'); // ثانیه
    } else {
        console.error("Timer structure is incomplete. Make sure there are 4 values (days, hours, minutes, seconds) in HTML.");
        console.log("Found timer items:", timerItems.length);
    }
}

/**
 * تابع برای ذخیره زمان سپری شده در پایگاه داده
 */
function saveElapsedTime() {
    if (!ticketId) {
        console.error("Cannot save elapsed time: Ticket ID is not available.");
        return;
    }
    
    console.log("Saving elapsed time to database:", timerSeconds);
    
    // ارسال درخواست با Fetch API
    fetch('/support_system/tickets/update_elapsed_time', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `ticket_id=${ticketId}&elapsed_time=${timerSeconds}`
    })
    .then(response => {
        if (response.ok) {
            console.log("Elapsed time saved successfully.");
            return response.json();
        } else {
            console.error("Failed to save elapsed time. Status:", response.status);
            throw new Error("Server responded with an error");
        }
    })
    .then(data => {
        console.log("Server response:", data);
    })
    .catch(error => {
        console.error("Error saving elapsed time:", error);
    });
}

/**
 * تابع برای به‌روزرسانی تایمر
 */
function updateTimer() {
    // افزایش یک ثانیه به مقدار
    timerSeconds++;

    // به‌روزرسانی نمایش تایمر
    updateTimerDisplay(timerSeconds);
    
    // ذخیره زمان در پایگاه داده هر 60 ثانیه
    if (timerSeconds - lastSavedTime >= 60) {
        saveElapsedTime();
        lastSavedTime = timerSeconds;
    }
}