<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    die(json_encode(["error" => "Unauthorized"]));
}

$state_file = 'state.json';
$action = $_POST['action'] ?? '';

if ($action === 'start') {
    $loc = $_POST['lokasi'] ?? '';
    $coord = $_POST['koordinat'] ?? '';
    $state = [
        "status" => "MENGUKUR",
        "start_time" => time(),
        "location_name" => $loc,
        "coords" => $coord,
        "session_id" => uniqid('meas_')
    ];
    file_put_contents($state_file, json_encode($state));
    echo json_encode(["success" => true]);
} 
else if ($action === 'stop') {
    $state = json_decode(file_get_contents($state_file), true);
    $state['status'] = "STANDBY";
    file_put_contents($state_file, json_encode($state));
    echo json_encode(["success" => true]);
}
?>
