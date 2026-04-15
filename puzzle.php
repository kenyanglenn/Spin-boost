<?php
// The puzzle module is included inside spin.php.
?>
<section class="game-card">
    <div class="puzzle-card">
        <div class="puzzle-header">
            <div>
                <h2>Word Puzzle</h2>
                <p>Press Play first, then choose your stake and solve the word.</p>
            </div>
            <button class="secondary-btn" id="newPuzzle">Play Puzzle</button>
        </div>
        <div class="puzzle-board" id="puzzleBoard">
            <div class="reel-row" id="reelRow"></div>
        </div>
        <div class="puzzle-controls">
            <label for="puzzleStake">Stake (KES)</label>
            <input type="number" id="puzzleStake" placeholder="Min 10" min="10" step="1" value="10" disabled>
            <label for="puzzleAnswer">Your guess</label>
            <input type="text" id="puzzleAnswer" placeholder="Type the word" autocomplete="off" disabled>
            <div class="puzzle-meta">
                <span id="puzzleTimer">Time: 00:00</span>
                <span id="puzzleMultiplier">Reward ×0.0</span>
            </div>
            <button class="primary-btn" id="submitPuzzle" disabled>Submit Answer</button>
            <p class="hint" id="puzzleLimitText">Remaining puzzles today: <?php echo $planLimits['puzzles'] - $puzzleCount; ?> / <?php echo $planLimits['puzzles']; ?></p>
        </div>
        <div class="result-panel" id="puzzleResultPanel">
            <h3>Game stats</h3>
            <p id="puzzleResultText">New puzzle loads a random word with animated reels.</p>
        </div>
    </div>
</section>
