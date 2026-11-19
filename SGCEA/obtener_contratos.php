<?php
// obtener_contratos.php: lista de contratos con su evento, pagos y vista previa de cancelación
session_start();
header('Content-Type: application/json');
require_once 'conexion.php';
require_once 'politica_cancelacion.php';

$rol = $_SESSION['rol'] ?? '';
if (!in_array($rol, ['Administrador', 'Cliente'])) {
    echo json_encode(["success" => false, "message" => "No tiene permiso para ver contratos."]);
    exit;
}

$sql = "SELECT ct.id_contrato, ct.estatus, ct.fecha_evento, ct.ubicacion,
               ct.fecha_cancelacion, ct.monto_penalizacion, ct.monto_reembolso, ct.monto_adeudo,
               c.nombre_evento, c.monto_total, cl.nombre AS cliente,
               e.id_evento, e.estado AS estado_evento,
               COALESCE((SELECT SUM(p.monto) FROM pagos p WHERE p.id_evento = e.id_evento), 0) AS total_pagado
        FROM contratos ct
        JOIN cotizaciones c ON ct.id_cotizacion = c.id_cotizacion
        JOIN clientes cl ON c.id_cliente = cl.id_cliente
        JOIN eventos e ON e.id_contrato = ct.id_contrato";

$id_cliente = null;
if ($rol === 'Cliente') {
    $id_cliente = intval($_SESSION['id_cliente'] ?? 0);
    $sql .= " WHERE c.id_cliente = ?";
}
$sql .= " ORDER BY ct.id_contrato DESC";

$stmt = $conexion->prepare($sql);
if ($rol === 'Cliente') $stmt->bind_param("i", $id_cliente);
$stmt->execute();
$res = $stmt->get_result();

$contratos = [];
while ($r = $res->fetch_assoc()) {
    $activo = ($r['estatus'] === 'Activo' && !in_array($r['estado_evento'], ['Finalizado', 'Cancelado']));
    $r['puede_modificar'] = $activo;
    $r['puede_cancelar'] = $activo;
    $r['cancelacion'] = $activo
        ? calcular_cancelacion(floatval($r['monto_total']), floatval($r['total_pagado']), $r['fecha_evento'])
        : null;
    $r['dias_para_evento'] = dias_para_evento($r['fecha_evento']);
    $r['min_dias_cliente'] = DIAS_MIN_REPROGRAMACION_CLIENTE;
    $contratos[] = $r;
}

echo json_encode(["success" => true, "rol" => $rol, "contratos" => $contratos]);
