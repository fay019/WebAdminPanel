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

get_wayland_env() {
  while read -r sid _; do
    local user type state uid rt wld
    user="$(loginctl show-session "$sid" -p Name  --value 2>/dev/null)"  || true
    type="$(loginctl show-session "$sid" -p Type  --value 2>/dev/null)"  || true
    state="$(loginctl show-session "$sid" -p State --value 2>/dev/null)" || true
    [[ "$type" == "wayland" && "$state" == "active" ]] || continue
    uid="$(id -u "$user" 2>/dev/null)" || continue
    rt="/run/user/$uid"
    wld="$(ls "$rt"/wayland-* 2>/dev/null | head -n1 | xargs -n1 basename)"
    [[ -n "$wld" ]] || continue
    echo "$user $rt $wld"; return
  done < <(loginctl list-sessions --no-legend 2>/dev/null)
  echo ""
}

hdmi_status() {
  # Retourne 1 si au moins UNE sortie HDMI est Enabled, 0 si toutes Off, null si inconnu
  read -r GUI_USER GUI_RT GUI_WLD <<<"$(get_wayland_env || true)"
  if [[ -n "$GUI_USER" && -n "$GUI_RT" && -n "$GUI_WLD" && $(command -v wlr-randr) ]]; then
    local any=0
    while IFS= read -r line; do
      # Exemple: "HDMI-A-2 ..." puis plus bas "  Enabled: yes"
      if [[ "$line" =~ ^HDMI-A-[0-9] ]]; then curr="$line"
      elif [[ "$line" =~ ^[[:space:]]+Enabled:\ yes$ ]]; then any=1; fi
    done < <(sudo -u "$GUI_USER" env XDG_RUNTIME_DIR="$GUI_RT" WAYLAND_DISPLAY="$GUI_WLD" wlr-randr)
    echo -n "$any"; return
  fi
  # Fallback DRM câble connecté (moins précis)
  local p s any=0
  for p in /sys/class/drm/*HDMI-A-*/status; do
    [[ -f "$p" ]] || continue
    s="$(cat "$p" 2>/dev/null || true)"
    [[ "$s" == "connected" ]] && { any=1; break; }
  done
  [[ $any -eq 1 ]] && echo -n "1" || echo -n "0"
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

build_hdmi_map() {
  read -r GUI_USER GUI_RT GUI_WLD <<<"$(get_wayland_env || true)"
  if [[ -z "$GUI_USER" || -z "$GUI_RT" || -z "$GUI_WLD" || ! $(command -v wlr-randr) ]]; then
    echo "{}"; return
  fi
  local curr key val json="{"
  while IFS= read -r ln; do
    if [[ "$ln" =~ ^(HDMI-A-[0-9]) ]]; then
      [[ "$json" != "{" ]] && json+=","
      curr="${BASH_REMATCH[1]}"
      key="\"$curr\""
      val="\"unknown\""
    elif [[ -n "$curr" && "$ln" =~ ^[[:space:]]+Enabled:\ (yes|no)$ ]]; then
      val="$([[ "${BASH_REMATCH[1]}" == "yes" ]] && echo '"on"' || echo '"off"')"
      json+="$key:$val"
      curr=""
    fi
  done < <(sudo -u "$GUI_USER" env XDG_RUNTIME_DIR="$GUI_RT" WAYLAND_DISPLAY="$GUI_WLD" wlr-randr)
  json+="}"
  echo "$json"
}

emit_status_json() {
  local h w b m
  h="$(hdmi_status)"
  w="$(wifi_status)"
  b="$(bt_status)"
  m="$(build_hdmi_map)"
  printf '{"hdmi":%s,"wifi":"%s","bluetooth":"%s","hdmi_map":%s}\n' "${h}" "${w}" "${b}" "${m}"
}

case "$cmd" in
  status|"")
    emit_status_json
    ;;
            hdmi)
              # Usage: power_saver.sh hdmi 0|1 [OUTPUT_NAME]
              act="${arg:-}"; out_req="${3:-}"
              [[ "$act" == "0" || "$act" == "1" ]] || { emit_status_json; exit 0; }

              read -r GUI_USER GUI_RT GUI_WLD <<<"$(get_wayland_env || true)"
              if [[ -z "$GUI_USER" || -z "$GUI_RT" || -z "$GUI_WLD" || ! $(command -v wlr-randr) ]]; then
                emit_status_json; exit 0
              fi

              # Lister toutes les sorties HDMI + Enabled yes/no + preferred mode
                mapfile -t lines < <(sudo -u "$GUI_USER" env XDG_RUNTIME_DIR="$GUI_RT" WAYLAND_DISPLAY="$GUI_WLD" wlr-randr)
                declare -A enabled preferred
                curr_out=""
                for ln in "${lines[@]}"; do
                  if [[ "$ln" =~ ^(HDMI-A-[0-9]) ]]; then
                    curr_out="${BASH_REMATCH[1]}"
                    enabled["$curr_out"]="unknown"
                    preferred["$curr_out"]=""
                  elif [[ -n "$curr_out" && "$ln" =~ ^[[:space:]]+Enabled:\ (yes|no)$ ]]; then
                    [[ "${BASH_REMATCH[1]}" == "yes" ]] && enabled["$curr_out"]="on" || enabled["$curr_out"]="off"
                  elif [[ -n "$curr_out" && "$ln" =~ ^[[:space:]]+([0-9]+x[0-9]+)[[:space:]]+px,\ ([0-9.]+)[[:space:]]+Hz\ \(preferred ]]; then
                    # Ex: "  3440x1440 px, 59.973000 Hz (preferred, current)"
                    preferred["$curr_out"]="${BASH_REMATCH[1]}@${BASH_REMATCH[2]}"
                  fi
                done

              # Choix des sorties ciblées
              outputs=()
              if [[ -n "$out_req" ]]; then
                if [[ -n "${enabled[$out_req]:-}" ]]; then
                  outputs+=("$out_req")
                else
                  # sortie inconnue -> ne rien faire mais renvoyer l'état (le front affichera un message)
                  emit_status_json; exit 0
                fi
              else
                # Toutes les HDMI connues
                for k in "${!enabled[@]}"; do outputs+=("$k"); done
                IFS=$'\n' outputs=($(sort <<<"${outputs[*]}")); unset IFS
              fi

              # Anti-flicker: ne pas relancer si l'état demandé = état courant
              for o in "${outputs[@]}"; do
                  want="$([[ "$act" == "1" ]] && echo on || echo off)"
                  curr="${enabled[$o]:-unknown}"
                  [[ "$curr" == "$want" ]] && continue  # anti-flicker

                  if [[ "$act" == "1" ]]; then
                    pref="${preferred[$o]:-}"
                    if [[ -n "$pref" ]]; then
                      sudo -u "$GUI_USER" env XDG_RUNTIME_DIR="$GUI_RT" WAYLAND_DISPLAY="$GUI_WLD" \
                        wlr-randr --output "$o" --on --mode "$pref" >/dev/null 2>&1 || true
                    else
                      sudo -u "$GUI_USER" env XDG_RUNTIME_DIR="$GUI_RT" WAYLAND_DISPLAY="$GUI_WLD" \
                        wlr-randr --output "$o" --on >/dev/null 2>&1 || true
                    fi
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