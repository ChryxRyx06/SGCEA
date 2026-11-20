<?php
// ver_contrato.php: vista imprimible del contrato (RF-04)
session_start();
require_once 'conexion.php';
require_once 'politica_cancelacion.php';

if (!isset($_SESSION['id_usuario'])) {
    die("Debe iniciar sesión para ver el contrato.");
}

$id_contrato = intval($_GET['id_contrato'] ?? 0);

$query = "SELECT ct.id_contrato, ct.fecha_evento, ct.ubicacion, ct.condiciones,
                 cl.nombre AS cliente, cl.contacto, cl.direccion, cl.datos_fiscales,
                 ct.estatus, ct.fecha_cancelacion, ct.motivo_cancelacion, ct.porcentaje_penalizacion,
                 ct.monto_penalizacion, ct.monto_reembolso, ct.monto_adeudo,
                 c.id_cliente, c.monto_total, c.descripcion_tecnica, c.nombre_evento
          FROM contratos ct
          JOIN cotizaciones c ON ct.id_cotizacion = c.id_cotizacion
          JOIN clientes cl ON c.id_cliente = cl.id_cliente
          WHERE ct.id_contrato = ?";

$stmt = $conexion->prepare($query);
$stmt->bind_param("i", $id_contrato);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if (!$row) {
    die("Contrato no encontrado.");
}

