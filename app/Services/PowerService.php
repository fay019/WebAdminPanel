<?php
namespace App\Services;

class PowerService {
    private string $deploy = '/var/www/adminpanel/bin/power.sh';
    private string $local;
    public function __construct(){ $this->local = __DIR__ . '/../../bin/power.sh'; }

    private function script(): string { return file_exists($this->deploy) ? $this->deploy : $this->local; }

    // Execute power action; if $stream true, passthru streaming output
    public function execute(string $action, bool $stream=false): string {
        if (!in_array($action, ['shutdown','reboot'], true)) { return 'ERR: invalid action'; }
        $cmd = 'sudo -n ' . escapeshellarg($this->script()) . ' ' . escapeshellarg($action);
        if ($stream) {
            header('Content-Type: text/plain; charset=utf-8');
            passthru($cmd, $code);
            return '';
        }
        $out = shell_exec($cmd . ' 2>&1');
        return $out ?? 'ERR: command failed';
    }
}
