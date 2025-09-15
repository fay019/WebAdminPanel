<?php
// Local active state logic (strict version)
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$active = function (string $p) use ($path): string {
    if ($p === '/dashboard') return $path === '/dashboard' ? 'active' : '';
    return ($path === $p || str_starts_with($path, $p . '/')) ? 'active' : '';
};

// Determine auth state if not provided by including context
if (!isset($loggedIn)) {
    $loggedIn = function_exists('is_logged_in') ? is_logged_in() : false;
}
?>
<nav id="mainNav" class="nav">
    <?php if ($loggedIn): ?>
        <a href="/dashboard" class="nav-link <?= $active('/dashboard') ?>" title="Dashboard" aria-label="Dashboard">
            <svg class="nav-ico" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M3 12l9-9 9 9M5 10v10h14V10" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6"/>
            </svg>
            <span>Dashboard</span>
        </a>

        <a href="/dashboard/error-log" class="nav-link <?= $active('/dashboard/error-log') ?>" title="Error Log" aria-label="Error Log">
            <svg class="nav-ico" viewBox="0 0 24 24" aria-hidden="true">
                <!-- icône "alerte" simple -->
                <path d="M12 9v4M12 17h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6"/>
            </svg>
            <span>Error Log</span>
        </a>

        <a href="/php/manage" class="nav-link <?= $active('/php') ?>" title="Système" aria-label="Système">
            <svg class="nav-ico" viewBox="0 0 24 24" aria-hidden="true">
                <!-- icône "server" -->
                <path d="M4 6h16v4H4zM4 14h16v4H4zM6 8h.01M6 16h.01" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6"/>
            </svg>
            <span>Système</span>
        </a>

        <a href="/sites" class="nav-link <?= $active('/sites') ?>" title="Sites" aria-label="Sites">
            <svg class="nav-ico" viewBox="0 0 24 24" aria-hidden="true" >
                <!-- icône "globe" -->
                <path d="M12 21a9 9 0 100-18 9 9 0 000 18zm0-18c3 3 3 15 0 18m0-18c-3 3-3 15 0 18M3 12h18" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6"/>
            </svg>
            <span>Sites</span>
        </a>

        <a href="/users" class="nav-link <?= $active('/users') ?>" title="Utilisateurs" aria-label="Utilisateurs">
            <svg class="nav-ico" viewBox="0 0 24 24" aria-hidden="true">
                <!-- Icône groupe (2 silhouettes) -->
                <path d="M17 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"
                      stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6"/>
                <circle cx="9" cy="7" r="4"
                        stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" fill="none"/>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"
                      stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6"/>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"
                      stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6"/>
            </svg>
            <span>Utilisateurs</span>
        </a>

        <a href="/account" class="nav-link <?= $active('/account') ?>" title="Compte" aria-label="Compte">
            <svg class="nav-ico" viewBox="0 0 24 24" aria-hidden="true">
                <!-- Icône utilisateur simple -->
                <path d="M12 12a5 5 0 1 0-5-5 5 5 0 0 0 5 5Z"
                      stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6"/>
                <path d="M3 21a9 9 0 1 1 18 0"
                      stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6"/>
            </svg>
            <span>Compte</span>
        </a>

        <a class="btn" href="/sites/create" title="Nouveau site" aria-label="Nouveau">
            <svg class="nav-ico" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M12 5v14M5 12h14" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6"/>
            </svg>
            <span>Nouveau</span>
        </a>

        <a class="btn danger ml-auto" href="/logout?_csrf=<?= htmlspecialchars($_SESSION['csrf'] ?? csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" title="Déconnexion" aria-label="Déconnexion">
            <svg class="nav-ico" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6"/>
            </svg>
            <span>Déconnexion</span>
        </a>
    <?php endif; ?>

    <div class="srv-led" id="srv-led" title="État du serveur (via /api/sysinfo)" aria-live="polite" aria-atomic="true">
        <span class="srv-led-dot" aria-hidden="true"></span>
        <span class="srv-led-label">Serveur: Inconnu</span>
    </div>
</nav>
