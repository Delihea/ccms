<?php
// manage_candidates.php
include 'layout/header.php';

if (!has_access(['Super Admin', 'Club Adviser'])) {
    header('Location: dashboard.php');
    exit;
}

$election_id = intval($_GET['election_id'] ?? 0);
if (!$election_id) {
    echo "<div class='alert alert-danger'>Election not specified.</div>";
    include 'layout/footer.php';
    exit;
}

// Fetch election details
$stmt = $pdo->prepare("SELECT * FROM elections WHERE election_id = ?");
$stmt->execute([$election_id]);
$election = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$election) {
    echo "<div class='alert alert-danger'>Election not found.</div>";
    include 'layout/footer.php';
    exit;
}

// Fetch positions for election
$positions = $pdo->prepare("SELECT * FROM election_positions WHERE election_id = ?");
$positions->execute([$election_id]);
$positions = $positions->fetchAll(PDO::FETCH_ASSOC);

// Fetch club members for dropdown
$members = $pdo->prepare("
    SELECT u.user_id, u.full_name 
    FROM club_members m 
    JOIN users u ON m.user_id = u.user_id 
    WHERE m.club_id = ?
");
$members->execute([$election['club_id']]);
$members = $members->fetchAll(PDO::FETCH_ASSOC);

// Fetch existing candidates and vote counts
$candidates = $pdo->prepare("
    SELECT ec.*, u.full_name, p.position_name, 
           COUNT(ev.vote_id) AS vote_count
    FROM election_candidates ec
    JOIN users u ON ec.user_id = u.user_id
    JOIN election_positions p ON ec.position_id = p.position_id
    LEFT JOIN election_votes ev ON ec.candidate_id = ev.candidate_id AND ec.election_id = ev.election_id
    WHERE ec.election_id = ?
    GROUP BY ec.candidate_id
    ORDER BY p.position_name, vote_count DESC
");
$candidates->execute([$election_id]);
$candidates = $candidates->fetchAll(PDO::FETCH_ASSOC);
$existing_candidate_ids = array_column($candidates, 'user_id');

// Handle add candidate
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_candidate'])) {
    $position_id = intval($_POST['position_id']);
    $user_id = intval($_POST['user_id']);
    $manifesto = trim($_POST['manifesto']);

    // Prevent adding duplicate candidate
    if (in_array($user_id, $existing_candidate_ids)) {
        echo "<div class='alert alert-warning'>This member is already a candidate.</div>";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO election_candidates (election_id, position_id, user_id, manifesto) 
                                   VALUES (?, ?, ?, ?)");
            $stmt->execute([$election_id, $position_id, $user_id, $manifesto]);
            echo "<div class='alert alert-success'>Candidate added successfully.</div>";
            header("Location: manage_candidates.php?election_id=$election_id");
            exit;
        } catch (PDOException $e) {
            echo "<div class='alert alert-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
}

// Handle approve/reject
if (isset($_GET['action'], $_GET['candidate_id'])) {
    $cid = intval($_GET['candidate_id']);
    if ($_GET['action'] === 'approve') {
        $pdo->prepare("UPDATE election_candidates SET status='approved' WHERE candidate_id=?")->execute([$cid]);
    } elseif ($_GET['action'] === 'reject') {
        $pdo->prepare("UPDATE election_candidates SET status='rejected' WHERE candidate_id=?")->execute([$cid]);
    }
    header("Location: manage_candidates.php?election_id=$election_id");
    exit;
}
?>

<div class="container mt-4">
  <h2>Manage Candidates - <?= htmlspecialchars($election['title']) ?></h2>

  <div class="card mb-4">
    <div class="card-header">Add Candidate</div>
    <div class="card-body">
      <form method="post">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Position</label>
            <select name="position_id" class="form-select" required>
              <option value="">Select position</option>
              <?php foreach ($positions as $pos): ?>
                <option value="<?= $pos['position_id'] ?>"><?= htmlspecialchars($pos['position_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Member</label>
            <select name="user_id" class="form-select" required>
              <option value="">Select member</option>
              <?php foreach ($members as $m): 
                  $disabled = in_array($m['user_id'], $existing_candidate_ids) ? 'disabled' : '';
                  $label = htmlspecialchars($m['full_name']);
                  if ($disabled) $label .= " (Already Candidate)";
              ?>
                <option value="<?= $m['user_id'] ?>" <?= $disabled ?>><?= $label ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-12">
            <label class="form-label">Manifesto</label>
            <textarea name="manifesto" class="form-control" rows="2"></textarea>
          </div>
        </div>
        <button type="submit" name="add_candidate" class="btn btn-primary mt-3">Add Candidate</button>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header">Candidate List</div>
    <div class="card-body">
      <table class="table table-bordered">
        <thead>
          <tr>
            <th>Position</th>
            <th>Candidate</th>
            <th>Manifesto</th>
            <th>Status</th>
            <th>Votes</th>
            <th>Actions</th>
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
              <td>
                <?php if ($c['status'] === 'pending'): ?>
                  <a href="?election_id=<?= $election_id ?>&action=approve&candidate_id=<?= $c['candidate_id'] ?>" class="btn btn-sm btn-success">Approve</a>
                  <a href="?election_id=<?= $election_id ?>&action=reject&candidate_id=<?= $c['candidate_id'] ?>" class="btn btn-sm btn-danger">Reject</a>
                <?php else: ?>
                  <span class="text-muted">No actions</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($candidates)): ?>
            <tr><td colspan="6" class="text-center">No candidates yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include 'layout/footer.php'; ?>
