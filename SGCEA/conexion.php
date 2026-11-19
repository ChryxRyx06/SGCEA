<?php
// conexion.php - Configuración dinámica para la nube (variables de entorno) y desarrollo local
$mysql_url = getenv('MYSQL_URL') ?: getenv('DATABASE_URL') ?: ($_ENV['MYSQL_URL'] ?? $_SERVER['MYSQL_URL'] ?? '');

if (!empty($mysql_url)) {
    $parts = parse_url($mysql_url);
    $host = $parts['host'] ?? 'localhost';
    $port = (int)($parts['port'] ?? 3306);
    $user = $parts['user'] ?? 'root';
    $pass = $parts['pass'] ?? '';
    $dbname = isset($parts['path']) ? ltrim($parts['path'], '/') : 'sgcea_db';
} else {
    $host = getenv('DB_HOST') ?: getenv('MYSQLHOST') ?: ($_ENV['DB_HOST'] ?? $_SERVER['DB_HOST'] ?? 'localhost');
    $port = (int)(getenv('DB_PORT') ?: getenv('MYSQLPORT') ?: ($_ENV['DB_PORT'] ?? $_SERVER['DB_PORT'] ?? 3306));
    $user = getenv('DB_USER') ?: getenv('MYSQLUSER') ?: ($_ENV['DB_USER'] ?? $_SERVER['DB_USER'] ?? 'root');
    $pass = getenv('DB_PASS') ?: getenv('MYSQLPASSWORD') ?: ($_ENV['DB_PASS'] ?? $_SERVER['DB_PASS'] ?? '');
    $dbname = getenv('DB_NAME') ?: getenv('MYSQLDATABASE') ?: ($_ENV['DB_NAME'] ?? $_SERVER['DB_NAME'] ?? 'sgcea_db');
}

mysqli_report(MYSQLI_REPORT_OFF);
$conexion = @new mysqli($host, $user, $pass, $dbname, $port);

if ($conexion->connect_error) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    die(json_encode([
        "status" => "error",
        "success" => false,
        "message" => "Error de conexión a la base de datos: " . $conexion->connect_error
    ]));
}

$conexion->set_charset("utf8mb4");
?>
