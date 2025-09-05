#!/usr/bin/env bash
set -euo pipefail

# ─────────────────────────────────────────────────────────────
# Mini Web Panel — install.sh (interactif & idempotent)
# Flags:
#   --panel-dir=/srv/www/webadminpanel-v2
#   --php=8.3
#   --vhost-name=beta.awp.local.conf
#   --server-names="beta.awp.local"
#   --force                 # écrase vhost existant
#   --non-interactive       # utilise defaults/flags sans prompts
# ─────────────────────────────────────────────────────────────

# ========== Defaults ==========
PANEL_DIR_DEFAULT="$(pwd)"
PHP_DEFAULT=""
VHOST_NAME_DEFAULT="adminpanel.conf"
SERVER_NAMES_DEFAULT="adminpanel.local"
FORCE_OVERWRITE=0
NON_INTERACTIVE=0

# ========== Parse flags ==========
for arg in "$@"; do
  case "$arg" in
    --panel-dir=*) PANEL_DIR_DEFAULT="${arg#*=}";;
    --php=*)       PHP_DEFAULT="${arg#*=}";;
    --vhost-name=*) VHOST_NAME_DEFAULT="${arg#*=}";;
    --server-names=*) SERVER_NAMES_DEFAULT="${arg#*=}";;
    --force)       FORCE_OVERWRITE=1;;
    --non-interactive) NON_INTERACTIVE=1;;
    *) echo "Unknown arg: $arg"; exit 1;;
  esac
done

# ========== Helpers ==========
prompt() {
  local q="$1"; local def="${2-}"
  if [[ "$NON_INTERACTIVE" -eq 1 ]]; then
    echo "${def}"
    return
  fi
  local ans
  if [[ -n "${def}" ]]; then
    read -r -p "$q [$def]: " ans || true
    echo "${ans:-$def}"
  else
    read -r -p "$q: " ans || true
    echo "${ans}"
  fi
}

ensure_pkg() {
  local pkg="$1"
  if ! dpkg -s "$pkg" >/dev/null 2>&1; then
    echo "[pkg] Installing $pkg"
    sudo apt-get update -y
    sudo apt-get install -y "$pkg"
  fi
}

APP_USER="${SUDO_USER:-$USER}"
umask 002  # le groupe (www-data) a l'écriture par défaut

mk_dir() { sudo mkdir -p "$1"; }

chown_code_tree() {
  # Code: owner=user, group=www-data (Nginx lit)
  sudo chown -R "$APP_USER":www-data "$PANEL_DIR"
  sudo find "$PANEL_DIR" -type d -exec chmod 775 {} \;
  sudo find "$PANEL_DIR" -type f -exec chmod 664 {} \; 2>/dev/null || true
}

chown_runtime_dirs() {
  # Runtime (data/logs): écriture www-data
  sudo chown -R www-data:www-data "$DATA_DIR" "$LOGS_DIR"
  sudo chmod -R 775 "$DATA_DIR" "$LOGS_DIR"
}

# ========== UI ==========
echo "────────────────────────────────────────────────────────"
echo " Mini Web Panel — Installation"
echo "────────────────────────────────────────────────────────"

