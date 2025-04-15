<div class="modal fade" id="messageModal" tabindex="-1" aria-labelledby="messageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered"> <!-- modal-dialog-centered برای نمایش در وسط صفحه -->
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="messageModalLabel" style="color: #6c757d;">پیغام سیستم</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن"></button>
                </div>
                <div class="modal-body text-center">
                    <?php if ($successMessage): ?>
                        <div class="alert alert-success mb-0">
                            <?php echo htmlspecialchars($successMessage); ?>
                        </div>
                    <?php elseif ($errorMessage): ?>
                        <div class="alert alert-danger mb-0">
                            <?php echo htmlspecialchars($errorMessage); ?>
                        </div>
                    <?php else: ?>
                        <p>هیچ پیامی برای نمایش وجود ندارد.</p>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button>
                </div>
            </div>
        </div>
    </div>