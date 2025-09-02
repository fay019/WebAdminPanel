<?php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
function csrf_token(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function csrf_input(): string { return '<input type="hidden" name="csrf" value="'.htmlspecialchars(csrf_token(),ENT_QUOTES).'">'; }
function csrf_check(): void {
  if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $posted = $_POST['_csrf'] ?? ($_POST['csrf'] ?? '');
    $ok = is_string($posted) && $posted !== '' && hash_equals($_SESSION['csrf'] ?? '', $posted);
    if (!$ok) { http_response_code(400); die('CSRF token invalid.'); }
  }
}
