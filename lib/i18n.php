<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

function i18n_langs(): array { return ['fr','en','de','dz']; }

function i18n_set_lang_from_request(): void {
  if (isset($_GET['lang'])) {
    $want = strtolower($_GET['lang']);
    if (in_array($want, i18n_langs(), true)) $_SESSION['lang'] = $want;
  }
  if (empty($_SESSION['lang'])) $_SESSION['lang'] = 'fr';
}
function i18n_lang(): string { return $_SESSION['lang'] ?? 'fr'; }

function __t(string $key, array $vars = []): string {
  static $cache = [];
  $lang = i18n_lang();
  if (!isset($cache[$lang])) {
    $file = __DIR__ . '/../locales/' . $lang . '.php';
    $cache[$lang] = is_file($file) ? include $file : include __DIR__ . '/../locales/fr.php';
  }
  $txt = $cache[$lang][$key] ?? (include __DIR__ . '/../locales/fr.php')[$key] ?? $key;
  return $vars ? strtr($txt, $vars) : $txt;
}
