<?php
include 'layout/header.php';

// 🕐 **IMPROVED TIME CALCULATION FUNCTION**
function time_elapsed_string($datetime, $full = false) {
    // Ensure we're using the configured timezone
    $timezone = new DateTimeZone('Asia/Manila'); // Match your timezone
    $now = new DateTime('now', $timezone);
    $ago = new DateTime($datetime, $timezone);
    
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    );
    
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}

// Initialize variables
$totalClubs = 0;
$totalMembers = 0;
$upcomingEvents = 0;
$upcomingEventsList = [];
$recentActivities = [];

try {
    // Fetch basic stats
    $totalClubs = $pdo->query("SELECT COUNT(*) FROM clubs")->fetchColumn();
    $totalMembers = $pdo->query("SELECT COUNT(*) FROM users WHERE role IN ('Student','Club Member')")->fetchColumn();
    
    // Fetch upcoming events count - includes today's events and future events
    $stmt = $pdo->query("SELECT COUNT(*) FROM events WHERE date_start >= CURDATE() AND status = 'Approved'");
    $upcomingEvents = $stmt->fetchColumn();
    
    // Fetch upcoming events for dashboard - shows events from today onwards
    // Prioritizes events created by Super Admin and Club Adviser
    $effective_role = get_effective_role();
    
    // First, let's show all approved upcoming events (today and future)
    $stmt = $pdo->query("
        SELECT e.event_name, e.date_start, e.date_end, c.club_name, e.created_by, e.created_at
        FROM events e 
        JOIN clubs c ON e.club_id = c.club_id 
        WHERE e.date_start >= CURDATE() AND e.status = 'Approved'
        ORDER BY 
            e.date_start ASC,
            e.created_at DESC
        LIMIT 10
    ");
    $upcomingEventsList = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // If no upcoming events, show recent events (last 7 days) as fallback
    if (empty($upcomingEventsList)) {
        $stmt = $pdo->query("
            SELECT e.event_name, e.date_start, e.date_end, c.club_name, e.created_by, e.created_at
            FROM events e 
            JOIN clubs c ON e.club_id = c.club_id 
            WHERE e.date_start >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) 
            AND e.status = 'Approved'
            ORDER BY e.date_start DESC
            LIMIT 5
        ");
        $upcomingEventsList = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Fetch recent activities (Facebook-like news feed)
    // Combine club creations and event creations from the last 30 days
    $stmt = $pdo->query("
        SELECT 
            'club' as type,
            club_name as title,
            'New club created' as content,
            created_at,
            NULL as event_date
        FROM clubs 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        
        UNION ALL
        
        SELECT 
            'event' as type,
            event_name as title,
            'New event created' as content,
            created_at,
            date_start as event_date
        FROM events 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) 
        AND status = 'Approved'
        
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $recentActivities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    // Handle database errors gracefully
    error_log("Dashboard error: " . $e->getMessage());
}
?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">

<style>
:root {
  --primary-color: #2563eb;
  --secondary-color: #64748b;
  --success-color: #10b981;
  --warning-color: #f59e0b;
  --danger-color: #ef4444;
  --info-color: #3b82f6;
  --light-bg: #f8fafc;
  --dark-bg: #0f172a;
  --light-card: #ffffff;
  --dark-card: #1e293b;
  --light-text: #1e293b;
  --dark-text: #f1f5f9;
  --border-color: #e2e8f0;
  --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
  --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
}

body {
  background-color: var(--light-bg);
  color: var(--light-text);
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  transition: background-color 0.3s ease, color 0.3s ease;
}

body[data-theme="dark"] {
  background-color: var(--dark-bg);
  color: var(--dark-text);
}

/* Modern Card Design */
.modern-card {
  background: var(--light-card);
  border: 1px solid var(--border-color);
  border-radius: 12px;
  box-shadow: var(--shadow);
  transition: all 0.3s ease;
  overflow: hidden;
}

body[data-theme="dark"] .modern-card {
  background: var(--dark-card);
  border-color: #334155;
}

.modern-card:hover {
  box-shadow: var(--shadow-lg);
  transform: translateY(-2px);
}

/* Stat Cards */
.stat-card {
  background: linear-gradient(135deg, var(--primary-color) 0%, #3b82f6 100%);
  color: white;
  border: none;
}

.stat-card.success {
  background: linear-gradient(135deg, var(--success-color) 0%, #059669 100%);
}

.stat-card.warning {
  background: linear-gradient(135deg, var(--warning-color) 0%, #d97706 100%);
}

.stat-card.info {
  background: linear-gradient(135deg, var(--info-color) 0%, #2563eb 100%);
}

.stat-icon {
  font-size: 2.5rem;
  opacity: 0.8;
}

/* Timeline */
.timeline {
  position: relative;
  padding-left: 2rem;
}

.timeline::before {
  content: '';
  position: absolute;
  left: 0;
  top: 0;
  bottom: 0;
  width: 2px;
  background: linear-gradient(to bottom, var(--primary-color), transparent);
}

.timeline-item {
  position: relative;
  padding-bottom: 1.5rem;
}

.timeline-item::before {
  content: '';
  position: absolute;
  left: -2rem;
  top: 0.5rem;
  width: 12px;
  height: 12px;
  border-radius: 50%;
  background: var(--primary-color);
  border: 3px solid var(--light-card);
  box-shadow: 0 0 0 3px var(--primary-color);
}

body[data-theme="dark"] .timeline-item::before {
  border-color: var(--dark-card);
}

/* Activity Feed (Facebook-like) */
.activity-feed {
  padding: 0;
}

.activity-item {
  display: flex;
  align-items: flex-start;
  margin-bottom: 1.5rem;
  padding-bottom: 1.5rem;
  border-bottom: 1px solid var(--border-color);
}

.activity-item:last-child {
  margin-bottom: 0;
  padding-bottom: 0;
  border-bottom: none;
}

.activity-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--primary-color), #3b82f6);
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 1.2rem;
  margin-right: 1rem;
  flex-shrink: 0;
}

.activity-content {
  flex: 1;
}

.activity-header {
  display: flex;
  align-items: center;
  margin-bottom: 0.5rem;
}

.activity-title {
  font-weight: 600;
  color: var(--light-text);
  margin: 0;
  font-size: 0.95rem;
}

body[data-theme="dark"] .activity-title {
  color: var(--dark-text);
}

.activity-time {
  color: var(--secondary-color);
  font-size: 0.8rem;
  margin-left: auto;
}

.activity-description {
  color: var(--secondary-color);
  font-size: 0.9rem;
  margin: 0;
}

.activity-type-badge {
  display: inline-block;
  padding: 0.25rem 0.5rem;
  border-radius: 12px;
  font-size: 0.7rem;
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.activity-type-badge.club {
  background: rgba(16, 185, 129, 0.1);
  color: var(--success-color);
}

.activity-type-badge.event {
  background: rgba(245, 158, 11, 0.1);
  color: var(--warning-color);
}

/* Buttons */
.btn-modern {
  border-radius: 8px;
  font-weight: 500;
  padding: 0.5rem 1rem;
  transition: all 0.2s ease;
  border: none;
}

.btn-modern:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

/* Typography */
h1, h2, h3, h4, h5, h6 {
  font-weight: 600;
  letter-spacing: -0.025em;
}

.display-4 {
  font-weight: 700;
  background: linear-gradient(135deg, var(--primary-color), #8b5cf6);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

/* Animations */
@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.fade-in {
  animation: fadeInUp 0.6s ease-out;
}

/* Responsive */
@media (max-width: 768px) {
  .container-fluid {
    padding: 1rem;
  }
  
  .stat-icon {
    font-size: 2rem;
  }
  
  .activity-item {
    flex-direction: column;
    align-items: flex-start;
  }
  
  .activity-avatar {
    margin-bottom: 0.5rem;
  }
}
</style>

<div class="container-fluid py-4 fade-in">
  <!-- Welcome Section -->
  <div class="row mb-5">
    <div class="col-12">
      <div class="text-center mb-4">
        <h1 class="display-4 mb-2">Welcome back, <?= htmlspecialchars($name) ?>!</h1>
      </div>
    </div>
  </div>

  <!-- Stats Cards -->
  <div class="row mb-5 g-4">
    <div class="col-xl-3 col-md-6">
      <div class="card modern-card stat-card h-100">
        <div class="card-body d-flex align-items-center">
          <div class="flex-shrink-0">
            <i class="bi bi-building stat-icon"></i>
          </div>
          <div class="flex-grow-1 ms-3">
            <h5 class="card-title mb-1 opacity-75">Total Clubs</h5>
            <p class="fs-3 mb-0 fw-bold"><?= $totalClubs ?></p>
          </div>
        </div>
      </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
      <div class="card modern-card stat-card success h-100">
        <div class="card-body d-flex align-items-center">
          <div class="flex-shrink-0">
            <i class="bi bi-people stat-icon"></i>
          </div>
          <div class="flex-grow-1 ms-3">
            <h5 class="card-title mb-1 opacity-75">Active Members</h5>
            <p class="fs-3 mb-0 fw-bold"><?= $totalMembers ?></p>
          </div>
        </div>
      </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
      <div class="card modern-card stat-card warning h-100">
        <div class="card-body d-flex align-items-center">
          <div class="flex-shrink-0">
            <i class="bi bi-calendar-event stat-icon"></i>
          </div>
          <div class="flex-grow-1 ms-3">
            <h5 class="card-title mb-1 opacity-75">Upcoming Events</h5>
            <p class="fs-3 mb-0 fw-bold"><?= $upcomingEvents ?></p>
          </div>
        </div>
      </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
      <div class="card modern-card stat-card info h-100">
        <div class="card-body d-flex align-items-center">
          <div class="flex-shrink-0">
            <i class="bi bi-lightning stat-icon"></i>
          </div>
          <div class="flex-grow-1 ms-3">
            <h5 class="card-title mb-1 opacity-75">Recent Updates</h5>
            <p class="fs-3 mb-0 fw-bold"><?= count($recentActivities) ?></p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Main Content -->
  <div class="row g-4">
    <div class="col-lg-8">
      <div class="card modern-card h-100">
        <div class="card-header bg-transparent border-0 pb-0">
          <h4 class="card-title mb-0">
            <i class="bi bi-calendar-check text-primary me-2"></i><?= empty($upcomingEventsList) ? 'Recent Events' : 'Upcoming Events' ?>
          </h4>
        </div>
        <div class="card-body">
          <?php if (!empty($upcomingEventsList)): ?>
            <div class="timeline">
              <?php foreach ($upcomingEventsList as $event): ?>
                <div class="timeline-item">
                  <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                      <h6 class="fw-semibold mb-1"><?= htmlspecialchars($event['event_name']) ?></h6>
                      <p class="text-muted mb-2">
                        <i class="bi bi-building me-1"></i><?= htmlspecialchars($event['club_name']) ?>
                      </p>
                      <small class="text-muted">
                        <i class="bi bi-clock me-1"></i><?= date('M j, Y \a\t g:i A', strtotime($event['date_start'])) ?>
                      </small>
                    </div>
                    <a href="events.php" class="btn btn-sm btn-outline-primary btn-modern">
                      <i class="bi bi-eye me-1"></i>View
                    </a>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div class="text-center py-5">
              <div class="mb-4">
                <i class="bi bi-calendar-x display-1 text-muted"></i>
              </div>
              <h5 class="text-muted">No events found</h5>
              <p class="text-muted">Create a new event to get started</p>
              <?php if (can_access_nav('create_event')): ?>
                <a href="create_event.php" class="btn btn-primary btn-modern">
                  <i class="bi bi-calendar-plus me-1"></i>Create Event
                </a>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card modern-card h-100">
        <div class="card-header bg-transparent border-0 pb-0">
          <h4 class="card-title mb-0">
            <i class="bi bi-activity text-success me-2"></i>Recent Activity
          </h4>
        </div>
        <div class="card-body">
          <?php if (!empty($recentActivities)): ?>
            <div class="activity-feed">
              <?php foreach ($recentActivities as $activity): ?>
                <div class="activity-item">
                  <div class="activity-avatar">
                    <i class="bi <?= $activity['type'] == 'club' ? 'bi-people' : 'bi-calendar-event' ?>"></i>
                  </div>
                  <div class="activity-content">
                    <div class="activity-header">
                      <h6 class="activity-title"><?= htmlspecialchars($activity['title']) ?></h6>
                      <small class="activity-time" data-datetime="<?= $activity['created_at'] ?>">
                        <?= time_elapsed_string($activity['created_at']) ?>
                      </small>
                    </div>
                    <p class="activity-description"><?= htmlspecialchars($activity['content']) ?></p>
                    <span class="activity-type-badge <?= $activity['type'] ?>">
                      <?= $activity['type'] ?>
                    </span>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div class="text-center py-5">
              <i class="bi bi-bell-slash display-1 text-muted"></i>
              <h5 class="text-muted mt-3">No recent activity</h5>
              <p class="text-muted">Check back later for updates</p>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// Time elapsed function for real-time updates
function time_elapsed_string_js(datetime) {
    const now = new Date();
    const ago = new Date(datetime);
    const diff = now - ago;
    const seconds = Math.floor(diff / 1000);
    const minutes = Math.floor(seconds / 60);
    const hours = Math.floor(minutes / 60);
    const days = Math.floor(hours / 24);
    const weeks = Math.floor(days / 7);
    const months = Math.floor(days / 30);
    const years = Math.floor(days / 365);

    if (years > 0) return years + (years === 1 ? ' year' : ' years') + ' ago';
    if (months > 0) return months + (months === 1 ? ' month' : ' months') + ' ago';
    if (weeks > 0) return weeks + (weeks === 1 ? ' week' : ' weeks') + ' ago';
    if (days > 0) return days + (days === 1 ? ' day' : ' days') + ' ago';
    if (hours > 0) return hours + (hours === 1 ? ' hour' : ' hours') + ' ago';
    if (minutes > 0) return minutes + (minutes === 1 ? ' minute' : ' minutes') + ' ago';
    return seconds <= 1 ? 'just now' : seconds + ' seconds ago';
}

// Update timestamps every minute
setInterval(() => {
    document.querySelectorAll('[data-datetime]').forEach(el => {
        const dt = el.getAttribute('data-datetime');
        el.textContent = time_elapsed_string_js(dt);
    });
}, 60000);
</script>

<?php include 'layout/footer.php'; ?>