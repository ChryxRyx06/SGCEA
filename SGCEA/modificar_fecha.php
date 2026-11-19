<?php
// modificar_fecha.php: cambio de fecha de un evento (y de su contrato).
// Cliente: solo sus eventos y con la anticipación mínima. Administrador: sin restricción de anticipación.
session_start();
header('Content-Type: application/json');
require_once 'conexion.php';
require_once 'politica_cancelacion.php';

$rol = $_SESSION['rol'] ?? '';
if (!in_array($rol, ['Administrador', 'Cliente'])) {
    echo json_encode(["success" => false, "message" => "No tiene permiso para modificar fechas."]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$id_evento = intval($data['id_evento'] ?? 0);
$nueva = $data['nueva_fecha'] ?? '';
$motivo = mb_substr(trim($data['motivo'] ?? ''), 0, 255);

$d = DateTime::createFromFormat('Y-m-d', $nueva);
if (!$id_evento || !$d || $d->format('Y-m-d') !== $nueva) {
    echo json_encode(["success" => false, "message" => "Indique una nueva fecha válida."]);
    exit;
}
if ($nueva <= date('Y-m-d')) {
    echo json_encode(["success" => false, "message" => "La nueva fecha debe ser posterior a hoy."]);
    exit;
}

$conexion->begin_transaction();
try {
    $stmt = $conexion->prepare("SELECT e.fecha, e.estado, ct.id_contrato, ct.estatus, c.id_cliente
                                FROM eventos e
                                JOIN contratos ct ON e.id_contrato = ct.id_contrato
                                JOIN cotizaciones c ON ct.id_cotizacion = c.id_cotizacion
                                WHERE e.id_evento = ? FOR UPDATE");
    $stmt->bind_param("i", $id_evento);
    $stmt->execute();
    $ev = $stmt->get_result()->fetch_assoc();

    if (!$ev) throw new RuntimeException("El evento no existe.");
    if ($rol === 'Cliente' && intval($ev['id_cliente']) !== intval($_SESSION['id_cliente'] ?? 0)) {
        throw new RuntimeException("No tiene permiso para modificar este evento.");
    }
    if ($ev['estatus'] === 'Cancelado' || in_array($ev['estado'], ['Cancelado', 'Finalizado'])) {
        throw new RuntimeException("No se puede cambiar la fecha de un evento cancelado o finalizado.");
    }
    if ($ev['fecha'] === $nueva) throw new RuntimeException("La nueva fecha es igual a la actual.");

    if ($rol === 'Cliente') {
        $dias = dias_para_evento($ev['fecha']);
        if ($dias < DIAS_MIN_REPROGRAMACION_CLIENTE) {
            throw new RuntimeException("El cambio de fecha debe solicitarse con al menos " . DIAS_MIN_REPROGRAMACION_CLIENTE . " días de anticipación. Comuníquese con la empresa.");
        }
    }

    // Los recursos ya asignados deben estar libres en la nueva fecha (RF-09)
    $chk = $conexion->prepare("SELECT r.nombre
                               FROM asignacion_recursos mine
                               JOIN asignacion_recursos otro ON otro.id_recurso = mine.id_recurso
                                    AND otro.fecha = ? AND otro.id_evento <> mine.id_evento
                               JOIN recursos r ON r.id_recurso = mine.id_recurso
                               WHERE mine.id_evento = ?");
    $chk->bind_param("si", $nueva, $id_evento);
    $chk->execute();
    $conf = [];
    $rs = $chk->get_result();
    while ($r = $rs->fetch_assoc()) $conf[] = $r['nombre'];
    if ($conf) {
        throw new RuntimeException("No hay disponibilidad en esa fecha. Recursos ocupados: " . implode(', ', $conf) . ".");
    }

    $anterior = $ev['fecha'];
    $u1 = $conexion->prepare("UPDATE eventos SET fecha = ?, estado = 'Reprogramado' WHERE id_evento = ?");
    $u1->bind_param("si", $nueva, $id_evento);
    $u1->execute();

    $u2 = $conexion->prepare("UPDATE contratos SET fecha_evento = ? WHERE id_contrato = ?");
    $u2->bind_param("si", $nueva, $ev['id_contrato']);
    $u2->execute();

    $u3 = $conexion->prepare("UPDATE asignacion_recursos SET fecha = ? WHERE id_evento = ?");
    $u3->bind_param("si", $nueva, $id_evento);
    $u3->execute();

    $uid = intval($_SESSION['id_usuario'] ?? 0) ?: null;
    $log = $conexion->prepare("INSERT INTO modificaciones_fecha (id_evento, fecha_anterior, fecha_nueva, motivo, id_usuario) VALUES (?, ?, ?, ?, ?)");
    $log->bind_param("isssi", $id_evento, $anterior, $nueva, $motivo, $uid);
    $log->execute();

    $conexion->commit();
    echo json_encode(["success" => true, "message" => "Fecha modificada de $anterior a $nueva. El evento quedó en estado Reprogramado."]);
} catch (RuntimeException $e) {
    $conexion->rollback();
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
} catch (Exception $e) {
    $conexion->rollback();
    echo json_encode(["success" => false, "message" => "Error al modificar la fecha: " . $e->getMessage()]);
}
