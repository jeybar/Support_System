<?php
// app/helpers/date_helper.php

if (!function_exists('jdate')) {
    /**
     * تبدیل تاریخ میلادی به شمسی
     * 
     * @param string $format فرمت تاریخ شمسی
     * @param int|string $timestamp تایم استمپ یا تاریخ میلادی
     * @return string تاریخ شمسی فرمت شده
     */
    function jdate($format, $timestamp = null) {
        if ($timestamp === null) {
            $timestamp = time();
        } elseif (!is_numeric($timestamp)) {
            $timestamp = strtotime($timestamp);
            if ($timestamp === false) {
                return false;
            }
        }
        
        // آرایه روزهای هفته
        $weekdays = [
            0 => 'یکشنبه',
            1 => 'دوشنبه',
            2 => 'سه‌شنبه',
            3 => 'چهارشنبه',
            4 => 'پنجشنبه',
            5 => 'جمعه',
            6 => 'شنبه'
        ];
        
        // آرایه ماه‌های سال
        $months = [
            1 => 'فروردین',
            2 => 'اردیبهشت',
            3 => 'خرداد',
            4 => 'تیر',
            5 => 'مرداد',
            6 => 'شهریور',
            7 => 'مهر',
            8 => 'آبان',
            9 => 'آذر',
            10 => 'دی',
            11 => 'بهمن',
            12 => 'اسفند'
        ];
        
        // تبدیل تاریخ میلادی به شمسی
        list($year, $month, $day) = gregorian_to_jalali(
            date('Y', $timestamp),
            date('m', $timestamp),
            date('d', $timestamp)
        );
        
        // جایگزینی فرمت‌های تاریخ شمسی
        $formatted = str_replace(
            [
                'Y', 'y', 'm', 'n', 'd', 'j', 'l', 'F', 'M',
                'H', 'h', 'i', 's', 'a', 'A'
            ],
            [
                $year, substr($year, 2), 
                str_pad($month, 2, '0', STR_PAD_LEFT), $month,
                str_pad($day, 2, '0', STR_PAD_LEFT), $day,
                $weekdays[date('w', $timestamp)],
                $months[$month],
                substr($months[$month], 0, 3),
                date('H', $timestamp),
                date('h', $timestamp),
                date('i', $timestamp),
                date('s', $timestamp),
                date('a', $timestamp),
                date('A', $timestamp)
            ],
            $format
        );
        
        // تبدیل اعداد انگلیسی به فارسی
        return convert_numbers_to_persian($formatted);
    }
}

if (!function_exists('gregorian_to_jalali')) {
    /**
     * تبدیل تاریخ میلادی به شمسی
     * 
     * @param int $gy سال میلادی
     * @param int $gm ماه میلادی
     * @param int $gd روز میلادی
     * @return array آرایه سال، ماه و روز شمسی
     */
    function gregorian_to_jalali($gy, $gm, $gd) {
        $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
        $days = 355666 + (365 * $gy) + floor(($gy2 + 3) / 4) - floor(($gy2 + 99) / 100) + floor(($gy2 + 399) / 400) + $gd + $g_d_m[$gm - 1];
        $jy = -1595 + (33 * floor($days / 12053));
        $days %= 12053;
        $jy += 4 * floor($days / 1461);
        $days %= 1461;
        if ($days > 365) {
            $jy += floor(($days - 1) / 365);
            $days = ($days - 1) % 365;
        }
        if ($days < 186) {
            $jm = 1 + floor($days / 31);
            $jd = 1 + ($days % 31);
        } else {
            $jm = 7 + floor(($days - 186) / 30);
            $jd = 1 + (($days - 186) % 30);
        }
        return [$jy, $jm, $jd];
    }
}

if (!function_exists('convert_numbers_to_persian')) {
    /**
     * تبدیل اعداد انگلیسی به فارسی
     * 
     * @param string $string رشته حاوی اعداد انگلیسی
     * @return string رشته با اعداد فارسی
     */
    function convert_numbers_to_persian($string) {
        $persian_digits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $english_digits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        
        return str_replace($english_digits, $persian_digits, $string);
    }
}