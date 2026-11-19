<?php
// auth_check.php: Middleware centralizado de autenticación y control de acceso por rol (RF-10)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function requerir_sesion($esApi = true) {
    if (!isset($_SESSION['id_usuario'])) {
        if ($esApi) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(401);
            echo json_encode(["success" => false, "message" => "Sesión expirada o no iniciada. Por favor inicie sesión."]);
        } else {
            header("Location: login.php");
        }
        exit;
    }
}

function requerir_rol(array $rolesPermitidos, $esApi = true) {
    requerir_sesion($esApi);
    $rolActual = $_SESSION['rol'] ?? '';
    if (!in_array($rolActual, $rolesPermitidos, true)) {
        if ($esApi) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(403);
            echo json_encode(["success" => false, "message" => "Acceso denegado: no cuenta con los permisos necesarios."]);
        } else {
            header("Location: panel.php");
        }
        exit;
    }
}
?>
