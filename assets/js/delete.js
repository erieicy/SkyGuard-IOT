//<!-- Skrip penghapusan riwayat foto -->

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
