<?php
/**
 * SkyGuard AI - History & Photo Gallery API
 * Returns telemetry history for Chart.js graphs and photo audit records with filtering.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/db.php';
$pdo = getDbConnection();

$type = $_GET['type'] ?? 'all'; // 'sensors', 'photos', 'stats', 'all'
$limit = (int)($_GET['limit'] ?? 50);
$limit = min(max($limit, 5), 200);

$response = ['success' => true];

if ($type === 'sensors' || $type === 'all') {
    $sensorStmt = $pdo->prepare("
        SELECT id, timestamp, rain_detected, light_level, roof_status, control_mode, weather_condition, light_verdict, action_triggered
        FROM sensor_logs
        ORDER BY id DESC
        LIMIT ?
    ");
    $sensorStmt->execute([$limit]);
    $logs = $sensorStmt->fetchAll();
    $response['sensor_logs'] = array_reverse($logs);
}

if ($type === 'photos' || $type === 'all') {
    $photoStmt = $pdo->prepare("
        SELECT id, timestamp, image_path, source, ai_classification, ai_confidence, light_detected, roof_action, notes
        FROM camera_history
        ORDER BY id DESC
        LIMIT ?
    ");
    $photoStmt->execute([$limit]);
    $photos = $photoStmt->fetchAll();
    $response['photos'] = $photos;
}

if ($type === 'stats' || $type === 'all') {
    // Calculate daily statistics
    $today = date('Y-m-d');
    
    $rainEvents = $pdo->query("SELECT COUNT(*) FROM sensor_logs WHERE rain_detected = 1 AND timestamp LIKE '$today%'")->fetchColumn();
    $photoCount = $pdo->query("SELECT COUNT(*) FROM camera_history WHERE timestamp LIKE '$today%'")->fetchColumn();
    $totalOpenings = $pdo->query("SELECT COUNT(*) FROM sensor_logs WHERE roof_status = 'OPEN' AND timestamp LIKE '$today%'")->fetchColumn();
    $avgLight = $pdo->query("SELECT AVG(light_level) FROM sensor_logs WHERE timestamp LIKE '$today%'")->fetchColumn();

    $response['stats'] = [
        'today_rain_events' => (int)$rainEvents,
        'today_photos_captured' => (int)$photoCount,
        'today_roof_actuations' => (int)$totalOpenings,
        'today_avg_light' => round((float)$avgLight, 1)
    ];
}

echo json_encode($response);
