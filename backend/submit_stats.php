<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

$data = json_decode(file_get_contents("php://input"), true);

// Handle both logged in users and guests
$user_id = isset($data['user_id']) && $data['user_id'] ? intval($data['user_id']) : null;
$puzzle_size = $conn->real_escape_string($data['puzzle_size']);
$time_taken_seconds = intval($data['time_taken_seconds']);
$moves_count = intval($data['moves_count']);
$background_image_id = isset($data['background_image_id']) ? intval($data['background_image_id']) : null;
$win_status = $data['win_status'] ? 1 : 0;
$game_date = date('Y-m-d H:i:s');

// Only save stats for registered users (not guests)
if ($user_id) {
    $sql = "INSERT INTO game_stats (user_id, puzzle_size, time_taken_seconds, moves_count, background_image_id, win_status, game_date)
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isiiiis", $user_id, $puzzle_size, $time_taken_seconds, $moves_count, $background_image_id, $win_status, $game_date);
    
    if ($stmt->execute()) {
        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["success" => false, "error" => $conn->error]);
    }
    $stmt->close();
} else {
    // For guests, just return success without saving
    echo json_encode(["success" => true, "message" => "Guest game completed"]);
}
?>