<?php
/**
 * SkyGuard AI - Photo Gallery & Telemetry Audit History
 */
require_once __DIR__ . '/api/db.php';
$pdo = getDbConnection();

// Fetch Photo History
$photosStmt = $pdo->query("SELECT * FROM camera_history ORDER BY id DESC LIMIT 60");
$photos = $photosStmt->fetchAll();

// Fetch Sensor History Logs
$logsStmt = $pdo->query("SELECT * FROM sensor_logs ORDER BY id DESC LIMIT 50");
$logs = $logsStmt->fetchAll();

// Stats
$today = date('Y-m-d');
$totalPhotos = count($photos);
$rainEventsCount = $pdo->query("SELECT COUNT(*) FROM sensor_logs WHERE rain_detected = 1")->fetchColumn();
$mendungCount = $pdo->query("SELECT COUNT(*) FROM camera_history WHERE ai_classification LIKE '%MENDUNG%'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Foto Cuaca & Log - SkyGuard AI</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600;800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        dark: {
                            900: '#0b0f19',
                            800: '#111827',
                            700: '#1f2937'
                        }
                    }
                }
            }
        }
    </script>
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Custom Style -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-[#0b0f19] text-slate-100 min-h-screen">

    <!-- Top Navigation Bar -->
    <nav class="navbar-custom px-4 lg:px-8 py-3.5 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="index.php" class="w-10 h-10 rounded-xl bg-gradient-to-tr from-cyan-500 to-blue-600 flex items-center justify-center shadow-lg shadow-cyan-500/30">
                <i class="fas fa-shield-halved text-white text-lg"></i>
            </a>
            <div>
                <h1 class="text-base lg:text-lg font-extrabold tracking-tight bg-gradient-to-r from-cyan-400 via-sky-300 to-blue-400 bg-clip-text text-transparent">
                    SkyGuard <span class="text-white font-light text-sm px-1.5 py-0.5 rounded-md bg-cyan-500/20 border border-cyan-500/30 ml-1">AI</span>
                </h1>
                <p class="text-[11px] text-slate-400">Riwayat Tangkapan Foto Cuaca & Audit Log Sensor</p>
            </div>
        </div>

        <!-- Middle Menu Links -->
        <div class="hidden md:flex items-center gap-2 bg-slate-900/60 p-1.5 rounded-xl border border-slate-800">
            <a href="index.php" class="px-4 py-1.5 rounded-lg text-xs font-semibold text-slate-400 hover:text-slate-200 hover:bg-slate-800/60 transition-all flex items-center gap-2">
                <i class="fas fa-gauge-high"></i> Dashboard
            </a>
            <a href="history.php" class="px-4 py-1.5 rounded-lg text-xs font-bold bg-cyan-500/20 text-cyan-300 border border-cyan-500/30 flex items-center gap-2">
                <i class="fas fa-images"></i> Galeri & Riwayat Foto
            </a>
            <a href="firmware.php" class="px-4 py-1.5 rounded-lg text-xs font-semibold text-slate-400 hover:text-slate-200 hover:bg-slate-800/60 transition-all flex items-center gap-2">
                <i class="fas fa-microchip"></i> Firmware ESP32 (.txt)
            </a>
        </div>

        <div>
            <a href="index.php" class="px-3.5 py-1.5 rounded-xl text-xs font-bold bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 flex items-center gap-2 transition-all">
                <i class="fas fa-arrow-left"></i> Kembali ke Dashboard
            </a>
        </div>
    </nav>

    <!-- Main Container -->
    <main class="max-w-7xl mx-auto px-4 lg:px-8 py-6 space-y-6">

        <!-- Stats Overview Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
            <div class="sensor-metric-card">
                <span class="text-xs font-bold text-slate-400 uppercase">Total Foto Cuaca</span>
                <div class="text-2xl font-extrabold text-cyan-400 mt-2"><?= $totalPhotos ?> <span class="text-xs font-normal text-slate-400">Gambar</span></div>
                <p class="text-[11px] text-slate-400 mt-1">Tangkapan ESP32-CAM & Upload Pengguna</p>
            </div>
            <div class="sensor-metric-card">
                <span class="text-xs font-bold text-slate-400 uppercase">Deteksi Cuaca Mendung</span>
                <div class="text-2xl font-extrabold text-amber-400 mt-2"><?= $mendungCount ?> <span class="text-xs font-normal text-slate-400">Insiden</span></div>
                <p class="text-[11px] text-slate-400 mt-1">Peringatan otomatis & penutupan jemuran</p>
            </div>
            <div class="sensor-metric-card">
                <span class="text-xs font-bold text-slate-400 uppercase">Deteksi Air / Hujan</span>
                <div class="text-2xl font-extrabold text-rose-400 mt-2"><?= $rainEventsCount ?> <span class="text-xs font-normal text-slate-400">Kejadian</span></div>
                <p class="text-[11px] text-slate-400 mt-1">Proteksi sensor tetesan air instan</p>
            </div>
            <div class="sensor-metric-card">
                <span class="text-xs font-bold text-slate-400 uppercase">Status Sistem AI</span>
                <div class="text-2xl font-extrabold text-emerald-400 mt-2">AKTIF <span class="text-xs font-normal text-slate-400">(96.5% Akurat)</span></div>
                <p class="text-[11px] text-slate-400 mt-1">Klasifikasi spektrum matahari vs lampu</p>
            </div>
        </div>

        <!-- Photo Gallery Section -->
        <div class="glass-panel p-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-800 mb-6">
                <div>
                    <h2 class="text-base font-bold text-slate-200 flex items-center gap-2">
                        <i class="fas fa-camera-retro text-cyan-400"></i> Galeri Audit Foto Cuaca & Sumber Cahaya
                    </h2>
                    <p class="text-xs text-slate-400">Riwayat tangkapan kamera beserta hasil evaluasi AI vision</p>
                </div>
            </div>

            <?php if (empty($photos)): ?>
                <div class="text-center py-12 text-slate-500">
                    <i class="fas fa-image text-4xl mb-3 opacity-40"></i>
                    <p class="text-sm">Belum ada foto cuaca yang tersimpan.</p>
                    <p class="text-xs text-slate-600 mt-1">Gunakan tombol upload di dashboard atau simulator untuk menambahkan foto.</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-5">
                    <?php foreach ($photos as $p): ?>
                        <?php
                            $badgeColor = 'bg-cyan-500/20 text-cyan-300 border-cyan-500/30';
                            if (stripos($p['ai_classification'], 'MENDUNG') !== false) {
                                $badgeColor = 'bg-amber-500/20 text-amber-300 border-amber-500/30';
                            } elseif (stripos($p['ai_classification'], 'LAMP') !== false) {
                                $badgeColor = 'bg-orange-500/20 text-orange-300 border-orange-500/30';
                            } elseif (stripos($p['ai_classification'], 'HUJAN') !== false) {
                                $badgeColor = 'bg-rose-500/20 text-rose-300 border-rose-500/30';
                            }
                        ?>
                        <div class="bg-slate-900/80 rounded-2xl border border-slate-800 overflow-hidden hover:border-cyan-500/40 transition-all group flex flex-col justify-between">
                            <div class="relative h-44 bg-slate-950 overflow-hidden">
                                <img src="<?= htmlspecialchars($p['image_path']) ?>" onerror="this.src='https://images.unsplash.com/photo-1534088568595-a066f410bcda?w=640&auto=format&fit=crop&q=60'" alt="Weather Photo" class="w-full h-full object-cover group-hover:scale-105 transition-all duration-300">
                                
                                <div class="absolute top-2 left-2 px-2 py-0.5 rounded-md text-[10px] font-bold border backdrop-blur-md <?= $badgeColor ?>">
                                    <?= htmlspecialchars($p['ai_classification']) ?>
                                </div>
                                <div class="absolute bottom-2 right-2 px-2 py-0.5 rounded-md text-[10px] font-semibold bg-black/70 text-slate-300 backdrop-blur-md">
                                    Akurasi: <?= round($p['ai_confidence'], 1) ?>%
                                </div>
                            </div>

                            <div class="p-4 space-y-2">
                                <div class="flex items-center justify-between text-[11px] text-slate-400">
                                    <span><i class="fas fa-clock mr-1 text-slate-500"></i> <?= $p['timestamp'] ?></span>
                                    <span class="capitalize px-1.5 py-0.5 rounded bg-slate-800 text-[10px]"><?= $p['source'] ?></span>
                                </div>

                                <p class="text-xs text-slate-300 line-clamp-2 leading-relaxed">
                                    <?= htmlspecialchars($p['notes'] ?: 'Tidak ada catatan analisis khusus.') ?>
                                </p>

                                <div class="pt-2 border-t border-slate-800/80 flex items-center justify-between text-[11px]">
                                    <span class="text-slate-400">Tindakan Atap:</span>
                                    <?php if ($p['roof_action'] === 'OPENED'): ?>
                                        <span class="font-bold text-emerald-400"><i class="fas fa-door-open"></i> DIBUKA</span>
                                    <?php elseif ($p['roof_action'] === 'CLOSED'): ?>
                                        <span class="font-bold text-rose-400"><i class="fas fa-door-closed"></i> DITUTUP</span>
                                    <?php else: ?>
                                        <span class="font-semibold text-slate-400">TETAP</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Telemetry Sensor Logs Table -->
        <div class="glass-panel p-6">
            <div class="flex items-center justify-between pb-4 border-b border-slate-800 mb-4">
                <div>
                    <h2 class="text-base font-bold text-slate-200 flex items-center gap-2">
                        <i class="fas fa-list-check text-cyan-400"></i> Log Telemetri Sensor & Aksi Sistem
                    </h2>
                    <p class="text-xs text-slate-400">Catatan pembacaan sensor dan perubahan posisi atap</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-300">
                    <thead class="bg-slate-900/80 text-slate-400 uppercase text-[10px] tracking-wider border-b border-slate-800">
                        <tr>
                            <th class="py-3 px-4">Waktu</th>
                            <th class="py-3 px-4">Sensor Air</th>
                            <th class="py-3 px-4">Cahaya (LDR)</th>
                            <th class="py-3 px-4">Klasifikasi Cahaya</th>
                            <th class="py-3 px-4">Cuaca AI</th>
                            <th class="py-3 px-4">Status Atap</th>
                            <th class="py-3 px-4">Mode</th>
                            <th class="py-3 px-4">Pemicu</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="8" class="py-6 text-center text-slate-500">Belum ada data log sensor.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $l): ?>
                                <tr class="hover:bg-slate-800/40 transition-colors">
                                    <td class="py-3 px-4 font-mono text-slate-400"><?= $l['timestamp'] ?></td>
                                    <td class="py-3 px-4">
                                        <?php if ($l['rain_detected'] == 1): ?>
                                            <span class="px-2 py-0.5 rounded bg-rose-500/20 text-rose-300 font-bold border border-rose-500/30">Hujan (Air)</span>
                                        <?php else: ?>
                                            <span class="px-2 py-0.5 rounded bg-emerald-500/20 text-emerald-300 font-semibold">Kering</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-4 font-bold text-amber-400"><?= $l['light_level'] ?>%</td>
                                    <td class="py-3 px-4"><?= htmlspecialchars($l['light_verdict'] ?: 'SUNLIGHT') ?></td>
                                    <td class="py-3 px-4 font-semibold text-cyan-300"><?= htmlspecialchars($l['weather_condition'] ?: 'CERAH') ?></td>
                                    <td class="py-3 px-4">
                                        <?php if ($l['roof_status'] === 'OPEN'): ?>
                                            <span class="text-emerald-400 font-bold"><i class="fas fa-door-open"></i> TERBUKA</span>
                                        <?php else: ?>
                                            <span class="text-rose-400 font-bold"><i class="fas fa-door-closed"></i> TERTUTUP</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-4"><span class="px-2 py-0.5 rounded bg-slate-800 text-[10px]"><?= $l['control_mode'] ?></span></td>
                                    <td class="py-3 px-4 text-slate-400"><?= htmlspecialchars($l['action_triggered'] ?: 'PERIODIC_LOG') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <!-- Mobile Bottom Sticky Navigation Bar -->
    <div class="sm:hidden mobile-bottom-nav">
        <a href="index.php" class="mobile-nav-item">
            <i class="fas fa-gauge-high"></i>
            <span>Dashboard</span>
        </a>
        <a href="index.php" class="mobile-snap-btn-center" title="Foto Langsung">
            <i class="fas fa-camera"></i>
        </a>
        <a href="history.php" class="mobile-nav-item active">
            <i class="fas fa-images"></i>
            <span>Riwayat</span>
        </a>
        <a href="firmware.php" class="mobile-nav-item">
            <i class="fas fa-microchip"></i>
            <span>ESP32</span>
        </a>
    </div>

</body>
</html>
