<?php
// obtener_pagos.php (RF-07): consulta historial de pagos registrados y estado financiero por evento
require_once 'auth_check.php';
header('Content-Type: application/json; charset=utf-8');
require_once 'conexion.php';

requerir_rol(['Administrador', 'Cliente'], true);
$rol = $_SESSION['rol'];
$id_cliente = intval($_SESSION['id_cliente'] ?? 0);

$sql = "SELECT p.id_pago, p.id_evento, p.monto, p.tipo, p.metodo, p.referencia, p.fecha, p.estatus,
               e.fecha AS fecha_evento, e.estado AS estado_evento,
               c.nombre_evento, c.monto_total, cl.nombre AS cliente
        FROM pagos p
        JOIN eventos e ON p.id_evento = e.id_evento
        JOIN contratos ct ON e.id_contrato = ct.id_contrato
        JOIN cotizaciones c ON ct.id_cotizacion = c.id_cotizacion
        JOIN clientes cl ON c.id_cliente = cl.id_cliente";

if ($rol === 'Cliente') {
    $sql .= " WHERE c.id_cliente = ?";
}
$sql .= " ORDER BY p.fecha DESC, p.id_pago DESC";

$stmt = $conexion->prepare($sql);
if ($rol === 'Cliente') {
    $stmt->bind_param("i", $id_cliente);
}
$stmt->execute();
$res = $stmt->get_result();

$pagos = [];
while ($row = $res->fetch_assoc()) {
    $pagos[] = $row;
}

echo json_encode(["success" => true, "pagos" => $pagos]);
?>
