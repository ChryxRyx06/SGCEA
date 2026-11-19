<?php
// asignar_recurso.php (RF-08 / RF-09)
session_start();
header('Content-Type: application/json');
require_once 'conexion.php';

if (!in_array($_SESSION['rol'] ?? '', ['Administrador', 'Tecnico'])) {
    echo json_encode(["success" => false, "message" => "No tiene permiso para asignar recursos."]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$id_evento = intval($data['id_evento'] ?? 0);
$id_recurso = intval($data['id_recurso'] ?? 0);
$fecha = $data['fecha'] ?? '';

if (!$id_evento || !$id_recurso || empty($fecha)) {
    echo json_encode(["success" => false, "message" => "Faltan datos obligatorios."]);
    exit;
}

$st = $conexion->prepare("SELECT estado FROM eventos WHERE id_evento = ?");
$st->bind_param("i", $id_evento);
$st->execute();
$ev = $st->get_result()->fetch_assoc();
if (!$ev || in_array($ev['estado'], ['Cancelado', 'Finalizado'])) {
    echo json_encode(["success" => false, "message" => "No se pueden asignar recursos a un evento cancelado o finalizado."]);
    exit;
}

// Verificar estado operativo del recurso
$colCheck = $conexion->query("SHOW COLUMNS FROM recursos LIKE 'estado'");
if ($colCheck && $colCheck->num_rows > 0) {
    $stRec = $conexion->prepare("SELECT estado FROM recursos WHERE id_recurso = ?");
    $stRec->bind_param("i", $id_recurso);
    $stRec->execute();
    $rec = $stRec->get_result()->fetch_assoc();
    if (!$rec) {
        echo json_encode(["success" => false, "message" => "El recurso seleccionado no existe."]);
        exit;
    }
    if (in_array($rec['estado'], ['En reparación', 'Dañado'], true)) {
        echo json_encode(["success" => false, "message" => "No se puede asignar: el recurso se encuentra marcado como '" . $rec['estado'] . "'."]);
        exit;
    }
}

$stmt = $conexion->prepare("INSERT INTO asignacion_recursos (id_evento, id_recurso, fecha) VALUES (?, ?, ?)");
$stmt->bind_param("iis", $id_evento, $id_recurso, $fecha);

if ($stmt->execute()) {
    echo json_encode(["success" => true, "message" => "Recursos asignados correctamente."]);
} else {
    echo json_encode(["success" => false, "message" => "El recurso seleccionado ya está ocupado en esa fecha."]);
}
?>
