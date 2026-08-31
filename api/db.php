<?php
/**
 * SkyGuard AI - Database Connection & Schema Initializer
 * Uses SQLite for zero-configuration, seamless deployment in XAMPP.
 */

// Gunakan zona waktu lokal (WIB) agar semua waktu (jam server & penyimpanan)
// sesuai dengan dunia nyata / jam pengguna.
date_default_timezone_set('Asia/Jakarta');

define('DB_DIR', __DIR__ . '/../data');
define('DB_FILE', DB_DIR . '/skyguard.db');
define('UPLOAD_DIR', __DIR__ . '/../uploads');

/**
 * Muat variabel lingkungan dari file .env (jika ada).
 * Diprioritaskan untuk konfigurasi API AI Vision:
 *   AI_PROVIDER = gemini | openai
 *   AI_API_KEY  = key API (Gemini atau OpenAI)
 *   AI_MODEL    = nama model (opsional)
 */
function loadEnvFile($path) {
    if (!file_exists($path)) {
        return false;
    }
    $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return false;
    }
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || $line[0] === ';') {
            continue;
        }
        if (strpos($line, '=') === false) {
            continue;
        }
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        $value = trim($value, "\"'"); // buang tanda kutip jika ada
        if ($key === '') {
            continue;
        }
        // Jangan timpa nilai yang sudah ada di environment sistem
        if (!isset($_ENV[$key]) && getenv($key) === false) {
            $_ENV[$key] = $value;
            @putenv("$key=$value");
        }
    }
    return true;
}

// Muat .env dari root project (satu level di atas folder api/)
loadEnvFile(__DIR__ . '/../.env');

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

/**
 * Dapatkan URL endpoint server yang tepat dan dapat diakses oleh ESP32 pada jaringan Wi-Fi LAN.
 * Mengembalikan format lengkap: http://<LAN_IP>/SkyGuard-AI/api/esp32.php
 */
function getEsp32ServerEndpointUrl($pdo = null) {
    // 1. Cek apakah ada override server_host di database yang valid (bukan dummy/lama)
    if ($pdo !== null) {
        try {
            $customHost = $pdo->query("SELECT value FROM settings WHERE key = 'server_host'")->fetchColumn();
            if (!empty($customHost) && !in_array($customHost, ['http://192.168.1.50/SkyGuard-AI', '192.168.1.50', 'http://192.168.1.100/SkyGuard-AI'])) {
                if (stripos($customHost, 'esp32.php') !== false) {
                    return $customHost;
                }
                return rtrim($customHost, '/') . '/api/esp32.php';
            }
        } catch (Exception $e) {}
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';

    $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '');
    $port = $_SERVER['SERVER_PORT'] ?? '80';

    // Jika diakses dari localhost / 127.0.0.1, gunakan IP LAN komputer agar bisa diakses ESP32
    if (empty($host) || in_array(strtolower($host), ['localhost', '127.0.0.1', '::1']) || strpos($host, 'localhost:') === 0 || strpos($host, '127.0.0.1:') === 0) {
        $lanIp = gethostbyname(gethostname());
        if (!empty($lanIp) && $lanIp !== '127.0.0.1') {
            $host = $lanIp;
            if ($port !== '80' && $port !== '443' && strpos($host, ':') === false && !empty($_SERVER['SERVER_PORT'])) {
                $host .= ':' . $port;
            }
        } else {
            $host = 'localhost';
        }
    }

    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    if (!empty($script) && stripos($script, 'SkyGuard-AI') !== false) {
        $base = preg_replace('#/api/[^/]*$#i', '', $script);
        $base = rtrim($base, '/');
        // Ambil hanya bagian web path mulai dari /SkyGuard-AI
        $pos = stripos($base, '/SkyGuard-AI');
        if ($pos !== false) {
            $base = substr($base, $pos);
        } else {
            $base = '/SkyGuard-AI';
        }
    } else {
        $base = '/SkyGuard-AI';
    }

    return $scheme . '://' . $host . $base . '/api/esp32.php';
}

/**
 * Terapkan perintah perubahan status atap (OPEN/CLOSED) secara terpusat.
 * Digunakan baik oleh kontrol manual (control.php) maupun oleh AI Vision
 * (ai_analyze.php) agar setiap "input" atap tercatat sebagai log sensor
 * yang konsisten dengan perlindungan keamanan (tidak bisa membuka saat hujan).
 *
 * @param PDO    $pdo
 * @param string $status  'OPEN' | 'CLOSED'
 * @param string $reason  Alasan perubahan (ditampilkan di dashboard)
 * @param string $source  Pemicu: 'MANUAL_OVERRIDE', 'AI_VISION', 'TIMER', 'AUTO_LOGIC', dll
 * @return array ['success'=>bool, 'roof_status'=>?string, 'error'=>?string]
 */

/**
 * Batasi jumlah baris pada tabel riwayat agar hanya menyimpan $limit data terbaru.
 * Menghapus baris terlama (id terkecil) yang melebihi batas.
 */
function enforceRetention($pdo, $limit = 10) {
    foreach (['camera_history', 'sensor_logs'] as $table) {
        $count = $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
        if ($count > $limit) {
            $pdo->exec("DELETE FROM `$table` WHERE id NOT IN (SELECT id FROM `$table` ORDER BY id DESC LIMIT $limit)");
        }
    }
}

/**
 * Batasi jumlah file foto pada folder uploads agar hanya menyimpan $limit file terbaru.
 * Menghapus file terlama (berdasarkan waktu modifikasi) yang melebihi batas,
 * termasuk file yatim yang baris databasenya sudah terhapus.
 */
