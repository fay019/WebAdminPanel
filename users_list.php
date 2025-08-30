<?php
require_once __DIR__ . '/lib/auth.php'; require_login();
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/csrf.php';
require_once __DIR__ . '/partials/flash.php';

// Handle delete action (POST)
if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='delete'){
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    // Prevent deleting self
    $st = db()->prepare('SELECT username FROM users WHERE id=:id'); $st->execute([':id'=>$id]); $row=$st->fetch();
    if($row){
        if($row['username']===current_user()){
            flash('err','Impossible de supprimer votre propre compte.');
        } else {
            // Ensure at least 1 user remains
            $c = (int)db()->query('SELECT COUNT(*) AS c FROM users')->fetchColumn();
            if($c<=1){
                flash('err','Au moins un utilisateur doit exister.');
            } else {
                db()->prepare('DELETE FROM users WHERE id=:id')->execute([':id'=>$id]);
                audit('user.delete',[ 'username'=>$row['username'] ]);
                flash('ok','Utilisateur supprimé.');
            }
        }
    }
    header('Location: /users_list.php'); exit;
}

$users = db()->query('SELECT id, username, created_at FROM users ORDER BY username ASC')->fetchAll();
include __DIR__ . '/partials/header.php';
?>
<div class="card">
  <h2>Utilisateurs</h2>
  <?php show_flash(); ?>
  <div style="margin:10px 0">
    <a class="btn primary" href="/user_new.php">+ Ajouter un utilisateur</a>
  </div>
  <div class="table-responsive">
    <table class="table">
    <thead><tr><th>ID</th><th>Nom</th><th>Créé le</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach($users as $u): ?>
      <tr>
        <td><?= (int)$u['id'] ?></td>
        <td><?= htmlspecialchars($u['username']) ?><?= $u['username']===current_user() ? ' <span class="badge">moi</span>' : '' ?></td>
        <td><?= htmlspecialchars($u['created_at']) ?></td>
        <td>
          <a class="btn" href="/user_edit.php?id=<?= (int)$u['id'] ?>">Éditer</a>
          <?php if($u['username']!==current_user()): ?>
          <form method="post" style="display:inline" data-confirm="Supprimer cet utilisateur ?">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
            <button class="btn danger">Supprimer</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
    </table>
  </div>
</div>
<?php include __DIR__ . '/partials/footer.php'; ?>
