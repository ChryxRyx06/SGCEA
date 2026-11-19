<?php
// obtener_historial.php (RF-11)
session_start();
header('Content-Type: application/json');
require_once 'conexion.php';

if (!isset($_SESSION['id_usuario'])) {
    echo json_encode(["success" => false, "message" => "Debe iniciar sesión."]);
    exit;
}

$rol = $_SESSION['rol'] ?? '';
$id_cliente = $_SESSION['id_cliente'] ?? null;

$query = "SELECT h.id_historial, e.id_evento, cl.nombre AS cliente, e.fecha, e.tipo_evento,
                 h.servicios_brindados, h.resultado, h.seguimiento_cliente
          FROM historial_eventos h
          JOIN eventos e ON h.id_evento = e.id_evento
          JOIN contratos ct ON e.id_contrato = ct.id_contrato
          JOIN cotizaciones c ON ct.id_cotizacion = c.id_cotizacion
          JOIN clientes cl ON c.id_cliente = cl.id_cliente";

if ($rol === 'Cliente' && $id_cliente) {
    $query .= " WHERE c.id_cliente = " . intval($id_cliente);
}

$query .= " ORDER BY e.fecha DESC";

$result = $conexion->query($query);
$historial = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $historial[] = $row;
    }
}

echo json_encode(["success" => true, "historial" => $historial]);
?>
