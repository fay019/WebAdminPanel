<?php
declare(strict_types=1);
// Front controller
// Configure session cookie params before starting session
if (session_status() !== PHP_SESSION_ACTIVE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '80') === '443');
    $params = session_get_cookie_params();
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $params['path'] ?: '/',
        'domain' => $params['domain'] ?: '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Simple PSR-4 like autoloader for App namespace
spl_autoload_register(function($class){
    if (str_starts_with($class, 'App\\')) {
        $rel = str_replace('App\\','app/', $class);
        $path = __DIR__.'/../'.str_replace('\\','/',$rel).'.php';
        if (file_exists($path)) require $path; }
});

// Load existing libs for compatibility
require_once __DIR__.'/../lib/auth.php';
require_once __DIR__.'/../lib/csrf.php';
require_once __DIR__.'/../app/Views/partials/flash.php';
// i18n helper (no-op for now in views)
require_once __DIR__.'/../app/Helpers/I18n.php';

// Emit server_online once per boot as soon as app is reachable
try {
    \App\Services\PowerEventBus::class; // autoload available
    $bus = new \App\Services\PowerEventBus();
    $bus->publishServerOnlineIfNeeded();
} catch (\Throwable $e) { /* ignore */ }

$routes = require __DIR__.'/../config/routes.php';
require_once __DIR__.'/../app/Helpers/Router.php';

// Middlewares pipeline (centralized)
use App\Middlewares\AuthMiddleware;
use App\Middlewares\CsrfMiddleware;
use App\Middlewares\FlashMiddleware;

AuthMiddleware::handle();
CsrfMiddleware::handle();
FlashMiddleware::handle();

$router = new Router($routes);
$router->dispatch();
