#!/usr/bin/env bash
set -euo pipefail
# Usage: site_delete.sh <name> <yes|no>
if [[ $# -lt 2 ]]; then echo "Usage: $(basename "$0") <name> <yes|no>" >&2; exit 1; fi
name="$1"; del="$2"
conf="/etc/nginx/sites-available/${name}.conf"
link="/etc/nginx/sites-enabled/${name}.conf"

echo "[info] deleting site '$name' (delete_root=$del)"
[[ -L "$link" ]] && rm -f "$link" && echo "[ok] disabled: $link"
if [[ -f "$conf" ]]; then
  # lire doc_root AVANT de supprimer la conf
  doc_root="$(awk '/^[[:space:]]*root[[:space:]]+/ {gsub(/;/,"",$2); print $2; exit}' "$conf" || true)"
  rm -f "$conf" && echo "[ok] removed conf: $conf"
else
  doc_root=""
fi

[[ -f "/var/log/nginx/${name}.access.log" ]] && rm -f "/var/log/nginx/${name}.access.log"
[[ -f "/var/log/nginx/${name}.error.log"  ]] && rm -f "/var/log/nginx/${name}.error.log"

if [[ "$del" == "yes" ]]; then
  candidate=""
  if [[ -n "$doc_root" ]]; then
    site_dir="$(dirname "$doc_root")"   # /var/www/tmp/public -> /var/www/tmp
    if [[ "$(basename "$site_dir")" == "$name" && "$site_dir" == /var/www/* ]]; then
      candidate="$site_dir"
    fi
  fi
  [[ -z "$candidate" ]] && candidate="/var/www/${name}"

  if [[ -d "$candidate" && "$candidate" == /var/www/* ]]; then
    rm -rf -- "$candidate"
    echo "[ok] removed dir: $candidate"
  else
    echo "[skip] dir removal (unsafe or missing): $candidate"
  fi
fi

if command -v nginx >/dev/null; then nginx -t || true; fi
echo "[done] site '$name' deleted (root removal: $del)"
