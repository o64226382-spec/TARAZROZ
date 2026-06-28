<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('SECURE_ACCESS', true);
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireAdmin();

echo "<h2>🔍 خطایابی اهداف</h2>";

// 1. تست اتصال
echo "<h3>1. اتصال به دیتابیس:</h3>";
if ($conn) {
    echo "✅ اتصال برقرار است<br>";
} else {
    die("❌ اتصال失败");
}

// 2. تست جدول goal_types
echo "<h3>2. جدول goal_types:</h3>";
$q = mysqli_query($conn, "SELECT * FROM goal_types");
if ($q) {
    echo "✅ کوئری اجرا شد، تعداد رکورد: " . mysqli_num_rows($q) . "<br>";
    while ($row = mysqli_fetch_assoc($q)) {
        echo "- ID: {$row['id']}, Name: {$row['name']}, Unit: {$row['unit']}<br>";
    }
} else {
    echo "❌ خطا: " . mysqli_error($conn) . "<br>";
}

// 3. تست جدول branches
echo "<h3>3. جدول users (شعب):</h3>";
$q = mysqli_query($conn, "SELECT id, branch_name FROM users WHERE role = 'branch'");
if ($q) {
    echo "✅ تعداد شعب: " . mysqli_num_rows($q) . "<br>";
    while ($row = mysqli_fetch_assoc($q)) {
        echo "- ID: {$row['id']}, Name: {$row['branch_name']}<br>";
    }
} else {
    echo "❌ خطا: " . mysqli_error($conn) . "<br>";
}

// 4. تست جدول branch_goals
echo "<h3>4. جدول branch_goals:</h3>";
$q = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM branch_goals");
if ($q) {
    $row = mysqli_fetch_assoc($q);
    echo "✅ تعداد رکورد: {$row['cnt']}<br>";
} else {
    echo "❌ خطا: " . mysqli_error($conn) . "<br>";
}

echo "<h3>5. مسیر فایل:</h3>";
echo "مسیر فعلی: " . __DIR__ . "<br>";
echo "فایل این است: " . __FILE__ . "<br>";

echo "<h3>✅ تست کامل شد. اگر خطایی دیدی، لطفاً کپی کن.</h3>";
?>