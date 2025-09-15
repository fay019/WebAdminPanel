<?php
// View: Error Log viewer (admin-only)
// Expects $lines array
?>
<div class="card">
  <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
    <h3 style="margin:0;display:flex;align-items:center;gap:8px;">🚨 Error Log</h3>
    <a href="/dashboard/error-log/download" class="btn">Télécharger complet</a>
  </div>
  <div class="card-body" style="max-height:600px;overflow:auto;font-family:monospace;background:#111;color:#eee;padding:10px;border-radius:4px;">
    <?php if (!empty($lines)): ?>
      <?php foreach ($lines as $line): ?>
        <div><?= htmlspecialchars((string)$line, ENT_QUOTES, 'UTF-8') ?></div>
      <?php endforeach ?>
    <?php else: ?>
      <div style="color:#aaa;">(vide)</div>
    <?php endif; ?>
  </div>
</div>
