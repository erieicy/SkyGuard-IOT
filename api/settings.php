<?php
/**
 * SkyGuard AI - System Settings
 * Menyediakan alamat server (server_host) untuk konfigurasi firmware ESP32.
 * Konfigurasi AI Vision dilakukan langsung via file .env (AI_PROVIDER, AI_API_KEY, AI_MODEL).
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

/**
 * Deteksi alamat server otomatis (IP LAN) untuk diisi ke firmware ESP32.
 */
function detectServerHost() {
    $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? gethostbyname(gethostname()));
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    // api/settings.php -> naik dua level ke root project
    $base = preg_replace('#/api/[^/]*$#', '', $script);
    return $scheme . '://' . $host . $base;
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'get_settings';

if ($action === 'get_settings') {
    $stmt = $pdo->query("SELECT key, value FROM settings");
    $settingsList = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Tentukan provider AI aktif (hanya informasi status, bukan pengaturan):
    // prioritas .env -> lalu database (jika masih ada sisa).
    $envProvider = strtolower(trim((string)($_ENV['AI_PROVIDER'] ?? getenv('AI_PROVIDER') ?? '')));
    $envKey = trim((string)($_ENV['AI_API_KEY'] ?? getenv('AI_API_KEY') ?? ''));
    $activeProvider = 'none';
    if ($envProvider === 'gemini' && $envKey !== '') {
        $activeProvider = 'gemini';
    } elseif ($envProvider === 'openai' && $envKey !== '') {
        $activeProvider = 'openai';
    } elseif (($settingsList['ai_provider'] ?? 'local') !== 'local' &&
              (!empty($settingsList['gemini_api_key']) || !empty($settingsList['openai_api_key']))) {
        $activeProvider = strtolower($settingsList['ai_provider']);
    }

    $serverHost = $settingsList['server_host'] ?? '';
    if (empty($serverHost)) {
        $serverHost = detectServerHost();
    }

    echo json_encode([
        'success' => true,
        'settings' => [
            'ai_provider' => $activeProvider,
            'auto_close_on_mendung' => (int)($settingsList['auto_close_on_mendung'] ?? 1),
            'light_threshold' => (int)($settingsList['light_threshold'] ?? 40),
            'rain_threshold' => (int)($settingsList['rain_threshold'] ?? 30),
            'server_host' => $serverHost,
            'detected_host' => detectServerHost()
        ]
    ]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Aksi tidak dikenal']);
