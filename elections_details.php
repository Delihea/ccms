<?php
// election_details.php
include 'layout/header.php';

$election_id = intval($_GET['election_id'] ?? 0);

if (!$election_id) {
    header('Location: elections.php');
    exit;
}

// Fetch election details along with club name
$stmt = $pdo->prepare("
    SELECT e.*, c.club_name 
    FROM elections e 
    LEFT JOIN clubs c ON e.club_id = c.club_id 
    WHERE e.election_id = ?
");
$stmt->execute([$election_id]);
$election = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$election) {
    echo "<div class='alert alert-danger'>Election not found.</div>";
    include 'layout/footer.php';
    exit;
}

// Fetch positions for this election
$stmt = $pdo->prepare("SELECT * FROM election_positions WHERE election_id = ? ORDER BY position_name");
$stmt->execute([$election_id]);
$positions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch candidates with votes count
$stmt = $pdo->prepare("
    SELECT ec.*, u.full_name, p.position_name, 
           (SELECT COUNT(*) FROM election_votes ev 
            WHERE ev.candidate_id = ec.candidate_id) as vote_count
    FROM election_candidates ec
    JOIN users u ON ec.user_id = u.user_id
    JOIN election_positions p ON ec.position_id = p.position_id
    WHERE ec.election_id = ?
    ORDER BY p.position_name, ec.candidate_id
");
$stmt->execute([$election_id]);
$candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container mt-4">
    <h2><?= htmlspecialchars($election['title']) ?></h2>
    <p class="text-muted">Club: <?= htmlspecialchars($election['club_name'] ?? '—') ?></p>
    <p>Nomination: <?= date('F j, Y g:i A', strtotime($election['nomination_start'])) ?> – <?= date('F j, Y g:i A', strtotime($election['nomination_end'])) ?></p>
    <p>Voting: <?= date('F j, Y g:i A', strtotime($election['voting_start'])) ?> – <?= date('F j, Y g:i A', strtotime($election['voting_end'])) ?></p>

    <?php if (has_access(['Super Admin','Club Adviser'])): ?>
        <a href="manage_candidates.php?election_id=<?= $election_id ?>" class="btn btn-primary mb-3">Manage Candidates</a>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h5>Candidate List</h5>
        </div>
        <div class="card-body">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Position</th>
                        <th>Candidate</th>
                        <th>Manifesto</th>
                        <th>Status</th>
                        <th>Votes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($candidates as $c): ?>
                        <tr>
                            <td><?= htmlspecialchars($c['position_name']) ?></td>
                            <td><?= htmlspecialchars($c['full_name']) ?></td>
                            <td><?= nl2br(htmlspecialchars($c['manifesto'])) ?></td>
                            <td>
                                <span class="badge bg-<?= $c['status']=='approved'?'success':($c['status']=='rejected'?'danger':'secondary') ?>">
                                    <?= ucfirst($c['status']) ?>
                                </span>
                            </td>
                            <td><?= $c['vote_count'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($candidates)): ?>
                        <tr><td colspan="5" class="text-center">No candidates yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'layout/footer.php'; ?>
