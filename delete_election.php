<?php
// delete_election.php
require_once __DIR__ . '/inc/config.php';

// Must be logged in
if (!is_logged_in()) {
    header('Location: index.php');
    exit;
}

$role = $_SESSION['role'] ?? 'Student';
$election_id = intval($_GET['election_id'] ?? 0);

// Only Super Admin and Club Adviser can delete
if (!in_array($role, ['Super Admin', 'Club Adviser'])) {
    $_SESSION['error'] = "Unauthorized access.";
    header('Location: elections.php');
    exit;
}

if ($election_id > 0) {
    // Check election status first
    $stmt = $pdo->prepare("SELECT status FROM elections WHERE election_id = ?");
    $stmt->execute([$election_id]);
    $election = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($election) {
        if ($election['status'] === 'upcoming') {
            // Safe delete
            $del = $pdo->prepare("DELETE FROM elections WHERE election_id = ?");
            $del->execute([$election_id]);

            // Also cascade delete related data (positions, candidates, votes)
            $pdo->prepare("DELETE FROM election_positions WHERE election_id = ?")->execute([$election_id]);
            $pdo->prepare("DELETE FROM election_candidates WHERE election_id = ?")->execute([$election_id]);
            $pdo->prepare("DELETE FROM election_votes WHERE election_id = ?")->execute([$election_id]);

            $_SESSION['success'] = "Election deleted successfully.";
        } else {
            $_SESSION['error'] = "Only upcoming elections can be deleted.";
        }
    } else {
        $_SESSION['error'] = "Election not found.";
    }
} else {
    $_SESSION['error'] = "Invalid election ID.";
}

header('Location: elections.php');
exit;
