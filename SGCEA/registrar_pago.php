<?php
// registrar_pago.php
session_start();
header('Content-Type: application/json');
require_once 'conexion.php';

if (($_SESSION['rol'] ?? '') !== 'Administrador') {
    echo json_encode(["success" => false, "message" => "Solo el administrador puede registrar pagos."]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$id_evento = $data['id_evento'] ?? null;
$monto = floatval($data['monto'] ?? 0);
$tipo = $data['tipo'] ?? ''; // 'Anticipo 50%' o 'Pago Final'
$metodo = $data['metodo'] ?? ''; // 'Efectivo', 'Transferencia', 'Tarjeta'
$referencia = trim($data['referencia'] ?? '');
$fecha = $data['fecha'] ?? date('Y-m-d');

if (!$id_evento || $monto <= 0 || empty($tipo) || empty($metodo)) {
    echo json_encode(["success" => false, "message" => "Ingrese un monto y datos de pago válidos."]);
    exit;
}

$st = $conexion->prepare("SELECT estado FROM eventos WHERE id_evento = ?");
$st->bind_param("i", $id_evento);
$st->execute();
$ev = $st->get_result()->fetch_assoc();
if (!$ev || $ev['estado'] === 'Cancelado') {
    echo json_encode(["success" => false, "message" => "No se pueden registrar pagos en un evento cancelado."]);
    exit;
}

// Validar que el pago acumulado no supere el monto total del servicio
$stmt_m = $conexion->prepare("SELECT c.monto_total,
                                     COALESCE((SELECT SUM(p.monto) FROM pagos p WHERE p.id_evento = e.id_evento), 0) AS total_pagado
                              FROM eventos e 
                              JOIN contratos ct ON e.id_contrato = ct.id_contrato 
                              JOIN cotizaciones c ON ct.id_cotizacion = c.id_cotizacion 
                              WHERE e.id_evento = ?");
$stmt_m->bind_param("i", $id_evento);
$stmt_m->execute();
$res_m = $stmt_m->get_result()->fetch_assoc();

if ($res_m) {
    $total_servicio = floatval($res_m['monto_total']);
    $ya_pagado = floatval($res_m['total_pagado']);
    $restante = round($total_servicio - $ya_pagado, 2);
    if ($monto > $restante) {
        echo json_encode([
            "success" => false,
            "message" => "El monto ingresado excede el saldo pendiente del evento ($" . number_format($restante, 2) . " MXN)."
        ]);
        exit;
    }
}

$stmt = $conexion->prepare("INSERT INTO pagos (id_evento, monto, tipo, metodo, referencia, fecha, estatus) VALUES (?, ?, ?, ?, ?, ?, 'Pagado')");
$stmt->bind_param("idssss", $id_evento, $monto, $tipo, $metodo, $referencia, $fecha);

if ($stmt->execute()) {
    echo json_encode(["success" => true, "message" => "Pago registrado correctamente."]);
} else {
    echo json_encode(["success" => false, "message" => "Error al registrar el pago: " . $conexion->error]);
}
?>