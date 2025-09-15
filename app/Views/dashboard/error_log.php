<?php
// Error Log — modern UI
// Expects: $lines (array of raw lines), $ts (int last updated)
$ts = isset($ts) ? (int)$ts : time();
$initialJson = isset($entries) ? json_encode(array_values($entries), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : json_encode(array_values($lines ?? []), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
?>
<div class="page-toolbar card">
  <div class="toolbar-left">
    <h2 style="margin:0;display:flex;align-items:center;gap:8px;">🚨 Error Log</h2>
  </div>
  <div class="toolbar-right" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
    <div class="small muted" id="logLastUpdated" data-ts="<?= htmlspecialchars((string)$ts, ENT_QUOTES, 'UTF-8') ?>">
      Dernière mise à jour: <span id="logLastUpdatedTxt"></span>
    </div>
    <button type="button" class="btn" id="btnRefresh" title="Rafraîchir">Rafraîchir</button>
    <a href="/dashboard/error-log/download" class="btn" title="Télécharger complet">Télécharger complet</a>
  </div>
</div>

<div class="card">
  <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
    <div class="filters" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
      <label class="chip small" for="flt_php_error"><input id="flt_php_error" type="checkbox" class="flt" value="php_error" checked> php_error</label>
      <label class="chip small danger" for="flt_php_exception"><input id="flt_php_exception" type="checkbox" class="flt" value="php_exception" checked> php_exception</label>
      <label class="chip small info" for="flt_http_404"><input id="flt_http_404" type="checkbox" class="flt" value="http_404" checked> http_404</label>
      <label class="chip small warn" for="flt_http_500"><input id="flt_http_500" type="checkbox" class="flt" value="http_500" checked> http_500</label>
      <label class="chip small" for="flt_other"><input id="flt_other" type="checkbox" class="flt" value="other" checked> autres</label>
    </div>
    <div style="display:flex;gap:8px;align-items:center">
      <input type="text" id="logSearch" class="input" placeholder="Rechercher… (ex: PDO, Router)" aria-label="Rechercher dans les logs">
    </div>
  </div>
  <div id="logList" class="log-list" aria-live="polite"></div>
  <div class="card-footer" style="display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap">
    <div class="small">
      <label><input type="checkbox" id="autoScroll" checked> Auto-scroll vers les plus récents</label>
    </div>
    <div style="display:flex;gap:8px;align-items:center">
      <button type="button" class="btn" id="btnLoadMore">Charger plus</button>
      <span class="small muted" id="logCount"></span>
    </div>
  </div>
</div>

<script id="__error_log_data" type="application/json"><?= $initialJson ?></script>
<script src="/js/error_log.js" defer></script>
