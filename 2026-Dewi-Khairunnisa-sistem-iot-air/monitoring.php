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
  <title>Monitoring - Sistem Kualitas Air</title>
  <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@500;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/style.css?v=12">
</head>
<body>
  
  <div id="floating-banner" class="floating-banner" style="display:none;" onclick="window.location.href='index.php'">
     Pengukuran sedang berlangsung (<span id="banner-timer">00:00</span>) - [Klik untuk Kembali]
  </div>

  <!-- Sidebar -->
  <aside class="sidebar">
    <div class="brand" style="cursor: pointer" onclick="promptAppName()">
      <span>💧</span> <span id="brand-name"><?php echo $app_name; ?></span>
    </div>
    
    <div class="sidebar-menu">
      <a href="index.php" class="nav-link" id="nav-pengukuran">▶ <span class="nav-text">Pengukuran</span></a>
      <a href="monitoring.php" class="nav-link active" id="nav-monitoring">📊 <span class="nav-text">Monitoring</span></a>
      
      <div class="nav-link" style="cursor: default;" id="mode-selector-container">
        <span>◉</span>
        <select id="mode-selector" class="mode-select">
          <option value="digital">Digital Mode </option>
          <option value="speedometer">Speedometer </option>
          <option value="diagram">Diagram </option>
          <option value="bar">Bar Mode </option>
        </select>
      </div>

      <a href="riwayat.php" class="nav-link">📜 <span class="nav-text">Riwayat</span></a>
    </div>

    <div class="sidebar-spacer"></div>
    <a href="logout.php" class="nav-link logout-btn" id="btn-logout">⎋ <span class="nav-text">Logout</span></a>
  </aside>

  <!-- Main Content -->
  <main class="main-content">
    <header class="topbar">
      <h1>Pemantauan Kualitas Air</h1>
      <div class="top-actions">
        <div class="online-indicator" id="online-status">
          <div class="dot"></div>
          <span class="text">Mengecek...</span>
        </div>
        <div class="theme-toggle" id="theme-toggle" title="Ganti Tema">☀️</div>
      </div>
    </header>

    <div class="dashboard-grid" id="dashboard-grid">
      <!-- Suhu -->
      <div class="card-container">
        <div class="card">
          <h2>🌡️ Suhu Air</h2>
          <div class="value-display" id="disp-suhu">
            <!-- content injected by JS -->
          </div>
        </div>
      </div>

      <!-- pH -->
      <div class="card-container">
        <div class="card">
          <h2>🧪 pH Air</h2>
          <div class="value-display" id="disp-ph">
            <!-- content injected by JS -->
          </div>
        </div>
      </div>

      <!-- Turbidity -->
      <div class="card-container">
        <div class="card">
          <h2>🌫️ Kekeruhan (Turbidity)</h2>
          <div class="value-display" id="disp-turbidity">
            <!-- content injected by JS -->
          </div>
        </div>
      </div>
    </div>

    <div class="last-update" id="last-update">
      Pembaruan terakhir: --
    </div>
  </main>

  <script src="assets/theme.js"></script>
  <script>
    let currentMode = localStorage.getItem('displayMode') || 'digital';
    const modeSelector = document.getElementById('mode-selector');
    modeSelector.value = currentMode;
    
    // Config sensor limits for gauge/bar
    const limits = {
      suhu: { min: 0, max: 50, unit: '°C' },
      ph: { min: 0, max: 14, unit: '' },
      turbidity: { min: 0, max: 3000, unit: 'NTU' }
    };

    modeSelector.addEventListener('change', (e) => {
      currentMode = e.target.value;
      localStorage.setItem('displayMode', currentMode);
      if(lastData) renderData(lastData, lastHistory);
    });

    let lastData = null;
    let lastHistory = [];
    let liveHistory = [];

    async function fetchData() {
      try {
        const res = await fetch('api/get_data.php');
        if (res.status === 401) { window.location.href = 'login.php'; return; }
        const json = await res.json();
        
        updateOnlineStatus(json.online);
        
        if (json.latest) {
          lastData = json.latest;
          lastHistory = json.history;
          
          if (liveHistory.length === 0 || liveHistory[liveHistory.length - 1].waktu !== lastData.waktu) {
              liveHistory.push(lastData);
              if (liveHistory.length > 30) liveHistory.shift();
          }

          document.getElementById('last-update').innerHTML = `Pembaruan terakhir: ${lastData.waktu}`;
          renderData(lastData, liveHistory);
        }

        // Logic for floating banner and greying out monitoring
        const isMeasuring = json.state && json.state.status === 'MENGUKUR';
        window.isMeasuringGlobal = isMeasuring; // store globally for logout check
        
        const banner = document.getElementById('floating-banner');
        const navMon = document.getElementById('nav-monitoring');
        const navPeng = document.getElementById('nav-pengukuran');
        const navMode = document.getElementById('mode-selector-container');
        
        if (isMeasuring) {
            banner.style.display = 'block';
            navMon.classList.add('disabled-link');
            navPeng.classList.add('disabled-link');
            navMode.classList.add('disabled-link');
            // update timer
            const elapsed = Math.floor((Date.now() / 1000) - json.state.start_time);
            const m = Math.floor(elapsed / 60).toString().padStart(2, '0');
            const s = (elapsed % 60).toString().padStart(2, '0');
            document.getElementById('banner-timer').textContent = `${m}:${s}`;
        } else {
            banner.style.display = 'none';
            navMon.classList.remove('disabled-link');
            navPeng.classList.remove('disabled-link');
            navMode.classList.remove('disabled-link');
        }

      } catch (err) {
        console.error("Fetch error", err);
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

    function renderData(data, history) {
      const grid = document.getElementById('dashboard-grid');
      
      if(currentMode === 'digital' || currentMode === 'bar' || currentMode === 'speedometer') {
         grid.classList.add('turbidity-full');
      } else {
         grid.classList.remove('turbidity-full');
      }

      renderItem('suhu', data.suhu, history);
      renderItem('ph', data.ph, history);
      renderItem('turbidity', data.turbidity, history);
    }

    function renderItem(key, val, history) {
      const container = document.getElementById(`disp-${key}`);
      const lim = limits[key];
      
      if (currentMode === 'digital') {
        container.innerHTML = `<div class="digital-value">${val}<span class="digital-unit">${lim.unit}</span></div>`;
      } 
      else if (currentMode === 'speedometer') {
        let pct = (val - lim.min) / (lim.max - lim.min) * 100;
        if(pct > 100) pct = 100; if(pct < 0) pct = 0;
        const deg = -90 + (pct * 1.8); // -90 to 90
        
        container.innerHTML = `
          <div class="gauge-container">
            <svg viewBox="0 0 100 50" style="width:100%; height:auto;">
              <path d="M 10 50 A 40 40 0 0 1 90 50" fill="none" stroke="rgba(13, 148, 136, 0.2)" stroke-width="15" stroke-linecap="round"/>
              <path d="M 10 50 A 40 40 0 0 1 90 50" fill="none" stroke="var(--accent-color)" stroke-width="15" stroke-linecap="round" stroke-dasharray="125.6" stroke-dashoffset="${125.6 - (pct/100*125.6)}"/>
              <line x1="50" y1="50" x2="50" y2="15" stroke="var(--text-primary)" stroke-width="3" stroke-linecap="round" class="gauge-needle" style="transform: rotate(${deg}deg);"/>
              <circle cx="50" cy="50" r="5" fill="var(--text-primary)"/>
            </svg>
            <div class="gauge-value">${val} <span style="font-size:1rem;font-weight:normal">${lim.unit}</span></div>
          </div>
        `;
      }
      else if (currentMode === 'bar') {
        let pct = (val - lim.min) / (lim.max - lim.min) * 100;
        if(pct > 100) pct = 100; if(pct < 0) pct = 0;
        
        container.innerHTML = `
          <div class="bar-container">
            <div class="digital-value" style="font-size:2rem;margin-bottom:1rem;text-align:center">${val} ${lim.unit}</div>
            <div class="bar-bg">
              <div class="bar-fill" style="width: ${pct}%"></div>
            </div>
            <div class="bar-labels">
              <span>${lim.min}</span>
              <span>${lim.max}</span>
            </div>
          </div>
        `;
      }
      else if (currentMode === 'diagram') {
        if(history.length === 0) return;
        const w = 300, h = 100;
        const vals = history.map(h => h[key]);
        const minVal = Math.min(...vals) * 0.9;
        const maxVal = Math.max(...vals) * 1.1;
        const range = (maxVal - minVal) || 1;
        
        let path = "";
        let dots = "";
        vals.forEach((v, i) => {
          const x = (i / (vals.length - 1 || 1)) * w;
          const y = h - ((v - minVal) / range * h);
          path += `${i===0?'M':'L'} ${x} ${y} `;
          dots += `
            <g class="chart-point">
              <circle cx="${x}" cy="${y}" r="15" fill="transparent"/>
              <circle cx="${x}" cy="${y}" r="3" class="chart-dot"/>
              <text x="${x}" y="${y - 8}" class="chart-tooltip">${v}</text>
            </g>
          `;
        });

        container.innerHTML = `
          <div style="width:100%; text-align:center">
             <div style="font-size:2rem; font-weight:bold; margin-bottom:0.5rem">${val} <span style="font-size:1rem;font-weight:normal">${lim.unit}</span></div>
             <svg viewBox="0 0 ${w} ${h}" class="chart-container" preserveAspectRatio="none" style="padding: 5px; overflow: visible;">
               <path d="${path}" class="chart-line"/>
               ${dots}
             </svg>
          </div>
        `;
      }
    }

    document.getElementById('btn-logout').addEventListener('click', async (e) => {
        e.preventDefault();
        if (window.isMeasuringGlobal) {
            if (confirm("Masih dalam pengukuran, stop dan logout?")) {
                if(confirm("Pengukuran dihentikan. Apakah Anda ingin MENYIMPAN data terakhir ini ke Riwayat?")) {
                    await fetch('api/save_manual.php', { method: 'POST' });
                }
                const fd = new URLSearchParams();
                fd.append('action', 'stop');
                await fetch('api/set_state.php', { method: 'POST', body: fd });
                window.location.href = 'logout.php';
            }
        } else {
            if (confirm("Apakah Anda yakin ingin logout?")) {
                window.location.href = 'logout.php';
            }
        }
    });

    function promptAppName() {
        if (window.isMeasuringGlobal) {
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

    // Fetch immediately, then every 5 seconds
    fetchData();
    setInterval(fetchData, 5000);
  </script>
</body>
</html>
