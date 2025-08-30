#!/usr/bin/env bash
set -euo pipefail
NAME="${1:-}"
test -n "$NAME"
rm -f "/etc/nginx/sites-enabled/$NAME.conf" || true
/usr/sbin/nginx -t
