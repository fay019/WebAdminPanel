<?php
// Deprecated legacy file: auto-submit a POST form to new /logout with CSRF
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/lib/csrf.php';
$csrf = csrf_token();
?><!doctype html>
<html><head><meta charset="utf-8"><title>Déconnexion…</title></head>
<body>
<form id="f" method="POST" action="/logout">
  <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
</form>
<script>document.getElementById('f').submit();</script>
<noscript>
  <p>JavaScript désactivé. Cliquez pour confirmer la déconnexion.</p>
  <button form="f" type="submit">Se déconnecter</button>
</noscript>
</body></html>
