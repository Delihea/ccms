<?php
// Simple debug script to check events table
require_once 'inc/config.php';

echo "<h2>Events Table Debug Information</h2>";
echo "<hr>";

try {
    // Check total events
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM events");
    $total = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p><strong>Total events in database:</strong> " . $total['total'] . "</p>";
    
    // Check events by status
    $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM events GROUP BY status");
    $statuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<p><strong>Events by status:</strong></p><ul>";
    foreach ($statuses as $status) {
        echo "<li>" . htmlspecialchars($status['status']) . ": " . $status['count'] . "</li>";
    }
    echo "</ul>";
    
    // Check current date/time
    $stmt = $pdo->query("SELECT CURDATE() as `current_date`, NOW() as `current_datetime`");
    $current = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p><strong>Current date:</strong> " . $current['current_date'] . "</p>";
    echo "<p><strong>Current datetime:</strong> " . $current['current_datetime'] . "</p>";
    
    // Check events by date
    $stmt = $pdo->query("SELECT COUNT(*) as future FROM events WHERE date_start >= CURDATE()");
    $future = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p><strong>Future events (date_start >= CURDATE()):</strong> " . $future['future'] . "</p>";
    
    $stmt = $pdo->query("SELECT COUNT(*) as approved_future FROM events WHERE date_start >= CURDATE() AND status = 'Approved'");
    $approved_future = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p><strong>Approved future events:</strong> " . $approved_future['approved_future'] . "</p>";
    
    // Show sample events (last 5)
    echo "<h3>Sample Events (Last 5 created)</h3>";
    $stmt = $pdo->query("SELECT event_name, date_start, date_end, status, created_by FROM events ORDER BY event_id DESC LIMIT 5");
    $sample_events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($sample_events) > 0) {
        echo "<table border='1' cellpadding='5' cellspacing='0'>";
        echo "<tr><th>Event Name</th><th>Start Date</th><th>End Date</th><th>Status</th><th>Created By</th></tr>";
        foreach ($sample_events as $event) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($event['event_name']) . "</td>";
            echo "<td>" . $event['date_start'] . "</td>";
            echo "<td>" . $event['date_end'] . "</td>";
            echo "<td>" . htmlspecialchars($event['status']) . "</td>";
            echo "<td>" . htmlspecialchars($event['created_by']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No events found in database.</p>";
    }
    // Add these queries to your debug_events.php after line 55:

// Check if clubs exist
$stmt = $pdo->query("SELECT COUNT(*) as total_clubs FROM clubs");
$clubs = $stmt->fetch(PDO::FETCH_ASSOC);
echo "<p><strong>Total clubs in database:</strong> " . $clubs['total_clubs'] . "</p>";

// Check events with club linking (this is what dashboard uses)
$stmt = $pdo->query("
    SELECT COUNT(*) as dashboard_events 
    FROM events e 
    JOIN clubs c ON e.club_id = c.club_id 
    WHERE e.date_start >= CURDATE() AND e.status = 'Approved'
");
$dashboard_events = $stmt->fetch(PDO::FETCH_ASSOC);
echo "<p><strong>Events that meet dashboard requirements:</strong> " . $dashboard_events['dashboard_events'] . "</p>";

// Show detailed event info with club linking
echo "<h3>Detailed Event Analysis</h3>";
$stmt = $pdo->query("
    SELECT e.event_name, e.date_start, e.date_end, e.status, e.club_id, c.club_name 
    FROM events e 
    LEFT JOIN clubs c ON e.club_id = c.club_id 
    ORDER BY e.event_id DESC LIMIT 5
");
$detailed_events = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table border='1' cellpadding='5' cellspacing='0'>";
echo "<tr><th>Event Name</th><th>Start Date</th><th>Status</th><th>Club ID</th><th>Club Name</th><th>Valid for Dashboard?</th></tr>";
foreach ($detailed_events as $event) {
    $is_valid = ($event['status'] == 'Approved' && $event['date_start'] >= date('Y-m-d') && $event['club_name'] != null) ? '✅ YES' : '❌ NO';
    echo "<tr>";
    echo "<td>" . htmlspecialchars($event['event_name']) . "</td>";
    echo "<td>" . $event['date_start'] . "</td>";
    echo "<td>" . htmlspecialchars($event['status']) . "</td>";
    echo "<td>" . $event['club_id'] . "</td>";
    echo "<td>" . htmlspecialchars($event['club_name'] ?? 'NO CLUB') . "</td>";
    echo "<td><strong>" . $is_valid . "</strong></td>";
    echo "</tr>";
}
echo "</table>";
    // Check events happening today
    $stmt = $pdo->query("SELECT COUNT(*) as today_events FROM events WHERE DATE(date_start) = CURDATE() AND status = 'Approved'");
    $today_events = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p><strong>Events happening TODAY:</strong> " . $today_events['today_events'] . "</p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Database error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='dashboard.php'>Back to Dashboard</a></p>";
?>