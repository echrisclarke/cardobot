#!/bin/bash
set -e
export PORT="${PORT:-8080}"

# PHP module requires prefork; keep a single MPM enabled.
rm -f /etc/apache2/mods-enabled/mpm_event.load \
      /etc/apache2/mods-enabled/mpm_event.conf \
      /etc/apache2/mods-enabled/mpm_worker.load \
      /etc/apache2/mods-enabled/mpm_worker.conf
if [ ! -e /etc/apache2/mods-enabled/mpm_prefork.load ]; then
  a2enmod mpm_prefork >/dev/null
fi

# Always render Apache listen/vhost from templates so restarts stay correct.
sed "s/\${PORT}/${PORT}/g" /etc/apache2/ports.conf.template > /etc/apache2/ports.conf
sed "s/\${PORT}/${PORT}/g" /etc/apache2/sites-available/000-default.conf.template \
  > /etc/apache2/sites-available/000-default.conf

mkdir -p "${UPLOAD_ROOT:-/data/uploads}" /var/www/html/public/uploads
chown -R www-data:www-data "${UPLOAD_ROOT:-/data/uploads}" /var/www/html/public/uploads || true

# Apply CREATE TABLE IF NOT EXISTS schema (idempotent).
if [ -f /var/www/html/database/migrate.php ]; then
  php /var/www/html/database/migrate.php || true
fi

exec apache2-foreground
