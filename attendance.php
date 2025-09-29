<?php
// attendance.php

// Include config first to establish database connection
require_once 'inc/config.php';

// Check if user is logged in and has proper access
if (!is_logged_in()) {
    header('Location: index.php');
    exit;
}

// 🚫 **REMOVE CLUB MEMBER ACCESS TO ATTENDANCE**
if (!can_access_nav('attendance')) {
    header('Location: dashboard.php');
    exit;
}

$event_id = intval($_GET['event_id'] ?? 0);

// Mark attendance - handle this before including header
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark'])) {
    $uid = intval($_POST['user_id']);
    $status = $_POST['status'] ?? 'Present';
    
    try {
        // Use INSERT ... ON DUPLICATE KEY UPDATE (attendance table has UNIQUE(event_id, user_id))
        $stmt = $pdo->prepare("INSERT INTO attendance (event_id,user_id,status) VALUES (?,?,?) ON DUPLICATE KEY UPDATE status = VALUES(status), time_in = CURRENT_TIMESTAMP");
        $stmt->execute([$event_id, $uid, $status]);
        
        // Redirect to prevent form resubmission
        header("Location: attendance.php?event_id={$event_id}");
        exit;
    } catch (PDOException $e) {
        // Handle database errors
        error_log("Attendance error: " . $e->getMessage());
        // Continue to show the page with error message
    }
}

// Now include the header after all header operations are complete
include 'layout/header.php';

if ($event_id <= 0) {
    echo "<div class='alert alert-warning'>No event selected.</div>";
    include 'layout/footer.php';
    exit;
}

// Load event and members
try {
    $event = $pdo->prepare("SELECT * FROM events WHERE event_id = ?");
    $event->execute([$event_id]);
    $ev = $event->fetch(PDO::FETCH_ASSOC);
    
    if (!$ev) {
        echo "<div class='alert alert-danger'>Event not found.</div>";
        include 'layout/footer.php';
        exit;
    }

    $members = $pdo->prepare("SELECT u.user_id,u.full_name FROM users u JOIN club_members m ON u.user_id = m.user_id WHERE m.club_id = ?");
    $members->execute([$ev['club_id']]);
    $members = $members->fetchAll(PDO::FETCH_ASSOC);

    // existing attendance
    $att = $pdo->prepare("SELECT * FROM attendance WHERE event_id = ?");
    $att->execute([$event_id]);
    $attend = $att->fetchAll(PDO::FETCH_ASSOC);
    $present = [];
    foreach ($attend as $a) $present[$a['user_id']] = $a;
    
} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>Database error: " . htmlspecialchars($e->getMessage()) . "</div>";
    include 'layout/footer.php';
    exit;
}
?>

<h2>Attendance for <?=htmlspecialchars($ev['event_name'])?></h2>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success">Attendance updated successfully!</div>
<?php endif; ?>

<table class="table">
  <thead><tr><th>Name</th><th>Status</th><th>Action</th></tr></thead>
  <tbody>
    <?php foreach ($members as $m): ?>
      <tr>
        <td><?=htmlspecialchars($m['full_name'])?></td>
        <td><?=htmlspecialchars($present[$m['user_id']]['status'] ?? '—')?></td>
        <td>
          <form method="post" style="display:inline">
            <input type="hidden" name="user_id" value="<?=$m['user_id']?>">
            <select name="status" class="form-select form-select-sm d-inline-block" style="width:120px">
              <option value="Present" <?= (isset($present[$m['user_id']]['status']) && $present[$m['user_id']]['status'] === 'Present') ? 'selected' : '' ?>>Present</option>
              <option value="Absent" <?= (isset($present[$m['user_id']]['status']) && $present[$m['user_id']]['status'] === 'Absent') ? 'selected' : '' ?>>Absent</option>
              <option value="Excused" <?= (isset($present[$m['user_id']]['status']) && $present[$m['user_id']]['status'] === 'Excused') ? 'selected' : '' ?>>Excused</option>
            </select>
            <button name="mark" class="btn btn-sm btn-primary">Mark</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<?php include 'layout/footer.php'; ?>
