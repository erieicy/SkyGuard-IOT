/**
 * SkyGuard AI - Main Frontend Application Logic
 */

let appState = {};
let pollTimer = null;
let countdownInterval = null;
let lastAlertCount = 0;
let isAudioEnabled = true;

// Synthesized Audio Tones via Web Audio API (No external sound files required)
const AudioAlerts = {
    audioCtx: null,
    init() {
        if (!this.audioCtx) {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (AudioContext) this.audioCtx = new AudioContext();
        }
    },
    playBeep(freq = 880, duration = 0.15, type = 'sine') {
        if (!isAudioEnabled) return;
        try {
            this.init();
            if (!this.audioCtx) return;
            const osc = this.audioCtx.createOscillator();
            const gain = this.audioCtx.createGain();
            osc.type = type;
            osc.frequency.value = freq;
            gain.gain.setValueAtTime(0.2, this.audioCtx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, this.audioCtx.currentTime + duration);
            osc.connect(gain);
            gain.connect(this.audioCtx.destination);
            osc.start();
            osc.stop(this.audioCtx.currentTime + duration);
        } catch (e) {
            console.log('Audio error:', e);
        }
    },
    playEmergencyRain() {
        this.playBeep(950, 0.2, 'sawtooth');
        setTimeout(() => this.playBeep(750, 0.2, 'sawtooth'), 220);
        setTimeout(() => this.playBeep(950, 0.3, 'sawtooth'), 450);
    },
    playSuccessTone() {
        this.playBeep(523.25, 0.1, 'sine'); // C5
        setTimeout(() => this.playBeep(659.25, 0.1, 'sine'), 120); // E5
        setTimeout(() => this.playBeep(783.99, 0.2, 'sine'), 240); // G5
    }
};

