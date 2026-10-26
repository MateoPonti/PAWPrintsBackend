# PHP + Apache con la raíz del sitio en public/ (para Render). Incluye pdo_pgsql para Neon.
FROM php:8.3-apache

# pdo_pgsql necesita las cabeceras de libpq; git y unzip los usa Composer
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev git unzip \
    && docker-php-ext-install pdo pdo_pgsql \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Configuración de producción de PHP: display_errors desactivado, errores al log
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Document root = public/ y .htaccess habilitado
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf \
    && echo "ServerName localhost" > /etc/apache2/conf-available/servername.conf \
    && a2enconf servername

WORKDIR /var/www/html
COPY . /var/www/html/

# Dependencias (PHPMailer, psr/log) y autoload (--no-audit evita bloqueos de seguridad en el build)
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-audit

# Apache corre como www-data: necesita escribir el log
RUN mkdir -p /var/www/html/logs \
    && chown -R www-data:www-data /var/www/html/logs

EXPOSE 80