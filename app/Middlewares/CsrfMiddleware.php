<?php
namespace App\Middlewares;

class CsrfMiddleware {
    public static function handle(): void {
        // 1) CSRF uniquement pour les POST
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method !== 'POST') {
            return;
        }

        // 2) S'assurer que la session est ouverte (sinon $_SESSION est vide)
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        // 3) Token en session : accepter plusieurs clés possibles
        $sessionToken =
            $_SESSION['csrf_token'] ??
            $_SESSION['csrf'] ??
            $_SESSION['_csrf'] ??
            '';

        // 4) Token fourni : POST (plusieurs noms acceptés) ou Header
        $postedToken =
            $_POST['_token'] ??
            $_POST['csrf'] ??
            $_POST['token'] ??
            '';

        $headerToken =
            $_SERVER['HTTP_X_CSRF_TOKEN'] ??
            $_SERVER['HTTP_X_CSRFTOKEN'] ??
            '';

        $provided = $postedToken !== '' ? $postedToken : $headerToken;

        // 5) Validation constante-time
        if (!is_string($provided) || $provided === '' || !is_string($sessionToken) || $sessionToken === '' || !hash_equals($sessionToken, $provided)) {
            http_response_code(400);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'CSRF token invalid.';
            exit;
        }
    }
}