const App = {
    init() {
        console.log('Initializing SkyGuard AI Dashboard...');
        initTelemetryChart();
        this.fetchStatus();
        
        // Start live polling loop every 1.8 seconds
        pollTimer = setInterval(() => this.fetchStatus(), 1800);

        // Setup user audio interaction trigger
        document.addEventListener('click', () => {
            if (AudioAlerts.audioCtx && AudioAlerts.audioCtx.state === 'suspended') {
                AudioAlerts.audioCtx.resume();
            }
        }, { once: true });
    },

    fetchStatus() {
        fetch('api/status.php')
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    appState = data.state;
                    this.renderState(data);
                }
            })
            .catch(err => {
                console.error('Fetch status error:', err);
                this.updateHardwareStatus(false);
            });
    },

    renderState(data) {
        const s = data.state;
        const roofBox = document.getElementById('roofVisualizerBox');
        const roofStructure = document.getElementById('roofStructure');
        const roofBadge = document.getElementById('roofStatusBadge');
        const sunOrb = document.getElementById('sunOrb');
        const rainAnim = document.getElementById('rainAnimationContainer');

        // 1. Update Roof Status Visualization
        if (s.roof_status === 'OPEN') {
            roofStructure.className = 'clothesline-structure roof-state-open';
            roofBadge.className = 'badge-status badge-status-open';
            roofBadge.innerHTML = '<i class="fas fa-umbrella"></i> ATAP TERBUKA (MENJEMUR)';
            document.getElementById('btnOpenRoof').classList.add('opacity-50', 'pointer-events-none');
            document.getElementById('btnCloseRoof').classList.remove('opacity-50', 'pointer-events-none');
        } else {
            roofStructure.className = 'clothesline-structure roof-state-closed';
            roofBadge.className = 'badge-status badge-status-closed';
            roofBadge.innerHTML = '<i class="fas fa-shield-alt"></i> ATAP TERTUTUP RAPAT (AMAN)';
            document.getElementById('btnOpenRoof').classList.remove('opacity-50', 'pointer-events-none');
            document.getElementById('btnCloseRoof').classList.add('opacity-50', 'pointer-events-none');
        }

        // 2. Update Sky Weather Background in Visualizer
        let skyClass = 'roof-visualizer-box';
        if (s.rain_detected == 1) {
            skyClass += ' sky-rainy';
            sunOrb.style.opacity = '0';
            rainAnim.style.display = 'block';
            this.generateRaindrops(30);
        } else if (s.ai_weather_verdict === 'MENDUNG') {
            skyClass += ' sky-cloudy';
            sunOrb.style.opacity = '0.2';
            rainAnim.style.display = 'none';
        } else if (s.ai_light_verdict === 'ARTIFICIAL_LAMP') {
            skyClass += ' sky-lamp';
            sunOrb.style.opacity = '0';
            rainAnim.style.display = 'none';
        } else if (s.ai_weather_verdict === 'MALAM') {
            skyClass += ' sky-night';
            sunOrb.style.opacity = '0';
            rainAnim.style.display = 'none';
        } else {
            skyClass += ' sky-sunny';
            sunOrb.style.opacity = '1';
            rainAnim.style.display = 'none';
        }
        roofBox.className = skyClass;

        // 3. Update Mode Pills
        document.querySelectorAll('.mode-pill-btn').forEach(btn => {
            btn.classList.remove('active-auto', 'active-manual', 'active-timer');
        });
        if (s.control_mode === 'AUTO') {
            document.getElementById('btnModeAuto')?.classList.add('active-auto');
        } else if (s.control_mode === 'MANUAL') {
            document.getElementById('btnModeManual')?.classList.add('active-manual');
        } else if (s.control_mode === 'TIMER') {
            document.getElementById('btnModeTimer')?.classList.add('active-timer');
        }

        // 4. Update Sensors Telemetry
        // Rain Sensor Indicator
        const rainValBadge = document.getElementById('rainSensorVal');
        if (s.rain_detected == 1) {
            rainValBadge.className = 'px-3 py-1 text-xs font-bold rounded-full bg-red-500/20 text-red-400 border border-red-500/40 inline-flex items-center gap-1.5';
            rainValBadge.innerHTML = '<span class="pulse-dot offline"></span> AIR / HUJAN TERDETEKSI!';
            document.getElementById('rainCard').classList.add('border-red-500/50', 'glow-rose');
        } else {
            rainValBadge.className = 'px-3 py-1 text-xs font-bold rounded-full bg-emerald-500/20 text-emerald-400 border border-emerald-500/40 inline-flex items-center gap-1.5';
            rainValBadge.innerHTML = '<span class="pulse-dot online"></span> KERING (AMAN)';
            document.getElementById('rainCard').classList.remove('border-red-500/50', 'glow-rose');
        }

        // Light Sensor Meter
        document.getElementById('lightPercentVal').innerText = s.light_level + '%';
        document.getElementById('lightProgressBar').style.width = s.light_level + '%';
        
        const lightVerdictBadge = document.getElementById('lightVerdictBadge');
        if (s.ai_light_verdict === 'SUNLIGHT') {
            lightVerdictBadge.className = 'px-2.5 py-0.5 rounded text-xs font-bold bg-amber-500/20 text-amber-300 border border-amber-500/30';
            lightVerdictBadge.innerHTML = '<i class="fas fa-sun text-amber-400"></i> SINAR MATAHARI ALAMI';
        } else if (s.ai_light_verdict === 'ARTIFICIAL_LAMP') {
            lightVerdictBadge.className = 'px-2.5 py-0.5 rounded text-xs font-bold bg-orange-500/20 text-orange-300 border border-orange-500/30';
            lightVerdictBadge.innerHTML = '<i class="fas fa-lightbulb text-yellow-400"></i> LAMPU RUANGAN (BUATAN)';
        } else {
            lightVerdictBadge.className = 'px-2.5 py-0.5 rounded text-xs font-bold bg-slate-500/20 text-slate-300 border border-slate-500/30';
            lightVerdictBadge.innerHTML = '<i class="fas fa-moon text-slate-400"></i> GELAP / REDUP';
        }

        // 5. Update Hardware Status (ESP32)
        this.updateHardwareStatus(s.esp32_online, s.esp32_last_seen);

        // 6. AI Vision & Recommendation Card
        document.getElementById('aiWeatherBadge').innerText = s.ai_weather_verdict;
        document.getElementById('aiConfidenceVal').innerText = (s.ai_confidence || 95).toFixed(1) + '%';
        document.getElementById('aiRecommendationText').innerText = s.ai_drying_recommendation || 'Data cuaca optimal.';
        document.getElementById('aiRecommendedMinutes').innerText = s.recommended_minutes || 45;
        document.getElementById('lastActionReason').innerText = s.last_action_reason || '-';
        document.getElementById('serverTimeDisplay').innerText = data.server_time || '';

        // Auto Close on Mendung Switch
        const chkMendung = document.getElementById('chkMendungAutoClose');
        if (chkMendung) chkMendung.checked = (s.auto_close_on_mendung == 1);

        // 7. Stopwatch & Timer Countdown Logic
        this.updateTimerDisplay(s);

        // 8. Latest Camera Photo
        if (data.latest_photo && data.latest_photo.image_path) {
            const photoEl = document.getElementById('latestCameraImage');
            const placeholderEl = document.getElementById('noPhotoPlaceholder');
            if (photoEl) {
                photoEl.src = data.latest_photo.image_path;
                photoEl.style.display = 'block';
            }
            if (placeholderEl) placeholderEl.style.display = 'none';
            document.getElementById('latestPhotoTime').innerText = data.latest_photo.timestamp;
            document.getElementById('latestPhotoVerdict').innerText = data.latest_photo.ai_classification;
        } else {
            const photoEl = document.getElementById('latestCameraImage');
            const placeholderEl = document.getElementById('noPhotoPlaceholder');
            if (photoEl) photoEl.style.display = 'none';
            if (placeholderEl) placeholderEl.style.display = 'flex';
            document.getElementById('latestPhotoTime').innerText = 'Belum Ada Foto';
            document.getElementById('latestPhotoVerdict').innerText = 'SIAP AMBIL FOTO LANGSUNG';
        }

        // 9. Telemetry Charts
        if (data.recent_logs && data.recent_logs.length > 0) {
            updateTelemetryChart(data.recent_logs);
        }

        // 10. Alerts
        this.renderAlerts(data.alerts || []);
    },

    generateRaindrops(count = 25) {
        const container = document.getElementById('rainAnimationContainer');
        if (!container || container.childElementCount >= count) return;
        container.innerHTML = '';
        for (let i = 0; i < count; i++) {
            const drop = document.createElement('div');
            drop.className = 'raindrop';
            drop.style.left = Math.random() * 100 + '%';
            drop.style.animationDuration = (0.5 + Math.random() * 0.5) + 's';
            drop.style.animationDelay = (Math.random() * 1) + 's';
            container.appendChild(drop);
        }
    },

    updateHardwareStatus(isOnline, lastSeen) {
        const dot = document.getElementById('espStatusDot');
        const text = document.getElementById('espStatusText');
        if (isOnline) {
            dot.className = 'pulse-dot online';
            text.className = 'text-xs font-semibold text-emerald-400';
            text.innerText = 'ESP32 ONLINE (TERHUBUNG)';
        } else {
            dot.className = 'pulse-dot offline';
            text.className = 'text-xs font-semibold text-rose-400';
            text.innerText = 'ESP32 OFFLINE / STANDBY';
        }
    },

    updateTimerDisplay(s) {
        const timerContainer = document.getElementById('activeTimerWidget');
        const setTimerContainer = document.getElementById('setTimerControls');
        const digitsEl = document.getElementById('timerDigitsDisplay');

        if (s.timer_active == 1 && s.timer_remaining_seconds > 0) {
            timerContainer.style.display = 'block';
            setTimerContainer.style.display = 'none';

            const remaining = s.timer_remaining_seconds;
            const hours = Math.floor(remaining / 3600);
            const minutes = Math.floor((remaining % 3600) / 60);
            const seconds = remaining % 60;

            digitsEl.innerText = 
                String(hours).padStart(2, '0') + ':' +
                String(minutes).padStart(2, '0') + ':' +
                String(seconds).padStart(2, '0');
            
            document.getElementById('timerTargetEndTime').innerText = 'Selesai pada: ' + (s.timer_end_time || '-');
        } else {
            timerContainer.style.display = 'none';
            setTimerContainer.style.display = 'block';
            digitsEl.innerText = '00:00:00';
        }
    },

    renderAlerts(alerts) {
        const listEl = document.getElementById('alertsListContainer');
        const badgeEl = document.getElementById('alertsCountBadge');
        if (!listEl) return;

        badgeEl.innerText = alerts.length;

        if (alerts.length === 0) {
            listEl.innerHTML = `
                <div class="text-center py-6 text-slate-500 text-sm">
                    <i class="fas fa-check-circle text-emerald-500/50 text-2xl mb-2"></i>
                    <p>Semua sistem normal. Tidak ada peringatan aktif.</p>
                </div>
            `;
            return;
        }

        // Sound trigger for new alert
        if (alerts.length > lastAlertCount) {
            if (alerts[0].alert_type === 'RAIN_DETECTED') {
                AudioAlerts.playEmergencyRain();
            } else {
                AudioAlerts.playBeep(600, 0.15);
            }
        }
        lastAlertCount = alerts.length;

        let html = '';
        alerts.slice(0, 5).forEach(a => {
            let icon = 'fa-info-circle text-cyan-400';
            let bgBorder = 'border-cyan-500/20 bg-cyan-500/5';
            if (a.severity === 'danger') {
                icon = 'fa-cloud-showers-heavy text-rose-400';
                bgBorder = 'border-rose-500/30 bg-rose-500/10 glow-rose';
            } else if (a.severity === 'warning') {
                icon = 'fa-cloud-meatball text-amber-400';
                bgBorder = 'border-amber-500/30 bg-amber-500/10 glow-amber';
            } else if (a.severity === 'success') {
                icon = 'fa-check-circle text-emerald-400';
                bgBorder = 'border-emerald-500/30 bg-emerald-500/10';
            }

            html += `
                <div class="p-3.5 rounded-xl border ${bgBorder} mb-2.5 transition-all">
                    <div class="flex items-start gap-3">
                        <i class="fas ${icon} mt-1 text-lg"></i>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-2">
                                <h5 class="text-xs font-bold text-slate-200 truncate">${a.title}</h5>
                                <span class="text-[10px] text-slate-400">${a.timestamp.split(' ')[1] || a.timestamp}</span>
                            </div>
                            <p class="text-xs text-slate-400 mt-0.5 leading-relaxed">${a.message}</p>
                        </div>
                    </div>
                </div>
            `;
        });

        listEl.innerHTML = html;
    },

    // ==========================================
    // ACTION CONTROLS
    // ==========================================
    setRoof(targetStatus) {
        showToast(`Mengirim perintah: ${targetStatus === 'OPEN' ? 'Buka Atap' : 'Tutup Atap'}...`, 'info');
        
        fetch('api/control.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'set_roof', status: targetStatus })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                AudioAlerts.playSuccessTone();
                showToast(data.message, 'success');
                this.fetchStatus();
            } else {
                showToast(data.error || 'Gagal mengubah status atap', 'danger');
            }
        })
        .catch(err => console.error('Control error:', err));
    },

    setMode(mode) {
        showToast(`Mengubah mode ke: ${mode}...`, 'info');

        fetch('api/control.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'set_mode', mode: mode })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast(data.message, 'success');
                this.fetchStatus();
            } else {
                showToast(data.error || 'Gagal mengubah mode', 'danger');
            }
        })
        .catch(err => console.error('Mode error:', err));
    },

    startTimer(minutes) {
        if (!minutes || minutes <= 0) {
            minutes = parseInt(document.getElementById('customTimerInput')?.value || 30);
        }

        showToast(`Mengaktifkan stopwatch timer selama ${minutes} menit...`, 'info');

        fetch('api/control.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'set_timer', minutes: minutes })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                AudioAlerts.playSuccessTone();
                showToast(data.message, 'success');
                this.fetchStatus();
            } else {
                showToast(data.error || 'Gagal mengatur timer', 'danger');
            }
        })
        .catch(err => console.error('Timer error:', err));
    },

    cancelTimer() {
        if (!confirm('Batalkan timer stopwatch yang sedang berjalan?')) return;

        fetch('api/control.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'cancel_timer' })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast('Timer berhasil dibatalkan', 'info');
                this.fetchStatus();
            }
        })
        .catch(err => console.error('Cancel timer error:', err));
    },

    toggleMendungAutoClose(checked) {
        fetch('api/control.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'toggle_mendung_autoclose', enabled: checked ? 1 : 0 })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast(data.message, 'success');
            }
        })
        .catch(err => console.error('Mendung toggle error:', err));
    },

    uploadUserPhoto(fileInput) {
        if (!fileInput.files || fileInput.files.length === 0) return;
        const file = fileInput.files[0];

        showToast('📸 Mengunggah foto awan & menganalisis dengan AI Vision...', 'info');

        const formData = new FormData();
        formData.append('image', file);
        formData.append('source', 'user_upload');

        fetch('api/ai_analyze.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const a = data.analysis;
                let toastType = 'success';
                if (a.weather === 'MENDUNG') toastType = 'warning';
                if (a.weather === 'HUJAN') toastType = 'danger';

                showToast(`AI Verdict: ${a.weather} (${a.light_verdict}) - Keyakinan: ${a.confidence}%`, toastType);
                this.fetchStatus();
            } else {
                showToast(data.error || 'Gagal menganalisis foto', 'danger');
            }
        })
        .catch(err => console.error('Upload error:', err));
    },

    clearAlerts() {
        fetch('api/alerts.php?action=clear_all', { method: 'POST' })
            .then(res => res.json())
            .then(data => {
                showToast('Semua notifikasi berhasil dibersihkan', 'info');
                this.fetchStatus();
            })
            .catch(err => console.error(err));
    }
};

