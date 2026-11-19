<?php
// eliminar_recurso.php: permite solo al Administrador eliminar un recurso del inventario
session_start();
header('Content-Type: application/json');
require_once 'conexion.php';

if (($_SESSION['rol'] ?? '') !== 'Administrador') {
    echo json_encode(["success" => false, "message" => "Solo el administrador puede eliminar recursos."]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$id_recurso = intval($data['id_recurso'] ?? 0);

if ($id_recurso <= 0) {
    echo json_encode(["success" => false, "message" => "ID de recurso no válido."]);
    exit;
}

// Verificar si el recurso está asignado a eventos activos/próximos
$stCheck = $conexion->prepare("
    SELECT COUNT(*) AS total
    FROM asignacion_recursos ar
    JOIN eventos e ON ar.id_evento = e.id_evento
    WHERE ar.id_recurso = ?
      AND e.estado IN ('Programado', 'Confirmado', 'Reprogramado')
");
$stCheck->bind_param("i", $id_recurso);
$stCheck->execute();
$asignadosActivos = intval($stCheck->get_result()->fetch_assoc()['total'] ?? 0);

if ($asignadosActivos > 0) {
    echo json_encode([
        "success" => false,
        "message" => "No se puede eliminar el recurso porque está asignado a $asignadosActivos evento(s) activo(s)."
    ]);
    exit;
}

$stmt = $conexion->prepare("DELETE FROM recursos WHERE id_recurso = ?");
$stmt->bind_param("i", $id_recurso);

if ($stmt->execute() && $stmt->affected_rows > 0) {
    echo json_encode(["success" => true, "message" => "Recurso eliminado del inventario correctamente."]);
} else {
    echo json_encode(["success" => false, "message" => "No se encontró el recurso o ya fue eliminado."]);
}
?>
