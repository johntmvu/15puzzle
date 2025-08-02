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

// Fetch comprehensive game statistics
$overall_stats_sql = "SELECT 
    COUNT(*) as total_games,
    COUNT(CASE WHEN win_status = 1 THEN 1 END) as total_wins,
    COUNT(CASE WHEN win_status = 0 THEN 1 END) as total_losses,
    ROUND(AVG(time_taken_seconds), 2) as avg_time,
    ROUND(AVG(moves_count), 2) as avg_moves,
    MIN(time_taken_seconds) as fastest_time,
    MAX(time_taken_seconds) as slowest_time,
    MIN(moves_count) as fewest_moves,
    MAX(moves_count) as most_moves
    FROM game_stats";
$overall_stats = $conn->query($overall_stats_sql)->fetch_assoc();

// Games per day (last 30 days)
$daily_stats_sql = "SELECT 
    DATE(game_date) as game_day,
    COUNT(*) as games_count,
    COUNT(CASE WHEN win_status = 1 THEN 1 END) as wins_count
    FROM game_stats 
    WHERE game_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY DATE(game_date)
    ORDER BY game_day DESC
    LIMIT 30";
$daily_stats = $conn->query($daily_stats_sql);

// Top performers
$top_players_sql = "SELECT 
    u.username,
    COUNT(*) as total_games,
    COUNT(CASE WHEN gs.win_status = 1 THEN 1 END) as wins,
    ROUND(AVG(CASE WHEN gs.win_status = 1 THEN gs.time_taken_seconds END), 2) as avg_win_time,
    ROUND(AVG(CASE WHEN gs.win_status = 1 THEN gs.moves_count END), 2) as avg_win_moves,
    MIN(CASE WHEN gs.win_status = 1 THEN gs.time_taken_seconds END) as best_time,
    MIN(CASE WHEN gs.win_status = 1 THEN gs.moves_count END) as fewest_moves
    FROM users u
    JOIN game_stats gs ON u.user_id = gs.user_id
    WHERE u.role != 'inactive'
    GROUP BY u.user_id, u.username
    HAVING wins > 0
    ORDER BY avg_win_time ASC, fewest_moves ASC
    LIMIT 20";
$top_players = $conn->query($top_players_sql);

// Recent activity
$recent_activity_sql = "SELECT 
    u.username,
    gs.win_status,
    gs.time_taken_seconds,
    gs.moves_count,
    gs.game_date
    FROM game_stats gs
    JOIN users u ON gs.user_id = u.user_id
    ORDER BY gs.game_date DESC
    LIMIT 50";
$recent_activity = $conn->query($recent_activity_sql);

// Game trends by hour
$hourly_trends_sql = "SELECT 
    HOUR(game_date) as game_hour,
    COUNT(*) as games_count,
    ROUND(AVG(CASE WHEN win_status = 1 THEN time_taken_seconds END), 2) as avg_win_time
    FROM game_stats 
    WHERE game_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY HOUR(game_date)
    ORDER BY game_hour";
