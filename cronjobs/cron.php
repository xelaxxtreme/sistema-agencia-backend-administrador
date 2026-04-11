<?php
require_once __DIR__ . '/../config/db.php';

function obtener_tasa_cambio() {
    $url = 'https://openexchangerates.org/api/latest.json?app_id=b72cd02b6a89434da078df74c40d46e4';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        error_log("Error cURL: " . curl_error($ch));
        curl_close($ch);
        return;
    }

    curl_close($ch);

    $data = json_decode($response, true);

    if (!$data || !isset($data['rates']['PEN'])) {
        error_log("Respuesta inválida API");
        return;
    }

    guardar_tasa($data['rates']['PEN']);
}

function guardar_tasa($tasa) {
    global $conn;
    
    $suma = round($tasa + 0.0645,3);
    $stmt = $conn->prepare("UPDATE tipocambio SET tasa=? WHERE id = 1");

    if (!$stmt) {
        error_log("Error prepare: " . $conn->error);
        return;
    }

    $stmt->bind_param("d", $suma);

    if (!$stmt->execute()) {
        error_log("Error execute: " . $stmt->error);
    }

    $stmt->close();
}

obtener_tasa_cambio();
?>