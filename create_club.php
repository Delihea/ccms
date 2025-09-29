<?php
// create_club.php
require_once 'inc/config.php';

// Check if user has access to create clubs
if (!can_access_nav('create_club')) {
    header('Location: index.php');
    exit;
}

// Check if logged in
if (!is_logged_in()) {
    header('Location: index.php');
    exit;
}

$msg = '';
$advisers = $pdo->query("SELECT user_id, full_name FROM users WHERE role='Adviser' OR role='Club Adviser' ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission before any output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['club_name'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $adv = intval($_POST['adviser'] ?? 0) ?: null;

    if ($name === '') {
        $msg = 'Club name is required.';
    } else {
        $ins = $pdo->prepare("INSERT INTO clubs (club_name, description, bio, adviser_id) VALUES (?, ?, ?, ?)");
        $ins->execute([$name, $desc, $bio, $adv]);
        header('Location: clubs.php');
        exit;
    }
}

// Now include the header and display the form
include 'layout/header.php';
?>

<style>
  .club-form-section {
    background: white;
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
  }
  
  body.dark .club-form-section {
    background: #1a1a1a;
    box-shadow: 0 2px 10px rgba(0,0,0,0.3);
  }
</style>

<h2>Create Club</h2>
<?php if ($msg): ?><div class="alert alert-danger"><?=htmlspecialchars($msg)?></div><?php endif; ?>

<form method="post">
  <div class="club-form-section">
    <h4>Basic Information</h4>
    <div class="mb-3">
      <label class="form-label">Club Name *</label>
      <input class="form-control" name="club_name" required>
    </div>
    <div class="mb-3">
      <label class="form-label">Description</label>
      <textarea class="form-control" name="description" rows="3"></textarea>
    </div>
    <div class="mb-3">
      <label class="form-label">Bio (Short Description)</label>
      <textarea class="form-control" name="bio" rows="2" placeholder="A brief bio about your club..."></textarea>
    </div>
    <div class="mb-3">
      <label class="form-label">Adviser</label>
      <select class="form-select" name="adviser">
        <option value="">-- none --</option>
        <?php foreach ($advisers as $a): ?>
          <option value="<?=$a['user_id']?>"><?=htmlspecialchars($a['full_name'])?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
  
  <button class="btn btn-primary btn-lg">Create Club</button>
</form>

<?php include 'layout/footer.php'; ?>