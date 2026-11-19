<?php
// obtener_cotizaciones.php
require_once 'auth_check.php';
header('Content-Type: application/json; charset=utf-8');
require_once 'conexion.php';

requerir_sesion(true);

// Actualizar cotizaciones pendientes cuya vigencia expiró
$conexion->query("UPDATE cotizaciones SET estado = 'Vencida' WHERE estado = 'Pendiente' AND vigencia < CURDATE()");

$rol = $_SESSION['rol'] ?? 'Administrador';
$id_cliente = intval($_SESSION['id_cliente'] ?? 0);

$sql = "SELECT c.id_cotizacion, c.id_cliente, cl.nombre AS cliente, c.nombre_evento AS evento,
               c.descripcion_tecnica, c.monto_total, c.vigencia, c.estado,
               ct.id_contrato,
               GROUP_CONCAT(cs.servicio SEPARATOR ', ') AS servicios
        FROM cotizaciones c
        JOIN clientes cl ON c.id_cliente = cl.id_cliente
        LEFT JOIN cotizacion_servicios cs ON c.id_cotizacion = cs.id_cotizacion
        LEFT JOIN contratos ct ON ct.id_cotizacion = c.id_cotizacion";

if ($rol === 'Cliente') {
    $sql .= " WHERE c.id_cliente = ?";
}

$sql .= " GROUP BY c.id_cotizacion ORDER BY c.id_cotizacion DESC";

$stmt = $conexion->prepare($sql);
if ($rol === 'Cliente') {
    $stmt->bind_param("i", $id_cliente);
}
$stmt->execute();
$result = $stmt->get_result();

$cotizaciones = [];
while ($row = $result->fetch_assoc()) {
    $cotizaciones[] = $row;
}

echo json_encode(["success" => true, "rol" => $rol, "cotizaciones" => $cotizaciones]);
?>