// ==========================================
// Direct Live Camera Viewfinder & Shutter (Webcam / Mobile Camera)
// ==========================================
const LiveCamera = {
    stream: null,
    facingMode: 'environment', // Default to back camera for sky/clouds on smartphones

    open() {
        const modal = document.getElementById('liveCameraModal');
        if (!modal) return;
        modal.classList.remove('hidden');
        this.startStream();
    },

    startStream() {
        const video = document.getElementById('webcamVideo');
        const statusEl = document.getElementById('cameraStreamStatus');
        if (!video) return;

        if (this.stream) {
            this.stream.getTracks().forEach(t => t.stop());
        }

        if (statusEl) statusEl.innerText = 'Mengakses sensor kamera...';

        const constraints = {
            video: {
                facingMode: { ideal: this.facingMode },
                width: { ideal: 1280 },
                height: { ideal: 720 }
            },
            audio: false
        };

        if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
            navigator.mediaDevices.getUserMedia(constraints)
                .then(mediaStream => {
                    this.stream = mediaStream;
                    video.srcObject = mediaStream;
                    video.play();
                    if (statusEl) {
                        const modeLabel = this.facingMode === 'environment' ? 'Kamera Belakang (Arah Langit)' : 'Kamera Depan';
                        statusEl.innerHTML = `<span class="pulse-dot online mr-1.5"></span> Kamera Aktif: ${modeLabel}`;
                    }
                })
                .catch(err => {
                    console.error('Camera access error:', err);
                    if (statusEl) statusEl.innerText = 'Gagal akses kamera: ' + err.message;
                    showToast('Izin kamera ditolak atau tidak didukung pada browser ini.', 'danger');
                });
        } else {
            if (statusEl) statusEl.innerText = 'Browser tidak mendukung akses kamera langsung.';
            showToast('Browser Anda tidak mendukung WebRTC Camera.', 'danger');
        }
    },

    switchFacing() {
        this.facingMode = (this.facingMode === 'environment') ? 'user' : 'environment';
        this.startStream();
    },

    snapPhoto() {
        const video = document.getElementById('webcamVideo');
        const canvas = document.getElementById('webcamCanvas');
        if (!video || !canvas) return;

        // Visual flash effect on viewfinder
        const flashOverlay = document.getElementById('viewfinderFlash');
        if (flashOverlay) {
            flashOverlay.style.opacity = '1';
            setTimeout(() => flashOverlay.style.opacity = '0', 150);
        }

        // Play shutter sound
        AudioAlerts.playBeep(1200, 0.08, 'square');

        // Draw frame to canvas
        canvas.width = video.videoWidth || 640;
        canvas.height = video.videoHeight || 480;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

        // Convert to base64
        const base64Image = canvas.toDataURL('image/jpeg', 0.88);

        showToast('📸 Foto langsung berhasil diambil! Mengirim ke AI Vision...', 'info');

        // Send to AI analyze endpoint
        const formData = new FormData();
        formData.append('image_base64', base64Image);
        formData.append('source', 'live_direct_camera');

        fetch('api/ai_analyze.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const a = data.analysis;
                let toastType = 'success';
                if (a.weather === 'MENDUNG') toastType = 'warning';
                if (a.weather === 'HUJAN') toastType = 'danger';

                showToast(`Hasil AI: ${a.weather} (${a.light_verdict}) - Keyakinan: ${a.confidence}%`, toastType);
                App.fetchStatus();
                this.close();
            } else {
                showToast(data.error || 'Gagal analisis AI pada foto', 'danger');
            }
        })
        .catch(err => {
            console.error('AI upload error:', err);
            showToast('Gagal mengirim foto ke server', 'danger');
        });
    },

    close() {
        if (this.stream) {
            this.stream.getTracks().forEach(t => t.stop());
            this.stream = null;
        }
        const modal = document.getElementById('liveCameraModal');
        if (modal) modal.classList.add('hidden');
    }
};

