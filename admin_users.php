<?php
// admin_users.php - All users section
if ($section !== 'users') return;

$totalPages = ceil($total / $limit);
$hasPrev = $page > 1;
$hasNext = $page < $totalPages;
?>

<h2>All Users (<?php echo $total; ?> total)</h2>

<?php if (empty($data)): ?>
    <div class="empty-state">
        <h3>No users found</h3>
        <p>There are no registered users in the system.</p>
    </div>
<?php else: ?>
    <table class="deposits-table">
        <thead>
            <tr>
                <th>User</th>
                <th>Phone</th>
                <th>Wallet Balance</th>
                <th>Plan</th>
                <th>Joined</th>
                <th>Role</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($data as $user): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                    <td><?php echo htmlspecialchars($user['phone']); ?></td>
                    <td><strong><?php echo number_format($user['wallet'], 2); ?> KES</strong></td>
                    <td>
                        <span class="badge <?php echo $user['plan'] === 'NONE' ? 'deposit' : 'plan'; ?>">
                            <?php echo htmlspecialchars($user['plan']); ?>
                        </span>
                    </td>
                    <td><small><?php echo date('M d, Y', strtotime($user['created_at'])); ?></small></td>
                    <td>
                        <?php if ($user['is_admin']): ?>
                            <span class="badge plan">Admin</span>
                        <?php else: ?>
                            <span class="badge deposit">User</span>
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
        <a href="?section=users&page=<?php echo $page - 1; ?>">&laquo; Previous</a>
    <?php else: ?>
        <span class="disabled">&laquo; Previous</span>
    <?php endif; ?>

    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
        <?php if ($i == $page): ?>
            <span class="current"><?php echo $i; ?></span>
        <?php else: ?>
            <a href="?section=users&page=<?php echo $i; ?>"><?php echo $i; ?></a>
        <?php endif; ?>
    <?php endfor; ?>

    <?php if ($hasNext): ?>
        <a href="?section=users&page=<?php echo $page + 1; ?>">Next &raquo;</a>
    <?php else: ?>
        <span class="disabled">Next &raquo;</span>
    <?php endif; ?>
</div>
<?php endif; ?>