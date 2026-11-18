FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libonig5 libonig-dev \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql mbstring \
    && apt-get purge -y --auto-remove libonig-dev \
    && rm -rf /var/lib/apt/lists/* \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && mkdir -p /var/lib/php/sessions \
    && chown www-data:www-data /var/lib/php/sessions \
    && printf '%s\n' \
        'date.timezone=Asia/Ho_Chi_Minh' \
        'session.save_path=/var/lib/php/sessions' \
        'session.cookie_httponly=1' \
        'session.cookie_samesite=Lax' \
        'session.use_strict_mode=1' \
        'expose_php=Off' \
        > "$PHP_INI_DIR/conf.d/app.ini" \
    && printf '%s\n' \
        'ServerName localhost' \
        '<Directory /var/www/html>' \
        '    Options -Indexes' \
        '</Directory>' \
        '<DirectoryMatch "^/var/www/html/(config|includes)(/|$)">' \
        '    Require all denied' \
        '</DirectoryMatch>' \
        > /etc/apache2/conf-available/app.conf \
    && a2enconf app

WORKDIR /var/www/html
COPY *.php ./
COPY actions/ ./actions/
COPY assets/ ./assets/
COPY config/ ./config/
COPY includes/ ./includes/

HEALTHCHECK --interval=10s --timeout=5s --start-period=20s --retries=5 \
    CMD php -r 'exit(@file_get_contents("http://127.0.0.1/login.php") === false ? 1 : 0);'
