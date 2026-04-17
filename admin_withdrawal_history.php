<?php
// admin_withdrawal_history.php - All withdrawals history section
if ($section !== 'all_withdrawals') return;

$totalPages = ceil($total / $limit);
$hasPrev = $page > 1;
$hasNext = $page < $totalPages;
?>

<h2>Withdrawal History (<?php echo $total; ?> total)</h2>

<?php if (empty($data)): ?>
    <div class="empty-state">
        <h3>No withdrawals found</h3>
        <p>No withdrawal transactions have been recorded yet.</p>
    </div>
<?php else: ?>
    <table class="deposits-table">
        <thead>
            <tr>
                <th>User</th>
                <th>Amount</th>
                <th>Phone</th>
                <th>Status</th>
                <th>Requested</th>
                <th>Processed</th>
                <th>Admin</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($data as $withdrawal): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($withdrawal['username']); ?></strong></td>
                    <td><strong><?php echo number_format($withdrawal['amount'], 2); ?> KES</strong></td>
                    <td><?php echo htmlspecialchars($withdrawal['phone'] ?? $withdrawal['user_phone']); ?></td>
                    <td>
                        <?php
                        $statusClass = '';
                        switch ($withdrawal['status']) {
                            case 'completed': $statusClass = 'plan'; break;
                            case 'failed': $statusClass = 'deposit'; break;
                            default: $statusClass = 'deposit';
                        }
                        ?>
                        <span class="badge <?php echo $statusClass; ?>"><?php echo ucfirst($withdrawal['status']); ?></span>
                    </td>
                    <td><small><?php echo date('M d, Y H:i', strtotime($withdrawal['created_at'])); ?></small></td>
                    <td>
                        <?php if ($withdrawal['processed_at']): ?>
                            <small><?php echo date('M d, Y H:i', strtotime($withdrawal['processed_at'])); ?></small>
                        <?php else: ?>
                            <small>-</small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($withdrawal['processed_by']): ?>
                            <small>Admin #<?php echo $withdrawal['processed_by']; ?></small>
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
        <a href="?section=all_withdrawals&page=<?php echo $page - 1; ?>">&laquo; Previous</a>
    <?php else: ?>
        <span class="disabled">&laquo; Previous</span>
    <?php endif; ?>

    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
        <?php if ($i == $page): ?>
            <span class="current"><?php echo $i; ?></span>
        <?php else: ?>
            <a href="?section=all_withdrawals&page=<?php echo $i; ?>"><?php echo $i; ?></a>
        <?php endif; ?>
    <?php endfor; ?>

    <?php if ($hasNext): ?>
        <a href="?section=all_withdrawals&page=<?php echo $page + 1; ?>">Next &raquo;</a>
    <?php else: ?>
        <span class="disabled">Next &raquo;</span>
    <?php endif; ?>
</div>
<?php endif; ?>