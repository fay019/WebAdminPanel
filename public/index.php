<?php
declare(strict_types=1);
// Front controller

// === Error Reporting global (prod ready) ===
error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('display_errors', '0');
// Centralized error log path
ini_set('error_log', '/srv/www/webadminpanel-v2/logs/error.log');

// === Logger minimal (utilitaire interne) ===
final class ErrorLogger {
    public static function log(string $level, string $message, array $ctx = []): void {
        $ts = date('Y-m-d H:i:s');
        $rid = self::requestId();
        $safe = [];
        foreach ($ctx as $k => $v) {
            // Filter to avoid logging secrets and non-scalar data as-is
            if (is_scalar($v) || $v === null) {
                $safe[$k] = (string)$v;
            } else {
                $safe[$k] = json_encode($v, JSON_UNESCAPED_UNICODE);
            }
        }
        $line = sprintf('[%s] [%s] [%s] %s | ctx=%s', $ts, $level, $rid, $message, json_encode($safe, JSON_UNESCAPED_UNICODE));
        error_log($line);
    }
    public static function requestId(): string {
        static $id = null;
        if ($id === null) {
            try { $id = bin2hex(random_bytes(8)); } catch (Throwable $e) { $id = bin2hex((string)mt_rand()); }
        }
        return $id;
    }
}
// Expose request id to clients for correlation
if (!headers_sent()) {
    header('X-Request-Id: ' . ErrorLogger::requestId());
}

// === Handlers centralisés ===
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) return false; // respect @-suppressed
    ErrorLogger::log('php_error', $message, ['file'=>$file,'line'=>$line,'sev'=>$severity]);
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(function ($ex) {
    ErrorLogger::log('php_exception', $ex->getMessage(), [
        'file'=>$ex->getFile(), 'line'=>$ex->getLine(), 'code'=>$ex->getCode(),
        'trace'=> substr($ex->getTraceAsString(), 0, 4000)
    ]);
    http_response_code(500);
    if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>false,'error'=>'internal_error','message'=>'Une erreur est survenue.'], JSON_UNESCAPED_UNICODE);
});

register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        ErrorLogger::log('php_fatal', $err['message'], ['file'=>$err['file'], 'line'=>$err['line'], 'type'=>$err['type']]);
    }
});

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