$hourly_trends = $conn->query($hourly_trends_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Game Statistics - Admin</title>
    <link href="../assets/fifteen.css" type="text/css" rel="stylesheet">
    <style>
        .admin-container {
            max-width: 1400px;
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
            font-size: 28px;
            font-weight: bold;
            color: #2c3e50;
        }
        .stat-label {
            color: #666;
            margin-top: 5px;
            font-size: 14px;
        }
        .section {
            margin-bottom: 40px;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background: white;
        }
        .data-table th,
        .data-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .data-table th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        .data-table tr:hover {
            background-color: #f5f5f5;
        }
        .win-status {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-win {
            background: #d4edda;
            color: #155724;
        }
        .status-loss {
            background: #f8d7da;
            color: #721c24;
        }
        .chart-container {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .chart-bar {
            display: flex;
            align-items: center;
            margin: 5px 0;
        }
        .chart-label {
            width: 100px;
            font-size: 12px;
        }
        .chart-value {
            background: #3498db;
            color: white;
            padding: 4px 8px;
            margin-left: 10px;
            border-radius: 3px;
            font-size: 12px;
        }
        .highlight-number {
            color: #e74c3c;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="admin-header">
            <h1>📊 Game Statistics Overview</h1>
            <p>Welcome, <?php echo htmlspecialchars(getCurrentUsername()); ?>! | <a href="../fifteen.html" style="color: #ecf0f1;">Back to Game</a> | <a href="../backend/logout.php" style="color: #ecf0f1;">Logout</a></p>
        </div>

        <div class="admin-nav">
            <a href="admin_dashboard.php" class="nav-btn">User Management</a>
            <a href="admin_backgrounds.php" class="nav-btn">Background Images</a>
            <a href="admin_stats.php" class="nav-btn active">Game Statistics</a>
        </div>

        <div class="section">
            <h2>Overall Game Performance</h2>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($overall_stats['total_games']); ?></div>
                    <div class="stat-label">Total Games Played</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($overall_stats['total_wins']); ?></div>
                    <div class="stat-label">Total Wins</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $overall_stats['total_games'] > 0 ? round(($overall_stats['total_wins'] / $overall_stats['total_games']) * 100, 1) . '%' : '0%'; ?></div>
                    <div class="stat-label">Win Rate</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $overall_stats['avg_time'] ?: 'N/A'; ?>s</div>
                    <div class="stat-label">Average Game Time</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $overall_stats['avg_moves'] ?: 'N/A'; ?></div>
                    <div class="stat-label">Average Moves</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $overall_stats['fastest_time'] ?: 'N/A'; ?>s</div>
                    <div class="stat-label">Fastest Win Time</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $overall_stats['fewest_moves'] ?: 'N/A'; ?></div>
                    <div class="stat-label">Fewest Moves to Win</div>
                </div>
            </div>
        </div>

        <div class="section">
            <h2>Activity Trends (Last 30 Days)</h2>
            <div class="chart-container">
                <?php if ($daily_stats->num_rows > 0): ?>
                    <?php 
                    $max_games = 0;
                    $daily_data = [];
                    while ($day = $daily_stats->fetch_assoc()) {
                        $daily_data[] = $day;
                        $max_games = max($max_games, $day['games_count']);
                    }
                    ?>
                    
                    <?php foreach (array_reverse($daily_data) as $day): ?>
                        <div class="chart-bar">
                            <div class="chart-label"><?php echo date('M j', strtotime($day['game_day'])); ?></div>
                            <div style="width: <?php echo $max_games > 0 ? ($day['games_count'] / $max_games) * 300 : 0; ?>px; background: #3498db; height: 20px; border-radius: 3px;"></div>
                            <div class="chart-value">
                                <?php echo $day['games_count']; ?> games (<?php echo $day['wins_count']; ?> wins)
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No game data for the last 30 days.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="section">
            <h2>Top Players</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>Username</th>
                        <th>Total Games</th>
                        <th>Wins</th>
                        <th>Win Rate</th>
                        <th>Avg Win Time</th>
                        <th>Best Time</th>
                        <th>Fewest Moves</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $rank = 1;
                    if ($top_players->num_rows > 0):
                        while ($player = $top_players->fetch_assoc()): 
                            $win_rate = $player['total_games'] > 0 ? round(($player['wins'] / $player['total_games']) * 100, 1) : 0;
                    ?>
                        <tr>
                            <td><strong><?php echo $rank; ?></strong></td>
                            <td><?php echo htmlspecialchars($player['username']); ?></td>
                            <td><?php echo $player['total_games']; ?></td>
                            <td><?php echo $player['wins']; ?></td>
                            <td><?php echo $win_rate; ?>%</td>
                            <td><?php echo $player['avg_win_time'] ?: 'N/A'; ?>s</td>
                            <td><span class="highlight-number"><?php echo $player['best_time'] ?: 'N/A'; ?>s</span></td>
                            <td><span class="highlight-number"><?php echo $player['fewest_moves'] ?: 'N/A'; ?></span></td>
                        </tr>
                    <?php 
                        $rank++;
                        endwhile; 
                    else: 
                    ?>
                        <tr><td colspan="8">No player data available.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="section">
            <h2>Recent Game Activity</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Player</th>
                        <th>Result</th>
                        <th>Time</th>
                        <th>Moves</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($recent_activity->num_rows > 0): ?>
                        <?php while ($game = $recent_activity->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($game['username']); ?></td>
                                <td>
                                    <span class="win-status status-<?php echo $game['win_status'] ? 'win' : 'loss'; ?>">
                                        <?php echo $game['win_status'] ? 'WIN' : 'LOSS'; ?>
                                    </span>
                                </td>
                                <td><?php echo $game['time_taken_seconds']; ?>s</td>
                                <td><?php echo $game['moves_count']; ?></td>
                                <td><?php echo date('M j, Y H:i', strtotime($game['game_date'])); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5">No recent activity.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="section">
            <h2>Hourly Activity Pattern (Last 7 Days)</h2>
            <div class="chart-container">
                <?php if ($hourly_trends->num_rows > 0): ?>
                    <?php 
                    $max_hourly = 0;
                    $hourly_data = [];
                    while ($hour = $hourly_trends->fetch_assoc()) {
                        $hourly_data[$hour['game_hour']] = $hour;
                        $max_hourly = max($max_hourly, $hour['games_count']);
                    }
                    
                    // Fill in missing hours
                    for ($h = 0; $h < 24; $h++) {
                        if (!isset($hourly_data[$h])) {
                            $hourly_data[$h] = ['game_hour' => $h, 'games_count' => 0, 'avg_win_time' => null];
                        }
                    }
                    ksort($hourly_data);
                    ?>
                    
                    <?php foreach ($hourly_data as $hour_data): ?>
                        <div class="chart-bar">
                            <div class="chart-label"><?php echo sprintf('%02d:00', $hour_data['game_hour']); ?></div>
                            <div style="width: <?php echo $max_hourly > 0 ? ($hour_data['games_count'] / $max_hourly) * 200 : 5; ?>px; background: #27ae60; height: 15px; border-radius: 3px;"></div>
                            <div class="chart-value">
                                <?php echo $hour_data['games_count']; ?> games
                                <?php if ($hour_data['avg_win_time']): ?>
                                    (<?php echo $hour_data['avg_win_time']; ?>s avg)
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No hourly data for the last 7 days.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
