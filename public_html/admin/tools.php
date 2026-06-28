<?php
define('SECURE_ACCESS', true);
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireAdmin();

$tools = [];
$res = mysqli_query($conn, "SELECT * FROM tools ORDER BY name");
while ($row = mysqli_fetch_assoc($res)) $tools[] = $row;

$users = [];
$res2 = mysqli_query($conn, "SELECT id, username, branch_name, role, permissions FROM users WHERE role != 'admin' ORDER BY branch_name");
while ($row = mysqli_fetch_assoc($res2)) $users[] = $row;
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>مدیریت ابزارها</title>
    <link href="../assets/fonts/fonts.css" rel="stylesheet">
    <style>
        :root {
            --bg: #0a0f1a; --surface: rgba(255,255,255,0.03);
            --border: rgba(255,255,255,0.05); --text: #e8ecf1;
            --accent: #4b8cf7; --green: #10b981; --red: #ef4444;
            --radius: 12px;
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Vazirmatn',sans-serif; background:var(--bg); color:var(--text); padding:16px; }
        .container { max-width:1100px; margin:0 auto; }
        h2 { margin-bottom:16px; }
        .card { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); padding:16px; margin-bottom:14px; }
        table { width:100%; border-collapse:collapse; font-size:0.75rem; margin-top:10px; }
        th,td { border:1px solid var(--border); padding:8px; text-align:center; }
        th { background:rgba(255,255,255,0.03); }
        .btn { padding:6px 12px; border:none; border-radius:6px; cursor:pointer; font-family:'Vazirmatn'; font-size:0.7rem; color:white; margin:2px; }
        .btn-primary { background:var(--accent); }
        .btn-success { background:var(--green); }
        .btn-danger { background:var(--red); }
        .btn-secondary { background:#475569; }
        .toggle { cursor:pointer; font-size:1.2rem; user-select:none; }
        .toggle.on { color:var(--green); }
        .toggle.off { color:#475569; }
        input,select { padding:8px; border-radius:6px; border:1px solid var(--border); background:rgba(255,255,255,0.03); color:var(--text); font-family:'Vazirmatn'; margin:4px; }
        .form-row { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
        .back-link { color:var(--accent); text-decoration:none; font-size:0.8rem; }
    </style>
</head>
<body>
<div class="container">
    <a href="index.php" class="back-link">← بازگشت به پنل</a>
    <h2>🔧 مدیریت ابزارها</h2>
    
    <!-- ===== فرم افزودن ===== -->
    <div class="card">
        <h3 id="formTitle">➕ افزودن ابزار جدید</h3>
        <input type="hidden" id="toolId" value="0">
        <div class="form-row">
            <input type="text" id="toolName" placeholder="نام ابزار" style="flex:2;">
            <input type="text" id="toolSlug" placeholder="slug (انگلیسی)" style="flex:1;">
            <input type="text" id="toolUrl" placeholder="مسیر فایل" style="flex:2;">
            <input type="text" id="toolIcon" placeholder="آیکون" style="flex:0.5;" value="🔧">
            <input type="text" id="toolDesc" placeholder="توضیحات" style="flex:2;">
            <select id="toolActive" style="flex:0.5;">
                <option value="1">فعال</option>
                <option value="0">غیرفعال</option>
            </select>
            <button class="btn btn-primary" onclick="saveTool()">💾 ذخیره</button>
            <button class="btn btn-secondary" onclick="resetForm()">🔄 جدید</button>
        </div>
    </div>
    
    <!-- ===== لیست ابزارها ===== -->
    <div class="card">
        <h3>📋 ابزارهای موجود</h3>
        <table>
            <thead><tr><th>آیکون</th><th>نام</th><th>slug</th><th>مسیر</th><th>وضعیت</th><th>عملیات</th></tr></thead>
            <tbody id="toolsTableBody">
                <?php foreach($tools as $t): ?>
                <tr>
                    <td><?php echo $t['icon']; ?></td>
                    <td><?php echo $t['name']; ?></td>
                    <td><?php echo $t['slug']; ?></td>
                    <td><?php echo $t['url']; ?></td>
                    <td><?php echo $t['active'] ? '✅' : '❌'; ?></td>
                    <td>
                        <button class="btn btn-primary" onclick="editTool(<?php echo $t['id']; ?>, '<?php echo addslashes($t['name']); ?>', '<?php echo $t['slug']; ?>', '<?php echo $t['url']; ?>', '<?php echo $t['icon']; ?>', '<?php echo addslashes($t['description']); ?>', <?php echo $t['active']; ?>)">✏️</button>
                        <button class="btn btn-danger" onclick="deleteTool(<?php echo $t['id']; ?>)">🗑️</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <!-- ===== دسترسی کاربران ===== -->
    <div class="card">
        <h3>👥 دسترسی کاربران</h3>
        <div style="overflow-x:auto;">
            <table>
                <thead>
                    <tr>
                        <th>کاربر</th>
                        <th>نقش</th>
                        <?php foreach($tools as $t): ?>
                        <th><?php echo $t['icon']; ?><br><small><?php echo $t['name']; ?></small></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($users as $u): 
                        $userPerms = $u['permissions'] ?? '';
                    ?>
                    <tr>
                        <td><?php echo $u['branch_name']; ?></td>
                        <td><?php echo $u['role'] === 'observer' ? 'ناظر' : 'شعبه'; ?></td>
                        <?php foreach($tools as $t): 
                            $hasAccess = strpos($userPerms, $t['slug']) !== false;
                        ?>
                        <td>
                            <span class="toggle <?php echo $hasAccess ? 'on' : 'off'; ?>" 
                                  onclick="toggleUserTool(<?php echo $u['id']; ?>, '<?php echo $t['slug']; ?>', this)">
                                <?php echo $hasAccess ? '✅' : '⬜'; ?>
                            </span>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function saveTool() {
    const id = document.getElementById('toolId').value;
    const name = document.getElementById('toolName').value;
    const slug = document.getElementById('toolSlug').value;
    const url = document.getElementById('toolUrl').value;
    const icon = document.getElementById('toolIcon').value;
    const desc = document.getElementById('toolDesc').value;
    const active = document.getElementById('toolActive').value;
    
    if (!name || !slug || !url) { alert('فیلدهای ضروری را پر کنید'); return; }
    
    fetch('tools_handler.php', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:`action=save_tool&id=${id}&name=${encodeURIComponent(name)}&slug=${encodeURIComponent(slug)}&url=${encodeURIComponent(url)}&icon=${encodeURIComponent(icon)}&description=${encodeURIComponent(desc)}&active=${active}`
    })
    .then(r => r.json())
    .then(d => { alert(d.message); if(d.success) location.reload(); });
}

function editTool(id, name, slug, url, icon, desc, active) {
    document.getElementById('formTitle').textContent = '✏️ ویرایش ابزار';
    document.getElementById('toolId').value = id;
    document.getElementById('toolName').value = name;
    document.getElementById('toolSlug').value = slug;
    document.getElementById('toolUrl').value = url;
    document.getElementById('toolIcon').value = icon;
    document.getElementById('toolDesc').value = desc;
    document.getElementById('toolActive').value = active;
}

function resetForm() {
    document.getElementById('formTitle').textContent = '➕ افزودن ابزار جدید';
    document.getElementById('toolId').value = '0';
    document.getElementById('toolName').value = '';
    document.getElementById('toolSlug').value = '';
    document.getElementById('toolUrl').value = '';
    document.getElementById('toolIcon').value = '🔧';
    document.getElementById('toolDesc').value = '';
    document.getElementById('toolActive').value = '1';
}

function deleteTool(id) {
    if (!confirm('آیا مطمئن هستید؟')) return;
    fetch('tools_handler.php', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:`action=delete_tool&id=${id}`
    })
    .then(r => r.json())
    .then(d => { alert(d.message); if(d.success) location.reload(); });
}

function toggleUserTool(userId, slug, el) {
    fetch('tools_handler.php', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:`action=toggle_user_tool&user_id=${userId}&slug=${encodeURIComponent(slug)}`
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            el.classList.toggle('on');
            el.classList.toggle('off');
            el.textContent = el.classList.contains('on') ? '✅' : '⬜';
        }
    });
}
</script>
</body>
</html>