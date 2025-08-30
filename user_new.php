<?php
require_once __DIR__ . '/lib/auth.php'; require_login();
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/csrf.php';
require_once __DIR__ . '/partials/flash.php';

if($_SERVER['REQUEST_METHOD']==='POST'){
    csrf_check();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';
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
        $st=db()->prepare('INSERT INTO users(username,password_hash,created_at) VALUES(:u,:p,:c)');
        $st->execute([':u'=>$username, ':p'=>password_hash($password,PASSWORD_BCRYPT), ':c'=>date('c')]);
        audit('user.create',['username'=>$username]);
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
    <div style="margin-top:10px">
      <button class="btn primary">Créer</button>
      <a class="btn" href="/users_list.php">Annuler</a>
    </div>
  </form>
</div>
<?php include __DIR__ . '/partials/footer.php'; ?>
