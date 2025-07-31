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

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'deactivate_user':
                $user_id = intval($_POST['user_id']);
                // Add is_active column if it doesn't exist
                $conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS is_active BOOLEAN DEFAULT TRUE");
                $stmt = $conn->prepare("UPDATE users SET is_active = FALSE WHERE user_id = ? AND role != 'admin'");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $message = "User deactivated successfully.";
                break;
                
            case 'activate_user':
                $user_id = intval($_POST['user_id']);
                $stmt = $conn->prepare("UPDATE users SET is_active = TRUE WHERE user_id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $message = "User activated successfully.";
                break;
                
            case 'make_admin':
                $user_id = intval($_POST['user_id']);
                $stmt = $conn->prepare("UPDATE users SET role = 'admin' WHERE user_id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $message = "User promoted to admin successfully.";
                break;
        }
    }
}

// Fetch all users
$users_sql = "SELECT user_id, username, email, role, registration_date, last_login, 
              COALESCE(is_active, TRUE) as is_active,
              (SELECT COUNT(*) FROM game_stats WHERE user_id = users.user_id) as total_games,
              (SELECT COUNT(*) FROM game_stats WHERE user_id = users.user_id AND win_status = 1) as total_wins
              FROM users ORDER BY registration_date DESC";
$users_result = $conn->query($users_sql);

// Fetch system statistics
$stats_sql = "SELECT 
    (SELECT COUNT(*) FROM users WHERE COALESCE(is_active, TRUE) = TRUE) as active_users,
    (SELECT COUNT(*) FROM users WHERE COALESCE(is_active, TRUE) = FALSE) as inactive_users,
    (SELECT COUNT(*) FROM game_stats) as total_games,
    (SELECT COUNT(*) FROM game_stats WHERE win_status = 1) as total_wins,
    (SELECT AVG(time_taken_seconds) FROM game_stats WHERE win_status = 1) as avg_win_time,
    (SELECT COUNT(*) FROM background_images WHERE is_active = 1) as active_backgrounds";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Fifteen Puzzle</title>
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
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            border-left: 4px solid #4CAF50;
        }
        .stat-value {
            font-size: 32px;
            font-weight: bold;
            color: #2c3e50;
        }
        .stat-label {
            color: #666;
            margin-top: 5px;
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
        .users-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .users-table th,
        .users-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .users-table th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        .users-table tr:hover {
            background-color: #f5f5f5;
        }
        .role-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .role-admin {
            background: #e74c3c;
            color: white;
        }
        .role-player {
            background: #27ae60;
            color: white;
        }
        .role-inactive {
            background: #95a5a6;
            color: white;
        }
        .action-btn {
            padding: 4px 8px;
            margin: 2px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
        }
        .btn-danger {
            background: #e74c3c;
            color: white;
        }
        .btn-success {
            background: #27ae60;
            color: white;
        }
        .btn-warning {
            background: #f39c12;
            color: white;
        }
        .message {
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="admin-header">
            <h1>🛠️ Admin Dashboard</h1>
            <p>Welcome, <?php echo htmlspecialchars(getCurrentUsername()); ?>! | <a href="../fifteen.html" style="color: #ecf0f1;">Back to Game</a> | <a href="../backend/logout.php" style="color: #ecf0f1;">Logout</a></p>
        </div>

        <?php if (isset($message)): ?>
            <div class="message"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <div class="admin-nav">
            <a href="admin_dashboard.php" class="nav-btn active">User Management</a>
            <a href="admin_backgrounds.php" class="nav-btn">Background Images</a>
            <a href="admin_stats.php" class="nav-btn">Game Statistics</a>
        </div>

        <h2>System Overview</h2>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['active_users']; ?></div>
                <div class="stat-label">Active Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['inactive_users']; ?></div>
                <div class="stat-label">Inactive Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($stats['total_games']); ?></div>
                <div class="stat-label">Total Games</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($stats['total_wins']); ?></div>
                <div class="stat-label">Total Wins</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['avg_win_time'] ? number_format($stats['avg_win_time'], 1) . 's' : 'N/A'; ?></div>
                <div class="stat-label">Avg Win Time</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['active_backgrounds']; ?></div>
                <div class="stat-label">Active Backgrounds</div>
            </div>
        </div>

        <h2>User Management</h2>
        <table class="users-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Games</th>
                    <th>Wins</th>
                    <th>Registered</th>
                    <th>Last Login</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($user = $users_result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $user['user_id']; ?></td>
                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td>
                            <span class="role-badge role-<?php echo $user['role']; ?> <?php echo !$user['is_active'] ? 'role-inactive' : ''; ?>">
                                <?php echo $user['role']; ?><?php echo !$user['is_active'] ? ' (Inactive)' : ''; ?>
                            </span>
                        </td>
                        <td><?php echo $user['total_games']; ?></td>
                        <td><?php echo $user['total_wins']; ?></td>
                        <td><?php echo date('M j, Y', strtotime($user['registration_date'])); ?></td>
                        <td><?php echo $user['last_login'] ? date('M j, Y', strtotime($user['last_login'])) : 'Never'; ?></td>
                        <td>
                            <?php if ($user['role'] !== 'admin' || $user['user_id'] != getCurrentUserId()): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                    
                                    <?php if ($user['is_active']): ?>
                                        <button type="submit" name="action" value="deactivate_user" class="action-btn btn-danger" 
                                                onclick="return confirm('Deactivate this user?')">Deactivate</button>
                                        <?php if ($user['role'] === 'player'): ?>
                                            <button type="submit" name="action" value="make_admin" class="action-btn btn-warning"
                                                    onclick="return confirm('Make this user an admin?')">Make Admin</button>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <button type="submit" name="action" value="activate_user" class="action-btn btn-success"
                                                onclick="return confirm('Activate this user?')">Activate</button>
                                    <?php endif; ?>
                                </form>
                            <?php else: ?>
                                <em>Current Admin</em>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
