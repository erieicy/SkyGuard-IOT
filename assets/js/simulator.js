/**
 * SkyGuard AI - Interactive IoT Hardware Simulator Logic
 */

const Simulator = {
    simulateRain(active) {
        showToast(active ? '💧 Mengaktifkan simulasi air hujan pada sensor...' : '☀️ Sensor air dikeringkan...', 'info');
        
        fetch('api/simulate.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'simulate_rain', rain: active ? 1 : 0 })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                if (active) {
                    showToast('⚠️ AIR TERDETEKSI! Proteksi darurat menutup atap jemuran.', 'danger');
                } else {
                    showToast('Sensor air kembali kering.', 'success');
                }
                App.fetchStatus();
            }
        })
        .catch(err => console.error('Sim error:', err));
    },

    simulateLight(value) {
        document.getElementById('simLightVal').innerText = value + '%';

        fetch('api/simulate.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'simulate_light', light: parseInt(value) })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                App.fetchStatus();
            }
        })
        .catch(err => console.error('Sim error:', err));
    },

    simulateAIPreset(preset) {
        showToast(`🤖 Menjalankan simulasi AI Vision: [${preset.toUpperCase()}]...`, 'info');
        
        const formData = new FormData();
        formData.append('action', 'simulate_camera_preset');
        formData.append('preset', preset);
        formData.append('source', 'simulation');

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
            } else {
                showToast(data.error || 'Gagal analisis simulasi', 'danger');
            }
        })
        .catch(err => console.error('Sim error:', err));
    },

    reset() {
        if (!confirm('Kembalikan status simulasi ke kondisi awal?')) return;
        
        fetch('api/simulate.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'reset_simulation' })
        })
        .then(res => res.json())
        .then(data => {
            showToast('Simulasi berhasil direset!', 'success');
            App.fetchStatus();
        })
        .catch(err => console.error('Sim error:', err));
    }
};
