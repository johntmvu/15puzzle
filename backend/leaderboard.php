<?php
session_start();
require_once 'db_connect.php';
require_once 'auth.php';

$sql = "SELECT gs.time_taken_seconds, gs.moves_count, u.username, gs.game_date
        FROM game_stats gs
        JOIN users u ON gs.user_id = u.user_id
        WHERE gs.win_status = 1
        ORDER BY gs.time_taken_seconds ASC, gs.moves_count ASC
        LIMIT 10";
$result = $conn->query($sql);

// Fetch total games stats
$stats_sql = "SELECT 
    COUNT(*) as total_games,
    COUNT(CASE WHEN win_status = 1 THEN 1 END) as total_wins,
    AVG(CASE WHEN win_status = 1 THEN time_taken_seconds END) as avg_win_time,
    AVG(CASE WHEN win_status = 1 THEN moves_count END) as avg_moves
    FROM game_stats 
    WHERE user_id IS NOT NULL";

$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();

echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Leaderboard</title><link rel="stylesheet" href="fifteen.css"><style>
.leaderboard-container { max-width: 800px; margin: 0 auto; padding: 20px; }
.leaderboard-table { width: 100%; border-collapse: collapse; font-size: 18px; background: white; border-radius: 10px; box-shadow: 0 0 20px rgba(0,0,0,0.3); overflow: hidden; margin: 20px 0; }
.leaderboard-table th { background: #49a09d; color: white; padding: 15px; text-align: center; font-weight: bold; }
.leaderboard-table td { padding: 12px 15px; text-align: center; border-bottom: 1px solid #eee; }
.leaderboard-table tr:nth-child(even) { background: #f9f9f9; }
.leaderboard-table tr:hover { background: #e8f4f8; }
.rank-1 { background: linear-gradient(45deg, #FFD700, #FFA500) !important; color: #333; font-weight: bold; }
.rank-2 { background: linear-gradient(45deg, #C0C0C0, #A0A0A0) !important; color: #333; font-weight: bold; }
.rank-3 { background: linear-gradient(45deg, #CD7F32, #8B4513) !important; color: white; font-weight: bold; }
.back-link { text-align: center; margin: 20px 0; }
.back-link a { background: #49a09d; color: white; padding: 12px 30px; text-decoration: none; border-radius: 25px; font-size: 18px; transition: all 0.3s ease; }
.back-link a:hover { background: #5f2c82; transform: translateY(-2px); }
</style></head><body>';
echo '<div class="leaderboard-container">';
echo '<h1>🏆 Global Leaderboard 🏆</h1>';

// Display global stats
echo '<div style="background: #f9f9f9; padding: 15px; border-radius: 5px; margin: 20px 0; display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; text-align: center;">';
echo '<div><div style="font-size: 24px; font-weight: bold; color: #49a09d;">' . number_format($stats['total_games']) . '</div><div style="color: #666; font-size: 14px;">Total Games</div></div>';
echo '<div><div style="font-size: 24px; font-weight: bold; color: #49a09d;">' . number_format($stats['total_wins']) . '</div><div style="color: #666; font-size: 14px;">Total Wins</div></div>';
echo '<div><div style="font-size: 24px; font-weight: bold; color: #49a09d;">' . ($stats['avg_win_time'] ? number_format($stats['avg_win_time'], 1) . 's' : 'N/A') . '</div><div style="color: #666; font-size: 14px;">Avg Win Time</div></div>';
echo '<div><div style="font-size: 24px; font-weight: bold; color: #49a09d;">' . ($stats['avg_moves'] ? number_format($stats['avg_moves'], 1) : 'N/A') . '</div><div style="color: #666; font-size: 14px;">Avg Moves</div></div>';
echo '</div>';

echo '<table class="leaderboard-table">';
echo '<tr style="background:#49a09d; color:white;"><th>Rank</th><th>Username</th><th>Time (s)</th><th>Moves</th><th>Date</th></tr>';
if ($result && $result->num_rows > 0) {
  $rank = 1;
  while($row = $result->fetch_assoc()) {
    $rankClass = '';
    if ($rank == 1) $rankClass = 'rank-1';
    else if ($rank == 2) $rankClass = 'rank-2';
    else if ($rank == 3) $rankClass = 'rank-3';
    
    $medal = '';
    if ($rank == 1) $medal = '🥇 ';
    else if ($rank == 2) $medal = '🥈 ';
    else if ($rank == 3) $medal = '🥉 ';
    
    echo '<tr class="' . $rankClass . '"><td>' . $medal . $rank . '</td><td>' . htmlspecialchars($row['username']) . '</td><td>' . $row['time_taken_seconds'] . '</td><td>' . $row['moves_count'] . '</td><td>' . date('M j, Y', strtotime($row['game_date'])) . '</td></tr>';
    $rank++;
  }
} else {
  echo '<tr><td colspan="5">No leaderboard data yet. Be the first to win!</td></tr>';
}
echo '</table>';
echo '<div class="back-link"><a href="../fifteen.html">🎮 Back to Game</a></div>';

// Add authentication links
if (isLoggedIn()) {
    echo '<div class="back-link"><a href="logout.php" style="background: #f44336;">Logout (' . htmlspecialchars(getCurrentUsername()) . ')</a></div>';
} else {
    echo '<div class="back-link"><a href="login.php">🔐 Login</a> <a href="register.php">📝 Register</a></div>';
}

echo '</div></body></html>';

$conn->close();
?>
