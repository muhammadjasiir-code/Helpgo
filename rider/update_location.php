<?php
require_once '../config.php';
if (!isRider()) { die("Unauthorized"); }

$orderId  = sanitize($_POST['order_id'] ?? '');
$lat      = floatval($_POST['lat'] ?? 0);
$lng      = floatval($_POST['lng'] ?? 0);
$heading  = floatval($_POST['heading'] ?? 0);
$speed    = floatval($_POST['speed'] ?? 0);
$riderId  = (int)$_SESSION['user_id'];

if ($orderId && $lat && $lng) {
    // Update rider_locations
    $exists = mysqli_query($conn, "SELECT id FROM rider_locations WHERE rider_id=$riderId AND order_id='$orderId'");
    if (mysqli_num_rows($exists) > 0) {
        mysqli_query($conn, "UPDATE rider_locations SET latitude=$lat, longitude=$lng, heading=$heading, speed=$speed, updated_at=NOW() WHERE rider_id=$riderId AND order_id='$orderId'");
    } else {
        mysqli_query($conn, "INSERT INTO rider_locations (rider_id, order_id, latitude, longitude, heading, speed) VALUES ($riderId, '$orderId', $lat, $lng, $heading, $speed)");
    }
    // Also update riders table for general location (optional)
    mysqli_query($conn, "UPDATE riders SET current_latitude=$lat, current_longitude=$lng WHERE user_id=$riderId");
    echo "ok";
} else {
    echo "error";
}