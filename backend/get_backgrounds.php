<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

// Fetch all active background images
$sql = "SELECT image_id, image_name, image_url FROM background_images WHERE is_active = 1 ORDER BY image_name";
$result = $conn->query($sql);

$backgrounds = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Ensure uploaded images have proper path handling
        $image_url = $row['image_url'];
        
        // For uploaded images, make sure they're accessible
        if (strpos($image_url, 'uploads/backgrounds/') === 0) {
            // Check if file actually exists
            $file_path = '../' . $image_url;
            if (!file_exists($file_path)) {
                // Skip this background if file doesn't exist
                continue;
            }
            // Use image server for uploaded files
            $image_url = 'backend/serve_image.php?path=' . urlencode($image_url);
        }
        
        $backgrounds[] = [
            'id' => $row['image_id'],
            'name' => $row['image_name'],
            'url' => $image_url
        ];
    }
}

// If no backgrounds found, provide fallback
if (empty($backgrounds)) {
    $backgrounds = [
        [
            'id' => 0,
            'name' => 'Default',
            'url' => 'img/background.png'
        ]
    ];
}

echo json_encode(['backgrounds' => $backgrounds]);

$conn->close();
?>
