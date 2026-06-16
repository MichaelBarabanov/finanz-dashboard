# Schlankes PHP-CLI-Image. Kein nginx nötig – wir nutzen `php artisan serve`
# (vollkommen ausreichend für ein privates Single-User-Tool).
FROM php:8.3-cli

# System-Abhängigkeiten:
# - libsqlite3 + pdo_sqlite : Datenbank
# - poppler-utils (pdftotext): später für PDF-Import (Phase 5)
# - libzip/zip/unzip + git   : composer install
RUN apt-get update && apt-get install -y --no-install-recommends \
        libsqlite3-dev \
        poppler-utils \
        libzip-dev zip unzip \
        git \
    && docker-php-ext-install pdo_sqlite zip opcache \
    && rm -rf /var/lib/apt/lists/*

# OPcache: kompiliertes PHP im Speicher halten -> viel weniger Datei-/Compile-Last
# pro Request (wichtig auf langsamen Windows-Bind-Mounts). revalidate_freq=0
# prüft Zeitstempel pro Request, damit Code-Änderungen ohne Rebuild greifen.
RUN { \
        echo 'opcache.enable=1'; \
        echo 'opcache.enable_cli=0'; \
        echo 'opcache.memory_consumption=128'; \
        echo 'opcache.max_accelerated_files=20000'; \
        echo 'opcache.validate_timestamps=1'; \
        echo 'opcache.revalidate_freq=0'; \
        echo 'realpath_cache_size=4096K'; \
        echo 'realpath_cache_ttl=600'; \
    } > /usr/local/etc/php/conf.d/zz-opcache.ini

# Composer aus dem offiziellen Image übernehmen.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Entrypoint kümmert sich um composer install, key:generate, migrate, serve.
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 8000

ENTRYPOINT ["entrypoint.sh"]
