<?php /* Copie fidèle de user_new.php (corps), servie via layout */ ?>
<div class="card">
  <h2>Nouvel utilisateur</h2>
  <?php show_flash(); ?>
  <form method="post" action="/users">
    <?= csrf_input() ?>
    <label>Nom d’utilisateur</label>
    <input name="username" required minlength="3" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
    <label>Mot de passe</label>
    <input type="password" name="password" required minlength="8" placeholder="Min. 8 caractères, maj/min/chiffre">
    <label>Confirmer le mot de passe</label>
    <input type="password" name="confirm" required minlength="8">
    <div class="pw-tools" data-pass-tools>
      <button type="button" class="btn small" data-action="generate" aria-label="Générer un mot de passe" onclick="window.generatePassword && window.generatePassword()">Générer</button>
      <button type="button" class="btn small" data-action="toggle" aria-label="Afficher ou masquer le mot de passe">Afficher</button>
      <button type="button" class="btn small" data-action="copy" aria-label="Copier le mot de passe">Copier</button>
      <span class="pw-strength" data-role="strength" aria-live="polite">Force: —</span>
    </div>
    <label>Notes (optionnel)</label>
    <textarea name="notes" maxlength="1000" rows="4" placeholder="Mémo interne (max 1000 caractères)"><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
    <div style="margin-top:10px">
      <button class="btn primary">Créer</button>
      <a class="btn" href="/users">Annuler</a>
    </div>
  </form>
</div>
<script src="/js/password.js" defer></script>
