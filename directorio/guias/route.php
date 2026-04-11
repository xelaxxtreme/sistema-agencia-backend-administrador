<?php
require_once '../../config/db.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    try {
        $sql = "SELECT * FROM guias";
        $result = $conn->query($sql);

        $guias = [];

        while ($row = $result->fetch_assoc()) {
            $row['numero'] = json_decode($row['numero'], true);
            $row['detalles'] = json_decode($row['detalles'], true);
            $guias[] = $row;
        }

        echo json_encode($guias);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => "Error al obtener guías"]);
    }
    exit;
}

if ($method === 'POST') {
    try {
        $documentsPath = __DIR__ . "/../../../directorioDocumentos/";
        if (!file_exists($documentsPath)) mkdir($documentsPath, 0777, true);

        $nombre   = $_POST['nombre'] ?? '';
        $lugar    = $_POST['lugar'] ?? '';
        $creado   = $_POST['creado'] ?? date('Y-m-d');
        $numero   = $_POST['numero'] ?? '[]'; 
        $detalles = $_POST['detalles'] ?? '[]';
        
        $rutaArchivoFinal = null;
        if (!empty($_FILES["documento"]["name"])) {
            $safeName = time() . "-" . preg_replace("/\s+/", "_", $_FILES["documento"]["name"]);
            $destino = $documentsPath . $safeName;
            if (move_uploaded_file($_FILES["documento"]["tmp_name"], $destino)) {
                $rutaArchivoFinal = $safeName;
            }
        }
        if ($rutaArchivoFinal === null) {
            $stmt = $conn->prepare("INSERT INTO guias (nombre, numero, lugar, detalles, creado) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $nombre, $numero, $lugar, $detalles, $creado);
        } else {
            $stmt = $conn->prepare("INSERT INTO guias (nombre, numero, lugar, detalles, creado, documento) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssss", $nombre, $numero, $lugar, $detalles, $creado, $rutaArchivoFinal);
        }

        if (!$stmt->execute()) {
            throw new Exception($stmt->error);
        }

        $insertedId = $stmt->insert_id;
        http_response_code(201);
        echo json_encode([
            "id" => $insertedId,
            "nombre" => $nombre,
            "numero" => json_decode($numero, true),
            "lugar" => $lugar,
            "detalles" => json_decode($detalles, true),
            "creado" => $creado,
            "documento" => $rutaArchivoFinal
        ]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                "error" => "Error al crear guía",
                "details" => $e->getMessage() // Útil para depurar
            ]);
        }
    exit;
}

http_response_code(405);
echo json_encode(["error" => "Método no permitido"]);
?>