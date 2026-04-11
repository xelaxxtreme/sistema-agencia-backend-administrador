<?php 
require_once '../config/db.php';

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

if ($method == 'GET') {
    $lang = $_GET['lang'] ?? null;
    try {
        $query = "SELECT * FROM posts";
        if ($lang) {
            $query .= " WHERE lang = ?";
        }
        $query .= " ORDER BY created_at DESC";
        $stmt = $conn->prepare($query);
        if ($lang) {
            $stmt->bind_param("s", $lang);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $blogs = $result->fetch_all(MYSQLI_ASSOC);
        echo json_encode($blogs);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error al obtener los blogs: ' . $e->getMessage()]);
    }
    exit;
} 

if ($method == 'POST') {
    try {
        $imagenesArticulos = __DIR__ ."/../../../storage.terresdesincas.com/public/imagenesBlog/";
        if (!file_exists($imagenesArticulos)) {
            mkdir($imagenesArticulos, 0777, true);
        }
        $title      = $_POST["title"] ?? "";
        $slug       = $_POST["slug"] ?? "";
        $resumen    = $_POST["resumen"] ?? "";
        $content    = $_POST["content"] ?? "";
        $created_at = $_POST["created_at"] ?? "";
        $idioma     = $_POST["idioma"] ?? "";

        // 3. Preparar la consulta principal
        $stmt = $conn->prepare("
            INSERT INTO posts (title, slug, resumen, content, created_at, lang)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        if (!$stmt) {
            throw new Exception("Error preparando la consulta del post: " . $conn->error);
        }

        $stmt->bind_param("ssssss", $title, $slug, $resumen, $content, $created_at, $idioma);
        $stmt->execute();
        $postId = $stmt->insert_id;
          if (isset($_FILES["galeria"])) {
            foreach ($_FILES["galeria"]["name"] as $i => $fileName) {
                if ($fileName) {
                    $safeName = time() . "-" . preg_replace("/\s+/", "_", $fileName);
                    $destino = $imagenesArticulos . $safeName;
                    if (move_uploaded_file($_FILES["galeria"]["tmp_name"][$i], $destino)) {
                        $stmtGal = $conn->prepare("INSERT INTO post_images (post_id, url, nombre) VALUES (?,?,?)");
                        $stmtGal->bind_param("iss", $postId, $safeName, $fileName);
                        $stmtGal->execute();
                    }
                }
            }
        }

        http_response_code(201);
        echo json_encode([
            "message" => "Post y galería guardados con éxito",
            "id" => $postId
        ], JSON_UNESCAPED_UNICODE);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    
    exit;
}
http_response_code(405);
echo json_encode(['error' => 'Método no permitido'], JSON_UNESCAPED_UNICODE);
?>