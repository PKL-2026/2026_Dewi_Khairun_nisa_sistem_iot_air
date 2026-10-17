<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    die(json_encode(["error" => "Unauthorized"]));
}
require_once '../db.php';

$action = $_POST['action'] ?? '';

if ($action === 'delete_all') {
    $conn->query("TRUNCATE TABLE sensor_data");
    echo json_encode(["status" => "ok"]);
    exit;
}

if ($action === 'delete') {
    $ids = $_POST['ids'] ?? [];
    if (!empty($ids) && is_array($ids)) {
        // Sanitize IDs
        $ids = array_map('intval', $ids);
        $id_str = implode(',', $ids);
        $conn->query("DELETE FROM sensor_data WHERE id IN ($id_str)");
    }
    echo json_encode(["status" => "ok"]);
    exit;
}

// GET request for searching / loading
$q = $_GET['q'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if($page < 1) $page = 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$where = "";
if ($q !== '') {
    $q_esc = $conn->real_escape_string($q);
    $where = "WHERE waktu LIKE '%$q_esc%' 
              OR suhu LIKE '%$q_esc%' 
              OR ph LIKE '%$q_esc%' 
              OR turbidity LIKE '%$q_esc%' 
              OR lokasi LIKE '%$q_esc%' 
              OR koordinat LIKE '%$q_esc%' 
              OR durasi LIKE '%$q_esc%'";
}

$sql = "SELECT * FROM sensor_data $where ORDER BY id DESC LIMIT $limit OFFSET $offset";
$res = $conn->query($sql);

$data = [];
if ($res) {
    while($row = $res->fetch_assoc()) {
        $row['waktu_fmt'] = date("d M Y, H:i:s", strtotime($row['waktu']));
        $data[] = $row;
    }
}

// Get total count for pagination
$total_sql = "SELECT COUNT(id) as total FROM sensor_data $where";
$total_res = $conn->query($total_sql);
$total_row = $total_res->fetch_assoc();
$total_items = (int)$total_row['total'];
$total_pages = ceil($total_items / $limit);

echo json_encode([
    "data" => $data,
    "pagination" => [
        "current_page" => $page,
        "total_pages" => $total_pages,
        "total_items" => $total_items
    ]
]);
$conn->close();
?>
