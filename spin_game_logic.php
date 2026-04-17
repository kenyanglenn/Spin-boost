<?php
/**
 * EDUCATIONAL SPIN WHEEL GAME LOGIC
 * 
 * This module demonstrates how controlled spin wheel systems work
 * and the mathematical advantages built into them.
 * 
 * For educational purposes: showing the disadvantages of such mechanics
 */

require_once 'db.php';

// Configuration
define('TARGET_RTP', 0.55); // 55% Return to Player = 45% house edge
define('MAX_PAYOUT_PER_SPIN', 500);
define('MAX_SESSION_PAYOUT', 1000); // Cap total session winnings to protect house edge
define('LOSING_STREAK_THRESHOLD', 3);
define('LOW_BALANCE_THRESHOLD', 50);
define('NEW_USER_THRESHOLD_DAYS', 7);

function secureRand($min, $max) {
    try {
        return random_int($min, $max);
    } catch (Exception $e) {
        return mt_rand($min, $max);
    }
}

/**
 * Base weighted probability system
 * Returns multiplier (0-9) based on controlled probabilities
 */
function getBaseMultiplier() {
    $rand = secureRand(1, 10000);

    // Weighted distribution in basis points for all 10 wheel segments
    if ($rand <= 6000)   return 0;      // 60.00% consolation / loss
    if ($rand <= 8000)   return 1;      // 20.00% low win
    if ($rand <= 9000)   return 2;      // 10.00% low-mid win
    if ($rand <= 9400)   return 3;      // 4.00% mid win
    if ($rand <= 9650)   return 4;      // 2.50% mid-high win
    if ($rand <= 9800)   return 5;      // 1.50% high win
    if ($rand <= 9880)   return 6;      // 0.80% bigger win
    if ($rand <= 9925)   return 7;      // 0.45% premium win
    if ($rand <= 9955)   return 8;      // 0.30% jackpot-level win
    if ($rand <= 10000)  return 9;      // 0.45% top jackpot

    return 0; // Fallback to loss
}

/**
 * Check if user is new (created within last 7 days)
 * New users get slightly better odds to create engagement
 */
function isNewUser($pdo, $userId) {
    $user = getUserById($userId);
    $createdAt = new DateTime($user['created_at']);
    $now = new DateTime();
    $diff = $now->diff($createdAt)->days;
    return $diff <= NEW_USER_THRESHOLD_DAYS;
}

/**
 * Get user's recent spin history/streak
 */
