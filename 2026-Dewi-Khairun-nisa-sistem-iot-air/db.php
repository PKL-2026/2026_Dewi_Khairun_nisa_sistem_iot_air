<?php
$servername = "db"; // Docker service name
$username = "esp32";
$password = "esp32password";
$dbname = "iot_air";

// Create connection
$conn = @new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    if (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status' => 'error',
            'message' => 'Koneksi database gagal! Silakan cek file db.php.'
        ]);
        exit;
    }
    
    // Tampilan pesan error menarik jika dibuka di browser
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Koneksi Database Belum Dikonfigurasi</title>
      <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
      <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
          font-family: 'Inter', sans-serif;
          background: #f0fdfa;
          color: #1e293b;
          min-height: 100vh;
          display: flex;
          align-items: center;
          justify-content: center;
          padding: 1.5rem;
        }
        .error-card {
          background: #ffffff;
          max-width: 520px;
          width: 100%;
          border-radius: 20px;
          padding: 2.2rem;
          box-shadow: 0 20px 40px rgba(13, 148, 136, 0.1);
          border: 1px solid #ccfbf1;
          text-align: center;
        }
        .icon-bubble {
          width: 72px;
          height: 72px;
          background: #fee2e2;
          color: #ef4444;
          border-radius: 50%;
          display: flex;
          align-items: center;
          justify-content: center;
          font-size: 2rem;
          margin: 0 auto 1.2rem;
        }
        h2 {
          font-family: 'Outfit', sans-serif;
          color: #0f172a;
          font-size: 1.45rem;
          margin-bottom: 0.6rem;
        }
        p {
          font-size: 0.95rem;
          color: #64748b;
          line-height: 1.5;
          margin-bottom: 1.4rem;
        }
        .tips-box {
          background: #f8fafc;
          border: 1px dashed #cbd5e1;
          border-radius: 12px;
          padding: 1rem;
          text-align: left;
          font-size: 0.88rem;
          color: #334155;
          margin-bottom: 1.2rem;
        }
        .tips-box ol { padding-left: 1.2rem; margin-top: 0.4rem; }
        .tips-box li { margin-bottom: 0.35rem; }
        .badge {
          display: inline-block;
          background: #fef3c7;
          color: #b45309;
          font-weight: 600;
          font-size: 0.78rem;
          padding: 0.25rem 0.65rem;
          border-radius: 6px;
          margin-bottom: 0.4rem;
        }
        .code-snippet {
          font-family: monospace;
          background: #e2e8f0;
          padding: 0.15rem 0.4rem;
          border-radius: 4px;
          color: #0f172a;
        }
      </style>
    </head>
    <body>
      <div class="error-card">
        <div class="icon-bubble">🔌</div>
        <span class="badge">Eits, Database Belum Nyambung!</span>
        <h2>Pengaturan Database Perlu Diubah</h2>
        <p>Sepertinya Anda baru saja meng-upload web ke hosting cPanel tapi lupa mengubah informasi koneksi di file <span class="code-snippet">db.php</span>.</p>
        
        <div class="tips-box">
          <strong>Langkah Mudah Memperbaikinya:</strong>
          <ol>
            <li>Buka <strong>File Manager</strong> di cPanel Anda.</li>
            <li>Klik kanan pada file <span class="code-snippet">db.php</span> ➔ pilih <strong>Edit</strong>.</li>
            <li>Ubah <span class="code-snippet">$servername</span> menjadi <code>"localhost"</code>.</li>
            <li>Isi nama database, username, dan password sesuai database cPanel Anda.</li>
            <li>Simpan perubahan dan muat ulang halaman ini.</li>
          </ol>
        </div>

        <small style="color: #94a3b8; font-size: 0.8rem;">
          Pesan Teknis: <?php echo htmlspecialchars($conn->connect_error); ?>
        </small>
      </div>
    </body>
    </html>
    <?php
    exit;
}

// Set timezone
date_default_timezone_set('Asia/Jakarta');
$conn->query("SET time_zone = '+07:00'");

// Create table if not exists
$table_sql = "CREATE TABLE IF NOT EXISTS sensor_data (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    suhu FLOAT NOT NULL,
    ph FLOAT NOT NULL,
    turbidity FLOAT NOT NULL,
    waktu TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

$conn->query($table_sql);

// Add lokasi and koordinat columns if they don't exist
$col_check = $conn->query("SHOW COLUMNS FROM sensor_data LIKE 'lokasi'");
if ($col_check->num_rows == 0) {
    $conn->query("ALTER TABLE sensor_data ADD COLUMN lokasi VARCHAR(255) DEFAULT '-'");
}
$col_check = $conn->query("SHOW COLUMNS FROM sensor_data LIKE 'koordinat'");
if ($col_check->num_rows == 0) {
    $conn->query("ALTER TABLE sensor_data ADD COLUMN koordinat VARCHAR(255) DEFAULT '-'");
}
$col_check = $conn->query("SHOW COLUMNS FROM sensor_data LIKE 'durasi'");
if ($col_check->num_rows == 0) {
    $conn->query("ALTER TABLE sensor_data ADD COLUMN durasi VARCHAR(50) DEFAULT '-'");
}
$col_check = $conn->query("SHOW COLUMNS FROM sensor_data LIKE 'session_id'");
if ($col_check->num_rows == 0) {
    $conn->query("ALTER TABLE sensor_data ADD COLUMN session_id VARCHAR(50) DEFAULT NULL");
}

// Table for detailed logs
$conn->query("CREATE TABLE IF NOT EXISTS sensor_logs (
    id INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(50) NOT NULL,
    suhu FLOAT NOT NULL,
    ph FLOAT NOT NULL,
    turbidity FLOAT NOT NULL,
    waktu TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");


// Table for settings
$conn->query("CREATE TABLE IF NOT EXISTS pengaturan (
    config_key VARCHAR(50) PRIMARY KEY,
    config_value VARCHAR(255) NOT NULL
)");
$conn->query("INSERT IGNORE INTO pengaturan (config_key, config_value) VALUES ('app_name', 'AquaMonitor')");
?>
