<?php
/**
 * SkyGuard AI - Alerts and Notification API
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

$action = $_GET['action'] ?? $_POST['action'] ?? 'get_alerts';

if ($action === 'get_alerts') {
    $stmt = $pdo->query("SELECT * FROM alerts ORDER BY id DESC LIMIT 20");
    $alerts = $stmt->fetchAll();
    echo json_encode(['success' => true, 'alerts' => $alerts]);
    exit;
}

if ($action === 'mark_read') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = $pdo->prepare("UPDATE alerts SET is_read = 1 WHERE id = ?");
        $stmt->execute([$id]);
    } else {
        $pdo->exec("UPDATE alerts SET is_read = 1");
    }
    echo json_encode(['success' => true, 'message' => 'Notifikasi ditandai telah dibaca']);
    exit;
}

if ($action === 'clear_all') {
    $pdo->exec("DELETE FROM alerts");
    echo json_encode(['success' => true, 'message' => 'Semua notifikasi berhasil dihapus']);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Aksi tidak valid']);
