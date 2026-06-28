/**
 * ============================================
 * فایل: assets/js/main.js
 * توضیح: تمام عملکردهای JavaScript داشبورد
 * ============================================
 */

// ---------- ۱. توابع نمایش و مدیریت تقویم ----------

/**
 * تابع اصلی بارگذاری تقویم با AJAX
 * @param {number} y سال جلالی
 * @param {number} m ماه جلالی
 */
window.loadCal = function(y, m) {
    var container = document.getElementById('calContainer');
    if (!container) return;
    
    // نمایش حالت بارگذاری
    container.innerHTML = '<div class="glass-panel loading-state"><span style="font-size:2rem; display:block; margin-bottom:10px;">⏳</span>در حال بارگذاری...</div>';
    
    // استفاده از XMLHttpRequest برای سازگاری بهتر
    var xhr = new XMLHttpRequest();
    var loaded = false;
    
    // تنظیم timeout برای شبکه‌های CGNAT
    xhr.timeout = 30000; // 30 ثانیه
    
    xhr.open('GET', 'calendar_content.php?year=' + y + '&month=' + m + '&_=' + Date.now(), true);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    
    xhr.onload = function() {
        if (loaded) return;
        loaded = true;
        
        if (xhr.status >= 200 && xhr.status < 400) {
            // درج محتوای HTML دریافت شده
            container.innerHTML = xhr.responseText;
            
            // اجرای تگ‌های اسکریپت موجود در پاسخ
            var scripts = container.querySelectorAll('script');
            for (var i = 0; i < scripts.length; i++) {
                var newScript = document.createElement('script');
                newScript.textContent = scripts[i].textContent;
                document.body.appendChild(newScript);
                scripts[i].remove();
            }
        } else {
            // نمایش خطا با دکمه تلاش مجدد
            container.innerHTML = '<div class="welcome-card glass-panel">' +
                '<p style="color: var(--red); margin-bottom: 10px;">⚠️ خطا در بارگذاری تقویم</p>' +
                '<button onclick="window.loadCal(' + y + ', ' + m + ')" class="btn-primary" style="font-size: 0.8rem;">🔄 تلاش مجدد</button>' +
            '</div>';
        }
    };
    
    xhr.onerror = function() {
        if (loaded) return;
        loaded = true;
        container.innerHTML = '<div class="welcome-card glass-panel">' +
            '<p style="color: var(--red); margin-bottom: 10px;">⚠️ خطا در بارگذاری تقویم</p>' +
            '<button onclick="window.loadCal(' + y + ', ' + m + ')" class="btn-primary" style="font-size: 0.8rem;">🔄 تلاش مجدد</button>' +
        '</div>';
    };
    
    xhr.ontimeout = function() {
        if (loaded) return;
        loaded = true;
        container.innerHTML = '<div class="welcome-card glass-panel">' +
            '<p style="color: var(--red); margin-bottom: 10px;">⚠️ خطا در بارگذاری تقویم ( timeout )</p>' +
            '<button onclick="window.loadCal(' + y + ', ' + m + ')" class="btn-primary" style="font-size: 0.8rem;">🔄 تلاش مجدد</button>' +
        '</div>';
    };
    
    xhr.send();
};

/**
 * هدایت به تقویم ماه قبل/بعد
 * @param {number} delta میزان تغییر ماه (1 یا -1)
 */
window.navCalMonth = function(delta) {
    var yearSelect = document.getElementById('calYear');
    var monthSelect = document.getElementById('calMonth');
    if (!yearSelect || !monthSelect) return;
    var y = parseInt(yearSelect.value);
    var m = parseInt(monthSelect.value) + delta;
    if (m > 12) { m = 1; y++; }
    if (m < 1) { m = 12; y--; }
    window.curYear = y;
    window.curMonth = m;
    window.loadCal(y, m);
};

/**
 * بازگشت به تاریخ امروز در تقویم
 */
window.goToday = function() {
    // curYear و curMonth توسط PHP در HTML تنظیم شده‌اند
    window.loadCal(window.curYear, window.curMonth);
};

// ---------- ۲. توابع نمایش خلاصه و هدایت به گزارشات ----------

/**
 * نمایش خلاصه تراکنش‌های یک روز انتخاب شده در تقویم
 * @param {string} dk تاریخ به فرمت YYYY-MM-DD
 * @param {string} du تاریخ به فرمت نمایشی
 */
