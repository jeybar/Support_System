$(document).ready(function () {
        
    // مقداردهی فیلدهای پلنت و واحد هنگام باز شدن مودال
    $('#createTicketModal').on('shown.bs.modal', function () { 
        // اگر گزینه "ثبت برای دیگران" فعال نباشد، مقادیر از پروفایل کاربر دریافت می‌شوند
        if (!$('#registerForOthers').is(':checked')) {
            $('#plant_name').val(userPlant).prop('readonly', true); // مقداردهی و readonly
            $('#unit_name').val(userUnit).prop('readonly', true); // مقداردهی و readonly
            
            // بارگذاری تجهیز‌های کاربر جاری
            loadUserAssets();
        }
    });

    // مدیریت تغییر وضعیت گزینه "ثبت برای دیگران"
    $('#registerForOthers').on('change', function () {
        if ($(this).is(':checked')) {
            // نمایش فیلدهای مربوط به دیگران
            $('#registerForOthersFields').show();
            $('#plant_name').prop('readonly', false).val(''); // فعال کردن تکست‌باکس پلنت
            $('#unit_name').prop('readonly', false).val(''); // فعال کردن تکست‌باکس واحد
            
            // غیرفعال کردن فیلد انتخاب تجهیز
            $('#asset_id').prop('disabled', true).val('');
        } else {
            // مخفی کردن فیلدهای مربوط به دیگران
            $('#registerForOthersFields').hide();
            $('#plant_name').prop('readonly', true).val(userPlant); // بازگرداندن مقدار از پروفایل
            $('#unit_name').prop('readonly', true).val(userUnit); // بازگرداندن مقدار از پروفایل
            
            // فعال کردن فیلد انتخاب تجهیز و بارگذاری تجهیز‌های کاربر
            $('#asset_id').prop('disabled', false);
            loadUserAssets();
        }
    });

    // مدیریت تغییر نوع درخواست
    $('#problem_type').on('change', function() {
        if ($(this).val() === 'hardware') {
            // اگر نوع درخواست سخت‌افزار است، فیلد تجهیز را نمایش بده
            $('#assetSelectionField').show();
        } else {
            // در غیر این صورت، فیلد تجهیز را مخفی کن و مقدار آن را پاک کن
            $('#assetSelectionField').hide();
            $('#asset_id').val('');
        }
    });

    // اجرای اولیه برای تنظیم وضعیت نمایش فیلد تجهیز بر اساس نوع درخواست
    if ($('#problem_type').val() !== 'hardware') {
        $('#assetSelectionField').hide();
    }

    // تابع بارگذاری تجهیز‌های کاربر
    function loadUserAssets() {
        // اگر ثبت برای دیگران فعال است یا نوع درخواست سخت‌افزار نیست، نیازی به بارگذاری نیست
        if ($('#registerForOthers').is(':checked') || $('#problem_type').val() !== 'hardware') {
            return;
        }

        // بارگذاری تجهیز‌های کاربر با Ajax
        $.ajax({
            url: '/support_system/assets/user_assets', // مسیر کامل با پیشوند
            type: 'GET',
            dataType: 'json',
            success: function(data) {
                var assetSelect = $('#asset_id');
                assetSelect.empty();
                assetSelect.append('<option value="">بدون تجهیز</option>');
                
                // اضافه کردن گزینه‌های تجهیز
                if (Array.isArray(data) && data.length > 0) {
                    $.each(data, function(index, asset) {
                        assetSelect.append('<option value="' + asset.id + '">' + asset.asset_tag + ' - ' + asset.category_name + ' ' + asset.model_name + '</option>');
                    });
                } else {
                    console.log('هیچ تجهیز برای کاربر جاری یافت نشد یا ساختار داده نامعتبر است');
                }
            },
            error: function(xhr, status, error) {
                console.error('خطا در بارگذاری تجهیز‌ها:', error);
                console.error('وضعیت:', status);
                console.error('پاسخ:', xhr.responseText);
            }
        });
    }

    // ارسال فرم با Ajax
    $('#createTicketForm').on('submit', function (e) {
        e.preventDefault(); // جلوگیری از ارسال پیش‌فرض فرم

        // جمع‌آوری داده‌های فرم
        var formData = new FormData(this);

        // ارسال درخواست به سرور
        $.ajax({
            url: '/support_system/tickets/create', // مسیر ارسال
            type: 'POST', // متد ارسال
            data: formData, // داده‌های فرم
            processData: false, // غیرفعال کردن پردازش داده‌ها
            contentType: false, // غیرفعال کردن تنظیم خودکار Content-Type
            success: function (response) {
                console.log('پاسخ سرور:', response); // نمایش پاسخ سرور در کنسول
                alert('درخواست با موفقیت ثبت شد!');
                $('#createTicketModal').modal('hide'); // بستن مودال
                $('#createTicketForm')[0].reset(); // بازنشانی فرم
                $('#registerForOthers').prop('checked', false).trigger('change'); // بازنشانی وضعیت "ثبت برای دیگران"
            },
            error: function (xhr, status, error) {
                console.error('خطای سرور:', xhr.responseText); // نمایش خطای سرور در کنسول
                alert('خطایی رخ داده است. لطفاً دوباره تلاش کنید.');
            }
        });
    });
});