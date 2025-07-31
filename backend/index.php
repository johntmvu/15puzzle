<?php
session_start();
require_once 'auth.php';

// If user is logged in, redirect to game, otherwise show login options
if (isLoggedIn()) {
    header('Location: ../fifteen.html');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fifteen Puzzle Game</title>
    <link href="../assets/fifteen.css" type="text/css" rel="stylesheet">
    <style>
        .welcome-container {
            max-width: 600px;
            margin: 50px auto;
            padding: 40px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            text-align: center;
        }
        .game-title {
            font-size: 48px;
            color: #4CAF50;
            margin-bottom: 20px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
        }
        .game-description {
            font-size: 18px;
            color: #666;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        .action-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
            flex-wrap: wrap;
            margin: 30px 0;
        }
        .action-btn {
            padding: 15px 30px;
            font-size: 18px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
            min-width: 150px;
        }
        .btn-primary {
            background: #4CAF50;
            color: white;
        }
        .btn-secondary {
            background: #2196F3;
            color: white;
        }
        .btn-guest {
            background: #FF9800;
            color: white;
        }
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        .features {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            margin: 30px 0;
        }
        .features h3 {
            color: #4CAF50;
            margin-bottom: 15px;
        }
        .features ul {
            text-align: left;
            color: #666;
        }
        .features li {
            margin: 8px 0;
        }
    </style>
</head>
<body>
    <div class="welcome-container">
        <h1 class="game-title">🧩 Fifteen Puzzle</h1>
        
        <p class="game-description">
            Challenge yourself with the classic sliding puzzle game! 
            Arrange the numbered tiles in order by sliding them into the empty space.
        </p>
        
        <div class="action-buttons">
            <a href="login.php" class="action-btn btn-primary">🔐 Login</a>
            <a href="register.php" class="action-btn btn-secondary">📝 Register</a>
            <a href="../fifteen.html" class="action-btn btn-guest">🎮 Play as Guest</a>
        </div>
        
        <div class="features">
            <h3>Game Features</h3>
            <ul>
                <li>✅ User accounts with secure authentication</li>
                <li>🏆 Global leaderboard for registered players</li>
                <li>⏱️ Time tracking and move counting</li>
                <li>🎵 Background music and sound effects</li>
                <li>📊 Game statistics and progress tracking</li>
                <li>👤 Guest play option (stats not saved)</li>
            </ul>
        </div>
        
        <div class="action-buttons">
            <a href="leaderboard.php" class="action-btn btn-secondary">🏆 View Leaderboard</a>
        </div>
    </div>
</body>
</html>
