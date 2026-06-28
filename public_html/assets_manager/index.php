<?php
define('SECURE_ACCESS', true);
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireLogin();

$user = getCurrentUser();
$role = $_SESSION['role'];
$user_id = intval($_SESSION['user_id']);
$is_readonly = ($role === 'observer');
$branch_id = ($role === 'branch') ? $user_id : (intval($_GET['branch_id'] ?? 0));
$branch_name = $user['branch_name'] ?? '';
$selected_date = $_GET['date'] ?? date('Y/m/d');

// ذخیره
if (isset($_POST['save'])) {
    header('Content-Type: application/json; charset=utf-8');
    $date = str_replace('/', '-', $_POST['date'] ?? '');
    $items = json_decode($_POST['items'] ?? '[]', true);
    
    $stmt = mysqli_prepare($conn, "DELETE FROM asset_records WHERE branch_id = ? AND record_date = ?");
    mysqli_stmt_bind_param($stmt, "is", $branch_id, $date);
    mysqli_stmt_execute($stmt);
    
    $count = 0;
    $stmt = mysqli_prepare($conn, "INSERT INTO asset_records (branch_id, item_id, record_date, amount, description, created_by) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($items as $item) {
        if (floatval($item['amount'] ?? 0) > 0) {
            mysqli_stmt_bind_param($stmt, "iisdsi", $branch_id, $item['id'], $date, $item['amount'], $item['desc'], $user_id);
            if (mysqli_stmt_execute($stmt)) $count++;
        }
    }
    echo json_encode(['success' => true, 'message' => "✅ $count مورد ذخیره شد"]);
    exit;
}

// آیتم‌ها
$items = [];
$res = mysqli_query($conn, "SELECT * FROM asset_items WHERE active = 1 ORDER BY sort_order");
while ($r = mysqli_fetch_assoc($res)) $items[] = $r;

// داده‌های ذخیره‌شده
$today_data = [];
$d = str_replace('/', '-', $selected_date);
$res = mysqli_query($conn, "SELECT * FROM asset_records WHERE branch_id = $branch_id AND record_date = '$d'");
while ($r = mysqli_fetch_assoc($res)) $today_data[$r['item_id']] = $r;
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>دارایی‌ها | <?php echo $branch_name; ?></title>
    <link href="../assets/fonts/fonts.css" rel="stylesheet">
    <link href="../assets/css/dynamic-theme.php" rel="stylesheet">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Vazirmatn',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;padding:14px}
        .container{max-width:700px;margin:0 auto}
        .header{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:14px 18px;display:flex;align-items:center;justify-content:space-between;margin-bottom:16px}
        .header h2{font-size:1.1rem;color:var(--gold)}
        .card{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:18px}
        .date-bar{display:flex;align-items:center;gap:10px;margin-bottom:14px}
        .date-bar input{padding:6px 10px;border-radius:6px;background:var(--input-bg);border:1px solid var(--input-border);color:var(--text);font-family:Vazirmatn}
        table{width:100%;border-collapse:collapse;margin-top:12px;font-size:.8rem}
        th,td{border:1px solid var(--border);padding:10px;text-align:center}
        th{background:rgba(255,255,255,0.03);color:var(--text-secondary);font-size:.7rem}
        td input{width:100%;padding:6px;text-align:center;background:var(--input-bg);border:1px solid var(--input-border);border-radius:6px;color:var(--text);font-family:Vazirmatn}
        .btn-back{color:var(--text-secondary);text-decoration:none;font-size:.75rem}
        .toast{position:fixed;bottom:20px;right:20px;padding:10px 18px;border-radius:10px;color:#fff;z-index:9999;display:none}
        .toast.success{background:var(--green)}
        .toast.error{background:var(--red)}
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h2>📦 دارایی‌ها | <?php echo $branch_name; ?></h2>
        <a href="../index.php" class="btn-back">← بازگشت</a>
    </div>
    
    <div class="card">
        <div class="date-bar">
            📅 <input type="text" id="recordDate" value="<?php echo $selected_date; ?>" onchange="loadDate()">
        </div>
        
        <table>
            <thead><tr><th>نام دارایی</th><th>مبلغ (میلیون تومان)</th><th>توضیحات</th></tr></thead>
            <tbody>
                <?php foreach($items as $item): $v = $today_data[$item['id']] ?? null; ?>
                <tr>
                    <td><?php echo htmlspecialchars($item['name']); ?></td>
                    <td><input type="text" class="amount-input" data-id="<?php echo $item['id']; ?>" value="<?php echo $v ? number_format($v['amount'], 1) : ''; ?>" placeholder="۰" oninput="formatNum(this);debounceSave()"></td>
                    <td><input type="text" class="desc-input" data-id="<?php echo $item['id']; ?>" value="<?php echo $v ? htmlspecialchars($v['description'] ?? '') : ''; ?>" placeholder="—" oninput="debounceSave()"></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<div id="toast" class="toast"></div>
<script>
function parseNum(s){return parseFloat(String(s||'').replace(/,/g,''))||0}
function formatNum(el){let raw=el.value.replace(/,/g,'').replace(/[^0-9.]/g,'');if(raw)el.value=Number(raw).toLocaleString('en-US')}
function showToast(msg,type){let t=document.getElementById('toast');t.textContent=msg;t.className='toast '+(type||'success');t.style.display='block';setTimeout(()=>t.style.display='none',2000)}
function loadDate(){window.location.href='index.php?date='+document.getElementById('recordDate').value}
let saveTimer;function debounceSave(){clearTimeout(saveTimer);saveTimer=setTimeout(saveData,2000)}
async function saveData(){
    let date=document.getElementById('recordDate').value;
    let items=[];
    document.querySelectorAll('.amount-input').forEach(inp=>{
        let amt=parseNum(inp.value);
        if(amt>0){
            let desc=document.querySelector('.desc-input[data-id="'+inp.dataset.id+'"]')?.value||'';
            items.push({id:parseInt(inp.dataset.id),amount:amt,desc:desc});
        }
    });
    if(!date||!items.length)return;
    await fetch('index.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'save=1&date='+encodeURIComponent(date)+'&items='+encodeURIComponent(JSON.stringify(items))});
}
</script>
</body>
</html>