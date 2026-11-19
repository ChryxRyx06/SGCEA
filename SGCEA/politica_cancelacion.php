<?php
// politica_cancelacion.php
// Política única de cancelación y reprogramación. La usan: la cláusula impresa
// en ver_contrato.php, obtener_contratos.php (vista previa) y cancelar_contrato.php.
// Si se quiere cambiar los porcentajes basta con editar este arreglo.

// Cada tramo: [días mínimos de anticipación, % del monto total que se retiene]
// Se evalúa de mayor a menor anticipación.
const TRAMOS_CANCELACION = [
    [30, 10],
    [15, 25],
    [7, 50],
    [0, 100],
];

// Días mínimos de anticipación para que el CLIENTE pueda cambiar la fecha.
// El administrador no tiene esta restricción.
const DIAS_MIN_REPROGRAMACION_CLIENTE = 7;

function dias_para_evento($fecha_evento) {
    $hoy = new DateTime('today');
    $ev = new DateTime($fecha_evento);
    return (int) $hoy->diff($ev)->format('%r%a');
}

function porcentaje_penalizacion($dias) {
    foreach (TRAMOS_CANCELACION as [$min, $pct]) {
        if ($dias >= $min) return $pct;
    }
    return 100;
}

// Devuelve el desglose económico de cancelar hoy un contrato.
function calcular_cancelacion($monto_total, $total_pagado, $fecha_evento) {
    $dias = dias_para_evento($fecha_evento);
    $pct = porcentaje_penalizacion($dias);
    $penalizacion = round($monto_total * $pct / 100, 2);
    $retenido = min($penalizacion, $total_pagado);          // descuento aplicado a lo ya pagado
    $reembolso = round($total_pagado - $retenido, 2);       // lo que se devuelve al cliente
    $adeudo = round($penalizacion - $retenido, 2);          // penalización no cubierta por lo pagado
    return [
        'dias' => $dias,
        'porcentaje' => $pct,
        'penalizacion' => $penalizacion,
        'retenido' => $retenido,
        'reembolso' => $reembolso,
        'adeudo' => $adeudo,
    ];
}

// Texto de la cláusula, para el contrato impreso.
function texto_clausula_cancelacion() {
    $lineas = [];
    $prev = null;
    foreach (TRAMOS_CANCELACION as [$min, $pct]) {
        if ($min === 0) {
            $lineas[] = "con menos de " . $prev . " días de anticipación: " . $pct . "%";
        } elseif ($prev === null) {
            $lineas[] = "con " . $min . " días o más de anticipación: " . $pct . "%";
        } else {
            $lineas[] = "de " . $min . " a " . ($prev - 1) . " días de anticipación: " . $pct . "%";
        }
        $prev = $min;
    }
    return $lineas;
}
