<?php
// Simple image server for background images
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

$image_path = isset($_GET['path']) ? $_GET['path'] : '';

if (empty($image_path)) {
    http_response_code(400);
    exit('No image path specified');
}

// Security: prevent directory traversal
if (strpos($image_path, '..') !== false) {
    http_response_code(403);
    exit('Invalid path');
}

// Handle different image sources
if (strpos($image_path, 'uploads/backgrounds/') === 0) {
    // Uploaded background image
    $file_path = __DIR__ . '/../' . $image_path;
} elseif (strpos($image_path, 'img/') === 0) {
    // Original game images
    $file_path = __DIR__ . '/../' . $image_path;
} else {
    // External URL - redirect
    if (filter_var($image_path, FILTER_VALIDATE_URL)) {
        header('Location: ' . $image_path);
        exit;
    } else {
        http_response_code(404);
        exit('Image not found');
    }
}

// Check if file exists
if (!file_exists($file_path)) {
    http_response_code(404);
    exit('Image file not found');
}

// Get file info
$file_info = pathinfo($file_path);
$extension = strtolower($file_info['extension']);

// Set content type
$mime_types = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'webp' => 'image/webp'
];

if (!isset($mime_types[$extension])) {
    http_response_code(403);
    exit('Invalid image type');
}

// Set headers
header('Content-Type: ' . $mime_types[$extension]);
header('Content-Length: ' . filesize($file_path));
header('Cache-Control: public, max-age=3600'); // Cache for 1 hour

// Output file
readfile($file_path);
?>
