<?php
require_once '../../config/db.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    try {
        $sql = "SELECT * FROM entradas";
        $result = $conn->query($sql);

        $entradas = [];

        while ($row = $result->fetch_assoc()) {
            $row['detalles'] = json_decode($row['detalles'], true);
            $entradas[] = $row;
        }

        echo json_encode($entradas);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => "Error al obtener entradas"]);
    }
    exit;
}

if ($method === 'POST') {
    try {
        $data = json_decode(file_get_contents("php://input"), true);

        $nombre = $data['nombre'] ?? '';
        $lugar = $data['lugar'] ?? '';
        $detalles = json_encode($data['detalles'] ?? []);
        $creado = $data['creado'] ?? date('Y-m-d');

        $stmt = $conn->prepare("INSERT INTO entradas (nombre, lugar, detalles, creado) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $nombre, $lugar, $detalles, $creado);
        $stmt->execute();

        $insertedId = $stmt->insert_id;

        http_response_code(201);
        echo json_encode([
            "id" => $insertedId,
            "nombre" => $nombre,
            "lugar" => $lugar,
            "detalles" => json_decode($detalles, true),
            "creado" => $creado
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => "Error al crear entrada"]);
    }
    exit;
}

http_response_code(405);
echo json_encode(["error" => "Método no permitido"]);
?>