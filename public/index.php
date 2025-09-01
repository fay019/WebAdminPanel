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

// Middlewares minimal: Auth (except login/logout and assets), CSRF on POST
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$publicPaths = ['/login.php','/logout.php','/public/','/public'];
$asset = str_starts_with($path, '/public/');
if (!$asset && !in_array($path, ['/login','/login.php','/logout','/logout.php'], true)) {
    if (!function_exists('is_logged_in') || !is_logged_in()) { header('Location: /login.php'); exit; }
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (function_exists('csrf_check')) { csrf_check(); }
}

$router = new Router($routes);
$router->dispatch();
