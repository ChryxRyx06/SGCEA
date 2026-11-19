<?php
// guardar_cliente.php (RF-01)
session_start();
header('Content-Type: application/json');
require_once 'conexion.php';
require_once 'validaciones.php';

if (($_SESSION['rol'] ?? '') !== 'Administrador') {
    echo json_encode(["success" => false, "message" => "Solo el administrador puede gestionar clientes."]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

$id_cliente     = !empty($data['id_cliente']) ? intval($data['id_cliente']) : null;
$nombre         = trim($data['nombre'] ?? '');
$contacto       = trim($data['contacto'] ?? '');
$direccion      = trim($data['direccion'] ?? '');
$datos_fiscales = strtoupper(trim($data['datos_fiscales'] ?? ''));

if (empty($nombre) || empty($contacto) || empty($direccion) || empty($datos_fiscales)) {
    echo json_encode(["success" => false, "message" => "Todos los campos del cliente son obligatorios."]);
    exit;
}

if (!validar_telefono($contacto)) {
    echo json_encode(["success" => false, "message" => "Número de teléfono inválido. Debe tener exactamente 10 dígitos (ej. 272-123-4567)."]);
    exit;
}

if (!validar_rfc($datos_fiscales)) {
    echo json_encode(["success" => false, "message" => "RFC inválido. Debe cumplir con el formato oficial SAT (12 caracteres Persona Moral o 13 Persona Física)."]);
    exit;
}

$contactoFormateado = formatear_telefono($contacto);

// Evitar RFC duplicado en otro cliente
if ($id_cliente) {
    $chk = $conexion->prepare("SELECT id_cliente FROM clientes WHERE datos_fiscales = ? AND id_cliente <> ?");
    $chk->bind_param("si", $datos_fiscales, $id_cliente);
} else {
    $chk = $conexion->prepare("SELECT id_cliente FROM clientes WHERE datos_fiscales = ?");
    $chk->bind_param("s", $datos_fiscales);
}
$chk->execute();
if ($chk->get_result()->fetch_assoc()) {
    echo json_encode(["success" => false, "message" => "Ya existe otro cliente registrado con el RFC $datos_fiscales."]);
    exit;
}

if ($id_cliente) {
    // Actualizar cliente
    $stmt = $conexion->prepare("UPDATE clientes SET nombre = ?, contacto = ?, direccion = ?, datos_fiscales = ? WHERE id_cliente = ?");
    $stmt->bind_param("ssssi", $nombre, $contactoFormateado, $direccion, $datos_fiscales, $id_cliente);
    $accion = "actualizado";
} else {
    // Registrar nuevo cliente
    $stmt = $conexion->prepare("INSERT INTO clientes (nombre, contacto, direccion, datos_fiscales) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $nombre, $contactoFormateado, $direccion, $datos_fiscales);
    $accion = "registrado";
}

if ($stmt->execute()) {
    echo json_encode(["success" => true, "message" => "Cliente $accion correctamente."]);
} else {
    echo json_encode(["success" => false, "message" => "Error al procesar cliente: " . $conexion->error]);
}
?>
