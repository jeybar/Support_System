<?php

    $cssLink = 'view_ticket.css';
    $pageTitle = 'جزئیات درخواست کار';

    require_once __DIR__ . '/components/page_header.php';
    require_once __DIR__ . '/header.php';
    require_once __DIR__ . '/../helpers/translate.php';
?>

<div class="container mt-4">
    <div class="row align-items-center mb-3">
        <!-- Breadcrumbs -->
        <div class="col-lg-8 col-md-6 col-sm-12">
            <?php echo generateBreadcrumbs(); ?>
        </div>

        <!-- دکمه‌ها -->
        <div class="col-lg-4 col-md-6 col-sm-12">
            <div class="d-flex justify-content-end flex-wrap align-items-center">
                <button class="btn btn-success mb-2" data-bs-toggle="modal" data-bs-target="#createTicketModal">
                    <i class="bi bi-plus-circle"></i> ثبت درخواست کار
                </button>
            </div>
        </div>
    </div>
</div>

<!-- نمایش پیام‌های خطا و موفقیت -->
<div class="container mt-4">
    <?php
    // ثبت لاگ برای اشکال‌زدایی
    error_log("Session in view_ticket.php: " . print_r($_SESSION, true));
    ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger">
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>
</div>

<!-- Main Content -->
<main class="container">
    <!-- Header Section -->
    <section class="card shadow-sm mb-4">
        <header class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <div>
                <!-- عنوان درخواست -->
                <div class="d-flex align-items-center">
                    <i class="fas fa-clipboard-list me-2"></i>
                    <!-- عنوان درخواست -->
                    <h2 class="mb-0">
                        <span class="fw-bold">عنوان درخواست:</span> <?php echo htmlspecialchars($ticket['title']); ?>
                    </h2>
                </div>
                <!-- شماره درخواست -->
                <div class="ticket-id">
                    <small>شماره درخواست: <?php echo htmlspecialchars($ticket['id']); ?></small>
                </div>
            </div>
            <div>
                <!-- اولویت -->
                <span class="badge priority-badge <?php echo $ticket['priority'] == 'high' ? 'bg-danger' : ($ticket['priority'] == 'medium' ? 'bg-warning text-dark' : 'bg-success'); ?>">
                    اولویت: <?php echo translatePriority($ticket['priority']); ?>
                </span>
                <!-- وضعیت -->
                <span class="badge status-badge <?php echo $ticket['status'] == 'open' ? 'bg-success' : ($ticket['status'] == 'in_progress' ? 'bg-warning text-dark' : 'bg-danger'); ?>">
                    وضعیت: <?php echo translateStatus($ticket['status']); ?>
                </span>
            </div>
        </header>
    </section>
    <div class="row">
        <!-- Left Column -->
        <aside class="col-md-4">
            <!-- Requester Info -->
            <section class="card shadow-sm mb-4">
                <header class="card-header bg-light">
                    <h4 class="mb-0"><i class="fas fa-user"></i> اطلاعات درخواست‌دهنده</h4>
                </header>
                <div class="card-body">
                    <p><strong>نام:</strong> <?php echo htmlspecialchars($ticket['requester_name'] ?? 'نامشخص'); ?></p>
                    <p><strong>شماره پرسنلی:</strong> <?php echo htmlspecialchars($ticket['requester_employee_id'] ?? 'نامشخص'); ?></p>
                    <p><strong>پلنت:</strong> <?php echo htmlspecialchars($ticket['requester_plant'] ?? 'نامشخص'); ?></p>
                    <p><strong>واحد:</strong> <?php echo htmlspecialchars($ticket['requester_unit'] ?? 'نامشخص'); ?></p>
                    <p><strong>شماره تماس داخلی:</strong> <?php echo htmlspecialchars($ticket['requester_phone'] ?? 'نامشخص'); ?></p>
                    <p><strong>شماره همراه:</strong> <?php echo htmlspecialchars($ticket['requester_mobile'] ?? 'نامشخص'); ?></p>
                </div>
            </section>

            <!-- Support Info -->
            <section class="card shadow-sm mb-4">
                <header class="card-header bg-light">
                    <h4 class="mb-0"><i class="fas fa-user-shield"></i> اطلاعات پشتیبان</h4>
                </header>
                <div class="card-body">
                    <p><strong>نام:</strong> <?php echo htmlspecialchars($ticket['support_name'] ?? 'هنوز اختصاص داده نشده'); ?></p>
                    <p><strong>ایمیل:</strong> <?php echo htmlspecialchars($ticket['support_email'] ?? '---'); ?></p>
                    <p><strong>شماره تماس:</strong> <?php echo htmlspecialchars($ticket['support_phone'] ?? '---'); ?></p>
                </div>
            </section>

            <!-- Elapsed Time -->
            <section class="card shadow-sm mb-4">
                <header class="card-header bg-light">
                    <h4 class="mb-0"><i class="fas fa-clock"></i> زمان صرف‌شده برای رسیدگی</h4>
                </header>
                <div class="card-body text-center">
                    <?php 
                    // محاسبه روز، ساعت، دقیقه و ثانیه از ستون elapsed_time
                    $elapsedTime = isset($ticket['elapsed_time']) ? (int)$ticket['elapsed_time'] : 0; // مقدار زمان صرف‌شده از پایگاه داده
                    $days = floor($elapsedTime / (24 * 60 * 60));
                    $hours = floor(($elapsedTime % (24 * 60 * 60)) / 3600);
                    $minutes = floor(($elapsedTime % 3600) / 60);
                    $seconds = $elapsedTime % 60;
                    
                    // ثبت لاگ برای اشکال‌زدایی
                    error_log("Elapsed Time: " . $elapsedTime);
                    error_log("Started At: " . ($ticket['started_at'] ?? 'NULL'));
                    error_log("Status: " . $ticket['status']);
                    ?>
                    <p><strong>زمان صرف‌شده:</strong></p>
                    <div class="timer-container" id="timer">
                        <div class="timer-item">
                            <span class="timer-value"><?php echo sprintf('%02d', $days); ?></span>
                            <span class="timer-label">روز</span>
                        </div>
                        <div class="timer-item">
                            <span class="timer-value"><?php echo sprintf('%02d', $hours); ?></span>
                            <span class="timer-label">ساعت</span>
                        </div>
                        <div class="timer-item">
                            <span class="timer-value"><?php echo sprintf('%02d', $minutes); ?></span>
                            <span class="timer-label">دقیقه</span>
                        </div>
                        <div class="timer-item">
                            <span class="timer-value"><?php echo sprintf('%02d', $seconds); ?></span>
                            <span class="timer-label">ثانیه</span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- فرم مخفی برای ارسال زمان سپری شده -->
            <input type="hidden" id="elapsed_time_input" name="elapsed_time" value="<?php echo isset($ticket['elapsed_time']) ? (int)$ticket['elapsed_time'] : 0; ?>">

            <!-- Update Status Form -->
            <section class="card shadow-sm mb-4">
                <header class="card-header bg-warning text-dark">
                    <h4 class="mb-0"><i class="fas fa-edit"></i> تغییر وضعیت درخواست کار</h4>
                </header>
                <div class="card-body">
                    <!-- نمایش پیام‌های خطا و موفقیت -->
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger">
                            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success">
                            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                        </div>
                    <?php endif; ?>

                    <!-- فرم تغییر وضعیت -->
                    <form id="updateStatusForm" method="POST" action="/support_system/tickets/update_status">
                        <input type="hidden" name="ticket_id" value="<?php echo htmlspecialchars($ticket['id']); ?>">
                        <div class="mb-3">
                            <label for="status" class="form-label">وضعیت:</label>
                            <select id="status" name="status" class="form-select">
                                <option value="open" <?php echo $ticket['status'] == 'open' ? 'selected' : ''; ?>>باز</option>
                                <option value="in_progress" <?php echo $ticket['status'] == 'in_progress' ? 'selected' : ''; ?>>در حال بررسی</option>
                                <option value="resolved" <?php echo $ticket['status'] == 'resolved' ? 'selected' : ''; ?>>حل‌شده</option>
                                <option value="closed" <?php echo $ticket['status'] == 'closed' ? 'selected' : ''; ?>>بسته</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> ذخیره تغییرات</button>
                    </form>

                    <!-- اسکریپت اشکال‌زدایی -->
                    <script>
                        document.getElementById('updateStatusForm').addEventListener('submit', function(e) {
                            console.log('Form submitted');
                            console.log('Form action:', this.action);
                            console.log('Form method:', this.method);
                            console.log('Ticket ID:', this.querySelector('input[name="ticket_id"]').value);
                            console.log('Status:', this.querySelector('select[name="status"]').value);
                            
                            // اضافه کردن لاگ به صفحه
                            const logDiv = document.createElement('div');
                            logDiv.className = 'alert alert-info mt-3';
                            logDiv.innerHTML = `
                                <p><strong>اطلاعات ارسالی:</strong></p>
                                <p>آدرس: ${this.action}</p>
                                <p>متد: ${this.method}</p>
                                <p>شناسه درخواست: ${this.querySelector('input[name="ticket_id"]').value}</p>
                                <p>وضعیت: ${this.querySelector('select[name="status"]').value}</p>
                            `;
                            this.parentNode.appendChild(logDiv);
                        });
                    </script>
                </div>
            </section>
        </aside>

        <!-- Right Column -->
        <section class="col-md-8">
            <!-- جزئیات درخواست کار -->
            <section class="card shadow-sm mb-4">
                <header class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h4 class="mb-0"><i class="fas fa-info-circle"></i> جزئیات درخواست کار</h4>
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#referTicketModal">
                        <i class="fas fa-share"></i> ارجاع درخواست
                    </button>
                </header>
                <div class="card-body">
                    <!-- ایجاد شده توسط -->
                    <p><strong>ایجاد شده توسط:</strong> <?php echo htmlspecialchars($ticket['created_by_fullname'] ?? 'نامشخص'); ?></p>

                    <!-- Row for تاریخ ایجاد و آخرین به‌روزرسانی -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p><strong>تاریخ ایجاد:</strong> <?php echo htmlspecialchars($ticket['created_at']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>آخرین به‌روزرسانی:</strong> <?php echo htmlspecialchars($ticket['updated_at']); ?></p>
                        </div>
                    </div>

                    <!-- نوع مشکل -->
                    <p><strong>نوع مشکل:</strong> <?php echo translateProblemType($ticket['problem_type']); ?></p>

                    <!-- توضیحات درخواست -->
                    <p><strong>توضیحات:</strong> <?php echo nl2br(htmlspecialchars($ticket['description'])); ?></p>

                    <!-- Attachments Section -->
                    <?php if (!empty($attachments) && count($attachments) > 0): ?>
                        <div class="attachments mt-4">
                            <h5><i class="fas fa-paperclip"></i> فایل‌های پیوست‌شده:</h5>
                            <ul class="list-group">
                                <?php foreach ($attachments as $attachment): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span><?php echo htmlspecialchars($attachment['file_name']); ?></span>
                                        <a href="/support_system/tickets/download/<?php echo $ticket['id']; ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-download"></i> دانلود
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">هیچ فایلی به این درخواست پیوست نشده است.</p>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Replies Section -->
            <section class="card shadow-sm mb-4">
                <header class="card-header bg-secondary text-white">
                    <h4 class="mb-0"><i class="fas fa-comments"></i> پاسخ‌ها</h4>
                </header>
                <div class="card-body">
                    <?php if (!empty($replies)): ?>
                        <ul class="list-group">
                            <?php foreach ($replies as $reply): ?>
                                <li class="list-group-item">
                                    <!-- Row for Full Name and Date -->
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <p class="mb-0"><strong><?php echo htmlspecialchars($reply['author']); ?>:</strong></p>
                                        <p class="text-muted small mb-0">تاریخ: <?php echo htmlspecialchars($reply['created_at']); ?></p>
                                    </div>
                                    <!-- Reply Content -->
                                    <p><?php echo nl2br(htmlspecialchars($reply['content'])); ?></p>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-muted">هیچ پاسخی برای این درخواست کار ثبت نشده است.</p>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Reply Form -->
            <section class="card shadow-sm mb-4">
                <header class="card-header bg-info text-white">
                    <h4 class="mb-0"><i class="fas fa-reply"></i> ارسال پاسخ جدید</h4>
                </header>
                <div class="card-body">
                    <form method="POST" action="/support_system/tickets/reply" id="reply-form">
                        <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                        <div class="mb-3">
                            <label for="reply-content" class="form-label">متن پاسخ:</label>
                            <textarea id="reply-content" name="content" class="form-control" rows="4" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> ارسال پاسخ</button>
                    </form>
                </div>
            </section>

            <!-- اسکریپت اشکال‌زدایی برای فرم پاسخ -->
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                const replyForm = document.getElementById('reply-form');
                if (replyForm) {
                    console.log('Reply form found:', replyForm);
                    console.log('Form action:', replyForm.action);
                    console.log('Form method:', replyForm.method);
                    
                    replyForm.addEventListener('submit', function(e) {
                        console.log('Reply form submitted!');
                        console.log('Action:', this.action);
                        console.log('Method:', this.method);
                        console.log('Ticket ID:', this.querySelector('input[name="ticket_id"]').value);
                        console.log('Content:', this.querySelector('textarea[name="content"]').value);
                    });
                } else {
                    console.error('Reply form not found!');
                }
            });
            </script>
        </section>
    </div>
