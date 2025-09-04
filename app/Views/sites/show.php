<div class="card">
  <h2>Site: <?= htmlspecialchars($site['name']) ?></h2>
  <dl>
    <dt>Server names</dt><dd><?= htmlspecialchars($site['server_names']) ?></dd>
    <dt>Root</dt><dd><code><?= htmlspecialchars($site['root']) ?></code></dd>
    <dt>PHP</dt><dd><?= htmlspecialchars($site['php_version']) ?></dd>
    <dt>Upload MB</dt><dd><?= (int)$site['client_max_body_size'] ?></dd>
    <dt>Logs dédiés</dt><dd><?= !empty($site['with_logs']) ? 'oui' : 'non' ?></dd>
    <dt>État</dt><dd><?= !empty($site['enabled']) ? 'activé' : 'désactivé' ?></dd>
  </dl>
  <div style="display:flex;gap:8px">
    <a class="btn" href="/sites">Retour</a>
    <a class="btn" href="/sites/<?= (int)$site['id'] ?>/edit">Éditer</a>
  </div>
</div>
