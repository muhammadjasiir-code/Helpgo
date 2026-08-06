<?php
require_once 'config.php';
if (!isLoggedIn()) die("You are not logged in.");

$user = getUserData($_SESSION['user_id']);
echo "Logged in as: " . $user['full_name'] . "<br>";
echo "Phone: " . $user['phone'] . "<br>";
echo "User type (DB): " . $user['user_type'] . "<br>";
echo "Session user_type: " . ($_SESSION['user_type'] ?? 'not set') . "<br>";
echo "isAdmin(): " . (isAdmin() ? 'YES' : 'NO') . "<br>";
?>