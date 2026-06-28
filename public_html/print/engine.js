(() => {
    const DATA = window.__PRINT_DATA__ || { mode: 'empty', payload: '', error: '' };

    const frame     = document.getElementById('previewFrame');
    const paperDiv  = document.getElementById('paper');
    const mode      = DATA.mode;

    // ========= وضعیت خطا یا خالی =========
    if (mode === 'empty' || mode === 'error') {
        const msg = mode === 'empty'
            ? '❌ محتوایی برای پرینت یافت نشد.'
            : `❌ ${DATA.error || 'خطا در دریافت محتوا.'}`;

        document.querySelector('.preview-area').innerHTML =
            `<div style="background:#1e293b; color:white; padding:40px; border-radius:20px; text-align:center;">${msg}<br><a href="../index.php" style="color:#60a5fa;">بازگشت به صفحه اصلی</a></div>`;
        return;
    }

    // ========= PDF Mode =========
    if (mode === 'pdf') {
        disableHtmlControls();
        loadPdf(DATA.payload);
        return;
    }

    // ========= HTML Mode =========
    if (mode === 'html') {
        loadHtml(DATA.payload);
        attachEvents();
        updateZoom();
        return;
    }

    // -------- Functions --------

    function disableHtmlControls() {
        document.getElementById('margin').disabled = true;
        document.getElementById('baseFont').disabled = true;
        document.getElementById('lineHeight').disabled = true;
        document.getElementById('fitBtn').disabled = true;

        document.getElementById('statPages').innerText = '--';
        attachEvents();
        updateZoom();
    }

    function loadPdf(base64) {
        // برای PDF بهتره sandbox حذف شود تا نمایشگر مرورگر درست کار کند
        frame.removeAttribute('sandbox');

        const blob = b64ToBlob(base64, 'application/pdf');
        const url = URL.createObjectURL(blob);
        frame.src = url;

        window.addEventListener('beforeunload', () => URL.revokeObjectURL(url));
    }

    function loadHtml(rawHTML) {
        frame.setAttribute('sandbox', 'allow-same-origin');
        frame.onload = () => {
            const doc = frame.contentDocument || frame.contentWindow.document;
            doc.open();
            doc.write(`<!doctype html>
                <html lang="fa" dir="rtl">
                <head>
                    <meta charset="UTF-8">
                    <link rel="stylesheet" href="fonts.css">
                </head>
                <body>${rawHTML}</body>
                </html>`);
            doc.close();

            applyAllStyles();
            updatePageCount();
        };
        frame.src = 'about:blank';
        attachEvents();
    }

    // ==================== توابع اصلی (HTML Mode) ====================
    function applyAllStyles() {
        const doc = frame.contentDocument || frame.contentWindow.document;
        if (!doc || !doc.body) return;

        let styleTag = doc.getElementById('printMasterStyle');
        if (styleTag) styleTag.remove();

        const paperSize = document.getElementById('paperSize').value;
        const marginVal = document.getElementById('margin').value;
        const baseFont = document.getElementById('baseFont').value;
        const lineH = document.getElementById('lineHeight').value;

        let pageSize = "A4", orientation = "portrait";
        if (paperSize === "A4-landscape") { pageSize = "A4"; orientation = "landscape"; }
        else if (paperSize === "A5-portrait") { pageSize = "A5"; orientation = "portrait"; }
        else if (paperSize === "A5-landscape") { pageSize = "A5"; orientation = "landscape"; }

        styleTag = doc.createElement('style');
        styleTag.id = 'printMasterStyle';
        styleTag.innerHTML = `
            @page {
                size: ${pageSize} ${orientation};
                margin: ${marginVal};
            }
            body {
                margin: 0 auto !important;
                padding: 0 !important;
                font-size: ${baseFont}px !important;
                line-height: ${lineH} !important;
                direction: rtl;
                text-align: justify;
                background: white;
                font-family: 'Vazirmatn', 'Segoe UI', system-ui, sans-serif;
            }
            table, div, p, h1, h2, h3, h4, h5, h6, .container, .content {
                margin-right: auto;
                margin-left: auto;
            }
            * { box-sizing: border-box; }
        `;
        doc.head.appendChild(styleTag);

        paperDiv.className = `paper ${paperSize}`;

        document.getElementById('statPaper').innerText =
            document.getElementById('paperSize').options[document.getElementById('paperSize').selectedIndex].text;
        document.getElementById('statMargin').innerText = marginVal;
        document.getElementById('statFont').innerText = baseFont + 'px';

        if (!window._isFitActive) {
            doc.body.style.transform = "";
        }
    }

    function updatePageCount() {
        const doc = frame.contentDocument || frame.contentWindow.document;
        if (!doc || !doc.body) return;

        const paperSize = document.getElementById('paperSize').value;
        const marginVal = document.getElementById('margin').value;

        let heightMM = (paperSize.includes('portrait') ? 297 : 210);
        if (paperSize.includes('A5')) heightMM = (paperSize.includes('portrait') ? 210 : 148);

        const marginMM = parseInt(marginVal, 10);
        const contentHeightPx = (heightMM - marginMM * 2) * 3.78;
        const totalHeight = doc.body.scrollHeight;
        const pages = Math.ceil(totalHeight / contentHeightPx);

        document.getElementById('statPages').innerText = pages;
    }

    function updateZoom() {
        const z = parseInt(document.getElementById('zoom').value, 10);
        paperDiv.style.transform = `scale(${z / 100})`;
        paperDiv.style.transformOrigin = 'top center';
        document.getElementById('zoomVal').innerText = z + '%';
    }

    function fitToPage() {
        const doc = frame.contentDocument || frame.contentWindow.document;
        if (!doc || !doc.body) return;

        const paperSize = document.getElementById('paperSize').value;
        const marginVal = document.getElementById('margin').value;

        let heightMM = (paperSize.includes('portrait') ? 297 : 210);
        if (paperSize.includes('A5')) heightMM = (paperSize.includes('portrait') ? 210 : 148);

        const marginMM = parseInt(marginVal, 10);
        const maxContentPx = (heightMM - marginMM * 2) * 3.78;
        const currentHeight = doc.body.scrollHeight;
        if (currentHeight <= maxContentPx) return;

        const scale = maxContentPx / currentHeight;
        const currentFont = parseInt(document.getElementById('baseFont').value, 10);
        const newFont = Math.max(8, Math.floor(currentFont * scale));

        document.getElementById('baseFont').value = newFont;
        window._isFitActive = true;

        applyAllStyles();
        doc.body.style.transform = `scale(${scale})`;
        doc.body.style.transformOrigin = 'top center';

        setTimeout(() => {
            updatePageCount();
            window._isFitActive = false;
        }, 100);
    }

    function resetAll() {
        document.getElementById('paperSize').value = "A4-portrait";
        document.getElementById('margin').value = "10mm";
        document.getElementById('baseFont').value = "12";
        document.getElementById('lineHeight').value = "1.5";
        document.getElementById('zoom').value = "100";

        updateZoom();
        window._isFitActive = false;

        applyAllStyles();
        const doc = frame.contentDocument || frame.contentWindow.document;
        if (doc && doc.body) doc.body.style.transform = "";

        setTimeout(updatePageCount, 100);
    }

    function doPrint() {
        applyAllStyles();
        setTimeout(() => {
            if (frame.contentWindow) frame.contentWindow.print();
        }, 300);
    }

    function attachEvents() {
        document.getElementById('paperSize').addEventListener('change', () => { applyAllStyles(); updatePageCount(); });
        document.getElementById('margin').addEventListener('change', () => { applyAllStyles(); updatePageCount(); });
        document.getElementById('baseFont').addEventListener('change', () => { applyAllStyles(); updatePageCount(); });
        document.getElementById('lineHeight').addEventListener('change', () => { applyAllStyles(); updatePageCount(); });
        document.getElementById('zoom').addEventListener('input', updateZoom);
        document.getElementById('fitBtn').addEventListener('click', fitToPage);
        document.getElementById('resetBtn').addEventListener('click', resetAll);
        document.getElementById('printBtn').addEventListener('click', doPrint);
        document.getElementById('closeBtn').addEventListener('click', () => window.close());

        setInterval(() => {
            if (mode === 'html' && frame.contentDocument && frame.contentDocument.body) updatePageCount();
        }, 800);
    }

    function b64ToBlob(base64, type) {
        const byteCharacters = atob(base64);
        const byteNumbers = new Array(byteCharacters.length);
        for (let i = 0; i < byteCharacters.length; i++) {
            byteNumbers[i] = byteCharacters.charCodeAt(i);
        }
        const byteArray = new Uint8Array(byteNumbers);
        return new Blob([byteArray], { type });
    }
})();
