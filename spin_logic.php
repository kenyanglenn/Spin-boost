<?php
require_once 'db.php';
require_once 'spin_game_logic.php';

$pdo = getPDO();
header('Content-Type: application/json');
$currentUser = getCurrentUser();
if (!$currentUser) {
    echo json_encode(['success' => false, 'message' => 'No active user session.']);
    exit;
}

if ($currentUser['plan'] === 'NONE') {
    echo json_encode(['success' => false, 'message' => 'Please select a plan before using the dashboard.']);
    exit;
}

$planLimits = getPlanLimits($currentUser['plan']);
$spinCount = getDailyUsage($pdo, $currentUser['id'], 'spin');
$puzzleCount = getDailyUsage($pdo, $currentUser['id'], 'puzzle');

$stake = filter_input(INPUT_POST, 'stake', FILTER_VALIDATE_FLOAT);
if ($stake === false || $stake <= 0) {
    echo json_encode(['success' => false, 'message' => 'Enter a valid stake above zero.']);
    exit;
}

if ($spinCount >= $planLimits['spins']) {
    echo json_encode([
        'success' => false,
        'message' => 'Daily spin limit reached for your plan.',
        'spinCount' => $spinCount,
        'spinLimit' => $planLimits['spins'],
        'puzzleCount' => $puzzleCount,
        'puzzleLimit' => $planLimits['puzzles'],
    ]);
    exit;
}

if ($stake > $currentUser['wallet']) {
    echo json_encode(['success' => false, 'message' => 'Insufficient wallet balance.']);
    exit;
}

// Use the comprehensive game logic system
$spinResult = getSpinResult($pdo, $currentUser['id'], $stake);

if (isset($spinResult['error'])) {
    echo json_encode(['success' => false, 'message' => $spinResult['error']]);
    exit;
}

$multiplier = $spinResult['multiplier'];
$winAmount = $spinResult['winAmount'];
$rotationAngle = $spinResult['rotationAngle'];
$spinDuration = $spinResult['spinDuration'];
$metadata = $spinResult['metadata'];
$newWallet = round($currentUser['wallet'] - $stake + $winAmount, 2);

// Map multiplier to segment label
$labels = ['x0', 'x1', 'x2', 'x3', 'x4', 'x5', 'x6', 'x7', 'x8', 'x9'];
$segmentLabel = $labels[$multiplier] ?? 'x0';
$segmentIndex = $multiplier;

$pdo->beginTransaction();
try {
    updateWallet($pdo, $currentUser['id'], $newWallet);
    recordSpinResult($pdo, $currentUser['id'], $stake, $multiplier, $winAmount, $metadata);
    $pdo->commit();
} catch (Exception $ex) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Server error saving spin.']);
    exit;
}

$spinCount = getDailyUsage($pdo, $currentUser['id'], 'spin');
$puzzleCount = getDailyUsage($pdo, $currentUser['id'], 'puzzle');

echo json_encode([
    'success' => true,
    'segmentIndex' => $segmentIndex,
    'segmentLabel' => $segmentLabel,
    'multiplier' => $multiplier,
    'winAmount' => $winAmount,
    'wallet' => $newWallet,
    'spinDuration' => $spinDuration,
    'rotationAngle' => $rotationAngle,
    'spinCount' => $spinCount + 1,
    'spinLimit' => $planLimits['spins'],
    'puzzleCount' => $puzzleCount,
    'puzzleLimit' => $planLimits['puzzles'],
    'message' => $multiplier > 0 ? 'Congratulations! You won ' . number_format($winAmount, 2) . ' KES.' : 'No win this round. House edge wins.',
]);
