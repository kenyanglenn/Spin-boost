<?php
// admin_spin_winners.php - Top spin winners section
if ($section !== 'spin_winners') return;

$totalPages = ceil($total / $limit);
$hasPrev = $page > 1;
$hasNext = $page < $totalPages;
?>

<h2>Top Spin Winners</h2>
<p>Users ranked by their highest single spin win amount.</p>

<?php if (empty($data)): ?>
    <div class="empty-state">
        <h3>No spin data found</h3>
        <p>No users have played spins yet.</p>
    </div>
<?php else: ?>
    <table class="deposits-table">
        <thead>
            <tr>
                <th>Rank</th>
                <th>User</th>
                <th>Plan</th>
                <th>Highest Single Spin</th>
                <th>Lifetime Spins</th>
                <th>Total Staked</th>
            </tr>
        </thead>
        <tbody>
            <?php $rank = ($page - 1) * $limit + 1; ?>
            <?php foreach ($data as $winner): ?>
                <tr>
                    <td><strong>#<?php echo $rank++; ?></strong></td>
                    <td><strong><?php echo htmlspecialchars($winner['username']); ?></strong></td>
                    <td>
                        <span class="badge <?php echo $winner['plan'] === 'NONE' ? 'deposit' : 'plan'; ?>">
                            <?php echo htmlspecialchars($winner['plan']); ?>
                        </span>
                    </td>
                    <td><strong><?php echo number_format($winner['highest_single_spin'], 2); ?> KES</strong></td>
                    <td><?php echo number_format($winner['lifetime_spins']); ?></td>
                    <td><?php echo number_format($winner['total_staked'], 2); ?> KES</td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php if ($totalPages > 1): ?>
<div class="pagination">
    <?php if ($hasPrev): ?>
        <a href="?section=spin_winners&page=<?php echo $page - 1; ?>">&laquo; Previous</a>
    <?php else: ?>
        <span class="disabled">&laquo; Previous</span>
    <?php endif; ?>

    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
        <?php if ($i == $page): ?>
            <span class="current"><?php echo $i; ?></span>
        <?php else: ?>
            <a href="?section=spin_winners&page=<?php echo $i; ?>"><?php echo $i; ?></a>
        <?php endif; ?>
    <?php endfor; ?>

    <?php if ($hasNext): ?>
        <a href="?section=spin_winners&page=<?php echo $page + 1; ?>">Next &raquo;</a>
    <?php else: ?>
        <span class="disabled">Next &raquo;</span>
    <?php endif; ?>
</div>
<?php endif; ?>