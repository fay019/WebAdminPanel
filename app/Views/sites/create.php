<div class="card">
  <h2>Nouveau site</h2>
  <?php show_flash(); ?>
  <form method="post" action="/sites">
    <?= csrf_input() ?>
    <label>Nom (slug)</label>
    <input name="name" required>

    <label>Server names (espace/virgule)</label>
    <input name="server_names" required placeholder="ex: site.local www.site.local">

    <div class="form-row">
      <div>
        <label>Racine (root)</label>
        <input name="root" placeholder="/var/www/{name}/public">
      </div>
      <div>
        <label>Version PHP-FPM</label>
        <select name="php_version">
          <option>8.2</option><option selected>8.3</option><option>8.4</option>
        </select>
      </div>
    </div>

    <div class="form-row">
      <div>
        <label>client_max_body_size (MB)</label>
        <input type="number" name="client_max_body_size" value="20" min="1" max="1024">
      </div>
      <div style="display:flex;align-items:end">
          <label style="width:auto">
              <input type="checkbox" name="with_logs" checked> Logs dédiés
          </label>
          <label style="width:auto">
              <input type="checkbox" name="reset_root" value="1"> Réinitialiser le dossier si existant
          </label>
      </div>
    </div>

    <div style="display:flex;gap:8px">
      <a class="btn" href="/sites">Annuler</a>
      <button class="btn primary" style="margin-top:10px">Créer</button>
    </div>
  </form>
</div>
