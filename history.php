<?php
/**
 * SkyGuard AI - Photo Gallery & Telemetry Audit History
 */
require_once __DIR__ . '/api/db.php';
$pdo = getDbConnection();

// Batasi riwayat foto & log hanya 10 entri terbaru (hapus sisanya)
enforceRetention($pdo, 10);

// Fetch Photo History
$photosStmt = $pdo->query("SELECT * FROM camera_history ORDER BY id DESC LIMIT 10");
$photos = $photosStmt->fetchAll();

// Fetch Sensor History Logs
$logsStmt = $pdo->query("SELECT * FROM sensor_logs ORDER BY id DESC LIMIT 10");
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

        <div>
            <a href="index.php" class="px-3.5 py-1.5 rounded-xl text-xs font-bold bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 flex items-center gap-2 transition-all">
                <i class="fas fa-arrow-left"></i> Kembali ke Dashboard
            </a>
        </div>
    </nav>

    <!-- Main Container -->
    <main class="max-w-7xl mx-auto px-4 lg:px-8 pt-6 pb-28 space-y-6">

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

                <button onclick="skyguardDeleteAllPhotos()" class="shrink-0 self-start sm:self-auto px-3 py-1.5 rounded-lg bg-rose-600/90 hover:bg-rose-500 text-white text-[11px] font-semibold shadow transition-all whitespace-nowrap flex items-center gap-1.5">
                    <i class="fas fa-trash-can"></i> Hapus Semua Foto
                </button>
            </div>

            <?php if (empty($photos)): ?>
                <div class="text-center py-12 text-slate-500">
                    <i class="fas fa-image text-4xl mb-3 opacity-40"></i>
                    <p class="text-sm">Belum ada foto cuaca yang tersimpan.</p>
                    <p class="text-xs text-slate-600 mt-1">Foto akan muncul otomatis saat ESP32-CAM mengirimkan tangkapan cuaca.</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
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
                            $photoModalData = [
                                'image'          => $p['image_path'],
                                'ts'             => $p['timestamp'],
                                'source'         => $p['source'],
                                'classification' => $p['ai_classification'],
                                'confidence'     => round($p['ai_confidence'], 1),
                                'light'          => $p['light_detected'] ? 'Terang / Ada Cahaya' : 'Gelap',
                                'roof'           => $p['roof_action'],
                                'notes'          => $p['notes'] ?: 'Tidak ada catatan analisis khusus.'
                            ];
                            $photoModalAttr = htmlspecialchars(json_encode($photoModalData), ENT_QUOTES, 'UTF-8');
                        ?>
                        <div class="bg-slate-900/80 rounded-2xl border border-slate-800 overflow-hidden hover:border-cyan-500/40 transition-all group flex flex-col justify-between">
                            <div class="relative h-32 sm:h-44 bg-slate-950 overflow-hidden">
                                <img src="<?= htmlspecialchars($p['image_path']) ?>" onerror="this.src='https://images.unsplash.com/photo-1534088568595-a066f410bcda?w=640&auto=format&fit=crop&q=60'" alt="Weather Photo" class="w-full h-full object-cover group-hover:scale-105 transition-all duration-300">
                                
                                <div class="absolute top-2 left-2 px-2 py-0.5 rounded-md text-[10px] font-bold border backdrop-blur-md <?= $badgeColor ?>">
                                    <?= htmlspecialchars($p['ai_classification']) ?>
                                </div>
                                <div class="absolute bottom-2 right-2 px-2 py-0.5 rounded-md text-[10px] font-semibold bg-black/70 text-slate-300 backdrop-blur-md">
                                    Akurasi: <?= round($p['ai_confidence'], 1) ?>%
                                </div>
                                <button onclick="skyguardDeletePhoto(<?= (int)$p['id'] ?>)" title="Hapus foto ini"
                                    class="absolute top-2 right-2 w-7 h-7 rounded-lg bg-rose-600/85 hover:bg-rose-600 text-white text-xs flex items-center justify-center backdrop-blur-md shadow transition-all">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>

                            <div class="p-4 space-y-2">
                                <div class="flex items-center justify-between text-[11px] text-slate-400">
                                    <span><i class="fas fa-clock mr-1 text-slate-500"></i> <?= $p['timestamp'] ?></span>
                                    <span class="capitalize px-1.5 py-0.5 rounded bg-slate-800 text-[10px]"><?= $p['source'] ?></span>
                                </div>

                                <p class="text-xs text-slate-300 line-clamp-2 leading-relaxed">
                                    <?= htmlspecialchars($p['notes'] ?: 'Tidak ada catatan analisis khusus.') ?>
                                </p>

                                <button type="button" onclick="skyguardOpenPhotoDetail(this)"
                                    data-photo="<?= $photoModalAttr ?>"
                                    class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-cyan-400 hover:text-cyan-300 transition-colors mt-1">
                                    <i class="fas fa-expand-alt"></i> Lihat Selengkapnya
                                </button>

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
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-800 mb-4">
                <div>
                    <h2 class="text-base font-bold text-slate-200 flex items-center gap-2">
                        <i class="fas fa-list-check text-cyan-400"></i> Log Telemetri Sensor & Aksi Sistem
                    </h2>
                    <p class="text-xs text-slate-400">Catatan pembacaan sensor dan perubahan posisi atap</p>
                </div>
                <button onclick="skyguardDeleteAllSensorLogs()" class="shrink-0 self-start sm:self-auto px-3 py-1.5 rounded-lg bg-rose-600/90 hover:bg-rose-500 text-white text-[11px] font-semibold shadow transition-all whitespace-nowrap flex items-center gap-1.5">
                    <i class="fas fa-trash-can"></i> Hapus Semua Log
                </button>
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
                            <th class="py-3 px-4 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="9" class="py-6 text-center text-slate-500">Belum ada data log sensor.</td>
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
                                    <td class="py-3 px-4">
                                        <button onclick="skyguardDeleteSensorLog(<?= (int)$l['id'] ?>)" title="Hapus log ini" class="w-7 h-7 rounded-lg bg-rose-600/85 hover:bg-rose-600 text-white text-xs flex items-center justify-center shadow transition-all">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
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
        <a href="history.php" class="mobile-nav-item active">
            <i class="fas fa-images"></i>
            <span>Riwayat AI</span>
        </a>
    </div>

    <!-- Desktop Bottom Navigation (mirrors mobile, shown on sm+) -->
    <div class="desktop-bottom-nav">
        <a href="index.php">
            <i class="fas fa-gauge-high"></i> Dashboard
        </a>
        <a href="history.php" class="active">
            <i class="fas fa-images"></i> Riwayat AI
        </a>
    </div>

    <!-- Modal Detail Foto (Lihat Selengkapnya) -->
    <div id="photoDetailModal"
        class="fixed inset-0 z-[2000] hidden items-center justify-center p-4 pt-20 pb-24 sm:p-6 sm:pt-6 sm:pb-6 bg-black/70 backdrop-blur-sm"
        onclick="if(event.target===this) skyguardClosePhotoDetail()">
        <div class="glass-panel w-full max-w-lg max-h-[calc(100vh-12rem)] sm:max-h-[88vh] overflow-y-auto rounded-2xl border border-slate-700 shadow-2xl">
            <div class="flex items-center justify-between px-5 py-4 border-b border-slate-800 sticky top-0 bg-slate-900/95 backdrop-blur z-10">
                <h3 class="text-sm font-bold text-slate-200 flex items-center gap-2">
                    <i class="fas fa-camera-retro text-cyan-400"></i> Detail Analisis Foto
                </h3>
                <button onclick="skyguardClosePhotoDetail()"
                    class="w-8 h-8 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 flex items-center justify-center transition-colors">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="p-5 space-y-4">
                <img id="pdImage" src="" alt="Detail Foto Cuaca"
                    class="w-full rounded-xl border border-slate-700 object-cover max-h-80 bg-slate-950"
                    onerror="this.style.display='none'">

                <div class="flex flex-wrap items-center gap-2">
                    <span id="pdClass" class="px-2.5 py-1 rounded-md text-[11px] font-bold border"></span>
                    <span id="pdConf" class="text-[11px] text-slate-400"></span>
                </div>

                <div class="grid grid-cols-2 gap-3 text-[11px]">
                    <div class="bg-slate-800/50 rounded-lg p-3">
                        <span class="text-slate-500 block mb-1">Waktu Tangkap</span>
                        <span id="pdTs" class="text-slate-200 font-semibold"></span>
                    </div>
                    <div class="bg-slate-800/50 rounded-lg p-3">
                        <span class="text-slate-500 block mb-1">Sumber</span>
                        <span id="pdSrc" class="text-slate-200 font-semibold capitalize"></span>
                    </div>
                    <div class="bg-slate-800/50 rounded-lg p-3">
                        <span class="text-slate-500 block mb-1">Cahaya Terdeteksi</span>
                        <span id="pdLight" class="text-slate-200 font-semibold"></span>
                    </div>
                    <div class="bg-slate-800/50 rounded-lg p-3">
                        <span class="text-slate-500 block mb-1">Tindakan Atap</span>
                        <span id="pdRoof" class="font-bold"></span>
                    </div>
                </div>

                <div class="bg-slate-800/40 rounded-xl p-4">
                    <span class="text-[11px] uppercase tracking-wider text-slate-500 font-bold">Penjelasan Lengkap Analisis AI</span>
                    <p id="pdNotes" class="text-xs text-slate-300 leading-relaxed mt-2 whitespace-pre-line"></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Skrip penghapusan riwayat foto -->
    <script>
        function skyguardOpenPhotoDetail(btn) {
            var d;
            try { d = JSON.parse(btn.getAttribute('data-photo')); }
            catch (e) { console.error('Invalid photo data', e); return; }

            document.getElementById('pdImage').src = d.image || '';
            document.getElementById('pdImage').style.display = d.image ? 'block' : 'none';

            var cls = document.getElementById('pdClass');
            cls.textContent = d.classification || '-';
            var badge = 'bg-cyan-500/20 text-cyan-300 border-cyan-500/30';
            var c = (d.classification || '').toUpperCase();
            if (c.indexOf('MENDUNG') !== -1) badge = 'bg-amber-500/20 text-amber-300 border-amber-500/30';
            else if (c.indexOf('LAMP') !== -1) badge = 'bg-orange-500/20 text-orange-300 border-orange-500/30';
            else if (c.indexOf('HUJAN') !== -1) badge = 'bg-rose-500/20 text-rose-300 border-rose-500/30';
            cls.className = 'px-2.5 py-1 rounded-md text-[11px] font-bold border ' + badge;

            document.getElementById('pdConf').textContent = 'Akurasi: ' + (d.confidence != null ? d.confidence + '%' : '-');
            document.getElementById('pdTs').textContent = d.ts || '-';
            document.getElementById('pdSrc').textContent = d.source || '-';
            document.getElementById('pdLight').textContent = d.light || '-';

            var roof = document.getElementById('pdRoof');
            if (d.roof === 'OPENED') { roof.textContent = 'DIBUKA'; roof.className = 'font-bold text-emerald-400'; }
            else if (d.roof === 'CLOSED') { roof.textContent = 'DITUTUP'; roof.className = 'font-bold text-rose-400'; }
            else { roof.textContent = 'TETAP'; roof.className = 'font-semibold text-slate-400'; }

            document.getElementById('pdNotes').textContent = d.notes || 'Tidak ada catatan analisis khusus.';

            var m = document.getElementById('photoDetailModal');
            m.classList.remove('hidden');
            m.classList.add('flex');
            document.body.style.overflow = 'hidden';
        }

        function skyguardClosePhotoDetail() {
            var m = document.getElementById('photoDetailModal');
            m.classList.add('hidden');
            m.classList.remove('flex');
            document.body.style.overflow = '';
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') skyguardClosePhotoDetail();
        });

        function skyguardDeletePhoto(id) {
            if (!confirm('Hapus foto riwayat ini beserta file gambarnya?')) return;
            fetch('api/history.php?action=delete_photo', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id })
            })
            .then(r => r.json())
            .then(d => {
                if (d.success) { location.reload(); }
                else { alert(d.error || 'Gagal menghapus foto.'); }
            })
            .catch(e => alert('Gagal berkomunikasi dengan server.'));
        }

        function skyguardDeleteAllPhotos() {
            if (!confirm('Hapus SELURUH riwayat foto? Tindakan ini tidak dapat dibatalkan.')) return;
            fetch('api/history.php?action=delete_all_photos', { method: 'POST' })
            .then(r => r.json())
            .then(d => {
                if (d.success) { location.reload(); }
                else { alert(d.error || 'Gagal menghapus foto.'); }
            })
            .catch(e => alert('Gagal berkomunikasi dengan server.'));
        }

        function skyguardDeleteSensorLog(id) {
            if (!confirm('Hapus log sensor ini?')) return;
            fetch('api/history.php?action=delete_sensor_log', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id })
            })
            .then(r => r.json())
            .then(d => {
                if (d.success) { location.reload(); }
                else { alert(d.error || 'Gagal menghapus log.'); }
            })
            .catch(e => alert('Gagal berkomunikasi dengan server.'));
        }

        function skyguardDeleteAllSensorLogs() {
            if (!confirm('Hapus SELURUH riwayat log sensor? Tindakan ini tidak dapat dibatalkan.')) return;
            fetch('api/history.php?action=delete_all_sensor_logs', { method: 'POST' })
            .then(r => r.json())
            .then(d => {
                if (d.success) { location.reload(); }
                else { alert(d.error || 'Gagal menghapus log.'); }
            })
            .catch(e => alert('Gagal berkomunikasi dengan server.'));
        }
    </script>

</body>
</html>
