<?php
session_start();
require_once 'auth.php';

header('Content-Type: application/json');

// Return user session information
$response = array(
    'logged_in' => isLoggedIn(),
    'user_id' => getCurrentUserId(),
    'username' => getCurrentUsername()
);

echo json_encode($response);
?>
