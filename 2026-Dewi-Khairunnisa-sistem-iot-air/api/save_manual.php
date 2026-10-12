<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    die(json_encode(["error" => "Unauthorized"]));
}
require_once '../db.php';

$latest_file = 'latest.json';
$state_file = 'state.json';

if (file_exists($latest_file) && file_exists($state_file)) {
    $latest = json_decode(file_get_contents($latest_file), true);
    $state = json_decode(file_get_contents($state_file), true);

    $suhu = $latest['suhu'] ?? 0;
    $ph = $latest['ph'] ?? 0;
    $turb = $latest['turbidity'] ?? 0;
    $lokasi = $conn->real_escape_string($state['location_name'] ?? '-');
    $koordinat = $conn->real_escape_string($state['coords'] ?? '-');
    $durasi = isset($_POST['durasi']) ? $conn->real_escape_string($_POST['durasi']) : '-';

    $session_id = $conn->real_escape_string($state['session_id'] ?? '');

    $sql = "INSERT INTO sensor_data (suhu, ph, turbidity, lokasi, koordinat, durasi, session_id) 
            VALUES ($suhu, $ph, $turb, '$lokasi', '$koordinat', '$durasi', '$session_id')";
    
    if ($conn->query($sql) === TRUE) {
        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["success" => false, "error" => $conn->error]);
    }
} else {
    echo json_encode(["success" => false, "error" => "Data not found"]);
}
?>
