<?php
require_once '../../config/db.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

$method = $_SERVER['REQUEST_METHOD'];
$overrideMethod = $_POST['_method'] ?? ($_GET['_method'] ?? null);
if ($overrideMethod) $method = strtoupper($overrideMethod);

$idCategoria = $_GET['idCategoria'] ?? null;

if ($method === 'GET') {
    try {
        if(!$idCategoria){
            http_response_code(400);
            echo json_encode(['error' => 'Falta el parámetro idCategoria']);
            exit;
        }
        $sql = "SELECT * FROM servicios WHERE idCategoria = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $idCategoria);
        $stmt->execute();
        $result = $stmt->get_result();

        $servicios = [];

        while ($row = $result->fetch_assoc()) {
            $row['telefono'] = json_decode($row['telefono'], true);
            $servicios[] = $row;
        }

        echo json_encode($servicios);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => "Error al obtener servicios"]);
    }
    exit;
}
?>