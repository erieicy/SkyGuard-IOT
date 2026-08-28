<?php
/**
 * SkyGuard AI - Database Seeder & Initial Sample Data Generator
 */

require_once __DIR__ . '/db.php';
$pdo = getDbConnection();

// Create sample image files
$uploadDir = __DIR__ . '/../uploads/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Generate sample presets if GD is available
require_once __DIR__ . '/ai_analyze.php';

$samplePresets = [
    ['type' => 'cerah', 'class' => 'CERAH (SUNLIGHT)', 'conf' => 97.4, 'action' => 'OPENED', 'note' => 'Langit biru cerah tanpa awan mendung, radiasi UV optimal untuk pengeringan pakaian.'],
    ['type' => 'mendung', 'class' => 'MENDUNG (SUNLIGHT)', 'conf' => 93.8, 'action' => 'CLOSED', 'note' => 'Awan kumulonimbus mendung tebal terdeteksi. Peringatan dini dikeluarkan & atap ditutup otomatis.'],
    ['type' => 'lampu', 'class' => 'INDOOR (ARTIFICIAL_LAMP)', 'conf' => 98.1, 'action' => 'CLOSED', 'note' => 'Cahaya ruangan listrik buatan terdeteksi. AI menolak membuka atap karena bukan panas matahari.'],
    ['type' => 'hujan', 'class' => 'HUJAN (DARK)', 'conf' => 95.5, 'action' => 'CLOSED', 'note' => 'Sensor air dan citra mendeteksi curah hujan aktif. Atap dipastikan tertutup rapat.']
];

// Check if camera_history has records
$count = $pdo->query("SELECT COUNT(*) FROM camera_history")->fetchColumn();
if ($count == 0) {
    foreach ($samplePresets as $p) {
        $filename = 'sample_' . $p['type'] . '.jpg';
        $filepath = $uploadDir . $filename;
        createPresetImage($p['type'], $filepath);

        $stmt = $pdo->prepare("
            INSERT INTO camera_history (image_path, source, ai_classification, ai_confidence, light_detected, roof_action, notes, timestamp)
            VALUES (?, 'simulation', ?, ?, 1, ?, ?, datetime('now', '-" . rand(5, 120) . " minutes', 'localtime'))
        ");
        $stmt->execute(['uploads/' . $filename, $p['class'], $p['conf'], $p['action'], $p['note']]);
    }
}

// Check if sensor_logs has records
$logCount = $pdo->query("SELECT COUNT(*) FROM sensor_logs")->fetchColumn();
if ($logCount < 10) {
    $now = time();
    for ($i = 15; $i >= 0; $i--) {
        $logTime = date('Y-m-d H:i:s', $now - ($i * 120));
        $light = rand(70, 95);
        $rain = ($i === 3) ? 1 : 0;
        $roof = ($rain === 1) ? 'CLOSED' : 'OPEN';
        
        $stmt = $pdo->prepare("
            INSERT INTO sensor_logs (timestamp, rain_detected, light_level, roof_status, control_mode, weather_condition, light_verdict, action_triggered)
            VALUES (?, ?, ?, ?, 'AUTO', 'CERAH', 'SUNLIGHT', 'PERIODIC_LOG')
        ");
        $stmt->execute([$logTime, $rain, $light, $roof]);
    }
}

echo "Database successfully seeded and initialized.\n";
