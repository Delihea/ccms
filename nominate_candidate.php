<?php
// nominate_candidate.php - Candidate Nomination Interface
include 'layout/header.php';

$election_id = intval($_GET['election_id'] ?? 0);

if (!$election_id) {
    header('Location: elections.php');
    exit;
}

// Get election details
$stmt = $pdo->prepare("SELECT e.*, c.name as club_name FROM elections e 
                      LEFT JOIN clubs c ON e.club_id = c.club_id 
                      WHERE e.election_id = ?");
$stmt->execute([$election_id]);
$election = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$election) {
    header('Location: elections.php');
    exit;
}

// Check if nomination period is active
$now = date('Y-m-d H:i:s');
if ($now < $election['nomination_start'] || $now > $election['nomination_end']) {
    $_SESSION['error'] = "Nomination period is not active.";
    header('Location: election_details.php?election_id=' . $election_id);
    exit;
}

// Get available positions
$stmt = $pdo->prepare("SELECT * FROM election_positions WHERE election_id = ? ORDER BY position_name");
$stmt->execute([$election_id]);
$positions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle nomination submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $position_id = intval($_POST['position_id']);
        $manifesto = $_POST['manifesto'];
        
        // Check if user is already nominated for this position
        $stmt = $pdo->prepare("SELECT * FROM election_candidates 
                              WHERE election_id = ? AND position_id = ? AND user_id = ?");
        $stmt->execute([$election_id, $position_id, current_user_id()]);
        
        if ($stmt->fetch()) {
            $error = "You have already been nominated for this position.";
        } else {
            // Create nomination
            $stmt = $pdo->prepare("INSERT INTO election_candidates 
                                (election_id, position_id, user_id, manifesto) 
                                VALUES (?, ?, ?, ?)");
            $stmt->execute([$election_id, $position_id, current_user_id(), $manifesto]);
            
            $_SESSION['success'] = "Nomination submitted successfully! It will be reviewed by club officers.";
            header('Location: election_details.php?election_id=' . $election_id);
            exit;
        }
    } catch (Exception $e) {
        $error = "Error submitting nomination: " . $e->getMessage();
    }
}
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h4>Nominate Candidate</h4>
                    <p class="mb-0 text-muted">
                        <?= htmlspecialchars($election['title']) ?> - <?= htmlspecialchars($election['club_name']) ?>
                    </p>
                </div>
                <div class="card-body">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    
                    <form method="post">
                        <div class="mb-3">
                            <label for="position_id" class="form-label">Position</label>
                            <select class="form-select" id="position_id" name="position_id" required>
                                <option value="">Select a position</option>
                                <?php foreach ($positions as $position): ?>
                                    <option value="<?= $position['position_id'] ?>">
                                        <?= htmlspecialchars($position['position_name']) ?>
                                        <?php if ($position['max_candidates'] > 1): ?>
                                            (Max <?= $position['max_candidates'] ?> candidates)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="manifesto" class="form-label">Manifesto/Platform</label>
                            <textarea class="form-control" id="manifesto" name="manifesto" rows="6" required
                                      placeholder="Share your vision, goals, and why you're running for this position..."></textarea>
                            <div class="form-text">
                                This will be visible to all voters during the election.
                            </div>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Submit Nomination</button>
                            <a href="election_details.php?election_id=<?= $election_id ?>" 
                               class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="card mt-4">
                <div class="card-header">
                    <h5>Nomination Guidelines</h5>
                </div>
                <div class="card-body">
                    <ul class="mb-0">
                        <li>Ensure you meet all eligibility requirements for the position</li>
                        <li>Your nomination will be reviewed by club officers before approval</li>
                        <li>You can only nominate yourself for one position per election</li>
                        <li>Be honest and professional in your manifesto</li>
                        <li>Nominations close on <?= date('F j, Y g:i A', strtotime($election['nomination_end'])) ?></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'layout/footer.php'; ?>