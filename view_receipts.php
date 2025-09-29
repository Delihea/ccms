<?php
include 'layout/header.php';

$election_id = intval($_GET['election_id'] ?? 0);
if (!$election_id) {
    echo "<div class='alert alert-danger'>Election not specified.</div>";
    exit;
}

// Fetch election info
$stmt = $pdo->prepare("SELECT * FROM elections WHERE election_id=?");
$stmt->execute([$election_id]);
$election = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch votes
$stmt = $pdo->prepare("
    SELECT ev.*, u.full_name AS voter_name, c.full_name AS candidate_name, p.position_name
    FROM election_votes ev
    JOIN users u ON ev.voter_user_id = u.user_id
    JOIN election_candidates c ON ev.candidate_id = c.candidate_id
    JOIN election_positions p ON ev.position_id = p.position_id
    WHERE ev.election_id=?
    ORDER BY ev.created_at
");
$stmt->execute([$election_id]);
$votes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<h2>Receipts for <?= htmlspecialchars($election['title']) ?></h2>
<table class="table table-bordered">
    <thead>
        <tr>
            <th>Voter</th>
            <th>Position</th>
            <th>Candidate</th>
            <th>Receipt Code</th>
            <th>Time</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($votes as $v): ?>
            <tr>
                <td><?= htmlspecialchars($v['voter_name']) ?></td>
                <td><?= htmlspecialchars($v['position_name']) ?></td>
                <td><?= htmlspecialchars($v['candidate_name']) ?></td>
                <td><?= htmlspecialchars($v['receipt_code']) ?></td>
                <td><?= date('M d, Y H:i', strtotime($v['created_at'])) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($votes)): ?>
            <tr><td colspan="5" class="text-center">No votes found.</td></tr>
        <?php endif; ?>
    </tbody>
</table>
