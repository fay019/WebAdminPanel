#!/usr/bin/env bash
# power_saver.sh — Contrôle HDMI / Wi-Fi / Bluetooth (Raspberry Pi / Debian)
# Usage :
#   power_saver.sh status
#   power_saver.sh hdmi 0|1
#   power_saver.sh wifi on|off
#   power_saver.sh bluetooth on|off
#
# Sorties JSON :
#   {"hdmi":0|1,"wifi":"on|off","bluetooth":"on|off"}
#
# Remarques :
# - HDMI : nécessite vcgencmd (firmware Raspberry Pi) et KMS/VC4 actif
# - Wi-Fi/Bluetooth : utilise rfkill (soft block)
# - Conseil : exécuter via sudoers NOPASSWD depuis le Dashboard web

set -euo pipefail

cmd="${1:-}"
arg="${2:-}"

die() { echo "ERR: $*" >&2; exit 1; }

have_cmd() { command -v "$1" >/dev/null 2>&1; }

hdmi_status() {
  # KMS/DRM (Pi 5) : on lit le statut de connexion (connected/disconnected)
  local p
  for p in /sys/class/drm/*HDMI-A-*/status; do
    [[ -f "$p" ]] || continue
    local s
    s="$(cat "$p" 2>/dev/null || true)"
    case "$s" in
      connected)    echo -n "1"; return ;;
      disconnected) echo -n "0"; return ;;
    esac
  done
  # Ancien firmware (Pi ≤4) : fallback vcgencmd si dispo
  if command -v vcgencmd >/dev/null 2>&1; then
    local v
    v="$(vcgencmd display_power 2>/dev/null | awk -F= '/display_power/ {print $2}')" || true
    case "$v" in
      1) echo -n "1"; return ;;
      0) echo -n "0"; return ;;
    esac
  fi
  echo -n "null"
}

wifi_status() {
  if ! have_cmd rfkill; then echo -n "unknown"; return; fi
  # Lire Soft blocked: yes/no
  local s
  s="$(rfkill list wifi 2>/dev/null | awk '/Soft blocked/ {print $3}' | tail -n1)" || true
  [[ "$s" == "no" ]] && echo -n "on" || echo -n "off"
}

bt_status() {
  if ! have_cmd rfkill; then echo -n "unknown"; return; fi
  local s
  s="$(rfkill list bluetooth 2>/dev/null | awk '/Soft blocked/ {print $3}' | tail -n1)" || true
  [[ "$s" == "no" ]] && echo -n "on" || echo -n "off"
}

emit_status_json() {
  local h w b
  h="$(hdmi_status)"
  w="$(wifi_status)"
  b="$(bt_status)"
  # h peut valoir null si vcgencmd absent
  printf '{"hdmi":%s,"wifi":"%s","bluetooth":"%s"}\n' "${h}" "${w}" "${b}"
}

case "$cmd" in
  status|"")
    emit_status_json
    ;;
          hdmi)
              # Usage: power_saver.sh hdmi 0|1 [OUTPUT_NAME]
              act="${arg:-}"; out_req="${3:-}"
              [[ "$act" == "0" || "$act" == "1" ]] || { emit_status_json; exit 0; }

              # Trouver une session Wayland active (seat0)
              get_wayland_env() {
                while read -r sid _; do
                  user="$(loginctl show-session "$sid" -p Name  --value 2>/dev/null)"  || true
                  type="$(loginctl show-session "$sid" -p Type  --value 2>/dev/null)"  || true
                  state="$(loginctl show-session "$sid" -p State --value 2>/dev/null)" || true
                  [[ "$type" == "wayland" && "$state" == "active" ]] || continue
                  uid="$(id -u "$user" 2>/dev/null)" || continue
                  rt="/run/user/$uid"
                  wld="$(ls "$rt"/wayland-* 2>/dev/null | head -n1 | xargs -n1 basename)"
                  [[ -n "$wld" ]] || continue
                  echo "$user $rt $wld"
                  return
                done < <(loginctl list-sessions --no-legend 2>/dev/null)
                echo ""
              }

              read -r GUI_USER GUI_RT GUI_WLD <<<"$(get_wayland_env || true)"
              if [[ -z "$GUI_USER" || -z "$GUI_RT" || -z "$GUI_WLD" ]]; then
                emit_status_json; exit 0
              fi

              # Vérifier wlr-randr
              if ! command -v wlr-randr >/dev/null 2>&1; then
                emit_status_json; exit 0
              fi

              # Si pas de sortie précisée → prendre toutes les HDMI connectées
              outputs=()
              if [[ -n "$out_req" ]]; then
                outputs+=("$out_req")
              else
                while IFS= read -r o; do outputs+=("$o"); done < <(
                  sudo -u "$GUI_USER" env XDG_RUNTIME_DIR="$GUI_RT" WAYLAND_DISPLAY="$GUI_WLD" \
                    wlr-randr | awk '/ connected/ && $1 ~ /HDMI-A-/ {print $1}'
                )
              fi

              # Appliquer ON/OFF
              for o in "${outputs[@]}"; do
                if [[ "$act" == "1" ]]; then
                  sudo -u "$GUI_USER" env XDG_RUNTIME_DIR="$GUI_RT" WAYLAND_DISPLAY="$GUI_WLD" \
                    wlr-randr --output "$o" --on  >/dev/null 2>&1 || true
                else
                  sudo -u "$GUI_USER" env XDG_RUNTIME_DIR="$GUI_RT" WAYLAND_DISPLAY="$GUI_WLD" \
                    wlr-randr --output "$o" --off >/dev/null 2>&1 || true
                fi
              done

              emit_status_json
              ;;

  wifi)
    [[ -n "$arg" ]] || die "missing arg (on|off)"
    [[ "$arg" == "on" || "$arg" == "off" ]] || die "invalid arg '$arg' (use on|off)"
    have_cmd rfkill || die "rfkill not found"
    if [[ "$arg" == "on" ]];  then rfkill unblock wifi || die "rfkill unblock wifi failed"; fi
    if [[ "$arg" == "off" ]]; then rfkill block   wifi || die "rfkill block wifi failed";   fi
    emit_status_json
    ;;

  bluetooth|bt)
    [[ -n "$arg" ]] || die "missing arg (on|off)"
    [[ "$arg" == "on" || "$arg" == "off" ]] || die "invalid arg '$arg' (use on|off)"
    have_cmd rfkill || die "rfkill not found"
    if [[ "$arg" == "on" ]];  then rfkill unblock bluetooth || die "rfkill unblock bt failed"; fi
    if [[ "$arg" == "off" ]]; then rfkill block   bluetooth || die "rfkill block bt failed";   fi
    emit_status_json
    ;;

  *)
    die "unknown command '$cmd' (use: status|hdmi 0|1|wifi on|off|bluetooth on|off)"
    ;;
esac