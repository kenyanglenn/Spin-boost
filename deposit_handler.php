<?php
/**
 * Deposit Handler - Manual Approval System
 * 
 * Flow:
 * 1. Validate user and amount
 * 2. Create pending deposit record
 * 3. Return success message - user will wait for admin approval
 */

session_start();
require_once 'db.php';

try {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: spin.php');
        exit;
    }

    $amount = isset($_POST['topup_amount']) ? floatval($_POST['topup_amount']) : 0;
    $phone = trim($_POST['topup_phone'] ?? '');

    if (!$amount || empty($phone)) {
        setFlashMessage('error', 'Enter a valid amount and phone number.');
        header('Location: spin.php');
        exit;
    }

    if ($amount < 50 || $amount > 100000) {
        setFlashMessage('error', 'Amount must be between 50 and 100,000 KES.');
        header('Location: spin.php');
        exit;
    }

    $userId = $_SESSION['user_id'];
    createPendingDeposit($userId, $amount, null, $phone);

    setFlashMessage('success', 'Deposit request created successfully. Send the money to 0701144109 and wait for admin approval.');
    header('Location: waiting_for_approval.php');
    exit;
} catch (Exception $e) {
    setFlashMessage('error', 'Unable to create deposit request. Please try again.');
    error_log('Deposit handler error: ' . $e->getMessage());
    header('Location: spin.php');
    exit;
}
?>
