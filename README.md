# 🛡️ SkyGuard AI - Smart Automated IoT Clothesline System

> **Jemuran Baju Pintar Berbasis AI & IoT dengan Kontrol Mikrokontroler ESP32-S & ESP32-CAM**

![SkyGuard AI Dashboard](https://images.unsplash.com/photo-1534088568595-a066f410bcda?w=1200&auto=format&fit=crop&q=80)

---

## 📖 Ringkasan Proyek

**SkyGuard AI** adalah solusi cerdas otomatisasi penjemuran pakaian rumah tangga berbasis IoT (*Internet of Things*) dan *Computer Vision AI*. Sistem ini dirancang untuk melindungi pakaian dari kehujanan secara instan, mengoptimalkan penjemuran saat matahari terik, serta mencegah kesalahan deteksi cahaya buatan (seperti lampu ruangan).

### 🌟 Fitur Utama:
1. **Proteksi Air & Hujan Instan (*Zero Latency*)**:
   - Sensor air/hujan (*Raindrop Sensor*) memantau tetesan air. Jika air menyentuh sensor, motor atap jemuran langsung menutup otomatis secara darurat dan membunyikan alarm peringatan.
2. **AI Vision: Matahari Alami vs Lampu Listrik**:
    - Ketika sensor LDR mendeteksi cahaya, modul ESP32-CAM mengambil citra.
    - Algoritma AI menganalisis spektrum pencahayaan untuk membedakan antara sinar matahari alami (membuka atap) dan cahaya lampu ruangan biasa (tetap menutup atap).
    - **Mesin AI multi-provider**: *Local AI Vision Engine* (offline, tanpa API key), **Google Gemini Vision**, atau **OpenAI Vision**. Pilih di menu Pengaturan AI.
3. **Deteksi Awan Mendung (*Overcast Protection*)**:
   - Pengguna atau ESP32-CAM dapat memotret kondisi awan langit.
   - Jika AI mendeteksi awan tebal mendung (*kumulonimbus/nimbostratus*), sistem memicu peringatan dini dan menutup atap jemuran secara otomatis.
4. **Smart Timer & Stopwatch Jemur**:
   - Rekomendasi durasi jemur optimal dari AI (misal: 45 menit saat matahari terik).
   - Stopwatch *countdown* timer (15m, 30m, 60m, atau kustom) dengan aksi otomatis menutup atap saat timer habis.
5. **Dashboard IoT Glassmorphism Real-Time**:
   - Visualisasi grafis status atap bergerak interaktif (SVG/3D-style animation).
   - Grafik telemetri sensor suhu/cahaya/air (*Chart.js*).
   - Galeri foto riwayat cuaca & audit log telemetri sensor.
   - Peringatan audio tersintesis via *Web Audio API*.
   6. **Koneksi ESP32 via Wi-Fi IP**:
    - Masukkan IP ESP32 di kolom WiFi pada dashboard. Saat modul ESP32 terdeteksi polling ke server, tombol indikator berubah **hijau (TERHUBUNG)**; jika putus menjadi **merah (TIDAK TERHUBUNG)** dan seluruh data telemetri ditampilkan kosong.
   7. **Firmware Mikrokontroler Lengkap (.txt)**:
   - File kode firmware C++/Arduino siap pakai untuk **ESP32** dan **ESP32-CAM** serta panduan skematik pinout lengkap.

---

## 📂 Struktur Direktori Proyek

```
c:/xampp/htdocs/SkyGuard-AI/
├── api/
│   ├── db.php                     # Koneksi SQLite & inisialisasi skema tabel otomatis
│   ├── status.php                 # Endpoint telemetri real-time, timer expiry & alerts
│   ├── control.php                # Endpoint pengendali motor atap, mode, & stopwatch
│   ├── esp32.php                  # Endpoint REST API khusus ESP32 & ESP32-CAM
│   ├── ai_analyze.php             # Engine Computer Vision AI (Matahari vs Lampu & Mendung)
│   ├── history.php                # Endpoint log audit sensor & riwayat foto
│   ├── alerts.php                 # Endpoint manajemen notifikasi sistem
│   └── seed.php                   # Database seeder untuk data awal
├── assets/
│   ├── css/
│   │   └── style.css              # Custom Glassmorphism IoT styling & animasi atap
│   └── js/
│       ├── app.js                 # Frontend app controller, polling, audio alarm & controls
│       └── charts.js              # Visualisasi grafik telemetri Chart.js
├── firmware/
│   ├── esp32_firmware.txt         # Source code C++/Arduino ESP32 (Sensor & Motor Servo)
│   ├── esp32_cam_firmware.txt     # Source code C++/Arduino ESP32-CAM (Snapshot & HTTP POST)
│   └── WIRING_AND_PINOUT_GUIDE.txt # Skematik sirkuit lengkap, tabel pinout, & BOM
├── uploads/                       # Direktori penyimpanan foto cuaca langit
├── data/                          # Database SQLite lokal (skyguard.db)
├── index.php                      # Halaman dashboard utama SkyGuard AI
├── history.php                    # Halaman galeri foto & audit trail
├── firmware.php                   # Halaman dokumentasi kode & skematik
└── README.md                      # Dokumentasi komprehensif proyek
```

---

## 🔌 Skematik Pinout & Hardware

### 1. ESP32 DevKit V1 (Modul Utama)
| Komponen | Pin Modul | Pin ESP32 | Keterangan |
| :--- | :--- | :--- | :--- |
| **Sensor Hujan** | DO | GPIO 34 | Sinyal Digital Air Instan |
| **Sensor Hujan** | AO | GPIO 35 | Sinyal Analog Kelembaban |
| **Sensor LDR** | AO | GPIO 32 | Intensitas Cahaya (0 - 4095) |
| **Motor Servo** | PWM (Kuning) | GPIO 18 | Penggerak Atap (0° Tutup / 180° Buka) |
| **Buzzer** | VCC (+) | GPIO 23 | Alarm Peringatan Hujan |
| **LED Indikator** | Onboard | GPIO 2 | Status Koneksi Wi-Fi |

### 2. ESP32-CAM AI-Thinker
- **Kamera**: Sensor OV2640 (VGA 640x480).
- **Komunikasi**: HTTP Multipart POST ke endpoint `/api/esp32.php?action=upload_cam`.

---

## 🚀 Cara Menjalankan Aplikasi di XAMPP

1. **Pastikan Web Server Aktif**:
   - Buka **XAMPP Control Panel**.
   - Jalankan modul **Apache**.
2. **Akses Dashboard**:
   - Buka browser dan navigasikan ke:
     ```
     http://localhost/SkyGuard-AI/
     ```
 3. **Hubungkan ESP32 via Wi-Fi**:
    - Isi IP ESP32 (mis. `192.168.1.x`) di kolom WiFi pojok kanan atas dashboard.
    - Saat ESP32 mem-poll server, tombol indikator berubah **hijau (TERHUBUNG)**; jika putus menjadi **merah (TIDAK TERHUBUNG)** dan seluruh data telemetri tampil kosong.
    - Pastikan firmware ESP32 & ESP32-CAM diisi `SERVER_URL` = alamat server di atas (lihat menu Pengaturan AI).

---

## 🌐 Dokumentasi REST API

| Method | Endpoint | Deskripsi |
| :--- | :--- | :--- |
| `GET` | `/api/status.php` | Mengambil status telemetri real-time, sisa timer, dan peringatan aktif. |
| `POST` | `/api/control.php` | Mengirim perintah buka/tutup atap, switch mode (Auto/Manual/Timer), set durasi timer. |
| `POST` | `/api/ai_analyze.php` | Menganalisis file citra/foto langit dan mengembalikan klasifikasi AI cuaca. |
| `GET` | `/api/settings.php?action=get_settings` | Mengambil konfigurasi (provider AI terdeteksi, API key mask, alamat server/IP untuk ESP32). |
| `POST` | `/api/settings.php?action=save_settings` | Menyimpan satu API key (otomatis deteksi Gemini/OpenAI) & alamat server. |
| `GET` | `/api/esp32.php?action=get_command` | Digunakan ESP32 untuk polling posisi target servo/motor & melaporkan IP-nya. |
| `POST` | `/api/esp32.php?action=update_sensors` | Digunakan ESP32 untuk mengirim nilai sensor air dan cahaya. |
| `POST` | `/api/esp32.php?action=upload_cam` | Digunakan ESP32-CAM untuk mengunggah foto langit. |
| `GET` | `/api/history.php` | Mengambil riwayat log telemetri dan galeri foto cuaca. |

---

## 🛠️ Hak Cipta & Lisensi
Proyek **SkyGuard AI** dikembangkan untuk ekosistem smart home & IoT jemuran pakaian cerdas.
Lisensi: MIT Open Source.
