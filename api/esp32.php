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
    // Update heartbeat HANYA jika tidak dalam mode disconnect paksa.
    // Jika ESP32 mem-poll, berarti ia merespons perintah koneksi -> koneksi resmi TERHUBUNG.
    $espIp = $_SERVER['REMOTE_ADDR'] ?? null;
    $disc = (int)$pdo->query("SELECT esp32_disconnected FROM device_state WHERE id = 1")->fetchColumn();
    if (!$disc) {
        $pdo->prepare("
            UPDATE device_state
            SET esp32_last_seen = datetime('now', 'localtime'), esp32_ip = ?, pending_esp32_ip = NULL, pending_since = NULL
            WHERE id = 1
        ")->execute([$espIp]);
    }

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

// Manual connection from Dashboard: verifikasi identitas ESP32, lalu TUNGGU
// ESP32 mem-poll balik sebelum status dianggap "terhubung".
if ($action === 'connect') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true) ?? $_POST;

    $ip = trim($data['ip'] ?? '');
    if (empty($ip)) {
        $ip = trim($_GET['ip'] ?? '');
    }

    if (empty($ip)) {
        echo json_encode(['success' => false, 'error' => 'IP ESP32 wajib diisi']);
        exit;
    }

    // Validasi format IP (IPv4)
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        echo json_encode(['success' => false, 'error' => 'Format alamat IP tidak valid']);
        exit;
    }

    // Tentukan URL server yang akan diberitahukan ke ESP32 (agar ia mulai mem-poll).
    $serverHost = $pdo->query("SELECT value FROM settings WHERE key = 'server_host'")->fetchColumn();
    if (empty($serverHost)) {
        $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $serverHost = $proto . '://' . $host . '/SkyGuard-AI/api/esp32.php';
    }
    $pingUrl = 'http://' . $ip . '/';
    $connectUrl = $pingUrl . 'connect?server=' . urlencode($serverHost);

    // 1. Kirim perintah koneksi ke ESP32 dan baca respons untuk VERIFIKASI IDENTITAS.
    //    Kita hanya lanjut jika perangkat benar-benar mengonfirmasi sebagai SkyGuard ESP32
    //    (bukan sekadar IP random yang kebetulan merespons HTTP).
    $ctx = @stream_context_create(['http' => ['timeout' => 4, 'ignore_errors' => true, 'method' => 'GET']]);
    $resp = @file_get_contents($connectUrl, false, $ctx, 0, 256);
    $headerOk = isset($http_response_header) && count($http_response_header) > 0;

    $isSkyGuard = ($resp !== false) && (
        stripos($resp, 'SKYGUARD') !== false ||
        stripos($resp, 'ESP32') !== false
    );
    // Fallback: coba root "/" yang mengembalikan "SkyGuard ESP32-CAM Ready"
    if (!$isSkyGuard && !$headerOk) {
        $r2 = @file_get_contents($pingUrl, false, $ctx, 0, 256);
        if ($r2 !== false && (stripos($r2, 'SKYGUARD') !== false || stripos($r2, 'ESP32') !== false)) {
            $isSkyGuard = true;
        }
    }

    if (!$isSkyGuard) {
        echo json_encode([
            'success' => false,
            'error' => 'Perangkat di ' . $ip . ' bukan SkyGuard ESP32 (tidak mengonfirmasi identitas). Pastikan IP benar & perangkat menyala.'
        ]);
        exit;
    }

    // 2. Perangkat terverifikasi -> tandai MENUNGGU (pending). JANGAN langsung "terhubung".
    //    Status baru menjadi TERHUBUNG setelah ESP32 benar-benar mem-poll server kita.
    $pdo->prepare("
        UPDATE device_state
        SET pending_esp32_ip = ?, pending_since = datetime('now','localtime'), esp32_ip = ?, esp32_disconnected = 0
        WHERE id = 1
    ")->execute([$ip, $ip]);

    echo json_encode([
        'success' => true,
        'status' => 'pending',
        'message' => 'Perintah dikirim ke ESP32. Menunggu ESP32 membalas koneksi...',
        'esp32_ip' => $ip
    ]);
    exit;
}

// Putuskan koneksi ESP32 (dashboard meminta disconnect)
if ($action === 'disconnect') {
    $pdo->prepare("
        UPDATE device_state
        SET pending_esp32_ip = NULL, pending_since = NULL, esp32_last_seen = NULL, esp32_disconnected = 1
        WHERE id = 1
    ")->execute();
    echo json_encode(['success' => true, 'message' => 'Koneksi ESP32 diputus.']);
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
                INSERT INTO alerts (timestamp, alert_type, title, message, severity)
                VALUES (datetime('now', 'localtime'), 'RAIN_DETECTED', 'Hujan Terdeteksi!', 'Sensor mendeteksi tetesan air hujan. Atap jemuran segera diamankan tertutup.', 'danger')
            ");
            $alert->execute();
        }
    } elseif ($state['control_mode'] === 'AUTO' && $state['roof_status'] === 'CLOSED' && $state['rain_detected'] == 1 && $rain == 0) {
        // Rain has stopped
        $reason = 'Hujan telah reda. Sensor mendeteksi kondisi kering.';
    }

    // Update state (jangan perbarui esp32_last_seen bila sedang disconnect paksa)
    $disc = (int)$pdo->query("SELECT esp32_disconnected FROM device_state WHERE id = 1")->fetchColumn();
    $lastSeenSql = $disc ? '' : "esp32_last_seen = datetime('now', 'localtime'),";
    $update = $pdo->prepare("
        UPDATE device_state 
        SET rain_detected = ?,
            light_level = ?,
            roof_status = ?,
            last_action_reason = ?,
            $lastSeenSql
            pending_esp32_ip = NULL,
            pending_since = NULL,
            updated_at = datetime('now', 'localtime')
        WHERE id = 1
    ");
    $update->execute([$rain, $light, $newRoofStatus, $reason]);

    // Periodically insert telemetry log (or on state change)
    if ($actionTriggered !== null || rand(1, 10) === 1) {
        $log = $pdo->prepare("
            INSERT INTO sensor_logs (timestamp, rain_detected, light_level, roof_status, control_mode, weather_condition, light_verdict, action_triggered)
            VALUES (datetime('now', 'localtime'), ?, ?, ?, ?, ?, ?, ?)
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

    // Call AI analyzer logic (file sudah disimpan -> lewat GLOBALS agar tidak baca ulang php://input)
    $GLOBALS['skyguard_uploaded_file'] = $targetPath;
    require_once __DIR__ . '/ai_analyze.php';
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Unknown action: ' . htmlspecialchars($action)]);
