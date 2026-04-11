<?php
require_once '../config/db.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents("php://input"), true);
    $email = $input['email'] ?? '';
    $password = $input['password'] ?? '';

    $stmt = $conn->prepare("SELECT id, nombre, apellidos, password, rol FROM usuarios WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if (password_verify($password, $row['password'])) {
            echo json_encode(["success" => true, "id" => $row['id'], "rol" => $row['rol'], "nombre"=>$row['nombre'], "apellidos"=>$row['apellidos']]);
            exit;
        }
    }
    echo json_encode(["success" => false, "message" => "Credenciales inválidas"]);
}