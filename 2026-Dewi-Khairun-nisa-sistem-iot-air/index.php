<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}
require_once 'db.php';

$app_name = "AquaMonitor";
$res = $conn->query("SELECT config_value FROM pengaturan WHERE config_key = 'app_name'");
if ($res && $res->num_rows > 0) {
    $row = $res->fetch_assoc();
    $app_name = htmlspecialchars($row['config_value']);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>Pengukuran - Sistem Kualitas Air</title>
  <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@500;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/style.css?v=12">
  
  <!-- Leaflet CSS -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
</head>
<body>

  <!-- Sidebar -->
  <aside class="sidebar">
    <div class="brand" style="cursor: pointer" onclick="promptAppName()">
      <span>💧</span> <span id="brand-name"><?php echo $app_name; ?></span>
    </div>
    <div class="sidebar-menu">
      <a href="index.php" class="nav-link active">▶ <span class="nav-text">Pengukuran</span></a>
      <a href="monitoring.php" class="nav-link" id="nav-monitoring">📊 <span class="nav-text">Monitoring</span></a>
      
      <div class="nav-link disabled-link">
        <span>◉</span> <span class="nav-text">Mode ▾</span>
      </div>
      <a href="riwayat.php" class="nav-link">📜 <span class="nav-text">Riwayat</span></a>
    </div>
    <div class="sidebar-spacer"></div>
    <a href="logout.php" class="nav-link logout-btn" id="btn-logout">⎋ <span class="nav-text">Logout</span></a>
  </aside>

  <!-- Main Content -->
  <main class="main-content">
    <header class="topbar">
      <h1>Pengukuran Manual <span class="help-icon" onclick="openHelpModal()" title="Bantuan">?</span></h1>
      <div class="top-actions">
        <div class="online-indicator" id="online-status">
          <div class="dot"></div>
          <span class="text">Mengecek...</span>
        </div>
        <div class="theme-toggle" id="theme-toggle" title="Ganti Tema">☀️</div>
      </div>
    </header>

    <div class="measuring-control">
        <div id="map-setup-container" style="max-width: 600px; margin: 0 auto 1rem auto; text-align:left;">
            <div style="display: flex; gap: 0.75rem; align-items: center; margin-top: 0.5rem; margin-bottom: 1rem;">
                <div class="form-field" style="flex: 1; margin-bottom: 0;">
                    <input type="text" id="input-nama-lokasi" placeholder=" " autocomplete="off">
                    <label for="input-nama-lokasi">Masukkan Lokasi</label>
                </div>
                <button type="button" class="btn" onclick="getGPSLocation()" style="height: 48px; border-radius: 12px; white-space: nowrap; font-weight: 600;">📍 Gunakan GPS</button>
            </div>
            
            <label style="display:block; margin-bottom: 0.5rem; font-weight:600;">Titik Koordinat (Geser pin jika kurang akurat):</label>
            <div id="map-container" style="height: 250px; border-radius: 12px; overflow:hidden; border: 2px solid var(--border-color); margin-bottom: 1rem;"></div>
            <input type="hidden" id="hidden-coords" value="-1.6111, 103.5786">
        </div>

        <button id="btn-start" class="btn-giant" onclick="startMeasurement()">▶ MULAI PENGUKURAN</button>
        
        <div id="active-panel" style="display:none; text-align:center;">
            <div class="timer" id="timer-display">00:00</div>
            <div class="location-display">Lokasi: <span id="loc-text"></span></div>
            <button class="btn-giant stop" onclick="stopMeasurement()">⏹ HENTIKAN</button>
        </div>
    </div>

    <!-- Kotak Sensor (Awalnya kosong jika belum mulai) -->
    <div class="dashboard-grid turbidity-full" id="dashboard-grid" style="opacity: 0.5;">
      <div class="card-container">
        <div class="card">
          <h2>🌡️ Suhu Air</h2>
          <div class="value-display" id="disp-suhu"><div class="digital-value">--<span class="digital-unit">°C</span></div></div>
        </div>
      </div>
      <div class="card-container">
        <div class="card">
          <h2>🧪 pH Air</h2>
          <div class="value-display" id="disp-ph"><div class="digital-value">--<span class="digital-unit"></span></div></div>
        </div>
      </div>
      <div class="card-container">
        <div class="card">
          <h2>🌫️ Kekeruhan (Turbidity)</h2>
          <div class="value-display" id="disp-turbidity"><div class="digital-value">--<span class="digital-unit">NTU</span></div></div>
        </div>
      </div>
    </div>

  </main>

  <!-- Help Modal -->
  <div id="help-modal" class="modal-overlay" style="display:none;">
    <div class="modal-content" style="max-width: 450px;">
      <div class="modal-header">
        <h3 id="modal-title">Panduan Penggunaan</h3>
        <button class="close-btn" onclick="closeHelpModal()">✕</button>
      </div>
      <div style="padding: 1.5rem; line-height: 1.6; color: var(--text-primary);">
        <p><strong>📍 Cara Memilih Lokasi:</strong></p>
        <ol style="margin-left: 1.5rem; margin-top: 0.5rem;">
          <li>Klik tombol <strong>"Gunakan GPS"</strong> untuk mendeteksi lokasimu otomatis.</li>
          <li>Jika titik lokasi kurang pas, kamu bisa <strong>menggesernya secara manual</strong> di peta.</li>
          <li>Jangan lupa ketik nama tempat (misal: <em>Sungai Jelutung</em>) di kolom yang disediakan sebelum memulai pengukuran.</li>
        </ol>
      </div>
    </div>
  </div>

  <script src="assets/theme.js"></script>
  <script>
    let isMeasuring = false;
    let startTime = 0;
    let timerInterval = null;
    let dataInterval = null;
    let lastSensorData = null;

    async function checkState() {
        const res = await fetch('api/get_data.php');
        if (res.status === 401) { window.location.href = 'login.php'; return; }
        const json = await res.json();
        
        updateOnlineStatus(json.online);

        const btnStart = document.getElementById('btn-start');
        
        // Handle Start Button state
        if (!json.online && !isMeasuring) {
            btnStart.title = 'Alat Offline! Tidak dapat memulai pengukuran.';
        } else if (json.online && !isMeasuring) {
            btnStart.title = '';
        }
        
        if(json.state && json.state.status === 'MENGUKUR') {
            // Force stop softly if device goes offline during measurement
            if (!json.online) {
                if(!window.offlinePrompted) {
                    window.offlinePrompted = true;
                    if(confirm("Peringatan: Alat terputus (Offline)! Apakah Anda ingin MENYIMPAN data terakhir sebelum alat mati?")) {
                        const durasi = document.getElementById('timer-display').textContent;
                        const fd = new URLSearchParams();
                        fd.append('durasi', durasi);
                        fetch('api/save_manual.php', { method: 'POST', body: fd }).then(() => {
                            forceStopMeasurement();
                        });
                    } else {
                        forceStopMeasurement();
                    }
                }
                return;
            }

            if(!isMeasuring) {
                // Resume active measurement UI
                isMeasuring = true;
                startTime = json.state.start_time;
                document.getElementById('loc-text').textContent = json.state.location_name;
                showActivePanel();
            }
            if(json.latest) {
                lastSensorData = json.latest;
                updateBoxes(json.latest);
            }
        } else {
            if(isMeasuring) {
                resetUI();
            }
        }
    }

    function updateOnlineStatus(isOnline) {
      const el = document.getElementById('online-status');
      if (isOnline) {
        el.className = 'online-indicator online';
        el.querySelector('.text').textContent = 'Online';
        document.body.classList.remove('offline-mode');
      } else {
        el.className = 'online-indicator offline';
        el.querySelector('.text').textContent = 'Offline';
        document.body.classList.add('offline-mode');
      }
    }

    function updateBoxes(data) {
        document.getElementById('disp-suhu').innerHTML = `<div class="digital-value">${data.suhu}<span class="digital-unit">°C</span></div>`;
        document.getElementById('disp-ph').innerHTML = `<div class="digital-value">${data.ph}<span class="digital-unit"></span></div>`;
        document.getElementById('disp-turbidity').innerHTML = `<div class="digital-value">${data.turbidity}<span class="digital-unit">NTU</span></div>`;
    }

    function resetUI() {
        isMeasuring = false;
        window.offlinePrompted = false;
        clearInterval(timerInterval);
        document.getElementById('map-setup-container').style.display = 'block';
        document.getElementById('btn-start').style.display = 'inline-block';
        document.getElementById('active-panel').style.display = 'none';
        document.getElementById('dashboard-grid').style.opacity = '0.5';
        document.getElementById('nav-monitoring').classList.remove('disabled-link');
        updateBoxes({suhu: '--', ph: '--', turbidity: '--'});
    }

    function showActivePanel() {
        document.getElementById('map-setup-container').style.display = 'none';
        document.getElementById('btn-start').style.display = 'none';
        document.getElementById('active-panel').style.display = 'block';
        document.getElementById('dashboard-grid').style.opacity = '1';
        document.getElementById('nav-monitoring').classList.add('disabled-link');
        
        clearInterval(timerInterval);
        
        const updateTimer = () => {
            const elapsed = Math.max(0, Math.floor((Date.now() / 1000) - startTime));
            const m = Math.floor(elapsed / 60).toString().padStart(2, '0');
            const s = (elapsed % 60).toString().padStart(2, '0');
            document.getElementById('timer-display').textContent = `${m}:${s}`;
        };
        
        updateTimer();
        timerInterval = setInterval(updateTimer, 1000);
    }



    async function startMeasurement() {
        const isOnline = !document.body.classList.contains('offline-mode');
        if (!isOnline) {
            alert("Alat Offline! Tidak dapat memulai pengukuran.");
            return;
        }

        const loc = document.getElementById('input-nama-lokasi').value;
        if(loc.trim() === '') { 
            const input = document.getElementById('input-nama-lokasi');
            input.style.border = "2px solid var(--danger)";
            input.classList.add('shake');
            setTimeout(() => {
                input.classList.remove('shake');
                input.style.border = "1px solid var(--border-color)";
            }, 500);
            return; 
        }

        const coords = document.getElementById('hidden-coords').value;

        const fd = new URLSearchParams();
        fd.append('action', 'start');
        fd.append('lokasi', loc);
        fd.append('koordinat', coords || '-');

        await fetch('api/set_state.php', { method: 'POST', body: fd });
        checkState();
    }

    async function stopMeasurement() {
        if(confirm("Pengukuran dihentikan. Apakah Anda ingin MENYIMPAN data terakhir ini ke Riwayat?")) {
            const durasi = document.getElementById('timer-display').textContent;
            const fdSave = new URLSearchParams();
            fdSave.append('durasi', durasi);
            await fetch('api/save_manual.php', { method: 'POST', body: fdSave });
            alert("Berhasil disimpan!");
        } else {
            alert("Dibatalkan!");
        }
        
        const fd = new URLSearchParams();
        fd.append('action', 'stop');
        await fetch('api/set_state.php', { method: 'POST', body: fd });
        checkState();
    }

    document.getElementById('btn-logout').addEventListener('click', async (e) => {
        e.preventDefault();
        if (isMeasuring) {
            if (confirm("Masih dalam pengukuran, stop dan logout?")) {
                await stopMeasurement(); // This handles simpan/batal
                window.location.href = 'logout.php';
            }
        } else {
            if (confirm("Apakah Anda yakin ingin logout?")) {
                window.location.href = 'logout.php';
            }
        }
    });

    async function forceStopMeasurement() {
        const fd = new URLSearchParams();
        fd.append('action', 'stop');
        await fetch('api/set_state.php', { method: 'POST', body: fd });
        resetUI();
        checkState();
    }

    // Leaflet Map Initialization
    let map = null;
    let marker = null;
    function initMap() {
        // Default coord (e.g., Jambi)
        let defLat = -1.6111;
        let defLng = 103.5786;

        map = L.map('map-container').setView([defLat, defLng], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        marker = L.marker([defLat, defLng], {draggable: true}).addTo(map);
        marker.on('dragend', function(e) {
            const pos = marker.getLatLng();
            document.getElementById('hidden-coords').value = `${pos.lat.toFixed(6)}, ${pos.lng.toFixed(6)}`;
        });
    }

    function getGPSLocation() {
        if(navigator.geolocation) {
            const btn = document.querySelector('button[onclick="getGPSLocation()"]');
            btn.textContent = "⏳ Mencari...";
            navigator.geolocation.getCurrentPosition(pos => {
                const lat = pos.coords.latitude;
                const lng = pos.coords.longitude;
                if (map && marker) {
                    map.setView([lat, lng], 15);
                    marker.setLatLng([lat, lng]);
                }
                document.getElementById('hidden-coords').value = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
                btn.textContent = "✅ Berhasil";
                setTimeout(() => btn.textContent = "📍 Gunakan GPS", 2000);
            }, err => {
                alert("Gagal mendapatkan lokasi GPS. Pastikan izin lokasi diaktifkan pada browser Anda.");
                btn.textContent = "📍 Gunakan GPS";
            });
        } else {
            alert("Browser Anda tidak mendukung fitur GPS.");
        }
    }

    function openHelpModal() {
        document.getElementById('help-modal').style.display = 'flex';
    }

    function closeHelpModal() {
        document.getElementById('help-modal').style.display = 'none';
    }

    // Leaflet JS
    const leafletScript = document.createElement('script');
    leafletScript.src = "https://unpkg.com/leaflet@1.9.4/dist/leaflet.js";
    leafletScript.onload = () => { initMap(); };
    document.head.appendChild(leafletScript);

    function promptAppName() {
        if (typeof isMeasuring !== 'undefined' && isMeasuring) {
            alert("Tidak bisa mengubah nama aplikasi saat pengukuran sedang berlangsung!");
            return;
        }
        const newName = prompt("Easter Egg! Ubah Nama Aplikasi (Kosongkan untuk Reset Default):");
        if (newName !== null) {
            const fd = new URLSearchParams();
            fd.append('name', newName);
            fetch('api/save_setting.php', { method: 'POST', body: fd })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'ok') {
                        document.getElementById('brand-name').textContent = data.name;
                    }
                });
        }
    }

    // Initial load
    checkState();
    dataInterval = setInterval(checkState, 2000);
  </script>
</body>
</html>
