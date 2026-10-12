<?php
$servername = "db"; // Docker service name
$username = "esp32";
$password = "esp32password";
$dbname = "database_2026_dewi_khairunnisa_iot_air";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
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
