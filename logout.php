<?php
// logout.php – Customer Logout
require_once "config.php";

// Destroy all session data
session_start();
$_SESSION = [];
session_destroy();

// Redirect to the login page (or home)
redirect('index.php');