# Mini Web Panel • Nginx + PHP-FPM (Raspberry Pi) V1.0.0

[![GitHub release](https://img.shields.io/github/v/release/fay019/WebAdminPanel?logo=github)](https://github.com/fay019/WebAdminPanel/releases/)
[![Debian 12](https://img.shields.io/badge/Debian-12-red?logo=debian)](#)
[![Nginx](https://img.shields.io/badge/Nginx-1.x-brightgreen?logo=nginx)](#)
[![PHP-FPM](https://img.shields.io/badge/PHP-8.2%20|%208.3%20|%208.4-777bb4?logo=php)](#)
[![SQLite](https://img.shields.io/badge/DB-SQLite-blue?logo=sqlite)](#)
[![Raspberry Pi 5](https://img.shields.io/badge/Raspberry%20Pi-5-green?logo=raspberrypi)](#)

Mini application PHP (sans framework) pour gérer les vhosts **Nginx** et sélectionner la version **PHP-FPM** par site.  
Cible: Raspberry Pi OS (Debian 12) en LAN, avec authentification obligatoire.

---

## 🤔 Pourquoi
- Simplifier la gestion des vhosts Nginx sans modifier les `.conf` à la main.
- Pouvoir basculer entre plusieurs versions de PHP-FPM (8.2 / 8.3 / 8.4).
- Offrir un **mini-cPanel LAN** pour Raspberry Pi sans surcouche lourde.

---

## ✨ Fonctionnalités principales
- CRUD complet sur les sites (vhosts Nginx)
- Sélection PHP-FPM par site (8.2, 8.3, 8.4)
- Vérifications automatiques (slug, server_name, root, conflits)
- Actions système: `nginx -t` + reload
- Éteindre / Redémarrer le Pi
- Gestion utilisateurs (CRUD + bcrypt)
- Sécurité: CSRF, sudoers limités, audit log

---

## ✅ Prérequis & installation de base

Avant d’installer le panel, installez Nginx et PHP-FPM (8.2, 8.3, 8.4) :

```bash
# Mettez à jour votre système
sudo apt update && sudo apt upgrade -y

# Installer Nginx
sudo apt install nginx -y

# Ajouter le dépôt Sury pour PHP (versions récentes)
sudo apt install -y lsb-release ca-certificates apt-transport-https software-properties-common gnupg2
echo "deb https://packages.sury.org/php $(lsb_release -sc) main" | sudo tee /etc/apt/sources.list.d/php.list
wget -qO - https://packages.sury.org/php/apt.gpg | sudo apt-key add -
sudo apt update

# Installer PHP-FPM 8.2 / 8.3 / 8.4 + SQLite
sudo apt install -y php8.2-fpm php8.2-sqlite3
sudo apt install -y php8.3-fpm php8.3-sqlite3
sudo apt install -y php8.4-fpm php8.4-sqlite3
```

---

## 🚀 Installation du panel

1) Copier le projet sur le Pi (chemin recommandé: `/var/www/adminpanel`) :

```bash
sudo cp -r adminpanel /var/www/adminpanel
```

2) Lancer l’installateur :

```bash
sudo chmod +x /var/www/adminpanel/install.sh
sudo /var/www/adminpanel/install.sh
```

3) Accéder au panel :
- URL: http://adminpanel.local/ (ou IP du Pi)
- Identifiants par défaut: **admin / admin** (à changer immédiatement !)

---

## 🔧 Configuration rapide
- PHP-FPM par défaut du panel: **8.3** (modifiable dans `install.sh`)
- Base SQLite: `data/sites.db`
- Logs: `logs/panel.log`
- Locales: `locales/fr|en|de|dz`

---

## 🛡️ Notes sécurité
- CSRF actif sur tous les formulaires
- Sudoers ultra-limités (`nginx -t`, reload, scripts bin/*)
- Audit log → `logs/panel.log`

---

## ❓ Dépannage rapide
- Pas d’output install PHP → vérifier `/etc/sudoers.d/adminpanel`
- `php-fpm.sock` manquant → adapter le vhost généré
- SQLite manquant →
  ```bash
  sudo apt install php8.3-sqlite3 && sudo systemctl restart php8.3-fpm
  ```  

---

## 🧺 Publication GitHub
**À ne PAS committer** : `data/`, `logs/`, `*.db`, `*.sqlite*`, `*.log`, `*.old.*`  
**À committer** : code source, `bin/`, `public/`, `install.sh`, `locales/`, `lib/`, `README.md`

---

## 📂 Structure
```
lib/         # Auth, CSRF, DB, i18n, validators
locales/     # fr, en, de, dz
public/      # CSS, JS, images, erreurs
bin/         # Scripts CLI (nginx, php-fpm, sites, power, sysinfo)
data/        # SQLite (non versionnée)
logs/        # Journaux (non versionnés)
*.php        # Pages UI (dashboard, sites, users, etc.)
```

---

## 📸 Captures d’écran

### Connexion
![Login](docs/screenshots/screenshot-login.png)

### Dashboard
![Dashboard](docs/screenshots/screenshot-dashboard.png)

### Gestion PHP
- Ajout d’une version PHP  
  ![PHP Config](docs/screenshots/screenshot-php-config.png)
- Versions détectées  
  ![PHP List](docs/screenshots/screenshot-php-list.png)

### Gestion des sites
- Liste des sites  
  ![Sites](docs/screenshots/screenshot-users-list.png)
- Nouveau site (création réussie)  
  ![New Site Created](docs/screenshots/screenshot-new-site-created.png)
- Nouveau site (erreur validation)  
  ![New Site Error](docs/screenshots/screenshot-new-site-error.png)
- Suppression d’un site  
  ![Site Deleted](docs/screenshots/screenshot-site-deleted.png)
- Test & reload Nginx  
  ![Nginx Testing](docs/screenshots/screenshot-nginx-testing.png)

### Gestion des utilisateurs
- Liste des utilisateurs  
  ![Users List](docs/screenshots/screenshot-users-list.png)
- Édition utilisateur  
  ![User Edit](docs/screenshots/screenshot-user-edit.png)
- Édition compte (moi)  
  ![Account Edit](docs/screenshots/screenshot-account-edit.png)

### Système
- Redémarrage en cours  
  ![Reboot](docs/screenshots/screenshot-reboot.png)
---

## 🗺️ Roadmap
- [ ] Backup/restore vhosts
- [ ] Export logs d’audit
- [ ] Mode lecture seule
- [ ] Gestion avancée multi-users (rôles)

---

## ⚠️ Limites connues
- Un seul vhost par site (pas encore de reverse proxy complexes)
- Pas de rollback automatique sur erreur Nginx
- Auth simple SQLite (à utiliser sur LAN sécurisé)

---

## 📄 Licence
Projet privé/démonstration. Adapter selon vos besoins.  
