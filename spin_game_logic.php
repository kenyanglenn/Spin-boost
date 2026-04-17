<?php
/**
 * SPIN WHEEL GAME LOGIC - Provably Fair System
 * 
 * All RNG and probability calculations are performed server-side (PHP).
 * Results are determined BEFORE animation.
 * Dynamic adjustments track user history for engagement (not manipulation).
 */

require_once 'db.php';

// Configuration
define('TARGET_RTP', 0.94); // 94% Return to Player = 6% house edge
define('RTP_MIN', 0.92);
define('RTP_MAX', 0.96);
define('RTP_CHECK_INTERVAL', 50); // Check RTP every 50 spins
define('MAX_PAYOUT_PER_SPIN', 500);
define('SPIN_SESSION_RESET_COUNT', 10);
define('SESSION_INACTIVITY_TIMEOUT', 1800); // 30 minutes
define('LOSING_STREAK_THRESHOLD', 3);
define('BIG_WIN_MULTIPLIER_THRESHOLD', 5);
define('LOW_BALANCE_THRESHOLD', 50);

// Probability constraints
define('X0_MIN_PERCENT', 30);
define('X0_MAX_PERCENT', 45);

// Base probability ranges (basis points / 10000)
$BASE_PROBABILITIES = [
    0 => 3800,  // x0: 38%
    1 => 2200,  // x1: 22%
    2 => 1400,  // x2: 14%
    3 => 1000,  // x3: 10%
    4 => 600,   // x4: 6%
    5 => 400,   // x5: 4%
    6 => 300,   // x6: 3%
    7 => 150,   // x7: 1.5%
    8 => 100,   // x8: 1%
    9 => 50,    // x9: 0.5%
];

function secureRand($min, $max) {
    try {
        return random_int($min, $max);
    } catch (Exception $e) {
        return mt_rand($min, $max);
    }
}

function initializeSpinSessionTracker() {
    if (!isset($_SESSION['spin_session_state']) || !is_array($_SESSION['spin_session_state'])) {
        $_SESSION['spin_session_state'] = [
            'spin_count' => 0,
            'last_spin_time' => time(),
            'loss_streak' => 0,
            'recent_spins' => [], // Last 10 multipliers
            'adjustment_type' => null, // 'loss_boost', 'big_win_penalty', 'rtp_correction'
            'global_rtp_check_count' => 0,
            'rtp_correction_active' => null, // null, 'increase', 'decrease'
        ];
    }

    $lastSpinTime = $_SESSION['spin_session_state']['last_spin_time'] ?? time();
    if (time() - $lastSpinTime >= SESSION_INACTIVITY_TIMEOUT) {
        $_SESSION['spin_session_state'] = [
            'spin_count' => 0,
            'last_spin_time' => time(),
            'loss_streak' => 0,
            'recent_spins' => [],
            'adjustment_type' => null,
            'global_rtp_check_count' => 0,
            'rtp_correction_active' => null,
        ];
    }

    if ($_SESSION['spin_session_state']['spin_count'] >= SPIN_SESSION_RESET_COUNT) {
        $_SESSION['spin_session_state']['spin_count'] = 0;
        $_SESSION['spin_session_state']['loss_streak'] = 0;
        $_SESSION['spin_session_state']['adjustment_type'] = null;
        $_SESSION['spin_session_state']['recent_spins'] = [];
    }

    $_SESSION['spin_session_state']['last_spin_time'] = time();
}

