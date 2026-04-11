<?php
require_once '../config/db.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

function generateSlug($nombre) {
    $slug = strtolower($nombre);
    $slug = iconv('UTF-8', 'ASCII//TRANSLIT', $slug); // elimina acentos
    $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug); // elimina símbolos
    $slug = trim($slug);
    $slug = preg_replace('/\s+/', '-', $slug); // reemplaza espacios por guiones
    return $slug;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

try {
    // 1️⃣ Obtener todos los tours sin slug
    $result = $conn->query("SELECT id, nombre FROM tours WHERE slug IS NULL OR slug = ''");
    $rows = $result->fetch_all(MYSQLI_ASSOC);

    if (count($rows) === 0) {
        echo json_encode(['message' => 'No hay tours sin slug.']);
        exit;
    }

    // 2️⃣ Generar y actualizar slugs
    foreach ($rows as $row) {
        $baseSlug = generateSlug($row['nombre']);
        $slug = $baseSlug;
        $suffix = 1;

        // Verificar unicidad
        $stmt = $conn->prepare("SELECT id FROM tours WHERE slug = ? AND id != ?");
        $stmt->bind_param("si", $slug, $row['id']);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_all();

        while (count($existing) > 0) {
            $slug = $baseSlug . '-' . $suffix++;
            $stmt->bind_param("si", $slug, $row['id']);
            $stmt->execute();
            $existing = $stmt->get_result()->fetch_all();
        }

        // Actualizar el registro
        $update = $conn->prepare("UPDATE tours SET slug = ? WHERE id = ?");
        $update->bind_param("si", $slug, $row['id']);
        $update->execute();
    }

    echo json_encode([
        'message' => 'Slugs generados correctamente',
        'total' => count($rows)
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error generando slugs: ' . $e->getMessage()]);
}
?>