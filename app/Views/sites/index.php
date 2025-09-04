<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center">
    <h2>Sites</h2>
    <a class="btn primary" href="/sites/create">+ Nouveau site</a>
  </div>
  <?php show_flash(); ?>
  <div class="table-responsive">
    <table class="tbl tbl--hover tbl--sticky" role="table" data-default-sort="name" data-default-dir="asc">
      <thead>
        <tr>
          <th class="col-name is-sortable" data-sort-key="name" scope="col">Nom</th>
          <th class="col-wide" scope="col">Server Names</th>
          <th class="col-wide" scope="col">Root</th>
          <th class="col-status" scope="col">PHP</th>
          <th class="col-status" scope="col">État</th>
          <th class="col-actions" scope="col">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach (($sites ?? []) as $s): ?>
        <tr>
          <td class="col-name" data-label="Nom"><strong><?= htmlspecialchars($s['name']) ?></strong></td>
          <td class="col-wide" data-label="Server Names"><span class="text-ellipsis" title="<?= htmlspecialchars($s['server_names']) ?>"><?= htmlspecialchars($s['server_names']) ?></span></td>
          <td class="col-wide" data-label="Root"><span class="text-ellipsis small" title="<?= htmlspecialchars($s['root']) ?>"><?= htmlspecialchars($s['root']) ?></span></td>
          <td class="col-status" data-label="PHP"><span class="badge"><?= htmlspecialchars($s['php_version']) ?></span></td>
          <td class="col-status" data-label="État"><?= !empty($s['enabled']) ? '<span class="badge ok">activé</span>' : '<span class="badge err">désactivé</span>' ?></td>
          <td class="col-actions" data-label="Actions">
            <a class="btn" href="/sites/<?= (int)$s['id'] ?>/edit">Éditer</a>
            <form method="post" action="/sites/<?= (int)$s['id'] ?>/toggle" class="action-form" style="display:inline">
              <?= csrf_input() ?>
              <input type="hidden" name="enable" value="<?= !empty($s['enabled']) ? '0' : '1' ?>">
              <button class="btn" data-confirm="<?= !empty($s['enabled']) ? 'Désactiver ce site ?' : 'Activer ce site ?' ?>">
                <?= !empty($s['enabled']) ? 'Désactiver' : 'Activer' ?>
              </button>
            </form>
            <form method="post" action="/sites/<?= (int)$s['id'] ?>/delete" class="action-form" style="display:inline">
              <?= csrf_input() ?>
              <button class="btn danger" data-confirm="Supprimer ce site (garder fichiers) ?">Supprimer</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($sites)): ?>
        <tr><td colspan="6" class="small">Aucun site pour l’instant.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if (!empty($orphans)): ?>
  <div class="card">
    <h3>🧹 Dossiers orphelins</h3>
    <div class="table-responsive">
      <table class="tbl tbl--hover tbl--sticky" role="table" data-default-sort="name" data-default-dir="asc">
        <thead>
          <tr>
            <th class="col-name is-sortable" data-sort-key="name" scope="col">Nom</th>
            <th class="col-wide" scope="col">Chemin</th>
            <th class="col-actions" scope="col">Action</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach (($orphans ?? []) as $o): ?>
          <tr>
            <td class="col-name" data-label="Nom"><span class="text-ellipsis" title="<?= htmlspecialchars($o['name']) ?>"><?= htmlspecialchars($o['name']) ?></span></td>
            <td class="col-wide" data-label="Chemin">
              <span class="text-ellipsis small" title="<?= htmlspecialchars($o['path']) ?>"><?= htmlspecialchars($o['path']) ?></span>
            </td>
            <td class="col-actions" data-label="Action">
              <form method="post" action="/orphan/delete" class="action-form">
                <?= csrf_input() ?>
                <input type="hidden" name="dir" value="<?= htmlspecialchars($o['path']) ?>">
                <input type="hidden" name="back" value="/sites">
                <button class="btn danger" data-confirm="Supprimer définitivement le dossier « <?= htmlspecialchars($o['name']) ?> » ?">Supprimer le dossier</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="small">⚠️ Supprime uniquement le répertoire. Pas d’entrée DB ni de conf Nginx.</div>
  </div>
<?php endif; ?>
