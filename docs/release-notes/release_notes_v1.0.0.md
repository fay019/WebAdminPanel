## 🎉 Mini Web Panel v1.0.0

Première version stable du mini panneau de gestion **Nginx + PHP-FPM** pour Raspberry Pi (Debian 12) en LAN.

### ✨ Added
- CRUD complet des sites (vhosts Nginx) : création, édition, activation/désactivation, suppression
- Sélection de la version PHP-FPM par site (8.2 / 8.3 / 8.4)
- Vérifications automatiques (slug unique, conflits `server_name`, root existant, vhost déjà présent)
- Dashboard système : CPU, RAM, load, disque, sockets PHP-FPM, version Nginx
- Gestion de l’alimentation système : Éteindre / Redémarrer le Pi (sudo whitelist)
- Authentification + gestion des utilisateurs (CRUD, mots de passe bcrypt)
- Sécurité : CSRF actif, sudoers ultra-limités, audit log des actions
- Scripts CLI : `php_manage.sh`, `site_add.sh`, `site_edit.sh`, `site_enable.sh`, `site_disable.sh`, `site_delete.sh`, `power.sh`, `sysinfo.sh`
- Documentation complète avec captures d’écran

### 🛠 Installation rapide
```bash
sudo apt update && sudo apt upgrade -y
sudo apt install nginx -y
sudo apt install -y lsb-release ca-certificates apt-transport-https software-properties-common gnupg2
echo "deb https://packages.sury.org/php $(lsb_release -sc) main" | sudo tee /etc/apt/sources.list.d/php.list
wget -qO - https://packages.sury.org/php/apt.gpg | sudo apt-key add -
sudo apt update
sudo apt install -y php8.2-fpm php8.2-sqlite3 php8.3-fpm php8.3-sqlite3 php8.4-fpm php8.4-sqlite3
```

### 🚀 Déploiement du panel
```bash
sudo cp -r adminpanel /var/www/adminpanel
sudo chmod +x /var/www/adminpanel/install.sh
sudo /var/www/adminpanel/install.sh
```

- Accès : http://adminpanel.local/ (ou IP du Pi)  
- Identifiants initiaux : **admin / admin** → à changer immédiatement  

### ⚠️ Notes
- Ne pas versionner `data/` (SQLite) ni `logs/` en production  
- Utilisation conseillée sur **LAN sécurisé**  
