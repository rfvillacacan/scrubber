FROM php:8.3-fpm

ARG APP_UID=1000
ARG APP_GID=1000

RUN apt-get update \
    && apt-get install -y --no-install-recommends libsqlite3-dev \
    && docker-php-ext-install pdo_sqlite \
    && groupmod -o -g ${APP_GID} www-data \
    && usermod -o -u ${APP_UID} -g ${APP_GID} www-data \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

# Align FPM worker user/group with mounted host files permissions.
RUN sed -ri 's/^user = .*/user = www-data/' /usr/local/etc/php-fpm.d/www.conf \
    && sed -ri 's/^group = .*/group = www-data/' /usr/local/etc/php-fpm.d/www.conf \
    && sed -ri 's|^listen = .*|listen = 9000|' /usr/local/etc/php-fpm.d/www.conf \
    && sed -ri 's/^;?clear_env = .*/clear_env = no/' /usr/local/etc/php-fpm.d/www.conf
