<!-- مودال ثبت درخواست -->
<div class="modal fade" id="createTicketModal" tabindex="-1" aria-labelledby="createTicketModalLabel" aria-hidden="inert">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="createTicketModalLabel">ثبت درخواست کار</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <form id="createTicketForm" method="POST" action="/tickets/create" enctype="multipart/form-data">
                        <!-- کلید ثبت برای دیگران -->
                        <div class="d-flex justify-content-end mb-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="registerForOthers" name="registerForOthers">
                                <label class="form-check-label form-label" for="registerForOthers">ثبت برای دیگران</label>
                            </div>
                        </div>
                        <!-- فیلدهای مربوط به ثبت برای دیگران -->
                        <div id="registerForOthersFields" style="display: none;">
                            <div class="mb-3 d-flex align-items-center">
                                <label for="employee_number" class="form-label">شماره پرسنلی:</label>
                                <input type="text" name="employee_number" id="employee_number" class="form-control" placeholder="شماره پرسنلی فرد نیازمند خدمت">
                            </div>
                            <div class="mb-3 d-flex align-items-center">
                                <label for="employee_name" class="form-label">نام و نام خانوادگی:</label>
                                <input type="text" name="employee_name" id="employee_name" class="form-control" placeholder="نام و نام خانوادگی">
                            </div>
                        </div>

                        <!-- فیلد نام پلنت -->
                        <div class="mb-3 d-flex align-items-center">
                            <label for="plant_name" class="form-label">نام پلنت:</label>
                            <input type="text" name="plant_name" id="plant_name" class="form-control" readonly>
                        </div>

                        <!-- فیلد نام واحد -->
                        <div class="mb-3 d-flex align-items-center">
                            <label for="unit_name" class="form-label">نام واحد:</label>
                            <input type="text" name="unit_name" id="unit_name" class="form-control" readonly>
                        </div>

                        <!-- فیلد انتخاب تجهیز سخت‌افزاری -->
                        <div class="mb-3 d-flex align-items-center" id="assetSelectionField">
                            <label for="asset_id" class="form-label">تجهیز مرتبط:</label>
                            <select name="asset_id" id="asset_id" class="form-select">
                                <option value="">بدون تجهیز</option>
                                <!-- گزینه‌های تجهیز با Ajax بارگذاری می‌شوند -->
                            </select>
                        </div>

                        <!-- سایر فیلدهای فرم -->
                        <div class="mb-3 d-flex align-items-center">
                            <label for="problem_type" class="form-label">نوع درخواست:</label>
                            <select name="problem_type" id="problem_type" class="form-select" required>
                                <option value="hardware">سخت‌افزار</option>
                                <option value="software">نرم‌افزار</option>
                                <option value="network">شبکه</option>
                            </select>
                        </div>
                        <div class="mb-3">
                        <label for="title" class="form-label">عنوان درخواست :</label>
                        <input type="text" name="title" id="title" class="form-control" placeholder="عنوان درخواست کار را وارد کنید" required>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">توضیحات درخواست:</label>
                            <textarea name="description" id="description" class="form-control" rows="4" style="height:80px;" required></textarea>
                        </div>
                        <div class="mb-3 d-flex align-items-center">
                            <label for="file" class="form-label">آپلود فایل:</label>
                            <input type="file" name="file" id="file" class="form-control">
                        </div>
                        <div class="mb-3 d-flex align-items-center">
                            <label for="priority" class="form-label">اولویت:</label>
                            <select name="priority" id="priority" class="form-select" required>
                                <option value="normal">کم</option>
                                <option value="urgent">متوسط</option>
                                <option value="critical">زیاد</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-success">ثبت درخواست</button>
                    </form>
                </div>
            </div>
        </div>
    </div>