<?php
require_once '../config/db.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

try {
    $data = json_decode(file_get_contents("php://input"), true);
    $slug = $data['slug'] ?? '';

    if (!$slug) {
        http_response_code(400);
        echo json_encode(['error' => 'Slug requerido']);
        exit;
    }

    $stmt = $conn->prepare("SELECT id FROM tours WHERE slug = ? LIMIT 1");
    $stmt->bind_param("s", $slug);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result->num_rows > 0;

    echo json_encode(['exists' => $exists]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor']);
}
?>