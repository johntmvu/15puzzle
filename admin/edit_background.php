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
    header('Location: ../fifteen.html');
    exit;
}

// Get background ID from URL
$background_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($background_id <= 0) {
    header('Location: admin_backgrounds.php');
    exit;
}

// Fetch background details
$stmt = $conn->prepare("SELECT * FROM background_images WHERE image_id = ?");
$stmt->bind_param("i", $background_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: admin_backgrounds.php');
    exit;
}

$background = $result->fetch_assoc();
$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $new_name = trim($_POST['image_name']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $new_url = $background['image_url']; // Keep current URL by default
    
    // Validate inputs
    if (empty($new_name)) {
        $error = "Image name is required.";
    } else {
        // Handle file upload if provided
        if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] == 0) {
            $upload_dir = '../uploads/backgrounds/';
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $max_size = 5 * 1024 * 1024; // 5MB
            
            $file = $_FILES['image_file'];
            $file_type = $file['type'];
            $file_size = $file['size'];
            
            // Validate file type
            if (!in_array($file_type, $allowed_types)) {
                $error = "Only JPEG, PNG, GIF, and WebP images are allowed.";
            } 
            // Validate file size
            elseif ($file_size > $max_size) {
                $error = "File size must be less than 5MB.";
            } 
            else {
                // Generate unique filename
                $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'bg_' . $background_id . '_' . time() . '.' . $file_extension;
                $upload_path = $upload_dir . $filename;
                
                // Move uploaded file
                if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                    // Delete old file if it exists in uploads directory
                    if (strpos($background['image_url'], 'uploads/backgrounds/') !== false) {
                        $old_file = '../' . $background['image_url'];
                        if (file_exists($old_file)) {
                            unlink($old_file);
                        }
                    }
                    
                    $new_url = 'uploads/backgrounds/' . $filename;
                } else {
                    $error = "Failed to upload file.";
                }
            }
        }
        
        // Update background details if no error
        if (empty($error)) {
            $update_stmt = $conn->prepare("UPDATE background_images SET image_name = ?, image_url = ?, is_active = ? WHERE image_id = ?");
            $update_stmt->bind_param("ssii", $new_name, $new_url, $is_active, $background_id);
            
            if ($update_stmt->execute()) {
                $message = "Background image updated successfully.";
                // Refresh background data
                $stmt = $conn->prepare("SELECT * FROM background_images WHERE image_id = ?");
                $stmt->bind_param("i", $background_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $background = $result->fetch_assoc();
            } else {
                $error = "Error updating background image.";
            }
            $update_stmt->close();
        }
    }
}

// Get usage count
$usage_stmt = $conn->prepare("SELECT COUNT(*) as usage_count FROM game_stats WHERE background_image_id = ?");
$usage_stmt->bind_param("i", $background_id);
$usage_stmt->execute();
$usage_result = $usage_stmt->get_result();
$usage = $usage_result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Background Image - Admin Dashboard</title>
    <link href="../assets/fifteen.css" type="text/css" rel="stylesheet">
    <style>
        .edit-container {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            box-sizing: border-box;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #4CAF50;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: auto;
        }
        
        .background-info-card {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        
        .background-preview {
            width: 200px;
            height: 200px;
            background-size: cover;
            background-position: center;
            background-color: #e9ecef;
            border: 1px solid #ddd;
            border-radius: 8px;
            margin: 15px 0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #666;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: #4CAF50;
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .url-hint {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
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
    </style>
</head>
<body>
    <div class="edit-container">
        <h1>Edit Background Image: <?php echo htmlspecialchars($background['image_name']); ?></h1>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="background-info-card">
            <h3>Background Information</h3>
            <p><strong>Background ID:</strong> <?php echo $background['image_id']; ?></p>
            <p><strong>Upload Date:</strong> <?php echo date('F j, Y g:i A', strtotime($background['upload_date'])); ?></p>
            <p><strong>Current Status:</strong> 
                <span class="status-badge status-<?php echo $background['is_active'] ? 'active' : 'inactive'; ?>">
                    <?php echo $background['is_active'] ? 'Active' : 'Inactive'; ?>
                </span>
            </p>
            <p><strong>Usage in Games:</strong> <?php echo $usage['usage_count']; ?> times</p>
            
            <h4>Current Preview</h4>
            <?php 
            // Handle image URL for preview - add ../ for local paths
            $preview_url = $background['image_url'];
            if (!filter_var($preview_url, FILTER_VALIDATE_URL)) {
                if (strpos($background['image_url'], 'uploads/backgrounds/') === 0) {
                    // Use image server for uploaded files
                    $preview_url = '../backend/serve_image.php?path=' . urlencode($background['image_url']);
                } else {
                    // Regular local files
                    $preview_url = '../' . $preview_url;
                }
            }
            ?>
            <div class="background-preview" style="background-image: url('<?php echo htmlspecialchars($preview_url); ?>');">
                <div style="background: rgba(255,255,255,0.8); padding: 5px 10px; border-radius: 3px;">
                    Preview
                </div>
            </div>
        </div>
        
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="image_name">Background Name</label>
                <input type="text" id="image_name" name="image_name" value="<?php echo htmlspecialchars($background['image_name']); ?>" required maxlength="100">
            </div>
            
            <div class="form-group">
                <label for="image_file">Upload New Image (Optional)</label>
                <input type="file" id="image_file" name="image_file" accept="image/*">
                <div class="url-hint">Leave empty to keep current image. Accepted formats: JPEG, PNG, GIF, WebP (max 5MB)</div>
            </div>
            
            <div class="form-group">
                <div class="checkbox-group">
                    <input type="checkbox" id="is_active" name="is_active" <?php echo $background['is_active'] ? 'checked' : ''; ?>>
                    <label for="is_active">Background is active (available for selection)</label>
                </div>
            </div>
            
            <div class="action-buttons">
                <button type="submit" class="btn btn-primary">Update Background</button>
                <a href="admin_backgrounds.php" class="btn btn-secondary">Back to Backgrounds</a>
            </div>
        </form>
    </div>
    
    <script>
        // Live preview update for file uploads
        document.getElementById('image_file').addEventListener('change', function() {
            const preview = document.querySelector('.background-preview');
            const file = this.files[0];
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.style.backgroundImage = `url('${e.target.result}')`;
                };
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>
</html>
