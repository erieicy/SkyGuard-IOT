<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SkyGuard AI - Dashboard Jemuran Pintar IoT</title>
    
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
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Custom Style -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-[#0b0f19] text-slate-100 min-h-screen">

    <!-- Top Navigation Bar -->
    <nav class="navbar-custom px-4 lg:px-8 py-3.5 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-cyan-500 to-blue-600 flex items-center justify-center shadow-lg shadow-cyan-500/30">
                <i class="fas fa-shield-halved text-white text-lg"></i>
            </div>
            <div>
                <h1 class="text-base lg:text-lg font-extrabold tracking-tight bg-gradient-to-r from-cyan-400 via-sky-300 to-blue-400 bg-clip-text text-transparent">
                    SkyGuard <span class="text-white font-light text-sm px-1.5 py-0.5 rounded-md bg-cyan-500/20 border border-cyan-500/30 ml-1">AI</span>
                </h1>
                <p class="text-[11px] text-slate-400 hidden sm:block">Automated IoT Clothesline & Weather Vision System</p>
            </div>
        </div>

        <!-- Middle Menu Links -->
        <div class="hidden md:flex items-center gap-2 bg-slate-900/60 p-1.5 rounded-xl border border-slate-800">
            <a href="index.php" class="px-4 py-1.5 rounded-lg text-xs font-bold bg-cyan-500/20 text-cyan-300 border border-cyan-500/30 flex items-center gap-2">
                <i class="fas fa-gauge-high"></i> Dashboard
            </a>
            <a href="history.php" class="px-4 py-1.5 rounded-lg text-xs font-semibold text-slate-400 hover:text-slate-200 hover:bg-slate-800/60 transition-all flex items-center gap-2">
                <i class="fas fa-images"></i> Galeri & Riwayat Foto AI
            </a>
        </div>

        <!-- Right System Status -->
        <div class="flex items-center gap-3">
            <button onclick="document.getElementById('settingsModal').classList.toggle('hidden')" class="px-3.5 py-1.5 rounded-xl text-xs font-bold bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 hover:border-cyan-500/40 shadow flex items-center gap-2 transition-all">
                <i class="fas fa-gear text-cyan-400"></i> <span class="hidden sm:inline">Pengaturan</span> AI
            </button>
            <div class="flex items-center gap-2 bg-slate-900/80 px-3.5 py-1.5 rounded-xl border border-slate-800">
                <span id="espStatusDot" class="pulse-dot offline"></span>
                <span id="espStatusText" class="text-xs font-semibold text-rose-400">ESP32 STANDBY</span>
            </div>
        </div>
    </nav>

    <!-- Main Content Container -->
    <main class="max-w-7xl mx-auto px-4 lg:px-8 py-6 space-y-6">

        <!-- Top Row: Interactive Roof Visualizer & Control Station -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

            <!-- Left: Animated SVG Roof Visualizer (7 Cols) -->
            <div class="lg:col-span-7 glass-panel p-5 relative overflow-hidden flex flex-col justify-between">
                <div class="flex items-center justify-between mb-3 z-20">
                    <div>
                        <h2 class="text-sm font-bold text-slate-300 flex items-center gap-2">
                            <i class="fas fa-house-chimney-window text-cyan-400"></i> Visualisasi Status Jemuran Real-Time
                        </h2>
                        <p class="text-[11px] text-slate-400">Posisi atap, cuaca sekitar, dan animasi penggerak</p>
                    </div>
                    <span id="roofStatusBadge" class="badge-status badge-status-closed">
                        <i class="fas fa-shield-alt"></i> ATAP TERTUTUP RAPAT
                    </span>
                </div>

                <!-- Animated Interactive Sky & Clothesline Visualizer -->
                <div id="roofVisualizerBox" class="roof-visualizer-box sky-sunny my-2">
                    <!-- Sun Orb -->
                    <div id="sunOrb" class="sun-orb"></div>

                    <!-- Rain Animation Drops Container -->
                    <div id="rainAnimationContainer" class="rain-container" style="display: none;"></div>

                    <!-- SVG Clothesline Rack & Moving Canopy Roof -->
                    <svg id="roofStructure" class="clothesline-structure roof-state-closed" viewBox="0 0 440 240" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <!-- Ground / Base Stand -->
                        <path d="M40 220 H400" stroke="#334155" stroke-width="8" stroke-linecap="round"/>
                        <path d="M70 220 L100 180 M370 220 L340 180" stroke="#475569" stroke-width="6" stroke-linecap="round"/>
                        <path d="M100 180 V70 M340 180 V70" stroke="#64748b" stroke-width="8" stroke-linecap="round"/>
                        <path d="M90 75 H350" stroke="#94a3b8" stroke-width="5" stroke-linecap="round"/>

                        <!-- Hanging Clothes Lines -->
                        <path d="M100 100 Q220 112 340 100" stroke="#cbd5e1" stroke-width="3" stroke-dasharray="6 3"/>

                        <!-- Clothes 1 (T-Shirt Cyan) -->
                        <g class="clothes-item" transform="translate(130, 102)">
                            <!-- Hanger -->
                            <path d="M25 0 L0 16 H50 Z" fill="none" stroke="#e2e8f0" stroke-width="2"/>
                            <!-- Shirt -->
                            <path d="M10 16 L0 26 L12 34 L12 70 H38 L38 34 L50 26 L40 16 Z" fill="#06b6d4" opacity="0.9"/>
                        </g>

                        <!-- Clothes 2 (Dress / Pants Amber) -->
                        <g class="clothes-item" transform="translate(205, 104)">
                            <path d="M20 0 L0 14 H40 Z" fill="none" stroke="#e2e8f0" stroke-width="2"/>
                            <path d="M8 14 L0 24 L10 30 L6 80 H34 L30 30 L40 24 L32 14 Z" fill="#f59e0b" opacity="0.9"/>
                        </g>

                        <!-- Clothes 3 (T-Shirt Violet) -->
                        <g class="clothes-item" transform="translate(270, 102)">
                            <path d="M25 0 L0 16 H50 Z" fill="none" stroke="#e2e8f0" stroke-width="2"/>
                            <path d="M10 16 L0 26 L12 34 L12 68 H38 L38 34 L50 26 L40 16 Z" fill="#8b5cf6" opacity="0.9"/>
                        </g>

                        <!-- Retractable Sliding Roof Canopy (Left & Right Wings) -->
                        <!-- Left Canopy Wing -->
                        <g class="canopy-left">
                            <path d="M60 40 L220 20 L220 60 L60 60 Z" fill="url(#canopyGradLeft)" stroke="#38bdf8" stroke-width="2"/>
                            <path d="M60 40 L220 20" stroke="#ffffff" stroke-width="2" stroke-dasharray="4 2"/>
                            <!-- Metal Track / Servo Arm -->
                            <circle cx="65" cy="50" r="5" fill="#f43f5e"/>
                            <line x1="65" y1="50" x2="100" y2="70" stroke="#f43f5e" stroke-width="3"/>
                        </g>

                        <!-- Right Canopy Wing -->
                        <g class="canopy-right">
                            <path d="M380 40 L220 20 L220 60 L380 60 Z" fill="url(#canopyGradRight)" stroke="#38bdf8" stroke-width="2"/>
                            <path d="M380 40 L220 20" stroke="#ffffff" stroke-width="2" stroke-dasharray="4 2"/>
                            <circle cx="375" cy="50" r="5" fill="#f43f5e"/>
                            <line x1="375" y1="50" x2="340" y2="70" stroke="#f43f5e" stroke-width="3"/>
                        </g>

                        <!-- Center Protective Apex Cap -->
                        <polygon points="210,18 230,18 225,25 215,25" fill="#38bdf8"/>

                        <!-- SVG Gradients -->
                        <defs>
                            <linearGradient id="canopyGradLeft" x1="0" y1="0" x2="1" y2="0">
                                <stop offset="0%" stop-color="#0284c7" stop-opacity="0.95"/>
                                <stop offset="100%" stop-color="#0ea5e9" stop-opacity="0.85"/>
                            </linearGradient>
                            <linearGradient id="canopyGradRight" x1="0" y1="0" x2="1" y2="0">
                                <stop offset="0%" stop-color="#0ea5e9" stop-opacity="0.85"/>
                                <stop offset="100%" stop-color="#0284c7" stop-opacity="0.95"/>
                            </linearGradient>
                        </defs>
                    </svg>
                </div>

                <!-- Last Action Status Footer -->
                <div class="pt-2 flex items-center justify-between text-xs text-slate-400 border-t border-slate-800">
                    <span class="flex items-center gap-1.5 truncate">
                        <i class="fas fa-circle-info text-cyan-400"></i> Alasan Tindakan: <span id="lastActionReason" class="text-slate-200 font-medium truncate">Menginisialisasi sistem...</span>
                    </span>
                    <span id="serverTimeDisplay" class="text-[11px] text-slate-500 whitespace-nowrap">--:--:--</span>
                </div>
            </div>

            <!-- Right: Control Center & Mode Management (5 Cols) -->
            <div class="lg:col-span-5 glass-panel p-5 flex flex-col justify-between space-y-4">
                <div>
                    <h2 class="text-sm font-bold text-slate-300 flex items-center gap-2 mb-1">
                        <i class="fas fa-sliders text-cyan-400"></i> Panel Kontrol & Mode Operasi
                    </h2>
                    <p class="text-[11px] text-slate-400 mb-4">Pilih metode pengendalian motor atap jemuran</p>

                    <!-- Mode Selector Pills -->
                    <div class="grid grid-cols-3 gap-2 p-1.5 bg-slate-900/80 rounded-2xl border border-slate-800 mb-4">
                        <button id="btnModeAuto" onclick="App.setMode('AUTO')" class="mode-pill-btn justify-center active-auto">
                            <i class="fas fa-robot text-xs"></i> Otomatis AI
                        </button>
                        <button id="btnModeManual" onclick="App.setMode('MANUAL')" class="mode-pill-btn justify-center">
                            <i class="fas fa-hand text-xs"></i> Manual
                        </button>
                        <button id="btnModeTimer" onclick="App.setMode('TIMER')" class="mode-pill-btn justify-center">
                            <i class="fas fa-stopwatch text-xs"></i> Timer
                        </button>
                    </div>

                    <!-- Direct Action Buttons -->
                    <div class="grid grid-cols-2 gap-3 mb-4">
                        <button id="btnOpenRoof" onclick="App.setRoof('OPEN')" class="btn-action-open flex items-center justify-center gap-2 text-sm">
                            <i class="fas fa-door-open text-base"></i> Buka Jemuran
                        </button>
                        <button id="btnCloseRoof" onclick="App.setRoof('CLOSED')" class="btn-action-close flex items-center justify-center gap-2 text-sm">
                            <i class="fas fa-door-closed text-base"></i> Tutup Jemuran
                        </button>
                    </div>

                    <!-- Smart Feature Toggle: Auto Close on Mendung -->
                    <div class="p-3.5 rounded-xl bg-slate-900/60 border border-slate-800 flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-lg bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400">
                                <i class="fas fa-cloud-sun-rain text-sm"></i>
                            </div>
                            <div>
                                <h4 class="text-xs font-bold text-slate-200">Auto-Close Saat Mendung</h4>
                                <p class="text-[10px] text-slate-400">Tutup otomatis jika AI mendeteksi awan gelap</p>
                            </div>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" id="chkMendungAutoClose" onchange="App.toggleMendungAutoClose(this.checked)" checked class="sr-only peer">
                            <div class="w-11 h-6 bg-slate-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-cyan-500"></div>
                        </label>
                    </div>
                </div>

                <!-- Emergency Rain Alert Banner (Conditional) -->
                <div class="p-3 rounded-xl bg-gradient-to-r from-red-950/40 to-slate-900 border border-red-500/30 text-xs text-red-300 flex items-center gap-3">
                    <i class="fas fa-shield-virus text-red-400 text-lg"></i>
                    <div>
                        <span class="font-bold">Proteksi Air Otomatis Aktif:</span>
                        <p class="text-[11px] text-slate-400">Sensor air memiliki prioritas tertinggi untuk menutup atap instan.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Middle Row: Sensor Telemetry Cards (4 Grid Columns) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">

            <!-- Card 1: Rain Sensor -->
            <div id="rainCard" class="sensor-metric-card flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Sensor Air / Hujan</span>
                    <div class="w-8 h-8 rounded-lg bg-cyan-500/10 border border-cyan-500/20 flex items-center justify-center text-cyan-400">
                        <i class="fas fa-droplet"></i>
                    </div>
                </div>
                <div class="my-3">
                    <span id="rainSensorVal" class="px-3 py-1 text-xs font-bold rounded-full bg-emerald-500/20 text-emerald-400 border border-emerald-500/40 inline-flex items-center gap-1.5">
                        <span class="pulse-dot online"></span> KERING (AMAN)
                    </span>
                </div>
                <p class="text-[11px] text-slate-400">Deteksi tetesan hujan pada pelat sensor ESP32</p>
            </div>

            <!-- Card 2: Light Sensor & AI Light Classifier -->
            <div class="sensor-metric-card flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Sensor Cahaya & AI</span>
                    <div class="w-8 h-8 rounded-lg bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400">
                        <i class="fas fa-sun"></i>
                    </div>
                </div>
                <div class="my-2">
                    <div class="flex items-baseline justify-between mb-1">
                        <span id="lightPercentVal" class="text-2xl font-extrabold text-amber-400">85%</span>
                        <span id="lightVerdictBadge" class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-500/20 text-amber-300 border border-amber-500/30">
                            <i class="fas fa-sun"></i> SINAR MATAHARI
                        </span>
                    </div>
                    <div class="w-full bg-slate-800 rounded-full h-2 overflow-hidden">
                        <div id="lightProgressBar" class="bg-gradient-to-r from-amber-500 to-yellow-400 h-2 rounded-full transition-all duration-500" style="width: 85%"></div>
                    </div>
                </div>
                <p class="text-[11px] text-slate-400">AI membedakan matahari asli vs lampu bohlam</p>
            </div>

            <!-- Card 3: AI Weather & Drying Recommendation -->
            <div class="sensor-metric-card flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">AI Cuaca & Jemur</span>
                    <div class="w-8 h-8 rounded-lg bg-blue-500/10 border border-blue-500/20 flex items-center justify-center text-blue-400">
                        <i class="fas fa-wand-magic-sparkles"></i>
                    </div>
                </div>
                <div class="my-2">
                    <div class="flex items-center gap-2">
                        <span id="aiWeatherBadge" class="text-base font-extrabold text-cyan-300">CERAH</span>
                        <span class="text-[11px] text-slate-400">(Akurasi: <span id="aiConfidenceVal" class="text-slate-200">95.5%</span>)</span>
                    </div>
                    <p id="aiRecommendationText" class="text-[11px] text-slate-300 mt-1 line-clamp-2 leading-relaxed">
                        Cahaya matahari optimal. Rekomendasi jemur: 45 menit.
                    </p>
                </div>
                <div class="flex items-center justify-between text-[11px] text-cyan-400 font-semibold border-t border-slate-800/80 pt-1.5">
                    <span>Target Jemur:</span>
                    <span><span id="aiRecommendedMinutes">45</span> Menit</span>
                </div>
            </div>

            <!-- Card 4: Stopwatch Timer Quick Widget -->
            <div class="sensor-metric-card flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Stopwatch Jemur</span>
                    <div class="w-8 h-8 rounded-lg bg-violet-500/10 border border-violet-500/20 flex items-center justify-center text-violet-400">
                        <i class="fas fa-stopwatch"></i>
                    </div>
                </div>

                <!-- If Timer Active -->
                <div id="activeTimerWidget" style="display: none;" class="my-2 text-center">
                    <div id="timerDigitsDisplay" class="timer-digits text-2xl">00:00:00</div>
                    <p id="timerTargetEndTime" class="text-[10px] text-violet-300 mt-1">Selesai: --:--</p>
                    <button onclick="App.cancelTimer()" class="mt-2 px-3 py-1 text-xs font-semibold rounded-lg bg-rose-500/20 text-rose-300 border border-rose-500/30 hover:bg-rose-500/30 transition-all">
                        <i class="fas fa-times"></i> Batalkan Timer
                    </button>
                </div>

                <!-- If Timer Inactive (Set Presets) -->
                <div id="setTimerControls" class="my-2">
                    <div class="grid grid-cols-3 gap-1.5 mb-2">
                        <button onclick="App.startTimer(15)" class="px-2 py-1 text-xs font-bold rounded-lg bg-slate-800 hover:bg-violet-600/40 text-slate-200 border border-slate-700 transition-all">15m</button>
                        <button onclick="App.startTimer(30)" class="px-2 py-1 text-xs font-bold rounded-lg bg-slate-800 hover:bg-violet-600/40 text-slate-200 border border-slate-700 transition-all">30m</button>
                        <button onclick="App.startTimer(60)" class="px-2 py-1 text-xs font-bold rounded-lg bg-slate-800 hover:bg-violet-600/40 text-slate-200 border border-slate-700 transition-all">60m</button>
                    </div>
                    <div class="flex gap-1.5">
                        <input id="customTimerInput" type="number" placeholder="Menit..." min="1" max="720" value="45" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-2.5 py-1 text-xs text-slate-200 focus:outline-none focus:border-violet-500">
                        <button onclick="App.startTimer()" class="px-3 py-1 text-xs font-bold rounded-lg bg-violet-600 hover:bg-violet-500 text-white shadow transition-all">Mulai</button>
                    </div>
                </div>

                <p class="text-[11px] text-slate-400">Tutup atap otomatis saat timer habis</p>
            </div>
        </div>

        <!-- Bottom Row: Camera AI Station, Charts, and System Notifications -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

            <!-- Left: ESP32-CAM AI Vision Snapshot Station (4 Cols) -->
            <div class="lg:col-span-4 glass-panel p-5 flex flex-col justify-between space-y-4">
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-sm font-bold text-slate-300 flex items-center gap-2">
                            <i class="fas fa-camera text-cyan-400"></i> Kamera Modul ESP32-CAM
                        </h3>
                        <span id="geminiStatusBadge" class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-cyan-500/20 text-cyan-300 border border-cyan-500/40 inline-flex items-center gap-1">
                            <i class="fas fa-microchip text-cyan-400"></i> AI Vision Engine
                        </span>
                    </div>

                    <!-- Camera Feed Box -->
                    <div class="camera-feed-box mb-3">
                        <img id="latestCameraImage" src="" alt="Live Sky View" class="camera-feed-img" style="display: none;">
                        <div id="noPhotoPlaceholder" class="flex flex-col items-center justify-center p-6 text-slate-500 text-center">
                            <i class="fas fa-camera-viewfinder text-3xl mb-2 text-cyan-500/40"></i>
                            <p class="text-xs font-semibold text-slate-400">Belum Ada Tangkapan Foto ESP32</p>
                            <p class="text-[10px] text-slate-500 mt-0.5">Modul ESP32-CAM akan mengirimkan foto secara otomatis atau saat dipicu.</p>
                        </div>
                        <div class="camera-overlay-badge">
                            <span class="pulse-dot online"></span>
                            <span id="latestPhotoTime">Kamera Standby</span>
                        </div>
                    </div>

                    <!-- AI Verdict Summary -->
                    <div class="ai-verdict-banner mb-3">
                        <div class="flex items-center justify-between text-xs mb-1">
                            <span class="text-slate-400">Klasifikasi AI Terakhir:</span>
                            <span id="latestPhotoVerdict" class="font-bold text-cyan-300">MENUNGGU FOTO</span>
                        </div>
                        <p class="text-[11px] text-slate-400">AI memeriksa matahari vs lampu & awan mendung secara langsung.</p>
                    </div>
                </div>

                <!-- Camera Trigger Actions -->
                <div class="space-y-2">
                    <button onclick="App.triggerEsp32CamSnapshot()" class="w-full flex items-center justify-center gap-2 px-4 py-3 rounded-xl bg-gradient-to-r from-cyan-600 to-blue-600 hover:from-cyan-500 hover:to-blue-500 text-white text-xs font-bold shadow-lg shadow-cyan-500/25 transition-all">
                        <i class="fas fa-tower-broadcast text-base"></i> Minta ESP32-CAM Ambil Foto
                    </button>
                    
                    <div class="grid grid-cols-2 gap-2">
                        <button onclick="LiveCamera.open()" class="flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-[11px] font-semibold border border-slate-700 transition-all">
                            <i class="fas fa-camera text-xs text-cyan-400"></i> Kamera HP/Webcam
                        </button>
                        <label class="flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-[11px] font-semibold border border-slate-700 cursor-pointer transition-all">
                            <i class="fas fa-folder-open text-xs"></i> Unggah Foto
                            <input type="file" accept="image/*" class="hidden" onchange="App.uploadUserPhoto(this)">
                        </label>
                    </div>
                </div>
            </div>

            <!-- Middle: Live Sensor Telemetry Chart (5 Cols) -->
            <div class="lg:col-span-5 glass-panel p-5 flex flex-col justify-between">
                <div class="flex items-center justify-between mb-2">
                    <div>
                        <h3 class="text-sm font-bold text-slate-300 flex items-center gap-2">
                            <i class="fas fa-chart-line text-cyan-400"></i> Grafik Riwayat Telemetri Sensor
                        </h3>
                        <p class="text-[11px] text-slate-400">Data cahaya matahari & deteksi air real-time</p>
                    </div>
                    <span class="text-[10px] text-slate-500 bg-slate-900 px-2.5 py-1 rounded-lg border border-slate-800">
                        Update per 2 detik
                    </span>
                </div>

                <!-- Canvas Chart -->
                <div class="w-full h-[260px] relative my-2">
                    <canvas id="sensorChart"></canvas>
                </div>

                <div class="flex items-center justify-between text-[11px] text-slate-400 pt-2 border-t border-slate-800">
                    <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-amber-500 inline-block"></span> Intensitas Cahaya (%)</span>
                    <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-cyan-400 inline-block"></span> Sensor Air Hujan (0/1)</span>
                </div>
            </div>

            <!-- Right: System Alerts & Notification Feed (3 Cols) -->
            <div class="lg:col-span-3 glass-panel p-5 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-sm font-bold text-slate-300 flex items-center gap-2">
                            <i class="fas fa-bell text-cyan-400"></i> Notifikasi Sistem
                        </h3>
                        <span id="alertsCountBadge" class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-cyan-500/20 text-cyan-300 border border-cyan-500/30">0</span>
                    </div>

                    <!-- Alerts Feed List -->
                    <div id="alertsListContainer" class="space-y-2 max-h-[260px] overflow-y-auto pr-1">
                        <div class="text-center py-6 text-slate-500 text-xs">
                            <i class="fas fa-spinner fa-spin mb-2"></i>
                            <p>Memuat notifikasi...</p>
                        </div>
                    </div>
                </div>

                <button onclick="App.clearAlerts()" class="w-full mt-3 py-1.5 rounded-lg text-xs font-semibold text-slate-400 hover:text-slate-200 bg-slate-900/60 hover:bg-slate-800 border border-slate-800 transition-all">
                    <i class="fas fa-trash-can text-[10px]"></i> Bersihkan Notifikasi
                </button>
            </div>
        </div>

    </main>

    <!-- Direct Live Camera Viewfinder Modal -->
    <div id="liveCameraModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/85 backdrop-blur-md p-3 sm:p-4">
        <div class="glass-panel w-full max-w-lg p-5 bg-slate-900/95 border-cyan-500/40 shadow-2xl relative flex flex-col justify-between max-h-[90vh]">
            <!-- Modal Header -->
            <div class="flex items-center justify-between pb-3 border-b border-slate-800 mb-3">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-cyan-600 flex items-center justify-center text-white shadow-lg shadow-cyan-500/30">
                        <i class="fas fa-camera"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-slate-100">Kamera Cuaca & Awan Langsung</h3>
                        <p id="cameraStreamStatus" class="text-[11px] text-cyan-300 flex items-center gap-1">Mengakses sensor kamera...</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button onclick="LiveCamera.switchFacing()" title="Ganti Kamera Depan/Belakang" class="w-8 h-8 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white flex items-center justify-center transition-all">
                        <i class="fas fa-camera-rotate text-xs"></i>
                    </button>
                    <button onclick="LiveCamera.close()" class="w-8 h-8 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white flex items-center justify-center transition-all">
                        <i class="fas fa-times text-xs"></i>
                    </button>
                </div>
            </div>

            <!-- Live Viewfinder Box -->
            <div class="viewfinder-container my-2 relative">
                <video id="webcamVideo" autoplay playsinline class="viewfinder-video"></video>
                
                <!-- Rule of thirds grid & corner brackets -->
                <div class="viewfinder-grid-overlay"></div>
                <div class="viewfinder-corner-tl"></div>
                <div class="viewfinder-corner-tr"></div>
                <div class="viewfinder-corner-bl"></div>
                <div class="viewfinder-corner-br"></div>
                
                <!-- Flash visual effect -->
                <div id="viewfinderFlash" class="absolute inset-0 bg-white pointer-events-none opacity-0 transition-opacity duration-150"></div>
            </div>

            <!-- Hidden Canvas for frame snapshot -->
            <canvas id="webcamCanvas" class="hidden"></canvas>

            <!-- Camera Shutter Control Footer -->
            <div class="pt-3 border-t border-slate-800 flex flex-col items-center gap-2">
                <div class="flex items-center justify-center gap-4 w-full">
                    <button onclick="LiveCamera.snapPhoto()" class="shutter-btn" title="Ambil Foto Cuaca Sekarang">
                        <div class="shutter-btn-inner">
                            <i class="fas fa-camera"></i>
                        </div>
                    </button>
                </div>
                <p class="text-[11px] text-slate-400 text-center">
                    Arahkan kamera ke langit/awan, lalu tekan tombol untuk analisis AI seketika
                </p>
            </div>
        </div>
    </div>

    <!-- AI Settings Modal -->
    <div id="settingsModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
        <div class="glass-panel w-full max-w-md p-6 bg-slate-900/95 border-cyan-500/40 shadow-2xl relative">
            <div class="flex items-center justify-between pb-3 border-b border-slate-800 mb-4">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-cyan-600 flex items-center justify-center text-white shadow-lg shadow-cyan-500/30">
                        <i class="fas fa-gear"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-slate-100">Pengaturan AI Vision Engine</h3>
                        <p class="text-[11px] text-slate-400">Konfigurasi Google Gemini Vision API</p>
                    </div>
                </div>
                <button onclick="document.getElementById('settingsModal').classList.add('hidden')" class="w-7 h-7 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white flex items-center justify-center transition-all">
                    <i class="fas fa-times text-xs"></i>
                </button>
            </div>

            <div class="space-y-4 text-xs">
                <div>
                    <label class="font-bold text-slate-200 block mb-1.5">Google Gemini API Key (Opsional):</label>
                    <input type="password" id="geminiApiKeyInput" placeholder="Masukkan Google AI Studio API Key..." class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3.5 py-2.5 text-xs text-slate-200 focus:outline-none focus:border-cyan-500">
                    <p class="text-[10px] text-slate-400 mt-1 leading-relaxed">
                        Jika diisi, analisis foto ESP32-CAM menggunakan <strong>Google Gemini Vision AI</strong>. Jika dikosongkan, sistem menggunakan <strong>AI Vision Engine Lokal</strong>.
                    </p>
                </div>
            </div>

            <div class="mt-5 pt-3 border-t border-slate-800 flex items-center justify-end gap-2">
                <button onclick="document.getElementById('settingsModal').classList.add('hidden')" class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-xs font-semibold text-slate-300 transition-all">
                    Batal
                </button>
                <button onclick="App.saveGeminiKey()" class="px-4 py-2 rounded-xl bg-cyan-600 hover:bg-cyan-500 text-xs font-bold text-white shadow transition-all">
                    Simpan Pengaturan
                </button>
            </div>
        </div>
    </div>

    <!-- Mobile Bottom Sticky Navigation Bar -->
    <div class="sm:hidden mobile-bottom-nav">
        <a href="index.php" class="mobile-nav-item active">
            <i class="fas fa-gauge-high"></i>
            <span>Dashboard</span>
        </a>
        <button onclick="LiveCamera.open()" class="mobile-snap-btn-center" title="Foto Langsung">
            <i class="fas fa-camera"></i>
        </button>
        <a href="history.php" class="mobile-nav-item">
            <i class="fas fa-images"></i>
            <span>Riwayat AI</span>
        </a>
        <button onclick="document.getElementById('settingsModal').classList.remove('hidden')" class="mobile-nav-item">
            <i class="fas fa-gear"></i>
            <span>Pengaturan</span>
        </button>
    </div>

    <!-- Live Toast Notification Container -->
    <div id="liveToast" class="fixed bottom-6 right-6 z-50 flex items-center gap-3 px-5 py-3.5 rounded-2xl border backdrop-blur-xl shadow-2xl transition-all duration-300 transform translate-y-8 opacity-0 pointer-events-none border-cyan-500/40 bg-slate-900/90 text-cyan-100">
        <i id="liveToastIcon" class="fas fa-info-circle text-lg text-cyan-400"></i>
        <span id="liveToastMsg" class="text-xs font-semibold">Notifikasi</span>
    </div>

    <!-- Scripts -->
    <script src="assets/js/charts.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>
