<?php
/**
 * SkyGuard AI - Clean Database & Remove Fake / Dummy Data
 * Resets database to a fresh state ready for real ESP32 hardware and real camera captures.
 */

require_once __DIR__ . '/db.php';
$pdo = getDbConnection();

// 1. Truncate dummy logs and history
$pdo->exec("DELETE FROM sensor_logs");
$pdo->exec("DELETE FROM camera_history");
$pdo->exec("DELETE FROM alerts");

// 2. Reset device state to fresh standby state
$pdo->exec("
    UPDATE device_state 
    SET roof_status = 'CLOSED',
        control_mode = 'AUTO',
        rain_detected = 0,
        light_level = 0,
        ai_light_verdict = 'UNKNOWN',
        ai_weather_verdict = 'MENUNGGU DATA',
        ai_confidence = 0.0,
        ai_drying_recommendation = 'Menunggu data sensor & foto langsung dari ESP32 / Kamera...',
        recommended_minutes = 0,
        timer_active = 0,
        timer_start_time = NULL,
        timer_duration_minutes = 0,
        timer_end_time = NULL,
        esp32_last_seen = NULL,
        esp32_cam_last_seen = NULL,
        last_action_reason = 'Sistem siap menerima data percobaan langsung dari ESP32',
        updated_at = datetime('now', 'localtime')
    WHERE id = 1
");

// 3. Remove dummy sample files from uploads/
$uploadDir = __DIR__ . '/../uploads/';
if (file_exists($uploadDir)) {
    $files = glob($uploadDir . '*');
    foreach ($files as $file) {
        if (is_file($file)) {
            @unlink($file);
        }
    }
}

echo "Database successfully cleaned. All dummy/fake data removed. Ready for real ESP32 tests.\n";
