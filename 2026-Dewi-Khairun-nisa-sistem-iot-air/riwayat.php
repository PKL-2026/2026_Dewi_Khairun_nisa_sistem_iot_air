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
  <title>Riwayat - Sistem Kualitas Air</title>
  <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@500;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/style.css?v=12">
  
  <!-- Leaflet CSS -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
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
      <a href="monitoring.php" class="nav-link" id="nav-monitoring">📊 <span class="nav-text">Monitoring</span></a>
      
      <!-- mode selector not needed here, but keeping layout consistent -->
      <div class="nav-link" style="opacity: 0.5; pointer-events:none">
        <span>◉</span> <span class="nav-text">Mode ▾</span>
      </div>

      <a href="riwayat.php" class="nav-link active">📜 <span class="nav-text">Riwayat</span></a>
    </div>

    <div class="sidebar-spacer"></div>
    <a href="logout.php" class="nav-link logout-btn" id="btn-logout">⎋ <span class="nav-text">Logout</span></a>
  </aside>

  <!-- Main Content -->
  <main class="main-content">
    <header class="topbar">
      <h1>Riwayat Data</h1>
      <div class="top-actions">
        <div class="theme-toggle" id="theme-toggle" title="Ganti Tema">☀️</div>
      </div>
    </header>

    <div class="controls">
      <input type="text" id="search" class="search-box" placeholder="Cari tanggal, jam, atau angka (contoh: 07 Okt, 29.8)...">
      <button class="btn" onclick="document.getElementById('search').value=''; currentPage=1; loadData()"><span style="font-size:1.2rem">↻</span> Refresh</button>
      <button class="btn danger" onclick="deleteAll()">
        <svg viewBox="0 0 32 32" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-miterlimit="10" style="vertical-align: middle; margin-right: 4px; margin-bottom: 2px;">
          <path d="M23,27H11c-1.1,0-2-0.9-2-2V8h16v17C25,26.1,24.1,27,23,27z"/>
          <line x1="27" y1="8" x2="7" y2="8"/>
          <path d="M14,8V6c0-0.6,0.4-1,1-1h4c0.6,0,1,0.4,1,1v2"/>
          <line x1="17" y1="23" x2="17" y2="12"/>
          <line x1="21" y1="23" x2="21" y2="12"/>
          <line x1="13" y1="23" x2="13" y2="12"/>
        </svg> Hapus Semua
      </button>
    </div>

    <div class="table-container">
      <table>
        <thead>
          <tr>
            <th style="width:40px; cursor:pointer;" onclick="toggleCheckAll()" title="Pilih Semua Data">
              <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle;">
                <path d="M8 6h13"></path>
                <path d="M8 12h13"></path>
                <path d="M8 18h13"></path>
                <path d="M3 6l2 2 4-4"></path>
                <path d="M3 12l2 2 4-4"></path>
                <path d="M3 18l2 2 4-4"></path>
              </svg>
            </th>
            <th>Waktu (WIB)</th>
            <th>Durasi</th>
            <th>Suhu (°C)</th>
            <th>pH</th>
            <th>Kekeruhan</th>
            <th>Lokasi</th>
            <th>Koordinat / GPS</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody id="table-body">
          <!-- loaded via JS -->
        </tbody>
      </table>
    </div>
    
    <!-- Pagination Controls -->
    <div id="pagination-controls" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-top: 1.5rem; width: 100%;">
      <div style="color: var(--text-secondary); font-size: 0.9rem; padding-left: 0.5rem;">
        Total Data: <strong id="total-items-text">0</strong>
      </div>
      <div style="display: flex; gap: 0.5rem; align-items: center; margin-left: auto;">
        <button class="btn" id="btn-prev" onclick="changePage(-1)" style="padding: 0.5rem 0.8rem;" title="Sebelumnya">
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle;"><polyline points="15 18 9 12 15 6"></polyline></svg>
        </button>
        <span style="color: var(--text-primary); font-weight: 500; padding: 0 0.5rem;">Hal <span id="current-page-text">1</span> / <span id="total-pages-text">1</span></span>
        <button class="btn" id="btn-next" onclick="changePage(1)" style="padding: 0.5rem 0.8rem;" title="Selanjutnya">
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle;"><polyline points="9 18 15 12 9 6"></polyline></svg>
        </button>
      </div>
    </div>

    <div class="controls" style="margin-top: 1rem;">
      <button class="btn danger" onclick="deleteSelected()" id="btn-del-sel" style="display:none">
        <svg viewBox="0 0 32 32" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-miterlimit="10" style="vertical-align: middle; margin-right: 4px; margin-bottom: 2px;">
          <path d="M23,27H11c-1.1,0-2-0.9-2-2V8h16v17C25,26.1,24.1,27,23,27z"/>
          <line x1="27" y1="8" x2="7" y2="8"/>
          <path d="M14,8V6c0-0.6,0.4-1,1-1h4c0.6,0,1,0.4,1,1v2"/>
          <line x1="17" y1="23" x2="17" y2="12"/>
          <line x1="21" y1="23" x2="21" y2="12"/>
          <line x1="13" y1="23" x2="13" y2="12"/>
        </svg> Hapus Terpilih
      </button>
    </div>

  </main>

  <script src="assets/theme.js"></script>
  <script>
    let searchTimeout;
    const searchInput = document.getElementById('search');
    let currentPage = 1;
    let totalPages = 1;
    
    searchInput.addEventListener('input', () => {
      clearTimeout(searchTimeout);
      currentPage = 1; // reset ke halaman 1 saat pencarian
      searchTimeout = setTimeout(() => {
        loadData();
      }, 300);
    });

    function changePage(delta) {
      const newPage = currentPage + delta;
      if (newPage >= 1 && newPage <= totalPages) {
        currentPage = newPage;
        loadData();
      }
    }

    async function loadData() {
      const q = searchInput.value;
      const res = await fetch(`api/history.php?q=${encodeURIComponent(q)}&page=${currentPage}`);
      const responseJson = await res.json();
      
      let data = [];
      if (responseJson.data) {
          data = responseJson.data;
          currentPage = responseJson.pagination.current_page;
          totalPages = responseJson.pagination.total_pages;
          
          document.getElementById('current-page-text').textContent = currentPage;
          document.getElementById('total-pages-text').textContent = totalPages || 1;
          document.getElementById('total-items-text').textContent = responseJson.pagination.total_items;
          
          const btnPrev = document.getElementById('btn-prev');
          const btnNext = document.getElementById('btn-next');
          
          btnPrev.style.opacity = currentPage <= 1 ? '0.4' : '1';
          btnPrev.style.pointerEvents = currentPage <= 1 ? 'none' : 'auto';
          
          btnNext.style.opacity = currentPage >= totalPages ? '0.4' : '1';
          btnNext.style.pointerEvents = currentPage >= totalPages ? 'none' : 'auto';
      } else {
          data = responseJson; // fallback if old format
      }
      
      const tbody = document.getElementById('table-body');
      tbody.innerHTML = '';
      
      if(data.length === 0) {
          tbody.innerHTML = '<tr><td colspan="9" style="text-align:center">Tidak ada data ditemukan</td></tr>';
          return;
      }

      data.forEach(row => {
        let mapsLink = '-';
        if (row.koordinat && row.koordinat !== '-') {
            const coords = row.koordinat.replace(/\s/g, '');
            mapsLink = `
              <button class="btn" style="padding:0.2rem 0.5rem; font-size:0.8rem; margin-bottom:0.2rem; white-space: nowrap;" onclick="openMapModal('${coords}', '${row.lokasi || ''}')">🗺️ Lihat Peta</button><br>
              <a href="https://maps.google.com/?q=${encodeURIComponent(row.koordinat)}" target="_blank" style="font-size:0.8rem; color:var(--accent-color); text-decoration:none; white-space: nowrap; display: inline-block;">🔗 Buka Gmaps</a>
            `;
        }

        const tr = document.createElement('tr');
        tr.id = `row-${row.id}`;
        tr.innerHTML = `
          <td><input type="checkbox" class="row-check" value="${row.id}" onchange="checkSelection()"></td>
          <td>${row.waktu_fmt}</td>
          <td>${row.durasi || '-'}</td>
          <td>${row.suhu}</td>
          <td>${row.ph}</td>
          <td>${row.turbidity}</td>
          <td>${row.lokasi || '-'}</td>
          <td>${row.koordinat || '-'} <br> ${mapsLink}</td>
          <td style="white-space: nowrap;">
             ${row.session_id ? `<button class="btn" style="padding: 0.3rem 0.6rem; background: var(--accent-color); color: white; margin-right: 5px;" onclick="openLogModal('${row.session_id}', '${row.waktu_fmt}')" title="Lihat Detail Log">
               <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle;">
                 <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
               </svg>
             </button>` : ''}
             <button class="btn danger" style="padding: 0.3rem 0.6rem" onclick="deleteSingle(${row.id})" title="Hapus">
               <svg viewBox="0 0 32 32" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-miterlimit="10" style="vertical-align: middle;">
                 <path d="M23,27H11c-1.1,0-2-0.9-2-2V8h16v17C25,26.1,24.1,27,23,27z"/>
                 <line x1="27" y1="8" x2="7" y2="8"/>
                 <path d="M14,8V6c0-0.6,0.4-1,1-1h4c0.6,0,1,0.4,1,1v2"/>
                 <line x1="17" y1="23" x2="17" y2="12"/>
                 <line x1="21" y1="23" x2="21" y2="12"/>
                 <line x1="13" y1="23" x2="13" y2="12"/>
               </svg>
             </button>
          </td>
        `;
        tbody.appendChild(tr);
      });
      checkSelection();
    }

    let isAllChecked = false;
    function toggleCheckAll() {
      isAllChecked = !isAllChecked;
      document.querySelectorAll('.row-check').forEach(cb => cb.checked = isAllChecked);
      checkSelection();
    }

    function checkSelection() {
      const checked = document.querySelectorAll('.row-check:checked').length;
      const btn = document.getElementById('btn-del-sel');
      if (checked > 0) {
        btn.style.display = 'inline-block';
        btn.innerHTML = `
          <svg viewBox="0 0 32 32" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-miterlimit="10" style="vertical-align: middle; margin-right: 4px; margin-bottom: 2px;">
            <path d="M23,27H11c-1.1,0-2-0.9-2-2V8h16v17C25,26.1,24.1,27,23,27z"/>
            <line x1="27" y1="8" x2="7" y2="8"/>
            <path d="M14,8V6c0-0.6,0.4-1,1-1h4c0.6,0,1,0.4,1,1v2"/>
            <line x1="17" y1="23" x2="17" y2="12"/>
            <line x1="21" y1="23" x2="21" y2="12"/>
            <line x1="13" y1="23" x2="13" y2="12"/>
          </svg> Hapus Terpilih (${checked})`;
      } else {
        btn.style.display = 'none';
      }
    }

    async function deleteSingle(id) {
      if(!confirm("Yakin hapus data ini?")) return;
      await fetch('api/history.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=delete&ids[]=${id}`
      });
      loadData();
    }

    async function deleteSelected() {
      const checked = Array.from(document.querySelectorAll('.row-check:checked')).map(cb => cb.value);
      if(checked.length === 0) return;
      if(!confirm(`Yakin hapus ${checked.length} data terpilih?`)) return;
      
      const body = new URLSearchParams();
      body.append('action', 'delete');
      checked.forEach(id => body.append('ids[]', id));
      
      await fetch('api/history.php', {
        method: 'POST',
        body: body
      });
      loadData();
    }

    async function deleteAll() {
      if(!confirm("YAKIN HAPUS SEMUA RIWAYAT? Data tidak bisa dikembalikan!")) return;
      await fetch('api/history.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=delete_all'
      });
      currentPage = 1;
      loadData();
    }

    async function checkState() {
        const res = await fetch('api/get_data.php');
        if(!res.ok) return;
        const json = await res.json();
        
        const isMeasuring = json.state && json.state.status === 'MENGUKUR';
        window.isMeasuringGlobal = isMeasuring;
        
        const banner = document.getElementById('floating-banner');
        const navMon = document.getElementById('nav-monitoring');
        const navPeng = document.getElementById('nav-pengukuran');
        
        if (isMeasuring) {
            banner.style.display = 'block';
            navMon.classList.add('disabled-link');
            navPeng.classList.add('disabled-link');
            const elapsed = Math.max(0, Math.floor((Date.now() / 1000) - json.state.start_time));
            const m = Math.floor(elapsed / 60).toString().padStart(2, '0');
            const s = (elapsed % 60).toString().padStart(2, '0');
            document.getElementById('banner-timer').textContent = `${m}:${s}`;
        } else {
            banner.style.display = 'none';
            navMon.classList.remove('disabled-link');
            navPeng.classList.remove('disabled-link');
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

    loadData();
    setInterval(checkState, 1000);
  </script>

  <!-- Modal Map -->
  <div id="map-modal" class="modal-overlay" style="display:none;">
    <div class="modal-content">
      <div class="modal-header">
        <h3 id="modal-title">Lokasi Pengukuran</h3>
        <button class="close-btn" onclick="closeMapModal()">✕</button>
      </div>
      <div id="modal-map-container" style="height: 300px; width: 100%; border-radius:8px;"></div>
    </div>
  </div>

  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
  <script>
    let modalMap = null;
    let modalMarker = null;

    function openMapModal(coordsStr, locName) {
        document.getElementById('map-modal').style.display = 'flex';
        document.getElementById('modal-title').textContent = locName ? `Lokasi: ${locName}` : 'Lokasi Pengukuran';
        
        const [latStr, lngStr] = coordsStr.split(',');
        const lat = parseFloat(latStr);
        const lng = parseFloat(lngStr);

        if(!modalMap) {
            modalMap = L.map('modal-map-container').setView([lat, lng], 15);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(modalMap);
            modalMarker = L.marker([lat, lng]).addTo(modalMap);
        } else {
            modalMap.setView([lat, lng], 15);
            modalMarker.setLatLng([lat, lng]);
            setTimeout(() => { modalMap.invalidateSize(); }, 200);
        }
    }

    function closeMapModal() {
        document.getElementById('map-modal').style.display = 'none';
    }

    async function openLogModal(sessionId, waktu) {
        document.getElementById('log-modal').style.display = 'flex';
        document.getElementById('log-modal-title').textContent = `Detail Log: ${waktu}`;
        const tbody = document.getElementById('log-table-body');
        tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding: 1rem;">Memuat data...</td></tr>';
        
        try {
            const res = await fetch(`api/get_logs.php?session_id=${sessionId}`);
            const logs = await res.json();
            
            if (logs.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding: 1rem; color: var(--text-secondary);">Tidak ada detail log untuk sesi ini (mungkin pengukuran terlalu singkat).</td></tr>';
                return;
            }
            
            tbody.innerHTML = logs.map(log => `
                <tr style="border-bottom: 1px solid var(--border-color);">
                    <td style="padding: 0.5rem; font-family: monospace;">${log.waktu_fmt}</td>
                    <td style="padding: 0.5rem;">${log.suhu}</td>
                    <td style="padding: 0.5rem;">${log.ph}</td>
                    <td style="padding: 0.5rem;">${log.turbidity}</td>
                </tr>
            `).join('');
        } catch (e) {
            tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding: 1rem; color: red;">Gagal memuat log.</td></tr>';
        }
    }

    function closeLogModal() {
        document.getElementById('log-modal').style.display = 'none';
    }
  </script>

  <!-- Modal Log -->
  <div id="log-modal" class="modal-overlay" style="display:none; z-index: 9999;">
    <div class="modal-content" style="max-width: 600px; width: 90%;">
      <div class="modal-header">
        <h3 id="log-modal-title">Detail Log Pengukuran</h3>
        <button class="close-btn" onclick="closeLogModal()">✕</button>
      </div>
      <div style="max-height: 50vh; overflow-y: auto; margin-top: 1rem; background: var(--bg-main); border-radius: 8px; border: 1px solid var(--border-color);">
        <table style="width: 100%; border-collapse: collapse; text-align: left;">
          <thead style="position: sticky; top: 0; z-index: 10;">
            <tr>
              <th style="padding: 0.75rem; color: var(--text-primary); background: var(--bg-main); border-bottom: 2px solid var(--border-color);">Waktu</th>
              <th style="padding: 0.75rem; color: var(--text-primary); background: var(--bg-main); border-bottom: 2px solid var(--border-color);">Suhu (°C)</th>
              <th style="padding: 0.75rem; color: var(--text-primary); background: var(--bg-main); border-bottom: 2px solid var(--border-color);">pH</th>
              <th style="padding: 0.75rem; color: var(--text-primary); background: var(--bg-main); border-bottom: 2px solid var(--border-color);">Kekeruhan</th>
            </tr>
          </thead>
          <tbody id="log-table-body" style="color: var(--text-primary);">
            <!-- loaded via JS -->
          </tbody>
        </table>
      </div>
    </div>
  </div>
</body>
</html>
