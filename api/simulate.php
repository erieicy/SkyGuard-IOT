<?php
/**
 * SkyGuard AI - Hardware Simulator Controller
 * Mengizinkan pengujian sistem (hujan, cahaya, preset cuaca, timer) langsung dari browser
 * tanpa hardware ESP32 terpasang. Simulator memanipulasi device_state persis seperti
 * yang dilakukan firmware ESP32.
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

function getState($pdo) {
    return $pdo->query("SELECT * FROM device_state WHERE id = 1")->fetch();
}

/**
 * Evaluasi logika AUTO (mirip control.php evaluateAutoLogic) berdasarkan state saat ini.
 */
function evaluateSimAuto($pdo) {
    $state = getState($pdo);
    if ($state['control_mode'] !== 'AUTO') return $state;

    $newRoofStatus = $state['roof_status'];
    $reason = $state['last_action_reason'];

    if ($state['rain_detected'] == 1) {
        $newRoofStatus = 'CLOSED';
        $reason = 'SIMULATOR: Sensor air mendeteksi hujan - Atap langsung ditutup';
    } elseif ($state['auto_close_on_mendung'] == 1 && in_array($state['ai_weather_verdict'], ['MENDUNG', 'HUJAN'])) {
        $newRoofStatus = 'CLOSED';
        $reason = 'SIMULATOR: Cuaca mendung/berpotensi hujan - Atap diamankan tertutup';
    } elseif ($state['light_level'] >= 40 && $state['ai_light_verdict'] === 'SUNLIGHT' && in_array($state['ai_weather_verdict'], ['CERAH', 'BERAWAN'])) {
        $newRoofStatus = 'OPEN';
        $reason = 'SIMULATOR: Sinar matahari terdeteksi cerah - Atap dibuka untuk menjemur';
    } elseif ($state['ai_light_verdict'] === 'ARTIFICIAL_LAMP') {
        $newRoofStatus = 'CLOSED';
        $reason = 'SIMULATOR: Cahaya terdeteksi hanya lampu ruangan - Atap tetap tertutup';
    } elseif ($state['light_level'] < 20 || $state['ai_light_verdict'] === 'DARK' || $state['ai_weather_verdict'] === 'MALAM') {
        $newRoofStatus = 'CLOSED';
        $reason = 'SIMULATOR: Hari sudah gelap / malam - Atap ditutup';
    }

    if ($newRoofStatus !== $state['roof_status']) {
        $pdo->prepare("UPDATE device_state SET roof_status = ?, last_action_reason = ?, updated_at = datetime('now','localtime') WHERE id = 1")
            ->execute([$newRoofStatus, $reason]);
        $pdo->prepare("INSERT INTO sensor_logs (rain_detected, light_level, roof_status, control_mode, weather_condition, light_verdict, action_triggered)
            VALUES (?, ?, ?, 'AUTO', ?, ?, 'SIMULATOR_AUTO')")
            ->execute([$state['rain_detected'], $state['light_level'], $newRoofStatus, $state['ai_weather_verdict'], $state['ai_light_verdict']]);
    }
    return getState($pdo);
}

function setState($pdo, $fields) {
    $cols = array_keys($fields);
    $sets = implode(', ', array_map(fn($c) => "$c = ?", $cols));
    $pdo->prepare("UPDATE device_state SET $sets, updated_at = datetime('now','localtime') WHERE id = 1")
        ->execute(array_values($fields));
}

function pushAlert($pdo, $type, $title, $msg, $sev) {
    $pdo->prepare("INSERT INTO alerts (alert_type, title, message, severity) VALUES (?, ?, ?, ?)")
        ->execute([$type, $title, $msg, $sev]);
}

// =========================================================
// Aksi Simulasi
// =========================================================

if ($action === 'sim_sensor') {
    $rain = isset($data['rain']) ? (int)$data['rain'] : 0;
    $light = isset($data['light']) ? (int)$data['light'] : 0;
    $light = max(0, min(100, $light));

    if ($rain == 1 && $light > 50) $light = 20; // hujan => redup

    $fields = [
        'rain_detected' => $rain,
        'light_level' => $light,
        'esp32_last_seen' => date('Y-m-d H:i:s')
    ];

    if ($rain == 1) {
        $fields['last_action_reason'] = 'SIMULATOR: Tetesan air (hujan) disimulasikan';
        pushAlert($pdo, 'RAIN_DETECTED', 'Simulasi Hujan!', 'Simulator menyimulasikan air menyentuh sensor. Atap diamankan tertutup.', 'danger');
    } else {
        $fields['last_action_reason'] = 'SIMULATOR: Kondisi sensor diperbarui (cahaya ' . $light . '%)';
    }

    setState($pdo, $fields);
    $state = evaluateSimAuto($pdo);

    echo json_encode(['success' => true, 'state' => $state]);
    exit;
}

if ($action === 'sim_weather') {
    $preset = strtoupper($data['preset'] ?? 'CERAH');
    $state = getState($pdo);

    $map = [
        'CERAH'   => ['weather' => 'CERAH',   'light' => 'SUNLIGHT',         'rain' => 0, 'lvl' => 85, 'rec' => 'Cerah, sinar matahari optimal. Rekomendasi jemur: 45 menit.'],
        'BERAWAN' => ['weather' => 'BERAWAN', 'light' => 'SUNLIGHT',         'rain' => 0, 'lvl' => 55, 'rec' => 'Sedikit berawan, masih ada sinar matahari. Rekomendasi jemur: 45 menit.'],
        'MENDUNG' => ['weather' => 'MENDUNG', 'light' => 'SUNLIGHT',         'rain' => 0, 'lvl' => 40, 'rec' => 'Awan mendung terdeteksi. Waspada hujan, sebaiknya jemuran ditutup.'],
        'HUJAN'   => ['weather' => 'HUJAN',   'light' => 'DARK',             'rain' => 1, 'lvl' => 12, 'rec' => 'Hujan aktif. Jemuran harus tertutup.'],
        'MALAM'   => ['weather' => 'MALAM',   'light' => 'DARK',             'rain' => 0, 'lvl' => 5,  'rec' => 'Malam hari, tidak ada sinar matahari. Jemuran tertutup.'],
        'LAMP'    => ['weather' => 'INDOOR',  'light' => 'ARTIFICIAL_LAMP',  'rain' => 0, 'lvl' => 70, 'rec' => 'Hanya cahaya lampu ruangan, bukan matahari. Jemuran tetap tertutup.']
    ];

    if (!isset($map[$preset])) {
        echo json_encode(['success' => false, 'error' => 'Preset tidak dikenal']);
        exit;
    }
    $cfg = $map[$preset];

    setState($pdo, [
        'ai_weather_verdict' => $cfg['weather'],
        'ai_light_verdict' => $cfg['light'],
        'rain_detected' => $cfg['rain'],
        'light_level' => $cfg['lvl'],
        'ai_confidence' => 94.0,
        'ai_drying_recommendation' => $cfg['rec'],
        'recommended_minutes' => ($cfg['light'] === 'SUNLIGHT' && $cfg['rain'] == 0) ? 45 : 0,
        'last_action_reason' => 'SIMULATOR: Preset cuaca ' . $preset,
        'esp32_last_seen' => date('Y-m-d H:i:s')
    ]);

    if ($preset === 'MENDUNG') pushAlert($pdo, 'MENDUNG_ALERT', 'Simulasi Awan Mendung!', 'Simulator mendeteksi awan mendung. ' . ($state['auto_close_on_mendung'] == 1 ? 'Atap ditutup otomatis.' : 'Harap tutup jemuran manual.'), 'warning');
    if ($preset === 'HUJAN') pushAlert($pdo, 'RAIN_DETECTED', 'Simulasi Hujan!', 'Simulator mendeteksi hujan aktif.', 'danger');
    if ($preset === 'LAMP') pushAlert($pdo, 'LAMP_DETECTED', 'Simulasi Lampu Ruangan', 'Simulator mendeteksi cahaya lampu (bukan matahari).', 'info');

    $state = evaluateSimAuto($pdo);
    echo json_encode(['success' => true, 'state' => $state]);
    exit;
}

if ($action === 'sim_timer') {
    $minutes = (int)($data['minutes'] ?? 45);
    if ($minutes <= 0 || $minutes > 720) {
        echo json_encode(['success' => false, 'error' => 'Durasi harus 1-720 menit']);
        exit;
    }
    $now = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
    $end = clone $now;
    $end->modify("+{$minutes} minutes");
    setState($pdo, [
        'control_mode' => 'TIMER',
        'roof_status' => 'OPEN',
        'timer_active' => 1,
        'timer_start_time' => $now->format('Y-m-d H:i:s'),
        'timer_duration_minutes' => $minutes,
        'timer_end_time' => $end->format('Y-m-d H:i:s'),
        'last_action_reason' => "SIMULATOR: Timer {$minutes} menit diaktifkan"
    ]);
    pushAlert($pdo, 'TIMER_SET', 'Simulasi Timer', "Stopwatch simulator aktif {$minutes} menit.", 'info');
    echo json_encode(['success' => true, 'state' => getState($pdo)]);
    exit;
}

if ($action === 'reset') {
    $pdo->prepare("UPDATE device_state SET
        roof_status = 'CLOSED', control_mode = 'AUTO', rain_detected = 0, light_level = 0,
        ai_light_verdict = 'UNKNOWN', ai_weather_verdict = 'MENUNGGU DATA', ai_confidence = 0,
        ai_drying_recommendation = 'Menunggu data sensor & kamera.', recommended_minutes = 0,
        timer_active = 0, timer_start_time = NULL, timer_duration_minutes = 0, timer_end_time = NULL,
        last_action_reason = 'SIMULATOR: Sistem di-reset ke kondisi awal', updated_at = datetime('now','localtime')
        WHERE id = 1")->execute();
    echo json_encode(['success' => true, 'state' => getState($pdo)]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Aksi simulasi tidak dikenal: ' . htmlspecialchars($action)]);
