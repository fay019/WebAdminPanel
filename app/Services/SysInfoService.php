<?php
namespace App\Services;

class SysInfoService {
    private string $deploy = '/var/www/adminpanel/bin/sysinfo.sh';
    private string $local;
    public function __construct(){ $this->local = __DIR__ . '/../../bin/sysinfo.sh'; }

    private function script(): string { return file_exists($this->deploy) ? $this->deploy : $this->local; }

    // Stream raw output (JSON/text) exactly as the script provides
    public function streamRaw(): void {
        $cmd = 'sudo -n ' . escapeshellarg($this->script());
        passthru($cmd);
    }

    // Return parsed snapshot as array for initial rendering
    public function snapshot(): array {
        $out = shell_exec('sudo -n ' . escapeshellarg($this->script()) . ' 2>&1');
        $sysinfo = [ 'cpu_temp'=>'n/a','ram'=>'n/a','disk'=>'n/a' ];
        if ($out) {
            foreach (explode("\n", trim($out)) as $line) {
                if (str_contains($line,'=')) { [$k,$v]=explode('=',$line,2); $sysinfo[$k]=trim($v); }
            }
        }
        if (empty($sysinfo['processes']) || $sysinfo['processes']==='n/a') {
            $sysinfo['processes'] = trim(shell_exec('ps -e --no-headers | wc -l')) ?: 'n/a';
        }
        if (empty($sysinfo['php_sockets']) || $sysinfo['php_sockets']==='n/a') {
            $socks = glob('/run/php/php*-fpm.sock') ?: [];
            $sysinfo['php_sockets'] = $socks ? implode(' ', array_map('basename', $socks)) : 'n/a';
        }
        if (empty($sysinfo['nginx_version']) || $sysinfo['nginx_version']==='n/a') {
            $ver = trim(shell_exec('nginx -v 2>&1'));
            $sysinfo['nginx_version'] = $ver ? preg_replace('/^nginx version:\s*/','',$ver) : 'n/a';
        }
        return $sysinfo;
    }

    public function phpFpmCompact(array $sysinfo): array {
        $php_fpm_compact = [];
        $seen = [];
        $socketList = [];
        if (!empty($sysinfo['php_fpm_sockets']) && $sysinfo['php_fpm_sockets'] !== 'n/a') {
            foreach (preg_split('/\s*,\s*/', trim($sysinfo['php_fpm_sockets'])) as $s) { if ($s !== '') { $socketList[] = trim($s); } }
        } else { foreach (glob('/run/php/php*-fpm.sock') ?: [] as $s) { $socketList[] = basename($s); } }
        foreach ($socketList as $sockName) {
            if (!preg_match('/^php(?:(\d+\.\d+))?-fpm\.sock$/', $sockName, $m)) continue;
            $ver   = $m[1] ?? null; $label = $ver ? "php{$ver}" : 'php'; if (isset($seen[$label])) continue;
            $sockPath = "/run/php/{$sockName}"; $sockOk = file_exists($sockPath);
            $svcName  = $ver ? "php{$ver}-fpm" : 'php-fpm';
            $svcOk    = (trim(shell_exec("systemctl is-active {$svcName} 2>/dev/null || true")) === 'active');
            $php_fpm_compact[] = ['label'=>$label,'v'=>$ver?:'','ok'=>($sockOk && $svcOk),'sock'=>$sockOk,'svc'=>$svcOk];
            $seen[$label] = true;
        }
        usort($php_fpm_compact, fn($a,$b)=>strnatcmp($a['label'],$b['label']));
        if (!$php_fpm_compact) {
            foreach (['8.2','8.3','8.4'] as $v) {
                $sockOk = file_exists("/run/php/php{$v}-fpm.sock");
                $svcOk  = (trim(shell_exec("systemctl is-active php{$v}-fpm 2>/dev/null || true")) === 'active');
                $php_fpm_compact[] = ['label'=>"php{$v}",'v'=>$v,'ok'=>($sockOk && $svcOk),'sock'=>$sockOk,'svc'=>$svcOk];
            }
        }
        return $php_fpm_compact;
    }
}
