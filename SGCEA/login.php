<?php
session_start();
require_once "conexion.php";

$error = "";
$paqueteSeleccionado = trim($_GET['paquete'] ?? $_POST['paquete'] ?? '');

function verificarPassword($passwordIngresada, $passwordAlmacenada) {
    $passwordAlmacenada = trim($passwordAlmacenada);
    if (preg_match('/^\$2y\$|^\$argon2/', $passwordAlmacenada)) {
        return password_verify($passwordIngresada, $passwordAlmacenada);
    }
    if (preg_match('/^[a-f0-9]{32}$/i', $passwordAlmacenada)) {
        return strtolower(md5($passwordIngresada)) === strtolower($passwordAlmacenada);
    }
    return $passwordIngresada === $passwordAlmacenada;
}

if (isset($_SESSION['id_usuario'])) {
    $redir = !empty($paqueteSeleccionado) ? "panel.php?paquete=" . urlencode($paqueteSeleccionado) : "panel.php";
    header("Location: " . $redir);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!empty($email) && !empty($password)) {
        $stmt = $conexion->prepare("SELECT id_usuario, nombre, password, rol, id_cliente FROM usuarios WHERE email = ?");
        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($user = $result->fetch_assoc()) {
                if (verificarPassword($password, $user['password'])) {
                    session_regenerate_id(true);
                    $_SESSION['id_usuario'] = $user['id_usuario'];
                    $_SESSION['nombre'] = $user['nombre'];
                    $_SESSION['rol'] = $user['rol'];
                    $_SESSION['id_cliente'] = $user['id_cliente'];

                    // Migrar contraseña a bcrypt si estaba en texto plano o MD5
                    if (!preg_match('/^\$2y\$|^\$argon2/', trim($user['password']))) {
                        $nuevoHash = password_hash($password, PASSWORD_DEFAULT);
                        $updHash = $conexion->prepare("UPDATE usuarios SET password = ? WHERE id_usuario = ?");
                        if ($updHash) {
                            $updHash->bind_param("si", $nuevoHash, $user['id_usuario']);
                            $updHash->execute();
                            $updHash->close();
                        }
                    }

                    $redir = !empty($paqueteSeleccionado) ? "panel.php?paquete=" . urlencode($paqueteSeleccionado) : "panel.php";
                    header("Location: " . $redir);
                    exit;
                } else {
                    $error = "Contraseña incorrecta.";
                }
            } else {
                $error = "El correo electrónico no se encuentra registrado.";
            }
            $stmt->close();
        } else {
            $error = "Error al preparar la consulta: " . $conexion->error;
        }
    } else {
        $error = "Por favor completa todos los campos.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - SGCEA | Quinto Canal de Eventos</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="Styles.css">
</head>
<body class="auth-page">

<header class="site-header">
    <div class="header-inner">
        <a href="index.html" class="logo">
            <span class="logo-badge">QC</span>
            <span>Quinto Canal <small>Eventos &amp; Audiovisual</small></span>
        </a>
        <nav id="nav-menu">
            <a href="index.html">Inicio</a>
            <a href="registro.php<?= !empty($paqueteSeleccionado) ? '?paquete=' . urlencode($paqueteSeleccionado) : '' ?>" class="btn-nav">Crear Cuenta</a>
        </nav>
    </div>
</header>

<main class="auth-wrapper">
    <div class="auth-box">
        <div class="auth-header">
            <span class="pill-tag">Portal SGCEA</span>
            <h2>Iniciar Sesión</h2>
            <p>Ingresa tus credenciales para acceder a tu panel de gestión.</p>
        </div>

        <?php if (!empty($paqueteSeleccionado)): ?>
            <div class="alert-info">
                <strong>Paquete seleccionado:</strong> <?= htmlspecialchars($paqueteSeleccionado) ?><br>
                <span>Inicia sesión para aplicar este paquete a tu solicitud de cotización.</span>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert-danger" style="text-align: center;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php<?= !empty($paqueteSeleccionado) ? '?paquete=' . urlencode($paqueteSeleccionado) : '' ?>">
            <input type="hidden" name="paquete" value="<?= htmlspecialchars($paqueteSeleccionado) ?>">
            <div class="form-group">
                <label for="email">Correo Electrónico</label>
                <input type="email" id="email" name="email" required placeholder="usuario@ejemplo.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" required placeholder="••••••••">
            </div>

            <button type="submit" class="btn btn-primary-full">Ingresar al Sistema</button>
        </form>

        <div class="auth-footer">
            <p>¿Aún no tienes cuenta? <a href="registro.php<?= !empty($paqueteSeleccionado) ? '?paquete=' . urlencode($paqueteSeleccionado) : '' ?>">Regístrate gratis aquí</a></p>
        </div>
    </div>
</main>

</body>
</html>
