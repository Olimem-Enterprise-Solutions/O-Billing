# syntax=docker/dockerfile:1
#
# O-Billing cloud "Sage worker" image. Unlike the web app (built by Railpack with
# NO SQL Server driver), this image bundles Microsoft's SQL Server driver so it
# can run the Sage queue jobs, reaching the council's on-prem SQL Server through
# the Railtail/Tailscale forwarder. Used ONLY by the worker service on Railway.

FROM php:8.4-cli-bookworm

# ---- system packages + Microsoft ODBC Driver 18 + PHP extensions -------------
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        ca-certificates curl gnupg apt-transport-https \
        unixodbc-dev libgssapi-krb5-2 \
        libicu-dev libzip-dev libpq-dev \
        libpng-dev libjpeg-dev libfreetype6-dev \
        git unzip $PHPIZE_DEPS; \
    # Microsoft package repo (Debian 12 = bookworm) for the ODBC driver
    curl -sSL https://packages.microsoft.com/keys/microsoft.asc | gpg --dearmor -o /usr/share/keyrings/microsoft-prod.gpg; \
    curl -sSL https://packages.microsoft.com/config/debian/12/prod.list -o /etc/apt/sources.list.d/mssql-release.list; \
    apt-get update; \
    ACCEPT_EULA=Y apt-get install -y --no-install-recommends msodbcsql18; \
    # PHP extensions the app requires (composer.json: ext-gd, ext-intl, ext-zip)
    docker-php-ext-configure gd --with-freetype --with-jpeg; \
    docker-php-ext-install -j"$(nproc)" gd intl zip pdo_pgsql; \
    # Microsoft SQL Server driver (used by the sage / sage_write connections)
    pecl install sqlsrv pdo_sqlsrv; \
    docker-php-ext-enable sqlsrv pdo_sqlsrv; \
    rm -rf /var/lib/apt/lists/*

# ---- composer + application --------------------------------------------------
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app
COPY . .
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# Drain ONLY the sage queue. --tries=1 is deliberate: posting must never silently
# retry (the poster's double-post guard is the only safe re-entry point).
CMD ["php", "artisan", "queue:work", "--queue=sage", "--tries=1", "--timeout=0", "--sleep=3"]
