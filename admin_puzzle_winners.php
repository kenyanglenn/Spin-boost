<?php
// admin_puzzle_winners.php - Top puzzle winners section
if ($section !== 'puzzle_winners') return;

$totalPages = ceil($total / $limit);
$hasPrev = $page > 1;
$hasNext = $page < $totalPages;
?>

<h2>Top Puzzle Winners</h2>
<p>Users ranked by their highest single puzzle reward.</p>

<?php if (empty($data)): ?>
    <div class="empty-state">
        <h3>No puzzle data found</h3>
        <p>No users have played puzzles yet.</p>
    </div>
<?php else: ?>
    <table class="deposits-table">
        <thead>
            <tr>
                <th>Rank</th>
                <th>User</th>
                <th>Plan</th>
                <th>Highest Single Puzzle</th>
                <th>Lifetime Puzzles</th>
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
                    <td><strong><?php echo number_format($winner['highest_single_puzzle'], 2); ?> KES</strong></td>
                    <td><?php echo number_format($winner['lifetime_puzzles']); ?></td>
                    <td><?php echo number_format($winner['total_staked'], 2); ?> KES</td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php if ($totalPages > 1): ?>
<div class="pagination">
    <?php if ($hasPrev): ?>
        <a href="?section=puzzle_winners&page=<?php echo $page - 1; ?>">&laquo; Previous</a>
    <?php else: ?>
        <span class="disabled">&laquo; Previous</span>
    <?php endif; ?>

    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
        <?php if ($i == $page): ?>
            <span class="current"><?php echo $i; ?></span>
        <?php else: ?>
            <a href="?section=puzzle_winners&page=<?php echo $i; ?>"><?php echo $i; ?></a>
        <?php endif; ?>
    <?php endfor; ?>

    <?php if ($hasNext): ?>
        <a href="?section=puzzle_winners&page=<?php echo $page + 1; ?>">Next &raquo;</a>
    <?php else: ?>
        <span class="disabled">Next &raquo;</span>
    <?php endif; ?>
</div>
<?php endif; ?>