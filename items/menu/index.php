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

switch($method){
    case 'GET':
        $sql = "SELECT m.id, m.menu_slug, m.nombre_slug, m.nombre, m.posicion, m.submenu,
               im.id AS idioma_menu_id, im.idioma_id, im.nombre_traducido,
               i.lang, i.imagen, im.id as idIdMe
        FROM menu m
        LEFT JOIN idioma_menu im ON m.id = im.menu_id
        LEFT JOIN idioma i ON im.idioma_id = i.id
        ORDER BY m.posicion ASC";

        $result = $conn->query($sql);

        $menus = [];
        while ($row = $result->fetch_assoc()) {
            $menu_id = $row['id'];

            if (!isset($menus[$menu_id])) {
                $menus[$menu_id] = [
                    "id"          => (int)$row['id'],
                    "menu_slug"   => $row['menu_slug'],
                    "nombre_slug" => $row['nombre_slug'],
                    "nombre"      => $row['nombre'],
                    "posicion"    => (int)$row['posicion'],
                    "submenu"     => (bool)$row['submenu'],
                    "traducciones" => []
                ];
            }

            if (!empty($row['idioma_id'])) {
                $menus[$menu_id]["traducciones"][] = [
                    "idIdMe"           => (int)$row['idIdMe'],
                    "idioma_menu_id"   => $row['idioma_menu_id'],
                    "idioma_id"        => $row['idioma_id'],
                    "nombre_traducido" => $row['nombre_traducido'],
                    "lang"             => $row['lang'],
                    "ruta"             => $row['imagen']
                ];
            }
        }

        echo json_encode(array_values($menus), JSON_UNESCAPED_UNICODE);
        break;


    case 'PUT':
        $data = json_decode(file_get_contents("php://input"), true);

        if (!isset($data['orden']) || !is_array($data['orden'])) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "Falta el array de orden"]);
            break;
        }

        $stmt = $conn->prepare("UPDATE menu SET posicion=? WHERE id=?");

        foreach ($data['orden'] as $item) {
            $posicion = intval($item['posicion']);
            $id       = intval($item['id']);
            $stmt->bind_param("ii", $posicion, $id);
            $stmt->execute();
        }

        $stmt->close();
        echo json_encode(["success" => true]);
        break;


        break;

    case 'DELETE':
        $data = json_decode(file_get_contents("php://input"), true);

        if (!$data || !isset($data['id'])) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "Falta el ID del menú"]);
            break;
        }

        $menu_id = intval($data['id']);

        // Primero eliminar las traducciones asociadas en idioma_menu
        $stmt = $conn->prepare("DELETE FROM idioma_menu WHERE menu_id = ?");
        $stmt->bind_param("i", $menu_id);
        $stmt->execute();
        $stmt->close();

        // Luego eliminar el menú en sí (opcional, según tu lógica)
        $stmtMenu = $conn->prepare("DELETE FROM menu WHERE id = ?");
        $stmtMenu->bind_param("i", $menu_id);
        $success = $stmtMenu->execute();
        $stmtMenu->close();

        echo json_encode(["success" => $success]);

        break;
}
?>