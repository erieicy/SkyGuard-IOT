<?php
/**
 * SkyGuard AI - System Settings & AI API Key Management
 * Mendukung multi-provider AI: local (default), Google Gemini, OpenAI.
 * Juga menyimpan alamat server (server_host) untuk konfigurasi firmware ESP32.
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

    // Mask sensitive API Key for display
    $maskKey = function ($k) {
        if (empty($k)) return '';
        $len = strlen($k);
        if ($len <= 10) return str_repeat('*', $len);
        return substr($k, 0, 6) . '...' . substr($k, -4);
    };

    $serverHost = $settingsList['server_host'] ?? '';
    if (empty($serverHost)) {
        $serverHost = detectServerHost();
    }

    echo json_encode([
        'success' => true,
        'settings' => [
            'ai_provider' => $settingsList['ai_provider'] ?? 'local',
            'has_gemini_key' => !empty($settingsList['gemini_api_key']),
            'masked_gemini_key' => $maskKey($settingsList['gemini_api_key'] ?? ''),
            'has_openai_key' => !empty($settingsList['openai_api_key']),
            'masked_openai_key' => $maskKey($settingsList['openai_api_key'] ?? ''),
            'ai_model' => $settingsList['ai_model'] ?? '',
            'auto_close_on_mendung' => (int)($settingsList['auto_close_on_mendung'] ?? 1),
            'light_threshold' => (int)($settingsList['light_threshold'] ?? 40),
            'rain_threshold' => (int)($settingsList['rain_threshold'] ?? 30),
            'server_host' => $serverHost,
            'detected_host' => detectServerHost()
        ]
    ]);
    exit;
}

if ($action === 'save_gemini_key' || $action === 'save_settings') {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true) ?? $_POST;

    $key = trim($data['api_key'] ?? '');
    $model = trim($data['ai_model'] ?? '');
    $serverHost = trim($data['server_host'] ?? '');

    // Satu key untuk semua: otomatis deteksi provider
    if ($key !== '') {
        $provider = (strpos($key, 'sk-') === 0) ? 'openai' : 'gemini';
        // simpan ke kolom yang sesuai, dan kosongkan kolom lain
        $pdo->prepare("INSERT OR REPLACE INTO settings (key, value) VALUES ('ai_provider', ?)")->execute([$provider]);
        $pdo->prepare("INSERT OR REPLACE INTO settings (key, value) VALUES ('gemini_api_key', ?)")->execute([$provider === 'gemini' ? $key : '']);
        $pdo->prepare("INSERT OR REPLACE INTO settings (key, value) VALUES ('openai_api_key', ?)")->execute([$provider === 'openai' ? $key : '']);
    }
    // jika key dikosongkan, biarkan key lama tetap (jangan hapus) agar server_host bisa diubah sendiri

    $pdo->prepare("INSERT OR REPLACE INTO settings (key, value) VALUES ('ai_model', ?)")->execute([$model]);
    $pdo->prepare("INSERT OR REPLACE INTO settings (key, value) VALUES ('server_host', ?)")->execute([$serverHost]);

    // tentukan provider aktif untuk pesan
    $cur = $pdo->query("SELECT value FROM settings WHERE key = 'ai_provider'")->fetchColumn();
    $msgProvider = strtoupper($cur ?: 'LOCAL');

    echo json_encode([
        'success' => true,
        'message' => 'Pengaturan AI Vision berhasil disimpan! (Provider: ' . $msgProvider . ')'
    ]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Aksi tidak dikenal']);
