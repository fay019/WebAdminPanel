# Migration vers MVC — Étape 1

Branche: feature/mvc-refactor (à créer dans Git avant de pousser ces changements)

Objectifs de cette étape:
- Introduire un front controller (public/index.php)
- Ajouter un Router minimal (app/Helpers/Router.php) avec table de routes (config/routes.php)
- Migrer la page Dashboard en Controller + View, sans changer l’UX ni les endpoints AJAX/POST
- Conserver l’auth, CSRF, flash existants via les librairies actuelles (lib/)
- Préparer la suite (Services, Middlewares dédiés, autres pages)

## Arborescence ajoutée

- public/index.php (front controller)
- app/Helpers/Router.php (routeur minimal)
- app/Helpers/Response.php (rend les vues avec layout + JSON)
- app/Controllers/DashboardController.php (nouveau contrôleur)
- app/Views/layouts/layout.php (layout qui réutilise partials/header.php & footer.php)
- app/Views/dashboard/index.php (vue dashboard extraite)
- config/routes.php (table de routage)
- docs/migration.md (ce document)

## Routage

config/routes.php
- GET / → DashboardController@index
- GET /dashboard → DashboardController@index
- GET /dashboard.php → redirection 302 → /dashboard (legacy temporaire)
- GET /dashboard.php?ajax=sysinfo → DashboardController@sysinfo (legacy AJAX conservé)
- GET /dashboard?ajax=sysinfo → DashboardController@sysinfo (pour la nouvelle URL)
- POST /dashboard/power → DashboardController@power
- POST /system_power.php → DashboardController@power (compat avec l’URL historique)
- GET /lang?set=fr|en → I18nController@set (AJAX simple pour changer la langue via cookie/session)

Le Router renvoie 404/405 explicites:
- 404: Response::view('errors/404') si possible, sinon public/404.html
- 405: envoie l’en-tête Allow et une réponse JSON si Accept=application/json

Remarque: les assets restent sous /public/… (inchangés).

## Middlewares (étape minimale)

Dans public/index.php:
- Auth: redirige vers /login.php si non connecté (sauf assets et login/logout)
- CSRF: vérification sur POST via lib/csrf.php (inchangé)
- Flash: via partials/flash.php (inchangé)

Des Middlewares dédiés (app/Middlewares/AuthMiddleware.php, CsrfMiddleware.php) seront introduits à l’étape suivante et branchés dans le front.

## Dashboard

- Controller: App/Controllers/DashboardController.php
  - index(): lit le nombre de sites, interroge sysinfo.sh (même chemin /var/www/adminpanel/bin/sysinfo.sh avec fallback local), et prépare les mêmes variables utilisées par la vue.
  - sysinfo(): renvoie la sortie JSON de bin/sysinfo.sh, identique au legacy (pas de changement de format).
  - power(): déclenche bin/power.sh reboot|shutdown et renvoie la même sortie (mode stream supporté si `?stream=1`).

- View: app/Views/dashboard/index.php
  - HTML/JS identiques à dashboard.php (déplacés). `window.SYSINFO_URL` reste `/dashboard.php?ajax=sysinfo` pour 0 régression.
  - Les liens power pointent toujours vers `/system_power.php?stream=1` et sont soumis en POST par le JS existant (data-confirm conservé).

- Layout: app/Views/layouts/layout.php
  - Réutilise partials/header.php et partials/footer.php pour garder le style et les scripts existants.

## Compatibilité legacy

- Les fichiers legacy ne sont pas supprimés. /dashboard.php continue d’exister.
- Le routeur sait rediriger GET /dashboard.php → /dashboard lorsqu’on passe par le front (public/index.php), et mappe POST /system_power.php → action power() pour conserver l’endpoint historique.

## Étapes suivantes

1) Créer Middlewares dédiés (AuthMiddleware, CsrfMiddleware) et les brancher dans public/index.php.  
2) Migrer `php_manage.php` → SystemController@phpManage + vues. Streaming identique.  
3) Migrer Sites (list/new/edit/delete) en conservant les mêmes URLs/params pour l’AJAX et les formulaires.  
4) Migrer Users (list/new/edit) avec validations et notes.  
5) Introduire Models (User, Site) pour encapsuler l’accès DB.  
6) Ajouter config/app.php, config/database.php, storage/ (logs, cache).  
7) Préparer l’i18n: lang/fr.php, lang/en.php et helper __() (déjà ajoutés, non utilisés par défaut).  
8) Remplacer progressivement les inclusions legacy par des Helpers/Middlewares/Services sans changer le comportement.  
9) Une fois chaque page validée, mettre en place les redirections définitives depuis les URLs legacy, puis supprimer les fichiers legacy en fin de migration.

## Conventions contrôleurs

- Nom de fichier: NomController.php (ex: DashboardController.php)
- Classe: App\\Controllers\\NomController
- Méthodes d’actions: camelCase (ex: index, sysinfo, power)

## Git

- Créer la branche: `git checkout -b feature/mvc-refactor`
- Commiter petit à petit pour permettre des tests à chaque étape.