</main>

<!-- Refer Ticket Modal -->
<div class="modal fade" id="referTicketModal" tabindex="-1" aria-labelledby="referTicketModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="referTicketModalLabel">ارجاع درخواست</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن"></button>
            </div>
            <form id="referForm" action="/support_system/tickets/refer" method="POST">
                <div class="modal-body">
                    <!-- شناسه درخواست -->
                    <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">

                    <!-- انتخاب فرد یا بخش -->
                    <div class="mb-3">
                        <label for="assignee" class="form-label">ارجاع به:</label>
                        <select id="assignee" name="assignee" class="form-select" required>
                            <option value="">انتخاب کنید</option>
                            <!-- گزینه‌ها با JavaScript اضافه می‌شوند -->
                        </select>
                    </div>

                    <!-- توضیحات ارجاع -->
                    <div class="mb-3">
                        <label for="reason" class="form-label">توضیحات:</label>
                        <textarea id="reason" name="reason" class="form-control" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">لغو</button>
                    <button type="submit" class="btn btn-success">ارجاع</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- اسکریپت اشکال‌زدایی -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const referForm = document.getElementById('referForm');
    if (referForm) {
        console.log('Form action:', referForm.action);
        console.log('Form method:', referForm.method);
        
        referForm.addEventListener('submit', function(e) {
            console.log('Form submitted!');
            console.log('Ticket ID:', this.querySelector('input[name="ticket_id"]').value);
            console.log('Assignee:', document.getElementById('assignee').value);
            console.log('Reason:', document.getElementById('reason').value);
        });
    }
});
</script>

<!-- ارسال مقدار elapsedSeconds به جاوااسکریپت -->
<script>
    let elapsedSeconds = <?php echo isset($ticket['elapsed_time']) ? (int)$ticket['elapsed_time'] : 0; ?>; // مقدار اولیه از PHP
    let ticketStatus = '<?php echo $ticket['status']; ?>'; // وضعیت درخواست
    
    // به‌روزرسانی فیلد مخفی زمان سپری شده قبل از ارسال فرم
    document.addEventListener('DOMContentLoaded', function() {
        const statusForm = document.getElementById('updateStatusForm');
        if (statusForm) {
            statusForm.addEventListener('submit', function() {
                // به‌روزرسانی فیلد مخفی با مقدار فعلی تایمر
                document.getElementById('elapsed_time_input').value = timerSeconds;
                console.log("Form submitted with elapsed time:", timerSeconds);
            });
        }
    });
</script>

<script src="/assets/js/job-detail/request_timer.js"></script>
<script src="/assets/js/job-detail/refer_ticket.js"></script>

<!-- Footer -->
<?php
    include __DIR__ . '/footer.php';
?>