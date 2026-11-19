FROM php:8.2-apache

# Instalar extensión mysqli y pdo_mysql para conexión con MySQL/MariaDB
RUN docker-php-ext-install mysqli pdo pdo_mysql && docker-php-ext-enable mysqli

# Habilitar mod_rewrite de Apache
RUN a2enmod rewrite

# Copiar el código de la aplicación al directorio raíz de Apache
COPY . /var/www/html/

# Ajustar permisos de lectura/escritura
RUN chown -R www-data:www-data /var/www/html

# Configurar Apache para escuchar en el puerto dinámico ($PORT) asignado por Render/Railway
CMD ["sh", "-c", "PORT=${PORT:-80}; sed -i \"s/Listen 80/Listen ${PORT}/g\" /etc/apache2/ports.conf && sed -i \"s/:80/:${PORT}/g\" /etc/apache2/sites-available/000-default.conf && apache2-foreground"]