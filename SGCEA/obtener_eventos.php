<?php
// obtener_eventos.php (RF-05 / RF-06 / RF-08): lista eventos con recursos asignados y estatus de pago
require_once 'auth_check.php';
header('Content-Type: application/json; charset=utf-8');
require_once 'conexion.php';

requerir_rol(['Administrador', 'Tecnico'], true);

$sql = "SELECT e.id_evento, e.id_contrato, e.fecha, e.horario, e.ubicacion, e.tipo_evento, e.estado,
               cl.nombre AS cliente, c.nombre_evento, c.monto_total,
               COALESCE((SELECT SUM(p.monto) FROM pagos p WHERE p.id_evento = e.id_evento), 0) AS total_pagado,
               COALESCE((SELECT GROUP_CONCAT(r.nombre SEPARATOR ', ')
                         FROM asignacion_recursos ar
                         JOIN recursos r ON ar.id_recurso = r.id_recurso
                         WHERE ar.id_evento = e.id_evento), 'Sin recursos asignados') AS recursos_asignados
        FROM eventos e
        JOIN contratos ct ON e.id_contrato = ct.id_contrato
        JOIN cotizaciones c ON ct.id_cotizacion = c.id_cotizacion
        JOIN clientes cl ON c.id_cliente = cl.id_cliente
        ORDER BY e.fecha DESC, e.id_evento DESC";

$res = $conexion->query($sql);
$eventos = [];
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $row['anticipo_cubierto'] = floatval($row['total_pagado']) >= (floatval($row['monto_total']) * 0.50);
        $eventos[] = $row;
    }
}

echo json_encode(["success" => true, "rol" => $_SESSION['rol'], "eventos" => $eventos]);
?>
