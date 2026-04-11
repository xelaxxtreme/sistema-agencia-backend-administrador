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

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            // Obtener registro por ID
            $id = intval($_GET['id']);
            $stmt = $conn->prepare("SELECT * FROM comentarios WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            echo json_encode($result->fetch_assoc(), JSON_UNESCAPED_UNICODE);
            $stmt->close();
        } else {
            // Listar todos
            $tipo = $_GET['tipo'] ?? null;
            if ($tipo) {
                $stmt = $conn->prepare("SELECT * FROM comentarios WHERE tipo = ?");
                $stmt->bind_param("s", $tipo);
                $stmt->execute();
                $result = $stmt->get_result();
                echo json_encode($result->fetch_all(MYSQLI_ASSOC), JSON_UNESCAPED_UNICODE);
                $stmt->close();
            } else {
            $result = $conn->query("SELECT * FROM comentarios");
            $rows = $result->fetch_all(MYSQLI_ASSOC);
            echo json_encode($rows, JSON_UNESCAPED_UNICODE);
        }
    }
        break;

    case 'POST':
        // Crear registro
        $input = json_decode(file_get_contents("php://input"), true);
        $nombre     = $input['nombre'] ?? null;
        $puntaje    = $input['puntaje'] ?? 5;
        $fecha      = $input['fecha'] ?? null;
        $comentario = $input['comentario'] ?? null;
        $titulo     = $input['titulo'] ?? null;
        $grupo      = $input['grupo'] ?? null;
        $pais       = $input['pais'] ?? null;
        $tipo       = $input['tipo'] ?? null;

        $stmt = $conn->prepare("INSERT INTO comentarios (nombre, puntaje, fecha, comentario, titulo, grupo, pais, tipo) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->bind_param("sissssss", $nombre, $puntaje, $fecha, $comentario, $titulo, $grupo, $pais, $tipo);

        if ($stmt->execute()) {
            $new_id = $stmt->insert_id;
            echo json_encode(["success" => true, "id" => $new_id]);
        } else {
            http_response_code(500);
            echo json_encode(["success" => false, "error" => $stmt->error]);
        }
        $stmt->close();

        break;

    case 'PUT':
        // Actualizar registro
        $input = json_decode(file_get_contents("php://input"), true);

        $id         = $input['id'] ?? null;
        $nombre     = $input['nombre'] ?? null;
        $puntaje    = $input['puntaje'] ?? 5;
        $fecha      = $input['fecha'] ?? null;
        $comentario = $input['comentario'] ?? null;
        $titulo     = $input['titulo'] ?? null;
        $grupo      = $input['grupo'] ?? null;
        $pais       = $input['pais'] ?? null;
        $tipo       = $input['tipo'] ?? null;

        if (!$id) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "Falta el ID para actualizar"]);
            break;
        }
        $stmt = $conn->prepare("UPDATE comentarios 
                                SET nombre=?, puntaje=?, fecha=?, comentario=?, titulo=?, grupo=?, pais=?, tipo=? 
                                WHERE id=?");
        $stmt->bind_param("sissssssi", $nombre, $puntaje, $fecha, $comentario, $titulo, $grupo, $pais, $tipo, $id);

        if ($stmt->execute()) {
            $stmt->close();

            echo json_encode(["success" => true, "id" => $id]);
        } else {
            http_response_code(500);
            echo json_encode(["success" => false, "error" => $stmt->error]);
            $stmt->close();
        }

        break;

    case 'DELETE':
        $input = json_decode(file_get_contents("php://input"), true);
        $id = $input['id'] ?? null;

        if (!$id) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "Falta el ID para eliminar"]);
            break;
        }

        $stmt = $conn->prepare("DELETE FROM comentarios WHERE id = ?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            echo json_encode(["success" => true, "id" => $id]);
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
?>