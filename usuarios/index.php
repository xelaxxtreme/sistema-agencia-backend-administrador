<?php
require_once '../config/db.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

// Manejo de preflight (CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            // Obtener usuario por ID
            $id = intval($_GET['id']);
            $stmt = $conn->prepare("SELECT id, email, nombre, apellidos, rol FROM usuarios WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            echo json_encode($result->fetch_assoc(), JSON_UNESCAPED_UNICODE);
            $stmt->close();
        } else {
            // Listar todos
            $result = $conn->query("SELECT id, email, nombre, apellidos, rol FROM usuarios");
            $rows = $result->fetch_all(MYSQLI_ASSOC);
            echo json_encode($rows, JSON_UNESCAPED_UNICODE);
        }
        break;

    case 'POST':
        // Crear usuario
        $input = json_decode(file_get_contents("php://input"), true);
     
        $email     = $input['email'];
        $password  = password_hash($input['password'], PASSWORD_DEFAULT);
        $nombre    = $input['nombre'] ?? '';
        $apellidos = $input['apellidos'] ?? '';
        $rol       = $input['rol'] ?? 'editor';

        $stmt = $conn->prepare("INSERT INTO usuarios (email, password, nombre, apellidos, rol) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $email, $password, $nombre, $apellidos, $rol);

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

    // Validar que llegaron datos y el ID
    if (!$data || !isset($_GET['id'])) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error"   => "Datos inválidos o falta el ID"
        ]);
        break;
    }

    $id        = intval($_GET['id']);
    $email     = $data['email']     ?? null;
    $nombre    = $data['nombre']    ?? null;
    $apellidos = $data['apellidos'] ?? null;
    $rol       = $data['rol']       ?? null;

    // Si se envía password, lo actualizamos
    if (!empty($data['password'])) {
        $password = password_hash($data['password'], PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE usuarios 
            SET email=?, password=?, nombre=?, apellidos=?, rol=? 
            WHERE id=?");
        if (!$stmt) {
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "error"   => $conn->error
            ]);
            break;
        }
        $stmt->bind_param("sssssi", $email, $password, $nombre, $apellidos, $rol, $id);
    } else {
        // Si no se envía password, no lo tocamos
        $stmt = $conn->prepare("UPDATE usuarios 
            SET email=?, nombre=?, apellidos=?, rol=? 
            WHERE id=?");
        if (!$stmt) {
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "error"   => $conn->error
            ]);
            break;
        }
        $stmt->bind_param("ssssi", $email, $nombre, $apellidos, $rol, $id);
    }

    $success = $stmt->execute();
    echo json_encode(["success" => $success]);
    $stmt->close();
    break;

    case 'DELETE':
        // Eliminar usuario
        $data = json_decode(file_get_contents("php://input"), true);

        if (!$data || !isset($data['id'])) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "Falta el ID"]);
            break;
        }

        $id = intval($data['id']);
        $stmt = $conn->prepare("DELETE FROM usuarios WHERE id=?");
        $stmt->bind_param("i", $id);

        $success = $stmt->execute();
        echo json_encode(["success" => $success]);
        $stmt->close();
        break;

    default:
        http_response_code(405);
        echo json_encode(["error" => "Método no soportado"]);
}