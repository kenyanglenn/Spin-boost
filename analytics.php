<?php
require_once 'db.php';
require_once 'spin_game_logic.php';

$pdo = getPDO();
$currentUser = getCurrentUser();

if (!$currentUser || $currentUser['plan'] === 'NONE') {
    header('Location: login');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Game Analytics - Educational Report</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .analytics-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .analytics-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 15px 0;
        }
        
        .stat-box {
            background: rgba(255,255,255,0.05);
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #7c3aed;
        }
        
        .stat-label {
            color: #c7d2fe;
            font-size: 0.9rem;
        }
        
        .stat-value {
            font-size: 1.3rem;
            font-weight: bold;
            color: #eef2ff;
        }
        
        .warning-box {
            background: rgba(248, 113, 113, 0.1);
            border-left: 4px solid #ef4444;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }
        
        .insight {
            padding: 10px 0;
            border-bottom: 1px solid var(--border);
        }
        
        .insight:last-child {
            border-bottom: none;
        }
        
        .mechanism {
            background: rgba(59, 130, 246, 0.1);
            padding: 10px;
            border-left: 4px solid #3b82f6;
            margin: 10px 0;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="app-shell">
        <header class="top-bar">
            <button class="hamburger" id="hamburger">☰</button>
            <div>
                <p class="eyebrow">Analytics</p>
                <h1>Game Mechanics Report</h1>
            </div>
            <div class="top-bar-right">
                <a href="spin" class="secondary-btn">Back to Game</a>
            </div>
        </header>
        
        <div class="analytics-container">
            <div class="analytics-card">
                <h2>📊 Your Game Statistics</h2>
                <?php 
                $analytics = getSpinAnalytics($pdo, $currentUser['id']);
                if ($analytics['total_spins'] > 0):
                ?>
                <div class="stat-grid">
                    <div class="stat-box">
                        <div class="stat-label">Total Spins</div>
                        <div class="stat-value"><?php echo $analytics['total_spins']; ?></div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-label">Total Staked</div>
                        <div class="stat-value">KES <?php echo number_format($analytics['total_stakes'], 2); ?></div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-label">Total Won</div>
                        <div class="stat-value">KES <?php echo number_format($analytics['total_winnings'], 2); ?></div>
                    </div>
                    <div class="stat-box" style="border-left-color: #ef4444;">
                        <div class="stat-label">Net Loss</div>
                        <div class="stat-value" style="color: #ef4444;">KES <?php echo number_format($analytics['total_stakes'] - $analytics['total_winnings'], 2); ?></div>
                    </div>
                    <div class="stat-box" style="border-left-color: #ef4444;">
                        <div class="stat-label">Your RTP</div>
                        <div class="stat-value" style="color: #ef4444;"><?php echo $analytics['actual_rtp']; ?>%</div>
                    </div>
                    <div class="stat-box" style="border-left-color: #ef4444;">
                        <div class="stat-label">House Edge</div>
                        <div class="stat-value" style="color: #ef4444;"><?php echo (100 - $analytics['actual_rtp']); ?>%</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-label">Loss Count</div>
                        <div class="stat-value"><?php echo $analytics['losses']; ?></div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-label">Win Rate</div>
                        <div class="stat-value"><?php echo round(($analytics['total_spins'] - $analytics['losses']) / $analytics['total_spins'] * 100, 1); ?>%</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-label">Max Win</div>
                        <div class="stat-value">KES <?php echo number_format($analytics['max_win'], 2); ?></div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-label">Avg Stake</div>
                        <div class="stat-value">KES <?php echo number_format($analytics['avg_stake'], 2); ?></div>
                    </div>
                </div>
                <?php else: ?>
                    <p style="color: #c7d2fe;">You haven't spun yet. Play some spins to see your analytics.</p>
                <?php endif; ?>
            </div>
            
            <div class="analytics-card">
                <h2>⚠️ The Game Mechanics (Disadvantages Explained)</h2>
                
                <div class="warning-box">
                    <strong>🎯 How the House Wins (Every Time)</strong>
                    <p>This game uses sophisticated mathematical systems to ensure the house always profits long-term. Your actual win probability is NOT what it appears.</p>
                </div>
                
                <h3>🔧 Mechanisms Working Against You:</h3>
                
                <div class="mechanism">
                    <strong>1. Weighted Probabilities</strong>
                    <p>The wheel appears random, but probabilities are heavily weighted:</p>
                    <ul>
                        <li>60% chance of losing everything (x0 = no win)</li>
                        <li>Only 0.01% chance of jackpot (x9)</li>
                        <li>Most wins are tiny (x1, x2) with rare big wins</li>
                    </ul>
                </div>
                
                <div class="mechanism">
                    <strong>2. RTP Control (Return to Player)</strong>
                    <p>The system automatically adjusts to maintain a target 55% RTP:</p>
                    <ul>
                        <li>If you're winning too much, your odds suddenly worsen</li>
                        <li>If you're losing excessively, small wins are forced to keep you playing</li>
                        <li>You ALWAYS lose ~45% of stakes in the long run</li>
                    </ul>
                </div>
                
                <div class="mechanism">
                    <strong>3. Losing Streak Breakers (Addiction Loop)</strong>
                    <p>After 3-5 losses, you're guaranteed a small win:</p>
                    <ul>
                        <li>Creates false hope: "I'm on a winning streak now!"</li>
                        <li>Encourages you to keep betting</li>
                        <li>The win is usually smaller than losses, keeping house ahead</li>
                    </ul>
                </div>
                
                <div class="mechanism">
                    <strong>4. Low Balance Boost (Trap Door)</strong>
                    <p>When your wallet is nearly empty:</p>
                    <ul>
                        <li>50% chance of winning a small amount to re-engage you</li>
                        <li>Lures you into "just one more spin"</li>
                        <li>You end up spending more chasing that boost</li>
                    </ul>
                </div>
                
                <div class="mechanism">
                    <strong>5. High Stake Penalty (The Trap)</strong>
                    <p>If you bet more (e.g., 100+ KES):</p>
                    <ul>
                        <li>Your odds for high multipliers DROP significantly</li>
                        <li>Larger bets = worse win probabilities</li>
                        <li>House exploits ambitious players</li>
                    </ul>
                </div>
                
                <div class="mechanism">
                    <strong>6. Near-Miss Effects (Visual Illusion)</strong>
                    <p>Even when you lose (x0):</p>
                    <ul>
                        <li>40% of losses are shown as "near-misses" (wheel almost landed on x8 or x9)</li>
                        <li>Your brain registers this as "I almost won!"</li>
                        <li>Psychological trigger to play again</li>
                    </ul>
                </div>
                
                <div class="mechanism">
                    <strong>7. Payout Caps (Your Win Ceiling)</strong>
                    <p>Maximum win per spin is capped at 500 KES:</p>
                    <ul>
                        <li>Even if you hit high multipliers, wins are limited</li>
                        <li>Losses are always full (no caps)</li>
                        <li>Asymmetric risk favors house</li>
                    </ul>
                </div>
                
                <div class="mechanism">
                    <strong>8. New User Honeymoon</strong>
                    <p>First week after registration:</p>
                    <ul>
                        <li>Slightly better odds to hook you on the game</li>
                        <li>Once you're addicted, odds revert to normal</li>
                        <li>Classic predatory game design</li>
                    </ul>
                </div>
            </div>
            
            <div class="analytics-card">
                <h2>💡 Key Insights</h2>
                <div class="insight">
                    <strong>Math Never Lies:</strong> Over 100+ spins, you WILL lose 35-50% of money. The odds are rigged mathematically.
                </div>
                <div class="insight">
                    <strong>Illusion of Control:</strong> The wheel feels random but it's completely determined by hidden code. Your "luck" doesn't exist.
                </div>
                <div class="insight">
                    <strong>Addiction by Design:</strong> Streak breaks, low balance boosts, and near-miss effects are all psychological hooks to keep you playing.
                </div>
                <div class="insight">
                    <strong>The House Always Wins:</strong> Even when you win, the house wins bigger. Over time, the math is always in their favor.
                </div>
                <div class="insight">
                    <strong>Your Expected Loss:</strong> If you bet 1000 KES total, expect to walk away with ~500 KES. That's not luck—it's the system.
                </div>
            </div>
            
            <div class="analytics-card">
                <h2>📌 Why This Matters</h2>
                <p>
                    This project demonstrates how modern gambling/game mechanics are designed to maximize profit extraction 
                    while making users feel like they're having fun. The same techniques are used in:
                </p>
                <ul>
                    <li>Mobile casino apps</li>
                    <li>Loot boxes in video games</li>
                    <li>Sports betting apps</li>
                    <li>Gacha game mechanics</li>
                </ul>
                <p>
                    Understanding these mechanics helps you recognize when you're being manipulated by game design.
                </p>
            </div>
        </div>
    </div>

    <script src="script.js"></script>
</body>
</html>
