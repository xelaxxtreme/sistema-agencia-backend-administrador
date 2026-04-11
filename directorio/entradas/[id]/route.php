<?php
header('Content-Type: application/json');
require_once '../../../config/db.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

$method = $_SERVER['REQUEST_METHOD'];
$overrideMethod = $_POST['_method'] ?? ($_GET['_method'] ?? null);
if ($overrideMethod) $method = strtoupper($overrideMethod);

$id = $_GET['id'] ?? null;

try {
    switch ($method) {
        // ✅ GET → obtener entrada por ID
        case 'GET':
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'Falta el parámetro ID']);
                exit;
            }

            $stmt = $conn->prepare("SELECT * FROM entradas WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 0) {
                http_response_code(404);
                echo json_encode(['error' => 'entrada no encontrado']);
                exit;
            }

            $row = $result->fetch_assoc();

            // decodificamos los campos JSON
            $row['numero'] = json_decode($row['numero'], true) ?? [];
            $row['detalles'] = json_decode($row['detalles'], true) ?? [];

            echo json_encode($row, JSON_UNESCAPED_UNICODE);
            break;

        // ✅ PUT → actualizar entrada
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            if (isset($data['_method']) && $data['_method'] === 'PUT') {
                $nombre = $data['nombre'] ?? '';
                $lugar = $data['lugar'] ?? '';
                $detalles = json_encode($data['detalles'] ?? []);
                $creado = $data['creado'] ?? date('Y-m-d');

                $stmt = $conn->prepare("
                    UPDATE entradas 
                    SET nombre = ?, lugar = ?, detalles = ?, creado = ? 
                    WHERE id = ?
                ");
                $stmt->bind_param("ssssi", $nombre, $lugar, $detalles, $creado, $id);
                $stmt->execute();

                http_response_code(200);
                echo json_encode(['message' => 'entrada actualizado correctamente', 'id' => $id]);
            }
            break;
        // ✅ DELETE → eliminar entrada
        case 'DELETE':
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'Falta el parámetro ID']);
                exit;
            }

            $stmt = $conn->prepare("DELETE FROM entradas WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();

            echo json_encode(['message' => 'entrada eliminado', 'id' => $id]);
            break;

        // 🚫 Método no permitido
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Método no permitido', 'recibido' => $method]);
            break;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Error interno del servidor',
        'detalle' => $e->getMessage()
    ]);
}
?>
