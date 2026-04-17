<?php
require_once 'db.php';

$currentAdmin = getCurrentAdmin();
if (!$currentAdmin) {
    header('Location: admin_login.php');
    exit;
}

if (!isAdminUser($currentAdmin['id'])) {
    http_response_code(403);
    echo "Access denied. Admin only.";
    exit;
}

// Handle approval/rejection actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $depositId = intval($_POST['deposit_id'] ?? 0);
    $withdrawalId = intval($_POST['withdrawal_id'] ?? 0);
    $adminNotes = trim($_POST['admin_notes'] ?? '');
    
    if ($depositId > 0) {
        if ($action === 'approve') {
            $result = approveDeposit($depositId, $currentAdmin['id'], $adminNotes);
            $flashType = $result['success'] ? 'success' : 'error';
            setFlashMessage($flashType, $result['message']);
        } elseif ($action === 'reject') {
            $result = rejectDeposit($depositId, $currentAdmin['id'], $adminNotes);
            setFlashMessage('success', $result['message']);
        }
    } elseif ($withdrawalId > 0) {
        if ($action === 'approve') {
            $result = approveWithdrawal($withdrawalId, $currentAdmin['id'], $adminNotes);
            $flashType = $result['success'] ? 'success' : 'error';
            setFlashMessage($flashType, $result['message']);
        } elseif ($action === 'reject') {
            $result = rejectWithdrawal($withdrawalId, $currentAdmin['id'], $adminNotes);
            $flashType = $result['success'] ? 'success' : 'error';
            setFlashMessage($flashType, $result['message']);
        }
    }
    
    header('Location: admin_deposits.php');
    exit;
}

// Get current section and pagination
$section = $_GET['section'] ?? 'pending';
$page = intval($_GET['page'] ?? 1);
$limit = 10;

// Load data based on section
switch ($section) {
    case 'users':
        $data = getAllUsers($page, $limit);
        $total = getTotalUsers();
        break;
    case 'spin_winners':
        $data = getTopSpinWinners($page, $limit);
        $total = count($data); // Approximate
        break;
    case 'puzzle_winners':
        $data = getTopPuzzleWinners($page, $limit);
        $total = count($data); // Approximate
        break;
    case 'all_deposits':
        $data = getAllDeposits($page, $limit);
        $total = getTotalDeposits();
        break;
    case 'all_withdrawals':
        $data = getAllWithdrawals($page, $limit);
        $total = getTotalWithdrawals();
        break;
    default: // pending
        $pendingDeposits = getPendingDepositsPaginated($page, $limit);
        $pendingWithdrawals = getPendingWithdrawalsPaginated($page, $limit);
        $totalPendingDeposits = getTotalPendingDeposits();
        $totalPendingWithdrawals = getTotalPendingWithdrawals();
        $total = max($totalPendingDeposits, $totalPendingWithdrawals);
        break;
}

