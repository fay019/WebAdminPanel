# Mini Web Panel • Nginx + PHP-FPM (Raspberry Pi)

[![Debian 12](https://img.shields.io/badge/Debian-12-red?logo=debian)](#) [![Nginx](https://img.shields.io/badge/Nginx-1.x-brightgreen?logo=nginx)](#) [![PHP-FPM](https://img.shields.io/badge/PHP-8.2%20|%208.3%20|%208.4-777bb4?logo=php)](#) [![SQLite](https://img.shields.io/badge/DB-SQLite-blue?logo=sqlite)](#)

Mini application PHP (sans framework) pour gérer les vhosts Nginx et sélectionner la version PHP‑FPM par site. Cible: Raspberry Pi OS (Debian 12) en LAN, avec authentification obligatoire.

## ✨ Fonctionnalités
- 🧩 Gestion des sites (CRUD)
  - Lecture des vhosts: `/etc/nginx/sites-available/*.conf`
  - Création avec choix PHP‑FPM 8.2/8.3/8.4, `root`, `server_names`, `client_max_body_size`, logs dédiés on/off
  - Édition: `server_names`, `root`, `php_version`, `client_max_body_size`, logs
  - Activer/Désactiver (symlink in/out `sites-enabled`)
  - Suppression (conf + symlink) avec option Supprimer + Dossier
- 🔎 Vérifications lors de la création
  - Unicité du slug (DB)
  - Conflits `server_name` (DB + scan conf Nginx)
  - Unicité du document root (DB)
  - Conflit de vhost existant côté Nginx
  - Si le dossier existe déjà: utiliser tel quel OU réinitialiser (backup en `xxx.old.TIMESTAMP` + page d’accueil par défaut)
  - Modales de confirmation thémées
- 🛠️ Actions Nginx
  - Tester `nginx -t` et Recharger `systemctl reload nginx` (sorties affichées)
- 📋 Tableau des sites avec actions (Éditer / Activer / Désactiver / Supprimer / Supprimer + Dossier)
- 🧹 Orphelins
  - Détection de `/var/www/*` non référencés et dossiers `*.old.TIMESTAMP`
  - Suppression sécurisée via `bin/orphan_delete.sh` (sudo whitelist)
- 🏁 Page d’accueil par défaut des sites (montre version PHP, document root, date/heure, prochaines étapes)
- 👤 Compte utilisateur
  - Changer nom d’utilisateur et mot de passe (bcrypt)
- 🔐 Sécurité
  - Login, CSRF, validations strictes
  - Sudoers ultra‑limités aux commandes/scripts nécessaires
  - Audit des actions dans `logs/panel.log` + table `audit`

## 🧭 Navigation (UI)
- Dashboard: `/dashboard.php` — métriques système (temp CPU, RAM, load, disque, sockets PHP‑FPM, version Nginx, etc.)
- Système PHP: `/php_manage.php` — lister/installer/supprimer/redémarrer PHP‑FPM 7.4–8.4 (via sudo + APT Sury)
- Sites: `/sites_list.php` — tableau des sites + actions
- Nouveau site: `/site_new.php`
- Éditer: `/site_edit.php?id=...`
- Activer/Désactiver: `/site_toggle.php?a=enable|disable&id=...`
- Supprimer: `/site_delete.php?id=...&delete_root=0|1`
- Compte: `/account.php`
- Connexion/Déconnexion: `/login.php` / `/logout.php`

## ⚙️ Scripts CLI disponibles (bin/)
- php_manage.sh
  - list [--json]
  - candidates [--json]
  - install <ver> (ex: 8.2 | 8.3 | 8.4)
  - remove <ver>
  - restart <ver>
- site_add.sh <name> <server_names> <root> <php_version> <max_upload> <with_logs> [reset_root]
- site_edit.sh <old_name> <new_name> <server_names> <root> <max_upload> <with_logs>
- site_enable.sh <name>
- site_disable.sh <name>
- site_delete.sh <name> <yes|no>
- sysinfo.sh (impressions k=v pour le dashboard)

Ces scripts sont whitelistes dans `/etc/sudoers.d/adminpanel` par `install.sh` pour l’utilisateur web `www-data` (NOPASSWD).

## ✅ Prérequis
- Raspberry Pi OS (Debian 12)
- Nginx installé et actif
- PHP 8.2/8.3/8.4 (FPM) + extensions pdo_sqlite et sqlite3
- Accès sudo pour déployer sudoers et recharger Nginx

## 🚀 Installation
1) Copier le dossier du projet sur le Pi:
- Chemin recommandé: `/var/www/adminpanel`

2) Lancer l’installateur:

```bash
sudo chmod +x /var/www/adminpanel/install.sh
sudo /var/www/adminpanel/install.sh
```

Ce que fait `install.sh`:
- Crée `data/` et `logs/` (droits www-data)
- Vérifie pdo_sqlite/sqlite3 et initialise la base SQLite (user admin/admin)
- Déploie `/etc/sudoers.d/adminpanel` (whitelist des commandes)
- Crée le vhost `adminpanel.conf` (PHP‑FPM 8.3 par défaut) et recharge Nginx

3) Accéder à l’interface:
- URL par défaut: http://adminpanel.local/ (ajouter dans /etc/hosts si besoin) ou via l’IP du Pi
- Identifiants initiaux: admin / admin (à changer dans Compte)

## 🔧 Configuration rapide
- PHP‑FPM par défaut du panel: 8.3 (modifiable dans `install.sh` via DEFAULT_PHP)
- Dossier d’installation: `/var/www/adminpanel`
- Base SQLite: `data/sites.db`
- Logs panel: `logs/panel.log`
- Assets: `public/css/style.css`, `public/js/app.js`, logo `public/img/logo.svg`
- Localisation: `locales/*.php` (fr, en, de, dz)

## 🛡️ Notes sécurité
- Sudoers limités aux commandes nécessaires (nginx -t, reload, scripts bin/*)
- CSRF actif sur tous les formulaires, validations côté serveur
- Les opérations système sensibles passent par `sudo -n` avec sorties streamées côté UI

## ❓ Dépannage
- Aucune sortie lors d’une installation PHP: vérifier `/etc/sudoers.d/adminpanel` (relancer `install.sh`) et consulter `/var/log/nginx/error.log`
- `php-fpm.sock` introuvable: adapter la version PHP‑FPM dans le vhost généré
- Extensions SQLite manquantes: `sudo apt install php8.3-sqlite3 && sudo systemctl restart php8.3-fpm`

## 🧺 Publication GitHub: quoi committer, quoi ignorer
- Ne PAS committer: base de données et logs générés en production.
  - data/ (contient la base SQLite avec les utilisateurs et l’historique)
  - logs/ (contient logs d’audit et erreurs)
  - Fichiers *.db, *.sqlite*, *.log, backups *.old.*
- OK à committer: code source PHP, scripts bin/*, assets dans public/, install.sh, README, locales, lib/*.
- Après installation, changez immédiatement les identifiants par défaut (admin/admin) via la page Compte, et ne commitez jamais la DB.
- Le fichier sudoers est généré côté système (/etc/sudoers.d/adminpanel) et ne doit pas être versionné.

Un fichier .gitignore adapté est fourni pour éviter de pousser ces artefacts.

## 📄 Licence
Projet privé/démonstration. Adapter selon vos besoins.