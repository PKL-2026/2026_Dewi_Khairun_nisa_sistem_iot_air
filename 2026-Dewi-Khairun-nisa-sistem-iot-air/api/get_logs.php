<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    die(json_encode(["error" => "Unauthorized"]));
}
require_once '../db.php';

$session_id = $_GET['session_id'] ?? '';

if (empty($session_id)) {
    echo json_encode([]);
    exit;
}

$session_id = $conn->real_escape_string($session_id);
$result = $conn->query("SELECT suhu, ph, turbidity, DATE_FORMAT(waktu, '%H:%i:%s') as waktu_fmt FROM sensor_logs WHERE session_id = '$session_id' ORDER BY id DESC");

$logs = [];
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $logs[] = $row;
    }
}

echo json_encode($logs);
?>
