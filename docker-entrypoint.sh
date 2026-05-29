#!/bin/bash
set -euo pipefail

STORAGE=/var/www/html/storage

mkdir -p "${STORAGE}/uploads/excel" \
         "${STORAGE}/uploads/images" \
         "${STORAGE}/cache/qr"

chown -R www-data:www-data "${STORAGE}"
chmod -R 775 "${STORAGE}"

exec "$@"
