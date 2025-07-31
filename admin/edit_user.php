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

// Get user ID from URL
$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($user_id <= 0) {
    header('Location: admin_dashboard.php');
    exit;
}

// Fetch user details
$stmt = $conn->prepare("SELECT user_id, username, email, role, registration_date, last_login, 
                        COALESCE(is_active, TRUE) as is_active FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: admin_dashboard.php');
    exit;
}

$user = $result->fetch_assoc();
$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $new_username = trim($_POST['username']);
    $new_email = trim($_POST['email']);
    $new_password = trim($_POST['password']);
    $new_role = $_POST['role'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Validate inputs
    if (empty($new_username) || empty($new_email)) {
        $error = "Username and email are required.";
    } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } else {
        // Check if username already exists (excluding current user)
        $check_stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? AND user_id != ?");
        $check_stmt->bind_param("si", $new_username, $user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error = "Username already exists.";
        } else {
            // Update user details
            if (!empty($new_password)) {
                // Update with new password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, password_hash = ?, role = ?, is_active = ? WHERE user_id = ?");
                $stmt->bind_param("ssssii", $new_username, $new_email, $hashed_password, $new_role, $is_active, $user_id);
            } else {
                // Update without changing password
                $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, role = ?, is_active = ? WHERE user_id = ?");
                $stmt->bind_param("sssii", $new_username, $new_email, $new_role, $is_active, $user_id);
            }
            
            if ($stmt->execute()) {
                $message = "User details updated successfully.";
                // Refresh user data
                $stmt = $conn->prepare("SELECT user_id, username, email, role, registration_date, last_login, 
                                        COALESCE(is_active, TRUE) as is_active FROM users WHERE user_id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $user = $result->fetch_assoc();
            } else {
                $error = "Error updating user details.";
            }
        }
    }
}

// Fetch user game statistics
$stats_stmt = $conn->prepare("SELECT 
    COUNT(*) as total_games,
    COUNT(CASE WHEN win_status = 1 THEN 1 END) as total_wins,
    AVG(CASE WHEN win_status = 1 THEN time_taken_seconds END) as avg_win_time,
    AVG(CASE WHEN win_status = 1 THEN moves_count END) as avg_moves,
    MIN(CASE WHEN win_status = 1 THEN time_taken_seconds END) as best_time,
    MIN(CASE WHEN win_status = 1 THEN moves_count END) as best_moves
    FROM game_stats WHERE user_id = ?");
$stats_stmt->bind_param("i", $user_id);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats = $stats_result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User - Admin Dashboard</title>
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
        
        .user-info-card {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .stat-item {
            text-align: center;
            padding: 15px;
            background: white;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #4CAF50;
        }
        
        .stat-label {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
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
        
        .password-hint {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="edit-container">
        <h1>Edit User: <?php echo htmlspecialchars($user['username']); ?></h1>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="user-info-card">
            <h3>User Information</h3>
            <p><strong>User ID:</strong> <?php echo $user['user_id']; ?></p>
            <p><strong>Registration Date:</strong> <?php echo date('F j, Y', strtotime($user['registration_date'])); ?></p>
            <p><strong>Last Login:</strong> <?php echo $user['last_login'] ? date('F j, Y g:i A', strtotime($user['last_login'])) : 'Never'; ?></p>
            
            <h4>Game Statistics</h4>
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-value"><?php echo $stats['total_games']; ?></div>
                    <div class="stat-label">Total Games</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo $stats['total_wins']; ?></div>
                    <div class="stat-label">Total Wins</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo $stats['avg_win_time'] ? number_format($stats['avg_win_time'], 1) . 's' : 'N/A'; ?></div>
                    <div class="stat-label">Avg Win Time</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo $stats['avg_moves'] ? number_format($stats['avg_moves'], 1) : 'N/A'; ?></div>
                    <div class="stat-label">Avg Moves</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo $stats['best_time'] ? $stats['best_time'] . 's' : 'N/A'; ?></div>
                    <div class="stat-label">Best Time</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo $stats['best_moves'] ? $stats['best_moves'] : 'N/A'; ?></div>
                    <div class="stat-label">Best Moves</div>
                </div>
            </div>
        </div>
        
        <form method="POST">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="password">New Password</label>
                <input type="password" id="password" name="password" placeholder="Leave blank to keep current password">
                <div class="password-hint">Leave blank if you don't want to change the password</div>
            </div>
            
            <div class="form-group">
                <label for="role">Role</label>
                <select id="role" name="role">
                    <option value="player" <?php echo $user['role'] === 'player' ? 'selected' : ''; ?>>Player</option>
                    <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                </select>
            </div>
            
            <div class="form-group">
                <div class="checkbox-group">
                    <input type="checkbox" id="is_active" name="is_active" <?php echo $user['is_active'] ? 'checked' : ''; ?>>
                    <label for="is_active">Account is active</label>
                </div>
            </div>
            
            <div class="action-buttons">
                <button type="submit" class="btn btn-primary">Update User</button>
                <a href="admin_dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
            </div>
        </form>
    </div>
</body>
</html>
