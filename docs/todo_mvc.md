# TODO (MVC gaps) — 2025-09-04

This checklist captures what is still missing or inconsistent in the MVC migration and proposes concrete next steps.

## 1) Sites module (major)
- Missing SitesController + Views (index/create/edit/show) and Service layer.
- Migrate legacy endpoints: `sites_list.php`, `site_new.php`, `site_edit.php`, `site_toggle.php`, `site_delete.php` to MVC routes.
- Update `config/routes.php` with REST-like routes `/sites`, `/sites/create`, `/sites/{id}`, `/sites/{id}/edit`, POST endpoints for create/update/delete/toggle, and legacy redirects.
- Extract shell logic now spread across `bin/site_*.sh` calls into a dedicated `App\Services\SitesService`.
- Update navigation (header) to point to `/sites` instead of legacy `sites_list.php`.

## 2) Account page (medium)
- Introduce `AccountController` with actions to edit current user profile and password (separate from admin Users CRUD).
- Create views `app/Views/account/edit.php` and route `/account` (GET/POST); redirect legacy `/account.php` to `/account`.

## 3) Auth and Logout links (small)
- Replace legacy `/logout.php` link with `/logout?_csrf={token}` or make the link a POST button with CSRF.
- Ensure `app/Views/partials/header.php` renders a CSRF token for logout action.

## 4) I18n normalization (medium)
- There are two i18n trees: `lang/*` (used by App\Helpers\I18n) and `locales/*` (documented in README). Decide on one.
  - Option A (recommended): switch loader to `locales/` and move `lang/*` keys into `locales/`.
  - Option B: keep `lang/` and remove `locales/` from README and tree.
- Audit all `__('key')` usages; create a keys map and fill missing translations.

## 5) Views and partials duplication (small)
- We have legacy `partials/*.php` and MVC `app/Views/partials/*.php`. Align helpers so MVC views only require MVC partials.
- Remove direct includes to root `partials/` from `public/index.php` and controllers, replacing with MVC equivalents (Response + layout).

## 6) JS endpoints consistency (small)
- Verify `public/js/*.js` call only MVC routes.
  - `php_manage.js` is OK (uses `/php/manage` endpoints).
  - Audit `energy.js`, `tables.js`, any site-related JS: update to MVC endpoints after SitesController is added.

## 7) System/Power legacy routes (small)
- `DashboardController@power` currently accepts legacy `/system_power.php`. Add GET routes for a friendly confirmation page and keep POST for action.
- Optional: create a small view for power actions instead of relying solely on dashboard buttons.

## 8) Error pages and HTTP semantics (small)
- 404 view exists. Add a simple 405 view or reuse 50x page for method not allowed.
- Ensure JSON 404/405 responses are consistent (already mostly handled by Router).

## 9) Middlewares hardening (small)
- `AuthMiddleware`: whitelist for assets is OK. Consider allowing `/favicon.ico` and `/robots.txt`.
- `CsrfMiddleware`: good coverage; add SameSite/secure flags management in session cookie in a separate place if needed.
- `FlashMiddleware`: placeholder — evaluate moving flash lifecycle fully into middleware later.

## 10) Logging and audit (small)
- Standardize calls to `audit()` across controllers (Users, Sites, System, PhpManage).
- Extract `audit()` into `App\Helpers\Audit` for namespacing instead of global in `lib/auth.php`.

---

# Plan for tomorrow (prioritized)

1. Sites MVC (controller, views, routes, service) — skeleton only (list + create form) [4–6h]
   - Create `SitesController` with `index()` listing from SQLite (reuse existing `lib/db.php`).
   - Create views: `app/Views/sites/index.php`, `app/Views/sites/create.php` minimal.
   - Add routes: GET `/sites`, `/sites/create`; legacy GET redirects from `sites_list.php` and `site_new.php`.
   - Update header nav to `/sites`.
2. Logout POST with CSRF [0.5h]
   - Replace link by form POST in header; keep GET route with CSRF query for direct URL compatibility.
3. I18n decision and doc update [0.5h]
   - Decide to keep `lang/` for now; update README to remove `locales/` mention OR update I18n loader to `locales/`.
4. Create placeholder AccountController + route + view [1h]
   - Route GET `/account` rendering current user info; link header to `/account`.
5. JS audit quick pass [0.5h]
   - Confirm `energy.js` hits `/energy/*` endpoints; no changes unless mismatch found.

Stretch goals (if time left):
- Add POST endpoints for Sites create/update and wire minimal validation.
- Add 405 error view.

---

## Notes
- Keep legacy files for now but ensure they 302 to MVC counterparts to avoid breaking existing bookmarks.
- Avoid large refactors; focus on introducing a minimal MVC surface for sites and account to reduce reliance on legacy.
