<?php
namespace App\Controllers;

use App\Helpers\Response;

class AuthController {
    private function ensureMigrate(): void { require_once __DIR__.'/../../lib/db.php'; migrate(); }

    private function getCsrfFromPost(): string {
        return (string)($_POST['_csrf'] ?? ($_POST['csrf'] ?? ''));
    }
    private function getCsrfFromQuery(): string {
        return (string)($_GET['_csrf'] ?? ($_GET['csrf'] ?? ''));
    }
    private function csrfToken(): string {
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
        if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
        return $_SESSION['csrf'];
    }
    private function checkCsrf(string $token): bool {
        $session = $_SESSION['csrf'] ?? '';
        return is_string($token) && $token !== '' && is_string($session) && hash_equals($session, $token);
    }

    public function loginForm(): void {
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
        require_once __DIR__.'/../../partials/flash.php';
        $this->ensureMigrate();
        // generate CSRF
        $this->csrfToken();
        Response::view('auth/login', []);
    }

    public function login(): void {
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
        require_once __DIR__.'/../../lib/db.php';
        require_once __DIR__.'/../../partials/flash.php';
        $this->ensureMigrate();

        // CSRF check (POST)
        $token = $this->getCsrfFromPost();
        if (!$this->checkCsrf($token)) {
            http_response_code(400);
            echo 'CSRF token invalid.';
            return;
        }

        $u = trim((string)($_POST['username'] ?? ''));
        $p = (string)($_POST['password'] ?? '');
        if ($u === '' || $p === '') {
            flash('err', 'Identifiants invalides.');
            Response::redirect('/login');
        }
        $st = db()->prepare('SELECT * FROM users WHERE lower(username) = lower(:u) LIMIT 1');
        $st->execute([':u'=>$u]);
        $row = $st->fetch();
        if ($row && password_verify($p, $row['password_hash'])) {
            session_regenerate_id(true);
            // Keep legacy compatibility: store username string in session
            $_SESSION['user'] = $row['username'];
            $_SESSION['user_id'] = (int)$row['id'];
            require_once __DIR__.'/../../lib/auth.php';
            audit('login', ['user'=>$row['username']]);
            Response::redirect('/');
        } else {
            flash('err', 'Identifiants invalides.');
            Response::redirect('/login');
        }
    }

    public function logout(): void {
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
        require_once __DIR__.'/../../partials/flash.php';
        // POST is recommended and already CSRF-checked by CsrfMiddleware; ensure GET also requires CSRF
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $ok = false;
        if ($method === 'POST') {
            // CsrfMiddleware would have validated, but double-check for safety
            $ok = $this->checkCsrf($this->getCsrfFromPost());
        } else { // GET
            $ok = $this->checkCsrf($this->getCsrfFromQuery());
        }
        if (!$ok) {
            http_response_code(400);
            echo 'CSRF token invalid.';
            return;
        }
        // Clear and destroy session
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        Response::redirect('/login');
    }
}
