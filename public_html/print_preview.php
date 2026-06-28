<?php
$printContent = $_POST['content'] ?? $_GET['content'] ?? '';
$originalContent = $printContent; // نگهداری نسخه اصلی برای تغییرات
if (empty($printContent)) {
    die('<div style="text-align:center;padding:50px;font-family:Vazirmatn;">محتوایی برای پیش‌نمایش وجود ندارد.<br><a href="index.php">بازگشت</a></div>');
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پیش‌نمایش و دانلود PDF | تراز روزانه</title>
    <link href="assets/fonts/fonts.css" rel="stylesheet">
    <link rel="icon" href="assets/images/logo.png">
    <style>
        :root {
            --bg: #1a1f2e; --surface: rgba(255,255,255,0.04);
            --border: rgba(255,255,255,0.08); --text: #e8ecf1;
            --text-secondary: #94a3b8; --accent: #4b8cf7;
            --gold: #d4af37; --success: #10b981; --danger: #ef4444;
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Vazirmatn', sans-serif;
            background: var(--bg); color: var(--text);
            min-height: 100vh; display: flex; flex-direction: column;
        }
        
        .toolbar {
            background: var(--surface); border-bottom: 1px solid var(--border);
            padding: 10px 16px; display: flex; align-items: center;
            gap: 12px; flex-wrap: wrap; backdrop-filter: blur(12px);
            position: sticky; top: 0; z-index: 100;
        }
        .toolbar-group {
            display: flex; align-items: center; gap: 8px;
            background: rgba(0,0,0,0.2); padding: 4px 10px;
            border-radius: 8px; border-right: 2px solid var(--accent);
        }
        .toolbar-group label { font-size: 0.7rem; color: var(--text-secondary); white-space: nowrap; }
        .toolbar select, .toolbar input {
            padding: 6px 10px; border-radius: 6px;
            background: rgba(255,255,255,0.04); border: 1px solid var(--border);
            color: var(--text); font-family: 'Vazirmatn'; font-size: 0.75rem;
        }
        .toolbar input[type="range"] { width: 80px; }
        
        .btn {
            padding: 8px 16px; border-radius: 6px; border: none;
            cursor: pointer; font-family: 'Vazirmatn'; font-weight: 600;
            font-size: 0.75rem; transition: all 0.2s;
        }
        .btn-print { background: var(--gold); color: #1a1a1a; }
        .btn-download { background: var(--accent); color: white; }
        .btn-close { background: var(--danger); color: white; }
        .btn-fit { background: var(--success); color: white; }
        .btn:hover { opacity: 0.85; transform: translateY(-1px); }
        
        .preview-container {
            flex: 1; overflow: auto; padding: 20px;
            display: flex; justify-content: center; background: #555;
        }
        
        .preview-frame {
            width: 210mm; height: 100%; border: none;
            background: white; box-shadow: 0 4px 20px rgba(0,0,0,0.4);
            transition: all 0.2s ease;
        }
        
        .preview-frame.landscape { width: 297mm; }
        .preview-frame.a5 { width: 148mm; }
        .preview-frame.a5.landscape { width: 210mm; }
        
        .status-bar {
            background: var(--surface); padding: 5px 16px;
            font-size: 0.7rem; color: var(--text-secondary);
            display: flex; gap: 20px; border-top: 1px solid var(--border);
        }
        
        @media print {
            .toolbar, .status-bar { display: none; }
            .preview-container { padding: 0; background: white; }
            .preview-frame { box-shadow: none; width: 100%; height: auto; }
        }
    </style>
</head>
<body>

<div class="toolbar">
    <div class="toolbar-group">
        <label>📄 سایز:</label>
        <select id="paperSize" onchange="updatePreview()">
            <option value="A4_portrait">A4 عمودی</option>
            <option value="A4_landscape">A4 افقی</option>
            <option value="A5_portrait">A5 عمودی</option>
            <option value="A5_landscape">A5 افقی</option>
        </select>
    </div>
    
    <div class="toolbar-group">
        <label>📏 حاشیه:</label>
        <select id="marginSize" onchange="updatePreview()">
            <option value="5mm">خیلی کم (۵mm)</option>
            <option value="8mm">کم (۸mm)</option>
            <option value="12mm" selected>متوسط (۱۲mm)</option>
            <option value="16mm">زیاد (۱۶mm)</option>
            <option value="20mm">خیلی زیاد (۲۰mm)</option>
        </select>
    </div>
    
    <div class="toolbar-group">
        <label>✏️ فونت:</label>
        <select id="fontSize" onchange="updatePreview()">
            <option value="10">بسیار ریز (۱۰px)</option>
            <option value="12" selected>ریز (۱۲px)</option>
            <option value="14">متوسط (۱۴px)</option>
            <option value="16">درشت (۱۶px)</option>
            <option value="18">بسیار درشت (۱۸px)</option>
        </select>
    </div>
    
    <div class="toolbar-group">
        <label>📏 فاصله خطوط:</label>
        <select id="lineHeight" onchange="updatePreview()">
            <option value="1.2">کم</option>
            <option value="1.5" selected>متوسط</option>
            <option value="1.8">زیاد</option>
            <option value="2">خیلی زیاد</option>
        </select>
    </div>
    
    <div class="toolbar-group">
        <label>🔍 بزرگنمایی:</label>
        <input type="range" id="zoomLevel" min="30" max="200" value="100" oninput="updateZoom()">
        <span id="zoomLabel" style="font-size:0.7rem;min-width:40px;">۱۰۰٪</span>
    </div>
    
    <div style="flex:1;"></div>
    
    <button class="btn btn-fit" onclick="fitToPage()">📐 فیت در یک صفحه</button>
    <button class="btn btn-print" onclick="doPrint()">🖨️ پرینت</button>
    <button class="btn btn-download" onclick="doDownload()">📥 دانلود PDF</button>
    <button class="btn btn-close" onclick="window.close()">✕ بستن</button>
</div>

<div class="preview-container">
    <iframe id="previewFrame" class="preview-frame" src="about:blank"></iframe>
</div>

<div class="status-bar">
    <span>📄 سایز کاغذ: A4 عمودی</span>
    <span>📏 حاشیه: ۱۲mm</span>
    <span>✏️ اندازه فونت: ۱۲px</span>
    <span>📐 وضعیت: در حال بارگذاری...</span>
</div>

<script>
// متغیرهای اصلی
let currentHtml = '';
let isApplyingFit = false;

// تابع به روز رسانی وضعیت
function updateStatus(message) {
    const statusSpan = document.querySelector('.status-bar span:last-child');
    if (statusSpan) statusSpan.textContent = message;
}

// تابع بارگذاری محتوا در iframe
function loadContent() {
    updateStatus('در حال بارگذاری محتوا...');
    let html = <?php echo json_encode($originalContent); ?>;
    currentHtml = html;
    
    let frame = document.getElementById('previewFrame');
    let doc = frame.contentDocument || frame.contentWindow.document;
    doc.open();
    doc.write(html);
    doc.close();
    
    // اعمال تنظیمات پس از بارگذاری
    setTimeout(() => {
        updatePreview();
        updateStatus('✅ آماده | تعداد صفحات: ' + getPageCount());
    }, 100);
}

// دریافت تعداد صفحات (تقریبی)
function getPageCount() {
    let frame = document.getElementById('previewFrame');
    let doc = frame.contentDocument || frame.contentWindow.document;
    let body = doc.body;
    if (!body) return '?';
    
    // محاسبه ارتفاع کل محتوا تقسیم بر ارتفاع صفحه
    let totalHeight = body.scrollHeight;
    let pageHeight = 277; // mm تقریبی A4 با حاشیه 12mm
    let size = document.getElementById('paperSize').value;
    if (size.includes('A5')) pageHeight = 200;
    
    let pages = Math.ceil(totalHeight / (pageHeight * 3.78)); // تبدیل تقریبی
    return pages;
}

// به روز رسانی تنظیمات در iframe
function updatePreview() {
    if (isApplyingFit) return;
    
    let paperSizeSelect = document.getElementById('paperSize');
    let margin = document.getElementById('marginSize').value;
    let baseFont = document.getElementById('fontSize').value;
    let lineHeight = document.getElementById('lineHeight').value;
    
    let paperSize = paperSizeSelect.value;
    let frame = document.getElementById('previewFrame');
    
    // تغییر کلاس‌های iframe برای نمایش
    frame.classList.remove('landscape', 'a5');
    if (paperSize === 'A4_landscape') {
        frame.classList.add('landscape');
    } else if (paperSize === 'A5_portrait') {
        frame.classList.add('a5');
    } else if (paperSize === 'A5_landscape') {
        frame.classList.add('a5', 'landscape');
    }
    
    // به روز رسانی وضعیت نمایشی
    let sizeText = paperSizeSelect.options[paperSizeSelect.selectedIndex].text;
    document.querySelector('.status-bar span:first-child').textContent = `📄 سایز: ${sizeText}`;
    document.querySelector('.status-bar span:nth-child(2)').textContent = `📏 حاشیه: ${margin}`;
    document.querySelector('.status-bar span:nth-child(3)').textContent = `✏️ فونت: ${baseFont}px`;
    
    // اعمال تغییرات به محتوای iframe
    try {
        let iframeDoc = frame.contentDocument || frame.contentWindow.document;
        if (iframeDoc && iframeDoc.body) {
            // ایجاد یا به روز رسانی استایل‌های صفحه
            let style = iframeDoc.querySelector('style#print-controls');
            if (!style) {
                style = iframeDoc.createElement('style');
                style.id = 'print-controls';
                iframeDoc.head.appendChild(style);
            }
            
            // تعیین سایز و جهت کاغذ
            let paperSizeCSS = '';
            let sheetSize = '';
            if (paperSize === 'A4_portrait') { sheetSize = 'A4'; paperSizeCSS = 'portrait'; }
            else if (paperSize === 'A4_landscape') { sheetSize = 'A4'; paperSizeCSS = 'landscape'; }
            else if (paperSize === 'A5_portrait') { sheetSize = 'A5'; paperSizeCSS = 'portrait'; }
            else if (paperSize === 'A5_landscape') { sheetSize = 'A5'; paperSizeCSS = 'landscape'; }
            
            style.innerHTML = `
                @page {
                    size: ${sheetSize} ${paperSizeCSS};
                    margin: ${margin};
                }
                body {
                    font-size: ${baseFont}px !important;
                    line-height: ${lineHeight} !important;
                    margin: 0 !important;
                    padding: 0 !important;
                }
                @media print {
                    body {
                        margin: 0;
                        padding: 0;
                    }
                }
            `;
            
            // اعمال فونت در تمام المنت‌ها
            let allElements = iframeDoc.querySelectorAll('*');
            allElements.forEach(el => {
                if (el.style) {
                    el.style.fontSize = baseFont + 'px';
                    el.style.lineHeight = lineHeight;
                }
            });
            
            updateStatus('✅ تنظیمات اعمال شد | صفحات: ' + getPageCount());
        }
    } catch(e) {
        console.error('خطا در اعمال تنظیمات:', e);
        updateStatus('⚠️ خطا در اعمال تنظیمات');
    }
}

// به روز رسانی فقط بزرگنمایی
function updateZoom() {
    let zoom = document.getElementById('zoomLevel').value;
    let frame = document.getElementById('previewFrame');
    frame.style.transform = `scale(${zoom / 100})`;
    frame.style.transformOrigin = 'top center';
    document.getElementById('zoomLabel').textContent = zoom + '٪';
}

// فیت کردن محتوا در یک صفحه
function fitToPage() {
    isApplyingFit = true;
    updateStatus('🔄 در حال فیت کردن محتوا در یک صفحه...');
    
    let frame = document.getElementById('previewFrame');
    let iframeDoc = frame.contentDocument || frame.contentWindow.document;
    let body = iframeDoc.body;
    
    if (!body) {
        updateStatus('❌ خطا: محتوایی یافت نشد');
        isApplyingFit = false;
        return;
    }
    
    // ذخیره تنظیمات فعلی
    let originalHeight = body.style.height;
    
    // دریافت ارتفاع صفحه بر اساس سایز انتخاب شده
    let paperSize = document.getElementById('paperSize').value;
    let margin = document.getElementById('marginSize').value;
    
    // تبدیل margin به px (تقریبی)
    let marginPx = parseInt(margin) * 3.78;
    let pageHeightPx = (paperSize.includes('A5') ? 210 : 297) * 3.78 - (marginPx * 2);
    
    // محاسبه نسبت فشردگی مورد نیاز
    let contentHeight = body.scrollHeight;
    let scale = pageHeightPx / contentHeight;
    
    if (scale >= 1) {
        updateStatus('✅ محتوا در یک صفحه جای می‌گیرد');
        isApplyingFit = false;
        return;
    }
    
    // اعمال فشردگی محتوا
    body.style.transform = `scale(${scale})`;
    body.style.transformOrigin = 'top center';
    body.style.height = `${contentHeight * scale}px`;
    
    // کوچک کردن فونت‌ها به نسبت
    let currentFont = parseInt(document.getElementById('fontSize').value);
    let newFont = Math.max(8, Math.floor(currentFont * scale));
    document.getElementById('fontSize').value = newFont;
    
    // به روز رسانی تنظیمات
    updatePreview();
    
    updateStatus(`✅ محتوا فیت شد (ضریب: ${scale.toFixed(2)}) | تعداد صفحات: ۱`);
    
    setTimeout(() => {
        isApplyingFit = false;
    }, 500);
}

// پرینت
function doPrint() {
    let frame = document.getElementById('previewFrame');
    frame.contentWindow.print();
    updateStatus('🖨️ ارسال به پرینتر...');
}

// دانلود به عنوان HTML/PDF
function doDownload() {
    let frame = document.getElementById('previewFrame');
    let doc = frame.contentDocument || frame.contentWindow.document;
    let html = '<!DOCTYPE html>\n' + doc.documentElement.outerHTML;
    
    // افزودن استایل پرینت بهینه برای PDF
    let blob = new Blob([html], { type: 'text/html;charset=utf-8' });
    let url = URL.createObjectURL(blob);
    let a = document.createElement('a');
    a.href = url;
    
    let paperSize = document.getElementById('paperSize').value;
    let fileName = paperSize.includes('A4') ? 'تراز_روزانه_A4' : 'تراز_روزانه_A5';
    fileName += '.html';
    a.download = fileName;
    a.click();
    URL.revokeObjectURL(url);
    
    updateStatus('📥 فایل دانلود شد');
}

// بارگذاری اولیه
window.onload = function() {
    loadContent();
};
</script>
</body>
</html>