window.showSum = function(dk, du) {
    window.selectedCalDate = dk;
    var allData = window.calAllData || {};
    
    // پاک کردن حالت انتخاب از همه سلول‌ها
    var cells = document.querySelectorAll('.day-cell');
    for (var i = 0; i < cells.length; i++) cells[i].classList.remove('selected');
    // انتخاب سلول جاری
    var cell = document.querySelector('[data-date="' + dk + '"]');
    if (cell) cell.classList.add('selected');
    
    var h = '', has = false;
    // پیمایش در داده‌های شعب
    for (var bid in allData) {
        var br = allData[bid], ba = br.bal[dk] || null, inc = br.inc[dk] || {rial:0, gold:0};
        if (!ba && inc.rial == 0 && inc.gold == 0) continue;
        has = true;
        
        h += '<div class="branch-summ"><div class="branch-name">' + br.name + '</div><div class="summ-grid">';
        
        if (ba && ba.de != 0) {
            h += '<div class="summ-item" onclick="goToBal(\'' + dk + '\', ' + bid + ')">';
            h += '<div class="val d">' + Number(ba.de).toLocaleString() + '</div><div class="lbl">بدهکاران</div></div>';
        }
        
        if (ba && ba.cr != 0) {
            h += '<div class="summ-item" onclick="goToBal(\'' + dk + '\', ' + bid + ')">';
            h += '<div class="val c">' + Number(ba.cr).toLocaleString() + '</div><div class="lbl">بستانکاران</div></div>';
        }
        
        if (ba && (ba.de != 0 || ba.cr != 0)) {
            var diff = ba.cr - ba.de;
            if (diff !== 0) {
                h += '<div class="summ-item" onclick="goToBal(\'' + dk + '\', ' + bid + ')">';
                h += '<div class="val" style="color:' + (diff < 0 ? 'var(--accent)' : 'var(--red)') + ';">' + Number(Math.abs(diff)).toLocaleString() + '</div>';
                h += '<div class="lbl">' + (diff < 0 ? 'فزونی' : 'کسری') + '</div></div>';
            }
        }
        
        if (ba && ba.pe != 0) {
            h += '<div class="summ-item" onclick="goToBal(\'' + dk + '\', ' + bid + ')">';
            h += '<div class="val p">' + Number(ba.pe).toLocaleString() + '</div><div class="lbl">تنخواه</div></div>';
        }
        
        if (ba && ba.ba != 0) {
            h += '<div class="summ-item" onclick="goToBal(\'' + dk + '\', ' + bid + ')">';
            h += '<div class="val b">' + Number(ba.ba).toLocaleString() + '</div><div class="lbl">بنکداران</div></div>';
        }
        
        if (ba && ba.dy != 0) {
            h += '<div class="summ-item" onclick="goToBal(\'' + dk + '\', ' + bid + ')">';
            h += '<div class="val dy">' + Number(ba.dy).toLocaleString() + '</div><div class="lbl">داینامیک</div></div>';
        }
        
        if (inc.rial != 0 || inc.gold != 0) {
            h += '<div class="summ-item" onclick="goToInc(\'' + dk + '\', ' + bid + ')">';
            if (inc.rial != 0) h += '<div class="val" style="color:' + (inc.rial < 0 ? 'var(--red)' : 'var(--green)') + ';">' + Number(inc.rial).toLocaleString() + ' ریال</div>';
            if (inc.gold != 0) h += '<div class="val" style="color:' + (inc.gold < 0 ? 'var(--red)' : 'var(--gold-light)') + ';">' + Number(inc.gold).toLocaleString() + ' گرم</div>';
            h += '<div class="lbl">درآمد</div></div>';
        }
        
        h += '</div></div>';
    }
    
    var sumTitle = document.getElementById('sumTitle');
    var sumContent = document.getElementById('sumContent');
    if (sumTitle) sumTitle.innerHTML = has ? 'گزارش مالی روز: <span style="color:#fff;">' + du + '</span>' : 'برای مشاهده جزئیات، روی یک روز کلیک کنید';
    if (sumContent) sumContent.innerHTML = has ? h : '<div class="loading-state">هیچ تراکنشی در این روز ثبت نشده است</div>';
};

/**
 * هدایت به صفحه بدهکاران/بستانکاران
 * @param {string} dk تاریخ
 * @param {number} bid شناسه شعبه
 */
function goToBal(dk, bid) {
    if (userRole === 'branch') {
        window.location.href = 'user/index.php?date=' + dk;
    } else {
        window.location.href = 'view.php?date=' + dk.replace(/-/g, '/') + '&branch_id=' + bid + '&tab=balance';
    }
}

/**
 * هدایت به صفحه درآمد
 * @param {string} dk تاریخ
 * @param {number} bid شناسه شعبه
 */
