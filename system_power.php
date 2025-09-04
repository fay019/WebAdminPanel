<?php
declare(strict_types=1);
// Legacy power endpoint moved under MVC routing.
// Keep bookmarks and existing JS/forms working by redirecting GET to the dashboard.
// POST requests are already handled by the Router mapping to DashboardController@power.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: /dashboard', true, 302);
    exit;
}
// For POST, do nothing here: public/index.php + Router will dispatch '/system_power.php' to DashboardController@power.
// This file intentionally contains no logic anymore.
exit;