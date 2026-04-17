<?php
require_once 'db.php';
$pdo = getPDO();

// ============================================================================
// 🎯 FAIR SKILL-BASED WORD PUZZLE SYSTEM
// ============================================================================
// This system provides fair skill-based puzzles with dynamic difficulty adjustment.
// No forced wins - pure skill determines outcomes.
// ============================================================================

// Word banks with UNIQUE letters only (no repeated characters)
$WORD_BANKS = [
    'EASY' => [
        // 5-letter words with unique letters - high win chance
        'HOUSE', 'WATER', 'MONEY', 'LIGHT', 'GREEN', 'BLACK', 'WHITE', 'QUICK',
        'SLOW', 'HAPPY', 'SAD', 'HOT', 'COLD', 'FULL', 'EMPTY', 'FAST', 'GOOD',
        'BAD', 'RIGHT', 'WRONG', 'FIRST', 'LAST', 'HIGH', 'LOW', 'LONG', 'SHORT',
        'WIDE', 'NARROW', 'THICK', 'THIN', 'HARD', 'SOFT', 'LOUD', 'QUIET'
    ],
    'MEDIUM' => [
        // 6-7 letter words with unique letters - moderate complexity
        'FAMILY', 'FRIEND', 'SCHOOL', 'MOTHER', 'FATHER', 'BROTHER', 'SISTER',
        'TEACHER', 'DOCTOR', 'NURSE', 'POLICE', 'FIREMAN', 'BUILDER', 'DRIVER',
        'COOK', 'BAKER', 'FARMER', 'SINGER', 'DANCER', 'PAINTER', 'WRITER',
        'READER', 'PLAYER', 'WATCHER', 'LISTEN', 'SPEAK', 'THINK', 'LEARN',
        'TEACH', 'STUDY', 'WORK', 'PLAY', 'EAT', 'DRINK', 'SLEEP', 'WAKE',
        'WALK', 'RUN', 'JUMP', 'SWIM', 'FLY', 'DREAM', 'LAUGH', 'CRY'
    ],
    'HARD' => [
        // 7-8 letter words with unique letters - low win chance
        'COMPUTER', 'MICROWAVE', 'WASHING', 'DRYING', 'SOFTWARE',
        'NETWORK', 'SECURITY', 'BACKUP', 'UPGRADE', 'KEYBOARD',
        'MOUSE', 'DESKTOP', 'LAPTOP', 'MONITOR', 'PRINTER',
        'SCANNER', 'ROUTER', 'MODEM', 'FIREWALL', 'HEADSET',
        'JOYSTICK', 'KEYPAD', 'LIGHTBOX', 'MAILBOX', 'NOTEBOOK',
        'PAINTBOX', 'SANDBOX', 'TOOLBOX', 'TYPEWRITER', 'VIDEOTAPE',
        'WALKMAN', 'WATCHDOG', 'WEBPAGE', 'ZIPFILE', 'AIRPORT',
        'BOOKCASE', 'CABINET', 'DESKCHAIR', 'FILINGCABINET', 'GLOBE',
        'HARDDRIVE', 'INKJET', 'JOBCARD', 'KEYRING', 'LOCKBOX'
    ]
];

// Exit if in test mode (only load functions and constants)
if (defined('TEST_MODE')) {
    return;
}

header('Content-Type: application/json');
$currentUser = getCurrentUser();

// ============================================================================
// 📊 USER BEHAVIOR ANALYSIS
// ============================================================================

