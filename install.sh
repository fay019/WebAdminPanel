#!/usr/bin/env bash
set -euo pipefail

# ──────────────────────────────────────────────────────────────────────────────
# Mini Web Panel — Script d’installation (idempotent)
# - Crée data/logs + droits
# - Vérifie extensions SQLite
# - (Ré)initialise la DB + admin
# - Déploie sudoers (incl. orphan_delete.sh)
# - Crée le vhost adminpanel.conf si absent (PHP-FPM 8.3 par défaut)
# - Test & reload nginx
# ──────────────────────────────────────────────────────────────────────────────

PANEL_DIR="/var/www/adminpanel"
DATA_DIR="$PANEL_DIR/data"
LOGS_DIR="$PANEL_DIR/logs"
DB_FILE="$DATA_DIR/sites.db"
SUDOERS_FILE="/etc/sudoers.d/adminpanel"
NGINX_AVAIL="/etc/nginx/sites-available"
NGINX_ENAB="/etc/nginx/sites-enabled"
VHOST="$NGINX_AVAIL/adminpanel.conf"

# PHP-FPM par défaut pour le panel
DEFAULT_PHP="8.3"
PHPFPM_SOCK="/run/php/php${DEFAULT_PHP}-fpm.sock"

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
ASSET_CSS_SRC="$SCRIPT_DIR/public/css/style.css"
ASSET_JS_SRC="$SCRIPT_DIR/public/js/app.js"
OVERWRITE_ASSETS="${OVERWRITE_ASSETS:-0}"  # 1 pour forcer la copie, 0 sinon

echo "[1/7] Dossiers & droits"
mkdir -p "$DATA_DIR" "$LOGS_DIR" "$PANEL_DIR/public" "$PANEL_DIR/bin"
chown -R www-data:www-data "$DATA_DIR" "$LOGS_DIR"
chmod 770 "$DATA_DIR" || true
chmod 750 "$LOGS_DIR" || true

# Assets CSS — copie depuis le dépôt si présent
mkdir -p "$PANEL_DIR/public/css"
if [ -f "$ASSET_CSS_SRC" ]; then
  if [ "$OVERWRITE_ASSETS" = "1" ] || [ ! -f "$PANEL_DIR/public/css/style.css" ]; then
    install -m 644 -D "$ASSET_CSS_SRC" "$PANEL_DIR/public/css/style.css"
    echo "[assets] CSS installé depuis le projet (OVERWRITE=$OVERWRITE_ASSETS)"
  else
    echo "[assets] CSS existant conservé"
  fi
fi

# Assets JS — copie depuis le dépôt si présent
mkdir -p "$PANEL_DIR/public/js"
if [ -f "$ASSET_JS_SRC" ]; then
  if [ "$OVERWRITE_ASSETS" = "1" ] || [ ! -f "$PANEL_DIR/public/js/app.js" ]; then
    install -m 644 -D "$ASSET_JS_SRC" "$PANEL_DIR/public/js/app.js"
    echo "[assets] JS installé depuis le projet (OVERWRITE=$OVERWRITE_ASSETS)"
  else
    echo "[assets] JS existant conservé"
  fi
fi

echo "[2/7] Scripts bin exécutable"
if compgen -G "$PANEL_DIR/bin/*.sh" > /dev/null; then
  chmod +x "$PANEL_DIR/bin/"*.sh || true
  chown -R www-data:www-data "$PANEL_DIR/bin" || true
fi

echo "[3/7] Extensions PHP (pdo_sqlite, sqlite3)"
if ! php -m | grep -qi pdo_sqlite; then
  echo "⚠️  pdo_sqlite manquant. Sur Debian: sudo apt install php${DEFAULT_PHP}-sqlite3 && sudo systemctl restart php${DEFAULT_PHP}-fpm"
fi
if ! php -m | grep -qi '^sqlite3$'; then
  echo "⚠️  sqlite3 manquant. Sur Debian: sudo apt install php${DEFAULT_PHP}-sqlite3 && sudo systemctl restart php${DEFAULT_PHP}-fpm"
fi

