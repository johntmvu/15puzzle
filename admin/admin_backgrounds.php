<?php
session_start();
require_once '../backend/db_connect.php';
require_once '../backend/auth.php';

// Check if user is admin
function isAdmin() {
    if (!isLoggedIn()) return false;
    
    global $conn;
    $user_id = getCurrentUserId();
    $stmt = $conn->prepare("SELECT role FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        return $user['role'] === 'admin';
    }
    return false;
}

// Require admin access
if (!isAdmin()) {
    header('Location: fifteen.html');
    exit;
}

$message = '';
$error = '';

// Handle background image actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_background':
                $image_name = trim($_POST['image_name']);
                $image_url = trim($_POST['image_url']);
                
                if (empty($image_name) || empty($image_url)) {
                    $error = 'Image name and URL are required.';
                } else {
                    $uploaded_by = getCurrentUserId();
                    $stmt = $conn->prepare("INSERT INTO background_images (image_name, image_url, uploaded_by_user_id) VALUES (?, ?, ?)");
                    $stmt->bind_param("ssi", $image_name, $image_url, $uploaded_by);
                    
                    if ($stmt->execute()) {
                        $message = "Background image added successfully.";
                    } else {
                        $error = "Failed to add background image.";
                    }
                }
                break;
                
            case 'toggle_active':
                $image_id = intval($_POST['image_id']);
                $is_active = intval($_POST['is_active']);
                $new_status = $is_active ? 0 : 1;
                
                $stmt = $conn->prepare("UPDATE background_images SET is_active = ? WHERE image_id = ?");
                $stmt->bind_param("ii", $new_status, $image_id);
                $stmt->execute();
                $message = $new_status ? "Background activated." : "Background deactivated.";
                break;
                
            case 'delete_background':
                $image_id = intval($_POST['image_id']);
                
                // Check if background is being used in any games
                $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM game_stats WHERE background_image_id = ?");
                $check_stmt->bind_param("i", $image_id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                $usage = $check_result->fetch_assoc();
                
                if ($usage['count'] > 0) {
                    $error = "Cannot delete background image that is used in game records.";
                } else {
                    $stmt = $conn->prepare("DELETE FROM background_images WHERE image_id = ?");
                    $stmt->bind_param("i", $image_id);
                    if ($stmt->execute()) {
                        $message = "Background image deleted successfully.";
                    } else {
                        $error = "Failed to delete background image.";
                    }
                }
                break;
        }
    }
}

// Fetch all background images
$backgrounds_sql = "SELECT bi.*, u.username as uploaded_by_username,
                    (SELECT COUNT(*) FROM game_stats WHERE background_image_id = bi.image_id) as usage_count
                    FROM background_images bi 
                    LEFT JOIN users u ON bi.uploaded_by_user_id = u.user_id
                    ORDER BY bi.image_id DESC";
$backgrounds_result = $conn->query($backgrounds_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Background Images Management - Admin</title>
    <link href="../assets/fifteen.css" type="text/css" rel="stylesheet">
    <style>
        .admin-container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .admin-header {
            background: #2c3e50;
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        .admin-nav {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        .nav-btn {
            padding: 10px 20px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background 0.3s;
        }
        .nav-btn:hover {
            background: #2980b9;
        }
        .nav-btn.active {
            background: #27ae60;
        }
        .add-form {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-group input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }
        .btn-primary {
            background: #3498db;
            color: white;
        }
        .btn-success {
            background: #27ae60;
            color: white;
        }
        .btn-danger {
            background: #e74c3c;
            color: white;
        }
        .btn-warning {
            background: #f39c12;
            color: white;
        }
        .backgrounds-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .background-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
            background: white;
        }
        .background-preview {
            width: 100%;
            height: 200px;
            background-size: cover;
            background-position: center;
            background-color: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #666;
        }
        .background-info {
            padding: 15px;
        }
        .background-title {
            font-weight: bold;
            margin-bottom: 10px;
        }
        .background-meta {
            font-size: 12px;
            color: #666;
            margin-bottom: 15px;
        }
        .background-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }
        .message {
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="admin-header">
            <h1>🖼️ Background Images Management</h1>
            <p>Welcome, <?php echo htmlspecialchars(getCurrentUsername()); ?>! | <a href="../fifteen.html" style="color: #ecf0f1;">Back to Game</a> | <a href="../backend/logout.php" style="color: #ecf0f1;">Logout</a></p>
        </div>

        <?php if ($message): ?>
            <div class="message"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="admin-nav">
            <a href="admin_dashboard.php" class="nav-btn">User Management</a>
            <a href="admin_backgrounds.php" class="nav-btn active">Background Images</a>
            <a href="admin_stats.php" class="nav-btn">Game Statistics</a>
        </div>

        <h2>Add New Background Image</h2>
        <div class="add-form">
            <form method="POST">
                <input type="hidden" name="action" value="add_background">
                <div class="form-group">
                    <label for="image_name">Image Name:</label>
                    <input type="text" id="image_name" name="image_name" required maxlength="100" 
                           placeholder="e.g., Nature Landscape">
                </div>
                <div class="form-group">
                    <label for="image_url">Image URL:</label>
                    <input type="url" id="image_url" name="image_url" required maxlength="255" 
                           placeholder="e.g., img/nature.jpg or https://example.com/image.jpg">
                </div>
                <button type="submit" class="btn btn-primary">Add Background Image</button>
            </form>
        </div>

        <h2>Manage Background Images</h2>
        <div class="backgrounds-grid">
            <?php if ($backgrounds_result->num_rows > 0): ?>
                <?php while ($bg = $backgrounds_result->fetch_assoc()): ?>
                    <div class="background-card">
                        <div class="background-preview" style="background-image: url('<?php echo htmlspecialchars($bg['image_url']); ?>');">
                            <div style="background: rgba(255,255,255,0.8); padding: 5px 10px; border-radius: 3px;">
                                Preview
                            </div>
                        </div>
                        <div class="background-info">
                            <div class="background-title">
                                <?php echo htmlspecialchars($bg['image_name']); ?>
                                <span class="status-badge status-<?php echo $bg['is_active'] ? 'active' : 'inactive'; ?>">
                                    <?php echo $bg['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </div>
                            <div class="background-meta">
                                ID: <?php echo $bg['image_id']; ?> | 
                                Uploaded by: <?php echo htmlspecialchars($bg['uploaded_by_username'] ?: 'Unknown'); ?> | 
                                Used in: <?php echo $bg['usage_count']; ?> games
                            </div>
                            <div class="background-actions">
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="toggle_active">
                                    <input type="hidden" name="image_id" value="<?php echo $bg['image_id']; ?>">
                                    <input type="hidden" name="is_active" value="<?php echo $bg['is_active']; ?>">
                                    <button type="submit" class="btn <?php echo $bg['is_active'] ? 'btn-warning' : 'btn-success'; ?>">
                                        <?php echo $bg['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                    </button>
                                </form>
                                
                                <?php if ($bg['usage_count'] == 0): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="delete_background">
                                        <input type="hidden" name="image_id" value="<?php echo $bg['image_id']; ?>">
                                        <button type="submit" class="btn btn-danger" 
                                                onclick="return confirm('Delete this background image?')">
                                            Delete
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <button class="btn btn-danger" disabled title="Cannot delete - used in games">
                                        Delete
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p>No background images found. Add some above!</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
