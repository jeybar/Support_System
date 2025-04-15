<?php
// app/helpers/QrCodeGenerator.php

class QrCodeGenerator {
    /**
     * ایجاد کد QR برای تجهیز
     * 
     * @param string $data داده‌ای که باید در کد QR قرار گیرد
     * @param int $size اندازه کد QR
     * @return string مسیر فایل کد QR تولید شده
     */
    public function generateAssetQrCode($data, $size = 200) {
        // استفاده از Google Chart API برای تولید QR Code
        $qrData = urlencode($data);
        $url = "https://chart.googleapis.com/chart?cht=qr&chs={$size}x{$size}&chl={$qrData}";
        
        // مسیر ذخیره فایل
        $filename = 'qr_' . md5($data . time()) . '.png';
        $filepath = __DIR__ . '/../../public/uploads/qrcodes/' . $filename;
        
        // اطمینان از وجود دایرکتوری
        if (!file_exists(dirname($filepath))) {
            mkdir(dirname($filepath), 0777, true);
        }
        
        // دانلود و ذخیره تصویر QR Code
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $qrImage = curl_exec($ch);
            curl_close($ch);
            file_put_contents($filepath, $qrImage);
        } else {
            file_put_contents($filepath, file_get_contents($url));
        }
        
        return '/uploads/qrcodes/' . $filename;
    }
    
    /**
     * تولید HTML برای نمایش کد QR
     * 
     * @param string $data داده‌ای که باید در کد QR قرار گیرد
     * @param string $title عنوان برای نمایش
     * @return string HTML کد QR
     */
    public function generateQrCodeHtml($data, $title = '') {
        $qrImagePath = $this->generateAssetQrCode($data);
        
        $html = '<div class="qr-code-container">';
        if (!empty($title)) {
            $html .= '<h4>' . htmlspecialchars($title) . '</h4>';
        }
        $html .= '<img src="' . $qrImagePath . '" alt="QR Code" class="qr-code-image">';
        $html .= '<p class="qr-code-data">' . htmlspecialchars($data) . '</p>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * تولید کد QR برای چندین تجهیز
     * 
     * @param array $assets آرایه‌ای از داده‌های تجهیز‌ها
     * @return array مسیرهای فایل‌های کد QR تولید شده
     */
    public function generateBulkQrCodes($assets) {
        $qrCodes = [];
        
        foreach ($assets as $asset) {
            $data = isset($asset['asset_tag']) ? $asset['asset_tag'] : $asset['id'];
            $qrCodes[$asset['id']] = $this->generateAssetQrCode($data);
        }
        
        return $qrCodes;
    }
    
    /**
     * تولید PDF حاوی کدهای QR برای چاپ
     * 
     * @param array $assets آرایه‌ای از داده‌های تجهیز‌ها
     * @return string مسیر فایل PDF تولید شده
     */
    public function generateQrCodePdf($assets) {
        // این متد نیاز به کتابخانه تولید PDF مانند TCPDF یا FPDF دارد
        // فعلاً یک پیاده‌سازی ساده برای جلوگیری از خطا
        
        $html = '<html><head><title>Asset QR Codes</title>';
        $html .= '<style>
            .qr-code-sheet { display: flex; flex-wrap: wrap; }
            .qr-code-item { margin: 10px; text-align: center; width: 200px; }
        </style>';
        $html .= '</head><body>';
        $html .= '<div class="qr-code-sheet">';
        
        foreach ($assets as $asset) {
            $data = isset($asset['asset_tag']) ? $asset['asset_tag'] : $asset['id'];
            $qrImagePath = $this->generateAssetQrCode($data);
            
            $html .= '<div class="qr-code-item">';
            $html .= '<img src="' . $_SERVER['DOCUMENT_ROOT'] . $qrImagePath . '" alt="QR Code">';
            $html .= '<p>' . htmlspecialchars($asset['name'] ?? 'Asset') . '</p>';
            $html .= '<p>' . htmlspecialchars($data) . '</p>';
            $html .= '</div>';
        }
        
        $html .= '</div></body></html>';
        
        // مسیر ذخیره فایل
        $filename = 'qr_codes_' . time() . '.html';
        $filepath = __DIR__ . '/../../public/uploads/qrcodes/' . $filename;
        
        // اطمینان از وجود دایرکتوری
        if (!file_exists(dirname($filepath))) {
            mkdir(dirname($filepath), 0777, true);
        }
        
        file_put_contents($filepath, $html);
        
        return '/uploads/qrcodes/' . $filename;
    }
}