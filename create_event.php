<?php
// create_event.php
require_once 'inc/config.php';

// Check if user has access to create events
if (!can_access_nav('create_event')) {
    header('Location: index.php');
    exit;
}

// Check if logged in
if (!is_logged_in()) {
    header('Location: index.php');
    exit;
}

$clubs = $pdo->query("SELECT club_id, club_name FROM clubs ORDER BY club_name")->fetchAll(PDO::FETCH_ASSOC);
$msg = '';

// Handle form submission before any output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $club_id = intval($_POST['club_id'] ?? 0);
    $name = trim($_POST['event_name'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $ds = $_POST['date_start'] ?? null;
    $de = $_POST['date_end'] ?? null;
    $loc = trim($_POST['location'] ?? '');

    if ($club_id <= 0 || $name === '') {
        $msg = 'Club and event name are required.';
    } else {
        $stmt = $pdo->prepare("INSERT INTO events (club_id,event_name,description,date_start,date_end,location,status,created_by) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->execute([$club_id, $name, $desc, $ds, $de, $loc, 'Approved', current_user_id()]);
        header('Location: events.php');
        exit;
    }
}

// Now include the header and display the form
include 'layout/header.php';
?>

<h2>Create Event</h2>
<?php if ($msg): ?><div class="alert alert-danger"><?=htmlspecialchars($msg)?></div><?php endif; ?>

<form method="post">
  <div class="mb-3">
    <label class="form-label">Club</label>
    <select name="club_id" class="form-select" required>
      <?php foreach ($clubs as $c): ?>
        <option value="<?=$c['club_id']?>"><?=htmlspecialchars($c['club_name'])?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="mb-3">
    <label class="form-label">Event Name</label>
    <input class="form-control" name="event_name" required>
  </div>
  <div class="mb-3">
    <label class="form-label">Description</label>
    <textarea class="form-control" name="description"></textarea>
  </div>
  <div class="row">
    <div class="col-md-6 mb-3">
      <label class="form-label">Start</label>
      <input type="datetime-local" name="date_start" class="form-control">
    </div>
    <div class="col-md-6 mb-3">
      <label class="form-label">End</label>
      <input type="datetime-local" name="date_end" class="form-control">
    </div>
  </div>
  <div class="mb-3">
    <label class="form-label">Location</label>
    <input class="form-control" name="location">
  </div>
  
  <button class="btn btn-primary">Submit Event</button>
</form>

<?php include 'layout/footer.php'; ?>