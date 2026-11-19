<?php
// finalizar_evento.php (RF-05 / RF-11): cierra un evento Confirmado y genera su registro de historial
session_start();
header('Content-Type: application/json');
require_once 'conexion.php';

if (!in_array($_SESSION['rol'] ?? '', ['Administrador', 'Tecnico'])) {
    echo json_encode(["success" => false, "message" => "No tiene permiso para finalizar eventos."]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$id_evento = intval($data['id_evento'] ?? 0);
$resultado = trim($data['resultado'] ?? 'Evento realizado sin incidentes.');
$seguimiento = trim($data['seguimiento'] ?? '');

if (!$id_evento) {
    echo json_encode(["success" => false, "message" => "Seleccione un evento válido."]);
    exit;
}

$stmt = $conexion->prepare("SELECT e.estado, GROUP_CONCAT(DISTINCT cs.servicio SEPARATOR ', ') AS servicios
                             FROM eventos e
                             JOIN contratos ct ON e.id_contrato = ct.id_contrato
                             JOIN cotizaciones c ON ct.id_cotizacion = c.id_cotizacion
                             LEFT JOIN cotizacion_servicios cs ON c.id_cotizacion = cs.id_cotizacion
                             WHERE e.id_evento = ?
                             GROUP BY e.id_evento");
$stmt->bind_param("i", $id_evento);
$stmt->execute();
$evento = $stmt->get_result()->fetch_assoc();

if (!$evento) {
    echo json_encode(["success" => false, "message" => "El evento no existe."]);
    exit;
}

if ($evento['estado'] !== 'Confirmado') {
    echo json_encode(["success" => false, "message" => "Solo se pueden finalizar eventos que estén Confirmados."]);
    exit;
}

$conexion->begin_transaction();
try {
    $update = $conexion->prepare("UPDATE eventos SET estado = 'Finalizado' WHERE id_evento = ?");
    $update->bind_param("i", $id_evento);
    $update->execute();

    $servicios = $evento['servicios'] ?: 'N/A';
    $stmt_h = $conexion->prepare("INSERT INTO historial_eventos (id_evento, servicios_brindados, resultado, seguimiento_cliente) VALUES (?, ?, ?, ?)");
    $stmt_h->bind_param("isss", $id_evento, $servicios, $resultado, $seguimiento);
    $stmt_h->execute();

    $conexion->commit();
    echo json_encode(["success" => true, "message" => "Evento finalizado y registrado en el historial."]);
} catch (Exception $e) {
    $conexion->rollback();
    echo json_encode(["success" => false, "message" => "Error al finalizar el evento: " . $e->getMessage()]);
}
?>
