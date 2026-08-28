<?php
/**
 * SkyGuard AI - Database Connection & Schema Initializer
 * Uses SQLite for zero-configuration, seamless deployment in XAMPP.
 */

define('DB_DIR', __DIR__ . '/../data');
define('DB_FILE', DB_DIR . '/skyguard.db');
define('UPLOAD_DIR', __DIR__ . '/../uploads');

// Ensure directories exist
if (!file_exists(DB_DIR)) {
    mkdir(DB_DIR, 0777, true);
}
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0777, true);
}

function getDbConnection() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO('sqlite:' . DB_FILE);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            initializeDatabase($pdo);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
            exit;
        }
    }
    return $pdo;
}

function initializeDatabase($pdo) {
    // 1. Device State Table (single row for real-time status)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS device_state (
            id INTEGER PRIMARY KEY CHECK (id = 1),
            roof_status TEXT NOT NULL DEFAULT 'CLOSED', -- OPEN, CLOSED, OPENING, CLOSING
            control_mode TEXT NOT NULL DEFAULT 'AUTO',   -- AUTO, MANUAL, TIMER
            rain_detected INTEGER NOT NULL DEFAULT 0,  -- 0 = No Rain, 1 = Rain Detected
            light_level INTEGER NOT NULL DEFAULT 0,     -- 0 - 100% or Lux
            ai_light_verdict TEXT DEFAULT 'UNKNOWN',    -- SUNLIGHT, ARTIFICIAL_LAMP, DARK
            ai_weather_verdict TEXT DEFAULT 'CERAH',    -- CERAH, BERAWAN, MENDUNG, HUJAN, MALAM
            ai_confidence REAL DEFAULT 0.0,
            ai_drying_recommendation TEXT DEFAULT 'Menunggu data cuaca...',
            recommended_minutes INTEGER DEFAULT 60,
            timer_active INTEGER DEFAULT 0,
            timer_start_time TEXT DEFAULT NULL,
            timer_duration_minutes INTEGER DEFAULT 0,
            timer_end_time TEXT DEFAULT NULL,
            auto_close_on_mendung INTEGER DEFAULT 1,
            esp32_last_seen TEXT DEFAULT NULL,
            esp32_cam_last_seen TEXT DEFAULT NULL,
            last_action_reason TEXT DEFAULT 'System Initialized',
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // Seed default state if not present
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM device_state WHERE id = 1");
    $row = $stmt->fetch();
    if ($row['count'] == 0) {
        $pdo->exec("
            INSERT INTO device_state (
                id, roof_status, control_mode, rain_detected, light_level,
                ai_light_verdict, ai_weather_verdict, ai_confidence,
                ai_drying_recommendation, recommended_minutes, auto_close_on_mendung,
                last_action_reason, updated_at
            ) VALUES (
                1, 'CLOSED', 'AUTO', 0, 0,
                'UNKNOWN', 'MENUNGGU DATA', 0.0,
                'Menunggu data sensor & tangkapan kamera langsung dari ESP32...', 0, 1,
                'Sistem siap menerima data percobaan ESP32', datetime('now', 'localtime')
            )
        ");
    }

    // 2. Sensor & Action Telemetry Logs Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sensor_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            timestamp TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            rain_detected INTEGER NOT NULL,
            light_level INTEGER NOT NULL,
            roof_status TEXT NOT NULL,
            control_mode TEXT NOT NULL,
            weather_condition TEXT DEFAULT 'CERAH',
            light_verdict TEXT DEFAULT 'SUNLIGHT',
            action_triggered TEXT DEFAULT NULL
        )
    ");

    // 3. Camera Capture & AI Analysis History Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS camera_history (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            timestamp TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            image_path TEXT NOT NULL,
            source TEXT DEFAULT 'esp32_cam', -- esp32_cam, direct_camera, user_upload
            ai_classification TEXT NOT NULL, -- SUNLIGHT, LAMP_INDOOR, CERAH, MENDUNG, HUJAN, MALAM
            ai_confidence REAL DEFAULT 0.0,
            light_detected INTEGER DEFAULT 1,
            roof_action TEXT NOT NULL,       -- OPENED, CLOSED, NO_CHANGE
            notes TEXT DEFAULT NULL
        )
    ");

    // 4. Alerts and Notifications Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS alerts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            timestamp TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            alert_type TEXT NOT NULL, -- RAIN_DETECTED, MENDUNG_ALERT, TIMER_EXPIRED, LAMP_DETECTED, SYSTEM
            title TEXT NOT NULL,
            message TEXT NOT NULL,
            severity TEXT DEFAULT 'info', -- info, warning, danger, success
            is_read INTEGER DEFAULT 0
        )
    ");

    // 5. App Settings Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS settings (
            key TEXT PRIMARY KEY,
            value TEXT NOT NULL
        )
    ");

    // Seed default settings if empty
    $defaultSettings = [
        'rain_threshold' => '30',
        'light_threshold' => '40',
        'open_roof_hour' => '07:00',
        'close_roof_hour' => '17:30',
        'auto_close_on_mendung' => '1',
        'ai_sensitivity' => '0.80',
        'sound_alert' => '1'
    ];

    foreach ($defaultSettings as $key => $val) {
        $check = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE key = ?");
        $check->execute([$key]);
        if ($check->fetchColumn() == 0) {
            $ins = $pdo->prepare("INSERT INTO settings (key, value) VALUES (?, ?)");
            $ins->execute([$key, $val]);
        }
    }
}
