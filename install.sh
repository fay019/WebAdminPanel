#!/usr/bin/env bash
set -euo pipefail

# ─────────────────────────────────────────────────────────────
# Mini Web Panel — install.sh (interactif & idempotent)
# - Choix dossier d'install (PANEL_DIR)
# - Choix PHP-FPM (auto / 8.3 / 8.2)
# - Choix nom de vhost Nginx (avec vérif d'existence)
# - DB SQLite & droits
# - Déploiement sudoers
# - Création vhost + test + reload
# Flags:
#   --panel-dir=/var/www/adminpanel
#   --php=8.3
#   --vhost-name=awp.local.conf
#   --server-names="awp.local adminpanel.local"
#   --force (écrase vhost existant)
# ─────────────────────────────────────────────────────────────

# ========== Defaults ==========
PANEL_DIR_DEFAULT="/var/www/adminpanel"
PHP_DEFAULT=""
VHOST_NAME_DEFAULT="adminpanel.conf"
SERVER_NAMES_DEFAULT="adminpanel.local"
FORCE_OVERWRITE=0

# ========== Parse flags simple ==========
for arg in "$@"; do
  case "$arg" in
    --panel-dir=*) PANEL_DIR_DEFAULT="${arg#*=}";;
    --php=*)       PHP_DEFAULT="${arg#*=}";;
    --vhost-name=*) VHOST_NAME_DEFAULT="${arg#*=}";;
    --server-names=*) SERVER_NAMES_DEFAULT="${arg#*=}";;
    --force)       FORCE_OVERWRITE=1;;
    *) echo "Unknown arg: $arg"; exit 1;;
  esac
done

# ========== Helpers ==========
prompt() {
  # $1=question, $2=default
  local q="$1"; local def="${2-}"
  local ans
  if [[ -n "${def}" ]]; then
    read -r -p "$q [$def]: " ans || true
    echo "${ans:-$def}"
  else
    read -r -p "$q: " ans || true
    echo "${ans}"
  fi
}

detect_php_version() {
  # Try to detect from /run/php sockets
  local sock
  sock=$(ls /run/php/php*-fpm.sock 2>/dev/null | head -n1 || true)
  if [[ -n "$sock" ]]; then
    echo "$sock" | sed -E 's#^/run/php/php([0-9]+\.[0-9]+)-fpm\.sock$#\1#'
    return
  fi
  # Fallback: check installed versions
  for v in 8.3 8.2 8.1; do
    if command -v "php$v" >/dev/null 2>&1 || dpkg -l "php$v-fpm" >/dev/null 2>&1; then
      echo "$v"; return
    fi
  done
  echo ""
}

ensure_pkg() {
  local pkg="$1"
  if ! dpkg -s "$pkg" >/dev/null 2>&1; then
    echo "[pkg] Installing $pkg"
    sudo apt-get update -y
    sudo apt-get install -y "$pkg"
  fi
}

mk_owned() {
  local path="$1"
  sudo mkdir -p "$path"
  sudo chown -R www-data:www-data "$path"
}

# ========== Interactive inputs ==========
echo "────────────────────────────────────────────────────────"
echo " Mini Web Panel — Installation"
echo "────────────────────────────────────────────────────────"

PANEL_DIR="${PANEL_DIR_DEFAULT}"
PANEL_DIR="$(prompt 'Chemin d’installation (PANEL_DIR)' "$PANEL_DIR")"
PANEL_DIR="${PANEL_DIR%/}" # trim trailing slash

PHP_VER="${PHP_DEFAULT}"
if [[ -z "$PHP_VER" ]]; then
  PHP_VER="$(detect_php_version)"
fi
if [[ -z "$PHP_VER" ]]; then
  PHP_VER="$(prompt 'Version PHP-FPM à utiliser (ex: 8.3, 8.2)' "8.3")"
fi
PHPFPM_SOCK="/run/php/php${PHP_VER}-fpm.sock"

if [[ ! -S "$PHPFPM_SOCK" ]]; then
  echo "[php] Socket $PHPFPM_SOCK introuvable — j’installe php${PHP_VER}-fpm si besoin…"
  ensure_pkg "php${PHP_VER}-fpm"
  # (re)lance fpm
  sudo systemctl enable "php${PHP_VER}-fpm" || true
  sudo systemctl restart "php${PHP_VER}-fpm"
  if [[ ! -S "$PHPFPM_SOCK" ]]; then
    echo "ERREUR: Socket PHP-FPM introuvable: $PHPFPM_SOCK"; exit 1
  fi
fi

VHOST_NAME="${VHOST_NAME_DEFAULT}"
VHOST_NAME="$(prompt 'Nom du fichier Nginx (sites-available) ' "$VHOST_NAME")"
# sécurité: suffixe .conf si absent
if [[ "$VHOST_NAME" != *.conf ]]; then
  VHOST_NAME="${VHOST_NAME}.conf"
fi

SERVER_NAMES="${SERVER_NAMES_DEFAULT}"
SERVER_NAMES="$(prompt 'server_name (séparés par espace)' "$SERVER_NAMES")"

