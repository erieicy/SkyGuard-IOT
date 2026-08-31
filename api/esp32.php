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
    $espIp = $_SERVER['REMOTE_ADDR'] ?? null;
    $pdo->prepare("
        UPDATE device_state
        SET esp32_last_seen = datetime('now', 'localtime'),
            esp32_ip = COALESCE(?, esp32_ip),
            pending_esp32_ip = NULL,
            pending_since = NULL,
            esp32_disconnected = 0
        WHERE id = 1
    ")->execute([$espIp]);

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

// Manual connection from Dashboard: kirim URL server ke ESP32 dan verifikasi koneksi
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
        echo json_encode(['success' => false, 'error' => 'Format alamat IP tidak valid (contoh: 192.168.1.50)']);
        exit;
    }

    // Dapatkan URL endpoint server yang otomatis mengarahkan ke IP LAN yang benar
    $serverHost = getEsp32ServerEndpointUrl($pdo);
    $connectUrl = 'http://' . $ip . '/connect?server=' . urlencode($serverHost);
    $pingUrl = 'http://' . $ip . '/';

    $isSkyGuard = false;

    // 1. Kirim perintah /connect ke Web Server di ESP32 menggunakan cURL (lebih cepat & reliable dibanding file_get_contents)
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $connectUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_TIMEOUT, 4);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($resp !== false && ($httpCode === 200 || stripos($resp, 'SKYGUARD') !== false || stripos($resp, 'ESP32') !== false)) {
            $isSkyGuard = true;
        } else {
            // Fallback ping root "/"
            $ch2 = curl_init();
            curl_setopt($ch2, CURLOPT_URL, $pingUrl);
            curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch2, CURLOPT_CONNECTTIMEOUT, 2);
            curl_setopt($ch2, CURLOPT_TIMEOUT, 3);
            $r2 = curl_exec($ch2);
            $httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
            curl_close($ch2);
            if ($r2 !== false && ($httpCode2 === 200 || stripos($r2, 'SKYGUARD') !== false || stripos($r2, 'ESP32') !== false)) {
                $isSkyGuard = true;
            }
        }
    } else {
        $ctx = @stream_context_create(['http' => ['timeout' => 3, 'ignore_errors' => true, 'method' => 'GET']]);
        $resp = @file_get_contents($connectUrl, false, $ctx, 0, 256);
        if ($resp !== false && (stripos($resp, 'SKYGUARD') !== false || stripos($resp, 'ESP32') !== false)) {
            $isSkyGuard = true;
        }
    }

    // Cek juga apakah ESP32 dari IP ini baru saja aktif
    $st = $pdo->query("SELECT esp32_last_seen, esp32_ip FROM device_state WHERE id = 1")->fetch();
    if (!empty($st['esp32_last_seen'])) {
        $lastSeen = new DateTime($st['esp32_last_seen']);
        $now = new DateTime('now');
        if (($now->getTimestamp() - $lastSeen->getTimestamp()) <= 45 && ($st['esp32_ip'] === $ip || empty($st['esp32_ip']))) {
            $isSkyGuard = true;
        }
    }

    // Daftarkan IP dan buka blokir disconnect agar request dari ESP32 langsung diterima
    $pdo->prepare("
        UPDATE device_state
        SET pending_esp32_ip = ?, pending_since = datetime('now','localtime'), esp32_ip = ?, esp32_disconnected = 0
        WHERE id = 1
    ")->execute([$ip, $ip]);

    echo json_encode([
        'success' => true,
        'status' => 'pending',
        'message' => $isSkyGuard 
            ? 'Perintah koneksi terkirim ke ESP32 (' . $ip . '). Menunggu respons data...'
            : 'IP ESP32 (' . $ip . ') didaftarkan. Mengirim konfigurasi server: ' . $serverHost,
        'esp32_ip' => $ip,
        'server_endpoint' => $serverHost
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

    $espIp = $_SERVER['REMOTE_ADDR'] ?? null;
    $update = $pdo->prepare("
        UPDATE device_state 
        SET rain_detected = ?,
            light_level = ?,
            roof_status = ?,
            last_action_reason = ?,
            esp32_last_seen = datetime('now', 'localtime'),
            esp32_ip = COALESCE(?, esp32_ip),
            pending_esp32_ip = NULL,
            pending_since = NULL,
            esp32_disconnected = 0,
            updated_at = datetime('now', 'localtime')
        WHERE id = 1
    ");
    $update->execute([$rain, $light, $newRoofStatus, $reason, $espIp]);

    // Catat log SETIAP perubahan sensor (hujan berubah ATAU cahaya berubah sedikit pun).
    // Jika tidak ada perubahan sama sekali, tidak ada log baru.
    $rainChanged = ($rain != (int)$state['rain_detected']);
    $lightChanged = ($light != (int)$state['light_level']);
    if ($actionTriggered !== null || $rainChanged || $lightChanged) {
        $log = $pdo->prepare("
            INSERT INTO sensor_logs (timestamp, rain_detected, light_level, roof_status, control_mode, weather_condition, light_verdict, action_triggered)
            VALUES (datetime('now', 'localtime'), ?, ?, ?, ?, ?, ?, ?)
        ");
        $log->execute([$rain, $light, $newRoofStatus, $state['control_mode'], $state['ai_weather_verdict'], $state['ai_light_verdict'], $actionTriggered]);
        enforceRetention($pdo, 10);
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
    $espIp = $_SERVER['REMOTE_ADDR'] ?? null;
    $pdo->prepare("
        UPDATE device_state
        SET esp32_cam_last_seen = datetime('now', 'localtime'),
            esp32_last_seen = datetime('now', 'localtime'),
            esp32_ip = COALESCE(?, esp32_ip),
            esp32_disconnected = 0
        WHERE id = 1
    ")->execute([$espIp]);

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
