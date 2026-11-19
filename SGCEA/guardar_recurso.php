<?php
// guardar_recurso.php: permite al Administrador dar de alta nuevos recursos al inventario
session_start();
header('Content-Type: application/json');
require_once 'conexion.php';

if (($_SESSION['rol'] ?? '') !== 'Administrador') {
    echo json_encode(["success" => false, "message" => "Solo el administrador puede agregar recursos."]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$nombre = trim($data['nombre'] ?? '');
$tipo   = trim($data['tipo'] ?? '');
$cantidad = max(1, min(50, intval($data['cantidad'] ?? 1)));

$tiposValidos = ['Audio', 'Iluminación', 'Video', 'Personal', 'Transporte'];
if ($nombre === '' || !in_array($tipo, $tiposValidos)) {
    echo json_encode(["success" => false, "message" => "Indique un nombre y un tipo de recurso válido."]);
    exit;
}

$stmt = $conexion->prepare("INSERT INTO recursos (nombre, tipo) VALUES (?, ?)");
$insertados = 0;
for ($i = 1; $i <= $cantidad; $i++) {
    $nombreFinal = ($cantidad > 1) ? "$nombre #$i" : $nombre;
    $stmt->bind_param("ss", $nombreFinal, $tipo);
    if ($stmt->execute()) $insertados++;
}

echo json_encode([
    "success" => true,
    "message" => $insertados > 1
        ? "Se registraron $insertados unidades de '$nombre' en $tipo."
        : "Recurso '$nombre' registrado correctamente."
]);
?>