// El cliente solo puede ver sus propios contratos
if (($_SESSION['rol'] ?? '') === 'Cliente' && intval($row['id_cliente']) !== intval($_SESSION['id_cliente'] ?? 0)) {
    die("No tiene permiso para ver este contrato.");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Contrato #<?= str_pad($row['id_contrato'], 4, '0', STR_PAD_LEFT) ?> - Quinto Canal de Eventos</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="Styles.css">
<style>
  body { background: #e9eef5; padding: 1.5rem 1rem; }
  .contrato {
      max-width: 860px;
      margin: 0 auto;
      background: #fff;
      padding: 3rem;
      border-radius: 12px;
      box-shadow: 0 12px 35px rgba(15, 23, 42, 0.12);
      border-top: 6px solid #0f172a;
      color: #1e293b;
  }
  .contrato-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      border-bottom: 2px solid #e2e8f0;
      padding-bottom: 1.5rem;
      margin-bottom: 1.8rem;
      gap: 1rem;
  }
  .contrato-brand h2 { margin: 0; font-size: 1.45rem; color: #0f172a; letter-spacing: 0.5px; }
  .contrato-brand p { margin: 0.25rem 0 0; color: #64748b; font-size: 0.9rem; }
  .contrato-folio {
      text-align: right;
      background: #f8fafc;
      border: 1px solid #cbd5e1;
      padding: 0.65rem 1rem;
      border-radius: 8px;
  }
  .contrato-folio span { display: block; font-size: 0.72rem; text-transform: uppercase; color: #64748b; font-weight: 700; }
  .contrato-folio strong { font-size: 1.15rem; color: #0284c7; }
  .contrato-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 1rem;
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 8px;
      padding: 1.2rem;
      margin-bottom: 1.8rem;
      font-size: 0.92rem;
  }
  .contrato-grid p { margin: 0.25rem 0; }
  .contrato-section-title {
      font-size: 0.95rem;
      text-transform: uppercase;
      letter-spacing: 0.8px;
      color: #0f172a;
      border-bottom: 1px solid #e2e8f0;
      padding-bottom: 0.4rem;
      margin: 1.6rem 0 0.8rem;
  }
  .monto-destacado {
      background: #f0fdf4;
      border: 1px solid #bbf7d0;
      color: #166534;
      padding: 0.85rem 1.2rem;
      border-radius: 8px;
      font-size: 1.05rem;
      margin-top: 1rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
  }
  .firmas { display:flex; justify-content:space-around; margin-top:4.5rem; text-align:center; gap: 2rem; }
  .firmas div { border-top:1.5px solid #334155; padding-top:.6rem; width:240px; font-size: 0.9rem; font-weight: 600; color: #334155; }
  @media print {
      body { background: #fff; padding: 0; }
      .contrato { box-shadow: none; border-radius: 0; padding: 1.5rem; }
      .no-print { display:none; }
  }
</style>
</head>
<body>
<div class="contrato">
    <div class="contrato-header">
        <div class="contrato-brand">
            <h2>QUINTO CANAL DE EVENTOS</h2>
            <p>Contrato de Prestación de Servicios Audiovisuales y Producción Técnica</p>
        </div>
        <div class="contrato-folio">
            <span>Folio de Contrato</span>
            <strong>#<?= str_pad($row['id_contrato'], 4, '0', STR_PAD_LEFT) ?></strong>
        </div>
    </div>

    <div class="contrato-grid">
        <div>
            <p><strong>Cliente / Razón Social:</strong> <?= htmlspecialchars($row['cliente']) ?></p>
            <p><strong>RFC / Datos Fiscales:</strong> <?= htmlspecialchars($row['datos_fiscales']) ?></p>
            <p><strong>Teléfono de Contacto:</strong> <?= htmlspecialchars($row['contacto']) ?></p>
        </div>
        <div>
            <p><strong>Evento:</strong> <?= htmlspecialchars($row['nombre_evento']) ?></p>
            <p><strong>Fecha del Evento:</strong> <?= htmlspecialchars($row['fecha_evento']) ?></p>
            <p><strong>Ubicación / Sede:</strong> <?= htmlspecialchars($row['ubicacion']) ?></p>
        </div>
    </div>

    <h4 class="contrato-section-title">1. Alcance y Especificación Técnica del Servicio</h4>
    <p style="font-size:0.94rem; line-height:1.65;"><?= nl2br(htmlspecialchars($row['descripcion_tecnica'])) ?></p>
    <div class="monto-destacado">
        <span>Inversión Total Acordada (MXN):</span>
        <strong>$<?= number_format($row['monto_total'], 2) ?> MXN</strong>
    </div>

    <h4 class="contrato-section-title">2. Cláusulas y Condiciones Generales</h4>
    <p style="font-size:0.93rem;"><?= nl2br(htmlspecialchars($row['condiciones'])) ?></p>
    <div style="font-size:0.9rem; color:#334155; line-height:1.65;">
        <p><strong>Primera (Anticipo):</strong> Se requiere un anticipo mínimo del 50% para la confirmación formal del evento y bloqueo de fecha.</p>
        <p><strong>Segunda (Disponibilidad):</strong> El equipo y personal técnico están sujetos a validación de disponibilidad previa a la firma.</p>
        <p><strong>Tercera (Cancelación y Descuento):</strong> Si el cliente cancela el evento, la empresa retendrá, como descuento sobre los pagos recibidos, un porcentaje del monto total del contrato según la anticipación con que se solicite la cancelación:</p>
        <p><strong>Cuarta (Facturaciones):</strong> Las facturaciones solo se haran por metodo SPEI a la siguiente cuenta CLABE 12345678965487164.</p>
        <ul>
            <?php foreach (texto_clausula_cancelacion() as $l): ?><li><?= htmlspecialchars($l) ?>.</li><?php endforeach; ?>
        </ul>
        <p>La diferencia entre lo pagado y el descuento retenido se reembolsará al cliente. Si lo pagado no cubre el descuento, el cliente cubrirá el saldo restante.</p>
        <p><strong>Cuarta (Modificación de Fechas):</strong> El cliente puede solicitar el cambio de fecha del evento con al menos <?= DIAS_MIN_REPROGRAMACION_CLIENTE ?> días de anticipación, sujeto a la disponibilidad de equipo y personal. El evento pasará a estado Reprogramado y la nueva fecha sustituye a la anterior en este contrato.</p>
    </div>

    <?php if ($row['estatus'] === 'Cancelado'): ?>
    <div style="border:2px solid #dc2626; background:#fef2f2; border-radius:8px; padding:1.2rem; margin-top:1.5rem;">
        <h4 style="color:#b91c1c; margin-top:0;">CONTRATO CANCELADO</h4>
        <p style="margin-bottom:0; font-size:0.92rem;"><strong>Fecha de cancelación:</strong> <?= htmlspecialchars($row['fecha_cancelacion']) ?><br>
           <strong>Motivo:</strong> <?= htmlspecialchars($row['motivo_cancelacion']) ?><br>
           <strong>Descuento aplicado:</strong> <?= number_format($row['porcentaje_penalizacion'], 0) ?>% ($<?= number_format($row['monto_penalizacion'], 2) ?> MXN)<br>
           <strong>Reembolso al cliente:</strong> $<?= number_format($row['monto_reembolso'], 2) ?> MXN
           <?php if ($row['monto_adeudo'] > 0): ?><br><strong>Saldo a cargo del cliente:</strong> $<?= number_format($row['monto_adeudo'], 2) ?> MXN<?php endif; ?></p>
    </div>
    <?php endif; ?>

    <div class="firmas">
        <div>Quinto Canal de Eventos<br><small style="font-weight:400; color:#64748b;">Prestador del Servicio</small></div>
        <div><?= htmlspecialchars($row['cliente']) ?><br><small style="font-weight:400; color:#64748b;">Cliente Contratante</small></div>
    </div>

    <div class="no-print" style="display:flex; justify-content:center; gap:0.8rem; margin-top:2.5rem;">
        <a href="panel.php" class="btn" style="width:auto; background:#475569; text-decoration:none; padding:0.7rem 1.4rem;">Volver al Panel</a>
        <button class="btn" style="width:auto; padding:0.7rem 1.6rem;" onclick="window.print()">Imprimir / Guardar como PDF</button>
    </div>
</div>
</body>
</html>
