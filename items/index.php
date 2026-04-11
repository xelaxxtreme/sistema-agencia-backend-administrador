<?php
require_once '../config/db.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            // Obtener registro por ID
            $id = intval($_GET['id']);
            $stmt = $conn->prepare("SELECT * FROM menu WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            echo json_encode($result->fetch_assoc(), JSON_UNESCAPED_UNICODE);
            $stmt->close();
        } else {
            // Listar todos
            $result = $conn->query("SELECT * FROM menu");
            $rows = $result->fetch_all(MYSQLI_ASSOC);
            echo json_encode($rows, JSON_UNESCAPED_UNICODE);
        }
        break;

    case 'POST':
        // Crear registro
        $input = json_decode(file_get_contents("php://input"), true);

        $menu_slug   = $input['menu_slug'] ?? null;
        $nombre_slug = $input['nombre_slug'] ?? null;
        $nombre      = $input['nombre'] ?? null;
        $posicion    = $input['posicion'] ?? null;
        $submenu     = $input['submenu'] ?? null;

        $stmt = $conn->prepare("INSERT INTO menu (menu_slug, nombre_slug, nombre, posicion, submenu) VALUES (?,?,?,?,?)");
        $stmt->bind_param("sssss", $menu_slug, $nombre_slug, $nombre, $posicion, $submenu);

        if ($stmt->execute()) {
            $menu_id = $stmt->insert_id;
            $stmt->close();

            // Insertar traducciones en idioma_menu
            if (!empty($input['traducciones']) && is_array($input['traducciones'])) {
                $stmtTrad = $conn->prepare("INSERT INTO idioma_menu (idioma_id, menu_id, nombre_traducido) VALUES (?,?,?)");

                foreach ($input['traducciones'] as $trad) {
                    $idioma_id        = intval($trad['idioma_id']);
                    $nombre_traducido = $trad['nombre_traducido'] ?? "";

                    $stmtTrad->bind_param("iis", $idioma_id, $menu_id, $nombre_traducido);
                    $stmtTrad->execute();
                }

                $stmtTrad->close();
            }

            echo json_encode(["success" => true, "id" => $menu_id]);
        } else {
            http_response_code(500);
            echo json_encode(["success" => false, "error" => $stmt->error]);
            $stmt->close();
        }

        break;

    case 'PUT':
        // Actualizar registro
        $input = json_decode(file_get_contents("php://input"), true);

        $id          = $input['id'] ?? null;
        $menu_slug   = $input['menu_slug'] ?? null;
        $nombre_slug = $input['nombre_slug'] ?? null;
        $nombre      = $input['nombre'] ?? null;
        $posicion    = $input['posicion'] ?? null;
        $submenu     = $input['submenu'] ?? null;

        if (!$id) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "Falta el ID para actualizar"]);
            break;
        }
        $stmt = $conn->prepare("UPDATE menu 
                                SET menu_slug=?, nombre_slug=?, nombre=?, posicion=?, submenu=? 
                                WHERE id=?");
        $stmt->bind_param("sssssi", $menu_slug, $nombre_slug, $nombre, $posicion, $submenu, $id);

        if ($stmt->execute()) {
            $stmt->close();

            if (!empty($input['traducciones']) && is_array($input['traducciones'])) {
                foreach ($input['traducciones'] as $trad) {
                    $idioma_menu_id   = $trad['idioma_menu_id'] ?? null;
                    $idioma_id        = intval($trad['idioma_id']);
                    $nombre_traducido = $trad['nombre_traducido'] ?? "";

                    if ($idioma_menu_id) {
                        $stmtTrad = $conn->prepare("UPDATE idioma_menu 
                                                    SET idioma_id=?, nombre_traducido=? 
                                                    WHERE id=? AND menu_id=?");
                        $stmtTrad->bind_param("isii", $idioma_id, $nombre_traducido, $idioma_menu_id, $id);
                        $stmtTrad->execute();
                        $stmtTrad->close();
                    } else {
                        $stmtTrad = $conn->prepare("INSERT INTO idioma_menu (idioma_id, menu_id, nombre_traducido) VALUES (?,?,?)");
                        $stmtTrad->bind_param("iis", $idioma_id, $id, $nombre_traducido);
                        $stmtTrad->execute();
                        $stmtTrad->close();
                    }
                }
            }

            echo json_encode(["success" => true, "id" => $id]);
        } else {
            http_response_code(500);
            echo json_encode(["success" => false, "error" => $stmt->error]);
            $stmt->close();
        }

        break;

    case 'DELETE':
        $input = json_decode(file_get_contents("php://input"), true);
        $id = $input['id'] ?? null;

        if (!$id) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "Falta el ID para eliminar"]);
            break;
        }

        // Primero eliminar traducciones relacionadas
        $stmtTrad = $conn->prepare("DELETE FROM idioma_menu WHERE menu_id = ?");
        $stmtTrad->bind_param("i", $id);
        $stmtTrad->execute();
        $stmtTrad->close();

        // Luego eliminar el menú
        $stmt = $conn->prepare("DELETE FROM menu WHERE id = ?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            echo json_encode(["success" => true, "id" => $id]);
        } else {
            http_response_code(500);
            echo json_encode(["success" => false, "error" => $stmt->error]);
        }

        $stmt->close();
        break;

    default:
        http_response_code(405);
        echo json_encode(["error" => "Método no soportado"]);
}
?>