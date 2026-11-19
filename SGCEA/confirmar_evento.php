<?php
// confirmar_evento.php (RF-05)
require_once 'auth_check.php';
header('Content-Type: application/json; charset=utf-8');
require_once 'conexion.php';

requerir_rol(['Administrador', 'Tecnico'], true);

$data = json_decode(file_get_contents("php://input"), true);
$id_evento = intval($data['id_evento'] ?? 0);

if (!$id_evento) {
    echo json_encode(["success" => false, "message" => "Seleccione un evento válido."]);
    exit;
}

$st = $conexion->prepare("SELECT estado FROM eventos WHERE id_evento = ?");
$st->bind_param("i", $id_evento);
$st->execute();
$ev = $st->get_result()->fetch_assoc();
if (!$ev || !in_array($ev['estado'], ['Programado', 'Reprogramado'], true)) {
    echo json_encode(["success" => false, "message" => "Solo se pueden confirmar eventos Programados o Reprogramados."]);
    exit;
}

// Consultar monto total y suma acumulada de pagos realizados
$query = "SELECT c.monto_total, 
                 COALESCE(SUM(p.monto), 0) AS total_pagado
          FROM eventos e
          JOIN contratos ct ON e.id_contrato = ct.id_contrato
          JOIN cotizaciones c ON ct.id_cotizacion = c.id_cotizacion
          LEFT JOIN pagos p ON e.id_evento = p.id_evento AND p.estatus = 'Pagado'
          WHERE e.id_evento = ?";

$stmt = $conexion->prepare($query);
$stmt->bind_param("i", $id_evento);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();

$monto_total = floatval($res['monto_total'] ?? 0);
$total_pagado = floatval($res['total_pagado'] ?? 0);
$anticipo_requerido = round($monto_total * 0.50, 2);

if ($total_pagado < $anticipo_requerido) {
    echo json_encode([
        "success" => false, 
        "message" => "No se puede confirmar el evento. Se requiere al menos el 50% de anticipo ($" . number_format($anticipo_requerido, 2) . " MXN). Pagado actual: $" . number_format($total_pagado, 2) . " MXN."
    ]);
    exit;
}

$update = $conexion->prepare("UPDATE eventos SET estado = 'Confirmado' WHERE id_evento = ?");
$update->bind_param("i", $id_evento);

if ($update->execute()) {
    echo json_encode(["success" => true, "message" => "Evento #$id_evento confirmado correctamente con anticipo del 50% validado."]);
} else {
    echo json_encode(["success" => false, "message" => "Error al actualizar el estado del evento."]);
}
?>
