<?php
define('SECURE_ACCESS', true);
require_once 'includes/config.php';
require_once 'includes/auth.php';

// فقط ادمین
if ($_SESSION['role'] !== 'admin') {
    die('شما دسترسی ندارید');
}

// تست اتصال به دیتابیس
echo "<h2>📊 تست اهداف</h2>";

// 1. بررسی جدول goal_types
$result = mysqli_query($conn, "SHOW TABLES LIKE 'goal_types'");
echo "<h3>1. بررسی جدول goal_types:</h3>";
if (mysqli_num_rows($result) > 0) {
    echo "✅ جدول goal_types وجود دارد<br>";
    
    // نمایش محتویات جدول
    $goals = mysqli_query($conn, "SELECT * FROM goal_types WHERE is_active=1 ORDER BY sort_order");
    echo "<table border='1' cellpadding='8' style='border-collapse:collapse;'>";
    echo "<tr><th>ID</th><th>نام</th><th>واحد</th><th>آیکون</th></tr>";
    while ($row = mysqli_fetch_assoc($goals)) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . htmlspecialchars($row['name']) . "</td>";
        echo "<td>" . $row['unit'] . "</td>";
        echo "<td>" . $row['icon'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "❌ جدول goal_types وجود ندارد!<br>";
    echo "لطفاً این جدول را در phpMyAdmin ایجاد کنید.<br>";
}

// 2. بررسی جدول branch_goals
$result2 = mysqli_query($conn, "SHOW TABLES LIKE 'branch_goals'");
echo "<h3>2. بررسی جدول branch_goals:</h3>";
if (mysqli_num_rows($result2) > 0) {
    echo "✅ جدول branch_goals وجود دارد<br>";
    
    // نمایش محتویات
    $branches = mysqli_query($conn, "SELECT * FROM branch_goals LIMIT 10");
    if (mysqli_num_rows($branches) > 0) {
        echo "<table border='1' cellpadding='8'>";
        echo "<tr><th>ID</th><th>شعبه ID</th><th>نوع هدف</th><th>مقدار هدف</th><th>سال</th><th>ماه</th></tr>";
        while ($row = mysqli_fetch_assoc($branches)) {
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . $row['branch_id'] . "</td>";
            echo "<td>" . $row['goal_type_id'] . "</td>";
            echo "<td>" . $row['target_value'] . "</td>";
            echo "<td>" . $row['year'] . "</td>";
            echo "<td>" . $row['month'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "📭 هنوز هیچ هدفی ثبت نشده است<br>";
    }
} else {
    echo "❌ جدول branch_goals وجود ندارد!<br>";
}

// 3. لیست شعب
echo "<h3>3. لیست شعب:</h3>";
$users = mysqli_query($conn, "SELECT id, username, branch_name, role FROM users");
echo "<table border='1' cellpadding='8'>";
echo "<tr><th>ID</th><th>نام کاربری</th><th>نام شعبه</th><th>نقش</th></tr>";
while ($user = mysqli_fetch_assoc($users)) {
    echo "<tr>";
    echo "<td>" . $user['id'] . "</td>";
    echo "<td>" . $user['username'] . "</td>";
    echo "<td>" . htmlspecialchars($user['branch_name']) . "</td>";
    echo "<td>" . $user['role'] . "</td>";
    echo "</tr>";
}
echo "</table>";

// 4. فرم ساده برای درج هدف
echo "<h3>4. فرم ثبت هدف جدید:</h3>";
?>

<form method="POST">
    <select name="branch_id" required>
        <option value="">انتخاب شعبه</option>
        <?php
        $users2 = mysqli_query($conn, "SELECT id, branch_name FROM users WHERE role = 'branch'");
        while ($user = mysqli_fetch_assoc($users2)) {
            echo "<option value='{$user['id']}'>{$user['branch_name']}</option>";
        }
        ?>
    </select>
    
    <select name="goal_type_id" required>
        <option value="">انتخاب نوع هدف</option>
        <option value="1">وام طلایی ثنا</option>
        <option value="2">فروش قسطی طلا</option>
        <option value="3">وام رسالت</option>
        <option value="4">وام نیک کارت</option>
        <option value="5">حساب آتیه طلا</option>
        <option value="6">معاملات ماهانه</option>
        <option value="7">وام آتیه ریالی</option>
    </select>
    
    <input type="number" step="0.001" name="target_value" placeholder="مقدار هدف" required>
    
    <select name="year" required>
        <?php for($y = 1404; $y <= 1406; $y++): ?>
            <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
        <?php endfor; ?>
    </select>
    
    <select name="month" required>
        <?php for($m = 1; $m <= 12; $m++): ?>
            <option value="<?php echo $m; ?>">ماه <?php echo $m; ?></option>
        <?php endfor; ?>
    </select>
    
    <button type="submit" name="submit">ذخیره هدف</button>
</form>

<?php
// پردازش فرم
if (isset($_POST['submit'])) {
    $branch_id = (int)$_POST['branch_id'];
    $goal_type_id = (int)$_POST['goal_type_id'];
    $target_value = (float)$_POST['target_value'];
    $year = (int)$_POST['year'];
    $month = (int)$_POST['month'];
    $created_by = $_SESSION['user_id'];
    
    // حذف رکورد قبلی اگر وجود دارد
    mysqli_query($conn, "DELETE FROM branch_goals WHERE branch_id = $branch_id AND goal_type_id = $goal_type_id AND year = $year AND month = $month");
    
    // درج رکورد جدید
    $sql = "INSERT INTO branch_goals (branch_id, goal_type_id, target_value, year, month, created_by) 
            VALUES ($branch_id, $goal_type_id, $target_value, $year, $month, $created_by)";
    
    if (mysqli_query($conn, $sql)) {
        echo "<p style='color:green;'>✅ هدف با موفقیت ثبت شد!</p>";
        echo "<meta http-equiv='refresh' content='2'>";
    } else {
        echo "<p style='color:red;'>❌ خطا: " . mysqli_error($conn) . "</p>";
    }
}
?>