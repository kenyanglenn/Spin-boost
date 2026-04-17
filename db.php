<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection settings
$DB_HOST = getenv('MYSQLHOST') ?: '127.0.0.1';
$DB_NAME = getenv('MYSQLDATABASE') ?: 'spinboost';
$DB_USER = getenv('MYSQLUSER') ?: 'root';
$DB_PASS = getenv('MYSQLPASSWORD') ?: '';
$DB_PORT = getenv('MYSQLPORT') ?: '3306';
function getPDO() {
    global $DB_HOST, $DB_NAME, $DB_USER, $DB_PASS, $DB_PORT;
    static $pdo;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . $DB_HOST . ';port=' . $DB_PORT . ';dbname=' . $DB_NAME . ';charset=utf8mb4';
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options);
        ensureDepositSchema($pdo);
        ensureWithdrawalSchema($pdo);
        ensureUserSchema($pdo);
    }
    return $pdo;
}

function ensureDepositSchema(PDO $pdo) {
    try {
        $tableExists = $pdo->query("SHOW TABLES LIKE 'deposits'")->fetch();
        if (!$tableExists) {
            return;
        }

        $column = $pdo->query("SHOW COLUMNS FROM deposits LIKE 'plan_id'")->fetch();
        if (!$column) {
            $pdo->exec("ALTER TABLE deposits ADD COLUMN plan_id VARCHAR(50) NULL COMMENT 'Plan type if this is for plan purchase (REGULAR, PREMIUM, PREMIUM+)' AFTER amount");
        }

        $column = $pdo->query("SHOW COLUMNS FROM deposits LIKE 'admin_notes'")->fetch();
        if (!$column) {
            $pdo->exec("ALTER TABLE deposits ADD COLUMN admin_notes TEXT COMMENT 'Admin notes for approval or rejection' AFTER status");
        }

        $column = $pdo->query("SHOW COLUMNS FROM deposits LIKE 'approved_by'")->fetch();
        if (!$column) {
            $pdo->exec("ALTER TABLE deposits ADD COLUMN approved_by INT NULL COMMENT 'Admin user ID who approved/rejected' AFTER admin_notes");
        }

        $column = $pdo->query("SHOW COLUMNS FROM deposits LIKE 'approved_at'")->fetch();
        if (!$column) {
            $pdo->exec("ALTER TABLE deposits ADD COLUMN approved_at TIMESTAMP NULL COMMENT 'When admin approved or rejected' AFTER approved_by");
        }

        $column = $pdo->query("SHOW COLUMNS FROM deposits LIKE 'payment_phone'")->fetch();
        if (!$column) {
            $pdo->exec("ALTER TABLE deposits ADD COLUMN payment_phone VARCHAR(20) NULL COMMENT 'Phone number provided by user for the payment request' AFTER plan_id");
        }

        $column = $pdo->query("SHOW COLUMNS FROM deposits LIKE 'status'")->fetch();
        if ($column && strpos($column['Type'], "enum('pending','approved','rejected')") === false) {
            $pdo->exec("UPDATE deposits SET status = 'approved' WHERE status = 'completed'");
            $pdo->exec("UPDATE deposits SET status = 'rejected' WHERE status IN ('failed','expired')");
            $pdo->exec("ALTER TABLE deposits MODIFY COLUMN status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending'");
        }
    } catch (Exception $e) {
        error_log('Deposit schema migration failed: ' . $e->getMessage());
    }
}

function ensureWithdrawalSchema(PDO $pdo) {
    try {
        $tableExists = $pdo->query("SHOW TABLES LIKE 'withdrawals'")->fetch();
        if (!$tableExists) {
            return;
        }

        $column = $pdo->query("SHOW COLUMNS FROM withdrawals LIKE 'phone'")->fetch();
        if (!$column) {
            $pdo->exec("ALTER TABLE withdrawals ADD COLUMN phone VARCHAR(20) NULL AFTER amount");
        }

        $column = $pdo->query("SHOW COLUMNS FROM withdrawals LIKE 'admin_notes'")->fetch();
        if (!$column) {
            $pdo->exec("ALTER TABLE withdrawals ADD COLUMN admin_notes TEXT NULL AFTER status");
        }

        $column = $pdo->query("SHOW COLUMNS FROM withdrawals LIKE 'processed_by'")->fetch();
        if (!$column) {
            $pdo->exec("ALTER TABLE withdrawals ADD COLUMN processed_by INT NULL AFTER admin_notes");
        }

        $column = $pdo->query("SHOW COLUMNS FROM withdrawals LIKE 'processed_at'")->fetch();
        if (!$column) {
            $pdo->exec("ALTER TABLE withdrawals ADD COLUMN processed_at TIMESTAMP NULL AFTER processed_by");
        }
    } catch (Exception $e) {
        error_log('Withdrawal schema migration failed: ' . $e->getMessage());
    }
}

