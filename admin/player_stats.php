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

// Fetch comprehensive game statistics
$stats_stmt = $conn->prepare("SELECT 
    COUNT(*) as total_games,
    COUNT(CASE WHEN win_status = 1 THEN 1 END) as total_wins,
    COUNT(CASE WHEN win_status = 0 THEN 1 END) as total_losses,
    AVG(CASE WHEN win_status = 1 THEN time_taken_seconds END) as avg_win_time,
    AVG(CASE WHEN win_status = 1 THEN moves_count END) as avg_win_moves,
    AVG(CASE WHEN win_status = 0 THEN time_taken_seconds END) as avg_loss_time,
    AVG(CASE WHEN win_status = 0 THEN moves_count END) as avg_loss_moves,
    MIN(CASE WHEN win_status = 1 THEN time_taken_seconds END) as best_time,
    MIN(CASE WHEN win_status = 1 THEN moves_count END) as best_moves,
    MAX(CASE WHEN win_status = 1 THEN time_taken_seconds END) as worst_win_time,
    MAX(CASE WHEN win_status = 1 THEN moves_count END) as worst_win_moves,
    AVG(time_taken_seconds) as avg_total_time,
    AVG(moves_count) as avg_total_moves
    FROM game_stats WHERE user_id = ?");
$stats_stmt->bind_param("i", $user_id);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats = $stats_result->fetch_assoc();

// Calculate win rate
$win_rate = $stats['total_games'] > 0 ? ($stats['total_wins'] / $stats['total_games']) * 100 : 0;

// Fetch recent games (last 10)
$recent_games_stmt = $conn->prepare("SELECT game_date, time_taken_seconds, moves_count, win_status 
                                     FROM game_stats WHERE user_id = ? 
                                     ORDER BY game_date DESC LIMIT 10");
$recent_games_stmt->bind_param("i", $user_id);
$recent_games_stmt->execute();
$recent_games_result = $recent_games_stmt->get_result();

// Fetch best performances (top 5 wins by time)
$best_games_stmt = $conn->prepare("SELECT game_date, time_taken_seconds, moves_count 
                                   FROM game_stats WHERE user_id = ? AND win_status = 1 
                                   ORDER BY time_taken_seconds ASC, moves_count ASC LIMIT 5");
$best_games_stmt->bind_param("i", $user_id);
$best_games_stmt->execute();
$best_games_result = $best_games_stmt->get_result();

// Fetch monthly statistics (last 6 months)
$monthly_stats_stmt = $conn->prepare("SELECT 
    DATE_FORMAT(game_date, '%Y-%m') as month,
    COUNT(*) as games_played,
    COUNT(CASE WHEN win_status = 1 THEN 1 END) as wins,
    AVG(CASE WHEN win_status = 1 THEN time_taken_seconds END) as avg_time,
    AVG(CASE WHEN win_status = 1 THEN moves_count END) as avg_moves
    FROM game_stats 
    WHERE user_id = ? AND game_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(game_date, '%Y-%m')
    ORDER BY month DESC");
$monthly_stats_stmt->bind_param("i", $user_id);
$monthly_stats_stmt->execute();
$monthly_stats_result = $monthly_stats_stmt->get_result();

// Fetch player ranking
$ranking_stmt = $conn->prepare("SELECT COUNT(*) + 1 as player_rank
    FROM (
        SELECT user_id, MIN(time_taken_seconds) as best_time
        FROM game_stats 
        WHERE win_status = 1 
        GROUP BY user_id
    ) as player_best
    WHERE best_time < COALESCE((
        SELECT MIN(time_taken_seconds) 
        FROM game_stats 
        WHERE user_id = ? AND win_status = 1
    ), 999999)");
$ranking_stmt->bind_param("i", $user_id);
$ranking_stmt->execute();
$ranking_result = $ranking_stmt->get_result();
$ranking = $ranking_result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Player Statistics - <?php echo htmlspecialchars($user['username']); ?></title>
    <link href="../assets/fifteen.css" type="text/css" rel="stylesheet">
    <style>
        .stats-container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
        }
        
        .stats-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            text-align: center;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        
        .stats-header h1 {
            margin: 0;
            font-size: 2.5em;
        }
        
        .stats-header .subtitle {
            margin: 10px 0 0 0;
            opacity: 0.9;
            font-size: 1.2em;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-value {
            font-size: 2.5em;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .stat-label {
            color: #666;
            font-size: 1.1em;
            font-weight: 500;
        }
        
        .stat-card.primary .stat-value { color: #4CAF50; }
        .stat-card.success .stat-value { color: #27ae60; }
        .stat-card.info .stat-value { color: #3498db; }
        .stat-card.warning .stat-value { color: #f39c12; }
        .stat-card.danger .stat-value { color: #e74c3c; }
        
        .section {
            background: white;
            margin-bottom: 30px;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .section-header {
            background: #f8f9fa;
            padding: 20px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .section-header h2 {
            margin: 0;
            color: #333;
            font-size: 1.5em;
        }
        
        .section-content {
            padding: 20px;
        }
        
        .games-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .games-table th,
        .games-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }
        
        .games-table th {
            background: #f8f9fa;
            font-weight: bold;
            color: #495057;
        }
        
        .games-table tr:hover {
            background: #f8f9fa;
        }
        
        .win-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .win-badge.win {
            background: #d4edda;
            color: #155724;
        }
        
        .win-badge.loss {
            background: #f8d7da;
            color: #721c24;
        }
        
        .progress-bar {
            background: #e9ecef;
            border-radius: 10px;
            height: 20px;
            overflow: hidden;
            margin: 10px 0;
        }
        
        .progress-fill {
            background: linear-gradient(90deg, #4CAF50, #45a049);
            height: 100%;
            transition: width 0.5s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 0.8em;
        }
        
        .back-btn {
            display: inline-block;
            padding: 12px 24px;
            background: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }
        
        .back-btn:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }
        
        .no-data {
            text-align: center;
            color: #666;
            font-style: italic;
            padding: 40px;
        }
        
        .monthly-chart {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
        }
        
        .month-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }
        
        .month-name {
            font-weight: bold;
            color: #495057;
            margin-bottom: 10px;
        }
        
        .month-stats {
            font-size: 0.9em;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="stats-container">
        <a href="admin_dashboard.php" class="back-btn">← Back to Dashboard</a>
        
        <div class="stats-header">
            <h1><?php echo htmlspecialchars($user['username']); ?></h1>
            <p class="subtitle">Detailed Game Statistics & Performance Analysis</p>
        </div>
        
        <!-- Overview Statistics -->
        <div class="stats-grid">
            <div class="stat-card primary">
                <div class="stat-value"><?php echo $stats['total_games']; ?></div>
                <div class="stat-label">Total Games</div>
            </div>
            <div class="stat-card success">
                <div class="stat-value"><?php echo $stats['total_wins']; ?></div>
                <div class="stat-label">Total Wins</div>
            </div>
            <div class="stat-card danger">
                <div class="stat-value"><?php echo $stats['total_losses']; ?></div>
                <div class="stat-label">Total Losses</div>
            </div>
            <div class="stat-card info">
                <div class="stat-value"><?php echo number_format($win_rate, 1); ?>%</div>
                <div class="stat-label">Win Rate</div>
            </div>
            <div class="stat-card warning">
                <div class="stat-value"><?php echo $stats['best_time'] ? $stats['best_time'] . 's' : 'N/A'; ?></div>
                <div class="stat-label">Best Time</div>
            </div>
            <div class="stat-card info">
                <div class="stat-value"><?php echo $stats['best_moves'] ? $stats['best_moves'] : 'N/A'; ?></div>
                <div class="stat-label">Best Moves</div>
            </div>
        </div>
        
        <!-- Win Rate Progress Bar -->
        <div class="section">
            <div class="section-header">
                <h2>Performance Overview</h2>
            </div>
            <div class="section-content">
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo $win_rate; ?>%">
                        <?php echo number_format($win_rate, 1); ?>% Win Rate
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-top: 20px;">
                    <div>
                        <strong>Global Ranking:</strong> #<?php echo $ranking['player_rank']; ?>
                    </div>
                    <div>
                        <strong>Average Win Time:</strong> <?php echo $stats['avg_win_time'] ? number_format($stats['avg_win_time'], 1) . 's' : 'N/A'; ?>
                    </div>
                    <div>
                        <strong>Average Win Moves:</strong> <?php echo $stats['avg_win_moves'] ? number_format($stats['avg_win_moves'], 1) : 'N/A'; ?>
                    </div>
                    <div>
                        <strong>Member Since:</strong> <?php echo date('F j, Y', strtotime($user['registration_date'])); ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Games -->
        <div class="section">
            <div class="section-header">
                <h2>Recent Games (Last 10)</h2>
            </div>
            <div class="section-content">
                <?php if ($recent_games_result->num_rows > 0): ?>
                    <table class="games-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Result</th>
                                <th>Time</th>
                                <th>Moves</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($game = $recent_games_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo date('M j, Y g:i A', strtotime($game['game_date'])); ?></td>
                                    <td>
                                        <span class="win-badge <?php echo $game['win_status'] ? 'win' : 'loss'; ?>">
                                            <?php echo $game['win_status'] ? 'Win' : 'Loss'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo $game['time_taken_seconds']; ?>s</td>
                                    <td><?php echo $game['moves_count']; ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="no-data">No games played yet</div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Best Performances -->
        <div class="section">
            <div class="section-header">
                <h2>Best Performances (Top 5 Wins)</h2>
            </div>
            <div class="section-content">
                <?php if ($best_games_result->num_rows > 0): ?>
                    <table class="games-table">
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Moves</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $rank = 1;
                            while ($game = $best_games_result->fetch_assoc()): 
                            ?>
                                <tr>
                                    <td>
                                        <?php 
                                        if ($rank == 1) echo '🥇';
                                        elseif ($rank == 2) echo '🥈';
                                        elseif ($rank == 3) echo '🥉';
                                        else echo $rank;
                                        ?>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($game['game_date'])); ?></td>
                                    <td><?php echo $game['time_taken_seconds']; ?>s</td>
                                    <td><?php echo $game['moves_count']; ?></td>
                                </tr>
                            <?php 
                            $rank++;
                            endwhile; 
                            ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="no-data">No wins recorded yet</div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Monthly Statistics -->
        <div class="section">
            <div class="section-header">
                <h2>Monthly Performance (Last 6 Months)</h2>
            </div>
            <div class="section-content">
                <?php if ($monthly_stats_result->num_rows > 0): ?>
                    <div class="monthly-chart">
                        <?php while ($month = $monthly_stats_result->fetch_assoc()): ?>
                            <div class="month-card">
                                <div class="month-name"><?php echo date('M Y', strtotime($month['month'] . '-01')); ?></div>
                                <div class="month-stats">
                                    <div><strong><?php echo $month['games_played']; ?></strong> games</div>
                                    <div><strong><?php echo $month['wins']; ?></strong> wins</div>
                                    <?php if ($month['avg_time']): ?>
                                        <div>Avg: <?php echo number_format($month['avg_time'], 1); ?>s</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="no-data">No games in the last 6 months</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
