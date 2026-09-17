FROM php:8.5-apache

# Extensions PHP nécessaires à InvestPro
RUN docker-php-ext-install \
    pdo_mysql \
    mbstring

# Activer mod_rewrite
RUN a2enmod rewrite

# Configuration Apache pour l'application
RUN printf '%s\n' \
    '<Directory /var/www/html>' \
    '    AllowOverride All' \
    '    Require all granted' \
    '</Directory>' \
    > /etc/apache2/conf-available/investpro.conf \
    && a2enconf investpro

# Installer Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copier les fichiers Composer
COPY composer.json composer.lock* ./

# Installer les dépendances PHP
RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader

# Copier l'application
COPY . .

# Permissions Apache
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]
