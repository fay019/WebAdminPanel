<?php /* Détails utilisateur */ ?>
<div class="card">
  <h2>Détails de l’utilisateur</h2>
  <div class="details">
    <p><strong>ID:</strong> <?= (int)$user['id'] ?></p>
    <p><strong>Nom:</strong> <?= htmlspecialchars($user['username']) ?><?= ($user['username']===current_user() ? ' <span class="badge">moi</span>' : '') ?></p>
    <p><strong>Notes:</strong><br>
      <?php $notes = trim((string)($user['notes'] ?? '')); ?>
      <?php if($notes===''): ?>
        <span class="small muted mono">—</span>
      <?php else: ?>
        <span class="mono"><?= nl2br(htmlspecialchars($notes, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) ?></span>
      <?php endif; ?>
    </p>
    <?php $ts = strtotime($user['created_at']); ?>
    <p><strong>Créé le:</strong> <span class="mono" title="<?= htmlspecialchars(date('c', $ts)) ?>"><?= htmlspecialchars(date('d.m.Y H:i', $ts)) ?></span></p>
  </div>
  <div class="actions" style="margin-top:10px;display:flex;gap:10px">
    <a class="btn" href="/users">Retour</a>
    <a class="btn" href="/users/<?= (int)$user['id'] ?>/edit">Éditer</a>
    <?php if($user['username']!==current_user()): ?>
    <form method="post" action="/users/<?= (int)$user['id'] ?>/delete" data-confirm="Supprimer cet utilisateur ?">
      <?= csrf_input() ?>
      <button class="btn danger">Supprimer</button>
    </form>
    <?php endif; ?>
  </div>
</div>
