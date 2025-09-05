# Audit — Centralizing /api/sysinfo polling

Context
- Multiple scripts referenced sysinfo: app.js (dashboard metrics), startReboot.js (boot_id / online checks), layout/view globals (SYSINFO_URL).
- Goal: single canonical poller in public/js/sysinfo.js. All consumers must subscribe to events or read shared state, no duplicate fetch/interval.

Search findings
- /api/sysinfo references:
  - app/Views/dashboard/index.php defines window.SYSINFO_URL = '/api/sysinfo'.
  - config/routes.php maps GET /api/sysinfo → DashboardController@api.
- Duplicates identified before change:
  - public/js/app.js: had its own setInterval fetching SYSINFO_URL every 2s and updating #cpuTempVal, #ramVal, #cpuLoadVal.
  - public/js/startReboot.js: performed a direct fetch to SYSINFO_URL to read boot_id before power flow.
  - public/js/energy.js: does NOT touch /api/sysinfo (it uses /api/energy/*), so no duplication there.

Changes applied
- Created public/js/sysinfo.js as the single poller:
  - Polls /api/sysinfo at configurable cadence; parses JSON with key=value fallback.
  - Maintains window.SYSINFO_LAST_TS and window.SYSINFO_LAST_DATA.
  - Emits CustomEvent("sysinfo:update", { detail: { data, at } }) on success, and "sysinfo:error" on failures.
  - Updates dashboard metrics (CPU Temp, RAM, CPU Load) if elements are present.
  - Respects Page Visibility (slower when hidden) and limits UI noise (show n/a after N consecutive failures).
  - Includes a singleton guard window.__SYSINFO_POLLING__ to prevent starting twice.
- public/js/app.js:
  - Removed the entire sysinfo polling block. app.js no longer fetches /api/sysinfo.
- public/js/startReboot.js:
  - Removed any direct fetch of SYSINFO_URL. Now uses window.SYSINFO_LAST_DATA for boot_id (if available) and subscribes to sysinfo:update to detect reboot completion (online comeback) before reloading.
  - Keeps UI/UX identical and remains discrete if boot_id is unavailable.
- app/Views/partials/header.php:
  - Included sysinfo.js after app.js and before startReboot.js to ensure consumers can subscribe to events/state.
- install.sh:
  - Added copying of public/js/sysinfo.js to the target public/js directory.

Definition of done — validation steps
1) Network: DevTools → filter "sysinfo" → observe exactly one repeating request from sysinfo.js; no parallel pollers across navigations.
2) Code: Only sysinfo.js contains interval/fetch to /api/sysinfo; app.js and startReboot.js contain no direct sysinfo fetch.
3) Functional: Dashboard metrics still update; reboot/shutdown flows rely on sysinfo.js events/state; no added polling.
4) Robustness: Reload and navigate between sections → still one poller; break network temporarily → polling resumes without duplication; singleton guard prevents double starts.

Final responsibilities
- sysinfo.js: single source of sysinfo data, events, and UI updates.
- app.js: general UI logic (confirmations, modals, navigation) — no sysinfo polling.
- startReboot.js: power overlay and flows, listens to sysinfo events/state for online detection.
- energy.js: independent energy endpoints, does not touch sysinfo.
