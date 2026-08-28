<?php
/**
 * SkyGuard AI - Database Seeder
 * Menginisialisasi ulang skema & data awal (device_state, settings, dll).
 * Cukup akses api/seed.php sekali melalui browser.
 */

require_once __DIR__ . '/db.php';
$pdo = getDbConnection();

// initializeDatabase() sudah dijalankan otomatis oleh getDbConnection().
// Pastikan baris device_state ada.
$count = $pdo->query("SELECT COUNT(*) FROM device_state WHERE id = 1")->fetchColumn();
if ($count == 0) {
    $pdo->exec("INSERT INTO device_state (id, roof_status, control_mode, last_action_reason, updated_at)
                VALUES (1, 'CLOSED', 'AUTO', 'Seeded via seed.php', datetime('now','localtime'))");
}

echo json_encode([
    'success' => true,
    'message' => 'Database SkyGuard AI berhasil di-seed / diverifikasi.',
    'device_state_exists' => (int)$count
]);