function goToInc(dk, bid) {
    if (userRole === 'branch') {
        window.location.href = 'income/index.php?date=' + dk;
    } else {
        window.location.href = 'view.php?date=' + dk.replace(/-/g, '/') + '&branch_id=' + bid + '&tab=income';
    }
}

/**
 * هدایت به صفحات ثبت با تاریخ انتخاب شده
 * @param {string} page مسیر صفحه
 * @param {boolean} isMonthly آیا ثبت ماهانه است؟
 */
window.goToRegPage = function(page, isMonthly) {
    isMonthly = isMonthly || false;
    var date = window.selectedCalDate || '<?php echo $todayDateEn; ?>';
    if (!date || date === 'undefined' || date === '') {
        alert('لطفاً ابتدا یک روز را از تقویم انتخاب کنید');
        return;
    }
    date = date.replace(/\//g, '-');
    if (isMonthly) {
        var parts = date.split('-');
        window.location.href = page + '?year=' + parts[0] + '&month=' + parts[1] + '&date=' + date;
    } else {
        window.location.href = page + '?date=' + date;
    }
};

// ---------- ۳. مدیریت تب‌ها و تم ----------

/**
 * جابجایی بین تب‌های اصلی
 * @param {string} tab نام تب ('home' یا 'tools')
 */
function switchTab(tab) {
    document.querySelectorAll('.tab-content').forEach(function(t) { t.classList.remove('active'); });
    document.querySelectorAll('.nav-logo, .nav-btn').forEach(function(n) { n.classList.remove('active'); });
    var target = document.getElementById('tab-' + tab);
    if (target) target.classList.add('active');
    var btn = document.querySelector('[data-tab="' + tab + '"]');
    if (btn) btn.classList.add('active');
    // اگر تب خانه فعال شد، تقویم را دوباره بارگذاری کن
    if (tab === 'home') window.loadCal(window.curYear, window.curMonth);
}

/**
 * تغییر تم بین حالت تاریک و روشن
 */
function toggleTheme() {
    document.body.classList.toggle('light');
    const isLight = document.body.classList.contains('light');
    // ذخیره تنظیمات در localStorage
    localStorage.setItem('theme', isLight ? 'light' : 'dark');
}

/**
 * اعمال تم ذخیره شده هنگام بارگذاری صفحه
 */
function applySavedTheme() {
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'light') {
        document.body.classList.add('light');
    } else {
        document.body.classList.remove('light');
    }
}

// ---------- ۴. وضعیت کاربران آنلاین ----------

/**
 * دریافت و نمایش کارت‌های وضعیت کاربران برای ناظرین
 */
function loadUsersMiniCards() {
    // استفاده از XMLHttpRequest برای سازگاری
    var xhr = new XMLHttpRequest();
    xhr.open('GET', 'update_activity.php', true);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    
    xhr.onload = function() {
        if (xhr.status >= 200 && xhr.status < 400) {
            try {
                var data = JSON.parse(xhr.responseText);
                if (data.users && data.users.length > 0) {
                    renderMiniCards(data.users);
                } else {
                    var grid = document.getElementById('usersOnlineGrid');
                    if (grid) grid.innerHTML = '<div class="glass-panel loading-state" style="grid-column:1/-1;">هیچ کاربری تخصیص داده نشده</div>';
                }
            } catch (e) {
                console.error('خطا در پردازش JSON وضعیت کاربران:', e);
            }
        }
    };
    
    xhr.onerror = function() {
        var grid = document.getElementById('usersOnlineGrid');
        if (grid) grid.innerHTML = '<div class="glass-panel loading-state" style="grid-column:1/-1;color:var(--red);">خطا در دریافت اطلاعات</div>';
    };
    
    xhr.send();
}

/**
 * رندر کردن کارت‌های HTML وضعیت کاربران
 * @param {Array} users آرایه‌ای از داده‌های کاربران
 */
