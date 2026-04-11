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
            // Obtener idioma por ID
            $id = intval($_GET['id']);
            $stmt = $conn->prepare("SELECT * FROM idioma WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            echo json_encode($result->fetch_assoc(), JSON_UNESCAPED_UNICODE);
            $stmt->close();
        } else {
            // Listar todos
            $result = $conn->query("SELECT * FROM idioma");
            $rows = $result->fetch_all(MYSQLI_ASSOC);
            echo json_encode($rows, JSON_UNESCAPED_UNICODE);
        }
        break;

    case 'POST':
        // Crear idioma
        $uploadsPath = __DIR__ . "/../../../storage.terresdesincas.com/public/banderas/";
        if (!file_exists($uploadsPath)) mkdir($uploadsPath, 0777, true);

        $lang   = $_POST['lang'] ?? '';
        $nombre = $_POST['nombre'] ?? '';

        // Imagen idioma
        $imagen = null;
        if (!empty($_FILES["imagen"]["name"])) {
            $safeName = preg_replace("/\s+/", "_", $_FILES["imagen"]["name"]);
            $destino = $uploadsPath . $safeName;
            if (move_uploaded_file($_FILES["imagen"]["tmp_name"], $destino)) {
                $imagen = $safeName;
            }
        }

        $stmt = $conn->prepare("INSERT INTO idioma (lang, nombre, imagen) VALUES (?,?,?)");
        $stmt->bind_param("sss", $lang, $nombre, $imagen);

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
        $uploadsPath = __DIR__ . "/../../../storage.terresdesincas.com/public/banderas/";

        if (!$data || !isset($_GET['id'])) {
            http_response_code(400);
            echo json_encode([
                "success" => false,
                "error"   => "Datos inválidos o falta el ID"
            ]);
            break;
        }

        $id     = intval($_GET['id']);
        $lang   = $data['lang'] ?? null;
        $nombre = $data['nombre'] ?? null;
        $imagen = null;

        // Si viene archivo en PUT (normalmente se maneja distinto, pero lo dejo como ejemplo)
        if (!empty($_FILES['imagen']['name'])) {
            $old = $conn->query("SELECT imagen FROM idioma WHERE id = $id")->fetch_assoc();
            if ($old && $old['imagen']) {
                $oldPath = $uploadsPath . $old['imagen'];
                if (file_exists($oldPath)) unlink($oldPath);
            }

            $safeName = preg_replace('/\s+/', '_', $_FILES['imagen']['name']);
            move_uploaded_file($_FILES['imagen']['tmp_name'], $uploadsPath . $safeName);
            $imagen = $safeName;
        }

        $stmt = $conn->prepare("UPDATE idioma SET lang=?, nombre=?, imagen=? WHERE id=?");
        $stmt->bind_param("sssi", $lang, $nombre, $imagen, $id);

        $success = $stmt->execute();
        echo json_encode(["success" => $success]);
        $stmt->close();
        break;

    case 'DELETE':
        // Eliminar idioma
        $data = json_decode(file_get_contents("php://input"), true);

        if (!$data || !isset($data['id'])) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "Falta el ID"]);
            break;
        }

        $id = intval($data['id']);
        $uploadsPath = __DIR__ . "/../../../storage.terresdesincas.com/public/banderas/";

        $portada = $conn->query("SELECT imagen FROM idioma WHERE id = $id")->fetch_assoc();
        if ($portada && $portada['imagen']) {
            $filePath = $uploadsPath . $portada['imagen'];
            if (file_exists($filePath)) unlink($filePath);
        }

        $stmt = $conn->prepare("DELETE FROM idioma WHERE id = ?");
        $stmt->bind_param("i", $id);

        $success = $stmt->execute();
        echo json_encode(["success" => $success]);
        $stmt->close();
        break;

    default:
        http_response_code(405);
        echo json_encode(["error" => "Método no soportado"]);
}
?>