<?php
namespace App\Services;

final class SitesService
{
    private string $deployDir = '/var/www/adminpanel/bin';
    private string $localDir;

    public function __construct()
    {
        $this->localDir = realpath(__DIR__ . '/../../bin') ?: __DIR__ . '/../../bin';
    }

    private function bin(string $name): string
    {
        $deploy = $this->deployDir . '/' . $name;
        $local = $this->localDir . '/' . $name;
        return is_file($deploy) ? $deploy : $local;
    }

    public function list(): array
    {
        require_once __DIR__ . '/../../lib/db.php';
        return db()->query('SELECT * FROM sites ORDER BY created_at DESC')->fetchAll();
    }

    public function find(int $id): ?array
    {
        require_once __DIR__ . '/../../lib/db.php';
        $st = db()->prepare('SELECT * FROM sites WHERE id=:id');
        $st->execute([':id' => $id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function create(array $data): array
    {
        // minimal: call site_add.sh then insert DB similar to legacy
        require_once __DIR__ . '/../../lib/db.php';
        $name = strtolower(trim($data['name'] ?? ''));
        $server_names = trim((string)($data['server_names'] ?? ''));
        $root = trim((string)($data['root'] ?? ''));
        $php = (string)($data['php_version'] ?? '8.3');
        $mb = (int)($data['client_max_body_size'] ?? 20);
        $with_logs = !empty($data['with_logs']) ? 1 : 0;
        $reset = !empty($data['reset_root']) ? 1 : 0;
        if ($root === '') { $root = "/var/www/{$name}/public"; }
        $cmd = sprintf(
            'sudo -n %s %s %s %s %s %d %d %d 2>&1',
            escapeshellarg($this->bin('site_add.sh')),
            escapeshellarg($name),
            escapeshellarg($server_names),
            escapeshellarg($root),
            escapeshellarg($php),
            $mb,
            $with_logs,
            $reset
        );
        $out = shell_exec($cmd) ?? '';
        db()->prepare('INSERT INTO sites(name,server_names,root,php_version,client_max_body_size,with_logs,enabled,created_at,updated_at) VALUES(:n,:s,:r,:p,:mb,:wl,0,:c,:c)')
            ->execute([':n'=>$name, ':s'=>$server_names, ':r'=>$root, ':p'=>$php, ':mb'=>$mb, ':wl'=>$with_logs, ':c'=>date('c')]);
        return ['ok'=>true,'output'=>$out];
    }

    public function update(int $id, array $data): array
    {
        require_once __DIR__ . '/../../lib/db.php';
        $site = $this->find($id); if (!$site) return ['ok'=>false,'output'=>'Site not found'];
        $server_names = trim((string)($data['server_names'] ?? $site['server_names']));
        $root = trim((string)($data['root'] ?? $site['root']));
        $php = (string)($data['php_version'] ?? $site['php_version']);
        $mb = (int)($data['client_max_body_size'] ?? $site['client_max_body_size']);
        $with_logs = !empty($data['with_logs']) ? 1 : 0;
        db()->prepare('UPDATE sites SET server_names=:s, root=:r, php_version=:p, client_max_body_size=:mb, with_logs=:wl, updated_at=:u WHERE id=:id')
            ->execute([':s'=>$server_names, ':r'=>$root, ':p'=>$php, ':mb'=>$mb, ':wl'=>$with_logs, ':u'=>date('c'), ':id'=>$id]);
        $cmd = sprintf(
            'sudo -n %s %s %s %s %s %d %d 2>&1',
            escapeshellarg($this->bin('site_edit.sh')),
            escapeshellarg($site['name']),
            escapeshellarg($server_names),
            escapeshellarg($root),
            escapeshellarg($php),
            $mb,
            $with_logs
        );
        $out = shell_exec($cmd) ?? '';
        return ['ok'=>true,'output'=>$out];
    }

    public function toggle(int $id, bool $enable): array
    {
        require_once __DIR__ . '/../../lib/db.php';
        $site = $this->find($id); if (!$site) return ['ok'=>false,'output'=>'Site not found'];
        $cmd = sprintf('sudo -n %s %s 2>&1',
            escapeshellarg($this->bin($enable ? 'site_enable.sh' : 'site_disable.sh')),
            escapeshellarg($site['name'])
        );
        $out = shell_exec($cmd) ?? '';
        db()->prepare('UPDATE sites SET enabled=:e, updated_at=:u WHERE id=:id')->execute([':e'=>$enable?1:0, ':u'=>date('c'), ':id'=>$id]);
        return ['ok'=>true,'output'=>$out];
    }

    public function delete(int $id, bool $deleteRoot): array
    {
        require_once __DIR__ . '/../../lib/db.php';
        $site = $this->find($id); if (!$site) return ['ok'=>false,'output'=>'Site not found'];
        $cmd = sprintf('sudo -n %s %s %s 2>&1',
            escapeshellarg($this->bin('site_delete.sh')),
            escapeshellarg($site['name']),
            escapeshellarg($deleteRoot ? 'yes' : 'no')
        );
        $out = shell_exec($cmd) ?? '';
        db()->prepare('DELETE FROM sites WHERE id=:id')->execute([':id'=>$id]);
        return ['ok'=>true,'output'=>$out];
    }
}
