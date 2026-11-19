<?php
// obtener_paquetes.php: devuelve los paquetes predeterminados de audio e iluminación
@ini_set('display_errors', '0');
error_reporting(0);
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
header('Content-Type: application/json; charset=utf-8');

$paquetesRespaldo = [
    [
        "id_paquete" => 1,
        "nombre" => "Paquete Audio Esencial (Hasta 80 personas)",
        "categoria" => "Audio",
        "descripcion" => '2 Bocinas amplificadas de 15" en tripié, consola mezcladora de 8 canales, 2 micrófonos inalámbricos Shure, cableado completo y técnico operador en sitio.',
        "servicios_incluidos" => "Audio",
        "costo" => "4500.00"
    ],
    [
        "id_paquete" => 2,
        "nombre" => "Paquete Audio Pro Concierto / Conferencia (Hasta 250 personas)",
        "categoria" => "Audio",
        "descripcion" => 'Sistema Line Array JBL (4 módulos + 2 subwoofers de 18"), consola digital Yamaha QL5, 4 micrófonos inalámbricos, monitores de piso e ingeniero de audio en sala.',
        "servicios_incluidos" => "Audio",
        "costo" => "12500.00"
    ],
    [
        "id_paquete" => 3,
        "nombre" => "Paquete Iluminación Ambiental & Fiesta",
        "categoria" => "Iluminación",
        "descripcion" => "8 Par LED RGBW arquitectónicos, 4 cabezas móviles Beam 230W en estructura truss vertical, controlador DMX y máquina de humo.",
        "servicios_incluidos" => "Iluminación",
        "costo" => "5200.00"
    ],
    [
        "id_paquete" => 4,
        "nombre" => "Paquete Iluminación Escénica Espectáculo",
        "categoria" => "Iluminación",
        "descripcion" => "Puente truss de 6 metros, 8 cabezas móviles Beam 230W, 12 Par LED RGBW, 2 máquinas de chispas frías, controlador DMX GrandMA2 y operador dedicado.",
        "servicios_incluidos" => "Iluminación",
        "costo" => "11800.00"
    ],
    [
        "id_paquete" => 5,
        "nombre" => "Paquete Combo Audio + Iluminación Básico",
        "categoria" => "Audio e Iluminación",
        "descripcion" => 'Audio para 100 personas (2 bafles activos + subwoofer 18" + 2 micrófonos inalámbricos) + Iluminación ambiental (6 Par LED RGBW + 2 cabezas móviles Beam + humo) con técnico operador.',
        "servicios_incluidos" => "Audio,Iluminación",
        "costo" => "8500.00"
    ],
    [
        "id_paquete" => 6,
        "nombre" => "Paquete Combo Audio + Iluminación Gala / Gran Evento",
        "categoria" => "Audio e Iluminación",
        "descripcion" => "Sistema Line Array completo + consola digital + 4 micrófonos Shure + puente truss con 8 cabezas móviles Beam, 12 Par LED, chispas frías, transporte y 2 técnicos especializados.",
        "servicios_incluidos" => "Audio,Iluminación",
        "costo" => "21500.00"
    ]
];

$paquetes = [];

try {
    if (file_exists(__DIR__ . '/conexion.php')) {
        require_once __DIR__ . '/conexion.php';
    }

    if (isset($conexion) && $conexion instanceof mysqli && !$conexion->connect_error) {
        $check = @$conexion->query("SHOW TABLES LIKE 'paquetes_predeterminados'");
        if ($check && $check->num_rows > 0) {
            $res = @$conexion->query("SELECT id_paquete, nombre, categoria, descripcion, servicios_incluidos, costo FROM paquetes_predeterminados ORDER BY categoria, costo ASC");
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $paquetes[] = $row;
                }
            }
        }
    }
} catch (Throwable $e) {
    // Se usará el respaldo automáticamente
}

if (empty($paquetes)) {
    $paquetes = $paquetesRespaldo;
}

echo json_encode(["success" => true, "paquetes" => $paquetes], JSON_UNESCAPED_UNICODE);
?>
