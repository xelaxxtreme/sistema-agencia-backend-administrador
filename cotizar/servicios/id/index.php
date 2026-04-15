<?php
require_once '../../../config/db.php';
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

$method = $_SERVER['REQUEST_METHOD'];
$overrideMethod = $_POST['_method'] ?? ($_GET['_method'] ?? null);
if ($overrideMethod) {
    $method = strtoupper($overrideMethod);
}

$idServicio = $_GET['idServicio'] ?? null;

if ($method === 'GET') {
    try {
        if (!$idServicio || !is_numeric($idServicio)) {
            http_response_code(400);
            echo json_encode(['error' => 'Falta o es inválido el parámetro idServicio']);
            exit;
        }

        $sql = "SELECT * FROM servicio WHERE idServicio = ?";
        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            throw new Exception("Error en la preparación de la consulta");
        }

        $stmt->bind_param("i", $idServicio);
        $stmt->execute();
        $result = $stmt->get_result();
        $servicio = $result->fetch_assoc();

        if ($servicio) {
            // Decodificar teléfono si es JSON válido
            if (!empty($servicio['telefono'])) {
                $decodedTelefono = json_decode($servicio['telefono'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $servicio['telefono'] = $decodedTelefono;
                }
            }

            // Obtener características
            $stmtCaracteristicas = $conn->prepare("SELECT * FROM caracteristicasServicio WHERE idServicio = ?");
            if (!$stmtCaracteristicas) {
                throw new Exception("Error en la preparación de la consulta de características");
            }
            $stmtCaracteristicas->bind_param("i", $idServicio);
            $stmtCaracteristicas->execute();
            $resultCaracteristicas = $stmtCaracteristicas->get_result();
            $servicio['caracteristicas'] = $resultCaracteristicas->fetch_all(MYSQLI_ASSOC);

            echo json_encode($servicio, JSON_UNESCAPED_UNICODE);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Servicio no encontrado']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => "Error al obtener servicio", "detalle" => $e->getMessage()]);
    }
    exit;
}
if($method === 'POST'){
    try {
        $documentsPath = __DIR__ . "/../../../directorioDocumentos/";
        if (!file_exists($documentsPath)) mkdir($documentsPath, 0777, true);
        
        $nombre = $_POST['nombre'] ?? '';
        $clasificacion = $_POST['clasificacion'] ?? '';
        $ubicacion = $_POST['ubicacion'] ?? '';
        $telefono = $_POST['telefono'] ?? '';

        $rutaArchivoFinal = null;
        if (!empty($_FILES["documento"]["name"])) {
            $safeName = time() . "-" . preg_replace("/\s+/", "_", $_FILES["documento"]["name"]);
            $destino = $documentsPath . $safeName;
            if (move_uploaded_file($_FILES["documento"]["tmp_name"], $destino)) {
                $rutaArchivoFinal = $safeName;
            }
        }

        if ($rutaArchivoFinal === null) {
            $stmt = $conn->prepare("INSERT INTO servicio (nombre, clasificacion, ubicacion, telefono) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $nombre, $clasificacion, $ubicacion, $telefono);
        } else {
            $stmt = $conn->prepare("INSERT INTO servicio (nombre, clasificacion, ubicacion, telefono, documento) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $nombre, $clasificacion, $ubicacion, $telefono, $rutaArchivoFinal);
        }

        if (!$stmt->execute()) {
            throw new Exception($stmt->error);
        }

        echo json_encode(["message" => "Servicio creado exitosamente"]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => "Error al crear servicio", "detalle" => $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Método no permitido'], JSON_UNESCAPED_UNICODE);
?>