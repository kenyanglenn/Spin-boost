<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection settings - Railway compatible
const DB_HOST = getenv('MYSQLHOST') ?: '127.0.0.1';
const DB_NAME = getenv('MYSQLDATABASE') ?: 'spinboost';
const DB_USER = getenv('MYSQLUSER') ?: 'root';
const DB_PASS = getenv('MYSQLPASSWORD') ?: '';
const DB_PORT = getenv('MYSQLPORT') ?: '3306';

function getPDO() {
    static $pdo;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    }
    return $pdo;
}

function setFlashMessage($type, $text) {
    $_SESSION['flash'] = ['type' => $type, 'text' => $text];
}

function getFlashMessage() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function getUserById($id) {
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function getCurrentUser() {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    return getUserById($_SESSION['user_id']);
}

function generateReferralCode() {
    return bin2hex(random_bytes(10));
}

function registerUser($username, $phone, $password, $referred_by = null) {
    $pdo = getPDO();
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $referralCode = generateReferralCode();
    
    $stmt = $pdo->prepare('INSERT INTO users (username, phone, password, referral_code, referred_by, plan) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$username, $phone, $hashedPassword, $referralCode, $referred_by, 'NONE']);
    return $pdo->lastInsertId();
}

function loginUser($usernameOrPhone, $password) {
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? OR phone = ? LIMIT 1');
    $stmt->execute([$usernameOrPhone, $usernameOrPhone]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        return $user;
    }
    return false;
}

function logoutUser() {
    unset($_SESSION['user_id']);
}

function updateUserPlan($userId, $plan) {
    $pdo = getPDO();
    $stmt = $pdo->prepare('UPDATE users SET plan = ? WHERE id = ?');
    $stmt->execute([$plan, $userId]);
}

function initiateMpesaPayment($phone, $amount, $description = 'Spin Boost plan purchase') {
    // Placeholder for MPESA API integration.
    // Replace this with the actual API call to send the STK push to the user's phone number.
    return [
        'success' => true,
        'message' => 'MPESA prompt sent to ' . htmlspecialchars($phone) . ' for ' . number_format($amount, 2) . ' KES.',
        'checkoutRequestID' => 'MPESA-' . bin2hex(random_bytes(5)),
    ];
}

function addWallet($pdo, $userId, $amount) {
    $stmt = $pdo->prepare('UPDATE users SET wallet = wallet + ? WHERE id = ?');
    $stmt->execute([$amount, $userId]);
}

function getReferrerId($referralCode) {
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT id FROM users WHERE referral_code = ? LIMIT 1');
    $stmt->execute([$referralCode]);
    $row = $stmt->fetch();
    return $row ? $row['id'] : null;
}

function addReferralReward($referrerId, $plan) {
    $rewards = [
        'REGULAR' => 10,
        'PREMIUM' => 25,
        'PREMIUM+' => 50
    ];
    if (isset($rewards[$plan])) {
        $pdo = getPDO();
        addWallet($pdo, $referrerId, $rewards[$plan]);
    }
}

function getUserByReferralCode($code) {
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE referral_code = ? LIMIT 1');
    $stmt->execute([$code]);
    return $stmt->fetch();
}

function getPlanLimits($planName) {
    $plans = [
        'NONE' => ['spins' => 0, 'puzzles' => 0, 'cost' => 0],
        'REGULAR' => ['spins' => 5, 'puzzles' => 3, 'cost' => 20],
        'PREMIUM' => ['spins' => 20, 'puzzles' => 25, 'cost' => 50],
        'PREMIUM+' => ['spins' => 9999, 'puzzles' => 9999, 'cost' => 100],
    ];
    return $plans[strtoupper($planName)] ?? $plans['NONE'];
}

function getDailyUsage($pdo, $userId, $type) {
    $today = date('Y-m-d');
    if ($type === 'spin') {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM spins WHERE user_id = ? AND DATE(created_at) = ?');
    } else {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM word_puzzles WHERE user_id = ? AND DATE(created_at) = ?');
    }
    $stmt->execute([$userId, $today]);
    return (int) $stmt->fetchColumn();
}

function updateWallet($pdo, $userId, $amount) {
    $stmt = $pdo->prepare('UPDATE users SET wallet = ? WHERE id = ?');
    $stmt->execute([$amount, $userId]);
}

function recordSpin($pdo, $userId, $stake, $multiplier, $winAmount) {
    $stmt = $pdo->prepare('INSERT INTO spins (user_id, stake, multiplier, win_amount, created_at) VALUES (?, ?, ?, ?, NOW())');
    $stmt->execute([$userId, $stake, $multiplier, $winAmount]);
}

function recordPuzzle($pdo, $userId, $word, $scrambled, $userAnswer, $stake, $reward, $difficulty, $status) {
    $stmt = $pdo->prepare('INSERT INTO word_puzzles (user_id, word, scrambled, user_answer, stake, reward, difficulty, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())');
    $stmt->execute([$userId, $word, $scrambled, $userAnswer, $stake, $reward, $difficulty, $status]);
}

function createReferralCode($seed) {
    return substr(strtoupper(preg_replace('/[^A-Z0-9]/', '', $seed)) . bin2hex(random_bytes(2)), 0, 8);
}

function getReferrals($userId) {
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT id, username, plan FROM users WHERE referred_by = (SELECT referral_code FROM users WHERE id = ?) ORDER BY created_at DESC');
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function getReferralEarnings($userId) {
    $referrals = getReferrals($userId);
    $totalEarnings = 0;
    $earnings = [];
    foreach ($referrals as $ref) {
        if ($ref['plan'] !== 'NONE') {
            $reward = 0;
            if ($ref['plan'] === 'PREMIUM+') {
                $reward = 50;
            } elseif ($ref['plan'] === 'PREMIUM') {
                $reward = 25;
            } else {
                $reward = 10;
            }
            $totalEarnings += $reward;
            $earnings[] = [
                'username' => $ref['username'],
                'plan' => $ref['plan'],
                'earnings' => $reward
            ];
        } else {
            $earnings[] = [
                'username' => $ref['username'],
                'plan' => 'NONE',
                'earnings' => 0
            ];
        }
    }
    return ['total' => $totalEarnings, 'details' => $earnings];
}

function changeUserPlan($userId, $newPlan) {
    $pdo = getPDO();
    $stmt = $pdo->prepare('UPDATE users SET plan = ? WHERE id = ?');
    $stmt->execute([$newPlan, $userId]);
}

function withdrawMoney($userId, $amount) {
    if ($amount < 5) {
        return ['success' => false, 'message' => 'Minimum withdrawal is 5 KES.'];
    }
    $pdo = getPDO();
    $user = getUserById($userId);
    if ($user['wallet'] < $amount) {
        return ['success' => false, 'message' => 'Insufficient wallet balance.'];
    }
    addWallet($pdo, $userId, -$amount);
    // Record withdrawal
    $stmt = $pdo->prepare('INSERT INTO withdrawals (user_id, amount, status) VALUES (?, ?, ?)');
    $stmt->execute([$userId, $amount, 'pending']);
    return ['success' => true, 'message' => 'Withdrawal of ' . number_format($amount, 2) . ' KES initiated.'];
}

function getWithdrawalHistory($userId) {
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT amount, status, created_at FROM withdrawals WHERE user_id = ? ORDER BY created_at DESC');
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

/**
 * Example database schema for this component.
 * Run these queries once in your MySQL database before using the app.
 *
 * CREATE TABLE users (
 *   id INT PRIMARY KEY AUTO_INCREMENT,
 *   username VARCHAR(100) NOT NULL,
 *   phone VARCHAR(20) NOT NULL,
 *   password VARCHAR(255) NOT NULL,
 *   wallet DECIMAL(10,2) NOT NULL DEFAULT 0,
 *   plan VARCHAR(30) NOT NULL DEFAULT 'REGULAR',
 *   referral_code VARCHAR(20) NOT NULL UNIQUE,
 *   referred_by VARCHAR(20) DEFAULT NULL,
 *   created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
 * );
 *
 * CREATE TABLE spins (
 *   id INT PRIMARY KEY AUTO_INCREMENT,
 *   user_id INT NOT NULL,
 *   stake DECIMAL(10,2) NOT NULL,
 *   multiplier DECIMAL(5,2) NOT NULL,
 *   win_amount DECIMAL(10,2) NOT NULL,
 *   created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 *   FOREIGN KEY (user_id) REFERENCES users(id)
 * );
 *
 * CREATE TABLE word_puzzles (
 *   id INT PRIMARY KEY AUTO_INCREMENT,
 *   user_id INT NOT NULL,
 *   word VARCHAR(50) NOT NULL,
 *   user_answer VARCHAR(50) NOT NULL,
 *   stake DECIMAL(10,2) NOT NULL,
 *   reward DECIMAL(10,2) NOT NULL,
 *   status VARCHAR(10) NOT NULL,
 *   created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 *   FOREIGN KEY (user_id) REFERENCES users(id)
 * );
 */
