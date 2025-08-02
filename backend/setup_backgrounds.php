<?php
require_once 'db_connect.php';

// Create background_images table if it doesn't exist
$create_table = "CREATE TABLE IF NOT EXISTS background_images (
    image_id INT PRIMARY KEY AUTO_INCREMENT,
    image_name VARCHAR(100) NOT NULL,
    image_url VARCHAR(255) NOT NULL,
    uploaded_by_user_id INT,
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (uploaded_by_user_id) REFERENCES users(user_id)
)";

if ($conn->query($create_table)) {
    echo "Background images table created/exists.\n";
} else {
    echo "Error creating table: " . $conn->error . "\n";
}

// Check if we already have backgrounds
$check_sql = "SELECT COUNT(*) as count FROM background_images";
$result = $conn->query($check_sql);
$count = $result->fetch_assoc()['count'];

if ($count == 0) {
    // Insert default background images
    $default_backgrounds = [
        ['Mario Bros (Original)', 'img/background.png'],
        ['Sunset Landscape', 'https://images.unsplash.com/photo-1506905925346-21bda4d32df4?w=800&h=800&fit=crop&crop=center'],
        ['Ocean Waves', 'https://images.unsplash.com/photo-1505142468610-359e7d316be0?w=800&h=800&fit=crop&crop=center'],
        ['Mountain View', 'https://images.unsplash.com/photo-1464822759844-d150baec3374?w=800&h=800&fit=crop&crop=center'],
        ['City Skyline', 'https://images.unsplash.com/photo-1449824913935-59a10b8d2000?w=800&h=800&fit=crop&crop=center'],
        ['Abstract Colors', 'https://images.unsplash.com/photo-1557672172-298e090bd0f1?w=800&h=800&fit=crop&crop=center'],
        ['Forest Path', 'https://images.unsplash.com/photo-1441974231531-c6227db76b6e?w=800&h=800&fit=crop&crop=center'],
        ['Space Nebula', 'https://images.unsplash.com/photo-1446776877081-d282a0f896e2?w=800&h=800&fit=crop&crop=center']
    ];

    $stmt = $conn->prepare("INSERT INTO background_images (image_name, image_url, uploaded_by_user_id) VALUES (?, ?, NULL)");
    
    foreach ($default_backgrounds as $bg) {
        $stmt->bind_param("ss", $bg[0], $bg[1]);
        if ($stmt->execute()) {
            echo "Added background: " . $bg[0] . "\n";
        } else {
            echo "Error adding " . $bg[0] . ": " . $stmt->error . "\n";
        }
    }
    $stmt->close();
} else {
    echo "Background images already exist in database.\n";
}

$conn->close();
?>
