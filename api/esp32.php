<?php
/**
 * SkyGuard AI - Dedicated IoT Endpoint for ESP32 & ESP32-CAM
 * Provides lightweight REST communication for sensors, motor commands, and camera uploads.
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

$action = $_GET['action'] ?? $_POST['action'] ?? 'get_command';

// 1. ESP32 Polling & Command Retrieval
if ($action === 'get_command' || ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
    // Update heartbeat
    $pdo->exec("UPDATE device_state SET esp32_last_seen = datetime('now', 'localtime') WHERE id = 1");

    $stmt = $pdo->query("SELECT * FROM device_state WHERE id = 1");
    $state = $stmt->fetch();

    $servoAngle = ($state['roof_status'] === 'OPEN') ? 180 : 0;

    // Check if snapshot requested
    $snapCheck = $pdo->query("SELECT value FROM settings WHERE key = 'trigger_cam_capture'")->fetchColumn();
    $triggerSnapshot = ($snapCheck == '1');

    if ($triggerSnapshot) {
        $pdo->exec("UPDATE settings SET value = '0' WHERE key = 'trigger_cam_capture'");
    }

    echo json_encode([
        'status' => 'ok',
        'roof_status' => $state['roof_status'],      // OPEN or CLOSED
        'servo_angle' => $servoAngle,                 // 180 (Open), 0 (Closed)
        'control_mode' => $state['control_mode'],     // AUTO, MANUAL, TIMER
        'rain_detected' => (int)$state['rain_detected'],
        'auto_close_mendung' => (int)$state['auto_close_on_mendung'],
        'trigger_snapshot' => $triggerSnapshot,
        'server_time' => date('Y-m-d H:i:s')
    ]);
    exit;
}

// Trigger snapshot request from Dashboard to ESP32-CAM
if ($action === 'request_snapshot') {
    $pdo->exec("INSERT OR REPLACE INTO settings (key, value) VALUES ('trigger_cam_capture', '1')");
    echo json_encode([
        'success' => true,
        'message' => 'Perintah ambil foto telah dikirim ke modul ESP32-CAM'
    ]);
    exit;
}

// 2. ESP32 Sensor Telemetry Upload
if ($action === 'update_sensors') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true) ?? $_POST;

    $rain = isset($data['rain']) ? (int)$data['rain'] : 0;
    $light = isset($data['light']) ? (int)$data['light'] : 0;

    // Fetch current state
    $stmt = $pdo->query("SELECT * FROM device_state WHERE id = 1");
    $state = $stmt->fetch();

    $newRoofStatus = $state['roof_status'];
    $reason = $state['last_action_reason'];
    $actionTriggered = null;

    // Critical Rain Rule: If rain touches sensor -> IMMEDIATELY CLOSE ROOF
    if ($rain == 1) {
        if ($state['roof_status'] !== 'CLOSED' || $state['rain_detected'] == 0) {
            $newRoofStatus = 'CLOSED';
            $reason = 'DARURAT: Sensor air mendeteksi hujan! Atap jemuran langsung ditutup otomatis.';
            $actionTriggered = 'RAIN_AUTO_CLOSE';

            // Insert alert
            $alert = $pdo->prepare("
                INSERT INTO alerts (alert_type, title, message, severity)
                VALUES ('RAIN_DETECTED', 'Hujan Terdeteksi!', 'Sensor mendeteksi tetesan air hujan. Atap jemuran segera diamankan tertutup.', 'danger')
            ");
            $alert->execute();
        }
    } elseif ($state['control_mode'] === 'AUTO' && $state['roof_status'] === 'CLOSED' && $state['rain_detected'] == 1 && $rain == 0) {
        // Rain has stopped
        $reason = 'Hujan telah reda. Sensor mendeteksi kondisi kering.';
    }

    // Update state
    $update = $pdo->prepare("
        UPDATE device_state 
        SET rain_detected = ?,
            light_level = ?,
            roof_status = ?,
            last_action_reason = ?,
            esp32_last_seen = datetime('now', 'localtime'),
            updated_at = datetime('now', 'localtime')
        WHERE id = 1
    ");
    $update->execute([$rain, $light, $newRoofStatus, $reason]);

    // Periodically insert telemetry log (or on state change)
    if ($actionTriggered !== null || rand(1, 10) === 1) {
        $log = $pdo->prepare("
            INSERT INTO sensor_logs (rain_detected, light_level, roof_status, control_mode, weather_condition, light_verdict, action_triggered)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $log->execute([$rain, $light, $newRoofStatus, $state['control_mode'], $state['ai_weather_verdict'], $state['ai_light_verdict'], $actionTriggered]);
    }

    $servoAngle = ($newRoofStatus === 'OPEN') ? 180 : 0;

    echo json_encode([
        'status' => 'success',
        'roof_status' => $newRoofStatus,
        'servo_angle' => $servoAngle,
        'rain_detected' => $rain,
        'light_level' => $light,
        'message' => 'Sensor data updated successfully'
    ]);
    exit;
}

// 3. ESP32-CAM Image Upload & Auto Evaluation
if ($action === 'upload_cam') {
    $uploadDir = __DIR__ . '/../uploads/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $savedFilename = 'esp32cam_' . date('Ymd_His') . '_' . uniqid() . '.jpg';
    $targetPath = $uploadDir . $savedFilename;

    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        move_uploaded_file($_FILES['image']['tmp_name'], $targetPath);
    } else {
        // Raw stream fallback
        $rawImage = file_get_contents('php://input');
        if (!empty($rawImage) && strlen($rawImage) > 500) {
            file_put_contents($targetPath, $rawImage);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'No image data received']);
            exit;
        }
    }

    // Call AI analyzer logic
    require_once __DIR__ . '/ai_analyze.php';
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Unknown action: ' . htmlspecialchars($action)]);
