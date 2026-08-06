<?php
require_once "config.php";
header('Content-Type: application/json');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    echo json_encode(['error' => 'Invalid store ID']);
    exit;
}

$store = mysqli_fetch_assoc(mysqli_query($conn, "SELECT name, slug, logo, payment_methods FROM stores WHERE id = $id"));
if (!$store) {
    echo json_encode(['error' => 'Store not found']);
    exit;
}

// Build logo URL (same logic as store_detail)
$logoFile = $store['logo'] ?? '';
$logoUrl = '';
if (!empty($logoFile)) {
    if (filter_var($logoFile, FILTER_VALIDATE_URL)) {
        $logoUrl = $logoFile;
    } else {
        $logoUrl = 'assets/storebanner/' . $logoFile;
    }
}

echo json_encode([
    'name'             => $store['name'],
    'slug'             => $store['slug'],
    'logo_url'         => $logoUrl,
    'payment_methods'  => $store['payment_methods'] ?? 'upi,cod'
]);