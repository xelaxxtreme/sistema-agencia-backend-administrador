<?php 
require_once '../../config/db.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");
// Manejo de preflight (CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
$method = $_SERVER['REQUEST_METHOD'];

$id = $_GET['id'] ?? null;
$imagenesArticulos = __DIR__ ."/../../../../storage.terresdesincas.com/public/imagenesBlog/";
if (!$id || !is_numeric($id)) {
    http_response_code(400);
    echo json_encode(['error' => 'ID requerido o inválido'], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($method === 'GET') {
    try{
        $stmt = $conn->prepare("
            SELECT p.*, g.id AS galeria_id, g.url, g.nombre AS galeria_nombre
            FROM posts p
            LEFT JOIN post_images g ON p.id = g.post_id
            WHERE p.id = ?
        ");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        if (!$rows) {
            http_response_code(404);
            echo json_encode(['error' => 'Post no encontrado'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $articulo = $rows[0];
        $articulo['galeria'] = array_values(array_map(fn($g) => [
            'id' => $g['galeria_id'],
            'url' => $g['url'],
            'nombre' => $g['galeria_nombre']
        ], array_filter($rows, fn($g) => $g['galeria_id'] !== null)));

        unset($articulo['galeria_id'], $articulo['url'], $articulo['galeria_nombre']);
        echo json_encode($articulo, JSON_UNESCAPED_UNICODE);
    }
    catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error al obtener el post: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}
if ($method === 'DELETE'){
    try{
        $galeria = $conn->query("SELECT url FROM post_images WHERE post_id = $id")->fetch_all(MYSQLI_ASSOC);
    foreach ($galeria as $item) {
        $filePath = $imagenesArticulos . $item['url'];
        if (file_exists($filePath)) unlink($filePath);
    }
    $conn->query("DELETE FROM posts WHERE id = $id");
    }catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error al eliminar el post: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}
if ($method === 'POST' && ($_POST['_method'] ?? '') === 'PUT') {
    try{
        $id = $_POST['id'] ?? null;
        if (!$id || !is_numeric($id)) {
            http_response_code(400);
            echo json_encode(['error' => 'ID requerido para actualizar'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $fields = [
            'title', 'slug', 'resumen', 'content', 'created_at', 'idioma'
        ];
        $values = [];
        foreach ($fields as $f) {
            $values[$f] = $_POST[$f] ?? '';
        }
        $stmt = $conn->prepare("
            UPDATE posts SET 
                title=?, slug=?, resumen=?, content=?, created_at=?, lang=?
            WHERE id=?
        ");
        $stmt->bind_param(
            "ssssssi", 
            $values['title'],          
            $values['slug'],            
            $values['resumen'],          
            $values['content'],         
            $values['created_at'],     
            $values['idioma'],                 
            $id                         
        );   
        $stmt->execute();

        $galeriaBD = $conn->query("SELECT id, url FROM post_images WHERE post_id = $id")->fetch_all(MYSQLI_ASSOC);
        if (isset($_FILES['galeria'])) {
            foreach ($_FILES['galeria']['name'] as $i => $name) {
                if ($_FILES['galeria']['error'][$i] === 0) {
                    $safeName = time() . "-" . preg_replace('/\s+/', '_', $name);
                    move_uploaded_file($_FILES['galeria']['tmp_name'][$i], $imagenesArticulos . $safeName);
                    $stmtGaleria = $conn->prepare("INSERT INTO post_images (post_id, nombre, url) VALUES (?, ?, ?)");
                    $stmtGaleria->bind_param("iss", $id, $name, $safeName)  ;
                    $stmtGaleria->execute();
                }
            }
        }

        $galeriaRaw = $_POST['galeria'] ?? [];
        if (!is_array($galeriaRaw)) $galeriaRaw = [$galeriaRaw];

        $jsonItems = array_filter($galeriaRaw, function ($item) {
            return is_string($item) && str_starts_with(trim($item), '{');
        });

        $referencias = array_map(function ($json) {
            $obj = json_decode($json, true);
            return is_array($obj) && isset($obj['id']) ? $obj['id'] : null;
        }, $jsonItems);

        $referencias = array_filter($referencias); // eliminar nulls

        // Eliminar los que no están en referencias
        foreach ($galeriaBD as $item) {
            if (!in_array($item['id'], $referencias)) {
                $conn->query("DELETE FROM post_images WHERE id = {$item['id']}");
                $filePath = $imagenesArticulos . $item['url'];
                if (file_exists($filePath)) unlink($filePath);
            }
        }
        http_response_code(200);
        echo json_encode([
            'message' => 'Formulario actualizado con éxito',
            'id' => $id
        ], JSON_UNESCAPED_UNICODE);

    }catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error al actualizar el post: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Método no permitido'], JSON_UNESCAPED_UNICODE);
?>