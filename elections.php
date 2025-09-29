<?php
// elections.php
include 'layout/header.php';

$role = get_effective_role();

// Fetch elections
$rows = $pdo->query("
    SELECT e.*, c.club_name 
    FROM elections e 
    LEFT JOIN clubs c ON e.club_id = c.club_id
    ORDER BY e.nomination_start DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container mt-4">
    <h2>Elections</h2>
    <p class="text-muted mb-4">Check ongoing and upcoming elections. Manage candidates if you have access.</p>

    <div class="row g-4">
        <?php foreach ($rows as $e): ?>
            <div class="col-md-6 col-lg-4">
                <div class="election-card p-4 rounded shadow-sm d-flex flex-column justify-content-between">
                    <div>
                        <h5 class="mb-2"><?= htmlspecialchars($e['title']) ?></h5>
                        <p class="text-secondary mb-2"><strong>Club:</strong> <?= htmlspecialchars($e['club_name']) ?></p>
                        <div class="period-info mb-3">
                            <div><strong>Nomination:</strong> <?= date('M d, Y g:i A', strtotime($e['nomination_start'])) ?> – <?= date('M d, Y g:i A', strtotime($e['nomination_end'])) ?></div>
                            <div><strong>Voting:</strong> <?= date('M d, Y g:i A', strtotime($e['voting_start'])) ?> – <?= date('M d, Y g:i A', strtotime($e['voting_end'])) ?></div>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2 mt-2">
                        <a href="elections_details.php?election_id=<?= $e['election_id'] ?>" class="btn btn-primary btn-sm">View</a>

                        <?php if (in_array($role, ['Super Admin', 'Club Adviser'])): ?>
                            <a href="manage_candidates.php?election_id=<?= $e['election_id'] ?>" class="btn btn-warning btn-sm">Manage</a>
                            <a href="end_election.php?election_id=<?= $e['election_id'] ?>" class="btn btn-danger btn-sm"
                               onclick="return confirm('Are you sure you want to end this election? Votes will be moved to receipts.')">
                               End
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (empty($rows)): ?>
            <div class="col-12">
                <div class="alert alert-info text-center">No elections available.</div>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
/* Modernized election card UI */
.election-card {
    background: #fff;
    border-radius: 16px;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    min-height: 250px; /* bigger cards */
}
.election-card:hover {
    box-shadow: 0 12px 25px rgba(0,0,0,0.15);
    transform: translateY(-5px);
}
body.dark .election-card {
    background: #1e1e1e;
    box-shadow: 0 2px 10px rgba(0,0,0,0.3);
}
body.dark .election-card:hover {
    box-shadow: 0 12px 25px rgba(0,0,0,0.45);
}
.election-card h5 {
    color: #222;
    font-weight: 600;
}
body.dark .election-card h5 {
    color: #fff;
}
.election-card p, .period-info div {
    font-size: 0.9rem;
    color: #555;
}
body.dark .election-card p, body.dark .period-info div {
    color: #ccc;
}
.btn-sm {
    font-size: 0.85rem;
    padding: 0.35rem 0.6rem;
    min-width: 80px; /* compact buttons */
}
.period-info div {
    margin-bottom: 4px;
}
</style>

<?php include 'layout/footer.php'; ?>
