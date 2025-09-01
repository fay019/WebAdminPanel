<?php /* View copied from legacy php_manage.php to preserve UI and JS behavior */ ?>
<div class="card">
    <h2>PHP (Système)</h2>
    <?php show_flash(); ?>

    <div class="form-row">
        <div>
            <label>Installer une version</label>
            <form method="post"
                  class="actions" style="align-items:end"
                  id="installForm"
                  action="/php_manage.php?stream=1">
                <?= csrf_input() ?>
                <input type="hidden" name="ajax" value="1">
                <div>
                    <label class="small">Choisir dans la liste</label>
                    <select name="version_sel">
                        <?php foreach ($choices as $v): ?>
                            <option><?= htmlspecialchars($v) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="small" style="margin-top:6px">…ou saisir une version précise (ex : 7.4)</div>
                    <input name="version_custom" placeholder="ex: 7.4">
                </div>
                <button class="btn primary" name="action" value="install" data-install>Installer</button>
            </form>
        </div>
    </div>

    <h3 style="margin-top:16px">Versions détectées</h3>
    <div class="table-responsive">
        <table class="tbl tbl--hover tbl--sticky" role="table" data-default-sort="version" data-default-dir="desc">
          <thead>
            <tr>
              <th class="col-name is-sortable" data-sort-key="version" scope="col">Version</th>
              <th class="col-status" scope="col">Socket</th>
              <th class="col-status" scope="col">Service</th>
              <th class="col-actions" scope="col">Actions</th>
            </tr>
          </thead>
          <tbody>
        <?php if (!$rows): ?>
            <tr>
                <td colspan="4" class="small">Aucune version PHP-FPM détectée.</td>
            </tr>
        <?php else: foreach ($rows as $r): ?>
            <tr>
                <td class="col-name" data-label="Version" data-sort="<?= htmlspecialchars($r['ver']) ?>"><strong>php<?= htmlspecialchars($r['ver']) ?></strong></td>
                <td class="col-status" data-label="Socket">
                  <span class="badge <?= $r['socket'] ? 'ok' : 'err' ?>">
                    <?= $r['socket'] ? 'présent' : 'absent' ?>
                  </span>
                </td>
                <td class="col-status" data-label="Service">
                  <span class="badge <?= $r['service'] ? 'ok' : 'err' ?>">
                    <?= $r['service'] ? 'actif' : 'inactif' ?>
                  </span>
                </td>
                <td class="col-actions" data-label="Actions">
                    <form method="post">
                        <?= csrf_input() ?>
                        <input type="hidden" name="version" value="<?= htmlspecialchars($r['ver']) ?>">
                        <button class="btn" name="action" value="restart">Redémarrer</button>
                    </form>
                    <form method="post">
                        <?= csrf_input() ?>
                        <input type="hidden" name="version" value="<?= htmlspecialchars($r['ver']) ?>">
                        <button class="btn danger" name="action" value="remove"
                                data-confirm="Désinstaller PHP <?= htmlspecialchars($r['ver']) ?> ?">
                            Désinstaller
                        </button>
                    </form>
                </td>
            </tr>
        <?php endforeach; endif; ?>
          </tbody>
        </table>
    </div>
</div>
<div class="overlay" id="busyOverlay" aria-hidden="true">
    <div class="overlay-card" role="dialog" aria-modal="true" aria-labelledby="busyTitle">
        <div class="overlay-header">
            <div class="ok-dot" style="background:#60a5fa"></div>
            <h3 class="overlay-title" id="busyTitle">Installation en cours…</h3>
        </div>
        <div class="overlay-body">
            <div id="busyHint" class="small" style="margin-bottom:8px">Merci de patienter, ne fermez pas la page.
            </div>
            <pre id="busyLog" class="pre-log"></pre>
        </div>
        <div class="overlay-footer">
            <button type="button" class="btn" id="busyClose" disabled>Fermer</button>
        </div>
    </div>
</div>
