<?php
define('SECURE_ACCESS', true);
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireAdmin();

$branches = [];
$res = mysqli_query($conn, "SELECT id, branch_name FROM users WHERE role = 'branch'");
while ($r = mysqli_fetch_assoc($res)) $branches[] = $r;

$selected_branch = intval($_GET['branch_id'] ?? ($branches[0]['id'] ?? 0));
$from = $_GET['from'] ?? date('Y/m/d', strtotime('-30 days'));
$to = $_GET['to'] ?? date('Y/m/d');

$from_db = str_replace('/', '-', $from);
$to_db = str_replace('/', '-', $to);

$data = [];
$stmt = mysqli_prepare($conn, "SELECT ar.record_date, SUM(ar.amount) as total FROM asset_records ar WHERE ar.branch_id = ? AND ar.record_date BETWEEN ? AND ? GROUP BY ar.record_date ORDER BY ar.record_date");
mysqli_stmt_bind_param($stmt, "iss", $selected_branch, $from_db, $to_db);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($r = mysqli_fetch_assoc($res)) $data[] = $r;
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>آمار دارایی‌ها</title>
    <link href="../assets/fonts/fonts.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        :root{--bg:#0a0f1a;--surface:rgba(255,255,255,0.03);--border:rgba(255,255,255,0.06);--text:#e8ecf1;--accent:#4b8cf7;--gold:#d4af37;--radius:14px}
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Vazirmatn',sans-serif;background:var(--bg);color:var(--text);padding:16px}
        .container{max-width:1100px;margin:0 auto}
        h2{color:var(--gold);margin-bottom:16px}
        .filters{display:flex;gap:10px;flex-wrap:wrap;align-items:end;margin-bottom:16px}
        select,input{padding:8px;border-radius:8px;background:rgba(255,255,255,0.03);border:1px solid var(--border);color:var(--text);font-family:Vazirmatn}
        .btn{padding:8px 16px;background:var(--accent);color:#fff;border:none;border-radius:8px;cursor:pointer;font-family:Vazirmatn}
        .chart-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:16px}
        .chart-box{height:350px}
        a{color:var(--accent);text-decoration:none;font-size:.8rem}
    </style>
</head>
<body>
<div class="container">
    <a href="index.php">← بازگشت</a>
    <h2>📊 آمار دارایی‌ها</h2>
    
    <form class="filters">
        <select name="branch_id"><?php foreach($branches as $b): ?><option value="<?php echo $b['id']; ?>" <?php echo $b['id']==$selected_branch?'selected':''; ?>><?php echo $b['branch_name']; ?></option><?php endforeach; ?></select>
        <input type="text" name="from" value="<?php echo $from; ?>" placeholder="از تاریخ">
        <input type="text" name="to" value="<?php echo $to; ?>" placeholder="تا تاریخ">
        <button class="btn">🔍 فیلتر</button>
    </form>
    
    <div class="chart-card">
        <div class="chart-box"><canvas id="assetChart"></canvas></div>
    </div>
</div>
<script>
const data = <?php echo json_encode($data); ?>;
new Chart(document.getElementById('assetChart'), {
    type: 'line',
    data: {
        labels: data.map(d => d.record_date),
        datasets: [{label:'دارایی (میلیون تومان)',data:data.map(d=>d.total),borderColor:'#d4af37',backgroundColor:'rgba(212,175,55,0.1)',fill:true,tension:.4}]
    },
    options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{labels:{color:'#8899aa',font:{family:'Vazirmatn'}}}},scales:{x:{ticks:{color:'#8899aa'}},y:{ticks:{color:'#8899aa'}}}}
});
</script>
</body>
</html>