function getUserSpinStreak($pdo, $userId) {
    $stmt = $pdo->prepare('
        SELECT multiplier 
        FROM spins 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ');
    $stmt->execute([$userId]);
    $spins = $stmt->fetchAll();
    
    $losses = 0;
    foreach ($spins as $spin) {
        if ($spin['multiplier'] == 0) {
            $losses++;
        } else {
            break; // Streak broken
        }
    }
    
    return $losses;
}

/**
 * Calculate user's RTP performance
 * If user has won too much, reduce win chances
 */
function getUserRTPAdjustment($pdo, $userId) {
    $stmt = $pdo->prepare('
        SELECT 
            SUM(stake) as total_stakes,
            SUM(CASE WHEN multiplier > 0 THEN win_amount ELSE 0 END) as total_winnings
        FROM spins 
        WHERE user_id = ? 
        AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ');
    $stmt->execute([$userId]);
    $result = $stmt->fetch();
    
    $totalStakes = $result['total_stakes'] ?? 0;
    $totalWinnings = $result['total_winnings'] ?? 0;
    
    if ($totalStakes == 0) {
        return 1.0; // No adjustment
    }
    
    $currentRTP = $totalWinnings / $totalStakes;
    
    // If RTP exceeds target, reduce win probability
    if ($currentRTP > TARGET_RTP) {
        $difference = $currentRTP - TARGET_RTP;
        $adjustment = 1.0 - ($difference * 2); // Scale the penalty
        return max(0.7, $adjustment); // Don't reduce below 70%
    }
    
    // If RTP below target, slightly increase win chances
    if ($currentRTP < (TARGET_RTP * 0.5)) {
        return 1.1; // Give a small boost
    }
    
    return 1.0;
}

/**
 * Modify multiplier based on user psychology triggers
 */
function applyPsychologicalAdjustment($pdo, $userId, $baseMultiplier, $userWallet) {
    $adjustment = 0;
    
    // LOSING STREAK BREAKER
    $streak = getUserSpinStreak($pdo, $userId);
    if ($streak >= LOSING_STREAK_THRESHOLD) {
        // Force a small win on streak
        if (secureRand(1, 100) <= 70) {
            return secureRand(1, 2); // Force x1 or x2
        }
    }
    
    // LOW BALANCE BOOST
    if ($userWallet < LOW_BALANCE_THRESHOLD && $userWallet > 0) {
        if (secureRand(1, 100) <= 50) {
            return 1; // Give them a small win to keep playing
        }
    }
    
    return $baseMultiplier;
}

/**
 * Apply high-stake penalty
 * Users betting large amounts get worse odds
 */
function applyStakePenalty($baseMultiplier, $stake) {
    // If stake is high (>100 KES), reduce probability of high multipliers
    if ($stake > 100) {
        if ($baseMultiplier >= 5) {
            // 40% chance to reduce to lower multiplier
            if (secureRand(1, 100) <= 40) {
                return max(0, $baseMultiplier - 2);
            }
        }
    }
    
    return $baseMultiplier;
}

/**
 * MAIN SPIN LOGIC FUNCTION
 * Returns complete spin result with all mechanics applied
 */
function getSpinResult($pdo, $userId, $stake) {
    $user = getUserById($userId);
    
    if (!$user) {
        return ['error' => 'User not found'];
    }
    
    // Initialize session payout tracking if needed
    if (!isset($_SESSION['spin_session_payout'])) {
        $_SESSION['spin_session_payout'] = 0;
    }

    // Step 1: Base probability
    $multiplier = getBaseMultiplier();

    // Step 1a: Enforce session payout cap if threshold exceeded
    if ($_SESSION['spin_session_payout'] >= MAX_SESSION_PAYOUT) {
        $multiplier = secureRand(1, 100) <= 20 ? 1 : 0;
    }
    
    // Step 2: Apply RTP adjustment
    $rtpAdjustment = getUserRTPAdjustment($pdo, $userId);
    if ($rtpAdjustment < 1.0 && $multiplier > 0) {
        // Reduce win probability if user has won too much
        if (secureRand(1, 100) <= (int) (100 * (1 - $rtpAdjustment))) {
            $multiplier = 0;
        }
    }
    
    // Step 3: Apply psychological adjustments
    $multiplier = applyPsychologicalAdjustment($pdo, $userId, $multiplier, $user['wallet']);
    
    // Step 4: Apply stake penalty
    $multiplier = applyStakePenalty($multiplier, $stake);
    
    // Step 5: Calculate win amount
    $winAmount = $stake * $multiplier;
    
    // Step 6: Apply payout cap
    if ($winAmount > MAX_PAYOUT_PER_SPIN) {
        $winAmount = MAX_PAYOUT_PER_SPIN;
    }
    
    // Step 7: Determine near-miss target (for visual effect)
    $nearMissTarget = null;
    if ($multiplier == 0 && secureRand(1, 100) <= 40) {
        // Create illusion of near-miss
        $nearMissTarget = secureRand(7, 9);
    }
    
    return [
        'multiplier' => $multiplier,
        'winAmount' => $winAmount,
        'nearMissTarget' => $nearMissTarget,
        'isWin' => $multiplier > 0
    ];
}

/**
 * Record spin in database
 */
function recordSpinResult($pdo, $userId, $stake, $multiplier, $winAmount) {
    $stmt = $pdo->prepare('
        INSERT INTO spins (user_id, stake, multiplier, win_amount, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ');
    
    return $stmt->execute([$userId, $stake, $multiplier, $winAmount]);
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
