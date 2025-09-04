<div class="card">
  <h2>Éditer : <?= htmlspecialchars($site['name']) ?></h2>
  <?php show_flash(); ?>
  <form method="post" action="/sites/<?= (int)$site['id'] ?>/update">
    <?= csrf_input() ?>
    <label>Nom (slug)</label>
    <input value="<?= htmlspecialchars($site['name']) ?>" disabled>
    <div class="small">Le renommage n'est pas géré ici.</div>

    <label>Server names (espace/virgule)</label>
    <input name="server_names" required value="<?= htmlspecialchars($site['server_names']) ?>">

    <div class="form-row">
      <div>
        <label>Racine (root)</label>
        <input name="root" required value="<?= htmlspecialchars($site['root']) ?>">
      </div>
      <div>
        <label>Version PHP-FPM</label>
        <select name="php_version">
          <?php foreach (["8.2","8.3","8.4"] as $v): ?>
            <option <?= $site['php_version']===$v?'selected':'' ?>><?= $v ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="form-row">
      <div>
        <label>client_max_body_size (MB)</label>
        <input type="number" name="client_max_body_size" value="<?= (int)$site['client_max_body_size'] ?>" min="1" max="1024">
      </div>
      <div style="display:flex;align-items:end">
        <label style="width:auto"><input type="checkbox" name="with_logs" <?= !empty($site['with_logs'])?'checked':''; ?>> Logs dédiés</label>
      </div>
    </div>

    <div style="display:flex;gap:8px">
      <a class="btn" href="/sites">Annuler</a>
      <button class="btn primary">Enregistrer</button>
    </div>
  </form>
</div>

<div class="card">
  <h3>Actions</h3>
  <form method="post" action="/sites/<?= (int)$site['id'] ?>/toggle" style="display:inline">
    <?= csrf_input() ?>
    <input type="hidden" name="enable" value="<?= !empty($site['enabled']) ? '0' : '1' ?>">
    <button class="btn" data-confirm="<?= !empty($site['enabled']) ? 'Désactiver ce site ?' : 'Activer ce site ?' ?>">
      <?= !empty($site['enabled']) ? 'Désactiver' : 'Activer' ?>
    </button>
  </form>
  <form method="post" action="/sites/<?= (int)$site['id'] ?>/delete" style="display:inline">
    <?= csrf_input() ?>
    <button class="btn danger" data-confirm="Supprimer ce site (garder fichiers) ?">Supprimer</button>
  </form>
</div>
