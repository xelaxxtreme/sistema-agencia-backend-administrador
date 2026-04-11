<?php
require_once '../config/db.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $texto = isset($_GET['q']) ? $_GET['q'] : '';
    $lang = isset($_GET['lang']) ? $_GET['lang'] : 'es';

    // Preparar consulta
    $sql = "
      SELECT 
        id,
        nombre,
        precio_enganche,
        descripcion,
        imagen_portada,
        duracion,
        departamento,
        slug,
        tipo
      FROM tours
      WHERE lang = ?
        AND (
          nombre LIKE ?
          OR descripcion LIKE ?
          OR itinerario LIKE ?
        )
      ORDER BY nombre ASC
    ";

    $stmt = $conn->prepare($sql);
    $likeTexto = '%' . $texto . '%';
    $stmt->bind_param("ssss", $lang, $likeTexto, $likeTexto, $likeTexto);
    $stmt->execute();

    $result = $stmt->get_result();
    $tours = [];

    while ($row = $result->fetch_assoc()) {
        $tours[] = $row;
    }

    echo json_encode($tours);

    $stmt->close();
    $conn->close();
} else {
    http_response_code(405);
    echo json_encode(["error" => "Método no permitido"]);
}
?>