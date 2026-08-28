<?php
/**
 * SkyGuard AI - Interactive IoT Hardware Simulator API
 * Allows developers and users to simulate sensor inputs, weather conditions,
 * and AI camera triggers without physical hardware.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/db.php';
$pdo = getDbConnection();

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true) ?? $_POST;

$action = $data['action'] ?? '';

// Fetch current state
$stmt = $pdo->query("SELECT * FROM device_state WHERE id = 1");
$state = $stmt->fetch();

switch ($action) {
    case 'simulate_rain':
        $rain = (int)($data['rain'] ?? 1);
        $newRoofStatus = $state['roof_status'];
        $reason = $state['last_action_reason'];

        if ($rain == 1) {
            $newRoofStatus = 'CLOSED';
            $reason = 'Simulasi: Sensor air mendeteksi tetesan hujan! Atap jemuran segera ditutup otomatis.';

            $alert = $pdo->prepare("
                INSERT INTO alerts (alert_type, title, message, severity)
                VALUES ('RAIN_DETECTED', '[SIMULASI] Hujan Terdeteksi!', 'Sensor air mendeteksi basah/hujan. Atap otomatis ditutup.', 'danger')
            ");
            $alert->execute();
        } else {
            $reason = 'Simulasi: Sensor air kembali kering.';
        }

        $update = $pdo->prepare("
            UPDATE device_state 
            SET rain_detected = ?,
                roof_status = ?,
                last_action_reason = ?,
                updated_at = datetime('now', 'localtime')
            WHERE id = 1
        ");
        $update->execute([$rain, $newRoofStatus, $reason]);

        $log = $pdo->prepare("
            INSERT INTO sensor_logs (rain_detected, light_level, roof_status, control_mode, weather_condition, light_verdict, action_triggered)
            VALUES (?, ?, ?, ?, ?, ?, 'SIMULATION_RAIN_EVENT')
        ");
        $log->execute([$rain, $state['light_level'], $newRoofStatus, $state['control_mode'], $state['ai_weather_verdict'], $state['ai_light_verdict']]);

        echo json_encode([
            'success' => true,
            'rain_detected' => $rain,
            'roof_status' => $newRoofStatus,
            'message' => $rain ? 'Simulasi hujan aktif: Atap ditutup otomatis' : 'Simulasi sensor air kering'
        ]);
        break;

    case 'simulate_light':
        $light = max(0, min(100, (int)($data['light'] ?? 50)));
        
        $update = $pdo->prepare("
            UPDATE device_state 
            SET light_level = ?,
                updated_at = datetime('now', 'localtime')
            WHERE id = 1
        ");
        $update->execute([$light]);

        // If light > 50 and mode AUTO, suggest opening if no rain and not dark
        if ($state['control_mode'] === 'AUTO' && $light >= 40 && $state['rain_detected'] == 0 && $state['ai_light_verdict'] === 'SUNLIGHT' && $state['ai_weather_verdict'] === 'CERAH') {
            $pdo->exec("UPDATE device_state SET roof_status = 'OPEN', last_action_reason = 'Simulasi: Cahaya terang matahari - Atap dibuka otomatis' WHERE id = 1");
        } elseif ($state['control_mode'] === 'AUTO' && $light < 20) {
            $pdo->exec("UPDATE device_state SET roof_status = 'CLOSED', last_action_reason = 'Simulasi: Cahaya gelap/redup - Atap ditutup otomatis' WHERE id = 1");
        }

        echo json_encode([
            'success' => true,
            'light_level' => $light,
            'message' => "Intensitas cahaya diatur ke {$light}%"
        ]);
        break;

    case 'simulate_camera_preset':
        $preset = $data['preset'] ?? 'sunlight';
        
        // Forward to ai_analyze with preset
        $_POST['preset'] = $preset;
        $_POST['source'] = 'simulation';
        require_once __DIR__ . '/ai_analyze.php';
        exit;

    case 'reset_simulation':
        $pdo->exec("
            UPDATE device_state 
            SET roof_status = 'CLOSED',
                control_mode = 'AUTO',
                rain_detected = 0,
                light_level = 75,
                ai_light_verdict = 'SUNLIGHT',
                ai_weather_verdict = 'CERAH',
                ai_confidence = 96.5,
                ai_drying_recommendation = 'Sinar matahari terik optimal. Rekomendasi jemur: 45 menit.',
                recommended_minutes = 45,
                timer_active = 0,
                timer_end_time = NULL,
                last_action_reason = 'Simulasi direset ke kondisi awal',
                updated_at = datetime('now', 'localtime')
            WHERE id = 1
        ");
        echo json_encode(['success' => true, 'message' => 'Status simulasi berhasil direset']);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Aksi simulasi tidak dikenal']);
        break;
}
