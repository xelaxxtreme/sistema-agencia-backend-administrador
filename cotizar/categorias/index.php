<?php
require_once '../../config/db.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
switch ($method) {
    case 'GET':
        if (isset($_GET['idCategoria'])) {
            $idCategoria = intval($_GET['idCategoria']);
            $stmt = $conn->prepare("SELECT * FROM categoria WHERE idCategoria = ?");
            $stmt->bind_param("i", $idCategoria);
            $stmt->execute();
            $result = $stmt->get_result();
            echo json_encode($result->fetch_assoc(), JSON_UNESCAPED_UNICODE);
            $stmt->close();
        } else {
            $result = $conn->query("SELECT * FROM categoria");
            $rows = $result->fetch_all(MYSQLI_ASSOC);
            echo json_encode($rows, JSON_UNESCAPED_UNICODE);
        }
        break;
        case 'POST':
        // Crear usuario
        $input = json_decode(file_get_contents("php://input"), true);
        $nombre     = $input['nombre'] ?? '';

        $stmt = $conn->prepare("INSERT INTO categoria (nombre) VALUES (?)");
        $stmt->bind_param("s", $nombre);

        if ($stmt->execute()) {
            echo json_encode(["success" => true, "id" => $stmt->insert_id]);
        } else {
            http_response_code(500);
            echo json_encode(["success" => false, "error" => $stmt->error]);
        }
        $stmt->close();
        break;

        case 'PUT':
        // Leer el body como JSON
        $data = json_decode(file_get_contents("php://input"), true);
        if (!isset($data['idCategoria'])) {
            http_response_code(400);
            echo json_encode(["error" => "ID es requerido"]);
            exit;
        }
        $idCategoria = intval($data['idCategoria']);
        $nombre = $data['nombre'] ?? '';
        $stmt = $conn->prepare("UPDATE categoria SET nombre = ? WHERE idCategoria = ?");
        $stmt->bind_param("si", $nombre, $idCategoria);

        if ($stmt->execute()) {
            echo json_encode(["success" => true]);
        } else {
            http_response_code(500);
            echo json_encode(["success" => false, "error" => $stmt->error]);
        }
        $stmt->close();
        break;

        case 'DELETE':
        if (!isset($_GET['idCategoria'])) {
            http_response_code(400);
            echo json_encode(["error" => "ID es requerido"]);
            exit;
        }
        $idCategoria = intval($_GET['idCategoria']);
        $stmt = $conn->prepare("DELETE FROM categoria WHERE idCategoria = ?");
        $stmt->bind_param("i", $idCategoria);
        if ($stmt->execute()) {
            echo json_encode(["success" => true]);
        } else {
            http_response_code(500);
            echo json_encode(["success" => false, "error" => $stmt->error]);
        }
        $stmt->close();
        break;
        default:
        http_response_code(405);
        echo json_encode(["error" => "Método no soportado"]);
}