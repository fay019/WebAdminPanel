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
  # KMS/DRM (Pi 5) : on lit le statut de connexion (pas l'alimentation)
  local p
  for p in /sys/class/drm/*HDMI-A-*/status; do
    [[ -f "$p" ]] || continue
    local s
    s="$(cat "$p" 2>/dev/null || true)"
    case "$s" in
      connected)   echo -n "1"; return ;;
      disconnected) echo -n "0"; return ;;
    esac
  done
  # Si pas de DRM, tenter encore vcgencmd (anciens modèles)
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

        # Priorité: Wayland (wlr-randr)
        if command -v wlr-randr >/dev/null 2>&1; then
          # Choix d'une sortie HDMI-A-* connectée
          out="$(wlr-randr | awk '/connected/ && $1 ~ /HDMI-A-/ {print $1; exit}')" || out=""
          if [[ -n "$out" ]]; then
            if [[ "$arg" == "1" ]]; then
              wlr-randr --output "$out" --on >/dev/null 2>&1 || true
            else
              wlr-randr --output "$out" --off >/dev/null 2>&1 || true
            fi
            emit_status_json; exit 0
          fi
        fi

        # X11 (xrandr) si DISPLAY présent
        if command -v xrandr >/dev/null 2>&1 && [[ -n "${DISPLAY:-}" ]]; then
          out="$(xrandr --query | awk '/ connected / && $1 ~ /HDMI-/ {print $1; exit}')" || out=""
          if [[ -n "$out" ]]; then
            if [[ "$arg" == "1" ]]; then
              xrandr --output "$out" --auto >/dev/null 2>&1 || true
            else
              xrandr --output "$out" --off  >/dev/null 2>&1 || true
            fi
            emit_status_json; exit 0
          fi
        fi

        # Console seule : fallback blank/unblank (économie légère)
        if [[ -w /sys/class/graphics/fbcon/blank ]]; then
          if [[ "$arg" == "1" ]]; then
            echo 0 > /sys/class/graphics/fbcon/blank || true
          else
            echo 1 > /sys/class/graphics/fbcon/blank || true
          fi
          emit_status_json; exit 0
        fi

        # Ancien firmware (non Pi5) : tentative vcgencmd (ne plante jamais)
        if command -v vcgencmd >/dev/null 2>&1; then
          vcgencmd display_power "$arg"      >/dev/null 2>&1 \
          || vcgencmd display_power "$arg" 0 >/dev/null 2>&1 \
          || vcgencmd display_power "$arg" 1 >/dev/null 2>&1 \
          || true
          emit_status_json; exit 0
        fi

        # Si aucune méthode applicable → renvoyer état sans erreur
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