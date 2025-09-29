<?php
// clubs.php
include 'layout/header.php';

// Check if user has access to clubs
if (!can_access_nav('clubs')) {
    echo "<div class='alert alert-danger'>You don't have permission to access clubs.</div>";
    include 'layout/footer.php';
    exit;
}

// Join club action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['join_club'])) {
    $club_id = intval($_POST['club_id']);
    $uid = current_user_id();
    $stmt = $pdo->prepare("SELECT 1 FROM club_members WHERE user_id=? AND club_id=?");
    $stmt->execute([$uid, $club_id]);
    if (!$stmt->fetch()) {
        $ins = $pdo->prepare("INSERT INTO club_members(user_id, club_id) VALUES(?,?)");
        $ins->execute([$uid, $club_id]);

        // If user was Student, show role promotion message
        if (current_user_role() === 'Student') {
            echo "<div class='alert alert-success'>Welcome! You have joined the club and your role has been promoted to Club Member.</div>";
        }
    }
    header('Location: clubs.php');
    exit;
}

// Delete club action (Super Admin and Club Adviser)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_club'])) {
    $current_role = get_effective_role();
    if ($current_role === 'Super Admin' || $current_role === 'Club Adviser') {
        $club_id = intval($_POST['club_id']);
        try {
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM club_members WHERE club_id = ?")->execute([$club_id]);
            $pdo->prepare("DELETE FROM events WHERE club_id = ?")->execute([$club_id]);
            $stmt = $pdo->prepare("DELETE FROM clubs WHERE club_id = ?");
            $stmt->execute([$club_id]);
            $pdo->commit();
            echo "<div class='alert alert-success'>Club deleted successfully!</div>";
        } catch (Exception $e) {
            $pdo->rollBack();
            echo "<div class='alert alert-danger'>Error deleting club: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    } else {
        echo "<div class='alert alert-danger'>You don't have permission to delete clubs.</div>";
    }
}

