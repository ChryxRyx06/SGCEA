<?php
// validaciones.php: validaciones centralizadas de RFC (SAT) y número telefónico (México)

/**
 * Valida un RFC mexicano (Persona Moral: 12 caracteres, Persona Física: 13 caracteres)
 * incluyendo validación de fecha YYMMDD y homoclave de 3 caracteres alfanuméricos.
 */
function validar_rfc($rfc) {
    $rfc = strtoupper(trim($rfc));
    // Patrón oficial SAT: 3 o 4 letras (incluyendo Ñ y &), 6 dígitos fecha (YYMMDD), 3 caracteres homoclave
    if (!preg_match('/^([A-ZÑ&]{3,4})(\d{2})(0[1-9]|1[0-2])(0[1-9]|[12]\d|3[01])([A-Z\d]{3})$/', $rfc, $m)) {
        return false;
    }
    $anio = intval($m[2]);
    $mes  = intval($m[3]);
    $dia  = intval($m[4]);
    // Validar calendario real (ej. evitar 31 de febrero); usamos 2000+anio como año bisiesto de referencia para 00
    $anioCompleto = ($anio <= intval(date('y'))) ? (2000 + $anio) : (1900 + $anio);
    return checkdate($mes, $dia, $anioCompleto);
}

/**
 * Valida un número telefónico de 10 dígitos (acepta guiones, espacios, paréntesis o prefijo +52 / 52).
 */
function validar_telefono($telefono) {
    $soloDigitos = preg_replace('/\D+/', '', trim($telefono));
    // Si incluye código de país 52 (12 dígitos), extraer los últimos 10
    if (strlen($soloDigitos) === 12 && strpos($soloDigitos, '52') === 0) {
        $soloDigitos = substr($soloDigitos, 2);
    }
    // Debe tener exactamente 10 dígitos y no ser todos el mismo dígito (ej. 0000000000 o 1111111111)
    if (!preg_match('/^\d{10}$/', $soloDigitos)) {
        return false;
    }
    if (preg_match('/^(\d)\1{9}$/', $soloDigitos)) {
        return false;
    }
    return true;
}

/**
 * Normaliza el teléfono al formato XXX-XXX-XXXX para almacenamiento consistente.
 */
function formatear_telefono($telefono) {
    $soloDigitos = preg_replace('/\D+/', '', trim($telefono));
    if (strlen($soloDigitos) === 12 && strpos($soloDigitos, '52') === 0) {
        $soloDigitos = substr($soloDigitos, 2);
    }
    if (strlen($soloDigitos) === 10) {
        return substr($soloDigitos, 0, 3) . '-' . substr($soloDigitos, 3, 3) . '-' . substr($soloDigitos, 6, 4);
    }
    return trim($telefono);
}
?>
