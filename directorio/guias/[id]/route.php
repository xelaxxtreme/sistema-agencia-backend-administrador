<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");
require_once '../../../config/db.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

$method = $_SERVER['REQUEST_METHOD'];
$overrideMethod = $_POST['_method'] ?? ($_GET['_method'] ?? null);
if ($overrideMethod) $method = strtoupper($overrideMethod);

$id = $_GET['id'] ?? null;
$documentsPath = __DIR__ . "/../../../../directorioDocumentos/";

try {
    switch ($method) {
        // ✅ GET → obtener guia por ID
        case 'GET':
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'Falta el parámetro ID']);
                exit;
            }

            $stmt = $conn->prepare("SELECT * FROM guias WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 0) {
                http_response_code(404);
                echo json_encode(['error' => 'guia no encontrado']);
                exit;
            }

            $row = $result->fetch_assoc();

            // decodificamos los campos JSON
            $row['numero'] = json_decode($row['numero'], true) ?? [];
            $row['detalles'] = json_decode($row['detalles'], true) ?? [];

            echo json_encode($row, JSON_UNESCAPED_UNICODE);
            break;

        // ✅ PUT → actualizar guia
        case 'POST':
            if (!file_exists($documentsPath)) mkdir($documentsPath, 0777, true);
            try {
                $id = $_POST['id'] ?? null;
                if (!$id) {
                    throw new Exception("ID de agencia no proporcionado");
                }
                $nombre   = $_POST['nombre'] ?? '';
                $numero   = $_POST['numero'] ?? '[]';
                $lugar    = $_POST['lugar'] ?? '';
                $detalles = $_POST['detalles'] ?? '[]';
                $creado   = $_POST['creado'] ?? date('Y-m-d');

                $documento = null; 
                
                if (!empty($_FILES['documento']['name'])) {
                    $old = $conn->query("SELECT documento FROM guias WHERE id = $id")->fetch_assoc();
                    if ($old && $old['documento']) {
                        $oldPath = $documentsPath . $old['documento'];
                        if (file_exists($oldPath)) unlink($oldPath);
                    }

                    $safeName = time() . "-" . preg_replace('/\s+/', '_', $_FILES['documento']['name']);
                    move_uploaded_file($_FILES['documento']['tmp_name'], $documentsPath . $safeName);
                    $documento = $safeName;
                }
                
                if ($documento) {
                    $sql = "UPDATE guias SET nombre=?, numero=?, lugar=?, detalles=?, creado=?, documento=? WHERE id=?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ssssssi", $nombre, $numero, $lugar, $detalles, $creado, $documento, $id);
                } else {
                    $sql = "UPDATE guias SET nombre=?, numero=?, lugar=?, detalles=?, creado=? WHERE id=?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("sssssi", $nombre, $numero, $lugar, $detalles, $creado, $id);
                }

                if (!$stmt->execute()) {
                    throw new Exception($stmt->error);
                }

                http_response_code(200);
                echo json_encode([
                    'message' => 'Agencia actualizada correctamente', 
                    'id' => $id,
                    'nuevoDocumento' => (bool)$documento
                ]);

            } catch (Exception $th) {
                http_response_code(500);
                echo json_encode([
                    'error' => 'Error interno', 
                    'details' => $th->getMessage()
                ], JSON_UNESCAPED_UNICODE);
            }
            break;
        // ✅ DELETE → eliminar guia
        case 'DELETE':
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'Falta el parámetro ID']);
                exit;
            }
                $doc = $conn->query("SELECT documento FROM guias WHERE id = $id")->fetch_assoc();
            if ($doc && $doc['documento']) {
                $filePath = $documentsPath . $doc['documento']; 
                if (file_exists($filePath)) unlink($filePath);
            }

                $stmt = $conn->prepare("DELETE FROM guias WHERE id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();

                echo json_encode(['message' => 'guía eliminada', 'id' => $id]);
                break;
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