function enforcePhotoFileRetention($limit = 10) {
    if (!is_dir(UPLOAD_DIR)) return;
    $files = glob(UPLOAD_DIR . DIRECTORY_SEPARATOR . '*.{jpg,jpeg,png,webp}', GLOB_BRACE);
    if (!is_array($files) || count($files) <= $limit) return;
    usort($files, function ($a, $b) {
        return filemtime($b) <=> filemtime($a); // terbaru lebih dulu
    });
    foreach (array_slice($files, $limit) as $old) {
        @unlink($old);
    }
}

function applyRoofCommand($pdo, $status, $reason, $source = 'MANUAL_OVERRIDE') {
    $status = strtoupper(trim($status));
    if (!in_array($status, ['OPEN', 'CLOSED'])) {
        return ['success' => false, 'error' => 'Status atap tidak valid.'];
    }
    $reason = trim($reason) ?: ($status === 'OPEN' ? 'Atap dibuka secara manual.' : 'Atap ditutup secara manual.');

    // Perlindungan keamanan: jangan buka atap jika sensor air mendeteksi hujan.
    $st = $pdo->query("SELECT rain_detected FROM device_state WHERE id = 1")->fetch();
    if ($status === 'OPEN' && $st && (int)$st['rain_detected'] === 1) {
        return [
            'success' => false,
            'error' => 'PERINGATAN: Sensor mendeteksi air/hujan! Atap tidak dapat dibuka untuk melindungi jemuran.'
        ];
    }

    $pdo->prepare("
        UPDATE device_state
        SET roof_status = ?, last_action_reason = ?, updated_at = datetime('now', 'localtime')
        WHERE id = 1
    ")->execute([$status, $reason]);

    // Catat sebagai log telemetri agar input atap (termasuk dari AI) terlihat di riwayat.
    $info = $pdo->query("SELECT rain_detected, light_level, control_mode, ai_weather_verdict, ai_light_verdict FROM device_state WHERE id = 1")->fetch();
    $pdo->prepare("
        INSERT INTO sensor_logs
            (timestamp, rain_detected, light_level, roof_status, control_mode, weather_condition, light_verdict, action_triggered)
        VALUES (datetime('now', 'localtime'), ?, ?, ?, ?, ?, ?, ?)
    ")->execute([
        (int)($info['rain_detected'] ?? 0),
        (int)($info['light_level'] ?? 0),
        $status,
        $info['control_mode'] ?? 'AUTO',
        $info['ai_weather_verdict'] ?? 'CERAH',
        $info['ai_light_verdict'] ?? 'SUNLIGHT',
        $source
    ]);

    enforceRetention($pdo, 10);

    return ['success' => true, 'roof_status' => $status];
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
            updated_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
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
            timestamp TEXT NOT NULL DEFAULT (datetime('now', 'localtime')),
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
            timestamp TEXT NOT NULL DEFAULT (datetime('now', 'localtime')),
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
            timestamp TEXT NOT NULL DEFAULT (datetime('now', 'localtime')),
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
        'sound_alert' => '1',
        'ai_provider' => 'local',
        'gemini_api_key' => '',
        'openai_api_key' => '',
        'ai_model' => '',
        'server_host' => ''
    ];

    foreach ($defaultSettings as $key => $val) {
        $check = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE key = ?");
        $check->execute([$key]);
        if ($check->fetchColumn() == 0) {
            $ins = $pdo->prepare("INSERT INTO settings (key, value) VALUES (?, ?)");
            $ins->execute([$key, $val]);
        }
    }

    // Pastikan server_host dummy lama dibersihkan agar selalu mendeteksi IP jaringan yang aktif
    try {
        $pdo->exec("UPDATE settings SET value = '' WHERE key = 'server_host' AND (value LIKE '%192.168.1.50%' OR value LIKE '%192.168.1.100%')");
    } catch (Exception $e) {}

    // Pastikan kolom esp32_ip ada (untuk menampilkan IP ESP32 yang terhubung)
    try {
        $pdo->exec("ALTER TABLE device_state ADD COLUMN esp32_ip TEXT DEFAULT NULL");
    } catch (Exception $e) {
        // Kolom sudah ada
    }

    // Kolom untuk proses koneksi yang menunggu konfirmasi dari ESP32 (bukan langsung "terhubung")
    try { $pdo->exec("ALTER TABLE device_state ADD COLUMN pending_esp32_ip TEXT DEFAULT NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE device_state ADD COLUMN pending_since TEXT DEFAULT NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE device_state ADD COLUMN esp32_disconnected INTEGER DEFAULT 0"); } catch (Exception $e) {}

    // Migrasi satu kali: ubah timestamp UTC (dari CURRENT_TIMESTAMP lama) ke waktu lokal (WIB)
    // agar seluruh riwayat & log sesuai dengan jam dunia nyata.
    $tzMigrated = $pdo->query("SELECT value FROM settings WHERE key = 'tz_migrated'")->fetchColumn();
    if ($tzMigrated !== '1') {
        $offset = (int)date('Z'); // offset zona waktu lokal dalam detik
        if ($offset != 0) {
            $mod = ($offset >= 0 ? '+' : '-') . abs($offset) . ' seconds';
            foreach (['camera_history', 'sensor_logs', 'alerts'] as $tbl) {
                $pdo->exec("UPDATE $tbl SET timestamp = strftime('%Y-%m-%d %H:%M:%S', timestamp, '$mod') WHERE timestamp IS NOT NULL AND timestamp <> ''");
            }
            $pdo->exec("UPDATE device_state SET esp32_last_seen = strftime('%Y-%m-%d %H:%M:%S', esp32_last_seen, '$mod') WHERE esp32_last_seen IS NOT NULL AND esp32_last_seen <> ''");
        }
        $pdo->prepare("INSERT OR REPLACE INTO settings (key, value) VALUES ('tz_migrated', '1')")->execute();
    }
}
