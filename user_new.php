<?php
require_once __DIR__ . '/lib/auth.php'; require_login();
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/csrf.php';
require_once __DIR__ . '/partials/flash.php';

migrate();

if($_SERVER['REQUEST_METHOD']==='POST'){
    csrf_check();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';
    $notesRaw = trim((string)($_POST['notes'] ?? ''));
    // Normalize notes: limit to 1000 chars, empty string -> NULL
    $notes = $notesRaw === '' ? null : mb_substr($notesRaw, 0, 1000);
    $err=[];
    if(strlen($username)<3) $err[]='Nom trop court (min 3).';
    if($password!==$confirm) $err[]='Confirmation du mot de passe différente.';
    if(strlen($password)<8) $err[]='Mot de passe trop court (min 8).';
    if(!preg_match('~[A-Z]~',$password)) $err[]='Ajouter une majuscule.';
    if(!preg_match('~[a-z]~',$password)) $err[]='Ajouter une minuscule.';
    if(!preg_match('~\\d~',$password)) $err[]='Ajouter un chiffre.';
    $st=db()->prepare('SELECT COUNT(*) FROM users WHERE username=:u COLLATE NOCASE'); $st->execute([':u'=>$username]);
    if($st->fetchColumn()>0) $err[]='Nom déjà pris.';
    if(!$err){
        $st=db()->prepare('INSERT INTO users(username,password_hash,notes,created_at) VALUES(:u,:p,:n,:c)');
        $st->execute([':u'=>$username, ':p'=>password_hash($password,PASSWORD_BCRYPT), ':n'=>$notes, ':c'=>date('c')]);
        audit('user.create',['username'=>$username, 'notes'=>$notes]);
        flash('ok','Utilisateur créé.');
        header('Location: /users_list.php'); exit;
    } else {
        flash('err', implode(' ', $err));
    }
}
include __DIR__ . '/partials/header.php';
?>
<div class="card">
  <h2>Nouvel utilisateur</h2>
  <?php show_flash(); ?>
  <form method="post">
    <?= csrf_input() ?>
    <label>Nom d’utilisateur</label>
    <input name="username" required minlength="3" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
    <label>Mot de passe</label>
    <input type="password" name="password" required minlength="8" placeholder="Min. 8 caractères, maj/min/chiffre">
    <label>Confirmer le mot de passe</label>
    <input type="password" name="confirm" required minlength="8">
    <div class="pw-tools" data-pass-tools>
      <button type="button" class="btn small" data-action="generate" aria-label="Générer un mot de passe">Générer</button>
      <button type="button" class="btn small" data-action="toggle" aria-label="Afficher ou masquer le mot de passe">Afficher</button>
      <button type="button" class="btn small" data-action="copy" aria-label="Copier le mot de passe">Copier</button>
      <span class="pw-strength" data-role="strength" aria-live="polite">Force: —</span>
    </div>
    <label>Notes (optionnel)</label>
    <textarea name="notes" maxlength="1000" rows="4" placeholder="Mémo interne (max 1000 caractères)"><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
    <div style="margin-top:10px">
      <button class="btn primary">Créer</button>
      <a class="btn" href="/users_list.php">Annuler</a>
    </div>
  </form>
</div>
<?php include __DIR__ . '/partials/footer.php'; ?>
