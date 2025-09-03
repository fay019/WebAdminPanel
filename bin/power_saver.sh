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
          [[ -n "$arg" ]] || { emit_status_json; exit 0; }
          [[ "$arg" == "0" || "$arg" == "1" ]] || { emit_status_json; exit 0; }

          # Détecter l'utilisateur de la session graphique seat0 + son type (x11/wayland)
          get_gui_env() {
            local sid user type uid rt wld disp xauth
            sid="$(loginctl list-sessions --no-legend 2>/dev/null | awk '$3=="seat0"{print $1; exit}')" || true
            [[ -n "$sid" ]] || { echo ""; return; }
            user="$(loginctl show-session "$sid" -p Name --value 2>/dev/null)" || true
            type="$(loginctl show-session "$sid" -p Type --value 2>/dev/null)" || true
            [[ -n "$user" ]] || { echo ""; return; }
            uid="$(id -u "$user" 2>/dev/null)"
            rt="/run/user/$uid"
            if [[ "$type" == "x11" ]]; then
              disp=":0"
              xauth="/home/$user/.Xauthority"
              echo "$user x11 $disp $rt $xauth"
            else
              # Wayland: chercher le socket wayland-*
              wld="$(ls "$rt"/wayland-* 2>/dev/null | head -n1 | xargs -n1 basename)"
              [[ -n "$wld" ]] || wld="wayland-0"
              echo "$user wayland $wld $rt -"
            fi
          }

          read -r GUI_USER GUI_TYPE GUI_DISP GUI_RT GUI_XAUTH <<<"$(get_gui_env || true)"

          # Wayland (wlr-randr) en priorité si dispo
          if [[ "$GUI_TYPE" == "wayland" ]] && command -v wlr-randr >/dev/null 2>&1; then
            out="$(sudo -u "$GUI_USER" env XDG_RUNTIME_DIR="$GUI_RT" WAYLAND_DISPLAY="$GUI_DISP" wlr-randr | awk '/ connected / && $1 ~ /HDMI-A-/ {print $1; exit}')" || out=""
            if [[ -n "$out" ]]; then
              if [[ "$arg" == "1" ]]; then
                sudo -u "$GUI_USER" env XDG_RUNTIME_DIR="$GUI_RT" WAYLAND_DISPLAY="$GUI_DISP" wlr-randr --output "$out" --on >/dev/null 2>&1 || true
              else
                sudo -u "$GUI_USER" env XDG_RUNTIME_DIR="$GUI_RT" WAYLAND_DISPLAY="$GUI_DISP" wlr-randr --output "$out" --off >/dev/null 2>&1 || true
              fi
              emit_status_json; exit 0
            fi
          fi

          # X11 (xrandr) si dispo
          if command -v xrandr >/dev/null 2>&1 && [[ "$GUI_TYPE" == "x11" ]]; then
            if [[ -z "${GUI_DISP:-}" ]]; then GUI_DISP=":0"; fi
            if [[ -z "${GUI_XAUTH:-}" ]]; then GUI_XAUTH="/home/${GUI_USER}/.Xauthority"; fi
            out="$(sudo -u "$GUI_USER" env DISPLAY="$GUI_DISP" XAUTHORITY="$GUI_XAUTH" xrandr --query | awk '/ connected / && $1 ~ /HDMI-/ {print $1; exit}')" || out=""
            if [[ -n "$out" ]]; then
              if [[ "$arg" == "1" ]]; then
                sudo -u "$GUI_USER" env DISPLAY="$GUI_DISP" XAUTHORITY="$GUI_XAUTH" xrandr --output "$out" --auto >/dev/null 2>&1 || true
              else
                sudo -u "$GUI_USER" env DISPLAY="$GUI_DISP" XAUTHORITY="$GUI_XAUTH" xrandr --output "$out" --off  >/dev/null 2>&1 || true
              fi
              emit_status_json; exit 0
            fi
          fi

          # Console pure : blank/unblank du framebuffer (fallback)
          if [[ -w /sys/class/graphics/fbcon/blank ]]; then
            if [[ "$arg" == "1" ]]; then echo 0 > /sys/class/graphics/fbcon/blank || true
            else                          echo 1 > /sys/class/graphics/fbcon/blank || true
            fi
            emit_status_json; exit 0
          fi

          # Ancien firmware (non Pi5) : dernière tentative vcgencmd (ne plante pas)
          if command -v vcgencmd >/dev/null 2>&1; then
            vcgencmd display_power "$arg"      >/dev/null 2>&1 \
            || vcgencmd display_power "$arg" 0 >/dev/null 2>&1 \
            || vcgencmd display_power "$arg" 1 >/dev/null 2>&1 \
            || true
            emit_status_json; exit 0
          fi

          # Rien d’applicable → renvoyer un état JSON (sans erreur)
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