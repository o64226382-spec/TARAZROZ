<?php
error_reporting(0);
ini_set('display_errors', 0);

session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die(json_encode(['ok' => false]));
}

require_once 'includes/config.php';
require_once 'includes/jdf.php';

$user_id = (int)$_SESSION['user_id'];
$current_role = $_SESSION['role'] ?? '';

mysqli_query($conn, "UPDATE users SET last_activity = NOW() WHERE id = $user_id");

$users = [];
if ($current_role === 'observer') {
    $query = "
        SELECT u.id, u.username, u.branch_name, u.role, u.last_activity
        FROM users u
        INNER JOIN observer_assignments oa ON u.id = oa.branch_id
        WHERE oa.observer_id = $user_id
        ORDER BY u.last_activity DESC
    ";
    
    $result = mysqli_query($conn, $query);
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $last = $row['last_activity'];
            $diff = $last ? floor((time() - strtotime($last)) / 60) : 999;
            $online = ($diff < 5);
            $last_shamsi = $last ? jdate('Y/m/d H:i:s', strtotime($last)) : null;
            
            $users[] = [
                'id' => (int)$row['id'],
                'username' => $row['username'],
                'full_name' => $row['branch_name'],
                'branch_name' => $row['branch_name'],
                'role' => $row['role'],
                'is_online' => $online,
                'last_activity_shamsi' => $last_shamsi,
                'last_seen' => $last ? elapsed($last) : 'نامشخص'
            ];
        }
    }
}

echo json_encode(['ok' => true, 'users' => $users]);

function elapsed($datetime) {
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