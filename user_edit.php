<?php
require_once __DIR__ . '/lib/auth.php'; require_login();
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/csrf.php';
require_once __DIR__ . '/partials/flash.php';

$id = (int)($_GET['id'] ?? 0);
$st = db()->prepare('SELECT * FROM users WHERE id=:id'); $st->execute([':id'=>$id]); $user=$st->fetch();
if(!$user){ http_response_code(404); die('Utilisateur introuvable'); }

if($_SERVER['REQUEST_METHOD']==='POST'){
    csrf_check();
    $action = $_POST['action'] ?? '';
    if($action==='save_profile'){
        $username = trim($_POST['username'] ?? '');
        $err=[];
        if(strlen($username)<3) $err[]='Nom trop court (min 3).';
        if($username!==$user['username']){
            $chk=db()->prepare('SELECT COUNT(*) FROM users WHERE username=:u COLLATE NOCASE AND id<>:id'); $chk->execute([':u'=>$username, ':id'=>$id]);
            if($chk->fetchColumn()>0) $err[]='Nom déjà pris.';
        }
        if(!$err){
            db()->prepare('UPDATE users SET username=:u WHERE id=:id')->execute([':u'=>$username, ':id'=>$id]);
            audit('user.update',[ 'from'=>$user['username'], 'to'=>$username ]);
            if($user['username']===current_user()) $_SESSION['user']=$username;
            flash('ok','Utilisateur mis à jour.');
            header('Location: /users_list.php'); exit;
        } else flash('err', implode(' ', $err));
    }
    if($action==='reset_password'){
        $p1=$_POST['password']??''; $p2=$_POST['confirm']??'';
        $err=[];
        if($p1!==$p2) $err[]='Confirmation différente.';
        if(strlen($p1)<8) $err[]='Mot de passe trop court (min 8).';
        if(!preg_match('~[A-Z]~',$p1)) $err[]='Ajouter une majuscule.';
        if(!preg_match('~[a-z]~',$p1)) $err[]='Ajouter une minuscule.';
        if(!preg_match('~\\d~',$p1)) $err[]='Ajouter un chiffre.';
        if(!$err){
            db()->prepare('UPDATE users SET password_hash=:h WHERE id=:id')->execute([':h'=>password_hash($p1,PASSWORD_BCRYPT), ':id'=>$id]);
            audit('user.password.reset',[ 'username'=>$user['username'] ]);
            flash('ok','Mot de passe réinitialisé.');
            header('Location: /users_list.php'); exit;
        } else flash('err', implode(' ', $err));
    }
}
include __DIR__ . '/partials/header.php';
?>
<div class="card">
  <h2>Éditer l’utilisateur</h2>
  <?php show_flash(); ?>
  <form method="post" style="margin-bottom:20px">
    <?= csrf_input() ?>
    <input type="hidden" name="action" value="save_profile">
    <label>Nom d’utilisateur</label>
    <input name="username" required minlength="3" value="<?= htmlspecialchars($_POST['username'] ?? $user['username']) ?>">
    <div style="margin-top:10px">
      <button class="btn primary">Enregistrer</button>
      <a class="btn" href="/users_list.php">Retour</a>
    </div>
  </form>

  <hr>
  <h3>Réinitialiser le mot de passe</h3>
  <form method="post">
    <?= csrf_input() ?>
    <input type="hidden" name="action" value="reset_password">
    <label>Nouveau mot de passe</label>
    <input type="password" name="password" required minlength="8" placeholder="Min. 8 caractères, maj/min/chiffre">
    <label>Confirmer</label>
    <input type="password" name="confirm" required minlength="8">
    <div class="pw-tools" data-pass-tools>
      <button type="button" class="btn small" data-action="generate" aria-label="Générer un mot de passe">Générer</button>
      <button type="button" class="btn small" data-action="toggle" aria-label="Afficher ou masquer le mot de passe">Afficher</button>
      <button type="button" class="btn small" data-action="copy" aria-label="Copier le mot de passe">Copier</button>
      <span class="pw-strength" data-role="strength" aria-live="polite">Force: —</span>
    </div>
    <div style="margin-top:10px">
      <button class="btn">Réinitialiser</button>
    </div>
  </form>
</div>
<?php include __DIR__ . '/partials/footer.php'; ?>
