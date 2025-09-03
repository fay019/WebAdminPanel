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
  if ! have_cmd vcgencmd; then echo -n "null"; return; fi
  local v
  # Essai sans index
  v="$(vcgencmd display_power 2>/dev/null | awk -F= '/display_power/ {print $2}')" || true
  # Si vide, tenter display 0 puis 1
  if [[ -z "$v" ]]; then
    v="$(vcgencmd display_power 2>/dev/null 0 | awk -F= '/display_power/ {print $2}')" || true
  fi
  if [[ -z "$v" ]]; then
    v="$(vcgencmd display_power 2>/dev/null 1 | awk -F= '/display_power/ {print $2}')" || true
  fi
  case "$v" in
    1) echo -n "1" ;;
    0) echo -n "0" ;;
    *) echo -n "null" ;;
  esac
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

      # Si vcgencmd absent -> on renvoie juste l'état (hdmi=null)
      if ! have_cmd vcgencmd; then
        emit_status_json
        exit 0
      fi

      # 1er essai : syntaxe classique
      if ! vcgencmd display_power "$arg" >/dev/null 2>&1; then
        # Pi 4/5/KMS : certaines stacks exigent un index d'écran (0 ou 1)
        vcgencmd display_power "$arg" 0 >/dev/null 2>&1 \
          || vcgencmd display_power "$arg" 1 >/dev/null 2>&1 \
          || true
        # Quoi qu'il arrive on ne "die" pas : on renverra l'état courant
      fi

      # Toujours renvoyer un JSON d'état (même si l'action a été ignorée)
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