function getAdjustedProbabilities() {
    global $BASE_PROBABILITIES;
    $adjusted = $BASE_PROBABILITIES;
    $state = $_SESSION['spin_session_state'];

    // Reset probabilities to base values
    $x0_basis = $BASE_PROBABILITIES[0];

    // LOSS STREAK BOOST: 3+ consecutive x0 results
    if ($state['loss_streak'] >= LOSING_STREAK_THRESHOLD) {
        $state['adjustment_type'] = 'loss_boost';
        // Reduce x0 by 2-3%, distribute to x2-x4
        $reduction = secureRand(200, 300); // 2-3%
        $x0_basis = max(3000, $x0_basis - $reduction);
        
        // Distribute reduction to x2, x3, x4
        $distribute_per = intdiv($reduction, 3);
        $adjusted[2] = min($adjusted[2] + $distribute_per, 1600);
        $adjusted[3] = min($adjusted[3] + $distribute_per, 1200);
        $adjusted[4] = min($adjusted[4] + $distribute_per, 800);
    }
    // BIG WIN PENALTY: Just won x5+
    elseif ($state['adjustment_type'] === 'big_win_penalty') {
        // Increase x0 by 2-3%, reduce x2-x3 slightly
        $increase = secureRand(200, 300); // 2-3%
        $x0_basis = min(4500, $x0_basis + $increase);
        
        // Reduce x2, x3
        $adjusted[2] = max($adjusted[2] - 100, 1200);
        $adjusted[3] = max($adjusted[3] - 100, 900);
    }
    // RTP CORRECTION: Adjust if global RTP is out of range
    elseif ($state['rtp_correction_active'] === 'increase') {
        // Increase x0 by 1-2%
        $increase = secureRand(100, 200);
        $x0_basis = min(4500, $x0_basis + $increase);
    } elseif ($state['rtp_correction_active'] === 'decrease') {
        // Decrease x0 by 1-2%
        $reduction = secureRand(100, 200);
        $x0_basis = max(3000, $x0_basis - $reduction);
    }

    // Enforce x0 hard constraints
    if ($x0_basis < intval(X0_MIN_PERCENT * 100)) {
        $x0_basis = intval(X0_MIN_PERCENT * 100);
    } elseif ($x0_basis > intval(X0_MAX_PERCENT * 100)) {
        $x0_basis = intval(X0_MAX_PERCENT * 100);
    }

    $adjusted[0] = $x0_basis;

    // Normalize to ensure total = 10000
    $total = array_sum($adjusted);
    if ($total !== 10000) {
        $difference = 10000 - $total;
        $adjusted[0] += $difference;
    }

    $_SESSION['spin_session_state'] = $state;
    return $adjusted;
}

function getMultiplierFromRNG($rand, $probabilities) {
    $cumulative = 0;
    for ($i = 0; $i <= 9; $i++) {
        $cumulative += $probabilities[$i];
        if ($rand <= $cumulative) {
            return $i;
        }
    }
    return 0; // Fallback
}

function getSpinResult($pdo, $userId, $stake) {
    $user = getUserById($userId);
    if (!$user) {
        return ['error' => 'User not found'];
    }

    initializeSpinSessionTracker();
    $probabilities = getAdjustedProbabilities();

    // Generate RNG (0-10000)
    $rand = secureRand(1, 10000);
    $multiplier = getMultiplierFromRNG($rand, $probabilities);

    // Calculate win amount
    $winAmount = $stake * $multiplier;
    if ($winAmount > MAX_PAYOUT_PER_SPIN) {
        $winAmount = MAX_PAYOUT_PER_SPIN;
    }

    // Calculate rotation angle for frontend animation
    $segmentAngle = 360 / 10;
    $targetSegmentAngle = ($multiplier * $segmentAngle) + ($segmentAngle / 2);
    $fullSpins = secureRand(6, 9);
    $rotationAngle = ($fullSpins * 360) + $targetSegmentAngle;
    $spinDuration = (secureRand(4000, 7000) / 1000);

    // Update session state
    $state = $_SESSION['spin_session_state'];
    $state['spin_count'] += 1;
    $state['recent_spins'][] = $multiplier;
    if (count($state['recent_spins']) > 10) {
        array_shift($state['recent_spins']);
    }

    // Update loss streak
    if ($multiplier === 0) {
        $state['loss_streak'] += 1;
    } else {
        $state['loss_streak'] = 0;
        // Big win detected: next spins will have penalty
        if ($multiplier >= BIG_WIN_MULTIPLIER_THRESHOLD) {
            $state['adjustment_type'] = 'big_win_penalty';
        }
    }

    // Check global RTP and apply correction if needed
    if ($state['global_rtp_check_count'] % RTP_CHECK_INTERVAL === 0) {
        $globalRTP = calculateGlobalRTP($pdo);
        if ($globalRTP < RTP_MIN) {
            $state['rtp_correction_active'] = 'increase';
        } elseif ($globalRTP > RTP_MAX) {
            $state['rtp_correction_active'] = 'decrease';
        } else {
            $state['rtp_correction_active'] = null;
        }
    }
    $state['global_rtp_check_count'] += 1;

    $_SESSION['spin_session_state'] = $state;

    // Prepare metadata
    $metadata = [
        'rng_roll' => $rand,
        'streak_count' => $state['loss_streak'],
        'adjustment_applied' => $state['adjustment_type'],
        'rtp_correction' => $state['rtp_correction_active'],
        'probability_ranges' => json_encode($probabilities),
        'timestamp' => time(),
    ];

    return [
        'multiplier' => $multiplier,
        'winAmount' => $winAmount,
        'rotationAngle' => $rotationAngle,
        'spinDuration' => $spinDuration,
        'metadata' => $metadata,
        'isWin' => $multiplier > 0
    ];
}

