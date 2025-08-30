<?php
declare(strict_types=1);
function v_slug(string $s): bool {
  return (bool)preg_match('~^[a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?$~',$s);
}
function v_server_names(string $s): bool {
  $parts = preg_split('~[,\s]+~', trim($s)); if(!$parts) return false;
  foreach($parts as $h){ if($h==='_') continue;
    if(!preg_match('~^([a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?)(\.[a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?)*$~',$h)) return false;
  } return true;
}
function v_root(string $p): bool { return str_starts_with($p,'/var/www/') && preg_match('~^/var/www/[a-z0-9\-_\/]+$~',$p); }
function v_php_version(string $v): bool { return in_array($v,['8.2','8.3','8.4'],true); }
function clean_server_names(string $s): string { $p=array_filter(preg_split('~[,\s]+~',trim($s))); return implode(' ', array_unique($p)); }