echo "[4/7] Dépendances Power Saver (rfkill + vcgencmd si Raspberry Pi)"
if command -v apt-get >/dev/null 2>&1; then
  # Tente l’install silencieuse (idempotent). Sur Pi, vcgencmd vient de libraspberrypi-bin.
  sudo apt-get update -y >/dev/null 2>&1 || true
  sudo apt-get install -y rfkill libraspberrypi-bin >/dev/null 2>&1 || true
else
  echo "apt-get non disponible (skip deps Power Saver)"
fi

echo "[5/7] Init DB + admin"
if [ ! -f "$DB_FILE" ]; then
  /usr/bin/php <<'PHP'
<?php
$db=new PDO('sqlite:/var/www/adminpanel/data/sites.db', null, null, [
  PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
]);

$db->exec("CREATE TABLE IF NOT EXISTS users(
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  username TEXT UNIQUE NOT NULL,
  password_hash TEXT NOT NULL,
  created_at TEXT NOT NULL
)");

$db->exec("CREATE TABLE IF NOT EXISTS sites(
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

$db->exec("CREATE TABLE IF NOT EXISTS audit(
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  username TEXT NOT NULL,
  action TEXT NOT NULL,
  payload TEXT,
  created_at TEXT NOT NULL
)");

$exists = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
if ((int)$exists === 0) {
  $hash = password_hash('admin', PASSWORD_BCRYPT);
  $st = $db->prepare("INSERT INTO users(username,password_hash,created_at) VALUES(:u,:p,:c)");
  $st->execute([':u'=>'admin', ':p'=>$hash, ':c'=>date('c')]);
  echo "Admin créé: admin/admin\n";
} else {
  echo "Admin déjà présent (aucune modif)\n";
}
PHP
else
  echo "DB déjà présente (skip init)"
fi

chown -R www-data:www-data "$DATA_DIR"
chown -R www-data:www-data "$PANEL_DIR/public" || true

echo "[6/7] Sudoers (whitelist commandes/scripts)"
sudo tee "$SUDOERS_FILE" >/dev/null <<'SUDO'
www-data ALL=(root) NOPASSWD: /usr/sbin/nginx -t, /bin/systemctl reload nginx, \
/var/www/adminpanel/bin/site_add.sh, /var/www/adminpanel/bin/site_edit.sh, \
/var/www/adminpanel/bin/site_enable.sh, /var/www/adminpanel/bin/site_disable.sh, \
/var/www/adminpanel/bin/site_delete.sh, /var/www/adminpanel/bin/sysinfo.sh, \
/var/www/adminpanel/bin/orphan_delete.sh *, /var/www/adminpanel/bin/php_manage.sh *, \
/var/www/adminpanel/bin/power.sh *
SUDO

sudo chown root:root "$SUDOERS_FILE"
sudo chmod 440 "$SUDOERS_FILE"
sudo visudo -c >/dev/null && echo "Sudoers OK"

echo "[7/7] Vhost adminpanel + reload nginx"
if [ ! -S "$PHPFPM_SOCK" ]; then
  echo "⚠️  Socket PHP-FPM $PHPFPM_SOCK introuvable. Adapte la version PHP-FPM dans le vhost si besoin."
fi

if [ ! -f "$VHOST" ]; then
  sudo tee "$VHOST" >/dev/null <<NGINX
server {
  listen 80;
  server_name adminpanel.local;
  root $PANEL_DIR;
  index index.php index.html;

  location / { try_files \$uri \$uri/ /index.php?\$query_string; }
  location ~ \.php$ {
    include snippets/fastcgi-php.conf;
    fastcgi_pass unix:$PHPFPM_SOCK;
  }

  access_log /var/log/nginx/adminpanel.access.log;
  error_log  /var/log/nginx/adminpanel.error.log;
}
NGINX
  sudo ln -sf "$VHOST" "$NGINX_ENAB/adminpanel.conf"
fi

sudo nginx -t
sudo systemctl reload nginx
echo "✅ Installation terminée. Accès: http://adminpanel.local/ (ou via l'IP du Pi)."