<?php
require_once '../db.php';
$latest_file = 'latest.json';
$state_file = 'state.json';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $suhu = isset($_POST['suhu']) ? floatval($_POST['suhu']) : 0;
    $ph = isset($_POST['ph']) ? floatval($_POST['ph']) : 0;
    $turbidity = isset($_POST['turbidity']) ? floatval($_POST['turbidity']) : 0;

    // Save to latest.json instead of DB
    $data = [
        "suhu" => $suhu,
        "ph" => $ph,
        "turbidity" => $turbidity,
        "waktu" => date("Y-m-d H:i:s")
    ];
    file_put_contents($latest_file, json_encode($data));

    // Respond to ESP32 with current state
    if (file_exists($state_file)) {
        $state = json_decode(file_get_contents($state_file), true);
        
        // Log to database if currently measuring
        if (($state['status'] ?? '') === 'MENGUKUR') {
            $session_id = $conn->real_escape_string($state['session_id'] ?? '');
            if ($session_id && $suhu != 0 && $ph != 0) {
                $sql_log = "INSERT INTO sensor_logs (session_id, suhu, ph, turbidity) VALUES ('$session_id', $suhu, $ph, $turbidity)";
                $conn->query($sql_log);
            }
        }
        
        echo "CMD:" . ($state['status'] ?? "STANDBY");
    } else {
        echo "CMD:STANDBY";
    }
}
?>
