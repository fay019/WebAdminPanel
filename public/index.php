<?php
declare(strict_types=1);
// Front controller
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

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
require_once __DIR__.'/../partials/flash.php';
// i18n helper (no-op for now in views)
require_once __DIR__.'/../app/Helpers/I18n.php';

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
