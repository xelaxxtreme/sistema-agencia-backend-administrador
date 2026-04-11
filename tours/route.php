<?php
require_once '../config/db.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    try {
        $lang = $_GET['lang'] ?? null;

        $query = "
            SELECT 
                t.id, 
                t.nombre, 
                t.idioma, 
                t.precio, 
                t.doc_itinerario as archivo,
                m.menu_slug as tipo, 
                t.precio_enganche, 
                t.duracion, 
                t.departamento, 
                t.imagen_portada,
                t.slug,
                g.id AS galeria_id,
                -- Subconsulta para contar las preguntas frecuentes
                (SELECT COUNT(*) FROM tour_faqs tp WHERE tp.tour_id = t.id) AS total_preguntas
            FROM tours t 
            INNER JOIN idioma_menu im ON im.id = t.idTipo 
            INNER JOIN menu m ON m.id = im.menu_id 
            LEFT JOIN tours_galeria g ON t.id = g.tour_id
        ";

        if ($lang) {
            $query .= " WHERE t.idioma = ? ORDER BY t.nombre ASC";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("s", $lang);
        } else {
            $query .= " ORDER BY t.nombre ASC";
            $stmt = $conn->prepare($query);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        $tours = [];

        while ($row = $result->fetch_assoc()) {
            $id = $row['id'];

            if (!isset($tours[$id])) {
                $tours[$id] = [
                    'id' => $row['id'],
                    'nombre' => $row['nombre'],
                    'idioma' => $row['idioma'],
                    'precio' => $row['precio'],
                    'tipo' => $row['tipo'],
                    'precio_enganche' => $row['precio_enganche'],
                    'duracion' => $row['duracion'],
                    'departamento' => $row['departamento'],
                    'imagen_portada' => $row['imagen_portada'],
                    'slug' => $row['slug'],
                    'archivo' => $row['archivo'],
                    // Guardamos el conteo aquí
                    'total_preguntas' => (int)$row['total_preguntas'], 
                    'galeria' => [],
                ];
            }

            if ($row['galeria_id']) {
                $tours[$id]['galeria'][] = [
                    'id' => $row['galeria_id'],
                ];
            }
        }

        echo json_encode(array_values($tours), JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error de consulta: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}


if ($method === 'POST') {
    $uploadsPath = __DIR__ . "/../../../storage.terresdesincas.com/public/imagenes/";
    $documentsPath = __DIR__ . "/../../../storage.terresdesincas.com/public/documentos/";

    if (!file_exists($uploadsPath)) mkdir($uploadsPath, 0777, true);
    if (!file_exists($documentsPath)) mkdir($documentsPath, 0777, true);

    // Campos obligatorios mínimos
    if (empty($_POST["nombre"]) || empty($_POST["idioma"]) || empty($_POST["precio"])) {
        http_response_code(400);
        echo json_encode(["error" => "Campos obligatorios faltantes"], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Campos de texto
    $nombre = $_POST["nombre"];
    $slug = $_POST["slug"] ?? '';
    $idioma = $_POST["idioma"];
    $descripcion = $_POST["descripcion"] ?? '';
    $precio = $_POST["precio"];
    $precio_enganche = $_POST["precio_enganche"] ?? '';
    $duracion = $_POST["duracion"] ?? '';
    $itinerario = $_POST["itinerario"] ?? '';
    $departamento = $_POST["departamento"] ?? '';
    $incluye = $_POST["incluye"] ?? '';
    $no_incluye = $_POST["no_incluye"] ?? '';
    $recomendaciones = $_POST["recomendaciones"] ?? '';
    $mapa = $_POST["mapa"] ?? '';
    $frase_seo = $_POST["frase_seo"] ?? '';
    $tipo = $_POST["tipo"] ?? '';
    $video = $_POST["video"] ?? '';
    $picos = $_POST["picos"] ?? '';
    $dificultad = $_POST["dificultad"] ?? '';
    $idTipo = $_POST["idTipo"] ?? '';
    $preguntasRaw = $_POST["preguntas"] ?? '[]';
    $parrafo = $_POST["parrafo"] ?? '[]';
    $keywords = $_POST["keywords"] ?? '[]';

    // Archivos
    $imagen_portada = null;
    if (!empty($_FILES["imagen_portada"]["name"])) {
        $safeName = time() . "-" . preg_replace("/\s+/", "_", $_FILES["imagen_portada"]["name"]);
        $destino = $uploadsPath . $safeName;
        if (move_uploaded_file($_FILES["imagen_portada"]["tmp_name"], $destino)) {
            $imagen_portada = $safeName;
        }
    }

    $doc_itinerario = null;
    if (!empty($_FILES["doc_itinerario"]["name"])) {
        $safeName = time() . "-" . preg_replace("/\s+/", "_", $_FILES["doc_itinerario"]["name"]);
        $destino = $documentsPath . $safeName;
        if (move_uploaded_file($_FILES["doc_itinerario"]["tmp_name"], $destino)) {
            $doc_itinerario = $safeName;
        }
    }

    // Insertar tour
    $stmt = $conn->prepare("
        INSERT INTO tours 
        (nombre, slug, idioma,parrafo, descripcion, precio, precio_enganche, duracion, itinerario,
        departamento, incluye, no_incluye, recomendaciones, mapa, imagen_portada, frase_seo,
        tipo, video, picos, dificultad, idTipo, doc_itinerario, keywords)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ");

    $stmt->bind_param(
        "sssssdsssssssssssssss",
        $nombre, $slug, $idioma, $parrafo, $descripcion, $precio, $precio_enganche, $duracion, $itinerario,
        $departamento, $incluye, $no_incluye, $recomendaciones, $mapa, $imagen_portada, $frase_seo,
        $tipo, $video, $picos, $dificultad, $idTipo, $doc_itinerario, $keywords
    );

    $stmt->execute();

    if ($stmt->errno) {
        http_response_code(500);
        echo json_encode(["error" => "Error al insertar el tour: " . $stmt->error], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $tourId = $stmt->insert_id;

    // Galería
    if (isset($_FILES["galeria"])) {
        foreach ($_FILES["galeria"]["name"] as $i => $fileName) {
            if ($fileName) {
                $safeName = time() . "-" . preg_replace("/\s+/", "_", $fileName);
                $destino = $uploadsPath . $safeName;

                if (move_uploaded_file($_FILES["galeria"]["tmp_name"][$i], $destino)) {
                    $stmtGal = $conn->prepare("INSERT INTO tours_galeria (tour_id, nombre, url) VALUES (?,?,?)");
                    $stmtGal->bind_param("iss", $tourId, $fileName, $safeName);
                    $stmtGal->execute();
                }
            }
        }
    }

    $preguntasArray = json_decode($preguntasRaw, true);
    if (is_array($preguntasArray) && !empty($preguntasArray)) {
        
        $stmtPreg = $conn->prepare("INSERT INTO tour_faqs (tour_id, question, answer, display_order) VALUES (?,?,?,?)");
        
        foreach ($preguntasArray as $item) {
            $preguntaTexto = $item['pregunta'] ?? '';
            $respuestaTexto = $item['respuesta'] ?? '';
            $orden = $item['orden'] ?? 0;

            if (!empty($preguntaTexto)) {
                $stmtPreg->bind_param("issi", $tourId, $preguntaTexto, $respuestaTexto, $orden);
                $stmtPreg->execute();
            }
        }
    }

    http_response_code(201);
    echo json_encode([
        "message" => "Formulario guardado con éxito",
        "id" => $tourId
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Método no permitido
http_response_code(405);
echo json_encode(['error' => 'Método no permitido'], JSON_UNESCAPED_UNICODE);
?>