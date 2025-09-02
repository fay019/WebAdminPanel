<?php /* Liste des utilisateurs (MVC) */ ?>
<div class="card">
  <h2><?php echo function_exists('__') ? __('nav.users') : 'Utilisateurs'; ?></h2>
  <?php show_flash(); ?>
  <div class="actions" style="display:flex;gap:10px;align-items:center;justify-content:space-between;flex-wrap:wrap">
    <form method="get" action="/users" style="display:flex;gap:8px;align-items:center">
      <input type="text" name="q" placeholder="Rechercher..." value="<?= htmlspecialchars($q ?? '') ?>">
      <button class="btn">Rechercher</button>
    </form>
    <a class="btn primary" href="/users/create">+ Ajouter un utilisateur</a>
  </div>
  <div class="table-wrap">
    <table class="tbl tbl--hover tbl--sticky" role="table" data-default-sort="created" data-default-dir="desc">
      <thead>
        <tr>
          <th class="col-id is-sortable" data-sort-key="id" scope="col">ID</th>
          <th class="col-name is-sortable" data-sort-key="name" scope="col">Nom</th>
          <th class="col-wide is-sortable" data-sort-key="notes" scope="col">Notes</th>
          <th class="col-date is-sortable" data-sort-key="created" scope="col">Créé le</th>
          <th class="col-actions" scope="col">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach($users as $u): ?>
        <?php $notes = trim((string)($u['notes'] ?? '')); $raw = $u['created_at']; $ts = strtotime($raw); ?>
        <tr>
          <td class="col-id" data-label="ID"><?= (int)$u['id'] ?></td>
          <td class="col-name" data-label="Nom">
            <strong><?= htmlspecialchars($u['username']) ?></strong><?= $u['username']===current_user() ? ' <span class="badge">moi</span>' : '' ?>
            <div class="name-sub">
              <?php if($notes===''): ?>
                <span class="muted mono">—</span>
              <?php else: ?>
                <span class="text-ellipsis mono" data-tip="<?= htmlspecialchars($notes, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" title="<?= htmlspecialchars($notes, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= htmlspecialchars($notes) ?></span>
              <?php endif; ?>
              <span class="sep"> • </span>
              <span class="mono" title="<?= htmlspecialchars(date('c', $ts)) ?>"><?= htmlspecialchars(date('d.m.Y', $ts)) ?></span>
            </div>
          </td>
          <td class="col-wide" data-label="Notes">
            <?php if($notes===''): ?>
              <span class="small muted mono">—</span>
            <?php else: ?>
              <span class="clamp-2 mono" data-tip="<?= htmlspecialchars($notes, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" title="<?= htmlspecialchars($notes, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= htmlspecialchars($notes) ?></span>
            <?php endif; ?>
          </td>
          <td class="col-date" data-label="Créé le" data-sort="<?= (int)$ts ?>"
              title="<?= htmlspecialchars(date('c', $ts)) ?>">
              <span class="mono"><?= htmlspecialchars(date('d.m.Y', $ts)) ?></span>
          </td>
          <td class="col-actions">
            <a class="btn btn-sm" href="/users/<?= (int)$u['id'] ?>/edit">Éditer</a>
            <?php if($u['username']!==current_user()): ?>
            <form method="post" data-confirm="Supprimer cet utilisateur ?" action="/users/<?= (int)$u['id'] ?>/delete">
              <?= csrf_input() ?>
              <button class="btn danger btn-sm">Supprimer</button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if (!empty($pagination)): ?>
    <div class="pagination" style="display:flex;gap:10px;justify-content:flex-end;margin-top:10px">
      <?php $qparam = ($q??'')!=='' ? '&q='.urlencode($q) : ''; ?>
      <a class="btn" href="/users?page=<?= (int)($pagination['hasPrev'] ? $pagination['prevPage'] : 1) ?><?= $qparam ?>" aria-disabled="<?= $pagination['hasPrev']? 'false':'true' ?>">&laquo; Précédent</a>
      <span class="small mono">Page <?= (int)$pagination['page'] ?> / <?= (int)$pagination['lastPage'] ?> — <?= (int)$pagination['total'] ?> utilisateur(s)</span>
      <a class="btn" href="/users?page=<?= (int)($pagination['hasNext'] ? $pagination['nextPage'] : $pagination['lastPage']) ?><?= $qparam ?>" aria-disabled="<?= $pagination['hasNext']? 'false':'true' ?>">Suivant &raquo;</a>
    </div>
  <?php endif; ?>
</div>
