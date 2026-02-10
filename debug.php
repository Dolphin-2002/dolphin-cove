<?php
// TEMPORARY DEBUG FILE - DELETE AFTER FIXING
// Visit: https://dolphin-cove.page.gd/debug.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h2>PHP Debug Info</h2>";
echo "<p><strong>PHP Version:</strong> " . phpversion() . "</p>";

// Test database connection
echo "<h3>Database Connection Test</h3>";

$host = 'sql307.infinityfree.com';
$user = 'if0_41119955';
$pass = 'BF82cLjlhMCesk';
$dbname = 'if0_41119955_dolphin_cove';

echo "<p>Trying to connect to: $host as $user to database $dbname</p>";

try {
    $conn = new mysqli($host, $user, $pass, $dbname);
    if ($conn->connect_error) {
        echo "<p style='color:red'><strong>DB Connection FAILED:</strong> " . $conn->connect_error . "</p>";
        echo "<p>Common fixes:</p>";
        echo "<ul>";
        echo "<li>Check the MySQL hostname in your InfinityFree Control Panel > MySQL Databases</li>";
        echo "<li>Check the database name (it must include your user prefix, e.g. if0_41119955_)</li>";
        echo "<li>Make sure you created the database in the InfinityFree control panel first</li>";
        echo "</ul>";
    } else {
        echo "<p style='color:green'><strong>DB Connection SUCCESS!</strong></p>";
        
        // Check if tables exist
        $result = $conn->query("SHOW TABLES");
        echo "<p><strong>Tables found:</strong> " . $result->num_rows . "</p>";
        echo "<ul>";
        while ($row = $result->fetch_array()) {
            echo "<li>" . $row[0] . "</li>";
        }
        echo "</ul>";
        
        if ($result->num_rows === 0) {
            echo "<p style='color:orange'><strong>No tables found!</strong> You need to import database.sql via phpMyAdmin in your InfinityFree control panel.</p>";
        }
        
        $conn->close();
    }
} catch (Exception $e) {
    echo "<p style='color:red'><strong>Exception:</strong> " . $e->getMessage() . "</p>";
}

echo "<h3>PHP Extensions</h3>";
echo "<p>mysqli: " . (extension_loaded('mysqli') ? '<span style="color:green">✅ Loaded</span>' : '<span style="color:red">❌ Missing</span>') . "</p>";
echo "<p>mbstring: " . (extension_loaded('mbstring') ? '<span style="color:green">✅ Loaded</span>' : '<span style="color:red">❌ Missing</span>') . "</p>";
echo "<p>session: " . (extension_loaded('session') ? '<span style="color:green">✅ Loaded</span>' : '<span style="color:red">❌ Missing</span>') . "</p>";

echo "<h3>File Permissions</h3>";
$dirs = ['uploads', 'uploads/avatars', 'uploads/jobs', 'uploads/posts'];
foreach ($dirs as $dir) {
    $fullPath = __DIR__ . '/' . $dir;
    if (is_dir($fullPath)) {
        echo "<p>$dir: <span style='color:green'>✅ Exists</span> (writable: " . (is_writable($fullPath) ? 'yes' : 'no') . ")</p>";
    } else {
        echo "<p>$dir: <span style='color:orange'>⚠️ Missing</span></p>";
    }
}

echo "<h3>Server Info</h3>";
echo "<p>Server: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'unknown') . "</p>";
echo "<p>Document Root: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'unknown') . "</p>";
echo "<p>HTTP Host: " . ($_SERVER['HTTP_HOST'] ?? 'unknown') . "</p>";

echo "<hr><p style='color:red'><strong>⚠️ DELETE this file after debugging!</strong></p>";
?>
