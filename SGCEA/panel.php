<?php
// panel.php: panel principal, protegido por sesión, con acceso diferenciado por rol (RF-10)
require_once 'auth_check.php';
require_once 'conexion.php';

requerir_sesion(false);

$rol = $_SESSION['rol'];
$nombre = $_SESSION['nombre'];
$id_cliente_sesion = intval($_SESSION['id_cliente'] ?? 0);

// Auto-marcar cotizaciones vencidas
$conexion->query("UPDATE cotizaciones SET estado = 'Vencida' WHERE estado = 'Pendiente' AND vigencia < CURDATE()");

// KPIs globales según el rol
if ($rol === 'Cliente') {
    $kpiCot = $conexion->query("SELECT COUNT(*) AS t FROM cotizaciones WHERE id_cliente = $id_cliente_sesion")->fetch_assoc()['t'] ?? 0;
    $kpiCon = $conexion->query("SELECT COUNT(*) AS t FROM contratos ct JOIN cotizaciones c ON ct.id_cotizacion = c.id_cotizacion WHERE c.id_cliente = $id_cliente_sesion AND ct.estatus = 'Activo'")->fetch_assoc()['t'] ?? 0;
    $kpiEv  = $conexion->query("SELECT COUNT(*) AS t FROM eventos e JOIN contratos ct ON e.id_contrato = ct.id_contrato JOIN cotizaciones c ON ct.id_cotizacion = c.id_cotizacion WHERE c.id_cliente = $id_cliente_sesion AND e.estado IN ('Programado','Confirmado','Reprogramado')")->fetch_assoc()['t'] ?? 0;
    $kpiPag = $conexion->query("SELECT COALESCE(SUM(p.monto),0) AS t FROM pagos p JOIN eventos e ON p.id_evento = e.id_evento JOIN contratos ct ON e.id_contrato = ct.id_contrato JOIN cotizaciones c ON ct.id_cotizacion = c.id_cotizacion WHERE c.id_cliente = $id_cliente_sesion")->fetch_assoc()['t'] ?? 0;
} else {
    $kpiCot = $conexion->query("SELECT COUNT(*) AS t FROM cotizaciones WHERE estado = 'Pendiente'")->fetch_assoc()['t'] ?? 0;
    $kpiCon = $conexion->query("SELECT COUNT(*) AS t FROM contratos WHERE estatus = 'Activo'")->fetch_assoc()['t'] ?? 0;
    $kpiEv  = $conexion->query("SELECT COUNT(*) AS t FROM eventos WHERE estado IN ('Programado','Confirmado','Reprogramado')")->fetch_assoc()['t'] ?? 0;
    $kpiPag = $conexion->query("SELECT COALESCE(SUM(monto),0) AS t FROM pagos")->fetch_assoc()['t'] ?? 0;
}

