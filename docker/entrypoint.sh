#!/bin/bash
set -e
export PORT="${PORT:-8080}"

# Rewrite Listen / VirtualHost port from env
sed -i "s/\${PORT}/${PORT}/g" /etc/apache2/ports.conf
sed -i "s/\${PORT}/${PORT}/g" /etc/apache2/sites-available/000-default.conf

mkdir -p "${UPLOAD_ROOT:-/data/uploads}" /var/www/html/public/uploads
chown -R www-data:www-data "${UPLOAD_ROOT:-/data/uploads}" /var/www/html/public/uploads || true

exec apache2-foreground