$flash = getFlashMessage();
$adminCount = countAdmins();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Admin - Deposit Approvals</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .admin-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            color: white;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        .admin-header h1 {
            margin: 0;
            color: white;
        }
        
        .admin-header .logout-btn {
            padding: 8px 16px;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .admin-header .logout-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        
        .admin-nav {
            display: flex;
            gap: 0;
            margin-bottom: 30px;
            background: #f8f9fa;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .nav-tab {
            flex: 1;
            padding: 15px 20px;
            background: none;
            border: none;
            cursor: pointer;
            text-decoration: none;
            color: #666;
            font-weight: 600;
            transition: all 0.3s;
            text-align: center;
        }
        
        .nav-tab:hover, .nav-tab.active {
            background: #667eea;
            color: white;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin: 30px 0;
        }
        
        .pagination a, .pagination span {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            color: #333;
            transition: all 0.3s;
        }
        
        .pagination a:hover, .pagination .current {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        
        .pagination .disabled {
            color: #ccc;
            cursor: not-allowed;
        }

        
        .toast {
            padding: 12px 20px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .toast.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .toast.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .deposits-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            animation: fadeInUp 0.5s ease-out;
        }
        
        .deposits-table thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .deposits-table th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
        }
        
        .deposits-table td {
            padding: 15px;
            border-bottom: 1px solid #e9ecef;
            transition: background-color 0.3s ease;
            color: #333;
        }
        
        .deposits-table tbody tr:hover {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        .badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            animation: pulse 2s infinite;
        }
        
        .badge.plan {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            box-shadow: 0 2px 4px rgba(40, 167, 69, 0.3);
        }
        
        .badge.deposit {
            background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
            color: white;
            box-shadow: 0 2px 4px rgba(108, 117, 125, 0.3);
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        
        .btn-small {
            padding: 8px 14px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .btn-approve {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            box-shadow: 0 2px 4px rgba(40, 167, 69, 0.3);
        }
        
        .btn-approve:hover {
            background: linear-gradient(135deg, #218838 0%, #17a2b8 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(40, 167, 69, 0.4);
        }
        
        .btn-reject {
            background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);
            color: white;
            box-shadow: 0 2px 4px rgba(220, 53, 69, 0.3);
        }
        
        .btn-reject:hover {
            background: linear-gradient(135deg, #c82333 0%, #e8680d 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(220, 53, 69, 0.4);
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.7;
            }
        }
        
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .modal-overlay.active {
            display: flex;
        }
        
        .modal-card {
            background: white;
            border-radius: 8px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        }
        
        .modal-card h2 {
            margin-top: 0;
            color: #333;
        }
        
        .modal-card .close-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #999;
        }
        
        .modal-card textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: inherit;
            resize: vertical;
            min-height: 100px;
            box-sizing: border-box;
        }
        
        .modal-card .form-group p {
            color: #333;
            font-size: 16px;
            margin: 0 0 15px 0;
        }
        
        .modal-card label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        
        .modal-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .modal-actions button {
            flex: 1;
            padding: 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
        }
        
        .modal-actions .btn-confirm {
            background: #28a745;
            color: white;
        }
        
        .modal-actions .btn-confirm.reject {
            background: #dc3545;
        }
        
        .modal-actions .btn-cancel {
            background: #e9ecef;
            color: #333;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        
        .empty-state h3 {
            color: #666;
        }
        
        .deposit-info {
            font-size: 13px;
            color: #666;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="admin-header">
            <div>
                <h1>🔐 Finance Approval Management</h1>
                <p style="margin: 4px 0 0; color: rgba(255,255,255,0.8); font-size: 14px;">Logged in as <?php echo htmlspecialchars($currentAdmin['username']); ?> • Admins: <?php echo $adminCount; ?>/5</p>
            </div>
            <div style="display:flex; gap:10px; align-items:center;">
                <?php if ($adminCount < 5): ?>
                    <a href="admin_register.php" class="logout-btn" style="background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3);">Add Admin</a>
                <?php endif; ?>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
        
        <nav class="admin-nav">
            <a href="?section=pending" class="nav-tab <?php echo $section === 'pending' ? 'active' : ''; ?>">Pending Approvals</a>
            <a href="?section=users" class="nav-tab <?php echo $section === 'users' ? 'active' : ''; ?>">All Users</a>
            <a href="?section=spin_winners" class="nav-tab <?php echo $section === 'spin_winners' ? 'active' : ''; ?>">Top Spin Winners</a>
            <a href="?section=puzzle_winners" class="nav-tab <?php echo $section === 'puzzle_winners' ? 'active' : ''; ?>">Top Puzzle Winners</a>
            <a href="?section=all_deposits" class="nav-tab <?php echo $section === 'all_deposits' ? 'active' : ''; ?>">Deposit History</a>
            <a href="?section=all_withdrawals" class="nav-tab <?php echo $section === 'all_withdrawals' ? 'active' : ''; ?>">Withdrawal History</a>
        </nav>

        
        <?php if ($flash): ?>
            <div class="toast <?php echo $flash['type']; ?>"><?php echo htmlspecialchars($flash['text']); ?></div>
        <?php endif; ?>
        
        <?php
        switch ($section) {
            case 'pending':
                include 'admin_pending.php';
                break;
            case 'users':
                include 'admin_users.php';
                break;
            case 'spin_winners':
                include 'admin_spin_winners.php';
                break;
            case 'puzzle_winners':
                include 'admin_puzzle_winners.php';
                break;
            case 'all_deposits':
                include 'admin_deposit_history.php';
                break;
            case 'all_withdrawals':
                include 'admin_withdrawal_history.php';
                break;
        }
        ?>
    </div>
    
    <!-- Approval Modal -->
    <div class="modal-overlay" id="actionModal">
        <div class="modal-card">
            <button class="close-btn" onclick="closeModal()">×</button>
            <h2 id="modalTitle">Approve Deposit</h2>
            
            <form method="post" id="actionForm">
                <input type="hidden" name="action" id="modalAction" value="approve">
                <input type="hidden" name="deposit_id" id="modalDepositId" value="">
                <input type="hidden" name="withdrawal_id" id="modalWithdrawalId" value="">
                
                <div class="form-group">
                    <p id="modalMessage">Are you sure?</p>
                </div>
                
                <div class="form-group">
                    <label for="admin_notes">Admin Notes (optional)</label>
                    <textarea name="admin_notes" id="admin_notes" placeholder="Add notes about this approval/rejection..."></textarea>
                </div>
                
                <div class="modal-actions">
                    <button type="submit" class="btn-confirm" id="modalConfirmBtn">Approve</button>
                    <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function openModal(action, requestId, username, requestType = 'deposit') {
            const modal = document.getElementById('actionModal');
            const title = document.getElementById('modalTitle');
            const message = document.getElementById('modalMessage');
            const actionInput = document.getElementById('modalAction');
            const depositInput = document.getElementById('modalDepositId');
            const withdrawalInput = document.getElementById('modalWithdrawalId');
            const confirmBtn = document.getElementById('modalConfirmBtn');

            if (action === 'approve') {
                title.textContent = requestType === 'withdrawal' ? 'Approve Withdrawal' : 'Approve Deposit';
                message.textContent = `Approve ${requestType} request from ${username}?`;
                actionInput.value = 'approve';
                confirmBtn.textContent = 'Approve';
                confirmBtn.className = 'btn-confirm';
            } else {
                title.textContent = requestType === 'withdrawal' ? 'Reject Withdrawal' : 'Reject Deposit';
                message.textContent = `Reject ${requestType} request from ${username}?`;
                actionInput.value = 'reject';
                confirmBtn.textContent = 'Reject';
                confirmBtn.className = 'btn-confirm reject';
            }

            if (requestType === 'withdrawal') {
                depositInput.value = '';
                withdrawalInput.value = requestId;
            } else {
                depositInput.value = requestId;
                withdrawalInput.value = '';
            }

            modal.classList.add('active');
        }
        
        function closeModal() {
            const modal = document.getElementById('actionModal');
            modal.classList.remove('active');
            document.getElementById('admin_notes').value = '';
            document.getElementById('modalDepositId').value = '';
            document.getElementById('modalWithdrawalId').value = '';
        }
        
        // Close modal when clicking outside
        document.getElementById('actionModal').addEventListener('click', (e) => {
            if (e.target.id === 'actionModal') closeModal();
        });
    </script>
</body>
</html>
