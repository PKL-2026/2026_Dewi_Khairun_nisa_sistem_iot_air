<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    die(json_encode(["error" => "Unauthorized"]));
}
require_once '../db.php';
header('Content-Type: application/json');

$latest_file = 'latest.json';
$state_file = 'state.json';

// Get state
$state = ["status" => "STANDBY", "start_time" => 0];
if (file_exists($state_file)) {
    $state = json_decode(file_get_contents($state_file), true);
}

// Get latest
$latest = null;
$seconds_ago = 9999;
if (file_exists($latest_file)) {
    $l = json_decode(file_get_contents($latest_file), true);
    if ($l) {
        $latest = [
            "suhu" => round($l['suhu'], 2),
            "ph" => round($l['ph'], 2),
            "turbidity" => round($l['turbidity'], 0),
            "waktu" => date("d M Y, H:i:s", strtotime($l['waktu'])) . " WIB"
        ];
        $seconds_ago = time() - strtotime($l['waktu']);
    }
}

// Get history for charts (last 20 from DB)
$sql_hist = "SELECT * FROM (SELECT * FROM sensor_data ORDER BY id DESC LIMIT 20) sub ORDER BY id ASC";
$res_hist = $conn->query($sql_hist);
$history = [];
if ($res_hist && $res_hist->num_rows > 0) {
    while($r = $res_hist->fetch_assoc()) {
        $history[] = [
            "suhu" => round($r['suhu'], 2),
            "ph" => round($r['ph'], 2),
            "turbidity" => round($r['turbidity'], 0),
            "time" => date("H:i:s", strtotime($r['waktu']))
        ];
    }
}

echo json_encode([
    "latest" => $latest,
    "history" => $history,
    "online" => $seconds_ago <= 15,
    "state" => $state
]);
$conn->close();
?>
