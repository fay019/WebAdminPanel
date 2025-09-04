# Migration vers MVC — Étape 1–2

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

## Middlewares centralisés

Pipeline dans public/index.php (sans changement de comportement):
- App\Middlewares\AuthMiddleware::handle() — redirige vers /login.php si non connecté (sauf assets et login/logout)
- App\Middlewares\CsrfMiddleware::handle() — vérification CSRF sur POST via lib/csrf.php
- App\Middlewares\FlashMiddleware::handle() — no-op pour l’instant; placeholder pour homogénéiser la pile

Fichiers:
- app/Middlewares/AuthMiddleware.php
- app/Middlewares/CsrfMiddleware.php
- app/Middlewares/FlashMiddleware.php

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

## Internationalisation (i18n) — Étape 2

- Helper: App/Helpers/I18n.php expose __($key, $replacements=[]).
- Dictionnaires: lang/fr.php, lang/en.php (quelques clés de test).
- Persistance: session + cookie 'locale' pendant 1 an.
- Endpoint AJAX: GET /lang?set=fr|en → I18nController@set, renvoie {ok:true, locale:"fr"}. Pas de rechargement nécessaire.
- Utilisation: laisser les textes en dur pour l’instant; on peut tester __() sur 2–3 libellés.

## Migration PhpManage (nouveau module MVC)

- Contrôleur: App/Controllers/PhpManageController.php
  - index(): liste les versions via service et rend la vue views/php_manage/index.php
  - runAction(): POST non-stream; exécute la commande en synchrone, flash + redirect vers /php/manage
  - streamAction(): POST stream; renvoie text/plain et stream le log jusqu’à la fin
  - legacyPost(): route POST /php_manage.php; si ajax=1 ou stream=1 ⇒ streamAction(), sinon ⇒ runAction()
- Service: App/Services/PhpManageService.php
  - resolveBinary(): résout le chemin du script (/var/www/adminpanel/bin/php_manage.sh ou ./bin/php_manage.sh)
  - listVersions(): exécute sudo -n <bin> list --json et retourne le tableau décodé
  - buildCommand(action, ver): construit la commande sudo -n <bin> action ver (échappement)
  - runSync(cmd): exécute la commande (stderr→stdout) et renvoie la sortie brute
  - streamCommand(cmd): lance proc_open non bloquant et stream stdout/err, keep-alive "." toutes ~2s; fin: "-- Fin (code X) --" + [OK]/[ERREUR]
  - Compat: méthodes listJson()/execute() existent encore et déléguent aux nouvelles
