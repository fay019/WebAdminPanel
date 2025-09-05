<?php
namespace App\Services;

class PowerService {
    private string $deploy = '/var/www/adminpanel/bin/power.sh';
    private string $local;
    public function __construct(){ $this->local = __DIR__ . '/../../bin/power.sh'; }

    private function script(): string { return file_exists($this->deploy) ? $this->deploy : $this->local; }

    // Execute power action; if $stream true, passthru streaming output
    // Returns an array: ['out' => string, 'code' => int]
    public function execute(string $action, bool $stream=false): array {
        if (!in_array($action, ['shutdown','reboot'], true)) { return ['out' => 'ERR: invalid action', 'code' => 1]; }
        $cmd = 'sudo -n ' . escapeshellarg($this->script()) . ' ' . escapeshellarg($action);
        if ($stream) {
            header('Content-Type: text/plain; charset=utf-8');
            $code = 1;
            passthru($cmd, $code);
            return ['out' => '', 'code' => (int)$code];
        }
        $output = [];
        $code = 1;
        @exec($cmd . ' 2>&1', $output, $code);
        $out = implode("\n", $output);
        return ['out' => $out, 'code' => (int)$code];
    }
}
