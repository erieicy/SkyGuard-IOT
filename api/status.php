<?php
/**
 * SkyGuard AI - Real-time Status API
 * GET: Retrieves latest device state, sensor telemetry, active alerts, and timer countdown
 * POST: Updates telemetry from ESP32 sensors or web hooks
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/db.php';
$pdo = getDbConnection();

// Check if timer has expired
function checkTimerExpiration($pdo, &$state) {
    if ($state['timer_active'] == 1 && !empty($state['timer_end_time'])) {
        $now = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
        $endTime = new DateTime($state['timer_end_time'], new DateTimeZone('Asia/Jakarta'));

        if ($now >= $endTime) {
            // Timer expired! Close roof automatically
            $update = $pdo->prepare("
                UPDATE device_state 
                SET roof_status = 'CLOSED',
                    timer_active = 0,
                    last_action_reason = 'Timer stopwatch selesai - Atap ditutup otomatis',
                    updated_at = datetime('now', 'localtime')
                WHERE id = 1
            ");
            $update->execute();

            // Insert alert
            $alert = $pdo->prepare("
                INSERT INTO alerts (alert_type, title, message, severity)
                VALUES ('TIMER_EXPIRED', 'Waktu Jemur Selesai', 'Stopwatch timer telah berakhir. Atap jemuran telah ditutup otomatis demi menjaga pakaian.', 'success')
            ");
            $alert->execute();

            // Insert sensor log
            $log = $pdo->prepare("
                INSERT INTO sensor_logs (rain_detected, light_level, roof_status, control_mode, weather_condition, light_verdict, action_triggered)
                VALUES (?, ?, 'CLOSED', ?, ?, ?, 'TIMER_AUTO_CLOSE')
            ");
            $log->execute([$state['rain_detected'], $state['light_level'], $state['control_mode'], $state['ai_weather_verdict'], $state['ai_light_verdict']]);

            // Refresh state array
            $stmt = $pdo->query("SELECT * FROM device_state WHERE id = 1");
            $state = $stmt->fetch();
        }
    }
}

// Fetch current device state
$stmt = $pdo->query("SELECT * FROM device_state WHERE id = 1");
$state = $stmt->fetch();

if (!$state) {
    echo json_encode(['success' => false, 'error' => 'Device state not found']);
    exit;
}

// Check timer
checkTimerExpiration($pdo, $state);

// Calculate remaining timer seconds
$timerRemainingSeconds = 0;
if ($state['timer_active'] == 1 && !empty($state['timer_end_time'])) {
    $now = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
    $endTime = new DateTime($state['timer_end_time'], new DateTimeZone('Asia/Jakarta'));
    if ($endTime > $now) {
        $interval = $now->diff($endTime);
        $timerRemainingSeconds = ($interval->days * 86400) + ($interval->h * 3600) + ($interval->i * 60) + $interval->s;
    }
}

// Fetch recent unread alerts
$alertsStmt = $pdo->query("SELECT * FROM alerts ORDER BY id DESC LIMIT 10");
$alerts = $alertsStmt->fetchAll();

// Check ESP online status (active if seen in last 30 seconds)
$espOnline = false;
if (!empty($state['esp32_last_seen'])) {
    $lastSeen = new DateTime($state['esp32_last_seen']);
    $now = new DateTime('now');
    $diffSec = $now->getTimestamp() - $lastSeen->getTimestamp();
    $espOnline = ($diffSec <= 45);
}

// Get recent history snapshot
$recentLogsStmt = $pdo->query("SELECT * FROM sensor_logs ORDER BY id DESC LIMIT 15");
$recentLogs = $recentLogsStmt->fetchAll();

// Get latest photo
$latestPhotoStmt = $pdo->query("SELECT * FROM camera_history ORDER BY id DESC LIMIT 1");
$latestPhoto = $latestPhotoStmt->fetch();

echo json_encode([
    'success' => true,
    'server_time' => date('Y-m-d H:i:s'),
    'state' => [
        'roof_status' => $state['roof_status'],
        'control_mode' => $state['control_mode'],
        'rain_detected' => (int)$state['rain_detected'],
        'light_level' => (int)$state['light_level'],
        'ai_light_verdict' => $state['ai_light_verdict'],
        'ai_weather_verdict' => $state['ai_weather_verdict'],
        'ai_confidence' => (float)$state['ai_confidence'],
        'ai_drying_recommendation' => $state['ai_drying_recommendation'],
        'recommended_minutes' => (int)$state['recommended_minutes'],
        'timer_active' => (int)$state['timer_active'],
        'timer_start_time' => $state['timer_start_time'],
        'timer_duration_minutes' => (int)$state['timer_duration_minutes'],
        'timer_end_time' => $state['timer_end_time'],
        'timer_remaining_seconds' => $timerRemainingSeconds,
        'auto_close_on_mendung' => (int)$state['auto_close_on_mendung'],
        'last_action_reason' => $state['last_action_reason'],
        'updated_at' => $state['updated_at'],
        'esp32_online' => $espOnline,
        'esp32_last_seen' => $state['esp32_last_seen'],
        'esp32_cam_last_seen' => $state['esp32_cam_last_seen']
    ],
    'alerts' => $alerts,
    'latest_photo' => $latestPhoto,
    'recent_logs' => array_reverse($recentLogs)
]);
