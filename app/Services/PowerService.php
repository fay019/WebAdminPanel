<?php
namespace App\Services;

class PowerService {
    private string $deploy = '/var/www/adminpanel/bin/power.sh';
    private string $local;
    public function __construct(){ $this->local = __DIR__ . '/../../bin/power.sh'; }

    private function script(): string { return file_exists($this->deploy) ? $this->deploy : $this->local; }

    private function findBin(array $candidates): string {
        foreach ($candidates as $bin) {
            if (is_string($bin) && $bin !== '' && (file_exists($bin) || is_link($bin))) {
                return $bin;
            }
        }
        return '';
    }

    private function buildCommand(string $action): array {
        $script = $this->script();
        if (file_exists($script)) {
            return ['sudo -n ' . escapeshellarg($script) . ' ' . escapeshellarg($action), true];
        }
        // Script missing on target
        if (class_exists('\\ErrorLogger')) { \ErrorLogger::log('power_exec', 'Script introuvable', ['script'=>$script]); }
        // Fallback to direct system commands if script is missing on target
        if ($action === 'reboot') {
            $reboot = $this->findBin(['/sbin/reboot','/usr/sbin/reboot','/bin/systemctl','/usr/bin/systemctl']);
            if ($reboot !== '') {
                if (basename($reboot) === 'systemctl') {
                    return ['sudo -n ' . escapeshellarg($reboot) . ' reboot', false];
                }
                return ['sudo -n ' . escapeshellarg($reboot), false];
            }
        }
        if ($action === 'shutdown') {
            $shutdown = $this->findBin(['/sbin/shutdown','/usr/sbin/shutdown','/bin/systemctl','/usr/bin/systemctl']);
            if ($shutdown !== '') {
                if (basename($shutdown) === 'systemctl') {
                    return ['sudo -n ' . escapeshellarg($shutdown) . ' poweroff', false];
                }
                return ['sudo -n ' . escapeshellarg($shutdown) . ' -h now', false];
            }
        }
        // No viable command found
        return ['sudo: power command not found', false];
    }

    // Execute power action; if $stream true, passthru streaming output
    // Returns an array: ['out' => string, 'code' => int]
    public function execute(string $action, bool $stream=false): array {
        if (!in_array($action, ['shutdown','reboot'], true)) { return ['out' => 'ERR: invalid action', 'code' => 1]; }
        [$cmd, $usesScript] = $this->buildCommand($action);
        if ($cmd === 'sudo: power command not found') {
            return ['out' => 'ERR: power command not found', 'code' => 127];
        }
        if ($stream) {
            header('Content-Type: text/plain; charset=utf-8');
            $code = 1;
            passthru($cmd, $code);
            if ($code !== 0 && class_exists('\\ErrorLogger')) { \ErrorLogger::log('power_exec', 'Retour non nul', ['code'=>(int)$code]); }
            return ['out' => '', 'code' => (int)$code];
        }
        $output = [];
        $code = 1;
        @exec($cmd . ' 2>&1', $output, $code);
        $out = implode("\n", $output);
        if ($code !== 0 && class_exists('\\ErrorLogger')) {
            \ErrorLogger::log('power_exec', 'Retour non nul', ['code'=>(int)$code]);
            if ($out !== '') { \ErrorLogger::log('power_exec', 'stderr', ['stderr'=>substr($out,0,1000)]); }
        }
        return ['out' => $out, 'code' => (int)$code];
    }
}
