/**
 * Persian Date Picker Integration
 * Lightweight Jalali DatePicker with Gregorian to Jalali conversion
 * Depends on: @majidh1/jalalidatepicker (CDN)
 */

(function() {
    'use strict';
    
    // تنظیمات پیش‌فرض تقویم
    const datepickerConfig = {
        format: 'YYYY-MM-DD',      // فرمت خروجی برای دیتابیس
        autoClose: true,           // بستن خودکار پس از انتخاب
        calendar: {
            locale: 'fa',          // زبان فارسی
            navigator: {
                enabled: true,
                text: {
                    btnNextText: '›',
                    btnPrevText: '‹'
                }
            },
            day: {
                titleFormat: 'YYYY MMMM DD',
                format: 'DD'
            }
        },
        onSelect: function(date) {
            // اطمینان از فرمت صحیح قبل از درج
            const input = this.element;
            let value = date.toString('YYYY-MM-DD');
            
            // تبدیل اعداد فارسی به انگلیسی برای ذخیره در دیتابیس
            value = value.replace(/[۰-۹]/g, d => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d));
            input.value = value;
            
            // اگر فرم وجود دارد، سابمیت خودکار (اختیاری)
            if (input.dataset.autoSubmit === 'true' && input.form) {
                input.form.requestSubmit();
            }
        }
    };

    // تابع راه‌اندازی تقویم برای فیلدهای مشخص‌شده
    window.initPersianDatepickers = function(selector = '[data-persian-datepicker]') {
        if (typeof jalaliDatepicker === 'undefined') {
            console.warn('jalaliDatepicker library not loaded');
            return;
        }
        
        // اعمال تنظیمات به ورودی‌ها
        document.querySelectorAll(selector).forEach(function(input) {
            if (!input.dataset.jdpInitialized) {
                input.setAttribute('data-jdp', '');
                input.dataset.jdpInitialized = 'true';
            }
        });
        
        // شروع نظارت بر ورودی‌ها
        jalaliDatepicker.startWatch(datepickerConfig);
    };

    // راه‌اندازی خودکار پس از لود صفحه
    document.addEventListener('DOMContentLoaded', function() {
        // بررسی وجود کتابخانه
        if (typeof jalaliDatepicker !== 'undefined') {
            window.initPersianDatepickers();
        }
    });

    // تابع کمکی برای تبدیل تاریخ میلادی به شمسی (پشتیبان)
    window.gregorianToJalali = function(gy, gm, gd) {
        const g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        let jy = (gy <= 1600) ? 0 : 979;
        gy -= (gy <= 1600) ? 621 : 1600;
        let gy2 = (gm > 2) ? (gy + 1) : gy;
        let days = (365 * gy) + Math.floor((gy2 + 3) / 4) - Math.floor((gy2 + 99) / 100) 
                 + Math.floor((gy2 + 399) / 400) - 80 + gd + g_d_m[gm - 1];
        jy += 33 * Math.floor(days / 12053);
        days %= 12053;
        jy += 4 * Math.floor(days / 1461);
        days %= 1461;
        if (days > 365) {
            jy += Math.floor((days - 1) / 365);
            days = (days - 1) % 365;
        }
        let jm = (days < 186) ? 1 + Math.floor(days / 31) : 7 + Math.floor((days - 186) / 30);
        let jd = 1 + ((days < 186) ? (days % 31) : ((days - 186) % 30));
        return {
            year: jy,
            month: jm,
            day: jd,
            formatted: jy + '-' + String(jm).padStart(2, '0') + '-' + String(jd).padStart(2, '0')
        };
    };

})();