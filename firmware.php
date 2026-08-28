<?php
/**
 * SkyGuard AI - ESP32 Firmware & Hardware Documentation Viewer
 */

$esp32Code = @file_get_contents(__DIR__ . '/firmware/esp32_firmware.txt') ?: '// File esp32_firmware.txt tidak ditemukan';
$esp32CamCode = @file_get_contents(__DIR__ . '/firmware/esp32_cam_firmware.txt') ?: '// File esp32_cam_firmware.txt tidak ditemukan';
$wiringGuide = @file_get_contents(__DIR__ . '/firmware/WIRING_AND_PINOUT_GUIDE.txt') ?: '// File WIRING_AND_PINOUT_GUIDE.txt tidak ditemukan';
?>
<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kode ESP32 & Skematik - SkyGuard AI</title>
    
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
                <p class="text-[11px] text-slate-400">Pusat Dokumentasi Firmware ESP32 & Skematik Sirkuit</p>
            </div>
        </div>

        <!-- Middle Menu Links -->
        <div class="hidden md:flex items-center gap-2 bg-slate-900/60 p-1.5 rounded-xl border border-slate-800">
            <a href="index.php" class="px-4 py-1.5 rounded-lg text-xs font-semibold text-slate-400 hover:text-slate-200 hover:bg-slate-800/60 transition-all flex items-center gap-2">
                <i class="fas fa-gauge-high"></i> Dashboard
            </a>
            <a href="history.php" class="px-4 py-1.5 rounded-lg text-xs font-semibold text-slate-400 hover:text-slate-200 hover:bg-slate-800/60 transition-all flex items-center gap-2">
                <i class="fas fa-images"></i> Galeri & Riwayat Foto
            </a>
            <a href="firmware.php" class="px-4 py-1.5 rounded-lg text-xs font-bold bg-cyan-500/20 text-cyan-300 border border-cyan-500/30 flex items-center gap-2">
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

        <!-- Header Card -->
        <div class="glass-panel p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-bold text-slate-100 flex items-center gap-2">
                    <i class="fas fa-microchip text-cyan-400"></i> File Kode ESP32 & Panduan Pemasangan
                </h2>
                <p class="text-xs text-slate-400 mt-1">
                    Seluruh kode mikrokontroler disediakan dalam format <code class="px-1.5 py-0.5 rounded bg-slate-800 text-cyan-300 font-mono">.txt</code> yang siap disalin ke Arduino IDE atau PlatformIO.
                </p>
            </div>

            <div class="flex items-center gap-2">
                <a href="firmware/esp32_firmware.txt" download class="px-3 py-2 rounded-xl bg-cyan-600 hover:bg-cyan-500 text-white font-bold text-xs flex items-center gap-2 transition-all shadow-lg shadow-cyan-500/25">
                    <i class="fas fa-download"></i> Unduh ESP32 (.txt)
                </a>
                <a href="firmware/esp32_cam_firmware.txt" download class="px-3 py-2 rounded-xl bg-violet-600 hover:bg-violet-500 text-white font-bold text-xs flex items-center gap-2 transition-all shadow-lg shadow-violet-500/25">
                    <i class="fas fa-download"></i> Unduh ESP32-CAM (.txt)
                </a>
            </div>
        </div>

        <!-- Tab Switching Component -->
        <div class="space-y-4">
            <div class="flex items-center gap-2 bg-slate-900/80 p-1.5 rounded-2xl border border-slate-800 w-fit">
                <button onclick="switchTab('esp32')" id="tabBtn-esp32" class="px-4 py-2 rounded-xl text-xs font-bold bg-cyan-500 text-slate-900 shadow transition-all">
                    <i class="fas fa-microchip mr-1.5"></i> 1. ESP32 Main Firmware
                </button>
                <button onclick="switchTab('esp32cam')" id="tabBtn-esp32cam" class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-400 hover:text-white transition-all">
                    <i class="fas fa-camera mr-1.5"></i> 2. ESP32-CAM AI Vision Firmware
                </button>
                <button onclick="switchTab('wiring')" id="tabBtn-wiring" class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-400 hover:text-white transition-all">
                    <i class="fas fa-plug-circle-bolt mr-1.5"></i> 3. Panduan Skematik & Pinout
                </button>
            </div>

            <!-- Tab 1: ESP32 Main Firmware -->
            <div id="tabContent-esp32" class="glass-panel p-6">
                <div class="flex items-center justify-between pb-3 border-b border-slate-800 mb-4">
                    <div>
                        <h3 class="text-sm font-bold text-slate-200">Kode Firmware ESP32 Utama (C++ / Arduino)</h3>
                        <p class="text-xs text-slate-400">File: <span class="font-mono text-cyan-300">firmware/esp32_firmware.txt</span></p>
                    </div>
                    <button onclick="copyToClipboard('codeEsp32')" class="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-xs font-bold text-cyan-300 border border-slate-700 flex items-center gap-2 transition-all">
                        <i class="fas fa-copy"></i> Salin Semua Kode
                    </button>
                </div>
                <div class="relative bg-slate-950 rounded-xl p-4 border border-slate-800 overflow-x-auto max-h-[500px]">
                    <pre id="codeEsp32" class="font-mono text-xs text-emerald-400 leading-relaxed"><code><?= htmlspecialchars($esp32Code) ?></code></pre>
                </div>
            </div>

            <!-- Tab 2: ESP32-CAM AI Vision Firmware -->
            <div id="tabContent-esp32cam" class="glass-panel p-6 hidden">
                <div class="flex items-center justify-between pb-3 border-b border-slate-800 mb-4">
                    <div>
                        <h3 class="text-sm font-bold text-slate-200">Kode Firmware ESP32-CAM AI Vision</h3>
                        <p class="text-xs text-slate-400">File: <span class="font-mono text-violet-300">firmware/esp32_cam_firmware.txt</span></p>
                    </div>
                    <button onclick="copyToClipboard('codeEsp32Cam')" class="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-xs font-bold text-violet-300 border border-slate-700 flex items-center gap-2 transition-all">
                        <i class="fas fa-copy"></i> Salin Semua Kode
                    </button>
                </div>
                <div class="relative bg-slate-950 rounded-xl p-4 border border-slate-800 overflow-x-auto max-h-[500px]">
                    <pre id="codeEsp32Cam" class="font-mono text-xs text-cyan-400 leading-relaxed"><code><?= htmlspecialchars($esp32CamCode) ?></code></pre>
                </div>
            </div>

            <!-- Tab 3: Wiring Guide -->
            <div id="tabContent-wiring" class="glass-panel p-6 hidden">
                <div class="flex items-center justify-between pb-3 border-b border-slate-800 mb-4">
                    <div>
                        <h3 class="text-sm font-bold text-slate-200">Skematik Pinout & Panduan Perakitan Hardware</h3>
                        <p class="text-xs text-slate-400">File: <span class="font-mono text-amber-300">firmware/WIRING_AND_PINOUT_GUIDE.txt</span></p>
                    </div>
                    <button onclick="copyToClipboard('guideWiring')" class="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-xs font-bold text-amber-300 border border-slate-700 flex items-center gap-2 transition-all">
                        <i class="fas fa-copy"></i> Salin Panduan
                    </button>
                </div>
                <div class="relative bg-slate-950 rounded-xl p-4 border border-slate-800 overflow-x-auto max-h-[500px]">
                    <pre id="guideWiring" class="font-mono text-xs text-slate-300 leading-relaxed"><code><?= htmlspecialchars($wiringGuide) ?></code></pre>
                </div>
            </div>
        </div>

    </main>

    <!-- Copy Notification Toast -->
    <div id="copyToast" class="fixed bottom-6 right-6 z-50 flex items-center gap-2.5 px-4 py-3 rounded-xl border border-emerald-500/40 bg-slate-900/90 text-emerald-300 text-xs font-bold backdrop-blur-xl shadow-2xl transition-all duration-300 transform translate-y-8 opacity-0 pointer-events-none">
        <i class="fas fa-check-circle text-base"></i> Teks berhasil disalin ke clipboard!
    </div>

    <script>
        function switchTab(tabId) {
            ['esp32', 'esp32cam', 'wiring'].forEach(t => {
                const content = document.getElementById('tabContent-' + t);
                const btn = document.getElementById('tabBtn-' + t);
                if (t === tabId) {
                    content.classList.remove('hidden');
                    btn.className = 'px-4 py-2 rounded-xl text-xs font-bold bg-cyan-500 text-slate-900 shadow transition-all';
                } else {
                    content.classList.add('hidden');
                    btn.className = 'px-4 py-2 rounded-xl text-xs font-semibold text-slate-400 hover:text-white transition-all';
                }
            });
        }

        function copyToClipboard(elementId) {
            const text = document.getElementById(elementId).innerText;
            navigator.clipboard.writeText(text).then(() => {
                const toast = document.getElementById('copyToast');
                toast.classList.remove('translate-y-8', 'opacity-0', 'pointer-events-none');
                setTimeout(() => {
                    toast.classList.add('translate-y-8', 'opacity-0', 'pointer-events-none');
                }, 3000);
            });
        }
    </script>
    <!-- Mobile Bottom Sticky Navigation Bar -->
    <div class="sm:hidden mobile-bottom-nav">
        <a href="index.php" class="mobile-nav-item">
            <i class="fas fa-gauge-high"></i>
            <span>Dashboard</span>
        </a>
        <a href="index.php" class="mobile-snap-btn-center" title="Foto Langsung">
            <i class="fas fa-camera"></i>
        </a>
        <a href="history.php" class="mobile-nav-item">
            <i class="fas fa-images"></i>
            <span>Riwayat</span>
        </a>
        <a href="firmware.php" class="mobile-nav-item active">
            <i class="fas fa-microchip"></i>
            <span>ESP32</span>
        </a>
    </div>

</body>
</html>
