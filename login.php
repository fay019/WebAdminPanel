<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/csrf.php';
require_once __DIR__ . '/partials/flash.php';

migrate();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $u = trim($_POST['username'] ?? '');
  $p = trim($_POST['password'] ?? '');
  if (login($u, $p)) { audit('login', ['user'=>$u]); header('Location: /dashboard.php'); exit; }
  flash('err', 'Identifiants invalides.');
}

include __DIR__ . '/partials/header.php';
?>
<div class="card" style="max-width:420px;margin:40px auto">
  <h2>Connexion</h2>
  <?php show_flash(); ?>
  <form method="post">
    <?= csrf_input() ?>
    <label>Utilisateur</label>
    <input name="username" required autofocus>
    <label>Mot de passe</label>
    <input name="password" type="password" required>
    <div style="margin-top:12px"><button class="btn primary">Se connecter</button></div>
  </form>
  <p class="small" style="margin-top:8px">Initial : <code>admin / admin</code></p>
</div>
<?php include __DIR__ . '/partials/footer.php'; ?>
