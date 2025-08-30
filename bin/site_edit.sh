#!/usr/bin/env bash
set -euo pipefail

OLD_NAME="${1:-}"
NEW_NAME="${2:-}"
SERVER_NAMES="${3:-}"
ROOT="${4:-}"
MAX_UPLOAD="${5:-20}"
WITH_LOGS="${6:-1}"
# reserved flag $7 (unused)

if [[ -z "$OLD_NAME" || -z "$NEW_NAME" || -z "$SERVER_NAMES" || -z "$ROOT" ]]; then
  echo "Usage: site_edit.sh <old_name> <new_name> <server_names> <root> <max_upload> <with_logs>" >&2
  exit 1
fi

CONF_OLD="/etc/nginx/sites-available/$OLD_NAME.conf"
CONF_NEW="/etc/nginx/sites-available/$NEW_NAME.conf"

# Read PHP from existing conf if present, else default 8.3
PHP_VERSION="$(grep -Po 'php\K[0-9\.]+(?=-fpm\.sock)' "$CONF_OLD" 2>/dev/null || echo "8.3")"

# If name changed, adjust symlinks
if [[ "$OLD_NAME" != "$NEW_NAME" ]]; then
  rm -f "/etc/nginx/sites-enabled/$OLD_NAME.conf" || true
fi

# Write new conf
cat > "$CONF_NEW" <<'NGINX'
server {
    listen 80;
    server_name {SERVER_NAMES};
    root {ROOT};
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php{PHP_VERSION}-fpm.sock;
    }

    access_log /var/log/nginx/{NAME}.access.log;
    error_log  /var/log/nginx/{NAME}.error.log;
    client_max_body_size {MAX_UPLOAD}M;
}

NGINX
sed -i "s#{{SERVER_NAMES}}#${SERVER_NAMES}#g" "$CONF_NEW"
sed -i "s#{{ROOT}}#${ROOT}#g" "$CONF_NEW"
sed -i "s#{{PHP_VERSION}}#${PHP_VERSION}#g" "$CONF_NEW"
sed -i "s#{{NAME}}#${NEW_NAME}#g" "$CONF_NEW"
sed -i "s#{{MAX_UPLOAD}}#${MAX_UPLOAD}#g" "$CONF_NEW"

ln -sf "$CONF_NEW" "/etc/nginx/sites-enabled/$NEW_NAME.conf"

/usr/sbin/nginx -t
