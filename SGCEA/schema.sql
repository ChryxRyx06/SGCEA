-- =====================================================================
-- SGCEA - Sistema de Gestión de Cotizaciones y Eventos Audiovisuales
-- Esquema Unificado de Base de Datos (Versión Consolidada)
-- Quinto Canal de Eventos
-- Importar este único archivo en phpMyAdmin / MySQL para instalar todo
-- el sistema desde cero y en completa armonía.
-- =====================================================================

DROP DATABASE IF EXISTS sgcea_db;
CREATE DATABASE sgcea_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sgcea_db;

-- ---------------------------------------------------------------------
-- 1. Clientes (RD-01)
-- ---------------------------------------------------------------------
CREATE TABLE clientes (
    id_cliente INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    contacto VARCHAR(150) NOT NULL,
    direccion VARCHAR(255) NOT NULL,
    datos_fiscales VARCHAR(255) NOT NULL,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 2. Usuarios del sistema (RF-10: acceso diferenciado por rol)
-- ---------------------------------------------------------------------
CREATE TABLE usuarios (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(120) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    rol ENUM('Administrador','Tecnico','Cliente') NOT NULL DEFAULT 'Cliente',
    id_cliente INT NULL, -- Enlaza con clientes.id_cliente cuando rol = 'Cliente'
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_usuarios_cliente
        FOREIGN KEY (id_cliente) REFERENCES clientes(id_cliente)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 3. Catálogo de Paquetes Predeterminados (Audio, Iluminación, Combos)
-- ---------------------------------------------------------------------
CREATE TABLE paquetes_predeterminados (
    id_paquete INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(120) NOT NULL,
    categoria ENUM('Audio','Iluminación','Audio e Iluminación') NOT NULL,
    descripcion TEXT NOT NULL,
    servicios_incluidos VARCHAR(150) NOT NULL,
    costo DECIMAL(10,2) NOT NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 4. Cotizaciones (RD-02)
-- ---------------------------------------------------------------------
CREATE TABLE cotizaciones (
    id_cotizacion INT AUTO_INCREMENT PRIMARY KEY,
    id_cliente INT NOT NULL,
    nombre_evento VARCHAR(150) NOT NULL,
    descripcion_tecnica TEXT NOT NULL,
    monto_total DECIMAL(10,2) NOT NULL,
    vigencia DATE NOT NULL,
    estado ENUM('Pendiente','Aprobada','Rechazada','Vencida') NOT NULL DEFAULT 'Pendiente',
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_cotizaciones_cliente
        FOREIGN KEY (id_cliente) REFERENCES clientes(id_cliente)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 5. Servicios audiovisuales incluidos en cada cotización (RD-06)
-- ---------------------------------------------------------------------
CREATE TABLE cotizacion_servicios (
    id_servicio INT AUTO_INCREMENT PRIMARY KEY,
    id_cotizacion INT NOT NULL,
    servicio VARCHAR(60) NOT NULL,
    CONSTRAINT fk_cotizacion_servicios_cot
        FOREIGN KEY (id_cotizacion) REFERENCES cotizaciones(id_cotizacion)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 6. Contratos (RD-03) - Incluye política de cancelación y penalizaciones
-- ---------------------------------------------------------------------
CREATE TABLE contratos (
    id_contrato INT AUTO_INCREMENT PRIMARY KEY,
    id_cotizacion INT NOT NULL UNIQUE,
    fecha_evento DATE NOT NULL,
    ubicacion VARCHAR(255) NOT NULL,
    condiciones TEXT NOT NULL,
    estatus ENUM('Activo','Cancelado') NOT NULL DEFAULT 'Activo',
    fecha_cancelacion DATETIME NULL,
    motivo_cancelacion VARCHAR(255) NULL,
    porcentaje_penalizacion DECIMAL(5,2) NULL,
    monto_penalizacion DECIMAL(10,2) NULL,
    monto_reembolso DECIMAL(10,2) NULL,
    monto_adeudo DECIMAL(10,2) NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_contratos_cotizacion
        FOREIGN KEY (id_cotizacion) REFERENCES cotizaciones(id_cotizacion)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 7. Eventos (RD-04)
-- ---------------------------------------------------------------------
CREATE TABLE eventos (
    id_evento INT AUTO_INCREMENT PRIMARY KEY,
    id_contrato INT NOT NULL,
    fecha DATE NOT NULL,
    horario TIME NOT NULL,
    ubicacion VARCHAR(255) NOT NULL,
    tipo_evento VARCHAR(60) NOT NULL DEFAULT 'Gala / Privado',
    estado ENUM('Programado','Confirmado','Reprogramado','Cancelado','Finalizado') NOT NULL DEFAULT 'Programado',
    CONSTRAINT fk_eventos_contrato
        FOREIGN KEY (id_contrato) REFERENCES contratos(id_contrato)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 8. Bitácora de modificaciones de fecha de eventos
-- ---------------------------------------------------------------------
CREATE TABLE modificaciones_fecha (
    id_modificacion INT AUTO_INCREMENT PRIMARY KEY,
    id_evento INT NOT NULL,
    fecha_anterior DATE NOT NULL,
    fecha_nueva DATE NOT NULL,
    motivo VARCHAR(255) NULL,
    id_usuario INT NULL,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_modificaciones_evento
        FOREIGN KEY (id_evento) REFERENCES eventos(id_evento)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_modificaciones_usuario
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 9. Pagos (RD-05)
-- ---------------------------------------------------------------------
CREATE TABLE pagos (
    id_pago INT AUTO_INCREMENT PRIMARY KEY,
    id_evento INT NOT NULL,
    monto DECIMAL(10,2) NOT NULL,
    tipo VARCHAR(30) NOT NULL,       -- 'Anticipo 50%' o 'Pago Final'
    metodo VARCHAR(30) NOT NULL,     -- 'Efectivo', 'Transferencia', 'Tarjeta'
    referencia VARCHAR(100),
    fecha DATE NOT NULL,
    estatus VARCHAR(20) NOT NULL DEFAULT 'Pagado',
    CONSTRAINT fk_pagos_evento
        FOREIGN KEY (id_evento) REFERENCES eventos(id_evento)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 10. Recursos audiovisuales y personal (RD-06) con estado operativo
-- ---------------------------------------------------------------------
CREATE TABLE recursos (
    id_recurso INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(120) NOT NULL,
    tipo ENUM('Audio','Iluminación','Video','Personal','Transporte') NOT NULL,
    estado ENUM('Disponible','En reparación','Dañado') NOT NULL DEFAULT 'Disponible'
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 11. Asignación de recursos a eventos (RD-07)
-- ---------------------------------------------------------------------
CREATE TABLE asignacion_recursos (
    id_asignacion INT AUTO_INCREMENT PRIMARY KEY,
    id_evento INT NOT NULL,
    id_recurso INT NOT NULL,
    fecha DATE NOT NULL,
    CONSTRAINT fk_asignacion_evento
        FOREIGN KEY (id_evento) REFERENCES eventos(id_evento)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_asignacion_recurso
        FOREIGN KEY (id_recurso) REFERENCES recursos(id_recurso)
        ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE KEY recurso_fecha_unico (id_recurso, fecha)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 12. Historial de eventos finalizados (RD-08)
-- ---------------------------------------------------------------------
CREATE TABLE historial_eventos (
    id_historial INT AUTO_INCREMENT PRIMARY KEY,
    id_evento INT NOT NULL UNIQUE,
    servicios_brindados TEXT NOT NULL,
    resultado VARCHAR(255) NOT NULL,
    seguimiento_cliente TEXT,
    CONSTRAINT fk_historial_evento
        FOREIGN KEY (id_evento) REFERENCES eventos(id_evento)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- =====================================================================
-- DATOS INICIALES DE PRODUCCIÓN / DEMOSTRACIÓN
-- =====================================================================

-- Cliente de ejemplo
INSERT INTO clientes (nombre, contacto, direccion, datos_fiscales) VALUES
('Grupo Empresarial Orizaba', '272-123-4567', 'Av. Poniente 5, Orizaba, Ver.', 'GEO900101ABC');

-- Usuarios de acceso (el sistema detecta automáticamente el rol por correo)
INSERT INTO usuarios (nombre, email, password, rol, id_cliente) VALUES
('Administrador General', 'admin@quintocanal.com', 'admin123', 'Administrador', NULL),
('Técnico de Campo', 'tecnico@quintocanal.com', 'tecnico123', 'Tecnico', NULL),
('Cliente Demo', 'cliente@quintocanal.com', 'cliente123', 'Cliente', 1);

-- Inventario consolidado de recursos por categoría
INSERT INTO recursos (nombre, tipo, estado) VALUES
-- Audio (6 unidades)
('Consola Digital Yamaha QL5 #1', 'Audio', 'Disponible'),
('Consola de Audio JBL #2', 'Audio', 'Disponible'),
('Sistema Line Array JBL VTX #1', 'Audio', 'Disponible'),
('Sistema Line Array JBL VTX #2', 'Audio', 'Disponible'),
('Set de Micrófonos Inalámbricos Shure #1', 'Audio', 'Disponible'),
('Set de Micrófonos Inalámbricos Shure #2', 'Audio', 'Disponible'),
-- Iluminación (5 unidades)
('Cabezas Móviles Beam 230W (Set A)', 'Iluminación', 'Disponible'),
('Cabezas Móviles Beam 230W (Set B)', 'Iluminación', 'Disponible'),
('Par LED RGBW Ambiental (Set 12 pzas)', 'Iluminación', 'Disponible'),
('Controlador DMX GrandMA2', 'Iluminación', 'Disponible'),
('Máquina de Humo y Chispas Frías', 'Iluminación', 'Disponible'),
-- Video (4 unidades)
('Pantalla LED Pitch 3.9mm 4x3m #1', 'Video', 'Disponible'),
('Pantalla LED Pitch 3.9mm 4x3m #2', 'Video', 'Disponible'),
('Procesador de Video Novastar VX600', 'Video', 'Disponible'),
('Kit Circuito Cerrado 2 Cámaras HD', 'Video', 'Disponible'),
-- Personal (5 elementos)
('Ingeniero de Audio en Sala', 'Personal', 'Disponible'),
('Técnico de Audio y Monitoreo', 'Personal', 'Disponible'),
('Operador de Iluminación DMX', 'Personal', 'Disponible'),
('Técnico de Video y Pantallas LED', 'Personal', 'Disponible'),
('Coordinador de Montaje y Escenario', 'Personal', 'Disponible'),
-- Transporte (3 unidades)
('Camioneta Van de Carga #1', 'Transporte', 'Disponible'),
('Camioneta Van de Carga #2', 'Transporte', 'Disponible'),
('Camión 3.5 Toneladas Caja Seca', 'Transporte', 'Disponible');

-- Catálogo completo de paquetes predeterminados
INSERT INTO paquetes_predeterminados (nombre, categoria, descripcion, servicios_incluidos, costo) VALUES
('Paquete Audio Esencial (Hasta 80 personas)', 'Audio', '2 Bocinas amplificadas de 15" en tripié, consola mezcladora de 8 canales, 2 micrófonos inalámbricos Shure, cableado completo y técnico operador en sitio.', 'Audio', 4500.00),
('Paquete Audio Pro Concierto / Conferencia (Hasta 250 personas)', 'Audio', 'Sistema Line Array JBL (4 módulos + 2 subwoofers de 18"), consola digital Yamaha QL5, 4 micrófonos inalámbricos, monitores de piso e ingeniero de audio en sala.', 'Audio', 12500.00),
('Paquete Iluminación Ambiental & Fiesta', 'Iluminación', '8 Par LED RGBW arquitectónicos, 4 cabezas móviles Beam 230W en estructura truss vertical, controlador DMX y máquina de humo.', 'Iluminación', 5200.00),
('Paquete Iluminación Escénica Espectáculo', 'Iluminación', 'Puente truss de 6 metros, 8 cabezas móviles Beam 230W, 12 Par LED RGBW, 2 máquinas de chispas frías, controlador DMX GrandMA2 y operador dedicado.', 'Iluminación', 11800.00),
('Paquete Combo Audio + Iluminación Básico', 'Audio e Iluminación', 'Audio para 100 personas (2 bafles activos + subwoofer 18" + 2 micrófonos inalámbricos) + Iluminación ambiental (6 Par LED RGBW + 2 cabezas móviles Beam + humo) con técnico operador.', 'Audio,Iluminación', 8500.00),
('Paquete Combo Audio + Iluminación Gala / Gran Evento', 'Audio e Iluminación', 'Sistema Line Array completo + consola digital + 4 micrófonos Shure + puente truss con 8 cabezas móviles Beam, 12 Par LED, chispas frías, transporte y 2 técnicos especializados.', 'Audio,Iluminación', 21500.00);