# ========== Layout ==========
DATA_DIR="$PANEL_DIR/data"
LOGS_DIR="$PANEL_DIR/logs"
DB_FILE="$DATA_DIR/sites.db"
ASSETS_SRC_DIR="$(cd "$(dirname "$0")" && pwd)/public"
NGINX_AVAIL="/etc/nginx/sites-available"
NGINX_ENAB="/etc/nginx/sites-enabled"
VHOST_PATH="$NGINX_AVAIL/$VHOST_NAME"

echo "[1/7] Dossiers & droits"
mk_owned "$PANEL_DIR/public"
mk_owned "$PANEL_DIR/bin"
mk_owned "$DATA_DIR"
mk_owned "$LOGS_DIR"
sudo chmod 770 "$DATA_DIR" || true
sudo chmod 750 "$LOGS_DIR" || true

echo "[2/7] Extensions & CLI PHP/SQLite"
ensure_pkg sqlite3
ensure_pkg "php${PHP_VER}-sqlite3"
ensure_pkg "php${PHP_VER}-cli"

echo "[3/7] Assets (CSS/JS) si présents dans le dépôt"
if [[ -d "$ASSETS_SRC_DIR" ]]; then
  sudo mkdir -p "$PANEL_DIR/public/css" "$PANEL_DIR/public/js"
  if [[ -f "$ASSETS_SRC_DIR/css/style.css" ]]; then
    sudo cp -n "$ASSETS_SRC_DIR/css/style.css" "$PANEL_DIR/public/css/style.css" || true
    echo "  - CSS ok"
  fi
  if [[ -f "$ASSETS_SRC_DIR/js/app.js" ]]; then
    sudo cp -n "$ASSETS_SRC_DIR/js/app.js" "$PANEL_DIR/public/js/app.js" || true
    echo "  - JS ok"
  fi
fi

echo "[4/7] DB SQLite"
if [[ ! -f "$DB_FILE" ]]; then
  sudo touch "$DB_FILE"
  sudo chown www-data:www-data "$DB_FILE"
  sudo chmod 664 "$DB_FILE"
  echo "  - DB créée: $DB_FILE"
else
  echo "  - DB déjà présente (skip): $DB_FILE"
fi

echo "[5/7] Sudoers"
SUDOERS_FILE="/etc/sudoers.d/adminpanel"
if [[ ! -f "$SUDOERS_FILE" ]]; then
  echo "www-data ALL=(ALL) NOPASSWD: /usr/bin/systemctl reload nginx, /usr/sbin/nginx -t, $PANEL_DIR/bin/*" | sudo tee "$SUDOERS_FILE" >/dev/null
  sudo chmod 440 "$SUDOERS_FILE"
  echo "  - sudoers créé"
else
  echo "  - sudoers déjà présent (skip)"
fi

echo "[6/7] Vhost Nginx"
# Protection: ne pas écraser un vhost existant sans --force
if [[ -f "$VHOST_PATH" && "$FORCE_OVERWRITE" -ne 1 ]]; then
  echo "ATTENTION: $VHOST_PATH existe déjà."
  read -r -p "  Voulez-vous l’écraser ? [y/N]: " yn
  yn="${yn:-N}"
  if [[ "$yn" != [yY] ]]; then
    echo "  - On conserve l’existant. (skip écriture)"
  else
    FORCE_OVERWRITE=1
  fi
fi

if [[ ! -f "$VHOST_PATH" || "$FORCE_OVERWRITE" -eq 1 ]]; then
  # Détermine si public/ existe
  ROOT_DIR="$PANEL_DIR"
  if [[ -d "$PANEL_DIR/public" ]]; then
    ROOT_DIR="$PANEL_DIR/public"
  fi

  sudo tee "$VHOST_PATH" >/dev/null <<EOF
server {
    listen 80;
    server_name ${SERVER_NAMES};

    root ${ROOT_DIR};
    index index.php index.html;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:${PHPFPM_SOCK};
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
    }

    access_log /var/log/nginx/${VHOST_NAME%.conf}_access.log;
    error_log  /var/log/nginx/${VHOST_NAME%.conf}_error.log;
}
EOF
  echo "  - Vhost écrit: $VHOST_PATH"
fi

# Symlink dans sites-enabled
if [[ ! -L "$NGINX_ENAB/$VHOST_NAME" ]]; then
  sudo ln -s "$VHOST_PATH" "$NGINX_ENAB/$VHOST_NAME"
  echo "  - Activé: $NGINX_ENAB/$VHOST_NAME"
else
  echo "  - Déjà activé (skip)"
fi

echo "[7/7] Test & reload Nginx"
sudo nginx -t
sudo systemctl reload nginx

echo "✅ Installation terminée."
echo "   • PANEL_DIR    : $PANEL_DIR"
echo "   • PHP-FPM      : $PHP_VER  (sock: $PHPFPM_SOCK)"
echo "   • VHOST        : $VHOST_PATH"
echo "   • server_name  : $SERVER_NAMES"