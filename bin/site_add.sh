#!/usr/bin/env bash
set -euo pipefail

# Usage: site_add.sh <name> <server_names> <root> <php_version> <max_upload> <with_logs> [reset_root]
if [[ $# -lt 6 || $# -gt 7 ]]; then
  echo "Usage: $(basename "$0") <name> <server_names> <root> <php_version> <max_upload> <with_logs> <reset_root>" >&2
  exit 1
fi

name="$1"
server_names="$2"
root="$3"
php_version="$4"   # 8.2 | 8.3 | 8.4
max_upload="$5"    # MB
with_logs="$6"     # 1|0
reset="${7:-0}"    # 0|1 (réinitialiser le dossier s'il existe)

# ex: root=/var/www/demo/public -> site_dir=/var/www/demo
site_dir="$(dirname "$root")"

# Validations rapides
[[ "$name" =~ ^[a-z0-9-]+$ ]] || { echo "Invalid name slug"; exit 2; }
[[ "$root" == /var/www/* ]] || { echo "Root must be under /var/www"; exit 2; }
[[ "$php_version" =~ ^8\.(2|3|4)$ ]] || { echo "Invalid PHP version"; exit 2; }

echo "[info] creating site '$name' (php=$php_version, max=${max_upload}M, logs=$with_logs, reset_root=$reset)"

# 0) Si le dossier existe déjà
if [[ -d "$site_dir" ]]; then
  if [[ "$reset" == "1" ]]; then
    bak="${site_dir}.old.$(date +%s)"
    mv -- "$site_dir" "$bak"
    echo "[ok] existing dir moved to: $bak"
  else
    echo "[info] existing dir kept: $site_dir"
  fi
fi

# 1) Dossiers du site (récrée propre si reset)
mkdir -p "$root"

# Droits sur TOUT le site (pas seulement /public)
chown -R www-data:www-data "$site_dir"
chmod -R u=rwX,g=rX,o=rX "$site_dir"

# Page de bienvenue seulement si pas d'index existant
if [[ ! -f "$root/index.php" ]]; then
  cat > "$root/index.php" <<'PHP'
<?php
$phpVersion = phpversion();
$root = __DIR__;
$server = $_SERVER['SERVER_SOFTWARE'] ?? 'Nginx/PHP-FPM';
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>Bienvenue sur <?= htmlspecialchars($_SERVER['SERVER_NAME'] ?? 'Mon site') ?></title>
  <style>
    body {font-family: system-ui, sans-serif; background:#0e1627; color:#eee; margin:0; padding:0;}
    header {background:#1f2d3d; padding:20px; text-align:center;}
    header h1 {margin:0; font-size:2em; color:#4fc3f7;}
    main {padding:40px; max-width:800px; margin:auto;}
    .card {background:#1b2735; border-radius:12px; padding:20px; margin:20px 0; box-shadow:0 2px 6px rgba(0,0,0,0.4);}
    h2 {margin-top:0; color:#ffca28;}
    code {background:#263445; padding:2px 6px; border-radius:4px;}
    footer {text-align:center; font-size:0.8em; color:#aaa; margin:40px 0;}
  </style>
</head>
<body>
  <header>
    <h1>🚀 Bienvenue sur <?= htmlspecialchars($_SERVER['SERVER_NAME'] ?? 'votre nouveau site') ?></h1>
    <p>Fay Mini Web Panel — Nginx + PHP-FPM</p>
  </header>
  <main>
    <div class="card">
      <h2>Configuration du serveur</h2>
      <ul>
        <li><strong>Serveur :</strong> <?= htmlspecialchars($server) ?></li>
        <li><strong>Version PHP :</strong> <?= htmlspecialchars($phpVersion) ?></li>
        <li><strong>Document root :</strong> <code><?= htmlspecialchars($root) ?></code></li>
        <li><strong>Date & heure :</strong> <?= date('Y-m-d H:i:s') ?></li>
      </ul>
    </div>

    <div class="card">
      <h2>Prochaines étapes</h2>
      <ol>
        <li>Ajoutez vos fichiers dans <code><?= htmlspecialchars($root) ?></code></li>
        <li>Modifiez ce fichier <code>index.php</code> ou créez votre propre application</li>
        <li>Configurez HTTPS plus tard avec <code>certbot</code></li>
      </ol>
    </div>
  </main>
  <footer>&copy; <?= date('Y') ?> — Fay Mini Web Panel</footer>
</body>
</html>
PHP
  chown www-data:www-data "$root/index.php"
  chmod 644 "$root/index.php"
fi

# 2) Générer la conf Nginx
conf="/etc/nginx/sites-available/${name}.conf"
cat > "$conf" <<'NGINX'
server {
    listen 80;
    server_name {SERVER_NAMES};
    root {ROOT};
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php{PHP_VERSION}-fpm.sock;
    }

    access_log /var/log/nginx/{NAME}.access.log;
    error_log  /var/log/nginx/{NAME}.error.log;
    client_max_body_size {MAX_UPLOAD}M;
}
NGINX

# Substitutions sûres
sed -i \
  -e "s/{SERVER_NAMES}/$server_names/" \
  -e "s#{ROOT}#$root#" \
  -e "s/{PHP_VERSION}/$php_version/" \
  -e "s/{NAME}/$name/g" \
  -e "s/{MAX_UPLOAD}/$max_upload/" \
  "$conf"

# 3) Activer le site
ln -sf "$conf" "/etc/nginx/sites-enabled/${name}.conf"

# 4) Logs dédiés (option)
if [[ "$with_logs" == "1" ]]; then
  : # les chemins /var/log/nginx/${name}.* seront créés/écrits par Nginx
fi

# 5) Test Nginx
/usr/sbin/nginx -t
echo "OK: site '$name' created at $root with PHP-FPM $php_version (reset_root=$reset)"