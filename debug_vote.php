<?php
// debug_vote.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'inc/config.php';   // must be the same require you use in vote.php

try {
    // show which DB we're connected to
    $dbName = $pdo->query("SELECT DATABASE()")->fetchColumn();
    echo "<h2>Connected database:</h2><pre>" . htmlspecialchars($dbName) . "</pre>";

    // show clubs table columns
    echo "<h2>SHOW COLUMNS FROM clubs</h2>";
    $cols = $pdo->query("SHOW COLUMNS FROM clubs")->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>" . htmlspecialchars(print_r($cols, true)) . "</pre>";

    // show elections table columns
    echo "<h2>SHOW COLUMNS FROM elections</h2>";
    $cols2 = $pdo->query("SHOW COLUMNS FROM elections")->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>" . htmlspecialchars(print_r($cols2, true)) . "</pre>";

    // attempt the query we want
    $sql = "
        SELECT e.*, c.club_name
        FROM elections e
        LEFT JOIN clubs c ON e.club_id = c.club_id
        WHERE e.voting_start <= NOW()
          AND e.voting_end >= NOW()
          AND e.status = 'active'
        ORDER BY e.voting_start DESC
        LIMIT 10
    ";
    echo "<h2>Test SQL:</h2><pre>" . htmlspecialchars($sql) . "</pre>";

    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    echo "<h2>Query result (first rows):</h2><pre>" . htmlspecialchars(print_r($rows, true)) . "</pre>";
} catch (PDOException $ex) {
    echo "<h2>PDO Exception:</h2><pre>" . htmlspecialchars($ex->getMessage()) . "</pre>";
    if (isset($sql)) {
        echo "<h3>SQL used:</h3><pre>" . htmlspecialchars($sql) . "</pre>";
    }
}