# PANEL_DIR (default = dossier courant), normaliser en absolu
PANEL_DIR="$(prompt 'Chemin d’installation (PANEL_DIR)' "$PANEL_DIR_DEFAULT")"
if [[ "$PANEL_DIR" != /* ]]; then
  PANEL_DIR="$(pwd)/$PANEL_DIR"
fi
PANEL_DIR="${PANEL_DIR%/}"

# ---------- PHP-FPM : détection + choix utilisateur ----------
# 1) Construire la liste des versions détectées par les sockets
available_php=()
for sock in /run/php/php*-fpm.sock; do
  [[ -S "$sock" ]] || continue
  ver="$(echo "$sock" | sed -E 's#.*/php([0-9]+\.[0-9]+)-fpm\.sock#\1#')"
  available_php+=("$ver")
done
# Si rien trouvé, proposer au moins 8.3
if [[ ${#available_php[@]} -eq 0 ]]; then
  available_php=("8.3")
fi
# Par défaut: la plus grande version détectée
# (tri "naturel" approximatif en bash: on prend la dernière trouvée, généralement la plus récente)
default_php="${available_php[-1]}"

# 2) Valeur forcée par flag --php, sinon poser la question
if [[ -n "$PHP_DEFAULT" ]]; then
  PHP_VER="$PHP_DEFAULT"
else
  PHP_VER="$(prompt "Version PHP-FPM à utiliser (disponibles: ${available_php[*]})" "$default_php")"
fi

PHPFPM_SOCK="/run/php/php${PHP_VER}-fpm.sock"
# Installer/activer php-fpm choisi si le socket n'existe pas
if [[ ! -S "$PHPFPM_SOCK" ]]; then
  echo "[php] Socket $PHPFPM_SOCK introuvable — tentative d’installation de php${PHP_VER}-fpm…"
  ensure_pkg "php${PHP_VER}-fpm"
  ensure_pkg "php${PHP_VER}-sqlite3"
  ensure_pkg "php${PHP_VER}-cli"
  sudo systemctl enable "php${PHP_VER}-fpm" || true
  sudo systemctl restart "php${PHP_VER}-fpm"
  if [[ ! -S "$PHPFPM_SOCK" ]]; then
    echo "ERREUR: Socket PHP-FPM introuvable: $PHPFPM_SOCK"; exit 1
  fi
fi

# VHOST defaults plus malins pour v2
guess_vhost="$VHOST_NAME_DEFAULT"
guess_server="$SERVER_NAMES_DEFAULT"
if [[ "$(basename "$PANEL_DIR")" =~ v2 ]]; then
  guess_vhost="beta.awp.local.conf"
  guess_server="beta.awp.local"
fi

VHOST_NAME="$(prompt 'Nom du fichier Nginx (sites-available)' "$guess_vhost")"
[[ "$VHOST_NAME" == *.conf ]] || VHOST_NAME="${VHOST_NAME}.conf"
SERVER_NAMES="$(prompt 'server_name (séparés par espace)' "$guess_server")"

# ========== Layout ==========
DATA_DIR="$PANEL_DIR/data"
LOGS_DIR="$PANEL_DIR/logs"
DB_FILE="$DATA_DIR/sites.db"
ASSETS_SRC_DIR="$(cd "$(dirname "$0")" && pwd)/public"
NGINX_AVAIL="/etc/nginx/sites-available"
NGINX_ENAB="/etc/nginx/sites-enabled"
VHOST_PATH="$NGINX_AVAIL/$VHOST_NAME"

echo "[1/7] Dossiers & droits"
mk_dir "$PANEL_DIR/public"
mk_dir "$PANEL_DIR/bin"
mk_dir "$DATA_DIR"
mk_dir "$LOGS_DIR"
chown_code_tree
chown_runtime_dirs

echo "[1b/7] Marquer bin/*.sh comme exécutables + normaliser (CRLF) + droits de traversée"
if compgen -G "$PANEL_DIR/bin/*.sh" >/dev/null 2>&1; then
  sudo chmod +x "$PANEL_DIR/bin/"*.sh || true
  sudo sed -i 's/\r$//' "$PANEL_DIR/bin/"*.sh 2>/dev/null || true
  sudo sed -i '1s/\r$//' "$PANEL_DIR/bin/"*.sh 2>/dev/null || true
  # Dossiers traversables (sudo a besoin du +x sur chaque répertoire du chemin)
  sudo chmod 755 "$PANEL_DIR" "$PANEL_DIR/bin" || true
  echo "  - bin/*.sh -> +x ; CRLF nettoyé ; dossiers +x"
else
  echo "  - Aucun script dans $PANEL_DIR/bin pour l’instant (skip)"
fi

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
  if [[ -f "$ASSETS_SRC_DIR/js/startReboot.js" ]]; then
    sudo cp -n "$ASSETS_SRC_DIR/js/startReboot.js" "$PANEL_DIR/public/js/startReboot.js" || true
    echo "  - JS power (startReboot.js) ok"
  fi
  if [[ -f "$ASSETS_SRC_DIR/js/energy.js" ]]; then
    sudo cp -n "$ASSETS_SRC_DIR/js/energy.js" "$PANEL_DIR/public/js/energy.js" || true
    echo "  - JS energy ok"
  fi
  if [[ -f "$ASSETS_SRC_DIR/js/sysinfo.js" ]]; then
    sudo cp -n "$ASSETS_SRC_DIR/js/sysinfo.js" "$PANEL_DIR/public/js/sysinfo.js" || true
    echo "  - JS sysinfo ok"
  fi
  if [[ -f "$ASSETS_SRC_DIR/js/php_manage.js" ]]; then
    sudo cp -n "$ASSETS_SRC_DIR/js/php_manage.js" "$PANEL_DIR/public/js/php_manage.js" || true
    echo "  - JS php_manage ok"
  fi
fi

echo "[3b/7] Dépendances Power Saver (rfkill + vcgencmd si Raspberry Pi)"
if command -v apt-get >/dev/null 2>&1; then
  sudo apt-get update -y >/dev/null 2>&1 || true
  # rfkill pour Wi-Fi/BT ; libraspberrypi-bin pour vcgencmd (HDMI)
  sudo apt-get install -y rfkill libraspberrypi-bin >/dev/null 2>&1 || true
else
  echo "  - apt-get indisponible (skip deps Power Saver)"
fi

echo "[4/7] DB SQLite (création + init admin)"
if [[ ! -f "$DB_FILE" ]]; then
  sudo touch "$DB_FILE"
  sudo chown www-data:www-data "$DB_FILE"
  sudo chmod 664 "$DB_FILE"
  # init schéma + admin par défaut (admin/admin) si table vide
  DB_PATH="$DB_FILE" /usr/bin/php <<'PHP'
<?php
$path = getenv('DB_PATH');
if (!$path) { fwrite(STDERR, "No DB_PATH\n"); exit(1); }
$pdo = new PDO('sqlite:'.$path, null, null, [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$pdo->exec("CREATE TABLE IF NOT EXISTS users(
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  username TEXT UNIQUE NOT NULL,
  password_hash TEXT NOT NULL,
  created_at TEXT NOT NULL
)");
$pdo->exec("CREATE TABLE IF NOT EXISTS sites(
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT UNIQUE NOT NULL,
  server_names TEXT NOT NULL,
  root TEXT NOT NULL,
  php_version TEXT NOT NULL,
  client_max_body_size INTEGER NOT NULL DEFAULT 20,
  with_logs INTEGER NOT NULL DEFAULT 1,
  enabled INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL
)");
$pdo->exec("CREATE TABLE IF NOT EXISTS audit(
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  username TEXT NOT NULL,
  action TEXT NOT NULL,
  payload TEXT,
  created_at TEXT NOT NULL
)");
$exists = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
if ($exists === 0) {
  $hash = password_hash('admin', PASSWORD_BCRYPT);
  $st = $pdo->prepare("INSERT INTO users(username,password_hash,created_at) VALUES(:u,:p,:c)");
  $st->execute([':u'=>'admin', ':p'=>$hash, ':c'=>date('c')]);
  echo "Admin créé: admin/admin\n";
} else {
  echo "Admin déjà présent (aucune modif)\n";
}
PHP
  echo "  - DB créée et initialisée: $DB_FILE"
else
  echo "  - DB déjà présente (skip init): $DB_FILE"
fi

echo "[5/7] Sudoers"
SUDOERS_FILE="/etc/sudoers.d/adminpanel"

# Résoudre en chemin absolu sans symlink pour éviter les mismatchs
PANEL_DIR_ABS="$(python3 - <<'PY'
import os,sys; p=os.environ.get("PANEL_DIR",""); print(os.path.realpath(p))
PY
)"
[[ -n "$PANEL_DIR_ABS" ]] || PANEL_DIR_ABS="$PANEL_DIR"

# Règle unique: www-data peut lancer TOUS les scripts de $PANEL_DIR/bin/ sans mot de passe
SUDO_LINE="www-data ALL=(ALL) NOPASSWD: /usr/bin/systemctl reload nginx, /usr/sbin/nginx -t, ${PANEL_DIR_ABS}/bin/*"

# (Ré)écriture idempotente
if [[ ! -f "$SUDOERS_FILE" ]] || ! grep -Fq "$PANEL_DIR_ABS/bin/*" "$SUDOERS_FILE"; then
  echo "$SUDO_LINE" | sudo tee "$SUDOERS_FILE" >/dev/null
  sudo chmod 440 "$SUDOERS_FILE"
  if sudo visudo -c >/dev/null 2>&1; then
    echo "  - sudoers créé/actualisé (OK)"
  else
    echo "  - WARNING: sudoers invalide"; exit 1
  fi
else
  echo "  - sudoers déjà présent (skip)"
fi

echo "[5b/7] PHP-FPM: injecter env[PANEL_DIR] pour l’app"
PHPFPM_POOL_CONF="/etc/php/${PHP_VER}/fpm/pool.d/www.conf"
if ! grep -q '^env\[PANEL_DIR\]' "$PHPFPM_POOL_CONF" ; then
  echo "env[PANEL_DIR] = $PANEL_DIR" | sudo tee -a "$PHPFPM_POOL_CONF" >/dev/null
  sudo systemctl reload "php${PHP_VER}-fpm"
  echo "  - env[PANEL_DIR] ajouté et PHP-FPM rechargé"
else
  echo "  - env[PANEL_DIR] déjà présent (skip)"
fi

echo "[6/7] Vhost Nginx"
# Protection overwrite
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
  # Backup si existait
  if [[ -f "$VHOST_PATH" ]]; then
    sudo cp -a "$VHOST_PATH" "${VHOST_PATH}.bak.$(date +%F-%H%M%S)"
    echo "  - Backup vhost: ${VHOST_PATH}.bak.*"
  fi
  # root = .../public s’il existe
  ROOT_DIR="$PANEL_DIR"
  [[ -d "$PANEL_DIR/public" ]] && ROOT_DIR="$PANEL_DIR/public"

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

# Activer
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
echo "   • DB           : $DB_FILE"