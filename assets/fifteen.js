window.onload = function () {
  const puzzleArea = document.getElementById("puzzlearea")   // The main area that holds all puzzle tiles.
  const tiles = []   // Array to store tile objects.
  let emptyX = 3, emptyY = 3   // Coordinates of the empty space.
  let timeLeft = 120, timerInterval   // Countdown timer values.
  let gameEnded = false, gameStarted = false   // Game state flags.
  let moveCount = 0; // Track number of moves.
  let currentUserId = null; // Store current user ID

  // DOM elements.
  const popup = document.getElementById("popup")                    // Win/loss message popup.
  const popupMessage = document.getElementById("popup-message")     // Message content inside the popup.
  const popupPlayAgain = document.getElementById("popup-play-again") // Button to reset the game after game ends.
  const mainMenuBtn = document.getElementById("main-menu-btn")
  const timerEl = document.getElementById("timer")                  // Timer display element.
  const moveCountEl = document.getElementById("move-count")         // Move count display element.
  const shuffleBtn = document.getElementById("shufflebutton")       // Shuffle/start/reset game button.
  const leaderboardBtn = document.getElementById("leaderboard-btn") // Leaderboard button.
  const adminBtn = document.getElementById("admin-btn")             // Admin button.
  const backgroundSelect = document.getElementById("background-select") // Background selector

  // Background functionality
  let availableBackgrounds = [];
  let currentBackgroundId = null;

  // User authentication elements
  const loggedInSection = document.getElementById("logged-in-section")
  const guestSection = document.getElementById("guest-section")
  const usernameDisplay = document.getElementById("username-display")
  const logoutBtn = document.getElementById("logout-btn")

  const bgMusic = document.getElementById("bg-music")   // Background music audio element.
  const bgToggle = document.getElementById("bg-toggle")   // Button to toggle background music.

  // Sound effects.
  const winSound = document.getElementById("win-sound")      // Sound to play when the user wins.
  const loseSound = document.getElementById("lose-sound")    // Sound to play when the user loses.
  const moveSound = document.getElementById("move-sound")    // Sound to play when a tile is moved.

  // Check user authentication status on page load
  checkUserAuth();
  
  // Load available backgrounds and set random default
  loadBackgrounds();

  // Function to check user authentication status
  function checkUserAuth() {
    fetch('backend/get_user_info.php')
      .then(response => response.json())
      .then(data => {
        if (data.logged_in) {
          currentUserId = data.user_id;
          usernameDisplay.textContent = data.username;
          loggedInSection.style.display = 'block';
          guestSection.style.display = 'none';
          
          // Check if user is admin
          checkAdminStatus();
        } else {
          currentUserId = null;
          loggedInSection.style.display = 'none';
          guestSection.style.display = 'block';
          if (adminBtn) adminBtn.style.display = 'none';
        }
      })
      .catch(error => {
        console.error('Error checking auth status:', error);
        currentUserId = null;
        loggedInSection.style.display = 'none';
        guestSection.style.display = 'block';
        if (adminBtn) adminBtn.style.display = 'none';
      });
  }

  // Function to check if user is admin
  function checkAdminStatus() {
    fetch('backend/check_admin.php')
      .then(response => response.json())
      .then(data => {
        if (data.is_admin && adminBtn) {
          adminBtn.style.display = 'inline-block';
        } else if (adminBtn) {
          adminBtn.style.display = 'none';
        }
      })
      .catch(error => {
        console.error('Error checking admin status:', error);
        if (adminBtn) adminBtn.style.display = 'none';
      });
  }

  mainMenuBtn.addEventListener("click", function () {
    window.location.href = "index.php";
  });

  // Logout functionality
  if (logoutBtn) {
    logoutBtn.addEventListener("click", function () {
      window.location.href = 'backend/logout.php';
    });
  }

  // Toggle background music on button click.
  bgToggle.addEventListener("click", function () {
    if (bgMusic.paused) {
      bgMusic.play()    // Start playing music.
      bgToggle.textContent = "Pause Music"  // Update button text.
    } else {
      bgMusic.pause()   // Pause music playback.
      bgToggle.textContent = "Play Music"   // Update button text.
    }
  })

  // Open leaderboard in new window/tab
  leaderboardBtn.addEventListener("click", function () {
    window.open("backend/leaderboard.php", "_blank")
  })

  // Open admin panel (if user is admin)
  if (adminBtn) {
    adminBtn.addEventListener("click", function () {
      window.open("admin/admin_dashboard.php", "_blank")
    })
  }

  // Displays the popup message.
  function displayPopup(message) {
    popup.style.display = "flex"    // Show the popup.
    popupMessage.textContent = message  // Set the message text.
  }

  // Shows the popup and plays win/lose sound.
  window.showPopup = function (message) {
    if (/won/i.test(message)) {
      winSound.play()   // Play win sound.
      submitGameStats(true) // Submit winning game stats
    } else {
      loseSound.play()  // Play lose sound.
      submitGameStats(false) // Submit losing game stats
    }
    displayPopup(message)   // Show message in popup.
  }

  // Submit game statistics to the database
  function submitGameStats(won) {
    const timeTaken = 120 - timeLeft; // Calculate time taken
    const gameData = {
      user_id: currentUserId, // Use actual user ID or null for guests
      puzzle_size: "4x4",
      time_taken_seconds: timeTaken,
      moves_count: moveCount,
      background_image_id: currentBackgroundId,
      win_status: won
    };

    fetch('backend/submit_stats.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(gameData)
    })
    .then(response => {
      if (!response.ok) {
        throw new Error('Network response was not ok');
      }
      return response.text(); // Get as text first to handle potential HTML errors
    })
    .then(text => {
      try {
        const data = JSON.parse(text);
        if (data.success) {
          console.log('Game stats submitted successfully');
        } else {
          console.error('Error submitting game stats:', data.error);
        }
      } catch (e) {
        console.error('Invalid JSON response:', text);
      }
    })
    .catch(error => {
      console.error('Error:', error);
    });
  }

  // Resets the game to the starting state.
  function resetGame() {
    clearInterval(timerInterval)    // Stop the timer.
    popup.style.display = "none"    // Hide popup if visible.

    // Reset tile positions to the solved state.
    tiles.forEach((tile, i) => {
      tile.x = i % 4
      tile.y = Math.floor(i / 4)
      tile.style.left = `${tile.x * 100}px`
      tile.style.top = `${tile.y * 100}px`
    })

    emptyX = 3  // Reset empty space.
    emptyY = 3
    updateMovableTiles()    // Update movable tiles' visual state.
    timeLeft = 120   // Reset timer.
    timerEl.textContent = `Time left: ${timeLeft}`
    moveCount = 0; // Reset move count.
    if (moveCountEl) moveCountEl.textContent = `Moves: ${moveCount}`;
    gameEnded = false
    gameStarted = false
    shuffleBtn.textContent = `Shuffle & Start`   // Reset button label.
  }

  // Resets game when "Play Again" is clicked.
  popupPlayAgain.addEventListener("click", resetGame)

  // Shuffle or reset the game when shuffle button is clicked.
  shuffleBtn.addEventListener("click", () => {
    if (!gameStarted) {
      startGame()    // Start new game and shuffle.
      shuffleBtn.textContent = "Start Over!"   // Update button text.
      gameStarted = true
    } else {
      resetGame()    // Reset to initial solved state.
    }
  })

  // Starts the game and initializes the timer.
  function startGame() {
    // Shuffle the board using 300 random valid moves.
    for (let i = 0; i < 300; i++) {
      const movable = tiles.filter((t) => isMovable(t.x, t.y))
      const rand = movable[Math.floor(Math.random() * movable.length)]
      moveTile(rand, true)    // Silent move (no sound).
    }

    updateMovableTiles()    // Highlight movable tiles.
    popup.style.display = "none"    // Hide any existing popup.

    // Start the countdown timer.
    clearInterval(timerInterval)    // Clear previous timer.
    timeLeft = 120
    timerEl.textContent = `Time left: ${timeLeft}`
    moveCount = 0; // Reset move count on start.
    if (moveCountEl) moveCountEl.textContent = `Moves: ${moveCount}`;
    gameEnded = false
    timerInterval = setInterval(() => {
      timeLeft--
      timerEl.textContent = `Time left: ${timeLeft}`
      if (timeLeft <= 0) {
        clearInterval(timerInterval)
        showPopup("You lost!") // Trigger loss.
        gameEnded = true
      }
    }, 1000)
  }

  // Create and add 15 puzzle tiles.
  for (let i = 0; i < 15; i++) {
    const tile = document.createElement("div")
    tile.className = "puzzlepiece"  // Add puzzle tile .
    tile.innerText = i + 1     // Display tile number.
    tile.x = i % 4             // Initial column.
    tile.y = Math.floor(i / 4) // Initial row.
    tile.style.left = `${tile.x * 100}px` // Set x position.
    tile.style.top = `${tile.y * 100}px`  // Set y position.
    tile.style.backgroundPosition = `-${tile.x * 100}px -${tile.y * 100}px`   // Align background.

    // Add click handler to move tile.
    tile.addEventListener("click", () => {
      moveTile(tile)    // Try to move the tile.
      updateMovableTiles()  // Refresh visual hints.
    })

    puzzleArea.appendChild(tile)   // Add tile to puzzle area.
    tiles.push(tile)   // Store tile in array.
  }

  // Check if a tile can move to the empty space.
  function isMovable(x, y) {
    return Math.abs(x - emptyX) + Math.abs(y - emptyY) === 1   // Must be adjacent.
  }

  // Moves the specified tile into the empty space.
  function moveTile(tile, silent = false) {
    if (gameEnded) return   // Prevent moves after game ends.
    if (!silent && !gameStarted) return     // Prevent moves before game starts.
    if (!isMovable(tile.x, tile.y)) return  // Tile not movable.

    const oldX = tile.x,
      oldY = tile.y
    tile.style.left = `${emptyX * 100}px`
    tile.style.top = `${emptyY * 100}px`
    tile.x = emptyX
    tile.y = emptyY
    emptyX = oldX
    emptyY = oldY

    if (!silent) {
      moveCount++;
      if (moveCountEl) moveCountEl.textContent = `Moves: ${moveCount}`;
      const soundClone = moveSound.cloneNode()  // Clone audio to allow overlaps.
      soundClone.play()     // Play move sound.
    }

    if (!silent && checkWin()) {
      clearInterval(timerInterval)  // Stop the timer.
      showPopup("You won!")         // Show win message.
      gameEnded = true
    }
  }

  // Checks if the puzzle is in a solved state.
  function checkWin() {
    return tiles.every((tile, i) => {
      const cx = i % 4,
        cy = Math.floor(i / 4)
      return tile.x === cx && tile.y === cy
    })
  }

  // Highlights tiles that can be moved.
  function updateMovableTiles() {
    tiles.forEach((tile) => {
      tile.classList.toggle("movablepiece", isMovable(tile.x, tile.y))
    })
  }

  // Load available backgrounds from the server
  function loadBackgrounds() {
    fetch('backend/get_backgrounds.php')
      .then(response => response.json())
      .then(data => {
        availableBackgrounds = data.backgrounds;
        populateBackgroundSelector();
        setRandomBackground();
      })
      .catch(error => {
        console.error('Error loading backgrounds:', error);
        // Fallback to default background
        availableBackgrounds = [{
          id: 0,
          name: 'Default',
          url: 'img/background.png'
        }];
        populateBackgroundSelector();
      });
  }

  // Populate the background selector dropdown
  function populateBackgroundSelector() {
    backgroundSelect.innerHTML = '';
    
    availableBackgrounds.forEach(bg => {
      const option = document.createElement('option');
      option.value = bg.id;
      option.textContent = bg.name;
      backgroundSelect.appendChild(option);
    });

    // Add change event listener
    backgroundSelect.addEventListener('change', function() {
      const selectedId = parseInt(this.value);
      changeBackground(selectedId);
    });
  }

  // Set a random background on page load
  function setRandomBackground() {
    if (availableBackgrounds.length > 0) {
      const randomIndex = Math.floor(Math.random() * availableBackgrounds.length);
      const randomBackground = availableBackgrounds[randomIndex];
      changeBackground(randomBackground.id);
      backgroundSelect.value = randomBackground.id;
    }
  }

  // Change the background image
  function changeBackground(backgroundId) {
    const selectedBackground = availableBackgrounds.find(bg => bg.id == backgroundId);
    if (selectedBackground) {
      currentBackgroundId = backgroundId;
      
      // Update all puzzle pieces with new background
      tiles.forEach(tile => {
        tile.style.backgroundImage = `url('${selectedBackground.url}')`;
      });
      
      console.log(`Background changed to: ${selectedBackground.name}`);
    }
  }

  // Initial visual update for movable tiles.
  updateMovableTiles()
  // Display initial move count
  if (moveCountEl) moveCountEl.textContent = `Moves: ${moveCount}`;
}