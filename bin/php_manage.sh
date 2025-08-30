#!/usr/bin/env bash
set -euo pipefail

# Usage:
#   php_manage.sh list [--json]
#   php_manage.sh candidates [--json]
#   php_manage.sh install <ver>     # ex: 7.4 | 8.1 | 8.2 | 8.3 | 8.4
#   php_manage.sh remove  <ver>
#   php_manage.sh restart <ver>

cmd="${1:-}"
json="${2:-}"

allowed_re='^[0-9]+\.[0-9]+$'
PKGS_COMMON="cli common mysql xml mbstring curl zip gd intl opcache"

# Must be run as root (we rely on sudo from the panel)
ensure_root() {
  if [[ ${EUID:-$(id -u)} -ne 0 ]]; then
    echo "Cette commande doit être exécutée en root (utiliser sudo)." >&2
    exit 1
  fi
}

ensure_sury_repo() {
  ensure_root
  # Ajoute le dépôt Sury si absent (idempotent)
  if ! apt-cache policy | grep -q "packages.sury.org/php"; then
    apt-get update -y
    apt-get install -y apt-transport-https lsb-release ca-certificates curl gnupg
    curl -fsSL https://packages.sury.org/php/apt.gpg | gpg --dearmor -o /usr/share/keyrings/php.gpg
    echo "deb [signed-by=/usr/share/keyrings/php.gpg] https://packages.sury.org/php $(lsb_release -sc) main" > /etc/apt/sources.list.d/php-sury.list
    apt-get update -y
  fi
}

detect_candidates() {
  # Retourne les versions disponibles via APT (si dépôt configuré), sinon une liste par défaut
  if apt-cache policy | grep -q "packages.sury.org/php"; then
    apt-cache search --names-only '^php[0-9]\.[0-9]-fpm$' 2>/dev/null \
      | awk '{print $1}' \
      | sed -E 's/^php([0-9]+\.[0-9]+)-fpm$/\1/' \
      | awk 'NF' \
      | sort -V -u
  else
    printf "%s\n" 7.4 8.0 8.1 8.2 8.3 8.4
  fi
}

detect_list() {
  # Détecte via sockets ET services
  vers=()
  # sockets: /run/php/phpX.Y-fpm.sock
  while IFS= read -r s; do
    [[ "$s" =~ /run/php/php([0-9]+\.[0-9]+)-fpm\.sock$ ]] || continue
    vers+=( "${BASH_REMATCH[1]}" )
  done < <(ls -1 /run/php/php*-fpm.sock 2>/dev/null || true)

  # services: phpX.Y-fpm
  while IFS= read -r u; do
    v="${u##php}"
    v="${v%-fpm.service}"
    vers+=( "$v" )
  done < <(systemctl list-unit-files --type=service --no-legend 2>/dev/null \
           | awk '/^php[0-9]+\.[0-9]+-fpm\.service/{print $1}')

  # unique + sort
  printf "%s\n" "${vers[@]}" | awk 'NF' | sort -u
}

status_row() {
  v="$1"
  sock="/run/php/php${v}-fpm.sock"
  svc="php${v}-fpm"
  sock_s=$( [[ -S "$sock" ]] && echo "oui" || echo "non" )
  systemctl is-active --quiet "$svc" && svc_s="actif" || svc_s="inactif"
  echo "${v};${sock_s};${svc_s}"
}

cmd_candidates() {
  mapfile -t vers < <(detect_candidates || true)
  if [[ "${json:-}" == "--json" ]]; then
    echo "["
    first=1
    for v in "${vers[@]}"; do
      [[ $first -eq 0 ]] && echo ","
      first=0
      echo "  \"$v\""
    done
    echo "]"
  else
    if [[ ${#vers[@]} -eq 0 ]]; then
      echo "Aucune version candidate détectée."
    else
      printf "%s\n" "${vers[@]}"
    fi
  fi
}

cmd_list() {
  # Si pas de version => peut-être rien d’installé
  mapfile -t vers < <(detect_list || true)

  if [[ "${json:-}" == "--json" ]]; then
    echo "["
    first=1
    for v in "${vers[@]}"; do
      [[ $first -eq 0 ]] && echo ","
      first=0
      sock="/run/php/php${v}-fpm.sock"
      svc="php${v}-fpm"
      sock_ok=$([[ -S "$sock" ]] && echo true || echo false)
      svc_ok=$(systemctl is-active --quiet "$svc" && echo true || echo false)
      echo "  {\"ver\":\"$v\",\"socket\":$sock_ok,\"service\":$svc_ok}"
    done
    echo "]"
  else
    if [[ ${#vers[@]} -eq 0 ]]; then
      echo "Aucune version PHP-FPM détectée."
    else
      for v in "${vers[@]}"; do
        status_row "$v"
      done
    fi
  fi
}

cmd_install() {
  v="$1"
  [[ "$v" =~ $allowed_re ]] || { echo "Version invalide: $v"; exit 2; }
  ensure_root

  # Construire correctement la liste des paquets phpX.Y-<ext>
  pkgs=()
  for p in $PKGS_COMMON; do
    pkgs+=("php${v}-${p}")
  done

  ensure_sury_repo
  apt-get update -y
  apt-get install -y "php${v}" "php${v}-fpm" "${pkgs[@]}"

  systemctl enable --now "php${v}-fpm"
  systemctl is-active --quiet "php${v}-fpm" || { echo "Le service php${v}-fpm ne démarre pas."; exit 3; }

  echo "OK: PHP $v installé et php${v}-fpm démarré."
}

cmd_remove() {
  v="$1"
  [[ "$v" =~ $allowed_re ]] || { echo "Version invalide: $v"; exit 2; }
  ensure_root

  # Construire la liste des paquets à purger
  pkgs=()
  for p in $PKGS_COMMON; do
    pkgs+=("php${v}-${p}")
  done

  systemctl stop "php${v}-fpm" || true
  apt-get purge -y "php${v}" "php${v}-fpm" "${pkgs[@]}"
  apt-get autoremove -y
  echo "OK: PHP $v désinstallé."
}

cmd_restart() {
  v="$1"
  [[ "$v" =~ $allowed_re ]] || { echo "Version invalide: $v"; exit 2; }
  ensure_root
  systemctl restart "php${v}-fpm"
  echo "OK: php${v}-fpm redémarré."
}

case "$cmd" in
  list)        cmd_list ;;
  candidates)  cmd_candidates ;;
  install)     [[ $# -ge 2 ]] || { echo "Usage: install <ver>"; exit 1; }; cmd_install "$2" ;;
  remove)      [[ $# -ge 2 ]] || { echo "Usage: remove <ver>"; exit 1; }; cmd_remove "$2" ;;
  restart)     [[ $# -ge 2 ]] || { echo "Usage: restart <ver>"; exit 1; }; cmd_restart "$2" ;;
  *) echo "Usage: $0 list [--json] | candidates [--json] | install <version> | remove <version> | restart <version>"; exit 1 ;;
esac