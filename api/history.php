<?php
/**
 * SkyGuard AI - History & Photo Gallery API
 * Returns telemetry history for Chart.js graphs and photo audit records with filtering.
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

// ------------------------------------------------------------------
// Aksi penghapusan riwayat foto (hanya via POST)
// ------------------------------------------------------------------
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

/**
 * Hapus file gambar dari folder uploads (dengan proteksi path traversal).
 */
function deletePhotoFile($imagePath) {
    if (empty($imagePath)) return;
    $base = __DIR__ . '/../';
    $uploadsDir = realpath($base . 'uploads');
    $full = realpath($base . ltrim($imagePath, '/\\'));
    if ($full && $uploadsDir && strpos($full, $uploadsDir) === 0 && is_file($full)) {
        @unlink($full);
    }
}

if ($action !== '' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $post = json_decode($raw, true);
    if (!is_array($post)) $post = [];
    $post = array_merge($post, $_POST);

    if ($action === 'delete_photo') {
        $id = (int)($post['id'] ?? 0);
        if ($id > 0) {
            $row = $pdo->query("SELECT image_path FROM camera_history WHERE id = $id")->fetch();
            if ($row) {
                deletePhotoFile($row['image_path']);
                $pdo->prepare("DELETE FROM camera_history WHERE id = ?")->execute([$id]);
                echo json_encode(['success' => true, 'message' => 'Foto riwayat berhasil dihapus.']);
                exit;
            }
        }
        echo json_encode(['success' => false, 'error' => 'Foto tidak ditemukan.']);
        exit;
    }

    if ($action === 'delete_all_photos') {
        $rows = $pdo->query("SELECT image_path FROM camera_history")->fetchAll();
        foreach ($rows as $r) deletePhotoFile($r['image_path']);
        $pdo->exec("DELETE FROM camera_history");
        echo json_encode(['success' => true, 'message' => 'Semua riwayat foto berhasil dihapus.']);
        exit;
    }

    if ($action === 'delete_sensor_log') {
        $id = (int)($post['id'] ?? 0);
        if ($id > 0) {
            $row = $pdo->query("SELECT id FROM sensor_logs WHERE id = $id")->fetch();
            if ($row) {
                $pdo->prepare("DELETE FROM sensor_logs WHERE id = ?")->execute([$id]);
                echo json_encode(['success' => true, 'message' => 'Log sensor berhasil dihapus.']);
                exit;
            }
        }
        echo json_encode(['success' => false, 'error' => 'Log sensor tidak ditemukan.']);
        exit;
    }

    if ($action === 'delete_all_sensor_logs') {
        $pdo->exec("DELETE FROM sensor_logs");
        echo json_encode(['success' => true, 'message' => 'Semua riwayat log sensor berhasil dihapus.']);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Aksi tidak dikenal']);
    exit;
}

$type = $_GET['type'] ?? 'all'; // 'sensors', 'photos', 'stats', 'all'
$limit = (int)($_GET['limit'] ?? 50);
$limit = min(max($limit, 5), 200);

$response = ['success' => true];

if ($type === 'sensors' || $type === 'all') {
    $sensorStmt = $pdo->prepare("
        SELECT id, timestamp, rain_detected, light_level, roof_status, control_mode, weather_condition, light_verdict, action_triggered
        FROM sensor_logs
        ORDER BY id DESC
        LIMIT ?
    ");
    $sensorStmt->execute([$limit]);
    $logs = $sensorStmt->fetchAll();
    $response['sensor_logs'] = array_reverse($logs);
}

if ($type === 'photos' || $type === 'all') {
    $photoStmt = $pdo->prepare("
        SELECT id, timestamp, image_path, source, ai_classification, ai_confidence, light_detected, roof_action, notes
        FROM camera_history
        ORDER BY id DESC
        LIMIT ?
    ");
    $photoStmt->execute([$limit]);
    $photos = $photoStmt->fetchAll();
    $response['photos'] = $photos;
}

if ($type === 'stats' || $type === 'all') {
    // Calculate daily statistics
    $today = date('Y-m-d');
    
    $rainEvents = $pdo->query("SELECT COUNT(*) FROM sensor_logs WHERE rain_detected = 1 AND timestamp LIKE '$today%'")->fetchColumn();
    $photoCount = $pdo->query("SELECT COUNT(*) FROM camera_history WHERE timestamp LIKE '$today%'")->fetchColumn();
    $totalOpenings = $pdo->query("SELECT COUNT(*) FROM sensor_logs WHERE roof_status = 'OPEN' AND timestamp LIKE '$today%'")->fetchColumn();
    $avgLight = $pdo->query("SELECT AVG(light_level) FROM sensor_logs WHERE timestamp LIKE '$today%'")->fetchColumn();

    $response['stats'] = [
        'today_rain_events' => (int)$rainEvents,
        'today_photos_captured' => (int)$photoCount,
        'today_roof_actuations' => (int)$totalOpenings,
        'today_avg_light' => round((float)$avgLight, 1)
    ];
}

echo json_encode($response);
