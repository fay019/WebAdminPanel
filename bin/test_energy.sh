#!/usr/bin/env bash
# Simple console tester for energy status and HDMI toggling under Wayland
# Usage:
#   bin/test_energy.sh [OUTPUT]
# If OUTPUT is provided (e.g., HDMI-A-2), it will try to turn it on then off.

set -euo pipefail
PANEL_DIR="${PANEL_DIR:-}"
if [[ -z "$PANEL_DIR" ]]; then
  THIS_DIR="$(cd "$(dirname "$0")" && pwd)"
  PANEL_DIR="$(cd "$THIS_DIR/.." && pwd)"
fi
SCRIPT="$PANEL_DIR/bin/power_saver.sh"

have_cmd() { command -v "$1" >/dev/null 2>&1; }

hr() { printf '\n%s\n' '------------------------------------------------------------'; }

printf 'Energy console test — %s\n' "$(date -Is)"
echo "Resolved PANEL_DIR=$PANEL_DIR"
echo "Using SCRIPT=$SCRIPT"
if [[ ! -x "$SCRIPT" ]]; then echo "ERROR: script not found or not executable: $SCRIPT"; fi

hr; echo "1) Wayland session detection:"; hr
if have_cmd loginctl; then
  loginctl list-sessions --no-legend || true
  echo
  echo "Probe active Wayland session:";
  while read -r sid _; do
    user="$(loginctl show-session "$sid" -p Name  --value 2>/dev/null || true)"
    type="$(loginctl show-session "$sid" -p Type  --value 2>/dev/null || true)"
    state="$(loginctl show-session "$sid" -p State --value 2>/dev/null || true)"
    [[ "$type" == "wayland" && "$state" == "active" ]] || continue
    uid=$(id -u "$user" 2>/dev/null || true)
    rt="/run/user/$uid"
    wld=$(ls "$rt"/wayland-* 2>/dev/null | head -n1 | xargs -n1 basename || true)
    echo "Active: user=$user uid=$uid rt=$rt wld=$wld"
  done < <(loginctl list-sessions --no-legend 2>/dev/null)
else
  echo "loginctl not found"
fi

hr; echo "2) wlr-randr presence:"; hr
if have_cmd wlr-randr; then
  which wlr-randr || true
else
  echo "wlr-randr not in PATH"
fi

hr; echo "3) Raw power_saver.sh status output:"; hr
set +e
OUT=$(sudo -n "$SCRIPT" status 2>&1)
RC=$?
set -e
printf 'RC=%s\n%s\n' "$RC" "$OUT"

hr; echo "4) JSON parse check (php -r json_decode):"; hr
php -r "\$s=file_get_contents('php://stdin'); json_decode(\$s); echo json_last_error()===JSON_ERROR_NONE?'OK':'ERR';" <<<"$OUT" || true

if [[ -n "${1:-}" ]]; then
  OUTNAME="$1"
  hr; echo "5) Toggle ON $OUTNAME:"; hr
  set +e
  ONOUT=$(sudo -n "$SCRIPT" hdmi 1 "$OUTNAME" 2>&1)
  RCON=$?
  set -e
  printf 'RC=%s\n%s\n' "$RCON" "$ONOUT"
  sleep 1
  hr; echo "6) Status after ON:"; hr
  sudo -n "$SCRIPT" status 2>&1 || true
  sleep 1
  hr; echo "7) Toggle OFF $OUTNAME:"; hr
  set +e
  OFFOUT=$(sudo -n "$SCRIPT" hdmi 0 "$OUTNAME" 2>&1)
  RCOFF=$?
  set -e
  printf 'RC=%s\n%s\n' "$RCOFF" "$OFFOUT"
  hr; echo "8) Status after OFF:"; hr
  sudo -n "$SCRIPT" status 2>&1 || true
fi

hr; echo "Done."; hr