// Toast notification helper
function showToast(message, type = 'info') {
    const toast = document.getElementById('liveToast');
    const toastMsg = document.getElementById('liveToastMsg');
    const toastIcon = document.getElementById('liveToastIcon');
    if (!toast || !toastMsg) return;

    let iconClass = 'fa-info-circle text-cyan-400';
    let borderColor = 'border-cyan-500/40 bg-slate-900/90 text-cyan-100';

    if (type === 'success') {
        iconClass = 'fa-check-circle text-emerald-400';
        borderColor = 'border-emerald-500/40 bg-slate-900/90 text-emerald-100';
    } else if (type === 'danger') {
        iconClass = 'fa-exclamation-triangle text-rose-400';
        borderColor = 'border-rose-500/40 bg-slate-900/90 text-rose-100';
    } else if (type === 'warning') {
        iconClass = 'fa-cloud-meatball text-amber-400';
        borderColor = 'border-amber-500/40 bg-slate-900/90 text-amber-100';
    }

    toastIcon.className = `fas ${iconClass} text-lg`;
    toast.className = `fixed bottom-6 right-6 z-50 flex items-center gap-3 px-5 py-3.5 rounded-2xl border backdrop-blur-xl shadow-2xl transition-all duration-300 transform translate-y-0 opacity-100 ${borderColor}`;
    toastMsg.innerText = message;

    clearTimeout(toast._hideTimeout);
    toast._hideTimeout = setTimeout(() => {
        toast.className += ' translate-y-8 opacity-0 pointer-events-none';
    }, 4000);
}

// Auto start when DOM is ready
document.addEventListener('DOMContentLoaded', () => App.init());

