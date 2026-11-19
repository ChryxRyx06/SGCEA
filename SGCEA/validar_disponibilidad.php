<?php
// validar_disponibilidad.php
session_start();
header('Content-Type: application/json');
require_once 'conexion.php';

if (!isset($_SESSION['id_usuario'])) {
    echo json_encode(["success" => false, "message" => "Debe iniciar sesión."]);
    exit;
}

$fecha = $_GET['fecha'] ?? date('Y-m-d');
$rol = $_SESSION['rol'] ?? '';

$d = DateTime::createFromFormat('Y-m-d', $fecha);
if (!$d || $d->format('Y-m-d') !== $fecha) {
    echo json_encode(["success" => false, "message" => "Proporcione una fecha válida (YYYY-MM-DD)."]);
    exit;
}

// Verificar si existe la columna estado en recursos (compatibilidad con BD previa)
$colCheck = $conexion->query("SHOW COLUMNS FROM recursos LIKE 'estado'");
$tieneColEstado = ($colCheck && $colCheck->num_rows > 0);
$campoEstado = $tieneColEstado ? "r.estado" : "'Disponible'";

$query = "SELECT r.id_recurso, r.nombre, r.tipo,
                 $campoEstado AS estado_recurso,
                 CASE
                     WHEN $campoEstado IN ('En reparación', 'Dañado') THEN $campoEstado
                     WHEN ar.id_asignacion IS NOT NULL THEN 'Ocupado'
                     ELSE 'Disponible'
                 END AS estado_disponibilidad,
                 e.id_evento, c.nombre_evento AS evento_asignado
          FROM recursos r
          LEFT JOIN asignacion_recursos ar ON r.id_recurso = ar.id_recurso AND ar.fecha = ?
          LEFT JOIN eventos e ON ar.id_evento = e.id_evento
          LEFT JOIN contratos ct ON e.id_contrato = ct.id_contrato
          LEFT JOIN cotizaciones c ON ct.id_cotizacion = c.id_cotizacion
          ORDER BY r.tipo, r.nombre";

$stmt = $conexion->prepare($query);
$stmt->bind_param("s", $fecha);
$stmt->execute();
$result = $stmt->get_result();

$recursos = [];
$porTipo = [];
$totalGeneral = 0;
$disponiblesGeneral = 0;
$ocupadosGeneral = 0;

while ($row = $result->fetch_assoc()) {
    $recursos[] = $row;
    $tipo = $row['tipo'];
    if (!isset($porTipo[$tipo])) {
        $porTipo[$tipo] = [
            'tipo' => $tipo,
            'total' => 0,
            'disponibles' => 0,
            'ocupados' => 0
        ];
    }
    $totalGeneral++;
    $porTipo[$tipo]['total']++;

    if ($row['estado_disponibilidad'] === 'Disponible') {
        $disponiblesGeneral++;
        $porTipo[$tipo]['disponibles']++;
    } else {
        $ocupadosGeneral++;
        $porTipo[$tipo]['ocupados']++;
    }
}

echo json_encode([
    "success" => true,
    "rol" => $rol,
    "fecha" => $fecha,
    "resumen_general" => [
        "total" => $totalGeneral,
        "disponibles" => $disponiblesGeneral,
        "ocupados" => $ocupadosGeneral
    ],
    "resumen_por_tipo" => array_values($porTipo),
    "recursos" => $recursos
]);
?>
