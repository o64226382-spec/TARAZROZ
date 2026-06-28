<?php
// =============================================
// print/index.php — نسخه ساده با پرینت مرورگر
// =============================================
?><!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پیش‌نمایش پرینت | تراز روزانه</title>
    
    <link href="fonts.css" rel="stylesheet">
    <link rel="icon" href="../assets/images/logo.png">
    
    <style>
        :root {
            --bg: #1a1f2e; --surface: rgba(255,255,255,0.04);
            --border: rgba(255,255,255,0.08); --text: #e8ecf1;
            --text-secondary: #94a3b8; --gold: #d4af37;
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Vazirmatn', sans-serif;
            background: var(--bg); color: var(--text);
            min-height: 100vh; display: flex; flex-direction: column;
        }
        
        /* نوار ابزار */
        .toolbar {
            background: var(--surface); border-bottom: 1px solid var(--border);
            padding: 8px 14px; display: flex; align-items: center;
            gap: 10px; flex-wrap: wrap; backdrop-filter: blur(12px);
        }
        .toolbar label { font-size: 0.7rem; color: var(--text-secondary); }
        .toolbar select {
            padding: 5px 8px; border-radius: 5px;
            background: rgba(255,255,255,0.04); border: 1px solid var(--border);
            color: var(--text); font-family: 'Vazirmatn'; font-size: 0.7rem;
        }
        
        .btn {
            padding: 7px 14px; border-radius: 5px; border: none;
            cursor: pointer; font-family: 'Vazirmatn'; font-weight: 600;
            font-size: 0.72rem; transition: all 0.2s;
        }
        .btn-print { background: var(--gold); color: #1a1a1a; }
        .btn-close { background: #ef4444; color: white; }
        .btn:hover { opacity: 0.85; }
        
        /* توضیح راهنما */
        .help-note {
            text-align: center; padding: 6px; font-size: 0.68rem;
            color: var(--text-secondary); background: rgba(255,255,255,0.02);
            border-bottom: 1px solid var(--border);
        }
        
        /* پیش‌نمایش */
        .preview-wrap {
            flex: 1; overflow: auto; padding: 16px;
            display: flex; justify-content: center; align-items: flex-start;
            background: #555;
        }
        .paper {
            background: white;
            box-shadow: 0 4px 20px rgba(0,0,0,0.5);
            transition: width 0.3s;
        }
        .paper.portrait { width: 210mm; min-height: 297mm; }
        .paper.landscape { width: 297mm; min-height: 210mm; }
        
        .paper-content {
            width: 100%; height: 100%; border: none; display: block;
        }
        .paper-content.portrait { min-height: 297mm; }
        .paper-content.landscape { min-height: 210mm; }
        
        .error-msg { text-align:center; padding:60px; color:#94a3b8; }
    </style>
</head>
<body>

    <div class="toolbar">
        <label>📄 اندازه کاغذ:</label>
        <select id="paperSize" onchange="updatePaper()">
            <option value="portrait">A4 عمودی</option>
            <option value="landscape">A4 افقی</option>
        </select>
        
        <label>📏 حاشیه:</label>
        <select id="marginSize" onchange="updateMargins()">
            <option value="5mm">کم (۵mm)</option>
            <option value="10mm" selected>متوسط (۱۰mm)</option>
            <option value="15mm">زیاد (۱۵mm)</option>
        </select>
        
        <label>🔍 بزرگنمایی:</label>
        <input type="range" id="zoomLevel" min="50" max="150" value="100" oninput="updateZoom()" style="width:80px;">
        <span id="zoomLabel" style="font-size:0.7rem;min-width:35px;">۱۰۰٪</span>
        
        <div style="flex:1;"></div>
        
        <button class="btn btn-print" onclick="doPrint()">🖨️ پرینت / Save as PDF</button>
        <button class="btn btn-close" onclick="window.close()">✕ بستن</button>
    </div>
    
    <div class="help-note">
        💡 برای ذخیره PDF: دکمه 🖨️ را بزنید ← مقصد: <b>Save as PDF</b> ← اندازه کاغذ را با انتخاب بالا هماهنگ کنید
    </div>

    <div class="preview-wrap">
        <div class="paper portrait" id="paper">
            <iframe id="previewFrame" class="paper-content portrait"></iframe>
        </div>
    </div>

<script>
// ========== ۱. گرفتن محتوا از localStorage ==========
const printContent = localStorage.getItem('printContent');
const frame = document.getElementById('previewFrame');

if (!printContent) {
    document.querySelector('.preview-wrap').innerHTML = 
        '<div class="error-msg">⛔ محتوایی برای پیش‌نمایش یافت نشد.<br><a href="../index.php" style="color:var(--accent);">بازگشت</a></div>';
} else {
    // محتوای HTML رو توی iframe نشون بده
    frame.srcdoc = printContent;
    
    // بعد از لود iframe، @page رو تنظیم کن
    frame.onload = function() {
        updateMargins();
    };
}

// ========== ۲. تغییر اندازه کاغذ ==========
function updatePaper() {
    const ps = document.getElementById('paperSize').value;
    const paper = document.getElementById('paper');
    const frame = document.getElementById('previewFrame');
    
    paper.className = 'paper ' + ps;
    frame.className = 'paper-content ' + ps;
    
    // تغییر @page داخل iframe
    updateIframePageStyle(ps, document.getElementById('marginSize').value);
}

// ========== ۳. تغییر حاشیه ==========
function updateMargins() {
    const ps = document.getElementById('paperSize').value;
    const ms = document.getElementById('marginSize').value;
    updateIframePageStyle(ps, ms);
}

function updateIframePageStyle(orientation, margin) {
    try {
        const iframeDoc = frame.contentDocument || frame.contentWindow.document;
        if (iframeDoc) {
            // حذف استایل @page قبلی
            let oldStyle = iframeDoc.getElementById('page-style');
            if (oldStyle) oldStyle.remove();
            
            // ایجاد استایل جدید
            let newStyle = iframeDoc.createElement('style');
            newStyle.id = 'page-style';
            newStyle.innerHTML = `@page { size: A4 ${orientation}; margin: ${margin}; }`;
            iframeDoc.head.appendChild(newStyle);
        }
    } catch(e) {
        // iframe ممکنه cross-origin باشه
    }
}

// ========== ۴. بزرگنمایی ==========
function updateZoom() {
    const z = parseInt(document.getElementById('zoomLevel').value);
    const paper = document.getElementById('paper');
    paper.style.transform = `scale(${z / 100})`;
    paper.style.transformOrigin = 'top center';
    document.getElementById('zoomLabel').textContent = z + '٪';
}

// ========== ۵. پرینت ==========
function doPrint() {
    // اول @page رو آپدیت کن
    updateMargins();
    
    // کمی صبر کن تا iframe آپدیت بشه، بعد پرینت
    setTimeout(() => {
        if (frame && frame.contentWindow) {
            frame.contentWindow.focus();
            frame.contentWindow.print();
        }
    }, 400);
}

// لود اولیه
updateZoom();
</script>
</body>
</html>