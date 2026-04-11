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

switch($method){
    case 'GET':
        if(isset($_GET['id'])){
            $id = intval($_GET['id']);
            $stmt = $conn->prepare("SELECT * FROM popups WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            echo json_encode($result->fetch_assoc(), JSON_UNESCAPED_UNICODE);
            $stmt->close();
        } else {
            $lang = $_GET['lang'] ?? 'es';
            $stmt = $conn->prepare("SELECT * FROM popups WHERE lang = ?");
            $stmt->bind_param("s", $lang);
            $stmt->execute();
            $result = $stmt->get_result();
            $data = $result->fetch_all(MYSQLI_ASSOC);
            echo json_encode($data, JSON_UNESCAPED_UNICODE);
            $stmt->close();
        }
        break;

    case 'POST':
        $uploadsPath = __DIR__ . "/../../../storage.terresdesincas.com/public/popups/";
        if (!file_exists($uploadsPath)) mkdir($uploadsPath, 0777, true);
        $nombre = $_POST['nombre'] ?? '';
        $tipo   = $_POST['tipo'] ?? '';
        $estado = $_POST['estado'] ?? 1;
        $url_destino = $_POST['url_destino'] ?? '';
        $lang = $_POST['lang'] ?? '';
        
        $media_url = null;
        if($tipo === 'imagen' && !empty($_FILES["media_url"]["name"])){
            $safeName = preg_replace("/\s+/", "_", $_FILES["media_url"]["name"]);
            $destino = $uploadsPath . $safeName;
            if (move_uploaded_file($_FILES["media_url"]["tmp_name"], $destino)) {
                $media_url = $safeName;
            }
        } else{
            $media_url = $_POST['media_url'] ?? '';
        }
        $stmt = $conn->prepare("INSERT INTO popups (nombre, tipo, estado, url_destino, lang, media_url) VALUES (?,?,?,?,?,?)");
        $stmt->bind_param("ssisss", $nombre, $tipo, $estado, $url_destino, $lang, $media_url);


        if ($stmt->execute()) {
            echo json_encode(["success" => true, "id" => $stmt->insert_id]);
        } else {
            http_response_code(500);
            echo json_encode(["success" => false, "error" => $stmt->error]);
        }

        $stmt->close();
        break;
    case 'PUT':
        $id = intval($_GET['id'] ?? 0);
        if (!$id) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "ID requerido"]);
            break;
        }
        $uploadsPath = __DIR__ . "/../../../storage.terresdesincas.com/public/popups/";
        $nombre      = $_POST['nombre'] ?? '';
        $tipo        = $_POST['tipo'] ?? '';
        $estado      = $_POST['estado'] ?? 1;
        $url_destino = $_POST['url_destino'] ?? '';
        $lang        = $_POST['lang'] ?? '';
        $media_url   = $_POST['media_url'] ?? '';
        
        $media_url = null;

        $oldData = $conn->query("SELECT media_url, tipo FROM popups WHERE id = $id")->fetch_assoc();
        if($oldData && $oldData['tipo'] === 'imagen'){
            $oldPath = $uploadsPath . $oldData['media_url'];
                if (file_exists($oldPath)) unlink($oldPath);
        }


        if($tipo === 'imagen' && !empty($_FILES["media_url"]["name"])){
            $safeName = preg_replace("/\s+/", "_", $_FILES["media_url"]["name"]);
            $destino = $uploadsPath . $safeName;
            if (move_uploaded_file($_FILES["media_url"]["tmp_name"], $destino)) {
                $media_url = $safeName;
            }
        } else{
            $media_url = $data['media_url'] ?? '';
        }
        $stmt = $conn->prepare("UPDATE popups SET nombre=?, tipo=?, estado=?, url_destino=?, lang=?, media_url=? WHERE id=?");
        $stmt->bind_param("sssissi", $nombre, $tipo, $estado, $url_destino, $lang, $media_url, $id);
        $success = $stmt->execute();
        echo json_encode(["success" => $success]);
        $stmt->close();

        break;
    case 'DELETE':
        $id = intval($_GET['id']);

        $oldData = $conn->query("SELECT media_url, tipo FROM popups WHERE id = $id")->fetch_assoc();
        if($oldData && $oldData['tipo'] === 'imagen'){
            $oldPath = $uploadsPath . $oldData['media_url'];
                if (file_exists($oldPath)) unlink($oldPath);
        }

        $stmt = $conn->prepare("DELETE FROM popups WHERE id = ?");
        $stmt->bind_param("i", $id);
        $success = $stmt->execute();
        echo json_encode(["success" => $success]);
        $stmt->close();
        break;
    default:
        http_response_code(405);
        echo json_encode(["error" => "Method not allowed"]);
}
?>