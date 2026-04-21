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
            if (!empty($servicio['telefono'])) {
                $decodedTelefono = json_decode($servicio['telefono'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $servicio['telefono'] = $decodedTelefono;
                }
            }
            $stmtCaracteristicas = $conn->prepare("SELECT * FROM caracteristicasServicio WHERE idServicio = ? ORDER BY estado DESC");
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
        $idServicio = $_POST['idServicio'] ?? null;
         if (!$idServicio || !is_numeric($idServicio)) {
            throw new Exception("Falta o es inválido el parámetro idServicio");
        }
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
            $old = $conn->query("SELECT documento FROM servicio WHERE idServicio = $idServicio")->fetch_assoc();
            if ($old && $old['documento']) {
                $oldPath = $documentsPath . $old['documento'];
                if (file_exists($oldPath)) unlink($oldPath);
            }

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
            $stmt = $conn->prepare("UPDATE servicio SET nombre = ?, clasificacion = ?, ubicacion = ?, telefono = ?, correo = ?, idCategoria = ? WHERE idServicio = ?");
            $stmt->bind_param("sssssii", $nombre, $clasificacion, $ubicacion, $telefono, $correo, $idCategoria, $idServicio);
        } else {
            $stmt = $conn->prepare("UPDATE servicio SET nombre = ?, clasificacion = ?, ubicacion = ?, telefono = ?, correo = ?, documento = ?, idCategoria = ? WHERE idServicio = ?");
            $stmt->bind_param("ssssssii", $nombre, $clasificacion, $ubicacion, $telefono, $correo, $rutaArchivoFinal, $idCategoria, $idServicio);
        }

        // Ejecutar la inserción principal
        if (!$stmt->execute()) {
            throw new Exception("Error al actualizar servicio: " . $stmt->error);
        }
        
        $stmt->close();

        // Insertar detalles/características del servicio
        $arrayDetalles = json_decode($detalles, true);
        if (is_array($arrayDetalles) && !empty($arrayDetalles)) {
            foreach ($arrayDetalles as $detalle) {
                $idCaracteristica = $detalle['idCaracteristica'] ?? 0;
                $nombreDetalle = $detalle['nombre'] ?? '';
                $descripcion = $detalle['descripcion'] ?? '';
                $costo = $detalle['costo'] ?? 1;
                $tipoMoneda = $detalle['tipoMoneda'] ?? 'USD';
                $tarifaConfidencial = $detalle['tarifaConfidencial'] ?? 0.00;
                $tarifaVenta = $detalle['tarifaVenta'] ?? 0.00;
                $estado = $detalle['estado'] ?? 1;

                if($idCaracteristica > 0){
                    $stmtDetalle = $conn->prepare("UPDATE caracteristicasServicio SET nombre = ?, descripcion = ?, costo = ?, tipoMoneda = ?, tarifaConfidencial = ?, tarifaVenta = ?, estado = ? WHERE idCaracteristica = ?");
                    $stmtDetalle->bind_param("ssisddii", $nombreDetalle, $descripcion, $costo, $tipoMoneda, $tarifaConfidencial, $tarifaVenta, $estado, $idCaracteristica);

                    if (!$stmtDetalle->execute()) {
                        throw new Exception("Error al actualizar detalle: " . $stmtDetalle->error);
                    }
                    $stmtDetalle->close();
                } else {
                    $stmtDetalle = $conn->prepare("INSERT INTO caracteristicasServicio (nombre, descripcion, costo, tipoMoneda, tarifaConfidencial, tarifaVenta, estado, idServicio) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmtDetalle->bind_param("ssisddii", $nombreDetalle, $descripcion, $costo, $tipoMoneda, $tarifaConfidencial, $tarifaVenta, $estado, $idServicio);

                    if (!$stmtDetalle->execute()) {
                        throw new Exception("Error al insertar detalle: " . $stmtDetalle->error);
                    }
                    $stmtDetalle->close();
                }
            }
        }

        http_response_code(200);
        echo json_encode([
            "success" => true,
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
echo json_encode(['error' => 'Método no permitido'], JSON_UNESCAPED_UNICODE);
?>