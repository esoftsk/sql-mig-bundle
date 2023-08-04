FROM dunglas/frankenphp:main-alpine

RUN install-php-extensions pdo_pgsql

RUN apk add --no-cache composer postgresql-client 7zip

RUN sed -i 's/\/usr\/bin\/php81/\/usr\/local\/bin\/php/g' /usr/bin/composer

RUN rm -rf /app && \
    export COMPOSER_ALLOW_SUPERUSER=1 && \
    composer -q create-project symfony/skeleton:"6.3.*" /app && \
    cd /app && \
    composer -q install --no-interaction --no-dev --optimize-autoloader && \
    composer -q config minimum-stability dev && \
    composer -q require esoftsk/sql-mig-bundle --no-interaction --optimize-autoloader
