<?php
// admin_deposit_history.php - All deposits history section
if ($section !== 'all_deposits') return;

$totalPages = ceil($total / $limit);
$hasPrev = $page > 1;
$hasNext = $page < $totalPages;
?>

<h2>Deposit History (<?php echo $total; ?> total)</h2>

<?php if (empty($data)): ?>
    <div class="empty-state">
        <h3>No deposits found</h3>
        <p>No deposit transactions have been recorded yet.</p>
    </div>
<?php else: ?>
    <table class="deposits-table">
        <thead>
            <tr>
                <th>User</th>
                <th>Type</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Phone</th>
                <th>Requested</th>
                <th>Processed</th>
                <th>Admin</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($data as $deposit): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($deposit['username']); ?></strong></td>
                    <td>
                        <?php if ($deposit['plan_id']): ?>
                            <span class="badge plan">Plan Purchase</span><br>
                            <small><?php echo htmlspecialchars($deposit['plan_id']); ?></small>
                        <?php else: ?>
                            <span class="badge deposit">Wallet Deposit</span>
                        <?php endif; ?>
                    </td>
                    <td><strong><?php echo number_format($deposit['amount'], 2); ?> KES</strong></td>
                    <td>
                        <?php
                        $statusClass = '';
                        switch ($deposit['status']) {
                            case 'approved': $statusClass = 'plan'; break;
                            case 'rejected': $statusClass = 'deposit'; break;
                            default: $statusClass = 'deposit';
                        }
                        ?>
                        <span class="badge <?php echo $statusClass; ?>"><?php echo ucfirst($deposit['status']); ?></span>
                    </td>
                    <td><?php echo htmlspecialchars($deposit['payment_phone'] ?? $deposit['user_phone']); ?></td>
                    <td><small><?php echo date('M d, Y H:i', strtotime($deposit['created_at'])); ?></small></td>
                    <td>
                        <?php if ($deposit['approved_at']): ?>
                            <small><?php echo date('M d, Y H:i', strtotime($deposit['approved_at'])); ?></small>
                        <?php else: ?>
                            <small>-</small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($deposit['approved_by']): ?>
                            <small>Admin #<?php echo $deposit['approved_by']; ?></small>
                        <?php else: ?>
                            <small>-</small>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php if ($totalPages > 1): ?>
<div class="pagination">
    <?php if ($hasPrev): ?>
        <a href="?section=all_deposits&page=<?php echo $page - 1; ?>">&laquo; Previous</a>
    <?php else: ?>
        <span class="disabled">&laquo; Previous</span>
    <?php endif; ?>

    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
        <?php if ($i == $page): ?>
            <span class="current"><?php echo $i; ?></span>
        <?php else: ?>
            <a href="?section=all_deposits&page=<?php echo $i; ?>"><?php echo $i; ?></a>
        <?php endif; ?>
    <?php endfor; ?>

    <?php if ($hasNext): ?>
        <a href="?section=all_deposits&page=<?php echo $page + 1; ?>">Next &raquo;</a>
    <?php else: ?>
        <span class="disabled">Next &raquo;</span>
    <?php endif; ?>
</div>
<?php endif; ?>