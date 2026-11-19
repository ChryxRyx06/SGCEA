<?php
session_start();
require_once "conexion.php";
require_once "validaciones.php";

$error = "";

$paqueteSeleccionado = trim($_GET['paquete'] ?? $_POST['paquete'] ?? '');


// Si ya hay sesión activa, redirigir al panel principal
if (isset($_SESSION['id_usuario'])) {
    $redir = !empty($paqueteSeleccionado) ? "panel.php?paquete=" . urlencode($paqueteSeleccionado) : "panel.php";
                    header("Location: " . $redir);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nombre         = trim($_POST['nombre'] ?? '');
    $contacto       = trim($_POST['contacto'] ?? '');
    $direccion      = trim($_POST['direccion'] ?? '');
    $datos_fiscales = strtoupper(trim($_POST['datos_fiscales'] ?? ''));
    $email          = trim($_POST['email'] ?? '');
    $password       = $_POST['password'] ?? '';
    $password2      = $_POST['password2'] ?? '';

    if (empty($nombre) || empty($contacto) || empty($direccion) || empty($datos_fiscales) || empty($email) || empty($password) || empty($password2)) {
        $error = "Por favor completa todos los campos.";
    } elseif (!validar_telefono($contacto)) {
        $error = "El número de teléfono no es válido. Debe contener exactamente 10 dígitos (ej. 272-123-4567).";
    } elseif (!validar_rfc($datos_fiscales)) {
        $error = "El RFC ingresado no es válido. Debe tener 12 caracteres (Persona Moral) o 13 caracteres (Persona Física) con formato oficial del SAT (ej. XAXX010101000).";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "El correo electrónico no es válido.";
    } elseif (strlen($password) < 6) {
        $error = "La contraseña debe tener al menos 6 caracteres.";
    } elseif ($password !== $password2) {
        $error = "Las contraseñas no coinciden.";
    } else {
        $contactoFormateado = formatear_telefono($contacto);

        // Verificar que el correo no esté registrado ya
        $stmt = $conexion->prepare("SELECT id_usuario FROM usuarios WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $error = "Ya existe una cuenta registrada con ese correo electrónico.";
            $stmt->close();
        } else {
            $stmt->close();

            // Verificar que el RFC no esté duplicado
            $stmtRfc = $conexion->prepare("SELECT id_cliente FROM clientes WHERE datos_fiscales = ?");
            $stmtRfc->bind_param("s", $datos_fiscales);
            $stmtRfc->execute();
            $stmtRfc->store_result();
            if ($stmtRfc->num_rows > 0) {
                $error = "Ya existe un cliente registrado con ese RFC.";
                $stmtRfc->close();
            } else {
                $stmtRfc->close();

                $conexion->begin_transaction();
                try {
                    // 1) Crear el registro del cliente (RD-01)
                    $stmtCliente = $conexion->prepare("INSERT INTO clientes (nombre, contacto, direccion, datos_fiscales) VALUES (?, ?, ?, ?)");
                    $stmtCliente->bind_param("ssss", $nombre, $contactoFormateado, $direccion, $datos_fiscales);
                    $stmtCliente->execute();
                    $id_cliente = $conexion->insert_id;
                    $stmtCliente->close();

                    // 2) Crear el usuario con rol Cliente, contraseña protegida con hash
                    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                    $rol = 'Cliente';
                    $stmtUsuario = $conexion->prepare("INSERT INTO usuarios (nombre, email, password, rol, id_cliente) VALUES (?, ?, ?, ?, ?)");
                    $stmtUsuario->bind_param("ssssi", $nombre, $email, $passwordHash, $rol, $id_cliente);
                    $stmtUsuario->execute();
                    $id_usuario = $conexion->insert_id;
                    $stmtUsuario->close();

                    $conexion->commit();

                    // Iniciar sesión automáticamente tras el registro
                    $_SESSION['id_usuario'] = $id_usuario;
                    $_SESSION['nombre'] = $nombre;
                    $_SESSION['rol'] = $rol;
                    $_SESSION['id_cliente'] = $id_cliente;

                    $redir = !empty($paqueteSeleccionado) ? "panel.php?paquete=" . urlencode($paqueteSeleccionado) : "panel.php";
                    header("Location: " . $redir);
                    exit;
                } catch (Exception $e) {
                    $conexion->rollback();
                    $error = "No se pudo completar el registro. Intenta nuevamente.";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Cuenta - SGCEA | Quinto Canal de Eventos</title>
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
            <span>Quinto Canal <small>Producción Audiovisual</small></span>
        </a>
        <nav id="nav-menu">
            <a href="index.html">Inicio</a>
            <a href="login.php<?= !empty($paqueteSeleccionado) ? '?paquete=' . urlencode($paqueteSeleccionado) : '' ?>" class="btn-nav">Iniciar Sesión</a>
        </nav>
    </div>
</header>

<main class="auth-wrapper">
    <div class="auth-box auth-box-wide">
        <div class="auth-header">
            <span class="pill-tag">Registro de Cliente</span>
            <h2>Crear Cuenta en SGCEA</h2>
            <p>Regístrate para solicitar cotizaciones, dar seguimiento a tus eventos y consultar tus contratos.</p>
        </div>

        <?php if (!empty($paqueteSeleccionado)): ?>
            <div class="alert-info">
                <strong>Paquete seleccionado:</strong> <?= htmlspecialchars($paqueteSeleccionado) ?><br>
                <span>Completa tu registro para solicitar y personalizar tu cotización al instante.</span>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert-danger" style="text-align: center;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="registro.php<?= !empty($paqueteSeleccionado) ? '?paquete=' . urlencode($paqueteSeleccionado) : '' ?>" id="form-registro">
            <input type="hidden" name="paquete" value="<?= htmlspecialchars($paqueteSeleccionado) ?>">

            <div class="form-grid-2">
                <div class="form-group">
                    <label for="nombre">Nombre o Razón Social:</label>
                    <input type="text" id="nombre" name="nombre" required placeholder="Nombre completo o empresa" value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="contacto">Teléfono de Contacto (10 dígitos):</label>
                    <input type="tel" id="contacto" name="contacto" required maxlength="15" placeholder="272-123-4567" value="<?= htmlspecialchars($_POST['contacto'] ?? '') ?>">
                    <small id="msg-tel-reg" class="hint-validacion"></small>
                </div>
            </div>

            <div class="form-grid-2">
                <div class="form-group">
                    <label for="datos_fiscales">Datos Fiscales (RFC):</label>
                    <input type="text" id="datos_fiscales" name="datos_fiscales" required maxlength="13" style="text-transform:uppercase;" placeholder="Ej. XAXX010101000" value="<?= htmlspecialchars($_POST['datos_fiscales'] ?? '') ?>">
                    <small id="msg-rfc-reg" class="hint-validacion"></small>
                </div>

                <div class="form-group">
                    <label for="email">Correo Electrónico:</label>
                    <input type="email" id="email" name="email" required placeholder="tucorreo@ejemplo.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="direccion">Dirección:</label>
                <input type="text" id="direccion" name="direccion" required placeholder="Calle, número, colonia, ciudad" value="<?= htmlspecialchars($_POST['direccion'] ?? '') ?>">
            </div>

            <div class="form-divider"><span>Credenciales de Acceso</span></div>

            <div class="form-grid-2">
                <div class="form-group">
                    <label for="password">Contraseña:</label>
                    <input type="password" id="password" name="password" required placeholder="Mínimo 6 caracteres">
                </div>

                <div class="form-group">
                    <label for="password2">Confirmar Contraseña:</label>
                    <input type="password" id="password2" name="password2" required placeholder="Repite tu contraseña">
                </div>
            </div>

            <button type="submit" class="btn btn-primary-full">Crear Cuenta y Continuar</button>
        </form>

        <div class="auth-footer">
            <p>¿Ya tienes cuenta? <a href="login.php<?= !empty($paqueteSeleccionado) ? '?paquete=' . urlencode($paqueteSeleccionado) : '' ?>">Inicia sesión aquí</a></p>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const regexRFC = /^([A-ZÑ&]{3,4})(\d{2})(0[1-9]|1[0-2])(0[1-9]|[12]\d|3[01])([A-Z\d]{3})$/;
    const inputTel = document.getElementById('contacto');
    const inputRfc = document.getElementById('datos_fiscales');
    const msgTel = document.getElementById('msg-tel-reg');
    const msgRfc = document.getElementById('msg-rfc-reg');

    function validarTelLocal(val) {
        let dig = val.replace(/\D+/g, '');
        if (dig.length === 12 && dig.startsWith('52')) dig = dig.slice(2);
        return /^\d{10}$/.test(dig) && !/^(\d)\1{9}$/.test(dig);
    }

    inputTel.addEventListener('input', () => {
        if (!inputTel.value.trim()) { msgTel.textContent = ''; return; }
        if (validarTelLocal(inputTel.value)) {
            msgTel.textContent = 'Teléfono válido (10 dígitos).';
            msgTel.style.color = '#10b981';
        } else {
            msgTel.textContent = 'Ingrese un número válido de 10 dígitos.';
            msgTel.style.color = '#ef4444';
        }
    });

    inputRfc.addEventListener('input', () => {
        inputRfc.value = inputRfc.value.toUpperCase();
        if (!inputRfc.value.trim()) { msgRfc.textContent = ''; return; }
        if (regexRFC.test(inputRfc.value)) {
            msgRfc.textContent = inputRfc.value.length === 12 ? 'RFC válido (Persona Moral).' : 'RFC válido (Persona Física).';
            msgRfc.style.color = '#10b981';
        } else {
            msgRfc.textContent = 'Formato: 3-4 letras + fecha YYMMDD + 3 homoclave.';
            msgRfc.style.color = '#ef4444';
        }
    });

    document.getElementById('form-registro').addEventListener('submit', (e) => {
        if (!validarTelLocal(inputTel.value)) {
            e.preventDefault();
            alert('Por favor ingresa un número de teléfono válido de 10 dígitos.');
            inputTel.focus();
            return;
        }
        if (!regexRFC.test(inputRfc.value.trim().toUpperCase())) {
            e.preventDefault();
            alert('Por favor ingresa un RFC válido (12 caracteres para Persona Moral o 13 para Persona Física).');
            inputRfc.focus();
        }
    });
});
</script>
</body>
</html>
