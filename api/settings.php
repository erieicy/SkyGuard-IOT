<?php
/**
 * SkyGuard AI - System Settings & AI API Key Management
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

$action = $_GET['action'] ?? $_POST['action'] ?? 'get_settings';

if ($action === 'get_settings') {
    $stmt = $pdo->query("SELECT key, value FROM settings");
    $settingsList = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Mask sensitive API Key for display
    $maskedKey = '';
    if (!empty($settingsList['gemini_api_key'])) {
        $len = strlen($settingsList['gemini_api_key']);
        $maskedKey = substr($settingsList['gemini_api_key'], 0, 6) . '...' . substr($settingsList['gemini_api_key'], -4);
    }

    echo json_encode([
        'success' => true,
        'settings' => [
            'has_gemini_key' => !empty($settingsList['gemini_api_key']),
            'masked_gemini_key' => $maskedKey,
            'auto_close_on_mendung' => (int)($settingsList['auto_close_on_mendung'] ?? 1),
            'light_threshold' => (int)($settingsList['light_threshold'] ?? 40),
            'rain_threshold' => (int)($settingsList['rain_threshold'] ?? 30)
        ]
    ]);
    exit;
}

if ($action === 'save_gemini_key') {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true) ?? $_POST;
    $apiKey = trim($data['api_key'] ?? '');

    $stmt = $pdo->prepare("INSERT OR REPLACE INTO settings (key, value) VALUES ('gemini_api_key', ?)");
    $stmt->execute([$apiKey]);

    echo json_encode([
        'success' => true,
        'message' => empty($apiKey) ? 'Gemini API Key dihapus (menggunakan AI Vision Lokal)' : 'Gemini AI Vision API Key berhasil disimpan!'
    ]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Aksi tidak dikenal']);
