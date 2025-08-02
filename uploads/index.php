<?php
// Simple file server for uploaded backgrounds
$file = isset($_GET['file']) ? $_GET['file'] : '';
$filePath = __DIR__ . '/backgrounds/' . basename($file);

// Security: only allow image files and prevent directory traversal
if (empty($file) || strpos($file, '..') !== false) {
    http_response_code(404);
    exit;
}

// Check if file exists and is an image
if (!file_exists($filePath)) {
    http_response_code(404);
    exit;
}

$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

if (!in_array($extension, $allowedExtensions)) {
    http_response_code(403);
    exit;
}

// Set proper content type
$mimeTypes = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg', 
    'png' => 'image/png',
    'gif' => 'image/gif',
    'webp' => 'image/webp'
];

header('Content-Type: ' . $mimeTypes[$extension]);
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: public, max-age=31536000'); // Cache for 1 year

// Output the file
readfile($filePath);
?>
