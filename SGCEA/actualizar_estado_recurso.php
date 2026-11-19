<?php
// actualizar_estado_recurso.php: actualiza el estado operativo de un recurso (Disponible, En reparación, Dañado)
session_start();
header('Content-Type: application/json');
require_once 'conexion.php';

if (!in_array($_SESSION['rol'] ?? '', ['Administrador', 'Tecnico'])) {
    echo json_encode(["success" => false, "message" => "No tiene permiso para actualizar el estado de los recursos."]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$id_recurso = intval($data['id_recurso'] ?? 0);
$estado = trim($data['estado'] ?? '');

$estadosValidos = ['Disponible', 'En reparación', 'Dañado'];
if ($id_recurso <= 0 || !in_array($estado, $estadosValidos, true)) {
    echo json_encode(["success" => false, "message" => "Datos o estado no válido."]);
    exit;
}

// Asegurar que exista la columna estado si es una BD migrada
$colCheck = $conexion->query("SHOW COLUMNS FROM recursos LIKE 'estado'");
if ($colCheck && $colCheck->num_rows === 0) {
    $conexion->query("ALTER TABLE recursos ADD COLUMN estado ENUM('Disponible','En reparación','Dañado') NOT NULL DEFAULT 'Disponible'");
}

$stmt = $conexion->prepare("UPDATE recursos SET estado = ? WHERE id_recurso = ?");
$stmt->bind_param("si", $estado, $id_recurso);

if ($stmt->execute()) {
    echo json_encode(["success" => true, "message" => "Estado del recurso actualizado a '$estado'."]);
} else {
    echo json_encode(["success" => false, "message" => "No se pudo actualizar el estado del recurso."]);
}
?>
