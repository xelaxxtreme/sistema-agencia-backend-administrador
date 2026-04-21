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
    try{
        if (!$idCategoria || !is_numeric($idCategoria)) {
            http_response_code(400);
            echo json_encode(['error' => 'Falta o es inválido el parámetro idCategoria']);
            exit;
        }

        $sql = "SELECT s.idServicio, s.nombre AS nombreServicio, s.ubicacion AS ubicacionServicio, c.idCaracteristica, c.nombre AS nombreCaracteristica, c.tarifaConfidencial, c.tarifaVenta, c.tipoMoneda FROM servicio s LEFT JOIN caracteristicasServicio c ON s.idServicio = c.idServicio WHERE s.idCategoria = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $idCategoria);
        $stmt->execute();
        $result = $stmt->get_result();
        $servicios = [];

        while ($row = $result->fetch_assoc()) {
            $idServicio = $row['idServicio'];        

            if (!isset($servicios[$idServicio])) {
                $servicios[$idServicio] = [
                "idServicio" => $row['idServicio'],
                "nombreServicio" => $row['nombreServicio'],
                "ubicacionServicio" => $row['ubicacionServicio'],
                "caracteristicas" => []
                ];
            }
            if (!empty($row['idCaracteristica'])) {
                        $servicios[$idServicio]["caracteristicas"][] = [
                        "idCaracteristica" => $row['idCaracteristica'],
                        "nombreCaracteristica" => $row['nombreCaracteristica'] ?? null,
                        "tarifaConfidencial" => $row['tarifaConfidencial'] ?? null,
                        "tarifaVenta" => $row['tarifaVenta'] ?? null,
                        "tipoMoneda" => $row['tipoMoneda'] ?? null
                        // ajusta según tus columnas reales
                    ];
                }

        }

        $servicios = array_values($servicios);
        echo json_encode($servicios, JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => "Error al obtener servicios", "detalle" => $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Método no permitido'], JSON_UNESCAPED_UNICODE);
?>