// Datos para los formularios y tablas
$clientes = $conexion->query("SELECT id_cliente, nombre, contacto, direccion, datos_fiscales FROM clientes ORDER BY nombre");
$cotizaciones_aprobadas = $conexion->query("SELECT c.id_cotizacion, c.nombre_evento, c.monto_total, cl.nombre AS cliente
                                             FROM cotizaciones c
                                             JOIN clientes cl ON c.id_cliente = cl.id_cliente
                                             LEFT JOIN contratos ct ON ct.id_cotizacion = c.id_cotizacion
                                             WHERE c.estado = 'Aprobada' AND ct.id_contrato IS NULL
                                             ORDER BY c.id_cotizacion DESC");
$eventos = $conexion->query("SELECT e.id_evento, e.fecha, e.horario, e.ubicacion, e.tipo_evento, e.estado,
                                    cl.nombre AS cliente, c.nombre_evento, c.monto_total,
                                    COALESCE((SELECT SUM(p.monto) FROM pagos p WHERE p.id_evento = e.id_evento), 0) AS total_pagado
                              FROM eventos e
                              JOIN contratos ct ON e.id_contrato = ct.id_contrato
                              JOIN cotizaciones c ON ct.id_cotizacion = c.id_cotizacion
                              JOIN clientes cl ON c.id_cliente = cl.id_cliente
                              ORDER BY e.fecha DESC");
$colEstRec = $conexion->query("SHOW COLUMNS FROM recursos LIKE 'estado'");
$sqlRecursosSelect = ($colEstRec && $colEstRec->num_rows > 0)
    ? "SELECT id_recurso, nombre, tipo FROM recursos WHERE estado = 'Disponible' ORDER BY tipo, nombre"
    : "SELECT id_recurso, nombre, tipo FROM recursos ORDER BY tipo, nombre";
$recursos = $conexion->query($sqlRecursosSelect);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Panel de Gestión - SGCEA | Quinto Canal de Eventos</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="Styles.css">
</head>
<body class="dashboard-body" data-rol="<?= htmlspecialchars($rol) ?>">

<header class="site-header dashboard-header">
    <div class="header-inner">
        <a href="index.html" class="logo">
            <span class="logo-badge">QC</span>
            <span>Quinto Canal <small>Panel Operativo SGCEA</small></span>
        </a>
        <nav class="dashboard-user-nav">
            <a href="index.html" style="font-size:0.86rem; color:#cbd5e1; text-decoration:none; margin-right:0.4rem;">Sitio Público</a>
            <div class="user-pill">
                <span class="user-dot"></span>
                <span id="current-user-text"><?= htmlspecialchars($nombre) ?></span>
                <span class="role-badge role-<?= strtolower($rol) ?>"><?= htmlspecialchars($rol) ?></span>
            </div>
            <a href="logout.php" class="btn-logout">Cerrar Sesión</a>
        </nav>
    </div>
</header>

<div class="container dashboard-container">
    <div class="dashboard-banner">
        <div>
            <span class="section-tag">SISTEMA DE GESTIÓN AUDIOVISUAL</span>
            <h1>Bienvenido, <?= htmlspecialchars($nombre) ?></h1>
            <p>Administra cotizaciones, contratos, inventario técnico y programación de eventos desde un solo lugar.</p>
        </div>
    </div>

    <!-- Indicadores rápidos (KPIs) -->
    <div class="resumen-grid" style="margin-bottom:1.5rem;">
        <div class="stat-card">
            <span class="stat-label"><?= $rol === 'Cliente' ? 'Mis Cotizaciones' : 'Cotizaciones Pendientes' ?></span>
            <strong class="stat-value"><?= intval($kpiCot) ?></strong>
        </div>
        <div class="stat-card stat-disponible">
            <span class="stat-label"><?= $rol === 'Cliente' ? 'Mis Contratos Activos' : 'Contratos Activos' ?></span>
            <strong class="stat-value"><?= intval($kpiCon) ?></strong>
        </div>
        <div class="stat-card">
            <span class="stat-label">Eventos Activos / Próximos</span>
            <strong class="stat-value"><?= intval($kpiEv) ?></strong>
        </div>
        <?php if ($rol !== 'Tecnico'): ?>
        <div class="stat-card stat-disponible">
            <span class="stat-label"><?= $rol === 'Cliente' ? 'Total Invertido / Pagado' : 'Ingresos Registrados' ?></span>
            <strong class="stat-value" style="font-size:1.5rem;">$<?= number_format(floatval($kpiPag), 2) ?></strong>
        </div>
        <?php endif; ?>
    </div>

    <nav class="tabs">
        <?php if ($rol === 'Administrador'): ?>
            <button class="nav-btn" data-target="window-clientes">Clientes</button>
            <button class="nav-btn" data-target="window-cotizaciones">Cotizaciones</button>
            <button class="nav-btn" data-target="window-contratos">Contratos</button>
            <button class="nav-btn" data-target="window-eventos">Eventos</button>
            <button class="nav-btn" data-target="window-pagos">Pagos</button>
            <button class="nav-btn" data-target="window-recursos">Recursos</button>
            <button class="nav-btn" data-target="window-historial">Historial</button>
        <?php elseif ($rol === 'Tecnico'): ?>
            <button class="nav-btn" data-target="window-eventos">Eventos</button>
            <button class="nav-btn" data-target="window-recursos">Recursos</button>
            <button class="nav-btn" data-target="window-historial">Historial</button>
        <?php elseif ($rol === 'Cliente'): ?>
            <button class="nav-btn" data-target="window-cotizaciones">Mis Cotizaciones</button>
            <button class="nav-btn" data-target="window-contratos">Mis Contratos</button>
            <button class="nav-btn" data-target="window-pagos">Mis Pagos</button>
            <button class="nav-btn" data-target="window-historial">Mi Historial</button>
        <?php endif; ?>
    </nav>

    <!-- CLIENTES (RF-01) -->
    <?php if ($rol === 'Administrador'): ?>
    <section id="window-clientes" class="window-content hidden">
        <h2>Gestión de Clientes</h2>
        <div class="card" style="padding:1.5rem; margin-bottom:1.5rem;">
            <h3>Registrar Nuevo Cliente</h3>
            <form id="form-cliente">
                <div class="form-grid-2">
                    <div class="form-group">
                        <label>Nombre o Razón Social:</label>
                        <input type="text" id="cli-nombre" required placeholder="Nombre completo o razón social">
                    </div>
                    <div class="form-group">
                        <label>Teléfono de Contacto (10 dígitos):</label>
                        <input type="tel" id="cli-contacto" maxlength="15" required placeholder="Ej. 272-123-4567">
                        <small id="msg-cli-tel" class="hint-validacion"></small>
                    </div>
                </div>
                <div class="form-grid-2">
                    <div class="form-group">
                        <label>Datos Fiscales (RFC - 12 o 13 caracteres):</label>
                        <input type="text" id="cli-fiscales" maxlength="13" style="text-transform:uppercase;" required placeholder="Ej. GEO900101ABC o XAXX010101000">
                        <small id="msg-cli-rfc" class="hint-validacion"></small>
                    </div>
                    <div class="form-group">
                        <label>Dirección:</label>
                        <input type="text" id="cli-direccion" required placeholder="Calle, número, colonia, ciudad">
                    </div>
                </div>
                <button type="submit" class="btn">Registrar Cliente</button>
            </form>
        </div>

        <h3>Clientes Registrados</h3>
        <div class="table-responsive"><table class="tabla">
            <thead>
                <tr><th>ID</th><th>Nombre / Razón Social</th><th>Teléfono</th><th>RFC</th><th>Dirección</th></tr>
            </thead>
            <tbody>
                <?php $clientes->data_seek(0); while ($c = $clientes->fetch_assoc()): ?>
                <tr>
                    <td>#<?= $c['id_cliente'] ?></td>
                    <td><?= htmlspecialchars($c['nombre']) ?></td>
                    <td><?= htmlspecialchars($c['contacto']) ?></td>
                    <td><strong><?= htmlspecialchars($c['datos_fiscales']) ?></strong></td>
                    <td><?= htmlspecialchars($c['direccion']) ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table></div>
    </section>
    <?php endif; ?>

    <!-- COTIZACIONES (RF-02, RF-03) -->
    <?php if ($rol === 'Administrador' || $rol === 'Cliente'): ?>
    <section id="window-cotizaciones" class="window-content hidden">
        <h2><?= $rol === 'Cliente' ? 'Mis Cotizaciones' : 'Gestión de Cotizaciones' ?></h2>

        <!-- Catálogo de Paquetes Predeterminados de Audio e Iluminación -->
        <div class="card" style="padding:1.5rem; margin-bottom:1.5rem;">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem; margin-bottom:1rem;">
                <h3 style="margin:0;">Paquetes Predeterminados de Audio e Iluminación</h3>
                <select id="filtro-categoria-paquete" style="padding:0.45rem 0.8rem; border:1px solid #cbd5e1; border-radius:6px;">
                    <option value="Todos">Todas las categorías</option>
                    <option value="Audio">Solo Audio</option>
                    <option value="Iluminación">Solo Iluminación</option>
                    <option value="Audio e Iluminación">Combos Audio + Iluminación</option>
                </select>
            </div>
            <div id="grid-paquetes-cotizacion" class="paquetes-grid">
                <p style="color:#666;">Cargando paquetes predeterminados...</p>
            </div>
        </div>

        <?php if ($rol === 'Administrador'): ?>
        <div class="card" style="padding:1.5rem; margin-bottom:1.5rem;">
            <h3>Nueva Cotización (RF-02)</h3>
            <form id="form-cotizacion">
                <div class="form-grid-2">
                    <div class="form-group">
                        <label>Paquete predeterminado (opcional):</label>
                        <select id="cot-paquete">
                            <option value="">-- Cotización personalizada (sin paquete base) --</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Cliente:</label>
                        <select id="cot-cliente" required>
                            <option value="">Seleccione...</option>
                            <?php $clientes->data_seek(0); while ($c = $clientes->fetch_assoc()): ?>
                                <option value="<?= $c['id_cliente'] ?>"><?= htmlspecialchars($c['nombre']) ?> (<?= htmlspecialchars($c['datos_fiscales']) ?>)</option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group"><label>Nombre del evento:</label><input type="text" id="cot-evento" required placeholder="Ej. Congreso Anual, Boda Jardín"></div>
                <div class="form-group"><label>Descripción técnica (equipo y servicios incluidos):</label><textarea id="cot-desc" rows="3" required></textarea></div>
                <div class="form-group">
                    <label>Servicios:</label><br>
                    <label style="margin-right:1rem;"><input type="checkbox" name="servicio" value="Audio"> Audio</label>
                    <label style="margin-right:1rem;"><input type="checkbox" name="servicio" value="Iluminación"> Iluminación</label>
                    <label style="margin-right:1rem;"><input type="checkbox" name="servicio" value="Video"> Video</label>
                    <label><input type="checkbox" name="servicio" value="Escenografía"> Escenografía</label>
                </div>
                <div class="form-grid-2">
                    <div class="form-group"><label>Monto total ($ MXN):</label><input type="number" step="0.01" min="1" id="cot-monto" required></div>
                    <div class="form-group"><label>Vigencia de la cotización:</label><input type="date" id="cot-vigencia" min="<?= date('Y-m-d') ?>" required></div>
                </div>
                <button type="submit" class="btn">Guardar Cotización</button>
            </form>
        </div>
        <?php endif; ?>

        <?php if ($rol === 'Cliente'): ?>
        <div class="card" style="padding:1.5rem; margin-bottom:1.5rem;">
            <h3>Solicitar Nueva Cotización</h3>
            <p style="font-size:0.9rem; color:#475569; margin-top:0;">Selecciona uno de nuestros paquetes predeterminados arriba o arma una solicitud a la medida para tu evento.</p>
            <form id="form-solicitud-cotizacion">
                <div class="form-group">
                    <label>Paquete predeterminado (opcional):</label>
                    <select id="sol-cot-paquete">
                        <option value="">-- Cotización personalizada (sin paquete base) --</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Nombre del evento:</label>
                    <input type="text" id="sol-cot-evento" required placeholder="Ej. Boda Civil, Graduación, Conferencia Anual">
                </div>
                <div class="form-group">
                    <label>Detalles y requerimientos técnicos (lugar, número de invitados, horario):</label>
                    <textarea id="sol-cot-desc" rows="3" required placeholder="Describe las características de tu evento o equipo requerido..."></textarea>
                </div>
                <div class="form-group">
                    <label>Servicios requeridos:</label><br>
                    <label style="margin-right:1rem;"><input type="checkbox" name="sol_servicio" value="Audio"> Audio</label>
                    <label style="margin-right:1rem;"><input type="checkbox" name="sol_servicio" value="Iluminación"> Iluminación</label>
                    <label style="margin-right:1rem;"><input type="checkbox" name="sol_servicio" value="Video"> Video</label>
                    <label><input type="checkbox" name="sol_servicio" value="Escenografía"> Escenografía</label>
                </div>
                <div class="form-grid-2">
                    <div class="form-group">
                        <label>Presupuesto estimado o costo del paquete ($ MXN):</label>
                        <input type="number" step="0.01" min="1" id="sol-cot-monto" required placeholder="Ej. 8500.00">
                    </div>
                    <div class="form-group">
                        <label>Fecha tentativa del evento / vigencia requerida:</label>
                        <input type="date" id="sol-cot-fecha" min="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
                <button type="submit" class="btn">Enviar Solicitud de Cotización</button>
            </form>
        </div>
        <?php endif; ?>

        <div class="table-responsive"><table class="tabla">
            <thead>
                <tr><th>ID</th><th>Cliente</th><th>Evento</th><th>Servicios</th><th>Monto</th><th>Vigencia</th><th>Estado</th><th>Acciones</th></tr>
            </thead>
            <tbody id="tabla-cotizaciones-body"></tbody>
        </table></div>
    </section>
    <?php endif; ?>

    <!-- CONTRATOS (RF-04) + cancelación y modificación de fechas -->
    <?php if ($rol === 'Administrador' || $rol === 'Cliente'): ?>
    <section id="window-contratos" class="window-content hidden">
        <h2><?= $rol === 'Cliente' ? 'Mis Contratos' : 'Contratos' ?></h2>
        <?php if ($rol === 'Administrador'): ?>
        <div class="card" style="padding:1.5rem; margin-bottom:1.5rem;">
            <h3>Generar Contrato</h3>
            <form id="form-contrato">
                <div class="form-group">
                    <label>Cotización aprobada (sin contrato previo):</label>
                    <select id="con-cotizacion" required>
                        <option value="">Seleccione...</option>
                        <?php while ($c = $cotizaciones_aprobadas->fetch_assoc()): ?>
                            <option value="<?= $c['id_cotizacion'] ?>">#<?= $c['id_cotizacion'] ?> - <?= htmlspecialchars($c['cliente']) ?> - <?= htmlspecialchars($c['nombre_evento']) ?> ($<?= number_format($c['monto_total'], 2) ?>)</option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-grid-2">
                    <div class="form-group"><label>Fecha del evento:</label><input type="date" id="con-fecha" min="<?= date('Y-m-d') ?>" required></div>
                    <div class="form-group"><label>Hora de inicio:</label><input type="time" id="con-horario" value="18:00" required></div>
                </div>
                <div class="form-grid-2">
                    <div class="form-group">
                        <label>Tipo de evento:</label>
                        <select id="con-tipo-evento" required>
                            <option value="Boda / Social">Boda / Social</option>
                            <option value="Concierto / Festival">Concierto / Festival</option>
                            <option value="Corporativo / Congreso">Corporativo / Congreso</option>
                            <option value="Graduación / Escolar">Graduación / Escolar</option>
                            <option value="Gala / Privado">Gala / Privado</option>
                        </select>
                    </div>
                    <div class="form-group"><label>Ubicación / Sede:</label><input type="text" id="con-ubicacion" required placeholder="Salón, auditorio o dirección del evento"></div>
                </div>
                <div class="form-group"><label>Condiciones particulares del servicio:</label><textarea id="con-condiciones" rows="3" required placeholder="Horarios de montaje, acceso a corriente eléctrica, requerimientos de sede..."></textarea></div>
                <button type="submit" class="btn">Generar Contrato y Evento</button>
            </form>
        </div>
        <?php endif; ?>

        <div class="table-responsive"><table class="tabla">
            <thead><tr><th>Contrato</th><?= $rol === 'Administrador' ? '<th>Cliente</th>' : '' ?><th>Evento</th><th>Fecha</th><th>Pagado / Total</th><th>Estado</th><th>Acciones</th></tr></thead>
            <tbody id="tabla-contratos-body"></tbody>
        </table></div>
        <p style="font-size:.85rem; color:#475569;">
            Cancelar un evento aplica la cláusula de descuento del contrato (se retiene un porcentaje del monto total según la anticipación).
            El cambio de fecha deja el evento en estado <strong>Reprogramado</strong>.
        </p>
    </section>

    <!-- Ventana modal: cancelar contrato / cambiar fecha -->
    <div id="modal-contrato" class="modal-overlay hidden">
        <div class="modal-box">
            <h3 id="modal-titulo"></h3>
            <div id="modal-resumen" style="margin-bottom:1rem;"></div>
            <div class="form-group hidden" id="modal-grupo-fecha">
                <label>Nueva fecha del evento:</label>
                <input type="date" id="modal-fecha">
            </div>
            <div class="form-group">
                <label id="modal-label-motivo">Motivo:</label>
                <textarea id="modal-motivo" rows="2" maxlength="255"></textarea>
            </div>
            <div style="display:flex; gap:.5rem; justify-content:flex-end;">
                <button type="button" class="btn-sm" id="modal-cerrar">Volver</button>
                <button type="button" class="btn-sm btn-rechazar" id="modal-confirmar">Confirmar</button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- EVENTOS (RF-05, RF-06, RF-11) -->
    <?php if ($rol === 'Administrador' || $rol === 'Tecnico'): ?>
    <section id="window-eventos" class="window-content hidden">
        <h2>Programación y Control de Eventos</h2>
        <div class="table-responsive"><table class="tabla">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Cliente / Evento</th>
                    <th>Fecha y Hora</th>
                    <th>Sede / Tipo</th>
                    <th>Anticipo / Total</th>
                    <th>Recursos Asignados</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody id="tabla-eventos-body">
                <tr><td colspan="8" style="text-align:center;">Cargando eventos...</td></tr>
            </tbody>
        </table></div>
    </section>

    <!-- Modal para Finalizar Evento con Resultado y Seguimiento -->
    <div id="modal-finalizar-evento" class="modal-overlay hidden">
        <div class="modal-box">
            <h3 id="modal-fin-titulo">Finalizar Evento</h3>
            <p style="font-size:0.9rem; color:#475569; margin-top:0;">Registra el reporte técnico de cierre y observaciones para el historial del cliente.</p>
            <div class="form-group">
                <label for="fin-resultado">Resultado operativo del evento:</label>
                <input type="text" id="fin-resultado" value="Evento realizado con éxito y sin incidentes." maxlength="250" required>
            </div>
            <div class="form-group">
                <label for="fin-seguimiento">Notas técnicas / Seguimiento al cliente (opcional):</label>
                <textarea id="fin-seguimiento" rows="3" placeholder="Ej. Desmontaje concluido a las 02:00 AM, equipo sin novedades, cliente satisfecho."></textarea>
            </div>
            <div style="display:flex; gap:.5rem; justify-content:flex-end;">
                <button type="button" class="btn-sm" id="btn-fin-cerrar">Cancelar</button>
                <button type="button" class="btn-sm btn-finalizar" id="btn-fin-confirmar">Cerrar y Guardar en Historial</button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- PAGOS (RF-07) -->
    <?php if ($rol === 'Administrador' || $rol === 'Cliente'): ?>
    <section id="window-pagos" class="window-content hidden">
        <h2><?= $rol === 'Cliente' ? 'Mis Pagos y Estado de Cuenta' : 'Control y Registro de Pagos' ?></h2>
        <?php if ($rol === 'Administrador'): ?>
        <div class="card" style="padding:1.5rem; margin-bottom:1.5rem;">
            <h3>Registrar Nuevo Pago</h3>
            <form id="form-pago">
                <div class="form-group">
                    <label>Evento (se muestra saldo pagado / total):</label>
                    <select id="pago-evento" required>
                        <option value="">Seleccione...</option>
                        <?php $eventos->data_seek(0); while ($e = $eventos->fetch_assoc()): if ($e['estado'] === 'Cancelado') continue; ?>
                            <option value="<?= $e['id_evento'] ?>">#<?= $e['id_evento'] ?> - <?= htmlspecialchars($e['cliente']) ?> (<?= htmlspecialchars($e['nombre_evento']) ?>) | Pagado: $<?= number_format($e['total_pagado'], 2) ?> / $<?= number_format($e['monto_total'], 2) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-grid-2">
                    <div class="form-group">
                        <label>Tipo de pago:</label>
                        <select id="pago-tipo" required>
                            <option value="Anticipo 50%">Anticipo 50%</option>
                            <option value="Abono Parcial">Abono Parcial</option>
                            <option value="Pago Final">Pago Final</option>
                        </select>
                    </div>
                    <div class="form-group"><label>Monto ($ MXN):</label><input type="number" step="0.01" min="1" id="pago-monto" required></div>
                </div>
                <div class="form-grid-2">
                    <div class="form-group">
                        <label>Método de pago:</label>
                        <select id="pago-metodo" required>
                            <option value="Transferencia">Transferencia</option>
                            <option value="Efectivo">Efectivo</option>
                            <option value="Tarjeta">Tarjeta</option>
                        </select>
                    </div>
                    <div class="form-group"><label>Referencia / Folio:</label><input type="text" id="pago-referencia" placeholder="Ej. SPEI-984312"></div>
                </div>
                <div class="form-group"><label>Fecha de pago:</label><input type="date" id="pago-fecha" value="<?= date('Y-m-d') ?>" required></div>
                <button type="submit" class="btn">Registrar Pago</button>
            </form>
        </div>
        <?php endif; ?>

        <h3>Historial de Pagos Registrados</h3>
        <div class="table-responsive"><table class="tabla">
            <thead>
                <tr>
                    <th>Folio</th>
                    <th>Evento</th>
                    <?= $rol === 'Administrador' ? '<th>Cliente</th>' : '' ?>
                    <th>Concepto</th>
                    <th>Método</th>
                    <th>Referencia</th>
                    <th>Fecha</th>
                    <th>Monto</th>
                </tr>
            </thead>
            <tbody id="tabla-pagos-body">
                <tr><td colspan="8" style="text-align:center;">Cargando pagos...</td></tr>
            </tbody>
        </table></div>
    </section>
    <?php endif; ?>

    <!-- RECURSOS (RF-08, RF-09) -->
    <?php if ($rol === 'Administrador' || $rol === 'Tecnico'): ?>
    <section id="window-recursos" class="window-content hidden">
        <h2>Inventario, Disponibilidad y Asignación de Recursos</h2>

        <div class="resumen-grid" id="resumen-general-recursos">
            <div class="stat-card">
                <span class="stat-label">Inventario Total</span>
                <strong class="stat-value" id="stat-total">-</strong>
            </div>
            <div class="stat-card stat-disponible">
                <span class="stat-label">Disponibles para Rentar</span>
                <strong class="stat-value" id="stat-disponibles">-</strong>
            </div>
            <div class="stat-card stat-ocupado">
                <span class="stat-label">No Disponibles (Ocupados / Taller)</span>
                <strong class="stat-value" id="stat-ocupados">-</strong>
            </div>
        </div>

        <div class="card" style="padding:1.5rem; margin-bottom:1.5rem;">
            <h3>Resumen por Tipo de Recurso</h3>
            <div class="table-responsive"><table class="tabla" style="margin-bottom:0;">
                <thead>
                    <tr>
                        <th>Tipo de Recurso</th>
                        <th>Cantidad Total (N)</th>
                        <th>Disponibles para Rentar</th>
                        <th>No Disponibles</th>
                    </tr>
                </thead>
                <tbody id="tabla-resumen-tipos-body">
                    <tr><td colspan="4" style="text-align:center;">Cargando resumen...</td></tr>
                </tbody>
            </table></div>
        </div>

        <div class="card" style="padding:1.5rem; margin-bottom:1.5rem;">
            <h3>Listado de Recursos y Disponibilidad por Fecha</h3>
            <div class="filtros-recursos">
                <div class="form-group" style="margin-bottom:0;">
                    <label for="disp-fecha">Fecha de consulta:</label>
                    <input type="date" id="disp-fecha" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label for="filtro-tipo-recurso">Filtrar por tipo:</label>
                    <select id="filtro-tipo-recurso">
                        <option value="Todos">Todos los tipos</option>
                        <option value="Audio">Audio</option>
                        <option value="Iluminación">Iluminación</option>
                        <option value="Video">Video</option>
                        <option value="Personal">Personal</option>
                        <option value="Transporte">Transporte</option>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label for="filtro-estado-recurso">Estado del recurso:</label>
                    <select id="filtro-estado-recurso">
                        <option value="Todos">Todos los estados</option>
                        <option value="Disponible">Solo Disponibles para rentar</option>
                        <option value="Ocupado">Solo Ocupados en evento</option>
                        <option value="En reparación">En reparación</option>
                        <option value="Dañado">Dañado</option>
                    </select>
                </div>
                <div>
                    <button id="btn-consultar-disponibilidad" class="btn" style="width:auto; padding:0.6rem 1.2rem;">Actualizar Consulta</button>
                </div>
            </div>

            <div class="table-responsive"><table class="tabla" style="margin-top:1rem; margin-bottom:0;" data-rol="<?= htmlspecialchars($rol) ?>">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Recurso</th>
                        <th>Tipo</th>
                        <th>Disponibilidad / Estado</th>
                        <th>Detalle / Evento Asignado</th>
                        <th>Actualizar Estado<?= $rol === 'Administrador' ? ' / Eliminar' : '' ?></th>
                    </tr>
                </thead>
                <tbody id="tabla-disponibilidad-body"></tbody>
            </table></div>
        </div>

        <div class="card" style="padding:1.5rem; margin-bottom:1.5rem;">
            <h3>Asignar Recurso a Evento</h3>
            <form id="form-recurso">
                <div class="form-group">
                    <label>Evento activo:</label>
                    <select id="rec-evento" required>
                        <option value="">Seleccione...</option>
                        <?php $eventos->data_seek(0); while ($e = $eventos->fetch_assoc()): if (in_array($e['estado'], ['Cancelado', 'Finalizado'])) continue; ?>
                            <option value="<?= $e['id_evento'] ?>" data-fecha="<?= $e['fecha'] ?>">#<?= $e['id_evento'] ?> - <?= htmlspecialchars($e['cliente']) ?> (<?= htmlspecialchars($e['nombre_evento']) ?>) - <?= htmlspecialchars($e['fecha']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Recurso:</label>
                    <select id="rec-recurso" required>
                        <option value="">Seleccione...</option>
                        <?php while ($r = $recursos->fetch_assoc()): ?>
                            <option value="<?= $r['id_recurso'] ?>">#<?= $r['id_recurso'] ?> - <?= htmlspecialchars($r['nombre']) ?> (<?= htmlspecialchars($r['tipo']) ?>)</option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <button type="submit" class="btn">Asignar Recurso</button>
            </form>
        </div>

        <?php if ($rol === 'Administrador'): ?>
        <div class="card" style="padding:1.5rem;">
            <h3>Agregar Unidades al Inventario de Recursos</h3>
            <form id="form-nuevo-recurso">
                <div class="form-group">
                    <label>Nombre del equipo o personal:</label>
                    <input type="text" id="nuevo-rec-nombre" required placeholder="Ej. Bocina Amplificada QSC K12">
                </div>
                <div class="form-grid-2">
                    <div class="form-group">
                        <label>Tipo de recurso:</label>
                        <select id="nuevo-rec-tipo" required>
                            <option value="Audio">Audio</option>
                            <option value="Iluminación">Iluminación</option>
                            <option value="Video">Video</option>
                            <option value="Personal">Personal</option>
                            <option value="Transporte">Transporte</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Cantidad de unidades a agregar (N):</label>
                        <input type="number" id="nuevo-rec-cantidad" min="1" max="50" value="1" required>
                    </div>
                </div>
                <button type="submit" class="btn">Agregar al Inventario</button>
            </form>
        </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <!-- HISTORIAL (RF-11) -->
    <section id="window-historial" class="window-content hidden">
        <h2>Historial de Eventos Finalizados</h2>
        <div class="table-responsive"><table class="tabla">
            <thead><tr><th>Evento</th><th>Cliente</th><th>Fecha</th><th>Tipo</th><th>Servicios Brindados</th><th>Resultado</th><th>Seguimiento</th></tr></thead>
            <tbody id="tabla-historial-body"></tbody>
        </table></div>
    </section>
</div>

<script src="script.js"></script>
</body>
</html>
