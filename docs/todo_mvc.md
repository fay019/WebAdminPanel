# TODO (Migration MVC) — 2025-09-04

Checklist avec cases à cocher. Les éléments déjà réalisés sont cochés, y compris l’historique.

## 0) Historique — déjà fait
- [x] Router MVC avec front controller `public/index.php`
- [x] Mise en place des middlewares Auth + CSRF
- [x] Module Utilisateurs (UsersController + vues index/create/edit/show)
- [x] Module PhpManage (contrôleur + service + vues + routes GET/POST + compat legacy)
- [x] Dashboard + API sysinfo (stream/ajax), Energy toggles de base
- [x] Layout MVC + partials (header/footer/flash)

## 1) Sites module (major)
- [x] Créer SitesController + Vues (index/create/edit/show) et Service layer
- [x] Migrer les endpoints legacy `sites_list.php`, `site_new.php`, `site_edit.php`, `site_toggle.php`, `site_delete.php` → routes MVC (+ redirections)
- [x] Mettre à jour `config/routes.php` avec routes REST-like `/sites`, `/sites/create`, `/sites/{id}`, `/sites/{id}/edit` et POST create/update/delete/toggle
- [x] Extraire la logique shell `bin/site_*.sh` dans `App/Services/SitesService`
- [x] Mettre à jour la navigation vers `/sites`
- [x] Ajouter section « Dossiers orphelins » avec suppression via POST `/orphan/delete`
- [x] Ajouter bouton « Supprimer + dossier » (supprime site + répertoire)

## 2) Page Compte (medium)
- [ ] Introduire `AccountController` (édition profil/mot de passe)
- [ ] Créer `app/Views/account/edit.php` et route `/account` (GET/POST)
- [ ] Rediriger legacy `/account.php` → `/account`

## 3) Auth et Déconnexion (small)
- [ ] Remplacer le lien `/logout.php` par un POST avec CSRF (ou `/logout?_csrf=...`)
- [ ] Rendre le token CSRF accessible dans `app/Views/partials/header.php` pour logout

## 4) Normalisation i18n (medium)
- [ ] Choisir entre `lang/*` et `locales/*` et unifier le loader
  - [ ] Option A: basculer vers `locales/` et migrer les clés de `lang/`
  - [ ] Option B: conserver `lang/` et supprimer `locales/` des docs
- [ ] Auditer tous les `__('key')` et compléter les traductions

## 5) Doublons vues/partials (small)
- [ ] Éliminer les includes legacy `partials/*.php` au profit de `app/Views/partials/`
- [ ] Nettoyer `public/index.php` et contrôleurs pour n’utiliser que `Response` + layout MVC

## 6) Cohérence endpoints JS (small)
- [x] Vérifier `php_manage.js` (OK: `/php/manage`)
- [ ] Auditer `energy.js`, `tables.js` et éventuels JS liés aux sites après migration complète

## 7) Système/Power (small)
- [ ] Ajouter routes GET conviviales pour power (confirm), garder POST action
- [ ] (Optionnel) Vue dédiée pour power actions

## 8) Pages d’erreur / sémantique HTTP (small)
- [ ] Ajouter une vue 405 simple (ou réutiliser 50x)
- [ ] Harmoniser réponses JSON 404/405 (vérifier Router)

## 9) Middlewares (small)
- [ ] AuthMiddleware: autoriser `/favicon.ico`, `/robots.txt`
- [ ] CsrfMiddleware: vérifier SameSite/Secure des cookies de session
- [ ] FlashMiddleware: envisager la gestion complète du lifecycle

## 10) Journalisation / audit (small)
- [ ] Standardiser `audit()` dans tous les contrôleurs
- [ ] Extraire `audit()` vers `App/Helpers/Audit`

---

# Plan (prochaines étapes)

1. Auth/Logout sécurisé (POST + CSRF) [0.5h]
2. Page Compte (GET/POST) [1h]
3. Décision i18n + MAJ README [0.5h]
4. JS audit rapide (`energy.js`) [0.5h]
5. 405 view (optionnel) [0.5h]

Notes:
- Les fichiers legacy restent mais redirigent (302) vers leurs équivalents MVC.
- Éviter les gros refactors; consolider progressivement.
