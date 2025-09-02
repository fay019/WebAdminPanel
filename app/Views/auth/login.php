<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/../../../partials/flash.php';
require_once __DIR__ . '/../../../lib/csrf.php';
$csrf = $_SESSION['csrf'] ?? csrf_token();
$loggedIn = isset($_SESSION['user']);
?>
<div class="card" style="max-width:420px;margin:40px auto">
  <h2>Connexion</h2>
  <?php show_flash(); ?>
  <?php if (!$loggedIn): ?>
  <form method="POST" action="/login">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <label>Utilisateur</label>
    <input name="username" required autofocus>
    <label>Mot de passe</label>
    <input name="password" type="password" required>
    <div style="margin-top:12px"><button class="btn primary">Se connecter</button></div>
  </form>
  <p class="small" style="margin-top:8px">Initial : <code>admin / admin</code></p>
  <?php else: ?>
  <p>Vous êtes déjà connecté en tant que <strong><?= htmlspecialchars((string)$_SESSION['user'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong>.</p>
  <form method="POST" action="/logout" style="margin-top:12px">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <button class="btn">Se déconnecter</button>
  </form>
  <?php endif; ?>
</div>
