<?php
// vote.php
include 'layout/header.php'; // header includes inc/config.php and checks login

$user_id = current_user_id();
$election_id = intval($_GET['election_id'] ?? 0);

// detect which columns exist on elections table (avoid unknown column errors)
try {
    $colStmt = $pdo->prepare("
        SELECT COLUMN_NAME 
        FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
          AND TABLE_NAME = 'elections' 
          AND COLUMN_NAME IN ('voting_start','voting_end','start_time','end_time')
    ");
    $colStmt->execute();
    $cols = $colStmt->fetchAll(PDO::FETCH_COLUMN);

    $startCol = $endCol = null;
    if (in_array('voting_start', $cols) && in_array('voting_end', $cols)) {
        $startCol = 'voting_start'; $endCol = 'voting_end';
    } elseif (in_array('start_time', $cols) && in_array('end_time', $cols)) {
        $startCol = 'start_time'; $endCol = 'end_time';
    }
} catch (PDOException $e) {
    echo "<div class='container mt-4'><div class='alert alert-danger'>DB error: " . htmlspecialchars($e->getMessage()) . "</div></div>";
    include 'layout/footer.php';
    exit;
}

/* --------------------------
   1) Show list of active elections
   -------------------------- */
if (!$election_id) {
    if ($startCol && $endCol) {
        $sql = "SELECT e.*, c.club_name 
                FROM elections e
                LEFT JOIN clubs c ON e.club_id = c.club_id
                WHERE e.{$startCol} <= NOW() AND e.{$endCol} >= NOW()
                ORDER BY e.{$startCol} DESC";
    } else {
        // fallback: no date columns — show all elections
        $sql = "SELECT e.*, c.club_name 
                FROM elections e
                LEFT JOIN clubs c ON e.club_id = c.club_id
                ORDER BY e.created_at DESC";
    }

    $elections = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <div class="container mt-4">
      <h2>Active Elections</h2>
      <ul class="list-group">
        <?php foreach ($elections as $e): ?>
          <li class="list-group-item d-flex justify-content-between align-items-center">
            <div>
              <strong><?= htmlspecialchars($e['title']) ?></strong><br>
              <small class="text-muted"><?= htmlspecialchars($e['club_name'] ?? '—') ?>
                <?php if ($startCol && $endCol): ?>
                  &middot; <?= htmlspecialchars($e[$startCol]) ?> - <?= htmlspecialchars($e[$endCol]) ?>
                <?php endif; ?>
              </small>
            </div>
            <a href="vote.php?election_id=<?= $e['election_id'] ?>" class="btn btn-sm btn-primary">Enter</a>
          </li>
        <?php endforeach; ?>
        <?php if (empty($elections)): ?>
          <li class="list-group-item text-center text-muted">No active elections right now.</li>
        <?php endif; ?>
      </ul>
    </div>

    <?php
    include 'layout/footer.php';
    exit;
}

/* --------------------------
   2) Specific election view: positions + approved candidates
   -------------------------- */
// fetch election
$stmt = $pdo->prepare("SELECT e.*, c.club_name FROM elections e LEFT JOIN clubs c ON e.club_id=c.club_id WHERE e.election_id = ?");
$stmt->execute([$election_id]);
$election = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$election) {
    echo "<div class='container mt-4'><div class='alert alert-warning'>Election not found.</div></div>";
    include 'layout/footer.php';
    exit;
}

// fetch positions
$posStmt = $pdo->prepare("SELECT position_id, position_name FROM election_positions WHERE election_id = ? ORDER BY position_id");
$posStmt->execute([$election_id]);
$positionsRows = $posStmt->fetchAll(PDO::FETCH_ASSOC);

// build positions => candidates list (only approved)
$positions = [];
$candStmt = $pdo->prepare("
    SELECT ec.candidate_id, ec.position_id, ec.manifesto, u.user_id, u.full_name
    FROM election_candidates ec
    JOIN users u ON ec.user_id = u.user_id
    WHERE ec.election_id = ? AND ec.position_id = ? AND ec.status = 'approved'
    ORDER BY u.full_name
");

// prefill
foreach ($positionsRows as $p) {
    $positions[$p['position_id']] = [
        'name' => $p['position_name'],
        'candidates' => []
    ];
    // get candidates for this position
    $candStmt->execute([$election_id, $p['position_id']]);
    $cands = $candStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cands as $c) {
        $positions[$p['position_id']]['candidates'][] = [
            'candidate_id' => $c['candidate_id'],
            'user_id' => $c['user_id'],
            'full_name' => $c['full_name'],
            'manifesto' => $c['manifesto']
        ];
    }
}

// check if election_votes table exists (needed to save votes)
$exists = $pdo->query("SHOW TABLES LIKE 'election_votes'")->fetchColumn();

/* --------------------------
   3) Handle submission (save votes)
   -------------------------- */
