<?php
$host = 'localhost';
$user = 'terresde_administrador';
$password = 'V{I~.WWYx]S5zJcv';
$database = 'terresde_incas';

$conn = new mysqli($host, $user, $password, $database);

if ($conn->connect_error) {
    die('Error de conexión: ' . $conn->connect_error);
}
$conn->set_charset('utf8');
?>