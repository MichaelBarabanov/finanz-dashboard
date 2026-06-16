#!/usr/bin/env bash
set -e

cd /app

echo "==> Finanz Dashboard startet ..."

# 1) .env sicherstellen
if [ ! -f .env ]; then
  echo "==> Keine .env gefunden, kopiere .env.example"
  cp .env.example .env
fi

# 2) Abhängigkeiten installieren (nur beim ersten Mal / wenn vendor fehlt)
if [ ! -d vendor ]; then
  echo "==> Installiere Composer-Abhängigkeiten (einmalig, kann dauern) ..."
  composer install --no-interaction --prefer-dist --optimize-autoloader
fi

# 3) App-Key erzeugen, falls leer
if ! grep -q "^APP_KEY=base64:" .env; then
  echo "==> Erzeuge APP_KEY ..."
  php artisan key:generate --force
fi

# 4) SQLite-Datei sicherstellen
mkdir -p database
if [ ! -f database/finanz.sqlite ]; then
  echo "==> Lege leere SQLite-Datenbank an ..."
  touch database/finanz.sqlite
fi

# 5) Schreibrechte für storage/cache
chmod -R ug+rw storage bootstrap/cache || true

# 6) Migrationen + Seed (idempotent)
echo "==> Migrationen ausführen ..."
php artisan migrate --force --seed

# 7) Caches frisch
php artisan config:clear || true
php artisan view:clear || true

echo "==> Bereit: http://localhost:8000"
php artisan serve --host=0.0.0.0 --port=8000
