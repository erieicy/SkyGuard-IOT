/**
 * SkyGuard AI - Hardware Simulator (Browser-based, no ESP32 required)
 * Mengirim perintah simulasi ke api/simulate.php lalu me-refresh dashboard.
 */
const Simulator = {
    init() {
        // Isi alamat server ke field firmware agar user tahu IP yang dipakai
        this.renderServerHost();
        // Sinkronkan toggle hujan dengan state
        const rainChk = document.getElementById('simRainChk');
        if (rainChk) {
            rainChk.addEventListener('change', () => this.sendSensor());
        }
        const lightSlider = document.getElementById('simLightSlider');
        if (lightSlider) {
            lightSlider.addEventListener('input', () => {
                const lbl = document.getElementById('simLightVal');
                if (lbl) lbl.innerText = lightSlider.value + '%';
            });
            lightSlider.addEventListener('change', () => this.sendSensor());
        }
    },

    open() {
        const modal = document.getElementById('simulatorModal');
        if (modal) modal.classList.remove('hidden');
        this.renderServerHost();
        this.syncFromState();
    },

    close() {
        const modal = document.getElementById('simulatorModal');
        if (modal) modal.classList.add('hidden');
    },

    renderServerHost() {
        const span = document.getElementById('simServerHost');
        if (!span) return;
        fetch('api/settings.php?action=get_settings')
            .then(r => r.json())
            .then(d => {
                if (d.success && d.settings && d.settings.server_host) {
                    span.innerText = d.settings.server_host;
                    const base = document.getElementById('simServerHostApi');
                    if (base) base.innerText = d.settings.server_host + '/api/esp32.php';
                }
            })
            .catch(() => {});
    },

    syncFromState() {
        const rainChk = document.getElementById('simRainChk');
        const lightSlider = document.getElementById('simLightSlider');
        const lightVal = document.getElementById('simLightVal');
        if (window.appState) {
            if (rainChk) rainChk.checked = (window.appState.rain_detected == 1);
            if (lightSlider && typeof window.appState.light_level === 'number') {
                lightSlider.value = window.appState.light_level;
                if (lightVal) lightVal.innerText = window.appState.light_level + '%';
            }
        }
    },

    sendSensor() {
        const rainChk = document.getElementById('simRainChk');
        const lightSlider = document.getElementById('simLightSlider');
        const rain = rainChk && rainChk.checked ? 1 : 0;
        const light = lightSlider ? parseInt(lightSlider.value) : 0;
        this.call({ action: 'sim_sensor', rain, light });
    },

    weather(preset) {
        this.call({ action: 'sim_weather', preset });
        // update toggle hujan sesuai preset
        const rainChk = document.getElementById('simRainChk');
        if (rainChk) rainChk.checked = (preset === 'HUJAN');
        this.syncFromState();
    },

    timer() {
        const inp = document.getElementById('simTimerInput');
        const minutes = inp ? parseInt(inp.value) : 45;
        this.call({ action: 'sim_timer', minutes });
    },

    reset() {
        this.call({ action: 'reset' });
        this.syncFromState();
    },

    call(payload) {
        fetch('api/simulate.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                showToast('Simulasi: ' + (payload.preset ? payload.preset : (payload.action === 'sim_sensor' ? 'sensor' : payload.action)) + ' diterapkan', 'success');
                if (window.App) App.fetchStatus();
            } else {
                showToast(d.error || 'Gagal simulasi', 'danger');
            }
        })
        .catch(err => {
            console.error(err);
            showToast('Gagal komunikasi simulator', 'danger');
        });
    }
};

document.addEventListener('DOMContentLoaded', () => Simulator.init());
