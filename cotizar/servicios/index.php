<?php
require_once '../../config/db.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

$method = $_SERVER['REQUEST_METHOD'];
$overrideMethod = $_POST['_method'] ?? ($_GET['_method'] ?? null);
if ($overrideMethod) {
    $method = strtoupper($overrideMethod);
}

$idCategoria = $_GET['idCategoria'] ?? null;

if ($method === 'GET') {
    try {
        if (!$idCategoria || !is_numeric($idCategoria)) {
            http_response_code(400);
            echo json_encode(['error' => 'Falta o es inválido el parámetro idCategoria']);
            exit;
        }

        $sql = "SELECT * FROM servicio WHERE idCategoria = ?";
        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            throw new Exception("Error en la preparación de la consulta");
        }

        $stmt->bind_param("i", $idCategoria);
        $stmt->execute();
        $result = $stmt->get_result();
        $servicios = [];

        while ($row = $result->fetch_assoc()) {
            if (!empty($row['telefono'])) {
                $row['telefono'] = json_decode($row['telefono'], true);
            }
            $servicios[] = $row;
        }

        echo json_encode($servicios, JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => "Error al obtener servicios", "detalle" => $e->getMessage()]);
    }
    exit;
}

if ($method === 'POST') {
    try {
        $documentsPath = __DIR__ . "/../../../directorioDocumentos/";
        if (!file_exists($documentsPath)) mkdir($documentsPath, 0777, true);
        
        $nombre = $_POST['nombre'] ?? '';
        $clasificacion = $_POST['clasificacion'] ?? '';
        $ubicacion = $_POST['ubicacion'] ?? '';
        $telefono = $_POST['telefono'] ?? '';
        $correo = $_POST['correo'] ?? '';
        $idCategoria = $_POST['idCategoria'] ?? 0;
        $detalles = $_POST['detalles'] ?? '[]';
        $moneda = $_POST['moneda'] ?? 'USD';
        
        // Validaciones básicas
        if (empty($nombre) || empty($clasificacion)) {
            throw new Exception("El nombre y clasificación son requeridos");
        }
        
        $rutaArchivoFinal = null;
        if (!empty($_FILES["documento"]["name"])) {
            $safeName = time() . "-" . preg_replace("/\s+/", "_", $_FILES["documento"]["name"]);
            $destino = $documentsPath . $safeName;
            if (move_uploaded_file($_FILES["documento"]["tmp_name"], $destino)) {
                $rutaArchivoFinal = $safeName;
            } else {
                throw new Exception("Error al subir el archivo");
            }
        }

        // Preparar la consulta INSERT
        if ($rutaArchivoFinal === null) {
            $stmt = $conn->prepare("INSERT INTO servicio (nombre, clasificacion, ubicacion, telefono, correo, idCategoria) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssi", $nombre, $clasificacion, $ubicacion, $telefono, $correo, $idCategoria);
        } else {
            $stmt = $conn->prepare("INSERT INTO servicio (nombre, clasificacion, ubicacion, telefono, correo, documento, idCategoria) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssi", $nombre, $clasificacion, $ubicacion, $telefono, $correo, $rutaArchivoFinal, $idCategoria);
        }

        // Ejecutar la inserción principal
        if (!$stmt->execute()) {
            throw new Exception("Error al insertar servicio: " . $stmt->error);
        }
        
        $insertedId = $stmt->insert_id;
        $stmt->close();

        // Insertar detalles/características del servicio
        $arrayDetalles = json_decode($detalles, true);
        if (is_array($arrayDetalles) && !empty($arrayDetalles)) {
            foreach ($arrayDetalles as $detalle) {
                $nombreDetalle = $detalle['nombre'] ?? '';
                $descripcion = $detalle['descripcion'] ?? '';
                $tipoMoneda = $detalle['tipoMoneda'] ?? 'USD';
                $tarifaConfidencial = $detalle['tarifaConfidencial'] ?? 0.00;
                $tarifaVenta = $detalle['tarifaVenta'] ?? 0.00;
                $costo = $detalle['costo'] ?? 0.00;

                $stmtDetalle = $conn->prepare("INSERT INTO caracteristicasServicio (nombre, descripcion, costo, tipoMoneda, tarifaConfidencial, tarifaVenta, idServicio) VALUES (?, ?, ?, ?, ?, ?)");
                $stmtDetalle->bind_param("ssisddi", $nombreDetalle, $descripcion, $costo, $tipoMoneda, $tarifaConfidencial, $tarifaVenta, $insertedId);

                if (!$stmtDetalle->execute()) {
                    throw new Exception("Error al insertar detalle: " . $stmtDetalle->error);
                }
                $stmtDetalle->close();
            }
        }

        http_response_code(201);
        echo json_encode([
            "success" => true,
            "id" => $insertedId,
            "nombre" => $nombre
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "error" => "Error al crear servicio: " . $e->getMessage()
        ]);
    }
    exit;
}

http_response_code(405);
echo json_encode(["error" => "Método no permitido"]);
?>
