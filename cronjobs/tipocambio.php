<?php
require_once '../config/db.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if($method === 'GET'){
    $stmt = $conn->prepare("SELECT * FROM tipocambio");
    $stmt->execute();
    $result = $stmt->get_result();
    echo json_encode($result->fetch_assoc(), JSON_UNESCAPED_UNICODE);
    $stmt->close();
} else {
    http_response_code(405);
    echo json_encode(["error" => "Método no permitido"]);
}
?>