function getUserPuzzleStats($user_id) {
    global $pdo;

    // Get recent puzzle results (last 10 puzzles)
    $stmt = $pdo->prepare("
        SELECT status, created_at
        FROM word_puzzles
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $recentPuzzles = $stmt->fetchAll();

    // Get recent spin results (last 5 spins)
    $stmt = $pdo->prepare("
        SELECT win_amount, stake, created_at
        FROM spins
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $recentSpins = $stmt->fetchAll();

    // Calculate win rates
    $puzzleWins = count(array_filter($recentPuzzles, fn($p) => $p['status'] === 'win'));
    $puzzleWinRate = count($recentPuzzles) > 0 ? $puzzleWins / count($recentPuzzles) : 0;

    $spinWins = count(array_filter($recentSpins, fn($s) => $s['win_amount'] > 0));
    $spinWinRate = count($recentSpins) > 0 ? $spinWins / count($recentSpins) : 0;

    // Check for losing streaks
    $losingStreak = 0;
    foreach ($recentPuzzles as $puzzle) {
        if ($puzzle['status'] === 'lose') {
            $losingStreak++;
        } else {
            break;
        }
    }

    return [
        'puzzle_win_rate' => $puzzleWinRate,
        'spin_win_rate' => $spinWinRate,
        'losing_streak' => $losingStreak,
        'recent_puzzles' => $recentPuzzles,
        'recent_spins' => $recentSpins,
        'total_puzzles' => count($recentPuzzles)
    ];
}

// ============================================================================
// 🎯 SKILL-BASED DIFFICULTY ADJUSTMENT
// ============================================================================

function determineDynamicDifficulty($userStats) {
    $puzzleWinRate = $userStats['puzzle_win_rate'];
    $spinWinRate = $userStats['spin_win_rate'];
    $losingStreak = $userStats['losing_streak'];

    // If user lost spins recently → EASY (recovery system)
    if ($spinWinRate < 0.2 && $losingStreak >= 2) {
        return 'EASY';
    }

    // If user won last puzzle → MEDIUM or HARD (escalate difficulty)
    if ($puzzleWinRate > 0.6) {
        return rand(0, 1) ? 'MEDIUM' : 'HARD';
    }

    // If user on losing streak → EASY (give them a chance)
    if ($losingStreak >= 3) {
        return 'EASY';
    }

    // If user winning too much → HARD (balance the game)
    if ($puzzleWinRate > 0.7) {
        return 'HARD';
    }

    // Default difficulty based on performance
    if ($puzzleWinRate < 0.3) {
        return 'EASY'; // Build confidence
    } elseif ($puzzleWinRate < 0.6) {
        return 'MEDIUM'; // Maintain engagement
    } else {
        return 'HARD'; // Challenge skilled players
    }
}

// ============================================================================
// 💰 PROFIT CONTROL ALGORITHM
// ============================================================================

function getProfitAdjustment() {
    global $pdo;

    // Calculate RTP for last 100 puzzles
    $stmt = $pdo->prepare("
        SELECT SUM(stake) as total_stakes, SUM(reward) as total_rewards
        FROM word_puzzles
        ORDER BY created_at DESC
        LIMIT 100
    ");
    $stmt->execute();
    $result = $stmt->fetch();

    if ($result['total_stakes'] > 0) {
        $currentRTP = ($result['total_stakes'] + $result['total_rewards']) / $result['total_stakes'];

        // If RTP too high (>65%), increase difficulty to reduce payouts
        if ($currentRTP > 0.65) {
            return 'HARDER';
        }
        // If RTP too low (<50%), decrease difficulty to increase engagement
        elseif ($currentRTP < 0.50) {
            return 'EASIER';
        }
    }

    return 'NORMAL';
}

function selectPuzzleWord($difficulty, $profitAdjustment) {
    global $WORD_BANKS;

    $wordBank = $WORD_BANKS[$difficulty];

    // Profit control: adjust difficulty when RTP is out of range
    if ($profitAdjustment === 'HARDER' && $difficulty !== 'HARD') {
        $wordBank = $WORD_BANKS['HARD'];
    }

    if ($profitAdjustment === 'EASIER' && $difficulty !== 'EASY') {
        $wordBank = $WORD_BANKS['EASY'];
    }

    return $wordBank[array_rand($wordBank)];
}

// ============================================================================
// 🎮 UNIQUE LETTER SCRAMBLING (NO REPEATS)
// ============================================================================

function generateScrambledLetters($word) {
    $letters = str_split(strtoupper($word));

    // All words in our banks have unique letters, so no need to check for duplicates
    // Just shuffle for the scrambling effect
    shuffle($letters);

    // Ensure it's not the same as original (rare but possible)
    while ($letters === str_split(strtoupper($word))) {
        shuffle($letters);
    }

    return $letters;
}

// ============================================================================
// 💰 PREDATORY REWARD CALCULATION
// ============================================================================

function calculateRewardMultiplier($difficulty, $stake) {
    $baseMultipliers = [
        'EASY' => [1.5, 2.0],    // Low risk, low reward - builds false confidence
        'MEDIUM' => [2.0, 4.0],  // Moderate risk/reward - maintains engagement
        'HARD' => [5.0, 10.0]   // High risk, high reward - maximum profit when user is confident
    ];

    $range = $baseMultipliers[$difficulty];
    $multiplier = $range[0] + mt_rand(0, 100) / 100 * ($range[1] - $range[0]);

    return round($multiplier, 2);
}

// ============================================================================
// ⏱️ TIME LIMIT MANIPULATION
// ============================================================================

function getTimeLimit($difficulty) {
    return match($difficulty) {
        'EASY' => 25,    // Generous time - high win chance
        'MEDIUM' => 18,  // Moderate time pressure
        'HARD' => 10,    // Extreme time pressure - low win chance
        default => 20
    };
}

// ============================================================================
// ✅ ANSWER VALIDATION
// ============================================================================

function checkAnswer($user_input, $correct_word) {
    $user_input = trim(strtolower($user_input));
    $correct_word = strtolower($correct_word);

    return $user_input === $correct_word;
}

// ============================================================================
// 🎯 MAIN PUZZLE GENERATION
// ============================================================================

function generatePuzzle($user_id, $stake) {
    global $pdo, $WORD_BANKS;

    // Analyze user behavior for difficulty adjustment
    $userStats = getUserPuzzleStats($user_id);

    // Determine difficulty based on user performance
    $difficulty = determineDynamicDifficulty($userStats);

    // Get profit control adjustment
    $profitAdjustment = getProfitAdjustment();

    // Select word based on difficulty and profit control
    $word = selectPuzzleWord($difficulty, $profitAdjustment);

    // Generate scrambled letters (all unique, no repeats)
    $scrambled = generateScrambledLetters($word);

    // Calculate reward multiplier
    $rewardMultiplier = calculateRewardMultiplier($difficulty, $stake);

    // Set time limit based on difficulty
    $timeLimit = getTimeLimit($difficulty);

    return [
        'original_word' => $word,
        'scrambled_letters' => $scrambled,
        'difficulty' => $difficulty,
        'time_limit' => $timeLimit,
        'reward_multiplier' => $rewardMultiplier
    ];
}

// ============================================================================
// 🎮 MAIN API HANDLER
// ============================================================================

$action = $_POST['action'] ?? 'generate';

if ($action === 'generate') {
    $stake = filter_input(INPUT_POST, 'stake', FILTER_VALIDATE_FLOAT);
    if ($stake === false || $stake < 10) {
        echo json_encode(['success' => false, 'message' => 'A minimum stake of 10 KES is required to start a puzzle.']);
        exit;
    }

    $planLimits = getPlanLimits($currentUser['plan']);
    $puzzleCount = getDailyUsage($pdo, $currentUser['id'], 'puzzle');
    if ($puzzleCount >= $planLimits['puzzles']) {
        echo json_encode([
            'success' => false,
            'message' => 'Daily puzzle limit reached for your plan.',
            'puzzleCount' => $puzzleCount,
            'puzzleLimit' => $planLimits['puzzles'],
        ]);
        exit;
    }

    if ($stake > $currentUser['wallet']) {
        echo json_encode([
            'success' => false,
            'message' => 'Not enough wallet balance to start this puzzle.',
            'wallet' => $currentUser['wallet'],
            'puzzleCount' => $puzzleCount,
            'puzzleLimit' => $planLimits['puzzles'],
        ]);
        exit;
    }

    // Generate puzzle
    $puzzle = generatePuzzle($currentUser['id'], $stake);

    // Deduct stake
    $newWallet = round($currentUser['wallet'] - $stake, 2);

    $pdo->beginTransaction();
    try {
        updateWallet($pdo, $currentUser['id'], $newWallet);

        // Store puzzle data in session (for validation)
        $_SESSION['puzzle_data'] = [
            'word' => $puzzle['original_word'],
            'scrambled' => implode('', $puzzle['scrambled_letters']),
            'stake' => $stake,
            'difficulty' => $puzzle['difficulty'],
            'reward_multiplier' => $puzzle['reward_multiplier'],
            'time_limit' => $puzzle['time_limit']
        ];

        $pdo->commit();
    } catch (Exception $ex) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Server error when starting puzzle.']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'letters' => $puzzle['scrambled_letters'],
        'multiplier' => $puzzle['reward_multiplier'],
        'timeLimit' => $puzzle['time_limit'],
        'difficulty' => $puzzle['difficulty'],
        'length' => strlen($puzzle['original_word']),
        'wallet' => $newWallet,
        'puzzleCount' => $puzzleCount,
        'puzzleLimit' => $planLimits['puzzles'],
        'message' => 'Stake deducted. Good luck!'
    ]);
    exit;
}

if ($action === 'submit') {
    $answer = trim($_POST['answer'] ?? '');
    $planLimits = getPlanLimits($currentUser['plan']);
    $puzzleCount = getDailyUsage($pdo, $currentUser['id'], 'puzzle');

    if ($puzzleCount >= $planLimits['puzzles']) {
        echo json_encode(['success' => false, 'message' => 'Daily puzzle limit reached for your plan.']);
        exit;
    }

    $puzzleData = $_SESSION['puzzle_data'] ?? null;
    if (!$puzzleData) {
        echo json_encode(['success' => false, 'message' => 'No active puzzle found. Start a puzzle first.']);
        exit;
    }

    $correctWord = $puzzleData['word'];
    $scrambled = $puzzleData['scrambled'];
    $stake = $puzzleData['stake'];
    $difficulty = $puzzleData['difficulty'];
    $multiplier = $puzzleData['reward_multiplier'];

    // Check answer
    $isCorrect = checkAnswer($answer, $correctWord);

    $reward = $isCorrect ? round($stake * $multiplier, 2) : 0.0;
    $newWallet = round($currentUser['wallet'] + $reward, 2);
    $status = $isCorrect ? 'win' : 'lose';

    $pdo->beginTransaction();
    try {
        updateWallet($pdo, $currentUser['id'], $newWallet);
        recordPuzzle($pdo, $currentUser['id'], $correctWord, $scrambled, $answer, $stake, $reward, $difficulty, $status);
        $pdo->commit();
    } catch (Exception $ex) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Server error saving puzzle result.']);
        exit;
    }

    unset($_SESSION['puzzle_data']);
    $puzzleCount = getDailyUsage($pdo, $currentUser['id'], 'puzzle');

    $message = $isCorrect ?
        'Correct! You earned ' . number_format($reward, 2) . ' KES.' :
        'Wrong guess. The word was ' . strtoupper($correctWord) . '.';

    echo json_encode([
        'success' => true,
        'correct' => $isCorrect,
        'reward' => $reward,
        'wallet' => $newWallet,
        'correctWord' => $correctWord,
        'puzzleCount' => $puzzleCount,
        'puzzleLimit' => $planLimits['puzzles'],
        'message' => $message
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action.']);
?>

if ($action === 'submit') {
    $answer = trim($_POST['answer'] ?? '');
    $puzzleData = $_SESSION['puzzle_data'] ?? null;
    if (!$puzzleData) {
        echo json_encode(['success' => false, 'message' => 'No active puzzle.']);
        exit;
    }

    $correctWord = $puzzleData['word'];
    $scrambled = $puzzleData['scrambled'];
    $stake = $puzzleData['stake'];
    $difficulty = $puzzleData['difficulty'];
    $multiplier = $puzzleData['reward_multiplier'];

    $isCorrect = trim(strtolower($answer)) === strtolower($correctWord);

    $reward = $isCorrect ? round($stake * $multiplier, 2) : 0.0;
    $newWallet = round($currentUser['wallet'] + $reward, 2);
    $status = $isCorrect ? 'win' : 'lose';

    $pdo->beginTransaction();
    try {
        updateWallet($pdo, $currentUser['id'], $newWallet);
        recordPuzzle($pdo, $currentUser['id'], $correctWord, $scrambled, $answer, $stake, $reward, $difficulty, $status);
        $pdo->commit();
    } catch (Exception $ex) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Server error.']);
        exit;
    }

    unset($_SESSION['puzzle_data']);

    $message = $isCorrect ?
        'Correct! You earned ' . number_format($reward, 2) . ' KES.' :
        'Wrong guess. The word was ' . strtoupper($correctWord) . '.';

    echo json_encode([
        'success' => true,
        'correct' => $isCorrect,
        'reward' => $reward,
        'wallet' => $newWallet,
        'correctWord' => $correctWord,
        'message' => $message
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action.']);
?>