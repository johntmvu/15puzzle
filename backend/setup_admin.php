<?php
require_once 'db_connect.php';

echo "<h2>Setting up Admin User</h2>";

// Create an admin user if none exists
$admin_check = $conn->query("SELECT COUNT(*) as admin_count FROM users WHERE role = 'admin'");
$admin_count = $admin_check->fetch_assoc()['admin_count'];

if ($admin_count == 0) {
    // Create default admin user
    $admin_username = "admin";
    $admin_email = "admin@example.com";
    $admin_password = "admin123"; // Change this!
    $admin_password_hash = password_hash($admin_password, PASSWORD_DEFAULT);
    $registration_date = date('Y-m-d H:i:s');
    
    $stmt = $conn->prepare("INSERT INTO users (username, password_hash, email, role, registration_date) VALUES (?, ?, ?, 'admin', ?)");
    $stmt->bind_param("ssss", $admin_username, $admin_password_hash, $admin_email, $registration_date);
    
    if ($stmt->execute()) {
        echo "<p style='color: green;'>✅ Admin user created successfully!</p>";
        echo "<p><strong>Username:</strong> admin<br>";
        echo "<strong>Password:</strong> admin123<br>";
        echo "<strong>⚠️ Please change the password after first login!</strong></p>";
    } else {
        echo "<p style='color: red;'>❌ Failed to create admin user: " . $conn->error . "</p>";
    }
} else {
    echo "<p style='color: blue;'>ℹ️ Admin user already exists.</p>";
}

// Add some sample background images if none exist
$bg_check = $conn->query("SELECT COUNT(*) as bg_count FROM background_images");
$bg_count = $bg_check->fetch_assoc()['bg_count'];

if ($bg_count == 0) {
    echo "<h3>Adding Sample Background Images</h3>";
    
    $sample_backgrounds = [
        ['Default Puzzle', 'img/background.png'],
        ['Nature Scene', 'https://picsum.photos/400/400?random=1'],
        ['Abstract Pattern', 'https://picsum.photos/400/400?random=2'],
        ['City Skyline', 'https://picsum.photos/400/400?random=3']
    ];
    
    foreach ($sample_backgrounds as $bg) {
        $stmt = $conn->prepare("INSERT INTO background_images (image_name, image_url, is_active, uploaded_by_user_id) VALUES (?, ?, 1, 1)");
        $stmt->bind_param("ss", $bg[0], $bg[1]);
        
        if ($stmt->execute()) {
            echo "<p style='color: green;'>✅ Added background: " . htmlspecialchars($bg[0]) . "</p>";
        } else {
            echo "<p style='color: red;'>❌ Failed to add background: " . htmlspecialchars($bg[0]) . "</p>";
        }
    }
} else {
    echo "<p style='color: blue;'>ℹ️ Background images already exist.</p>";
}

echo "<h3>Setup Complete!</h3>";
echo "<p><a href='fifteen.html'>🎮 Go to Game</a> | <a href='admin_dashboard.php'>🛠️ Admin Dashboard</a></p>";

$conn->close();
?>
