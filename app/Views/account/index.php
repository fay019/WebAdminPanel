<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/../partials/flash.php';
require_once __DIR__ . '/../../../lib/csrf.php';
require_once __DIR__ . '/../../../lib/auth.php';
$csrf = $_SESSION['csrf'] ?? csrf_token();
$me = current_user();
?>
<div class="card">
  <h2>Mon compte</h2>
  <?php show_flash(); ?>
</div>

<div class="form-row">
  <div class="card">
    <h3>Changer le nom d’utilisateur</h3>
    <form method="post" action="/account/username">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
      <label>Nom actuel</label>
      <input value="<?= htmlspecialchars((string)$me, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" disabled>
      <label>Nouveau nom</label>
      <input name="new_username" required minlength="3" value="<?= htmlspecialchars((string)$me, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
      <button class="btn primary" style="margin-top:10px">Enregistrer</button>
    </form>
  </div>

  <div class="card">
    <h3>Changer le mot de passe</h3>
    <form method="post" action="/account/password">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
      <label>Mot de passe actuel</label>
      <input type="password" name="current_password" required>
      <label>Nouveau mot de passe</label>
      <input type="password" name="new_password" required minlength="8" placeholder="Min. 8 caractères, maj/min/chiffre">
      <label>Confirmer le nouveau mot de passe</label>
      <input type="password" name="new_password_confirm" required minlength="8">
      <div class="pw-tools" data-pass-tools>
        <button type="button" class="btn small" data-action="generate" aria-label="Générer un mot de passe">Générer</button>
        <button type="button" class="btn small" data-action="toggle" aria-label="Afficher ou masquer le mot de passe">Afficher</button>
        <button type="button" class="btn small" data-action="copy" aria-label="Copier le mot de passe">Copier</button>
        <span class="pw-strength" data-role="strength" aria-live="polite">Force: —</span>
      </div>
      <button class="btn primary" style="margin-top:10px">Mettre à jour</button>
    </form>
  </div>
</div>
