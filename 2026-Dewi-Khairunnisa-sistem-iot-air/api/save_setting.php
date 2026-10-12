<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    die(json_encode(["error" => "Unauthorized"]));
}
require_once '../db.php';

$name = $_POST['name'] ?? '';
if (trim($name) === '') {
    $name = 'AquaMonitor';
}

$stmt = $conn->prepare("UPDATE pengaturan SET config_value = ? WHERE config_key = 'app_name'");
$stmt->bind_param("s", $name);
$stmt->execute();

echo json_encode(["status" => "ok", "name" => $name]);
$conn->close();
?>