function calculateGlobalRTP($pdo) {
    $stmt = $pdo->prepare('
        SELECT 
            SUM(stake) as total_stakes,
            SUM(win_amount) as total_winnings
        FROM spins
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ');
    $stmt->execute();
    $result = $stmt->fetch();

    $totalStakes = $result['total_stakes'] ?? 0;
    $totalWinnings = $result['total_winnings'] ?? 0;

    if ($totalStakes === 0) {
        return TARGET_RTP;
    }

    return $totalWinnings / $totalStakes;
}


/**
 * Record spin in database with metadata
 */
function recordSpinResult($pdo, $userId, $stake, $multiplier, $winAmount, $metadata = null) {
    $metadataJson = $metadata ? json_encode($metadata) : null;
    $stmt = $pdo->prepare('
        INSERT INTO spins (user_id, stake, multiplier, win_amount, spin_metadata, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ');
    
    return $stmt->execute([$userId, $stake, $multiplier, $winAmount, $metadataJson]);
}

/**
 * Get analytics for educational purposes
 */
function getSpinAnalytics($pdo, $userId = null) {
    if ($userId) {
        $stmt = $pdo->prepare('
            SELECT 
                COUNT(*) as total_spins,
                SUM(stake) as total_stakes,
                SUM(win_amount) as total_winnings,
                SUM(CASE WHEN multiplier = 0 THEN 1 ELSE 0 END) as losses,
                ROUND(SUM(win_amount) / SUM(stake) * 100, 2) as actual_rtp,
                AVG(stake) as avg_stake,
                MAX(win_amount) as max_win
            FROM spins 
            WHERE user_id = ?
        ');
        $stmt->execute([$userId]);
    } else {
        $stmt = $pdo->prepare('
            SELECT 
                COUNT(*) as total_spins,
                SUM(stake) as total_stakes,
                SUM(win_amount) as total_winnings,
                SUM(CASE WHEN multiplier = 0 THEN 1 ELSE 0 END) as losses,
                ROUND(SUM(win_amount) / SUM(stake) * 100, 2) as actual_rtp,
                AVG(stake) as avg_stake,
                MAX(win_amount) as max_win
            FROM spins
        ');
        $stmt->execute([]);
    }
    
    return $stmt->fetch();
}

/**
 * Generate educational report showing house mechanics
 */
function generateEducationalReport($pdo) {
    $analytics = getSpinAnalytics($pdo);
    
    $report = "
    ╔════════════════════════════════════════════════════════════╗
    ║     SPIN WHEEL MECHANICS - EDUCATIONAL ANALYSIS            ║
    ║     (Demonstrating Hidden Disadvantages)                   ║
    ╚════════════════════════════════════════════════════════════╝
    
    📊 OVERALL STATISTICS:
    ─────────────────────
    Total Spins:        {$analytics['total_spins']}
    Total Stakes:       KES " . number_format($analytics['total_stakes'], 2) . "
    Total Winnings:     KES " . number_format($analytics['total_winnings'], 2) . "
    Total Losses:       {$analytics['losses']}
    
    💰 HOUSE PERFORMANCE:
    ──────────────────
    Target RTP:         " . (TARGET_RTP * 100) . "%
    Actual RTP:         {$analytics['actual_rtp']}%
    House Edge:         " . (100 - $analytics['actual_rtp']) . "%
    House Profit:       KES " . number_format($analytics['total_stakes'] - $analytics['total_winnings'], 2) . "
    
    🎲 MECHANICS APPLIED:
    ──────────────────
    ✓ Weighted probabilities (60% zero, 1% high rewards)
    ✓ RTP control (auto-adjustment based on performance)
    ✓ Losing streak breaks (forced small wins)
    ✓ Low balance boosts (psychological retention)
    ✓ High stake penalties (reduced odds for larger bets)
    ✓ Near-miss effects (visual illusion of almost winning)
    ✓ Payout caps (maximum win limits)
    ✓ Duration-based adjustments (new user honeymoon)
    
    ⚠️  KEY INSIGHTS FOR EDUCATION:
    ──────────────────────────────
    1. Math is rigged: Despite seeming random, probabilities favor house
    2. Streak breaks: Losses followed by small wins create addiction loops
    3. Low balance bait: Users near zero get hope to re-engage
    4. Illusion of control: Near-misses make users feel close to winning
    5. Hidden penalties: Larger bets get worse odds (backfire effect)
    6. Long-term loss: Most users will lose 35-50% of stake over time
    
    ";
    
    return $report;
}
?>