$messages = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$exists) {
        $messages[] = ['type'=>'danger','text'=>'Voting is not available: "election_votes" table is missing. Ask admin to create it.'];
    } elseif (empty($_POST['vote']) || !is_array($_POST['vote'])) {
        $messages[] = ['type'=>'warning','text'=>'No votes submitted.'];
    } else {
        $votes = $_POST['vote']; // array position_id => candidate_id
        $pdo->beginTransaction();
        try {
            $receipts = [];
            // first validate each vote
            foreach ($votes as $posIdStr => $candIdStr) {
                $posId = intval($posIdStr);
                $candId = intval($candIdStr);

                // ensure position exists in this election
                if (!isset($positions[$posId])) {
                    throw new Exception("Invalid position selected.");
                }
                // ensure candidate exists and is for this position and approved
                $chk = $pdo->prepare("SELECT 1 FROM election_candidates WHERE candidate_id=? AND election_id=? AND position_id=? AND status='approved' LIMIT 1");
                $chk->execute([$candId, $election_id, $posId]);
                if (!$chk->fetchColumn()) {
                    throw new Exception("Invalid candidate selection detected for position " . htmlspecialchars($positions[$posId]['name']));
                }
                // ensure voter hasn't already voted for this position
                $already = $pdo->prepare("SELECT COUNT(*) FROM election_votes WHERE election_id=? AND position_id=? AND voter_user_id=?");
                $already->execute([$election_id, $posId, $user_id]);
                if ($already->fetchColumn() > 0) {
                    throw new Exception("You have already voted for position: " . htmlspecialchars($positions[$posId]['name']));
                }
            }

            // if all validations pass, insert all votes
            $ins = $pdo->prepare("INSERT INTO election_votes (election_id, position_id, candidate_id, voter_user_id, receipt_code) VALUES (?, ?, ?, ?, ?)");
            foreach ($votes as $posIdStr => $candIdStr) {
                $posId = intval($posIdStr);
                $candId = intval($candIdStr);
                $receipt = 'RCPT-' . substr(hash('sha256', uniqid('', true)), 0, 12);
                $ins->execute([$election_id, $posId, $candId, $user_id, $receipt]);
                $receipts[] = $receipt;
            }

            $pdo->commit();
            $messages[] = ['type'=>'success','text'=>'Your votes were recorded.'];
            // show receipts
            if (!empty($receipts)) {
                $messages[] = ['type'=>'info','text'=>'Receipts: ' . implode(', ', array_map('htmlspecialchars', $receipts))];
            }
        } catch (Exception $ex) {
            $pdo->rollBack();
            $messages[] = ['type'=>'danger','text'=>'Error: ' . htmlspecialchars($ex->getMessage())];
        }
    }
}

/* --------------------------
   4) Render page
   -------------------------- */
?>
<div class="container mt-4">
  <h2>Vote — <?= htmlspecialchars($election['title']) ?></h2>
  <p class="text-muted"><?= htmlspecialchars($election['club_name'] ?? '') ?></p>

  <?php foreach ($messages as $m): ?>
    <div class="alert alert-<?= $m['type'] ?>"><?= $m['text'] ?></div>
  <?php endforeach; ?>

  <?php if (empty($positions)): ?>
    <div class="alert alert-warning">No positions configured for this election.</div>
  <?php else: ?>
    <form method="post">
      <?php foreach ($positions as $posId => $p): ?>
        <div class="card mb-3">
          <div class="card-header"><strong><?= htmlspecialchars($p['name']) ?></strong></div>
          <div class="card-body">
            <?php if (empty($p['candidates'])): ?>
              <p class="text-muted">No approved candidates yet for this position.</p>
            <?php else: ?>
              <?php
                // check if user already voted for this position
                $chk = $pdo->prepare("SELECT COUNT(*) FROM election_votes WHERE election_id=? AND position_id=? AND voter_user_id=?");
                $chk->execute([$election_id, $posId, $user_id]);
                $already = $chk->fetchColumn() > 0;
              ?>
              <?php if ($already): ?>
                <div class="alert alert-info">You already voted for this position.</div>
              <?php else: ?>
                <?php foreach ($p['candidates'] as $cand): ?>
                  <div class="form-check">
                    <input class="form-check-input" type="radio" name="vote[<?= $posId ?>]" id="pos<?= $posId ?>cand<?= $cand['candidate_id'] ?>" value="<?= $cand['candidate_id'] ?>" required>
                    <label class="form-check-label" for="pos<?= $posId ?>cand<?= $cand['candidate_id'] ?>">
                      <?= htmlspecialchars($cand['full_name']) ?>
                      <?php if (!empty($cand['manifesto'])): ?>
                        <br><small class="text-muted"><?= htmlspecialchars($cand['manifesto']) ?></small>
                      <?php endif; ?>
                    </label>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>

      <button type="submit" class="btn btn-success">Submit Votes</button>
    </form>
  <?php endif; ?>
</div>

<?php include 'layout/footer.php'; ?>
