<?php
// Show all errors
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>PHP Version: " . phpversion() . "</h2>";

// Check mysqli extension
if (extension_loaded('mysqli')) {
    echo "<p style='color:green'>✅ mysqli extension loaded.</p>";
} else {
    echo "<p style='color:red'>❌ mysqli extension NOT loaded. Enable it in php.ini.</p>";
    exit;
}

// Test DB connection with your credentials
$host = 'localhost';
$user = 'lchwnsbw_helpto_db';
$pass = '[h+T$rHDW;_72NHg';
$db   = 'lchwnsbw_helpto_db';

$conn = @mysqli_connect($host, $user, $pass, $db);
if (!$conn) {
    echo "<p style='color:red'>❌ DB Connection Error: " . mysqli_connect_error() . "</p>";
} else {
    echo "<p style='color:green'>✅ Database connected successfully!</p>";
}

// Test a simple query
$res = @mysqli_query($conn, "SELECT setting_value FROM site_settings LIMIT 1");
if ($res) {
    echo "<p style='color:green'>✅ Query works fine.</p>";
} else {
    echo "<p style='color:orange'>⚠️ Query failed: " . mysqli_error($conn) . "</p>";
}
?>