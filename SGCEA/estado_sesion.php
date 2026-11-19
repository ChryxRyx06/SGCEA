<?php
// estado_sesion.php: informa al frontend público si existe una sesión activa
session_start();
header('Content-Type: application/json; charset=utf-8');
if (isset($_SESSION['id_usuario'])) {
    echo json_encode([
        "autenticado" => true,
        "nombre" => $_SESSION['nombre'] ?? '',
        "rol" => $_SESSION['rol'] ?? ''
    ]);
} else {
    echo json_encode(["autenticado" => false]);
}
?>
