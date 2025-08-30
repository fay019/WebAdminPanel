#!/usr/bin/env bash
set -euo pipefail

# Wrapper pour actions d'alimentation (Raspberry Pi / Debian)
# Usage: power.sh shutdown|reboot
# Sorties:
#   OK: ... en cas de succès
#   ERR: ... en cas d'erreur

cmd="$1" 2>/dev/null || { echo "ERR: missing command (shutdown|reboot)"; exit 1; }
case "$cmd" in
  shutdown)
    echo "[INFO] Demande d'arrêt immédiat (shutdown -h now)"
    /sbin/shutdown -h now || /usr/sbin/shutdown -h now || { echo "ERR: shutdown failed"; exit 1; }
    echo "OK: shutdown triggered"
    ;;
  reboot)
    echo "[INFO] Demande de redémarrage (reboot)"
    /sbin/reboot || /usr/sbin/reboot || { echo "ERR: reboot failed"; exit 1; }
    echo "OK: reboot triggered"
    ;;
  *)
    echo "ERR: unknown command '$cmd' (expected: shutdown|reboot)"; exit 1;
    ;;
 esac
