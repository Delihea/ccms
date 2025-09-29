<?php
// events.php
include 'layout/header.php';

// Check if user has access to this page
if (!can_access_nav('events')) {
    echo "<div class='alert alert-danger'>You don't have permission to access this page.</div>";
    include 'layout/footer.php';
    exit;
}

// Event Registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_event'])) {
    $eid = intval($_POST['event_id']);
    $user_id = current_user_id();
    
    // Check if already registered
    $check = $pdo->prepare("SELECT * FROM event_registrations WHERE event_id = ? AND user_id = ?");
    $check->execute([$eid, $user_id]);
    
    if (!$check->fetch()) {
        $stmt = $pdo->prepare("INSERT INTO event_registrations (event_id, user_id, registered_at) VALUES (?, ?, NOW())");
        $stmt->execute([$eid, $user_id]);
        echo "<div class='alert alert-success'>Successfully registered for the event!</div>";
    } else {
        echo "<div class='alert alert-warning'>You are already registered for this event.</div>";
    }
}

// Event Drop functionality
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['drop_event'])) {
    $eid = intval($_POST['event_id']);
    $user_id = current_user_id();
    
    // Check if user has permission to drop events (Super Admin, Club Adviser, or Club Member)
    $role = current_user_role();
    $can_drop = ($role === 'Super Admin' || $role === 'Club Adviser' || $role === 'Club Member');
    
    if ($can_drop) {
        $stmt = $pdo->prepare("DELETE FROM event_registrations WHERE event_id = ? AND user_id = ?");
        $stmt->execute([$eid, $user_id]);
        echo "<div class='alert alert-success'>Successfully dropped from the event!</div>";
    } else {
        echo "<div class='alert alert-danger'>You don't have permission to drop events.</div>";
    }
}

// Approve action (Club Adviser/Club Officer roles)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_event'])) {
    $eid = intval($_POST['event_id']);
    $stmt = $pdo->prepare("UPDATE events SET status = 'Approved', approved_by = ? WHERE event_id = ?");
    $stmt->execute([current_user_id(), $eid]);
    header('Location: events.php');
    exit;
}

// 🗑️ **DELETE EVENT FUNCTIONALITY**
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_event'])) {
    $eid = intval($_POST['event_id']);
    $role = current_user_role();
    
    // Super Admin and Club Adviser can delete events
    if ($role === 'Super Admin' || $role === 'Club Adviser') {
        // First delete any registrations for this event
        $stmt = $pdo->prepare("DELETE FROM event_registrations WHERE event_id = ?");
        $stmt->execute([$eid]);
        
        // Then delete the event itself
        $stmt = $pdo->prepare("DELETE FROM events WHERE event_id = ?");
        $stmt->execute([$eid]);
        
        echo "<div class='alert alert-success'>Event successfully deleted!</div>";
    } else {
        echo "<div class='alert alert-danger'>You don't have permission to delete events.</div>";
    }
}

// Fetch events with registration status for current user
$user_id = current_user_id();
$stmt = $pdo->prepare("
    SELECT e.*, c.club_name, u.full_name as creator,
           CASE WHEN er.user_id IS NOT NULL THEN 1 ELSE 0 END as is_registered
    FROM events e 
    LEFT JOIN clubs c ON e.club_id=c.club_id 
    LEFT JOIN users u ON e.created_by=u.user_id
    LEFT JOIN event_registrations er ON e.event_id = er.event_id AND er.user_id = ?
    WHERE e.status = 'Approved'
    ORDER BY e.date_start DESC
");
$stmt->execute([$user_id]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<h2>Events</h2>

<table class="table table-striped">
  <thead><tr><th>Event</th><th>Club</th><th>Date</th><th>Status</th><th>Registered</th><th>Actions</th></tr></thead>
  <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?=htmlspecialchars($r['event_name'])?></td>
        <td><?=htmlspecialchars($r['club_name'])?></td>
        <td><?=htmlspecialchars($r['date_start'])?></td>
        <td><?=htmlspecialchars($r['status'])?></td>
        <td><?= $r['is_registered'] ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>' ?></td>
        <td>
          <?php if (in_array($role, ['Club Adviser','Club Officer']) && $r['status'] === 'Proposed'): ?>
            <form method="post" style="display:inline">
              <input type="hidden" name="event_id" value="<?=$r['event_id']?>">
              <button name="approve_event" class="btn btn-sm btn-primary">Approve</button>
            </form>
          <?php endif; ?>
          
          <?php if (!$r['is_registered']): ?>
            <form method="post" style="display:inline">
              <input type="hidden" name="event_id" value="<?=$r['event_id']?>">
              <button name="register_event" class="btn btn-sm btn-success">Register</button>
            </form>
          <?php else: ?>
            <form method="post" style="display:inline">
              <input type="hidden" name="event_id" value="<?=$r['event_id']?>">
              <button name="drop_event" class="btn btn-sm btn-warning" onclick="return confirm('Are you sure you want to drop this event?')">Drop</button>
            </form>
          <?php endif; ?>
          
          <a class="btn btn-sm btn-outline-secondary" href="attendance.php?event_id=<?=$r['event_id']?>">Attendance</a>
          
          <!-- 🗑️ **DELETE BUTTON** - Super Admin and Club Adviser -->
          <?php if ($role === 'Super Admin' || $role === 'Club Adviser'): ?>
            <form method="post" style="display:inline">
              <input type="hidden" name="event_id" value="<?=$r['event_id']?>">
              <button name="delete_event" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this event? This action cannot be undone.')">Delete</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<?php include 'layout/footer.php'; ?>
