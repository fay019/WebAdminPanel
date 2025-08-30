#!/usr/bin/env bash
set -euo pipefail
NAME="${1:-}"
test -n "$NAME"
CONF="/etc/nginx/sites-available/$NAME.conf"
ln -sf "$CONF" "/etc/nginx/sites-enabled/$NAME.conf"
/usr/sbin/nginx -t
