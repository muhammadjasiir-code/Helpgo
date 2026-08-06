<?php
/**
 * HelpGo - Full Config File
 * Auto-detects domain & folder path
 * Database: lchwnsbw_helpto_db
 */

session_start();
error_reporting(0);
ini_set('display_errors', 0);
date_default_timezone_set('Asia/Kolkata');

// ============================================
// DATABASE CONNECTION
// ============================================
$host     = "localhost";
$username = "lchwnsbw_helpto_db";
$password = '[h+T$rHDW;_72NHg';
$database = "lchwnsbw_helpto_db";

$conn = mysqli_connect($host, $username, $password, $database);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

mysqli_set_charset($conn, "utf8mb4");

// ============================================
// DYNAMIC SITE CONFIGURATION
// ============================================
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$domain = $_SERVER['HTTP_HOST'];
$scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$basePath  = $scriptDir . '/';

define('SITE_NAME', 'HelpGo');
define('SITE_URL', $protocol . $domain . $basePath);
define('ADMIN_URL', SITE_URL . 'admin/');
define('ASSETS_URL', SITE_URL . 'assets/');
define('CURRENCY_SYMBOL', '₹');
define('UPLOAD_DIR', $_SERVER['DOCUMENT_ROOT'] . '/uploads/');
define('UPLOAD_URL', SITE_URL . 'uploads/');

// ============================================
// DEFAULT CHARGES (fallback if DB settings missing)
// ============================================
define('PETROL_PRICE_PER_LITRE', 110);
define('PETROL_BASE_CHARGE', 30);
define('GROCERY_BASE_CHARGE', 30);
define('PARCEL_DAY_CHARGE', 50);
define('PARCEL_NIGHT_CHARGE', 80);
define('NIGHT_START', '20:00');
define('NIGHT_END', '06:00');
define('MAX_DELIVERY_DISTANCE', 10);

// ============================================
// HELPER FUNCTIONS
// ============================================

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['admin_id']) || (isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'admin');
}

function redirect($url) {
    header("Location: " . $url);
    exit();
}

function sanitize($data) {
    global $conn;
    return mysqli_real_escape_string($conn, trim($data));
}

function getUserData($user_id) {
    global $conn;
    $user_id = (int)$user_id;
    $query = "SELECT * FROM users WHERE id = $user_id";
    $result = mysqli_query($conn, $query);
    return mysqli_fetch_assoc($result);
}

function getWalletBalance($user_id) {
    global $conn;
    $user_id = (int)$user_id;
    $check = mysqli_query($conn, "SELECT id FROM wallet WHERE user_id = $user_id");
    if (mysqli_num_rows($check) == 0) {
        mysqli_query($conn, "INSERT INTO wallet (user_id, balance) VALUES ($user_id, 0)");
    }
    $query = "SELECT balance FROM wallet WHERE user_id = $user_id";
    $result = mysqli_query($conn, $query);
    $row = mysqli_fetch_assoc($result);
    return $row ? $row['balance'] : 0;
}

function getSetting($key) {
    global $conn;
    $key = sanitize($key);
    $query = "SELECT setting_value FROM site_settings WHERE setting_key = '$key'";
    $result = mysqli_query($conn, $query);
    if ($row = mysqli_fetch_assoc($result)) {
        return $row['setting_value'];
    }
    return null;
}

function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371;
    $lat1 = deg2rad($lat1);
    $lon1 = deg2rad($lon1);
    $lat2 = deg2rad($lat2);
    $lon2 = deg2rad($lon2);
    $dlat = $lat2 - $lat1;
    $dlon = $lon2 - $lon1;
    $a = sin($dlat/2) * sin($dlat/2) + cos($lat1) * cos($lat2) * sin($dlon/2) * sin($dlon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return round($earthRadius * $c, 2);
}

function isNightTime() {
    $now = date('H:i');
    $night_start = getSetting('night_charge_start_time');
    $night_end   = getSetting('night_charge_end_time');
    if ($night_start === null) $night_start = NIGHT_START;
    if ($night_end   === null) $night_end   = NIGHT_END;
    return ($now >= $night_start || $now <= $night_end);
}

function generateOrderId($service_type = '') {
    $prefix = 'HLP'; // default

    switch (strtolower($service_type)) {
        case 'grocery':
            $prefix = 'GRC';
            break;
        case 'petrol':
            $prefix = 'PET';
            break;
        case 'parcel':
            $prefix = 'PRC';
            break;
        // add more services if needed
    }

    return $prefix . date('Ymd') . rand(1000, 9999);
}

function setFlashMessage($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function displayFlashMessage() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return "<div class='alert alert-{$flash['type']}'>{$flash['message']}</div>";
    }
    return '';
}

// ============================================
// UPDATED isRider() – checks riders table for approved status
// ============================================
function isRider() {
    if (!isset($_SESSION['user_id'])) return false;
    $userId = (int)$_SESSION['user_id'];
    global $conn;
    $check = mysqli_query($conn, "SELECT id FROM riders WHERE user_id = $userId AND verification_status = 'approved'");
    return mysqli_num_rows($check) > 0;
}

// ============================================
// LOAD DYNAMIC SETTINGS FROM DB (override defaults)
// ============================================
$settings = [];
$settingsQuery = mysqli_query($conn, "SELECT * FROM site_settings");
if ($settingsQuery && mysqli_num_rows($settingsQuery) > 0) {
    while ($row = mysqli_fetch_assoc($settingsQuery)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

define('PETROL_CHARGE', isset($settings['petrol_delivery_charge']) ? floatval($settings['petrol_delivery_charge']) : PETROL_BASE_CHARGE);
define('GROCERY_CHARGE', isset($settings['grocery_delivery_charge']) ? floatval($settings['grocery_delivery_charge']) : GROCERY_BASE_CHARGE);
define('PARCEL_NIGHT', isset($settings['parcel_night_charge']) ? floatval($settings['parcel_night_charge']) : PARCEL_NIGHT_CHARGE);
// Store rider location
function updateRiderLocation($riderId, $orderId, $lat, $lng) {
    global $conn;
    $riderId = (int)$riderId;
    $lat = floatval($lat);
    $lng = floatval($lng);
    $orderId = sanitize($orderId);
    // Check if entry exists for this rider+order
    $exists = mysqli_query($conn, "SELECT id FROM rider_locations WHERE rider_id=$riderId AND order_id='$orderId'");
    if (mysqli_num_rows($exists) > 0) {
        mysqli_query($conn, "UPDATE rider_locations SET latitude=$lat, longitude=$lng WHERE rider_id=$riderId AND order_id='$orderId'");
    } else {
        mysqli_query($conn, "INSERT INTO rider_locations (rider_id, order_id, latitude, longitude) VALUES ($riderId, '$orderId', $lat, $lng)");
    }
}

// Get rider's latest location for an order
function getRiderLocation($orderId) {
    global $conn;
    $orderId = sanitize($orderId);
    $result = mysqli_query($conn, "SELECT latitude, longitude, updated_at FROM rider_locations WHERE order_id='$orderId' ORDER BY updated_at DESC LIMIT 1");
    return mysqli_fetch_assoc($result);
}
?>