function ensureUserSchema(PDO $pdo) {
    try {
        $column = $pdo->query("SHOW COLUMNS FROM users LIKE 'is_admin'")->fetch();
        if (!$column) {
            $pdo->exec("ALTER TABLE users ADD COLUMN is_admin TINYINT(1) NOT NULL DEFAULT 0 AFTER plan");
        }
    } catch (Exception $e) {
        error_log('User schema migration failed: ' . $e->getMessage());
    }
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

function validateLogin($usernameOrPhone, $password) {
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? OR phone = ? LIMIT 1');
    $stmt->execute([$usernameOrPhone, $usernameOrPhone]);
    $user = $stmt->fetch();
    
    if (!$user) {
        return ['success' => false, 'message' => 'Username or phone not found.', 'type' => 'username'];
    }
    
    if (!password_verify($password, $user['password'])) {
        return ['success' => false, 'message' => 'Password is incorrect.', 'type' => 'password'];
    }
    
    return ['success' => true, 'user' => $user];
}

function logoutUser() {
    // Clear all session data to prevent stale data
    session_unset();
    session_destroy();
    session_start();
}

function getCurrentAdmin() {
    if (!isset($_SESSION['admin_id'])) {
        return null;
    }
    return getUserById($_SESSION['admin_id']);
}

function countAdmins() {
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE is_admin = 1');
    $stmt->execute();
    return (int) $stmt->fetchColumn();
}

function createAdminUser($username, $phone, $password) {
    if (countAdmins() >= 5) {
        throw new Exception('Maximum number of admins reached.');
    }

    $pdo = getPDO();
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $referralCode = generateReferralCode();

    $stmt = $pdo->prepare('INSERT INTO users (username, phone, password, referral_code, referred_by, plan, is_admin) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$username, $phone, $hashedPassword, $referralCode, null, 'NONE', 1]);
    return $pdo->lastInsertId();
}

function isAdminUser($userId) {
    $user = getUserById($userId);
    return $user && !empty($user['is_admin']);
}

function registerUser($username, $phone, $password, $referred_by = null, $isAdmin = 0) {
    $pdo = getPDO();
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $referralCode = generateReferralCode();
    
    $stmt = $pdo->prepare('INSERT INTO users (username, phone, password, referral_code, referred_by, plan, is_admin) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$username, $phone, $hashedPassword, $referralCode, $referred_by, 'NONE', $isAdmin]);
    return $pdo->lastInsertId();
}

function updateUserPlan($userId, $plan) {
    $pdo = getPDO();
    $stmt = $pdo->prepare('UPDATE users SET plan = ? WHERE id = ?');
    $stmt->execute([$plan, $userId]);
}

/**
 * MANUAL APPROVAL SYSTEM
 * Creates a pending deposit/plan request for manual admin approval
 */
function createPendingDeposit($userId, $amount, $planId = null, $paymentPhone = null) {
    $pdo = getPDO();
    $stmt = $pdo->prepare(
        'INSERT INTO deposits (user_id, amount, plan_id, payment_phone, provider, provider_reference, your_reference, verification_timestamp, status) 
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$userId, $amount, $planId, $paymentPhone, null, null, null, null, 'pending']);
    return $pdo->lastInsertId();
}

function getDepositHistory($userId) {
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT * FROM deposits WHERE user_id = ? ORDER BY created_at DESC');
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function getPendingWithdrawals() {
    $pdo = getPDO();
    $stmt = $pdo->prepare(
        'SELECT w.*, u.username, u.phone AS user_phone, u.plan
         FROM withdrawals w
         JOIN users u ON w.user_id = u.id
         WHERE w.status = ?
         ORDER BY w.created_at DESC'
    );
    $stmt->execute(['pending']);
    return $stmt->fetchAll();
}

function approveWithdrawal($withdrawalId, $adminUserId, $adminNotes = '') {
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT * FROM withdrawals WHERE id = ? LIMIT 1');
    $stmt->execute([$withdrawalId]);
    $withdrawal = $stmt->fetch();
    if (!$withdrawal || $withdrawal['status'] !== 'pending') {
        return ['success' => false, 'message' => 'Withdrawal not found or not pending'];
    }

    $stmt = $pdo->prepare(
        'UPDATE withdrawals SET status = ?, admin_notes = ?, processed_by = ?, processed_at = NOW() WHERE id = ?'
    );
    $stmt->execute(['completed', $adminNotes, $adminUserId, $withdrawalId]);

    return ['success' => true, 'message' => 'Withdrawal approved successfully'];
}

function rejectWithdrawal($withdrawalId, $adminUserId, $adminNotes = '') {
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT * FROM withdrawals WHERE id = ? LIMIT 1');
    $stmt->execute([$withdrawalId]);
    $withdrawal = $stmt->fetch();
    if (!$withdrawal || $withdrawal['status'] !== 'pending') {
        return ['success' => false, 'message' => 'Withdrawal not found or not pending'];
    }

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare(
            'UPDATE withdrawals SET status = ?, admin_notes = ?, processed_by = ?, processed_at = NOW() WHERE id = ?'
        );
        $stmt->execute(['failed', $adminNotes, $adminUserId, $withdrawalId]);

        addWallet($pdo, $withdrawal['user_id'], $withdrawal['amount']);
        $pdo->commit();
        return ['success' => true, 'message' => 'Withdrawal rejected and funds returned to wallet'];
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('Withdrawal rejection error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Unable to reject withdrawal at this time'];
    }
}

/**
 * Check if user has an approved deposit
 * Returns true if user has at least one approved deposit, false otherwise
 */
function hasApprovedDeposit($userId) {
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM deposits WHERE user_id = ? AND status = ?');
    $stmt->execute([$userId, 'approved']);
    return (int) $stmt->fetchColumn() > 0;
}

/**
 * Get user's pending deposit approval status
 * Returns the pending deposit record if exists
 */
function getPendingDeposit($userId) {
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT * FROM deposits WHERE user_id = ? AND status = ? ORDER BY created_at DESC LIMIT 1');
    $stmt->execute([$userId, 'pending']);
    return $stmt->fetch();
}

/**
 * Get all pending deposits for admin review
 */
function getPendingDeposits() {
    $pdo = getPDO();
    $stmt = $pdo->prepare(
        'SELECT d.*, u.username, u.phone, u.plan 
         FROM deposits d 
         JOIN users u ON d.user_id = u.id 
         WHERE d.status = ? 
         ORDER BY d.created_at DESC'
    );
    $stmt->execute(['pending']);
    return $stmt->fetchAll();
}

/**
 * Approve a deposit and activate user's plan or wallet
 */
function approveDeposit($depositId, $adminUserId, $adminNotes = '') {
    $pdo = getPDO();
    
    // Get deposit details
    $stmt = $pdo->prepare('SELECT * FROM deposits WHERE id = ?');
    $stmt->execute([$depositId]);
    $deposit = $stmt->fetch();
    
    if (!$deposit) {
        return ['success' => false, 'message' => 'Deposit not found'];
    }
    
    // Begin transaction
    $pdo->beginTransaction();
    try {
        // Update deposit status
        $stmt = $pdo->prepare(
            'UPDATE deposits SET status = ?, approved_by = ?, approved_at = NOW(), admin_notes = ? WHERE id = ?'
        );
        $stmt->execute(['approved', $adminUserId, $adminNotes, $depositId]);
        
        // If this was a plan purchase, activate the plan and expire session
        if ($deposit['plan_id']) {
            $stmt = $pdo->prepare('UPDATE users SET plan = ? WHERE id = ?');
            $stmt->execute([$deposit['plan_id'], $deposit['user_id']]);
            
            // Add referral bonus if applicable
            $user = getUserById($deposit['user_id']);
            if ($user['referred_by']) {
                $referrerId = getReferrerId($user['referred_by']);
                if ($referrerId) {
                    addReferralReward($referrerId, $deposit['plan_id']);
                }
            }
            
            // Note: Plan change notification is now handled by checking plan changes in spin.php
        } else {
            // If it's a wallet deposit, add to wallet
            addWallet($pdo, $deposit['user_id'], $deposit['amount']);
        }
        
        $pdo->commit();
        return ['success' => true, 'message' => 'Deposit approved successfully'];
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('Deposit approval error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Error processing approval'];
    }
}

/**
 * Reject a deposit request
 */
function rejectDeposit($depositId, $adminUserId, $adminNotes = '') {
    $pdo = getPDO();
    
    $stmt = $pdo->prepare(
        'UPDATE deposits SET status = ?, approved_by = ?, approved_at = NOW(), admin_notes = ? WHERE id = ?'
    );
    $stmt->execute(['rejected', $adminUserId, $adminNotes, $depositId]);
    
    return ['success' => true, 'message' => 'Deposit rejected'];
}

/**
 * Get approval status for a user (for display on waiting page)
 */
function getApprovalStatus($userId) {
    $pdo = getPDO();
    
    // Check if user has any approved deposit
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM deposits WHERE user_id = ? AND status = ?');
    $stmt->execute([$userId, 'approved']);
    if ((int) $stmt->fetchColumn() > 0) {
        return ['status' => 'approved', 'message' => 'Your deposit has been approved!'];
    }
    
    // Check for pending deposit
    $stmt = $pdo->prepare('SELECT * FROM deposits WHERE user_id = ? AND status = ? ORDER BY created_at DESC LIMIT 1');
    $stmt->execute([$userId, 'pending']);
    $pending = $stmt->fetch();
    if ($pending) {
        return ['status' => 'pending', 'message' => 'Your request is under review'];
    }
    
    // Check for rejected deposit
    $stmt = $pdo->prepare('SELECT * FROM deposits WHERE user_id = ? AND status = ? ORDER BY created_at DESC LIMIT 1');
    $stmt->execute([$userId, 'rejected']);
    $rejected = $stmt->fetch();
    if ($rejected) {
        return [
            'status' => 'rejected', 
            'message' => 'Your request was rejected. Reason: ' . ($rejected['admin_notes'] ?: 'No reason provided')
        ];
    }
    
    return ['status' => 'no_request', 'message' => 'No deposit request found'];
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

// Admin functions for paginated views
function getAllUsers($page = 1, $limit = 10) {
    $pdo = getPDO();
    $offset = ($page - 1) * $limit;
    $stmt = $pdo->prepare(
        'SELECT id, username, phone, wallet, plan, created_at, is_admin FROM users ORDER BY created_at DESC LIMIT ? OFFSET ?'
    );
    $stmt->execute([$limit, $offset]);
    return $stmt->fetchAll();
}

function getTotalUsers() {
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM users');
    $stmt->execute();
    return (int) $stmt->fetchColumn();
}

function getTopSpinWinners($page = 1, $limit = 10) {
    $pdo = getPDO();
    $offset = ($page - 1) * $limit;
    $stmt = $pdo->prepare(
        'SELECT u.username, u.plan, 
                MAX(s.win_amount) as highest_single_spin,
                COUNT(s.id) as lifetime_spins,
                SUM(s.stake) as total_staked
         FROM users u
         LEFT JOIN spins s ON u.id = s.user_id
         GROUP BY u.id, u.username, u.plan
         HAVING lifetime_spins > 0
         ORDER BY highest_single_spin DESC
         LIMIT ? OFFSET ?'
    );
    $stmt->execute([$limit, $offset]);
    return $stmt->fetchAll();
}

function getTopPuzzleWinners($page = 1, $limit = 10) {
    $pdo = getPDO();
    $offset = ($page - 1) * $limit;
    $stmt = $pdo->prepare(
        'SELECT u.username, u.plan,
                MAX(p.reward) as highest_single_puzzle,
                COUNT(p.id) as lifetime_puzzles,
                SUM(p.stake) as total_staked
         FROM users u
         LEFT JOIN word_puzzles p ON u.id = p.user_id
         WHERE p.status = "win"
         GROUP BY u.id, u.username, u.plan
         HAVING lifetime_puzzles > 0
         ORDER BY highest_single_puzzle DESC
         LIMIT ? OFFSET ?'
    );
    $stmt->execute([$limit, $offset]);
    return $stmt->fetchAll();
}

function getAllDeposits($page = 1, $limit = 10) {
    $pdo = getPDO();
    $offset = ($page - 1) * $limit;
    $stmt = $pdo->prepare(
        'SELECT d.*, u.username, u.phone as user_phone
         FROM deposits d
         JOIN users u ON d.user_id = u.id
         ORDER BY d.created_at DESC
         LIMIT ? OFFSET ?'
    );
    $stmt->execute([$limit, $offset]);
    return $stmt->fetchAll();
}

function getTotalDeposits() {
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM deposits');
    $stmt->execute();
    return (int) $stmt->fetchColumn();
}

function getAllWithdrawals($page = 1, $limit = 10) {
    $pdo = getPDO();
    $offset = ($page - 1) * $limit;
    $stmt = $pdo->prepare(
        'SELECT w.*, u.username, u.phone as user_phone
         FROM withdrawals w
         JOIN users u ON w.user_id = u.id
         ORDER BY w.created_at DESC
         LIMIT ? OFFSET ?'
    );
    $stmt->execute([$limit, $offset]);
    return $stmt->fetchAll();
}

function getTotalWithdrawals() {
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM withdrawals');
    $stmt->execute();
    return (int) $stmt->fetchColumn();
}

function getPendingDepositsPaginated($page = 1, $limit = 10) {
    $pdo = getPDO();
    $offset = ($page - 1) * $limit;
    $stmt = $pdo->prepare(
        'SELECT d.*, u.username, u.phone, u.plan 
         FROM deposits d 
         JOIN users u ON d.user_id = u.id 
         WHERE d.status = ? 
         ORDER BY d.created_at DESC
         LIMIT ? OFFSET ?'
    );
    $stmt->execute(['pending', $limit, $offset]);
    return $stmt->fetchAll();
}

function getPendingWithdrawalsPaginated($page = 1, $limit = 10) {
    $pdo = getPDO();
    $offset = ($page - 1) * $limit;
    $stmt = $pdo->prepare(
        'SELECT w.*, u.username, u.phone AS user_phone, u.plan
         FROM withdrawals w
         JOIN users u ON w.user_id = u.id
         WHERE w.status = ?
         ORDER BY w.created_at DESC
         LIMIT ? OFFSET ?'
    );
    $stmt->execute(['pending', $limit, $offset]);
    return $stmt->fetchAll();
}

function getTotalPendingDeposits() {
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM deposits WHERE status = ?');
    $stmt->execute(['pending']);
    return (int) $stmt->fetchColumn();
}

function getTotalPendingWithdrawals() {
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM withdrawals WHERE status = ?');
    $stmt->execute(['pending']);
    return (int) $stmt->fetchColumn();
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

function withdrawMoney($userId, $amount, $phone) {
    if ($amount < 5) {
        return ['success' => false, 'message' => 'Minimum withdrawal is 5 KES.'];
    }
    if (empty($phone)) {
        return ['success' => false, 'message' => 'Phone number is required for withdrawal.' ];
    }
    $pdo = getPDO();
    $user = getUserById($userId);
    if ($user['wallet'] < $amount) {
        return ['success' => false, 'message' => 'Insufficient wallet balance.'];
    }
    addWallet($pdo, $userId, -$amount);
    // Record withdrawal
    $stmt = $pdo->prepare('INSERT INTO withdrawals (user_id, amount, phone, status) VALUES (?, ?, ?, ?)');
    $stmt->execute([$userId, $amount, $phone, 'pending']);
    return ['success' => true, 'message' => 'Withdrawal of ' . number_format($amount, 2) . ' KES initiated and is pending admin approval.'];
}

function getWithdrawalHistory($userId) {
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT amount, phone, status, created_at FROM withdrawals WHERE user_id = ? ORDER BY created_at DESC');
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function getTotalDeposited($userId) {
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT SUM(amount) as total FROM deposits WHERE user_id = ? AND status = "approved"');
    $stmt->execute([$userId]);
    $result = $stmt->fetch();
    return $result['total'] ?? 0;
}

function getTotalWinnings($userId) {
    $pdo = getPDO();
    
    // Get spin winnings
    $stmt = $pdo->prepare('SELECT SUM(win_amount) as spin_wins FROM spins WHERE user_id = ?');
    $stmt->execute([$userId]);
    $spinResult = $stmt->fetch();
    $spinWins = $spinResult['spin_wins'] ?? 0;
    
    // Get puzzle winnings
    $stmt = $pdo->prepare('SELECT SUM(reward) as puzzle_wins FROM word_puzzles WHERE user_id = ? AND status = "win"');
    $stmt->execute([$userId]);
    $puzzleResult = $stmt->fetch();
    $puzzleWins = $puzzleResult['puzzle_wins'] ?? 0;
    
    return $spinWins + $puzzleWins;
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
