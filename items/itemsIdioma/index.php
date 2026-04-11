<?php
require_once '../../config/db.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method){
    case 'GET':
        if (isset($_GET['idioma_id'])) {
        $idioma_id = intval($_GET['idioma_id']);
        $stmt = $conn->prepare("
            SELECT im.*, m.menu_slug
            FROM idioma_menu im
            INNER JOIN menu m ON im.menu_id = m.id
            WHERE im.idioma_id = ? 
        ");
        $stmt->bind_param("i", $idioma_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }

        echo json_encode($rows, JSON_UNESCAPED_UNICODE);
        $stmt->close();
    }
    break;

}

?>