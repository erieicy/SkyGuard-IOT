/**
 * SkyGuard AI - Telemetry Charts Manager (Chart.js)
 */

let telemetryChart = null;

function initTelemetryChart() {
    const ctx = document.getElementById('sensorChart');
    if (!ctx) return;

    const chartCtx = ctx.getContext('2d');

    // Create Gradients
    const lightGradient = chartCtx.createLinearGradient(0, 0, 0, 250);
    lightGradient.addColorStop(0, 'rgba(245, 158, 11, 0.4)');
    lightGradient.addColorStop(1, 'rgba(245, 158, 11, 0.0)');

    const rainGradient = chartCtx.createLinearGradient(0, 0, 0, 250);
    rainGradient.addColorStop(0, 'rgba(6, 182, 212, 0.4)');
    rainGradient.addColorStop(1, 'rgba(6, 182, 212, 0.0)');

    telemetryChart = new Chart(chartCtx, {
        type: 'line',
        data: {
            labels: [],
            datasets: [
                {
                    label: 'Intensitas Cahaya (%)',
                    borderColor: '#f59e0b',
                    backgroundColor: lightGradient,
                    data: [],
                    fill: true,
                    tension: 0.4,
                    borderWidth: 2,
                    pointRadius: 3,
                    pointHoverRadius: 6,
                    yAxisID: 'y'
                },
                {
                    label: 'Sensor Air / Hujan',
                    borderColor: '#06b6d4',
                    backgroundColor: rainGradient,
                    data: [],
                    fill: true,
                    tension: 0.2,
                    borderWidth: 2,
                    pointRadius: 3,
                    pointHoverRadius: 6,
                    stepped: true,
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: {
                    labels: {
                        color: '#9ca3af',
                        font: { family: 'Plus Jakarta Sans', size: 12 }
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(17, 24, 39, 0.95)',
                    titleColor: '#f3f4f6',
                    bodyColor: '#9ca3af',
                    borderColor: 'rgba(255, 255, 255, 0.1)',
                    borderWidth: 1,
                    padding: 10,
                    callbacks: {
                        label: function(context) {
                            if (context.dataset.label.includes('Air')) {
                                return context.raw === 1 ? 'Sensor: Air Terdeteksi (Hujan)' : 'Sensor: Kering (Aman)';
                            }
                            return `Cahaya: ${context.raw}%`;
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: { color: 'rgba(255, 255, 255, 0.05)' },
                    ticks: { color: '#6b7280', font: { size: 10 } }
                },
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    min: 0,
                    max: 100,
                    grid: { color: 'rgba(255, 255, 255, 0.05)' },
                    ticks: {
                        color: '#f59e0b',
                        callback: val => val + '%'
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    min: 0,
                    max: 1.2,
                    grid: { drawOnChartArea: false },
                    ticks: {
                        stepSize: 1,
                        color: '#06b6d4',
                        callback: val => (val === 1 ? 'Hujan' : (val === 0 ? 'Kering' : ''))
                    }
                }
            }
        }
    });
}

function updateTelemetryChart(logs) {
    if (!telemetryChart || !logs || logs.length === 0) return;

    const labels = logs.map(item => {
        const d = new Date(item.timestamp);
        return isNaN(d) ? item.timestamp.split(' ')[1] || item.timestamp : d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    });

    const lightData = logs.map(item => item.light_level);
    const rainData = logs.map(item => item.rain_detected);

    telemetryChart.data.labels = labels;
    telemetryChart.data.datasets[0].data = lightData;
    telemetryChart.data.datasets[1].data = rainData;
    telemetryChart.update('none'); // Update without lagging animation
}
