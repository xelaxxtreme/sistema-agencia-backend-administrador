<?php
require_once '../config/db.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    try {
        $sql = "SELECT * FROM reservas ORDER BY fecha_registro DESC";
        $result = $conn->query($sql);

        $reservas = [];

        while ($row = $result->fetch_assoc()) {
            $reservas[] = $row;
        }

        echo json_encode($reservas);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => "Error al obtener reservas"]);
    }
    exit;
}

http_response_code(405);
echo json_encode(["error" => "Método no permitido"]);
?>