# Changelog

## [1.0.1] - 2025-09-15
### Fixed
- power: Remplace l’ancien contrôleur par un singleton robuste (power.js) avec gestion du streaming, countdown et fermeture automatique fiable.
- power: Normalise les requêtes vers /dashboard/power et gère les réponses JSON/erreurs HTTP proprement (plus de pages HTML 404/500 affichées dans la modale).
- power: Évite les doubles listeners et fuites (timers/AbortController nettoyés), ESC inactif pendant l’exécution, focus sur “Fermer” quand disponible.
- backend: DashboardController@power renvoie JSON pour GET (405) et mappe mieux les erreurs (script manquant vs droits sudo), PowerService bascule vers reboot/shutdown direct si le script est absent.

### Changed
- header: inclut power.js (une seule fois) après app.js; l’ancien startReboot.js est désactivé.

## [1.0.0] - 2025-08-31
### Added
- Première version stable du **Mini Web Panel** 🎉
- Gestion complète des sites (CRUD, activation/désactivation, suppression)
- Gestion multi PHP-FPM (8.2 / 8.3 / 8.4)
- Authentification + gestion utilisateurs
- Sécurité (CSRF, sudoers limités, audit log)
- UI responsive + mode mobile
- Scripts CLI (php_manage, site_add, site_delete, power, sysinfo)
- Documentation complète avec captures d’écran

### Fixed
- Amélioration du `.gitignore` pour éviter de versionner DB & logs
- Correction messages d’erreur sur création de site

### Notes
⚠️ Identifiants initiaux : `admin / admin` (à changer après installation).