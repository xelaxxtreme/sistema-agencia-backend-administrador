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

$slug = $_GET['slug'] ?? null;

if($method === 'GET'){
    try{
        $stmt = $conn->prepare("SELECT id FROM posts WHERE slug = ?");
        $stmt->bind_param("s", $slug);
        $stmt->execute();
        $result = $stmt->get_result();
        $post = $result->fetch_assoc();
        if (!$post) {
            http_response_code(404);
            echo json_encode(['error' => 'Post no encontrado'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        echo json_encode($post, JSON_UNESCAPED_UNICODE);
    }catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error al obtener el post: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
}
?>