// Fetch clubs with member count, event count, and images
$rows = $pdo->query("
    SELECT c.*, u.full_name as adviser, 
           (SELECT COUNT(*) FROM club_members cm WHERE cm.club_id = c.club_id) as member_count,
           (SELECT COUNT(*) FROM events e WHERE e.club_id = c.club_id AND e.status = 'Approved') as event_count
    FROM clubs c 
    LEFT JOIN users u ON c.adviser_id = u.user_id 
    ORDER BY c.club_name
")->fetchAll(PDO::FETCH_ASSOC);

// Helper function to check if current user is a member
function is_member($club_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT 1 FROM club_members WHERE user_id=? AND club_id=?");
    $stmt->execute([current_user_id(), $club_id]);
    return $stmt->fetch() ? true : false;
}
?>

<h2>Clubs</h2>
<p>All registered clubs.</p>

<div class="row g-4">
  <?php foreach ($rows as $r): ?>
    <div class="col-md-6 col-lg-4">
      <div class="club-card shadow-sm">
        <!-- Cover Image -->
        <?php if (!empty($r['cover_picture'])): ?>
          <div class="club-cover-wrapper">
            <img src="uploads/clubs/<?= htmlspecialchars($r['cover_picture']) ?>" class="club-cover-img" alt="Cover">
          </div>
        <?php endif; ?>

        <div class="club-card-body">
          <!-- Profile image + Club name -->
          <div class="d-flex align-items-center mb-2 gap-3">
            <?php if (!empty($r['profile_picture'])): ?>
              <img src="uploads/clubs/<?= htmlspecialchars($r['profile_picture']) ?>" class="club-profile-img" alt="Profile">
            <?php else: ?>
              <div class="club-profile-placeholder">
                <i class="bi bi-people"></i>
              </div>
            <?php endif; ?>

            <div>
              <h5 class="club-name"><?= htmlspecialchars($r['club_name']) ?></h5>
              <small class="club-adviser">Adviser: <?= htmlspecialchars($r['adviser'] ?? '—') ?></small>
            </div>
          </div>

          <!-- Club description -->
          <?php if (!empty($r['description'])): ?>
            <p class="club-description"><?= nl2br(htmlspecialchars(substr($r['description'], 0, 150))) ?>
              <?php if (strlen($r['description']) > 150): ?>
                <a href="club_details.php?id=<?= $r['club_id'] ?>" class="text-primary">...read more</a>
              <?php endif; ?>
            </p>
          <?php endif; ?>

          <!-- Stats -->
          <div class="d-flex justify-content-between my-3">
            <div class="text-center">
              <div class="stat-number"><?= $r['member_count'] ?></div>
              <div class="stat-label">Members</div>
            </div>
            <div class="text-center">
              <div class="stat-number"><?= $r['event_count'] ?></div>
              <div class="stat-label">Events</div>
            </div>
            <div class="text-center">
              <div class="stat-number">0</div>
              <div class="stat-label">Posts</div>
            </div>
          </div>

          <!-- Actions -->
          <div class="d-flex justify-content-center gap-2">
            <?php if (!is_member($r['club_id'])): ?>
              <form method="post" class="mb-0">
                <input type="hidden" name="club_id" value="<?= $r['club_id'] ?>">
                <button type="submit" name="join_club" class="btn-join">
                  <i class="bi bi-person-plus"></i> Join Club
                </button>
              </form>
            <?php else: ?>
              <button class="btn btn-outline-secondary btn-sm" disabled>Member</button>
            <?php endif; ?>

            <?php 
              $current_role = get_effective_role();
              if ($current_role === 'Super Admin' || $current_role === 'Club Adviser'): 
            ?>
              <button class="btn btn-outline-danger btn-sm" onclick="confirmDelete(<?= $r['club_id'] ?>, '<?= htmlspecialchars(addslashes($r['club_name'])) ?>')">
                <i class="bi bi-trash"></i> Delete
              </button>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<script>
function confirmDelete(clubId, clubName) {
  if (confirm(`Are you sure you want to delete "${clubName}"? This action cannot be undone.`)) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
      <input type="hidden" name="club_id" value="${clubId}">
      <input type="hidden" name="delete_club" value="1">
    `;
    document.body.appendChild(form);
    form.submit();
  }
}
</script>

<style>
.club-card { background: #fff; border-radius: 12px; overflow: hidden; transition: all 0.3s ease; }
.club-card:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,0.15); }
body.dark .club-card { background: #1e293b; color: #f1f5f9; }
.club-cover-wrapper { height: 120px; overflow: hidden; }
.club-cover-img { width: 100%; height: 100%; object-fit: cover; }
.club-card-body { padding: 15px 20px; }
.club-profile-img, .club-profile-placeholder { width: 60px; height: 60px; border-radius: 50%; object-fit: cover; border: 2px solid #2563eb; display: flex; align-items: center; justify-content: center; background: #e2e8f0; color: #2563eb; font-size: 1.25rem; }
.club-name { margin: 0; font-size: 1.1rem; font-weight: 600; }
.club-adviser { font-size: 0.8rem; color: #64748b; }
.club-description { font-size: 0.875rem; color: #475569; }
.stat-number { font-weight: 600; font-size: 1rem; color: #2563eb; }
.stat-label { font-size: 0.75rem; color: #64748b; }
.btn-join { display: inline-flex; align-items: center; gap: 5px; background: #2563eb; color: #fff; border: none; padding: 6px 12px; border-radius: 8px; font-size: 0.85rem; font-weight: 500; cursor: pointer; transition: all 0.2s ease; }
.btn-join:hover { background: #1d4ed8; transform: translateY(-1px); }
@media (max-width: 768px) { .club-card { margin-bottom: 20px; } .btn-join { width: 100%; justify-content: center; } }
</style>

<?php include 'layout/footer.php'; ?>
