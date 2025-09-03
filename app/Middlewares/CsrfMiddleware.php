<?php
namespace App\Middlewares;

class CsrfMiddleware {
    public static function handle(): void {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method !== 'POST') {
            return; // CSRF uniquement pour les POST
        }

        $sessionToken = $_SESSION['csrf_token'] ?? '';
        $postedToken  = $_POST['_token'] ?? $_POST['csrf'] ?? $_POST['token'] ?? '';
        $headerToken  = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_SERVER['HTTP_X_CSRFTOKEN'] ?? '';

        $provided = $postedToken !== '' ? $postedToken : $headerToken;

        if (!is_string($provided) || $provided === '' || !hash_equals($sessionToken, $provided)) {
            http_response_code(400);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'CSRF token invalid.';
            exit;
        }

        if (function_exists('csrf_check')) {
            csrf_check();
        }
    }
}