function showToast(msg, type='success') {
    var c = document.getElementById('toastContainer');
    if (!c) {
        c = document.createElement('div');
        c.id = 'toastContainer';
        c.style.cssText = 'position:fixed;bottom:20px;right:20px;z-index:9999;display:flex;flex-direction:column;gap:8px;';
        document.body.appendChild(c);
    }
    var t = document.createElement('div');
    t.className = 'toast ' + type;
    t.textContent = msg;
    t.style.cssText = 'padding:12px 20px;border-radius:10px;color:white;font-size:0.9rem;animation:slideIn 0.3s ease;';
    if (type === 'success') t.style.background = '#10b981';
    else if (type === 'error') t.style.background = '#ef4444';
    c.appendChild(t);
    setTimeout(() => t.remove(), 3000);
}