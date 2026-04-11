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

$uploadsPath = __DIR__ . "/../../../../storage.terresdesincas.com/public/imagenes/";
$docsPath = __DIR__ . "/../../../../storage.terresdesincas.com/public/documentos/";

if (!$id || !is_numeric($id)) {
    http_response_code(400);
    echo json_encode(['error' => 'ID requerido o inválido'], JSON_UNESCAPED_UNICODE);
    exit;
}


if ($method === 'GET') {
    try {
        
        $stmt = $conn->prepare("
            SELECT t.*, g.id AS galeria_id, g.url, g.nombre AS galeria_nombre
            FROM tours t
            LEFT JOIN tours_galeria g ON t.id = g.tour_id
            WHERE t.id = ?
        ");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = $result->fetch_all(MYSQLI_ASSOC);

        if (!$rows) {
            http_response_code(404);
            echo json_encode(['error' => 'Tour no encontrado'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $tour = $rows[0];
        $tour['galeria'] = array_values(array_map(fn($g) => [
            'id' => $g['galeria_id'],
            'url' => $g['url'],
            'nombre' => $g['galeria_nombre']
        ], array_filter($rows, fn($g) => $g['galeria_id'] !== null)));

        unset($tour['galeria_id'], $tour['url'], $tour['galeria_nombre']);

        $stmtPreg = $conn->prepare("
            SELECT id, question as pregunta, answer as respuesta, display_order as orden 
            FROM tour_faqs 
            WHERE tour_id = ? 
            ORDER BY orden ASC
        ");
        $stmtPreg->bind_param("i", $id);
        $stmtPreg->execute();
        $resPreg = $stmtPreg->get_result();
        
        $tour['preguntas'] = $resPreg->fetch_all(MYSQLI_ASSOC);

        echo json_encode($tour, JSON_UNESCAPED_UNICODE);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error de consulta', 'details' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// eliminar tour y archivos
if ($method === 'DELETE') {
    try {
        // 1. Obtener y eliminar archivos físicos de la Galería
        $galeria = $conn->query("SELECT url FROM tours_galeria WHERE tour_id = $id")->fetch_all(MYSQLI_ASSOC);
        foreach ($galeria as $item) {
            $filePath = $uploadsPath . $item['url'];
            if (file_exists($filePath)) unlink($filePath);
        }

        $portada = $conn->query("SELECT imagen_portada FROM tours WHERE id = $id")->fetch_assoc();
        if ($portada && $portada['imagen_portada']) {
            $filePath = $uploadsPath . $portada['imagen_portada'];
            if (file_exists($filePath)) unlink($filePath);
        }

        $doc = $conn->query("SELECT doc_itinerario FROM tours WHERE id = $id")->fetch_assoc();
        if ($doc && $doc['doc_itinerario']) {
            $filePath = $documentsPath . $doc['doc_itinerario']; 
            if (file_exists($filePath)) unlink($filePath);
        }

        $conn->query("DELETE FROM tours_galeria WHERE tour_id = $id");

        $conn->query("DELETE FROM tour_faqs WHERE tour_id = $id");

        $conn->query("DELETE FROM tours WHERE id = $id");

        echo json_encode(['message' => 'Tour y todos sus datos asociados eliminados correctamente'], JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error al eliminar tour', 'details' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// ✅ PUT: actualizar tour (simulado vía POST + _method=PUT)
if ($method === 'POST' && ($_POST['_method'] ?? '') === 'PUT') {
    try {
        $id = $_POST['id'] ?? null;
        if (!$id || !is_numeric($id)) {
            http_response_code(400);
            echo json_encode(['error' => 'ID requerido para actualizar'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Campos de texto
        $fields = [
            'nombre', 'slug', 'idioma', 'parrafo', 'descripcion', 'precio', 'precio_enganche', 'duracion',
            'itinerario', 'departamento', 'incluye', 'no_incluye', 'recomendaciones', 'mapa',
            'frase_seo', 'tipo', 'video', 'picos', 'dificultad','idTipo', 'keywords'
        ];
        $values = [];
        foreach ($fields as $f) {
            $values[$f] = $_POST[$f] ?? '';
        }

        // Imagen portada
        $imgPortada = null;
        if (!empty($_FILES['imagen_portada']['name'])) {
            $old = $conn->query("SELECT imagen_portada FROM tours WHERE id = $id")->fetch_assoc();
            if ($old && $old['imagen_portada']) {
                $oldPath = $uploadsPath . $old['imagen_portada'];
                if (file_exists($oldPath)) unlink($oldPath);
            }

            $safeName = time() . "-" . preg_replace('/\s+/', '_', $_FILES['imagen_portada']['name']);
            move_uploaded_file($_FILES['imagen_portada']['tmp_name'], $uploadsPath . $safeName);
            $imgPortada = $safeName;
        }

        // Documento itinerario
        $docItinerario = null;
        if (!empty($_FILES['doc_itinerario']['name'])) {
            $old = $conn->query("SELECT doc_itinerario FROM tours WHERE id = $id")->fetch_assoc();
            if ($old && $old['doc_itinerario']) {
                $oldPath = $docsPath . $old['doc_itinerario'];
                if (file_exists($oldPath)) unlink($oldPath);
            }

            $safeName = time() . "-" . preg_replace('/\s+/', '_', $_FILES['doc_itinerario']['name']);
            move_uploaded_file($_FILES['doc_itinerario']['tmp_name'], $docsPath . $safeName);
            $docItinerario = $safeName;
        }

        // Actualizar tour
        $stmt = $conn->prepare("
            UPDATE tours SET 
                nombre=?, slug=?, idioma=?, parrafo=?, descripcion=?, precio=?, precio_enganche=?, duracion=?, itinerario=?, 
                departamento=?, incluye=?, no_incluye=?, recomendaciones=?, mapa=?, 
                imagen_portada=COALESCE(?, imagen_portada), frase_seo=?, tipo=?, video=?, picos=?, dificultad=?, 
                doc_itinerario=COALESCE(?, doc_itinerario), idTipo=?, keywords=?
            WHERE id=?
        ");

        $stmt->bind_param(
            "sssssssssssssssssssssisi", 
            $values['nombre'],          
            $values['slug'],            
            $values['idioma'],          
            $values['parrafo'],         
            $values['descripcion'],     
            $values['precio'],          
            $values['precio_enganche'], 
            $values['duracion'],        
            $values['itinerario'],      
            $values['departamento'],    
            $values['incluye'],         
            $values['no_incluye'],      
            $values['recomendaciones'], 
            $values['mapa'],            
            $imgPortada,                
            $values['frase_seo'],       
            $values['tipo'],            
            $values['video'],           
            $values['picos'],           
            $values['dificultad'],      
            $docItinerario,             
            $values['idTipo'],                    
            $values['keywords'],        
            $id                         
        );
        $stmt->execute();
        //update preguntas frecuentes
        $preguntasRaw = $_POST['preguntas'] ?? '[]';
        $preguntasArray = json_decode($preguntasRaw, true);
        $conn->query("DELETE FROM tour_faqs WHERE tour_id = $id");
        if (is_array($preguntasArray) && !empty($preguntasArray)) {
            $stmtPreg = $conn->prepare("INSERT INTO tour_faqs (tour_id,  question, answer, display_order) VALUES (?, ?, ?, ?)");
            foreach ($preguntasArray as $item) {
                $preguntaTxt = $item['pregunta'] ?? '';
                $respuestaTxt = $item['respuesta'] ?? '';
                $orden = $item['orden'] ?? 0;

                if (!empty($preguntaTxt)) {
                    $stmtPreg->bind_param("issi", $id, $preguntaTxt, $respuestaTxt, $orden);
                    $stmtPreg->execute();
                }
            }
        }

        // Galería actual en BD
        $galeriaBD = $conn->query("SELECT id, url FROM tours_galeria WHERE tour_id = $id")->fetch_all(MYSQLI_ASSOC);

        // Insertar nuevos archivos
        if (isset($_FILES['galeria'])) {
            foreach ($_FILES['galeria']['name'] as $i => $name) {
                if ($_FILES['galeria']['error'][$i] === 0) {
                    $safeName = time() . "-" . preg_replace('/\s+/', '_', $name);
                    move_uploaded_file($_FILES['galeria']['tmp_name'][$i], $uploadsPath . $safeName);
                    $stmtGaleria = $conn->prepare("INSERT INTO tours_galeria (tour_id, nombre, url) VALUES (?, ?, ?)");
                    $stmtGaleria->bind_param("iss", $id, $name, $safeName);
                    $stmtGaleria->execute();
                }
            }
        }

        // Verificar galeria[] JSONs enviados
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
                $conn->query("DELETE FROM tours_galeria WHERE id = {$item['id']}");
                $filePath = $uploadsPath . $item['url'];
                if (file_exists($filePath)) unlink($filePath);
            }
        }

        http_response_code(200);
        echo json_encode([
            'message' => 'Formulario actualizado con éxito',
            'id' => $id
        ], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error interno', 'details' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Método no permitido'], JSON_UNESCAPED_UNICODE);
?>