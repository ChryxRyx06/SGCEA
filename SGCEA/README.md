# SGCEA — Sistema de Gestión de Cotizaciones y Eventos Audiovisuales

**Quinto Canal de Eventos**  
Plataforma web integral para la gestión comercial, operativa y logística de servicios de producción audiovisual (audio, iluminación, video y escenografía).

## Arquitectura y Stack Tecnológico
- **Frontend:** HTML5, CSS3 (Diseño responsivo corporativo), JavaScript Vanilla (Fetch API / ES6+).
- **Backend:** PHP 8.x con arquitectura modular orientada a endpoints REST/JSON y control de sesiones por rol.
- **Base de Datos:** MySQL / MariaDB (`sgcea_db`) con integridad referencial (`InnoDB`) y transacciones ACID.

## Roles del Sistema (RF-10)
| Rol | Usuario Demo | Contraseña | Permisos Principales |
| :--- | :--- | :--- | :--- |
| **Administrador** | `admin@quintocanal.com` | `admin123` | Gestión de clientes (validación RFC/Teléfono), cotizaciones, generación y cancelación de contratos, control de eventos, registro de pagos, inventario de recursos e historial. |
| **Técnico** | `tecnico@quintocanal.com` | `tecnico123` | Consulta de eventos, confirmación/cierre operativo con reporte técnico, consulta de disponibilidad por fecha, asignación de equipos y actualización de estado físico. |
| **Cliente** | `cliente@quintocanal.com` | `cliente123` | Solicitud de cotizaciones (personalizadas o por paquete), aprobación/rechazo de propuestas, consulta e impresión de contratos, reprogramación/cancelación y seguimiento de pagos e historial. |

## Instalación Rápida (XAMPP / WAMP / Laragon)
1. Copia la carpeta `SGCEA` dentro del directorio público de tu servidor web (`htdocs/`).
2. Inicia los servicios de **Apache** y **MySQL**.
3. Importa el archivo `schema.sql` desde **phpMyAdmin** o consola MySQL para crear la base de datos `sgcea_db` con sus datos iniciales.
4. Verifica las credenciales en `conexion.php` (`localhost`, `root`, sin contraseña por defecto).
5. Abre `http://localhost/SGCEA/index.html` en tu navegador.


## Despliegue en la Nube (Render / Railway + MySQL Remoto)
El proyecto está preparado para ejecutarse en servicios PaaS mediante Docker y variables de entorno:
1. **Base de datos remota:** Crea una instancia gratuita de MySQL/MariaDB (por ejemplo en **Aiven**, **Filess.io** o **Railway**) e importa el archivo `schema_cloud.sql`.
2. **Repositorio GitHub:** Sube el contenido de la carpeta `SGCEA` a un repositorio en GitHub.
3. **Servicio Web:** Crea un nuevo **Web Service** en **Render** o **Railway** apuntando al repositorio (detectará automáticamente el `Dockerfile` con PHP 8.2 + Apache + `mysqli` y puerto dinámico `$PORT`).
4. **Variables de Entorno:** Configura `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER` y `DB_PASS` (o `MYSQL_URL`) en el panel de tu proveedor.
