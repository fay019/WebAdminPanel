<?php
namespace App\Controllers;

use App\Helpers\Response;

class AccountController {
    private function requireLogin(): void {
        require_once __DIR__ . '/../../lib/auth.php';
        require_login();
    }
    private function db() { require_once __DIR__ . '/../../lib/db.php'; return db(); }
    private function currentUser(): string { require_once __DIR__ . '/../../lib/auth.php'; return (string) current_user(); }
    private function csrfToken(): string { require_once __DIR__ . '/../../lib/csrf.php'; return csrf_token(); }
    private function csrfCheck(): void { require_once __DIR__ . '/../../lib/csrf.php'; csrf_check(); }

    public function index(): void {
        $this->requireLogin();
        // Prepare CSRF
        $this->csrfToken();
        Response::view('account/index', [
            // no extra data required; view can access session and helpers
        ]);
    }

    public function changeUsername(): void {
        $this->requireLogin();
        $this->csrfCheck();
        require_once __DIR__ . '/../../partials/flash.php';
        $me = $this->currentUser();
        $pdo = $this->db();
        // Load current user row
        $st = $pdo->prepare('SELECT * FROM users WHERE lower(username) = lower(:u)');
        $st->execute([':u'=>$me]);
        $user = $st->fetch();
        if (!$user) { http_response_code(404); echo 'Utilisateur introuvable'; return; }

        $new = trim((string)($_POST['new_username'] ?? ''));
        $err = [];
        if ($new === '' || strlen($new) < 3) { $err[] = 'Nom trop court.'; }
        if ($new !== $me) {
            $chk = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username=:u COLLATE NOCASE AND id<>:id');
            $chk->execute([':u'=>$new, ':id'=>$user['id']]);
            if ((int)$chk->fetchColumn() > 0) { $err[] = 'Nom déjà pris.'; }
        }
        if ($err) {
            flash('err', implode(' ', $err));
            Response::redirect('/account');
            return;
        }
        $pdo->prepare('UPDATE users SET username=:n WHERE id=:id')->execute([':n'=>$new, ':id'=>$user['id']]);
        require_once __DIR__ . '/../../lib/auth.php';
        audit('user.rename', ['from'=>$me,'to'=>$new]);
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
        $_SESSION['user'] = $new;
        flash('ok','Nom mis à jour.');
        Response::redirect('/account');
    }

    public function changePassword(): void {
        $this->requireLogin();
        $this->csrfCheck();
        require_once __DIR__ . '/../../partials/flash.php';
        $me = $this->currentUser();
        $pdo = $this->db();
        $st = $pdo->prepare('SELECT * FROM users WHERE lower(username) = lower(:u)');
        $st->execute([':u'=>$me]);
        $user = $st->fetch();
        if (!$user) { http_response_code(404); echo 'Utilisateur introuvable'; return; }

        $cur = (string)($_POST['current_password'] ?? '');
        $n1  = (string)($_POST['new_password'] ?? '');
        $n2  = (string)($_POST['new_password_confirm'] ?? '');
        $err = [];
        if (!password_verify($cur, $user['password_hash'])) { $err[] = 'Mot de passe actuel invalide.'; }
        if ($n1 !== $n2) { $err[] = 'Confirmation différente.'; }
        if (strlen($n1) < 8) { $err[] = 'Min. 8 caractères.'; }
        if (!preg_match('~[A-Z]~', $n1)) { $err[] = 'Ajouter une majuscule.'; }
        if (!preg_match('~[a-z]~', $n1)) { $err[] = 'Ajouter une minuscule.'; }
        if (!preg_match('~\\d~', $n1))   { $err[] = 'Ajouter un chiffre.'; }

        if ($err) {
            flash('err', implode(' ', $err));
            Response::redirect('/account');
            return;
        }
        $pdo->prepare('UPDATE users SET password_hash=:h WHERE id=:id')
            ->execute([':h'=>password_hash($n1, PASSWORD_BCRYPT), ':id'=>$user['id']]);
        require_once __DIR__ . '/../../lib/auth.php';
        audit('user.password.change', ['user'=>$me]);
        // logout and force re-login
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
        $_SESSION = $_SESSION ?? [];
        // Preserve flash in legacy manner
        $_SESSION['flash']['ok'] = 'Mot de passe changé. Reconnectez-vous.';
        // Full logout using legacy helper
        logout();
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
        $_SESSION['flash']['ok'] = 'Mot de passe changé. Reconnectez-vous.';
        Response::redirect('/login');
    }
}
