// داده‌های نمودار وضعیت درخواست کار‌ها
const ticketStatusData = JSON.parse(document.getElementById('ticketStatusData').textContent);
const statusLabels = ticketStatusData.map(item => item.status);
const statusData = ticketStatusData.map(item => item.count);

// ایجاد نمودار وضعیت درخواست کار‌ها
const ctxStatusChart = document.getElementById('ticketStatusChart').getContext('2d');
new Chart(ctxStatusChart, {
    type: 'pie',
    data: {
        labels: statusLabels,
        datasets: [{
            data: statusData,
            backgroundColor: [
                'rgba(75, 192, 192, 0.2)',
                'rgba(255, 99, 132, 0.2)',
                'rgba(255, 206, 86, 0.2)'
            ],
            borderColor: [
                'rgba(75, 192, 192, 1)',
                'rgba(255, 99, 132, 1)',
                'rgba(255, 206, 86, 1)'
            ],
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'top',
            },
            title: {
                display: true,
                text: 'وضعیت درخواست کار‌ها'
            }
        }
    }
});

// داده‌های نمودار زمانی تعداد درخواست کار‌ها
const ticketCountsByDate = JSON.parse(document.getElementById('ticketCountsByDate').textContent);
const dateLabels = ticketCountsByDate.map(item => item.ticket_date);
const dateData = ticketCountsByDate.map(item => item.ticket_count);

// ایجاد نمودار زمانی تعداد درخواست کار‌ها
const ctxTimeChart = document.getElementById('ticketTimeChart').getContext('2d');
new Chart(ctxTimeChart, {
    type: 'line',
    data: {
        labels: dateLabels,
        datasets: [{
            label: 'تعداد درخواست کار‌ها',
            data: dateData,
            borderColor: 'rgba(54, 162, 235, 1)',
            backgroundColor: 'rgba(54, 162, 235, 0.2)',
            borderWidth: 2,
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'top',
            },
            title: {
                display: true,
                text: 'نمودار زمانی تعداد درخواست کار‌ها'
            }
        },
        scales: {
            x: {
                title: {
                    display: true,
                    text: 'تاریخ'
                }
            },
            y: {
                title: {
                    display: true,
                    text: 'تعداد درخواست کار‌ها'
                },
                beginAtZero: true
            }
        }
    }
});