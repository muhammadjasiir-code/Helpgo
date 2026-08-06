<?php
require_once 'config.php';
header('Content-Type: application/json');

$origin = sanitize($_GET['origin'] ?? '');  // "lat,lng"
$dest   = sanitize($_GET['dest'] ?? '');    // "lat,lng"
if (empty($origin) || empty($dest)) {
    echo json_encode(['error'=>'Missing parameters']);
    exit;
}

// Call OSRM demo server
$url = "https://router.project-osrm.org/route/v1/driving/{$origin};{$dest}?overview=full&geometries=polyline";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);
if (isset($data['routes'][0])) {
    $route = $data['routes'][0];
    echo json_encode([
        'success'   => true,
        'polyline'  => $route['geometry'],           // encoded polyline
        'distance'  => round($route['distance']/1000, 2), // km
        'duration'  => round($route['duration']/60, 1)    // minutes
    ]);
} else {
    echo json_encode(['success'=>false, 'message'=>'Route not found']);
}