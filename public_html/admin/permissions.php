<?php
define('SECURE_ACCESS', true);
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireAdmin();

// لیست همه دسترسی‌های موجود در سیستم
$all_permissions = ['pre_invoice', 'salary', 'reports', 'settings'];

// دریافت کاربران
$users = [];
$res = mysqli_query($conn, "SELECT id, username, branch_name, role, permissions FROM users ORDER BY role, branch_name");
while ($row = mysqli_fetch_assoc($res)) $users[] = $row;

// ذخیره تغییرات
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'])) {
    $user_id = intval($_POST['user_id']);
    $perms = isset($_POST['perms']) ? $_POST['perms'] : [];
    $perm_string = implode(',', array_intersect($perms, $all_permissions));
    
    mysqli_query($conn, "UPDATE users SET permissions = '" . mysqli_real_escape_string($conn, $perm_string) . "' WHERE id = $user_id");
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'دسترسی‌ها بروز شد']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>مدیریت دسترسی‌ها</title>
    <link href="../assets/fonts/fonts.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Vazirmatn', sans-serif;
            background: #0a0f1a;
            color: #e8ecf1;
            padding: 20px;
            max-width: 800px;
            margin: 0 auto;
        }
        .card {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: 14px;
            padding: 20px;
            margin-bottom: 16px;
        }
        table { width: 100%; border-collapse: collapse; font-size: 0.8rem; }
        th, td { padding: 10px 8px; border-bottom: 1px solid rgba(255,255,255,0.06); text-align: center; }
        th { color: #8899aa; font-size: 0.7rem; }
        .perm-checkbox { width: 18px; height: 18px; cursor: pointer; accent-color: #4b8cf7; }
        .save-btn {
            padding: 4px 12px; border-radius: 6px; background: #10b981;
            border: none; color: #fff; cursor: pointer; font-family: 'Vazirmatn';
            font-size: 0.7rem; transition: all 0.2s;
        }
        .save-btn:hover { filter: brightness(1.2); }
        .toast {
            position: fixed; top: 20px; left: 50%; transform: translateX(-50%);
            padding: 10px 24px; border-radius: 10px; z-index: 999;
            font-size: 0.8rem; font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="card">
        <h2 style="margin:0 0 16px;font-size:1rem;">مدیریت دسترسی کاربران</h2>
        <table>
            <thead>
                <tr>
                    <th>کاربر</th>
                    <th>نقش</th>
                    <?php foreach($all_permissions as $p): ?>
                    <th><?php echo $p; ?></th>
                    <?php endforeach; ?>
                    <th>ذخیره</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($users as $u): 
                    $user_perms = explode(',', $u['permissions'] ?? '');
                    $uid = $u['id'];
                ?>
                <tr id="row-<?php echo $uid; ?>">
                    <td><?php echo $u['branch_name'] ?: $u['username']; ?></td>
                    <td><?php echo $u['role'] === 'admin' ? 'ادمین' : ($u['role'] === 'branch' ? 'شعبه' : 'ناظر'); ?></td>
                    <?php foreach($all_permissions as $p): ?>
                    <td>
                        <input type="checkbox" 
                               class="perm-checkbox perm-<?php echo $uid; ?>" 
                               value="<?php echo $p; ?>"
                               <?php echo in_array($p, $user_perms) ? 'checked' : ''; ?>
                               <?php echo $u['role'] === 'admin' ? 'disabled' : ''; ?>>
                    </td>
                    <?php endforeach; ?>
                    <td>
                        <?php if($u['role'] !== 'admin'): ?>
                        <button class="save-btn" onclick="savePerms(<?php echo $uid; ?>)">ذخیره</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <script>
    function savePerms(userId) {
        let perms = [];
        document.querySelectorAll('.perm-' + userId + ':checked').forEach(function(cb) {
            perms.push(cb.value);
        });
        
        let formData = new URLSearchParams();
        formData.append('user_id', userId);
        perms.forEach(function(p) { formData.append('perms[]', p); });
        
        fetch('permissions.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(d => {
            showToast(d.message, d.success ? '#10b981' : '#ef4444');
        });
    }
    
    function showToast(msg, color) {
        let t = document.createElement('div');
        t.className = 'toast';
        t.textContent = msg;
        t.style.background = color;
        document.body.appendChild(t);
        setTimeout(function(){ t.remove(); }, 2000);
    }
    </script>
</body>
</html>