- Vue: app/Views/php_manage/index.php copie la structure/IDs/classes du legacy (table classes, badges, overlay #busyOverlay/#busyTitle/#busyHint/#busyLog/#busyClose). Inclut partials/header/footer via layout et charge /js/php_manage.js.
- Client JS: public/js/php_manage.js gère les actions avec data-stream="1" en overlay (fetch + ReadableStream) et garde ajax=1.
- Routage (config/routes.php):
  - GET  /php/manage → PhpManageController@index
  - POST /php/manage/action → PhpManageController@runAction
  - POST /php/manage/stream → PhpManageController@streamAction
  - Compat legacy: GET /php_manage.php → 302 /php/manage ; POST /php_manage.php → PhpManageController@legacyPost
- Sécurité: AuthMiddleware protège les routes; CsrfMiddleware s’applique aux POST; stream renvoie text/plain, pas de HTML.

### Tests manuels
- GET /php/manage → affichage OK, mêmes hooks/markup.
- Installer 8.0 (stream) → overlay live log, fin [OK]/[ERREUR], bouton Fermer activé.
- Installer 8.0 (non-stream) → flash + redirect /php/manage, sortie sanitizée.
- Remove/Restart en modes stream et non-stream → OK.
- Compat legacy: GET /php_manage.php → 302 /php/manage ; POST /php_manage.php route selon ajax/stream.
- CSRF: POST sans token → 400.
- Fonctionne avec le binaire en /var/www/adminpanel/bin ou ./bin.

## Migration Users (Étape en cours)

- Contrôleur: App/Controllers/UsersController.php avec actions index/create/store/edit/update/resetPassword/destroy.
- Vues: app/Views/users/index.php (liste), create.php (création), edit.php (édition) — copies fidèles du legacy avec mêmes ids/classes.
- Générateur de mot de passe: public/js/password.js (window.generatePassword) chargé uniquement sur create/edit Users.
- Champ note: colonne TEXT "notes" conservée/ajoutée via lib/db.php::migrate(); affichage tronqué et tooltip dans la liste.
- Routage:
  - GET  /users → UsersController@index
  - GET  /users/new → UsersController@create
  - POST /users → UsersController@store
  - GET  /users/{id}/edit → UsersController@edit
  - POST /users/{id} → UsersController@update
  - POST /users/{id}/reset-password → UsersController@resetPassword
  - POST /users/{id}/delete → UsersController@destroy
  - Compat legacy: GET /users_list.php → 302 → /users ; GET /user_new.php → 302 → /users/new ; GET /user_edit.php?id=… → UsersController@edit ; POST /user_edit.php → UsersController@update ; POST /users_list.php → UsersController@destroy
- CSRF/Flash/Auth inchangés (middlewares + partials).
- i18n: utilisation de __() pour 1–2 libellés (titre Users), le reste inchangé.

### Tests manuels Users
- Liste: même colonnes/UX; bouton Éditer; suppression avec data-confirm; flash OK.
- Création: validations identiques (messages exacts), flash OK, redirection vers /users.
- Édition: update OK; reset password OK (validations identiques), redirection vers /users; session mise à jour si renommage du user courant.
- Note: saisie/édition/affichage tronqué + tooltip; mobile intact.
- Générateur: clique "Générer" remplit password+confirm; aucun conflit avec public/js/app.js.
- CSRF: POST sans token → 400.

## Étapes suivantes

1) Migrer Sites (list/new/edit/delete) en conservant les mêmes URLs/params pour l’AJAX et les formulaires.  
2) Migrer Users (list/new/edit) avec validations et notes.  
3) Introduire Models (User, Site) pour encapsuler l’accès DB.  
4) Ajouter config/app.php, config/database.php, storage/ (logs, cache).  
5) Préparer l’i18n: lang/fr.php, lang/en.php et helper __() (déjà ajoutés).  
6) Remplacer progressivement les inclusions legacy par des Helpers/Middlewares/Services sans changer le comportement.  
7) Une fois chaque page validée, mettre en place les redirections définitives depuis les URLs legacy, puis supprimer les fichiers legacy en fin de migration.

## Conventions contrôleurs

- Nom de fichier: NomController.php (ex: DashboardController.php)
- Classe: App\\Controllers\\NomController
- Méthodes d’actions: camelCase (ex: index, sysinfo, power)

## Tests manuels recommandés

1) Connexion
- Aller sur /login.php, se connecter; vérifier redirection vers /dashboard.

2) Dashboard & sysinfo
- Ouvrir /dashboard via front (/) et via /dashboard.php (redirigé vers /dashboard).
- Ouvrir la console réseau: polling /dashboard.php?ajax=sysinfo doit répondre application/json.

3) Power (shutdown/reboot)
- Cliquer sur les icônes; la modale de confirmation s’ouvre; en OK, un POST est fait sur /system_power.php avec text/plain en retour (overlay alimenté), ou stream si paramètre ?stream=1.

4) CSRF
- Forcer un POST sans token doit renvoyer 400 CSRF token invalid.

5) i18n (AJAX sans reload)
- Appeler GET /lang?set=en puis vérifier que document.title passe à "Mini Web Panel" (ou valeur de lang/en.php) au prochain rendu.
- Ré-appeler /lang?set=fr; vérifier le cookie 'locale' est posé et persistant.

6) 404/405
- Requête GET vers /dashboard/power → 405 avec en-tête Allow et JSON si Accept=application/json.
- Route inconnue → 404 avec vue MVC errors/404.

## Git

- Créer la branche: `git checkout -b feature/mvc-refactor`
- Commiter petit à petit pour permettre des tests à chaque étape.

