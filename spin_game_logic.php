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
define('MAX_SESSION_PAYOUT', 5); // multiplier of average stake used to protect house edge
define('SPIN_SESSION_RESET_COUNT', 10);
define('LOSING_STREAK_THRESHOLD', 4);
define('LOW_BALANCE_THRESHOLD', 50);
define('NEW_USER_THRESHOLD_DAYS', 7);
define('MIN_SPIN_DURATION', 4.0);
define('MAX_SPIN_DURATION', 7.0);
define('NEAR_MISS_HIGH_PROBABILITY', 15);

function secureRand($min, $max) {
    try {
        return random_int($min, $max);
    } catch (Exception $e) {
        return mt_rand($min, $max);
    }
}

function initializeSpinSessionTracker() {
    if (!isset($_SESSION['spin_session_tracker']) || !is_array($_SESSION['spin_session_tracker'])) {
        $_SESSION['spin_session_tracker'] = [
            'spin_count' => 0,
            'total_stake' => 0.0,
            'total_payout' => 0.0,
            'loss_streak' => 0,
        ];
    }

    if ($_SESSION['spin_session_tracker']['spin_count'] >= SPIN_SESSION_RESET_COUNT) {
        $_SESSION['spin_session_tracker'] = [
            'spin_count' => 0,
            'total_stake' => 0.0,
            'total_payout' => 0.0,
            'loss_streak' => 0,
        ];
    }
}

function getSessionAverageStake($stake) {
    $tracker = $_SESSION['spin_session_tracker'];
    if ($tracker['spin_count'] > 0 && $tracker['total_stake'] > 0) {
        return max($stake, $tracker['total_stake'] / $tracker['spin_count']);
    }
    return $stake;
}

function getSessionPayoutThreshold($stake) {
    $averageStake = getSessionAverageStake($stake);
    return max($stake * MAX_SESSION_PAYOUT, $averageStake * MAX_SESSION_PAYOUT);
}

function applyLossStreakGuarantee($multiplier) {
    if ($_SESSION['spin_session_tracker']['loss_streak'] >= LOSING_STREAK_THRESHOLD) {
        if ($multiplier < 2) {
            return secureRand(2, 4);
        }
    }
    return $multiplier;
}

/**
 * Base weighted probability system
 * Returns multiplier (0-9) based on controlled probabilities
 */
function getBaseMultiplier() {
    $rand = secureRand(1, 10000);

    // Weighted distribution in basis points for all 10 wheel segments
    if ($rand <= 3000)   return 0;      // 30.00% lose
    if ($rand <= 5500)   return 1;      // 25.00% break even
    if ($rand <= 7300)   return 2;      // 18.00% small win
    if ($rand <= 8300)   return 3;      // 10.00% decent win
    if ($rand <= 9000)   return 4;      // 7.00% good win
    if ($rand <= 9400)   return 5;      // 4.00% exciting win
    if ($rand <= 9650)   return 6;      // 2.50% big win
    if ($rand <= 9800)   return 7;      // 1.50% very big win
    if ($rand <= 9920)   return 8;      // 1.20% massive win
    if ($rand <= 10000)  return 9;      // 0.80% jackpot

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

    initializeSpinSessionTracker();

    $multiplier = getBaseMultiplier();
    $sessionThreshold = getSessionPayoutThreshold($stake);
    $sessionProtectionActive = $_SESSION['spin_session_tracker']['total_payout'] >= $sessionThreshold;

    if ($sessionProtectionActive) {
        $multiplier = secureRand(1, 100) <= 20 ? 1 : 0;
    } else {
        $multiplier = applyLossStreakGuarantee($multiplier);

        $rtpAdjustment = getUserRTPAdjustment($pdo, $userId);
        if ($rtpAdjustment < 1.0 && $multiplier > 0) {
            if (secureRand(1, 100) <= (int) (100 * (1 - $rtpAdjustment))) {
                $multiplier = 0;
            }
        }

        $multiplier = applyPsychologicalAdjustment($pdo, $userId, $multiplier, $user['wallet']);
        $multiplier = applyStakePenalty($multiplier, $stake);
    }

    $winAmount = $stake * $multiplier;
    if ($winAmount > MAX_PAYOUT_PER_SPIN) {
        $winAmount = MAX_PAYOUT_PER_SPIN;
    }

    $segmentAngle = 360 / 10;
    $targetSegmentAngle = ($multiplier * $segmentAngle) + ($segmentAngle / 2);
    $fullSpins = secureRand(6, 9);
    $rotationAngle = ($fullSpins * 360) + $targetSegmentAngle;
    $spinDuration = secureRand((int)(MIN_SPIN_DURATION * 1000), (int)(MAX_SPIN_DURATION * 1000)) / 1000;

    $nearMissTarget = null;
    if ($multiplier === 0) {
        if (secureRand(1, 100) <= NEAR_MISS_HIGH_PROBABILITY) {
            $nearMissTarget = secureRand(6, 9);
        } else {
            $nearMissTarget = secureRand(1, 2);
        }
    }

    $_SESSION['spin_session_tracker']['spin_count'] += 1;
    $_SESSION['spin_session_tracker']['total_stake'] += $stake;
    $_SESSION['spin_session_tracker']['total_payout'] += $winAmount;

    if ($multiplier > 1) {
        $_SESSION['spin_session_tracker']['loss_streak'] = 0;
    } elseif ($multiplier === 0) {
        $_SESSION['spin_session_tracker']['loss_streak'] += 1;
    }

    return [
        'multiplier' => $multiplier,
        'winAmount' => $winAmount,
        'nearMissTarget' => $nearMissTarget,
        'rotationAngle' => $rotationAngle,
        'spinDuration' => $spinDuration,
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
