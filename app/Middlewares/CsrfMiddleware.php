<?php
namespace App\Middlewares;

class CsrfMiddleware {
    public static function handle(): void {
        // CSRF uniquement pour POST
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method !== 'POST') {
            return;
        }

        // S'assurer que la session est ouverte
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        // Token stocké côté session (accepter plusieurs clés)
        $sessionToken =
            $_SESSION['csrf_token'] ??
            $_SESSION['csrf'] ??
            $_SESSION['_csrf'] ??
            '';

        // Token fourni par le client : POST (plusieurs noms) ou Header
        $postedToken =
            $_POST['_csrf'] ??
            $_POST['_token'] ??
            $_POST['csrf'] ??
            $_POST['token'] ??
            '';

        $headerToken =
            $_SERVER['HTTP_X_CSRF_TOKEN'] ??
            $_SERVER['HTTP_X_CSRFTOKEN'] ??
            '';

        $provided = $postedToken !== '' ? $postedToken : $headerToken;

        if (!is_string($provided) || $provided === '' ||
            !is_string($sessionToken) || $sessionToken === '' ||
            !hash_equals($sessionToken, $provided)) {
            http_response_code(400);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'CSRF token invalid.';
            exit;
        }
    }
}
