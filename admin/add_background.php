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

$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $image_name = trim($_POST['image_name']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Validate inputs
    if (empty($image_name)) {
        $error = "Image name is required.";
    } elseif (!isset($_FILES['image_file']) || $_FILES['image_file']['error'] != 0) {
        $error = "Please select an image file to upload.";
    } else {
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
            $filename = 'bg_' . time() . '_' . uniqid() . '.' . $file_extension;
            $upload_path = $upload_dir . $filename;
            
            // Move uploaded file
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                $image_url = 'uploads/backgrounds/' . $filename;
                $user_id = getCurrentUserId();
                
                // Insert new background
                $insert_stmt = $conn->prepare("INSERT INTO background_images (image_name, image_url, uploaded_by_user_id, is_active) VALUES (?, ?, ?, ?)");
                $insert_stmt->bind_param("ssii", $image_name, $image_url, $user_id, $is_active);
                
                if ($insert_stmt->execute()) {
                    $message = "Background image added successfully!";
                    // Clear form
                    $image_name = '';
                    $is_active = 1;
                } else {
                    $error = "Failed to save background information to database.";
                    // Delete uploaded file
                    unlink($upload_path);
                }
                $insert_stmt->close();
            } else {
                $error = "Failed to upload file.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Background Image - Admin Dashboard</title>
    <link href="../assets/fifteen.css" type="text/css" rel="stylesheet">
    <style>
        .add-container {
            max-width: 600px;
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
        
        .file-upload-area {
            border: 2px dashed #ddd;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            background: #f9f9f9;
            transition: all 0.3s ease;
            position: relative;
            cursor: pointer;
        }
        
        .file-upload-area input[type="file"] {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }
        
        .upload-text {
            pointer-events: none;
        }
        
        .file-upload-area:hover {
            border-color: #4CAF50;
            background: #f0f8f0;
        }
        
        .file-upload-area.dragover {
            border-color: #4CAF50;
            background: #e8f5e8;
        }
        
        .preview-container {
            margin-top: 15px;
        }
        
        .background-preview {
            width: 200px;
            height: 200px;
            background-size: cover;
            background-position: center;
            background-color: #e9ecef;
            border: 1px solid #ddd;
            border-radius: 8px;
            margin: 15px auto;
            display: none;
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
        
        .file-hint {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="add-container">
        <h1>Add New Background Image</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="image_name">Background Name</label>
                <input type="text" id="image_name" name="image_name" value="<?php echo isset($image_name) ? htmlspecialchars($image_name) : ''; ?>" required maxlength="100" placeholder="Enter a descriptive name for this background">
            </div>
            
            <div class="form-group">
                <label for="image_file">Upload Image</label>
                <div class="file-upload-area" id="file-upload-area">
                    <input type="file" id="image_file" name="image_file" accept="image/*" required>
                    <div class="upload-text">
                        <p>Click to select an image or drag and drop here</p>
                        <div class="file-hint">Accepted formats: JPEG, PNG, GIF, WebP (max 5MB)</div>
                    </div>
                </div>
                
                <div class="preview-container">
                    <div class="background-preview" id="preview"></div>
                </div>
            </div>
            
            <div class="form-group">
                <div class="checkbox-group">
                    <input type="checkbox" id="is_active" name="is_active" <?php echo (!isset($is_active) || $is_active) ? 'checked' : ''; ?>>
                    <label for="is_active">Make this background active immediately</label>
                </div>
            </div>
            
            <div class="action-buttons">
                <button type="submit" class="btn btn-primary">Add Background</button>
                <a href="admin_backgrounds.php" class="btn btn-secondary">Back to Backgrounds</a>
            </div>
        </form>
    </div>
    
    <script>
        const fileInput = document.getElementById('image_file');
        const fileUploadArea = document.getElementById('file-upload-area');
        const preview = document.getElementById('preview');
        
        // File input change handler
        fileInput.addEventListener('change', function(e) {
            handleFileSelect(this.files[0]);
        });
        
        // Click handler for upload area
        fileUploadArea.addEventListener('click', function() {
            fileInput.click();
        });
        
        // Drag and drop handlers
        fileUploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.stopPropagation();
            this.classList.add('dragover');
        });
        
        fileUploadArea.addEventListener('dragleave', function(e) {
            e.preventDefault();
            e.stopPropagation();
            this.classList.remove('dragover');
        });
        
        fileUploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            this.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                handleFileSelect(files[0]);
            }
        });
        
        function handleFileSelect(file) {
            if (file && file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.style.backgroundImage = `url('${e.target.result}')`;
                    preview.style.display = 'flex';
                    preview.innerHTML = '<div style="background: rgba(255,255,255,0.8); padding: 5px 10px; border-radius: 3px;">Preview</div>';
                };
                reader.readAsDataURL(file);
                
                // Update upload area text
                const uploadText = fileUploadArea.querySelector('.upload-text p');
                if (uploadText) {
                    uploadText.textContent = `Selected: ${file.name}`;
                }
            }
        }
    </script>
</body>
</html>
