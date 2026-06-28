<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die(json_encode(['ok' => false]));
}

require_once 'includes/config.php';
require_once 'includes/jdf.php';

$user_id = $_SESSION['user_id'];
$current_role = $_SESSION['role'] ?? '';

// فقط آپدیت last_activity
$stmt = mysqli_prepare($conn, "UPDATE users SET last_activity = NOW() WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $user_id);
$success = mysqli_stmt_execute($stmt);

// برگردوندن کاربران برای ناظر
$users = [];
if ($current_role === 'observer') {
    $result = mysqli_query($conn, "
        SELECT u.id, u.username, u.full_name, u.branch_name, u.role, u.last_activity
        FROM users u
        INNER JOIN observer_assignments oa ON u.id = oa.branch_id
        WHERE oa.observer_id = $user_id
        ORDER BY u.last_activity DESC
    ");
    
    while ($row = mysqli_fetch_assoc($result)) {
        $last = $row['last_activity'];
        $diff = $last ? floor((time() - strtotime($last)) / 60) : 999;
        $online = ($diff < 5);
        
        $users[] = [
            'id' => $row['id'],
            'username' => $row['username'],
            'full_name' => $row['full_name'],
            'branch_name' => $row['branch_name'] ?? 'بدون شعبه',
            'role' => $row['role'],
            'is_online' => $online,
            'last_activity_shamsi' => $last ? jdate('Y/m/d H:i:s', strtotime($last)) : null,
            'last_seen' => $last ? time_elapsed($last) : 'نامشخص'
        ];
    }
}

echo json_encode(['ok' => $success, 'users' => $users]);

function time_elapsed($datetime) {
    if (!$datetime) return 'نامشخص';
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    if ($diff->y > 0) return $diff->y . ' سال پیش';
    if ($diff->m > 0) return $diff->m . ' ماه پیش';
    if ($diff->d > 0) return $diff->d . ' روز پیش';
    if ($diff->h > 0) return $diff->h . ' ساعت پیش';
    if ($diff->i > 0) return $diff->i . ' دقیقه پیش';
    return 'لحظاتی پیش';
}