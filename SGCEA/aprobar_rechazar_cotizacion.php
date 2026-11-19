<?php
// aprobar_rechazar_cotizacion.php (RF-03)
require_once 'auth_check.php';
header('Content-Type: application/json; charset=utf-8');
require_once 'conexion.php';

requerir_rol(['Administrador', 'Cliente'], true);
$rol = $_SESSION['rol'];

$data = json_decode(file_get_contents("php://input"), true);
$id_cotizacion = intval($data['id_cotizacion'] ?? 0);
$accion = $data['accion'] ?? ''; // 'Aprobar' o 'Rechazar'

if (!$id_cotizacion || !in_array($accion, ['Aprobar', 'Rechazar'], true)) {
    echo json_encode(["success" => false, "message" => "Acción o cotización no válida."]);
    exit;
}

$stmt = $conexion->prepare("SELECT vigencia, estado, id_cliente FROM cotizaciones WHERE id_cotizacion = ?");
$stmt->bind_param("i", $id_cotizacion);
$stmt->execute();
$cot = $stmt->get_result()->fetch_assoc();

if (!$cot) {
    echo json_encode(["success" => false, "message" => "La cotización no existe."]);
    exit;
}

if ($rol === 'Cliente' && intval($cot['id_cliente']) !== intval($_SESSION['id_cliente'] ?? 0)) {
    echo json_encode(["success" => false, "message" => "Esta cotización no pertenece a su cuenta."]);
    exit;
}

if ($cot['estado'] !== 'Pendiente') {
    echo json_encode(["success" => false, "message" => "Esta cotización ya ha sido procesada ({$cot['estado']})."]);
    exit;
}

if (strtotime($cot['vigencia']) < strtotime(date('Y-m-d'))) {
    $update = $conexion->prepare("UPDATE cotizaciones SET estado = 'Vencida' WHERE id_cotizacion = ?");
    $update->bind_param("i", $id_cotizacion);
    $update->execute();
    echo json_encode(["success" => false, "message" => "La cotización ha vencido y no puede ser aprobada."]);
    exit;
}

$nuevo_estado = ($accion === 'Aprobar') ? 'Aprobada' : 'Rechazada';
$update = $conexion->prepare("UPDATE cotizaciones SET estado = ? WHERE id_cotizacion = ?");
$update->bind_param("si", $nuevo_estado, $id_cotizacion);

if ($update->execute()) {
    echo json_encode(["success" => true, "message" => "Cotización #$id_cotizacion marcada como " . strtolower($nuevo_estado) . "."]);
} else {
    echo json_encode(["success" => false, "message" => "Error al actualizar estado."]);
}
?>
