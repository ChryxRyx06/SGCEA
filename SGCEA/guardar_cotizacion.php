<?php
// guardar_cotizacion.php (RF-02): permite al Administrador generar cotizaciones y al Cliente solicitarlas
require_once 'auth_check.php';
header('Content-Type: application/json; charset=utf-8');
require_once 'conexion.php';

requerir_rol(['Administrador', 'Cliente'], true);
$rol = $_SESSION['rol'];

$data = json_decode(file_get_contents("php://input"), true);

$id_cliente = ($rol === 'Cliente')
    ? intval($_SESSION['id_cliente'] ?? 0)
    : intval($data['id_cliente'] ?? 0);

$nombre_evento = trim($data['nombre_evento'] ?? '');
$descripcion_tecnica = trim($data['descripcion'] ?? '');
$servicios = $data['servicios'] ?? [];
$monto_total = floatval($data['monto'] ?? 0);
$vigencia = trim($data['vigencia'] ?? '');

if (!$id_cliente || empty($nombre_evento) || empty($descripcion_tecnica) || empty($servicios) || $monto_total <= 0 || empty($vigencia)) {
    echo json_encode([
        "status" => "error",
        "success" => false,
        "message" => "Todos los campos de la cotización son obligatorios y debe seleccionar al menos un servicio."
    ]);
    exit;
}

$d = DateTime::createFromFormat('Y-m-d', $vigencia);
if (!$d || $d->format('Y-m-d') !== $vigencia || $vigencia < date('Y-m-d')) {
    echo json_encode([
        "status" => "error",
        "success" => false,
        "message" => "La fecha de vigencia debe ser igual o posterior a hoy."
    ]);
    exit;
}

$conexion->begin_transaction();

try {
    $stmt = $conexion->prepare("INSERT INTO cotizaciones (id_cliente, nombre_evento, descripcion_tecnica, monto_total, vigencia, estado) VALUES (?, ?, ?, ?, ?, 'Pendiente')");
    $stmt->bind_param("issds", $id_cliente, $nombre_evento, $descripcion_tecnica, $monto_total, $vigencia);
    $stmt->execute();
    $id_cotizacion = $stmt->insert_id;

    $stmt_srv = $conexion->prepare("INSERT INTO cotizacion_servicios (id_cotizacion, servicio) VALUES (?, ?)");
    foreach ($servicios as $srv) {
        $srvClean = trim($srv);
        if ($srvClean !== '') {
            $stmt_srv->bind_param("is", $id_cotizacion, $srvClean);
            $stmt_srv->execute();
        }
    }

    $conexion->commit();
    $msg = ($rol === 'Cliente')
        ? "Solicitud de cotización #$id_cotizacion enviada correctamente. Queda en estado Pendiente."
        : "Cotización #$id_cotizacion registrada en estado Pendiente.";
    echo json_encode(["success" => true, "message" => $msg, "id_cotizacion" => $id_cotizacion]);
} catch (Exception $e) {
    $conexion->rollback();
    echo json_encode(["success" => false, "message" => "Error al guardar la cotización: " . $e->getMessage()]);
}
?>
