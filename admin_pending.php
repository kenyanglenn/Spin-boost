<?php
// admin_pending.php - Pending approvals section
if ($section !== 'pending') return;

$totalPages = ceil($total / $limit);
$hasPrev = $page > 1;
$hasNext = $page < $totalPages;
?>

<?php if (empty($pendingDeposits) && empty($pendingWithdrawals)): ?>
    <div class="empty-state">
        <h3>✅ All Caught Up!</h3>
        <p>There are no pending deposit or withdrawal requests to review.</p>
    </div>
<?php else: ?>
    <?php if (!empty($pendingDeposits)): ?>
        <h2>Pending Deposits (<?php echo $totalPendingDeposits; ?>)</h2>
        <table class="deposits-table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Requested</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pendingDeposits as $deposit): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($deposit['username']); ?></strong>
                            <div class="deposit-info">
                                📱 <?php echo htmlspecialchars($deposit['phone']); ?><br>
                                Current Plan: <?php echo htmlspecialchars($deposit['plan']); ?>
                            </div>
                        </td>
                        <td>
                            <?php if ($deposit['plan_id']): ?>
                                <?php if ($deposit['plan'] === 'NONE'): ?>
                                    <span class="badge plan">Plan Purchase</span><br>
                                    <small><?php echo htmlspecialchars($deposit['plan_id']); ?></small>
                                <?php else: ?>
                                    <span class="badge plan">Plan Change</span><br>
                                    <small><?php echo htmlspecialchars($deposit['plan']); ?> → <?php echo htmlspecialchars($deposit['plan_id']); ?></small>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="badge deposit">Wallet Deposit</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?php echo number_format($deposit['amount'], 2); ?> KES</strong>
                        </td>
                        <td>
                            <small><?php echo date('M d, Y H:i', strtotime($deposit['created_at'])); ?></small>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn-small btn-approve" onclick="openModal('approve', <?php echo $deposit['id']; ?>, '<?php echo htmlspecialchars($deposit['username']); ?>', 'deposit')">
                                    ✓ Approve
                                </button>
                                <button class="btn-small btn-reject" onclick="openModal('reject', <?php echo $deposit['id']; ?>, '<?php echo htmlspecialchars($deposit['username']); ?>', 'deposit')">
                                    ✕ Reject
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <?php if (!empty($pendingWithdrawals)): ?>
        <h2 style="margin-top: 40px;">Pending Withdrawals (<?php echo $totalPendingWithdrawals; ?>)</h2>
        <table class="deposits-table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Amount</th>
                    <th>Phone</th>
                    <th>Requested</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pendingWithdrawals as $withdrawal): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($withdrawal['username']); ?></strong>
                            <div class="deposit-info">
                                📱 <?php echo htmlspecialchars($withdrawal['phone'] ?? 'Not provided'); ?><br>
                                Current Plan: <?php echo htmlspecialchars($withdrawal['plan']); ?>
                            </div>
                        </td>
                        <td><strong><?php echo number_format($withdrawal['amount'], 2); ?> KES</strong></td>
                        <td><?php echo htmlspecialchars($withdrawal['phone'] ?? '-'); ?></td>
                        <td><small><?php echo date('M d, Y H:i', strtotime($withdrawal['created_at'])); ?></small></td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn-small btn-approve" onclick="openModal('approve', <?php echo $withdrawal['id']; ?>, '<?php echo htmlspecialchars($withdrawal['username']); ?>', 'withdrawal')">
                                    ✓ Approve
                                </button>
                                <button class="btn-small btn-reject" onclick="openModal('reject', <?php echo $withdrawal['id']; ?>, '<?php echo htmlspecialchars($withdrawal['username']); ?>', 'withdrawal')">
                                    ✕ Reject
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
<?php endif; ?>

<?php if ($totalPages > 1): ?>
<div class="pagination">
    <?php if ($hasPrev): ?>
        <a href="?section=pending&page=<?php echo $page - 1; ?>">&laquo; Previous</a>
    <?php else: ?>
        <span class="disabled">&laquo; Previous</span>
    <?php endif; ?>

    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
        <?php if ($i == $page): ?>
            <span class="current"><?php echo $i; ?></span>
        <?php else: ?>
            <a href="?section=pending&page=<?php echo $i; ?>"><?php echo $i; ?></a>
        <?php endif; ?>
    <?php endfor; ?>

    <?php if ($hasNext): ?>
        <a href="?section=pending&page=<?php echo $page + 1; ?>">Next &raquo;</a>
    <?php else: ?>
        <span class="disabled">Next &raquo;</span>
    <?php endif; ?>
</div>
<?php endif; ?>