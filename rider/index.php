<?php
require_once '../config.php';

// If not logged in as rider, send to main login
if (!isRider()) {
    redirect('../index.php');
}

// Logged in rider → go to dashboard
redirect('home.php');
