<?php
include 'layout/header.php';
$current_role = get_effective_role();

// Fetch ended elections
$rows = $pdo->query("
    SELECT e.*, c.name AS club_name 
    FROM elections e
    LEFT JOIN clubs c ON e.club_id = c.club_id
    WHERE e.is_ended = 1
    ORDER BY e.election_id DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<h2>Past Elections / Receipts</h2>
<div class="row">
<?php foreach ($rows as $election): ?>
    <div class="col-md-6 mb-3">
        <div class="card p-3">
            <h5><?= htmlspecialchars($election['title']) ?></h5>
            <p>Club: <?= htmlspecialchars($election['club_name']) ?></p>
            <p>Voting Period: <?= date('M d, Y H:i', strtotime($election['voting_start'])) ?> - <?= date('M d, Y H:i', strtotime($election['voting_end'])) ?></p>
            <a href="view_receipts.php?election_id=<?= $election['election_id'] ?>" class="btn btn-info btn-sm">View Receipts</a>
        </div>
    </div>
<?php endforeach; ?>
</div>
