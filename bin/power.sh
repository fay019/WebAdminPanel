#!/usr/bin/env bash
set -euo pipefail

# Wrapper pour actions d'alimentation (Raspberry Pi / Debian)
# Usage: power.sh shutdown|reboot
# Sorties:
#   stdout: "OK: reboot triggered" ou "OK: shutdown triggered" en cas de succès (une seule ligne)
#   stderr: lignes d'information [INFO] et messages d'erreur
#   code de sortie: 0 si dispatch réussi, !=0 en cas d'échec

cmd="$1" 2>/dev/null || { echo "ERR: missing command (shutdown|reboot)" >&2; exit 1; }
case "$cmd" in
  shutdown)
    echo "[INFO] Demande d'arrêt immédiat (shutdown -h now)" >&2
    /sbin/shutdown -h now || /usr/sbin/shutdown -h now || { echo "ERR: shutdown failed" >&2; exit 1; }
    echo "OK: shutdown triggered"
    ;;
  reboot)
    echo "[INFO] Demande de redémarrage (reboot)" >&2
    /sbin/reboot || /usr/sbin/reboot || { echo "ERR: reboot failed" >&2; exit 1; }
    echo "OK: reboot triggered"
    ;;
  *)
    echo "ERR: unknown command '$cmd' (expected: shutdown|reboot)" >&2; exit 1;
    ;;
 esac
