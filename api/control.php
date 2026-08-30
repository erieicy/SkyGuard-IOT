<?php
/**
 * SkyGuard AI - Roof Control & Mode Management API
 * Handles manual open/close commands, mode switching (Auto/Manual/Timer), and timer settings.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/db.php';
$pdo = getDbConnection();

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true) ?? $_POST;

$action = $data['action'] ?? '';

if (empty($action)) {
    echo json_encode(['success' => false, 'error' => 'Parameter action wajib diisi']);
    exit;
}

// Fetch current state
$stmt = $pdo->query("SELECT * FROM device_state WHERE id = 1");
$state = $stmt->fetch();

switch ($action) {
    case 'set_roof':
        $newStatus = strtoupper($data['status'] ?? '');
        if (!in_array($newStatus, ['OPEN', 'CLOSED'])) {
            echo json_encode(['success' => false, 'error' => 'Status atap tidak valid (harus OPEN atau CLOSED)']);
            exit;
        }

        $reason = $data['reason'] ?? ($newStatus === 'OPEN' ? 'Manual: Atap dibuka oleh pengguna' : 'Manual: Atap ditutup oleh pengguna');

        // Gunakan fungsi terpusat agar AI & manual konsisten (termasuk log & proteksi hujan).
        $res = applyRoofCommand($pdo, $newStatus, $reason, 'MANUAL_OVERRIDE');
        if (!$res['success']) {
            echo json_encode(['success' => false, 'error' => $res['error']]);
            exit;
        }

        echo json_encode([
            'success' => true, 
            'message' => 'Status atap berhasil diubah menjadi ' . $newStatus,
            'roof_status' => $res['roof_status']
        ]);
        break;

    case 'set_mode':
        $newMode = strtoupper($data['mode'] ?? '');
        if (!in_array($newMode, ['AUTO', 'MANUAL', 'TIMER'])) {
            echo json_encode(['success' => false, 'error' => 'Mode kontrol tidak valid (harus AUTO, MANUAL, atau TIMER)']);
            exit;
        }

        // If switching away from timer, cancel any active timer
        $timerActive = ($newMode === 'TIMER') ? $state['timer_active'] : 0;

        $update = $pdo->prepare("
            UPDATE device_state 
            SET control_mode = ?,
                timer_active = ?,
                last_action_reason = ?,
                updated_at = datetime('now', 'localtime')
            WHERE id = 1
        ");
        $update->execute([$newMode, $timerActive, 'Mode kontrol diubah ke ' . $newMode]);

        // If mode is set to AUTO, execute automatic evaluation immediately
        if ($newMode === 'AUTO') {
            evaluateAutoLogic($pdo);
        }

        echo json_encode([
            'success' => true, 
            'message' => 'Mode kontrol berhasil diubah ke ' . $newMode,
            'control_mode' => $newMode
        ]);
        break;

    case 'set_timer':
        $minutes = (int)($data['minutes'] ?? 0);
        if ($minutes <= 0 || $minutes > 720) { // Max 12 hours
            echo json_encode(['success' => false, 'error' => 'Durasi menit harus antara 1 sampai 720 menit']);
            exit;
        }

        // Check if rain detected
        if ($state['rain_detected'] == 1) {
            echo json_encode([
                'success' => false, 
                'error' => 'Tidak dapat memulai timer saat sensor mendeteksi hujan!'
            ]);
            exit;
        }

        $now = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
        $startTime = $now->format('Y-m-d H:i:s');
        $endTimeObj = clone $now;
        $endTimeObj->modify("+{$minutes} minutes");
        $endTime = $endTimeObj->format('Y-m-d H:i:s');

        $update = $pdo->prepare("
            UPDATE device_state 
            SET control_mode = 'TIMER',
                roof_status = 'OPEN',
                timer_active = 1,
                timer_start_time = ?,
                timer_duration_minutes = ?,
                timer_end_time = ?,
                last_action_reason = ?,
                updated_at = datetime('now', 'localtime')
            WHERE id = 1
        ");
        $update->execute([$startTime, $minutes, $endTime, "Timer diaktifkan selama {$minutes} menit (hingga {$endTime})"]);

        // Alert
        $alert = $pdo->prepare("
            INSERT INTO alerts (timestamp, alert_type, title, message, severity)
            VALUES (datetime('now', 'localtime'), 'TIMER_SET', 'Timer Jemur Aktif', 'Stopwatch timer diatur untuk {$minutes} menit. Atap jemuran dibuka dan akan tertutup otomatis pada {$endTime}.', 'info')
        ");
        $alert->execute();

        echo json_encode([
            'success' => true,
            'message' => "Timer aktif selama {$minutes} menit. Atap jemuran dibuka.",
            'timer_end_time' => $endTime,
            'timer_minutes' => $minutes
        ]);
        break;

    case 'cancel_timer':
        $update = $pdo->prepare("
            UPDATE device_state 
            SET timer_active = 0,
                timer_end_time = NULL,
                last_action_reason = 'Timer stopwatch dibatalkan oleh pengguna',
                updated_at = datetime('now', 'localtime')
            WHERE id = 1
        ");
        $update->execute();

        echo json_encode([
            'success' => true,
            'message' => 'Timer berhasil dibatalkan'
        ]);
        break;

    case 'toggle_mendung_autoclose':
        // Pastikan ESP32 dalam kondisi terhubung
        $espOnline = false;
        $disc = (int)$pdo->query("SELECT esp32_disconnected FROM device_state WHERE id = 1")->fetchColumn();
        if (!$disc && !empty($state['esp32_last_seen'])) {
            $lastSeen = new DateTime($state['esp32_last_seen']);
            $now = new DateTime('now');
            $diffSec = $now->getTimestamp() - $lastSeen->getTimestamp();
            $espOnline = ($diffSec <= 45);
        }

        if (!$espOnline) {
            echo json_encode([
                'success' => false,
                'error' => 'ESP32 belum terhubung. Hubungkan ESP32 terlebih dahulu untuk mengubah pengaturan Auto-Close Saat Mendung.'
            ]);
            exit;
        }

        $val = isset($data['enabled']) && ($data['enabled'] == 1 || $data['enabled'] === true || $data['enabled'] === '1') ? 1 : 0;
        $update = $pdo->prepare("
            UPDATE device_state 
            SET auto_close_on_mendung = ?,
                updated_at = datetime('now', 'localtime')
            WHERE id = 1
        ");
        $update->execute([$val]);

        echo json_encode([
            'success' => true,
            'auto_close_on_mendung' => $val,
            'message' => 'Pengaturan auto-close saat mendung diubah ke ' . ($val ? 'AKTIF' : 'NONAKTIF')
        ]);
        break;

    case 'clear_alerts':
        $pdo->exec("DELETE FROM alerts");
        echo json_encode(['success' => true, 'message' => 'Semua notifikasi dibersihkan']);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Aksi tidak dikenal: ' . htmlspecialchars($action)]);
        break;
}

/**
 * Automatic evaluation based on rain, light sensor, and AI verdicts
 */
