# Audit rapide — Module Système (power) après migration MVC

Contexte: Debian 12, Nginx 1.22.1, PHP‑FPM 8.3/8.4. Le module extinction/redémarrage fonctionnait avant la migration. Après migration MVC, des 404/405 apparaissent et un message trop visible autour de boot_id.

1) Endpoints réellement câblés
- POST /dashboard/power → DashboardController@power (MVC).
- POST /system_power.php → DashboardController@power (alias legacy).
- AVANT correction: pas de route GET explicite pour ces chemins, donc GET → 404/405 souvent avec HTML.
- APRÈS correction: GET /dashboard/power et GET /system_power.php → DashboardController@powerMethodNotAllowed (JSON 405).

2) Acheminement/Front Controller
- public/index.php charge AuthMiddleware + CsrfMiddleware + Router; dispatch se fait via config/routes.php.
- Router renvoie 405/404 avec page HTML si l’en‑tête Accept n’indique pas JSON → source des retours non JSON.

3) Contrôleur Système (DashboardController)
- Méthodes existantes: index(), api(), sysinfo(), power().
- AVANT: power() mélangeait flux legacy (text/plain ajax=1) et flash+redirect HTML.
- APRÈS: power() renvoie JSON immédiat (ok/message/raw) pour POST; stream=1 continue de faire un passthrough; ajout powerMethodNotAllowed() (JSON 405) pour GET.
- Auth admin et CSRF: gérés globalement par les middlewares existants (AuthMiddleware et CsrfMiddleware).

4) Service Power (PowerService)
- Exécute bin/power.sh via sudo, avec mode streaming (passthru) ou non.
- Réponses du script: "OK: ..." ou "ERR: ..."; côté contrôleur, on normalise en JSON.
- Exécution asynchrone suffisante (le shell renvoie immédiatement après déclenchement).

5) JS actuel (avant correction)
- La logique power était dans public/js/app.js (overlay, envoi, lecture boot_id, compte à rebours).
- Envoi vers URL legacy /system_power.php?stream=1 avec Accept: text/plain.
- Message UI bruyant si boot_id introuvable.

6) Points de friction identifiés
- GET sur /system_power.php ou /dashboard/power → 404/405 HTML (liens/JS non interceptés).
- JS pointait une URL legacy en dur et attendait du texte, pas du JSON.
- Message UI anxiogène sur boot_id manquant.

7) Plan de correction minimal (appliqué)
- Routage: ajouter GET /dashboard/power et /system_power.php retournant JSON 405; garder POST sur les deux.
- Contrôleur: JSON‑only pour POST, pas de redirect HTML; stream=1 conservé; erreurs explicites en JSON.
- Extraction JS: déplacer toute la logique power dans public/js/startReboot.js; paramétrer l’endpoint via window.POWER_ENDPOINT.
- UI boot_id: rendre l’absence silencieuse (aucun message bruyant en UI; log debug console seulement).
- app.js: retirer la section power; maintenir l’événement custom power:submit.
- Intégration: inclure startReboot.js après app.js; définir window.POWER_ENDPOINT = '/dashboard/power' dans la vue dashboard.
- install.sh: copier startReboot.js parmi les assets front.

8) Sécurité (vérifications)
- AuthMiddleware protège tout sauf login/logout; CsrfMiddleware valide les POST via token (POST/headers).
- Routes power: POST uniquement pour l’action; GET retourne JSON 405; aucune page HTML/redirect.

9) Tests manuels (checklist)
- POST /dashboard/power (action=shutdown|reboot): JSON {ok:true,message,...} immédiat; pas de 404/HTML.
- GET sur /dashboard/power et /system_power.php: JSON {ok:false,error:"method_not_allowed"} (405).
- Sans Auth/CSRF: redirection login (Auth) ou 400 CSRF avant exécution.
- UI: overlay s’ouvre, messages clairs, compte à rebours OK; reboot → reload auto, shutdown → message final sans reload auto.
- Aucun message bruyant si boot_id indisponible.
- Alias legacy /system_power.php: même JSON que la route MVC.
