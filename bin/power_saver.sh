#!/usr/bin/env bash
# power_saver.sh — Contrôle HDMI / Wi-Fi / Bluetooth (Raspberry Pi / Debian)
# Usage :
#   power_saver.sh status
#   power_saver.sh hdmi 0|1
#   power_saver.sh wifi on|off
#   power_saver.sh bluetooth on|off
#
# Sorties JSON :
#   {"hdmi":0|1,"wifi":"on|off","bluetooth":"on|off","hdmi_map":{ "HDMI-A-1":"on|off", ... }}
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
  # Retourne 1 si au moins UNE sortie HDMI est Enabled, 0 si toutes Off
  read -r GUI_USER GUI_RT GUI_WLD <<<"$(get_wayland_env || true)"
  if [[ -n "$GUI_USER" && -n "$GUI_RT" && -n "$GUI_WLD" ]] && have_cmd wlr-randr; then
    local any=0
    local in_hdmi=0
    while IFS= read -r line; do
      # Début de bloc pour un connecteur HDMI (token en début de ligne)
      if [[ "$line" =~ ^(HDMI-[A-Za-z0-9:-]+)\b ]]; then
        in_hdmi=1
        continue
      fi
      # Si on est dans un bloc HDMI, regarder Enabled: yes
      if (( in_hdmi )); then
        if [[ "$line" =~ Enabled:[[:space:]]*yes ]]; then any=1; fi
        # Fin de bloc si on arrive sur une nouvelle sortie (non fiable sans lookahead) →
        # on se repose sur le fait qu'on cherche juste "any".
      fi
      # Si on rencontre une ligne d'entête d'une autre sortie non HDMI, sortir du bloc
      if [[ "$line" =~ ^([A-Za-z0-9-]+)\b ]] && [[ ! "$line" =~ ^HDMI- ]]; then in_hdmi=0; fi
    done < <(sudo -u "$GUI_USER" env XDG_RUNTIME_DIR="$GUI_RT" WAYLAND_DISPLAY="$GUI_WLD" wlr-randr 2>/dev/null)
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
  if [[ -z "$GUI_USER" || -z "$GUI_RT" || -z "$GUI_WLD" ]] || ! have_cmd wlr-randr; then
    echo "{}"; return
  fi
  # Récupérer la liste des sorties HDMI (token $1 en début de ligne)
  mapfile -t outs < <(sudo -u "$GUI_USER" env XDG_RUNTIME_DIR="$GUI_RT" WAYLAND_DISPLAY="$GUI_WLD" \
    wlr-randr 2>/dev/null | awk '$1 ~ /^HDMI-/ {print $1}' | sort -u)
  local json="{" wrote=0
  for o in "${outs[@]}"; do
    [[ -n "$o" ]] || continue
    # Lire l'état Enabled: yes|no pour ce connecteur
    if sudo -u "$GUI_USER" env XDG_RUNTIME_DIR="$GUI_RT" WAYLAND_DISPLAY="$GUI_WLD" \
         wlr-randr 2>/dev/null | awk -v O="$o" '$1==O{hit=1} hit && $1=="Enabled:"{print $2; exit}' | grep -qx yes; then
      st=on
    else
      st=off
    fi
    [[ $wrote -eq 1 ]] && json+=","; wrote=1
    json+="\"$o\":\"$st\""
  done
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

                # Trouver une session Wayland active + vérifier wlr-randr
                read -r GUI_USER GUI_RT GUI_WLD <<<"$(get_wayland_env || true)"
                if [[ -z "$GUI_USER" || -z "$GUI_RT" || -z "$GUI_WLD" ]] || ! have_cmd wlr-randr; then
                  emit_status_json; exit 0
                fi

                # Lister sorties HDMI + Enabled + preferred + premier mode disponible + largeur courante
                # On utilisera la somme des largeurs des sorties déjà actives pour positionner la cible à droite
                # afin d'éviter un chevauchement (qui peut conduire à un écran noir/éteint selon le WM).
                # Exemple de ligne mode: "  1920x1080 px, 60.000000 Hz (preferred, current)"
                mapfile -t lines < <(sudo -u "$GUI_USER" env XDG_RUNTIME_DIR="$GUI_RT" WAYLAND_DISPLAY="$GUI_WLD" wlr-randr 2>/dev/null)
                declare -A enabled preferred firstmode curwidth
                curr_out=""
                for ln in "${lines[@]}"; do
                  if [[ "$ln" =~ ^(HDMI-[A-Za-z0-9:-]+)\b ]]; then
                    curr_out="${BASH_REMATCH[1]}"
                    enabled["$curr_out"]="unknown"
                    preferred["$curr_out"]=""
                    firstmode["$curr_out"]=""
                    curwidth["$curr_out"]=0
                    continue
                  fi
                  # Enabled: yes|no
                  if [[ -n "$curr_out" && "$ln" =~ Enabled:[[:space:]]*(yes|no) ]]; then
                    [[ "${BASH_REMATCH[1]}" == "yes" ]] && enabled["$curr_out"]="on" || enabled["$curr_out"]="off"
                    continue
                  fi
                  # Modes: ex "  3440x1440 px, 59.973000 Hz (preferred, current)"
                  if [[ -n "$curr_out" && "$ln" =~ ^[[:space:]]+([0-9]+)x([0-9]+)[[:space:]]+px,[[:space:]]+([0-9.]+)[[:space:]]+Hz ]]; then
                    mode_id="${BASH_REMATCH[1]}x${BASH_REMATCH[2]}@${BASH_REMATCH[3]}"
                    [[ -z "${firstmode[$curr_out]}" ]] && firstmode["$curr_out"]="$mode_id"
                    if [[ "$ln" =~ \(preferred ]]; then
                      preferred["$curr_out"]="$mode_id"
                    fi
                    # si (current) est mentionné, retenir la largeur courante
                    if [[ "$ln" =~ \(.*current.*\) ]]; then
                      curwidth["$curr_out"]="${BASH_REMATCH[1]}"
                    fi
                  fi
                done

                # Choix des sorties ciblées
                outputs=()
                if [[ -n "$out_req" ]]; then
                  if [[ -n "${enabled[$out_req]:-}" ]]; then
                    outputs+=("$out_req")
                  else
                    # Sortie inconnue -> ne rien faire; l'UI masquera le bouton via status.
                    emit_status_json; exit 0
                  fi
                else
                  for k in "${!enabled[@]}"; do outputs+=("$k"); done
                  IFS=$'\n' outputs=($(sort <<<"${outputs[*]}")); unset IFS
                fi

                # Helper: relire "Enabled: yes" pour une sortie donnée
                is_enabled() {
                  local out="$1"
                  sudo -u "$GUI_USER" env XDG_RUNTIME_DIR="$GUI_RT" WAYLAND_DISPLAY="$GUI_WLD" \
                    wlr-randr 2>/dev/null | awk -v O="$out" '
                      $1==O{hit=1}
                      hit && $1=="Enabled:"{print $2; exit}
                    ' | grep -qx "yes"
                }

                # Attendre qu'une sortie atteigne l'état Enabled=yes avec timeout
                wait_enabled() {
                  local out="$1" timeout_ms="${2:-1500}" interval_ms=150
                  local elapsed=0
                  while (( elapsed <= timeout_ms )); do
                    if is_enabled "$out"; then return 0; fi
                    sleep 0.15
                    (( elapsed += interval_ms ))
                  done
                  return 1
                }

                # Anti-flicker + séquence ON robuste
                # Calculer position de base (x) en additionnant les largeurs des sorties déjà actives
                base_x=0
                if [[ -n "$out_req" ]]; then
                  for k in "${!enabled[@]}"; do
                    if [[ "$k" != "$out_req" && "${enabled[$k]}" == "on" ]]; then
                      wx=${curwidth[$k]:-0}
                      [[ -n "$wx" ]] || wx=0
                      (( base_x += wx ))
                    fi
                  done
                fi

                for o in "${outputs[@]}"; do
                  want="$([[ "$act" == "1" ]] && echo on || echo off)"
                  curr="${enabled[$o]:-unknown}"
                  [[ "$curr" == "$want" ]] && continue

                  if [[ "$act" == "0" ]]; then
                    sudo -u "$GUI_USER" env XDG_RUNTIME_DIR="$GUI_RT" WAYLAND_DISPLAY="$GUI_WLD" \
                      wlr-randr --output "$o" --off >/dev/null 2>&1 || true
                    continue
                  fi

                  # ON: essais progressifs
                  pref="${preferred[$o]:-}"
                  fm="${firstmode[$o]:-}"

                  # 1) --on + attente
                  sudo -u "$GUI_USER" env XDG_RUNTIME_DIR="$GUI_RT" WAYLAND_DISPLAY="$GUI_WLD" \
                    wlr-randr --output "$o" --on >/dev/null 2>&1 || true
                  if wait_enabled "$o" 1200; then continue; fi

                  # 2) --on --mode <preferred>
                  if [[ -n "$pref" ]]; then
                    sudo -u "$GUI_USER" env XDG_RUNTIME_DIR="$GUI_RT" WAYLAND_DISPLAY="$GUI_WLD" \
                      wlr-randr --output "$o" --on --mode "$pref" >/dev/null 2>&1 || true
                    if wait_enabled "$o" 1500; then continue; fi

                    # 3) --on --pos base_x,0 --mode <preferred> (éviter le chevauchement)
                    sudo -u "$GUI_USER" env XDG_RUNTIME_DIR="$GUI_RT" WAYLAND_DISPLAY="$GUI_WLD" \
                      wlr-randr --output "$o" --on --pos ${base_x},0 --mode "$pref" >/dev/null 2>&1 || true
                    if wait_enabled "$o" 1500; then continue; fi
                  fi

                  # 4) Fallback: premier mode vu
                  if [[ -n "$fm" ]]; then
                    sudo -u "$GUI_USER" env XDG_RUNTIME_DIR="$GUI_RT" WAYLAND_DISPLAY="$GUI_WLD" \
                      wlr-randr --output "$o" --on --mode "$fm" >/dev/null 2>&1 || true
                    if wait_enabled "$o" 1500; then continue; fi
                  fi

                  # 5) Dernier essai: cycle off->on avec position calculée et scale 1
                  sudo -u "$GUI_USER" env XDG_RUNTIME_DIR="$GUI_RT" WAYLAND_DISPLAY="$GUI_WLD" \
                    wlr-randr --output "$o" --off >/dev/null 2>&1 || true
                  sleep 0.2
                  sudo -u "$GUI_USER" env XDG_RUNTIME_DIR="$GUI_RT" WAYLAND_DISPLAY="$GUI_WLD" \
                    wlr-randr --output "$o" --on --pos ${base_x},0 --scale 1 ${pref:+--mode "$pref"} >/dev/null 2>&1 || true
                  wait_enabled "$o" 1800 || true
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