<?php
namespace App\Middlewares;

class AuthMiddleware {
    public static function handle(): void {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $asset = str_starts_with($path, '/public/');
        $allow = ['/login','/login.php','/logout','/logout.php'];
        if ($asset || in_array($path, $allow, true)) { return; }
        if (!function_exists('is_logged_in')) {
            return; // compatibility: let legacy include enforce
        }
        if (!is_logged_in()) { header('Location: /login.php'); exit; }
    }
}