function renderMiniCards(users) {
    var grid = document.getElementById('usersOnlineGrid');
    if (!grid) return;
    var html = '';
    users.forEach(function(user) {
        var isOnline = user.is_online;
        var dotClass = isOnline ? 'online' : 'offline';
        var statusText = isOnline ? 'آنلاین' : 'آفلاین';
        var statusClass = isOnline ? '' : 'offline';
        var lastSeenTime = '', lastSeenDate = '';
        if (isOnline) {
            lastSeenTime = 'همین الان';
        } else if (user.last_activity_shamsi) {
            var parts = user.last_activity_shamsi.split(' ');
            if (parts.length === 2) {
                lastSeenDate = parts[0];
                lastSeenTime = parts[1];
            } else {
                lastSeenDate = user.last_activity_shamsi;
            }
        } else {
            lastSeenTime = 'نامشخص';
        }
        html += '<div class="user-status-mini-card">' +
                    '<div class="status-dot ' + dotClass + '"></div>' +
                    '<div class="user-name">' + escapeHtml(user.full_name || user.username) + '</div>' +
                    '<div class="user-status-text ' + statusClass + '">' + statusText + '</div>' +
                    '<div class="user-last-seen">' + 
                        (lastSeenTime ? '<span>' + lastSeenTime + '</span>' : '') + 
                        (lastSeenDate ? '<span>' + lastSeenDate + '</span>' : '') + 
                    '</div>' +
                '</div>';
    });
    grid.innerHTML = html;
}

/**
 * تابع کمکی برای فرار از کاراکترهای HTML
 * @param {string} text متن ورودی
 * @returns {string} متن ایمن شده
 */
