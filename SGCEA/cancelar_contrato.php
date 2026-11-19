<?php
// cancelar_contrato.php: cancelación de contrato/evento aplicando la cláusula de descuento.
// La puede ejecutar el Cliente (solo sobre sus contratos) o el Administrador.
session_start();
header('Content-Type: application/json');
require_once 'conexion.php';
require_once 'politica_cancelacion.php';

$rol = $_SESSION['rol'] ?? '';
if (!in_array($rol, ['Administrador', 'Cliente'])) {
    echo json_encode(["success" => false, "message" => "No tiene permiso para cancelar contratos."]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$id_contrato = intval($data['id_contrato'] ?? 0);
$motivo = trim($data['motivo'] ?? '');

if (!$id_contrato) {
    echo json_encode(["success" => false, "message" => "Seleccione un contrato válido."]);
    exit;
}
if ($motivo === '') {
    echo json_encode(["success" => false, "message" => "Indique el motivo de la cancelación."]);
    exit;
}
$motivo = mb_substr($motivo, 0, 255);

$conexion->begin_transaction();
try {
    // Bloquea la fila para evitar cancelaciones dobles
    $stmt = $conexion->prepare("SELECT ct.estatus, ct.fecha_evento, c.id_cliente, c.monto_total,
                                       e.id_evento, e.estado AS estado_evento
                                FROM contratos ct
                                JOIN cotizaciones c ON ct.id_cotizacion = c.id_cotizacion
                                JOIN eventos e ON e.id_contrato = ct.id_contrato
                                WHERE ct.id_contrato = ? FOR UPDATE");
    $stmt->bind_param("i", $id_contrato);
    $stmt->execute();
    $ct = $stmt->get_result()->fetch_assoc();

    if (!$ct) throw new RuntimeException("El contrato no existe.");
    if ($rol === 'Cliente' && intval($ct['id_cliente']) !== intval($_SESSION['id_cliente'] ?? 0)) {
        throw new RuntimeException("No tiene permiso para cancelar este contrato.");
    }
    if ($ct['estatus'] === 'Cancelado') throw new RuntimeException("El contrato ya está cancelado.");
    if ($ct['estado_evento'] === 'Finalizado') throw new RuntimeException("No se puede cancelar un evento ya finalizado.");

    $id_evento = intval($ct['id_evento']);
    $stmt_p = $conexion->prepare("SELECT COALESCE(SUM(monto),0) AS pagado FROM pagos WHERE id_evento = ?");
    $stmt_p->bind_param("i", $id_evento);
    $stmt_p->execute();
    $pagado = floatval($stmt_p->get_result()->fetch_assoc()['pagado']);

    $calc = calcular_cancelacion(floatval($ct['monto_total']), $pagado, $ct['fecha_evento']);

    $upd_c = $conexion->prepare("UPDATE contratos SET estatus='Cancelado', fecha_cancelacion=NOW(), motivo_cancelacion=?,
                                 porcentaje_penalizacion=?, monto_penalizacion=?, monto_reembolso=?, monto_adeudo=?
                                 WHERE id_contrato=?");
    $upd_c->bind_param("sddddi", $motivo, $calc['porcentaje'], $calc['penalizacion'], $calc['reembolso'], $calc['adeudo'], $id_contrato);
    $upd_c->execute();

    $upd_e = $conexion->prepare("UPDATE eventos SET estado='Cancelado' WHERE id_evento=?");
    $upd_e->bind_param("i", $id_evento);
    $upd_e->execute();

    // Libera los recursos para que otros eventos puedan usarlos
    $del = $conexion->prepare("DELETE FROM asignacion_recursos WHERE id_evento=?");
    $del->bind_param("i", $id_evento);
    $del->execute();

    $conexion->commit();

    $msg = "Contrato #$id_contrato cancelado. Descuento por cancelación: {$calc['porcentaje']}% ($" . number_format($calc['penalizacion'], 2) . "). "
         . "Reembolso al cliente: $" . number_format($calc['reembolso'], 2) . ".";
    if ($calc['adeudo'] > 0) $msg .= " Saldo pendiente a cargo del cliente: $" . number_format($calc['adeudo'], 2) . ".";
    echo json_encode(["success" => true, "message" => $msg, "detalle" => $calc]);
} catch (RuntimeException $e) {
    $conexion->rollback();
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
} catch (Exception $e) {
    $conexion->rollback();
    echo json_encode(["success" => false, "message" => "Error al cancelar: " . $e->getMessage()]);
}
