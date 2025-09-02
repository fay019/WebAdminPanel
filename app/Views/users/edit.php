<?php /* Copie fidèle de user_edit.php (corps), servie via layout */ ?>
<div class="card">
  <h2>Éditer l’utilisateur</h2>
  <?php show_flash(); ?>
  <form method="post" style="margin-bottom:20px" action="/users/<?= (int)$user['id'] ?>">
    <?= csrf_input() ?>
    <input type="hidden" name="action" value="save_profile">
    <label>Nom d’utilisateur</label>
    <input name="username" required minlength="3" value="<?= htmlspecialchars($_POST['username'] ?? $user['username']) ?>">
    <label>Notes (optionnel)</label>
    <textarea name="notes" maxlength="1000" rows="4" placeholder="Mémo interne (max 1000 caractères)"><?= htmlspecialchars($_POST['notes'] ?? ($user['notes'] ?? '')) ?></textarea>
    <div style="margin-top:10px">
      <button class="btn primary">Enregistrer</button>
      <a class="btn" href="/users">Retour</a>
    </div>
  </form>

  <hr>
  <h3>Réinitialiser le mot de passe</h3>
  <form method="post" action="/users/<?= (int)$user['id'] ?>/reset-password">
    <?= csrf_input() ?>
    <label>Nouveau mot de passe</label>
    <input type="password" name="password" required minlength="8" placeholder="Min. 8 caractères, maj/min/chiffre">
    <label>Confirmer</label>
    <input type="password" name="confirm" required minlength="8">
    <div class="pw-tools" data-pass-tools>
      <button type="button" class="btn small" data-action="generate" aria-label="Générer un mot de passe" onclick="window.generatePassword && window.generatePassword()">Générer</button>
      <button type="button" class="btn small" data-action="toggle" aria-label="Afficher ou masquer le mot de passe">Afficher</button>
      <button type="button" class="btn small" data-action="copy" aria-label="Copier le mot de passe">Copier</button>
      <span class="pw-strength" data-role="strength" aria-live="polite">Force: —</span>
    </div>
    <div style="margin-top:10px">
      <button class="btn">Réinitialiser</button>
    </div>
  </form>
</div>
<script src="/js/password.js" defer></script>
