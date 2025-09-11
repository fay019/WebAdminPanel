<?php
require_once __DIR__ . '/lib/auth.php'; require_login();
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/csrf.php';
require_once __DIR__ . '/partials/flash.php';

$me = current_user();
$stmt = db()->prepare('SELECT * FROM users WHERE lower(username) = lower(:u)');
$stmt->execute([':u'=>$me]);
$user = $stmt->fetch();
if(!$user){ http_response_code(404); die('Utilisateur introuvable'); }

if($_SERVER['REQUEST_METHOD']==='POST'){
    csrf_check();
    if(($_POST['action']??'')==='change_username'){
        $new = trim($_POST['new_username']??'');
        $err=[];
        if($new==='' || strlen($new)<3) $err[]='Nom trop court.';
        if($new!==$me){
            $chk=db()->prepare('SELECT COUNT(*) FROM users WHERE username=:u COLLATE NOCASE AND id<>:id'); $chk->execute([':u'=>$new, ':id'=>$user['id']]);
            if($chk->fetchColumn()>0) $err[]='Nom déjà pris.';
        }
        if(!$err){
            db()->prepare('UPDATE users SET username=:n WHERE id=:id')->execute([':n'=>$new, ':id'=>$user['id']]);
            audit('user.rename',['from'=>$me,'to'=>$new]);
            $_SESSION['user']=$new;
            flash('ok','Nom mis à jour.'); header('Location: /account.php'); exit;
        } else flash('err',implode(' ',$err));
    }
    if(($_POST['action']??'')==='change_password'){
        $cur=$_POST['current_password']??''; $n1=$_POST['new_password']??''; $n2=$_POST['new_password_confirm']??'';
        $err=[];
        if(!password_verify($cur,$user['password_hash'])) $err[]='Mot de passe actuel invalide.';
        if($n1!==$n2) $err[]='Confirmation différente.';
        if(strlen($n1)<8) $err[]='Min. 8 caractères.';
        if(!preg_match('~[A-Z]~',$n1)) $err[]='Ajouter une majuscule.';
        if(!preg_match('~[a-z]~',$n1)) $err[]='Ajouter une minuscule.';
        if(!preg_match('~\\d~',$n1))   $err[]='Ajouter un chiffre.';
        if(!$err){
            db()->prepare('UPDATE users SET password_hash=:h WHERE id=:id')->execute([':h'=>password_hash($n1,PASSWORD_BCRYPT), ':id'=>$user['id']]);
            audit('user.password.change',['user'=>$me]);
            logout(); session_start(); $_SESSION['flash']['ok']='Mot de passe changé. Reconnectez-vous.';
            header('Location: /login'); exit;
        } else flash('err',implode(' ',$err));
    }
}

include __DIR__ . '/partials/header.php';
?>
    <div class="card">
      <h2>Mon compte</h2>
      <?php show_flash(); ?>
    </div>

    <div class="form-row">
        <div class="card">
            <h3>Changer le nom d’utilisateur</h3>
            <form method="post">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="change_username">
                <label>Nom actuel</label>
                <input value="<?= htmlspecialchars($me) ?>" disabled>
                <label>Nouveau nom</label>
                <input name="new_username" required minlength="3" value="<?= htmlspecialchars($me) ?>">
                <button class="btn primary" style="margin-top:10px">Enregistrer</button>
            </form>
        </div>

        <div class="card">
            <h3>Changer le mot de passe</h3>
            <form method="post">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="change_password">
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
<?php include __DIR__ . '/partials/footer.php'; ?>