function evaluateAutoLogic($pdo) {
    $stmt = $pdo->query("SELECT * FROM device_state WHERE id = 1");
    $state = $stmt->fetch();

    if ($state['control_mode'] !== 'AUTO') return;

    $newRoofStatus = $state['roof_status'];
    $reason = $state['last_action_reason'];

    // Rule 1: Rain detected -> ALWAYS CLOSE IMMEDIATELY
    if ($state['rain_detected'] == 1) {
        $newRoofStatus = 'CLOSED';
        $reason = 'Auto AI: Sensor air mendeteksi hujan - Atap langsung ditutup';
    } 
    // Rule 2: Mendung / Cloudy detected and auto-close is enabled
    elseif ($state['auto_close_on_mendung'] == 1 && in_array($state['ai_weather_verdict'], ['MENDUNG', 'HUJAN'])) {
        $newRoofStatus = 'CLOSED';
        $reason = 'Auto AI: Cuaca mendung/berpotensi hujan - Atap diamankan tertutup';
    }
    // Rule 3: Sunlight detected and light level > threshold -> OPEN
    elseif ($state['light_level'] >= 40 && $state['ai_light_verdict'] === 'SUNLIGHT' && in_array($state['ai_weather_verdict'], ['CERAH', 'BERAWAN'])) {
        $newRoofStatus = 'OPEN';
        $reason = 'Auto AI: Sinar matahari terdeteksi cerah - Atap dibuka untuk menjemur';
    }
    // Rule 4: Artificial lamp detected -> DO NOT OPEN (Keep closed or close)
    elseif ($state['ai_light_verdict'] === 'ARTIFICIAL_LAMP') {
        $newRoofStatus = 'CLOSED';
        $reason = 'Auto AI: Cahaya terdeteksi hanya lampu ruangan - Atap tetap tertutup';
    }
    // Rule 5: Dark / Night -> CLOSE
    elseif ($state['light_level'] < 20 || $state['ai_light_verdict'] === 'DARK' || $state['ai_weather_verdict'] === 'MALAM') {
        $newRoofStatus = 'CLOSED';
        $reason = 'Auto AI: Hari sudah gelap / malam - Atap ditutup';
    }

    if ($newRoofStatus !== $state['roof_status']) {
        $update = $pdo->prepare("
            UPDATE device_state 
            SET roof_status = ?,
                last_action_reason = ?,
                updated_at = datetime('now', 'localtime')
            WHERE id = 1
        ");
        $update->execute([$newRoofStatus, $reason]);

        $log = $pdo->prepare("
            INSERT INTO sensor_logs (timestamp, rain_detected, light_level, roof_status, control_mode, weather_condition, light_verdict, action_triggered)
            VALUES (datetime('now', 'localtime'), ?, ?, ?, 'AUTO', ?, ?, 'AUTO_EVALUATION')
        ");
        $log->execute([$state['rain_detected'], $state['light_level'], $newRoofStatus, $state['ai_weather_verdict'], $state['ai_light_verdict']]);
        enforceRetention($pdo, 10);
    }
}
