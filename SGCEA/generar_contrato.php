<?php
// generar_contrato.php (RF-04): genera el contrato y el evento a partir de una cotización Aprobada
require_once 'auth_check.php';
header('Content-Type: application/json; charset=utf-8');
require_once 'conexion.php';

requerir_rol(['Administrador'], true);

$data = json_decode(file_get_contents("php://input"), true);
$id_cotizacion = intval($data['id_cotizacion'] ?? 0);
$fecha_evento  = trim($data['fecha_evento'] ?? '');
$horario       = trim($data['horario'] ?? '18:00');
$tipo_evento   = trim($data['tipo_evento'] ?? 'Social / Privado');
$ubicacion     = trim($data['ubicacion'] ?? '');
$condiciones   = trim($data['condiciones'] ?? '');

if (!$id_cotizacion || empty($fecha_evento) || empty($ubicacion) || empty($condiciones)) {
    echo json_encode(["success" => false, "message" => "Complete todos los campos obligatorios del contrato."]);
    exit;
}

$d = DateTime::createFromFormat('Y-m-d', $fecha_evento);
if (!$d || $d->format('Y-m-d') !== $fecha_evento || $fecha_evento < date('Y-m-d')) {
    echo json_encode(["success" => false, "message" => "La fecha del evento no puede ser anterior a hoy."]);
    exit;
}

if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $horario)) {
    $horario = '18:00:00';
} elseif (strlen($horario) === 5) {
    $horario .= ':00';
}

$tiposPermitidos = ['Boda / Social', 'Concierto / Festival', 'Corporativo / Congreso', 'Graduación / Escolar', 'Gala / Privado'];
if (!in_array($tipo_evento, $tiposPermitidos, true)) {
    $tipo_evento = 'Gala / Privado';
}

// Verificar que la cotización esté Aprobada y vigente
$stmt = $conexion->prepare("SELECT estado, vigencia FROM cotizaciones WHERE id_cotizacion = ?");
$stmt->bind_param("i", $id_cotizacion);
$stmt->execute();
$cot = $stmt->get_result()->fetch_assoc();

if (!$cot || $cot['estado'] !== 'Aprobada') {
    echo json_encode(["success" => false, "message" => "Solo se pueden generar contratos a partir de cotizaciones Aprobadas."]);
    exit;
}

// Evitar duplicar un contrato ya existente para la misma cotización
$stmt_chk = $conexion->prepare("SELECT id_contrato FROM contratos WHERE id_cotizacion = ?");
$stmt_chk->bind_param("i", $id_cotizacion);
$stmt_chk->execute();
if ($stmt_chk->get_result()->fetch_assoc()) {
    echo json_encode(["success" => false, "message" => "Esta cotización ya tiene un contrato generado."]);
    exit;
}

$conexion->begin_transaction();

try {
    $stmt_c = $conexion->prepare("INSERT INTO contratos (id_cotizacion, fecha_evento, ubicacion, condiciones, estatus) VALUES (?, ?, ?, ?, 'Activo')");
    $stmt_c->bind_param("isss", $id_cotizacion, $fecha_evento, $ubicacion, $condiciones);
    $stmt_c->execute();
    $id_contrato = $stmt_c->insert_id;

    $stmt_e = $conexion->prepare("INSERT INTO eventos (id_contrato, fecha, horario, ubicacion, tipo_evento, estado) VALUES (?, ?, ?, ?, ?, 'Programado')");
    $stmt_e->bind_param("issss", $id_contrato, $fecha_evento, $horario, $ubicacion, $tipo_evento);
    $stmt_e->execute();

    $conexion->commit();
    echo json_encode([
        "success" => true,
        "message" => "Contrato #$id_contrato y Evento en estado Programado generados correctamente.",
        "id_contrato" => $id_contrato
    ]);
} catch (Exception $e) {
    $conexion->rollback();
    echo json_encode(["success" => false, "message" => "Error al generar contrato: " . $e->getMessage()]);
}
?>
