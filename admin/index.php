<?php
require_once '../config.php';

// If already logged in as admin → go to dashboard
if (isAdmin()) {
    header("Location: dashboard.php");
    exit;
}

// Otherwise, send them to the login page
redirect('../index.php');