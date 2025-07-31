<?php
session_start();
require_once 'db_connect.php';
require_once 'auth.php';

header('Content-Type: application/json');

$response = array('is_admin' => false);

if (isLoggedIn()) {
    $user_id = getCurrentUserId();
    $stmt = $conn->prepare("SELECT role FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $response['is_admin'] = ($user['role'] === 'admin');
    }
}

echo json_encode($response);
?>
