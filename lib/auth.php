<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
function is_logged_in(): bool { return isset($_SESSION['user']); }
function require_login(): void { if (!is_logged_in()) { header('Location: /login'); exit; } }
function login(string $u, string $p): bool {
  // Case-insensitive username lookup (SQLite lower()) while keeping password_verify() intact
  $st = db()->prepare('SELECT * FROM users WHERE lower(username) = lower(:u) LIMIT 1');
  $st->execute([':u'=>$u]);
  $row = $st->fetch();
  if ($row && password_verify($p, $row['password_hash'])) { $_SESSION['user'] = $row['username']; return true; }
  return false;
}
function logout(): void { $_SESSION=[]; session_destroy(); }
function current_user(): string { return $_SESSION['user'] ?? 'unknown'; }
function audit(string $action, array $payload=[]): void {
  $st=db()->prepare('INSERT INTO audit(username,action,payload,created_at) VALUES(:u,:a,:p,:c)');
  $st->execute([':u'=>current_user(),':a'=>$action,':p'=>json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),':c'=>date('c')]);
  $dir=__DIR__.'/../logs'; if(!is_dir($dir)) @mkdir($dir,0775,true);
  file_put_contents($dir.'/panel.log', sprintf("%s\t%s\t%s\t%s\n", date('c'), current_user(), $action, json_encode($payload)), FILE_APPEND);
}
