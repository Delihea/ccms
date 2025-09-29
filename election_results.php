<?php
include 'layout/header.php';

$election_id = $_GET['election_id'] ?? 0;

$stmt = $pdo->prepare("
    SELECT p.position_name, u.full_name, COUNT(v.vote_id) as votes
    FROM election_positions p
    LEFT JOIN election_candidates c ON p.position_id = c.position_id
    LEFT JOIN users u ON c.user_id = u.user_id
    LEFT JOIN election_votes v ON c.candidate_id = v.candidate_id
    WHERE p.election_id = ?
    GROUP BY p.position_id, c.candidate_id
    ORDER BY p.position_id, votes DESC
");
$stmt->execute([$election_id]);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

$positions = [];
foreach ($results as $r) {
    $positions[$r['position_name']][] = [
        'name' => $r['full_name'] ?: 'No Candidate',
        'votes' => $r['votes']
    ];
}
?>

<div class="container mt-4">
    <h2>Election Results</h2>
    <?php foreach ($positions as $pos => $cands): ?>
        <div class="card mb-3">
            <div class="card-header"><strong><?= htmlspecialchars($pos) ?></strong></div>
            <div class="card-body">
                <?php foreach ($cands as $c): ?>
                    <p><?= htmlspecialchars($c['name']) ?> - <?= $c['votes'] ?> votes</p>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php include 'layout/footer.php'; ?>