function escapeHtml(text) {
    if (!text) return '';
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ---------- ۵. راه‌اندازی اولیه (DOMContentLoaded) ----------
document.addEventListener('DOMContentLoaded', function() {
    // الف. اعمال تم ذخیره شده
    applySavedTheme();
    
    // ب. فعال‌سازی دکمه تغییر تم
    const themeBtn = document.getElementById('themeToggle');
    if (themeBtn) {
        themeBtn.addEventListener('click', toggleTheme);
    }
    
    // پ. بارگذاری اولیه تقویم
    window.loadCal(window.curYear, window.curMonth);
    
    // ت. بارگذاری وضعیت کاربران آنلاین برای ناظرین و تنظیم به‌روزرسانی دوره‌ای
    if (userRole === 'observer') {
        loadUsersMiniCards();
        setInterval(loadUsersMiniCards, 30000); // هر ۳۰ ثانیه
    }
    
    // ث. به‌روزرسانی فعالیت کاربر جاری و تنظیم به‌روزرسانی دوره‌ای
    fetch('update_activity.php').catch(function() {});
    setInterval(function() { fetch('update_activity.php').catch(function() {}); }, 120000); // هر ۲ دقیقه
    
    // ========== تایمر زنده ==========
    (function(){
        var el = document.getElementById('workCountdown');
        if(!el) return;
        
        var text = el.textContent.trim();
        var parts = text.split(':');
        var seconds = 0;
        if (parts.length === 3) {
            seconds = parseInt(parts[0]) * 3600 + parseInt(parts[1]) * 60 + parseInt(parts[2]);
        } else if (parts.length === 2) {
            seconds = parseInt(parts[0]) * 60 + parseInt(parts[1]);
        }
        
        if(seconds <= 0) return;
        
        function formatTime(t){
            var h = Math.floor(t/3600);
            var m = Math.floor((t%3600)/60);
            var s = t%60;
            return (h>0?h+':':'')+String(m).padStart(2,'0')+':'+String(s).padStart(2,'0');
        }
        
        var interval = setInterval(function(){
            seconds--;
            if(seconds <= 0){
                el.parentElement.innerHTML = '<span style="font-weight:700;color:var(--gold-light);">ساعت کاری به پایان رسید ✨</span>';
                clearInterval(interval);
            } else {
                el.textContent = formatTime(seconds);
            }
        }, 1000);
    })();
    
});

/**
 * تایمر شمارش معکوس ساعات کاری
 */
(function(){
    var el = document.getElementById('workCountdown');
    if(!el) return;
    
    var parts = el.textContent.split(':');
    var seconds = 0;
    if (parts.length === 3) {
        seconds = parseInt(parts[0]) * 3600 + parseInt(parts[1]) * 60 + parseInt(parts[2]);
    } else if (parts.length === 2) {
        seconds = parseInt(parts[0]) * 60 + parseInt(parts[1]);
    }
    
    if(seconds <= 0) return;
    
    function formatTime(t){
        var h = Math.floor(t/3600);
        var m = Math.floor((t%3600)/60);
        var s = t%60;
        return (h>0?h+':':'')+String(m).padStart(2,'0')+':'+String(s).padStart(2,'0');
    }
    
    var interval = setInterval(function(){
        seconds--;
        if(seconds <= 0){
            el.parentElement.innerHTML = '<span style="font-weight:700;color:var(--gold-light);">ساعت کاری به پایان رسید ✨</span>';
            clearInterval(interval);
        } else {
            el.textContent = formatTime(seconds);
        }
    }, 1000);
})();

// ============================================
// ۶. توابع سیستم اعلان‌ها (Notification Bell)
// ============================================

/**
 * باز کردن پاپ‌آپ اعلان‌ها و لود محتوا با AJAX
 */
function openNotifPopup() {
    var overlay = document.getElementById('notifOverlay');
    var popup = document.getElementById('notifPopup');
    
    if (!overlay || !popup) return;
    
    overlay.classList.add('active');
    popup.classList.add('active');
    document.body.style.overflow = 'hidden';
    
    // لود محتوای popup.php با AJAX
    var xhr = new XMLHttpRequest();
    xhr.open('GET', 'notifications/popup.php?_=' + Date.now(), true);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    
    xhr.onload = function() {
        if (xhr.status >= 200 && xhr.status < 400) {
            popup.innerHTML = xhr.responseText;
        } else {
            popup.innerHTML = '<div class="notif-popup-header"><span>🔔 خطا</span><button class="notif-popup-close" onclick="closeNotifPopup()">✕</button></div><div class="notif-popup-body"><div class="notif-empty"><p style="color:var(--red);">خطا در بارگذاری</p></div></div>';
        }
    };
    
    xhr.onerror = function() {
        popup.innerHTML = '<div class="notif-popup-header"><span>🔔 خطا</span><button class="notif-popup-close" onclick="closeNotifPopup()">✕</button></div><div class="notif-popup-body"><div class="notif-empty"><p style="color:var(--red);">خطا در بارگذاری</p></div></div>';
    };
    
    xhr.send();
}

/**
 * بستن پاپ‌آپ اعلان‌ها
 */
function closeNotifPopup() {
    var overlay = document.getElementById('notifOverlay');
    var popup = document.getElementById('notifPopup');
    
    if (overlay) overlay.classList.remove('active');
    if (popup) popup.classList.remove('active');
    document.body.style.overflow = '';
    
    // بروزرسانی تعداد اعلان‌ها
    refreshNotifCount();
}

/**
 * علامت‌گذاری پیام به عنوان خوانده شده
 */
function markMessageRead(messageId) {
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'notifications/mark_read.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.send('message_id=' + messageId);
    
    // تغییر ظاهری
    var el = document.getElementById('message-' + messageId);
    if (el) {
        el.classList.remove('unread');
        el.classList.add('read');
    }
}

/**
 * بروزرسانی تعداد اعلان‌ها و وضعیت زنگوله
 */
function refreshNotifCount() {
    var xhr = new XMLHttpRequest();
    xhr.open('GET', 'notifications/get_count.php?_=' + Date.now(), true);
    xhr.onload = function() {
        if (xhr.status === 200) {
            var count = parseInt(xhr.responseText) || 0;
            var bell = document.getElementById('notifBell');
            var badge = bell ? bell.querySelector('.notif-badge') : null;
            
            if (count > 0) {
                // اضافه کردن کلاس has-notif برای قرمز شدن
                if (bell) bell.classList.add('has-notif');
                
                // بروزرسانی بج
                if (badge) {
                    badge.textContent = count;
                } else if (bell) {
                    var newBadge = document.createElement('span');
                    newBadge.className = 'notif-badge';
                    newBadge.textContent = count;
                    bell.appendChild(newBadge);
                }
            } else {
                // حذف کلاس هشدار
                if (bell) bell.classList.remove('has-notif');
                if (badge) badge.remove();
            }
        }
    };
    xhr.send();
}

// بروزرسانی خودکار هر ۶۰ ثانیه
setInterval(function() {
    if (typeof userRole !== 'undefined' && userRole === 'branch') {
        refreshNotifCount();
    }
}, 60000);
function sendObserverMsg(form) {
    var formData = new FormData(form);
    formData.append('send_msg', '1');
    
    fetch('notifications/popup.php', { method: 'POST', body: formData })
        .then(r => r.text())
        .then(html => {
            document.getElementById('notifPopup').innerHTML = html;
        });
}

function deleteObserverMsg(id) {
    if (!confirm('حذف این پیام؟')) return;
    fetch('notifications/delete_msg.php?id=' + id)
        .then(() => openNotifPopup());
}
// ====== تابع برگشت به صفحه قبلی (جهانی) ======
window.goBackOrHome = function() {
    if (document.referrer && document.referrer !== window.location.href) {
        window.history.back();
        // اگه history.back کار نکرد، بعد ۵۰۰ میلی‌ثانیه بره صفحه اصلی
        setTimeout(function() {
            window.location.href = 'index.php';
        }, 500);
    } else {
        window.location.href = 'index.php';
    }
};