<?php
namespace App\Services;

class PhpManageService {
    private string $deploy = '/var/www/adminpanel/bin/php_manage.sh';
    private string $local;
    public function __construct(){ $this->local = __DIR__ . '/../../bin/php_manage.sh'; }

    // New API
    public function resolveBinary(): string {
        return file_exists($this->deploy) ? $this->deploy : $this->local;
    }

    public function listVersions(): array {
        $cmd = 'sudo -n ' . escapeshellarg($this->resolveBinary()) . ' list --json';
        $json = shell_exec($cmd . ' 2>&1');
        $rows = json_decode((string)$json, true);
        return is_array($rows) ? $rows : [];
    }

    public function buildCommand(string $action, ?string $ver): string {
        $bin = $this->resolveBinary();
        $action = trim($action);
        if ($ver !== null && $ver !== '' && !preg_match('/^\d+\.\d+$/', $ver)) {
            // Keep legacy wording
            return 'echo '.escapeshellarg('[ERREUR] Version invalide: '.$ver);
        }
        if (!in_array($action, ['install','remove','restart'], true)) {
            return 'echo '.escapeshellarg('[ERREUR] Action manquante ou invalide.');
        }
        $cmd = 'sudo -n ' . escapeshellarg($bin) . ' ' . escapeshellarg($action);
        if ($ver !== null && $ver !== '') { $cmd .= ' ' . escapeshellarg($ver); }
        return $cmd;
    }

    public function runSync(string $cmd): string {
        $out = shell_exec($cmd . ' 2>&1');
        return $out === null ? '' : $out;
    }

    public function streamCommand(string $cmd): void {
        @ob_end_flush();
        ob_implicit_flush(true);
        ignore_user_abort(true);
        $bin = $this->resolveBinary();
        // Pre-check sudoers like legacy
        $check = shell_exec('sudo -n ' . escapeshellarg($bin) . ' --help 2>&1');
        if ($check !== null && (str_contains($check, 'a password is required') || str_contains($check, 'not in the sudoers'))) {
            echo "[ERREUR] sudo NOPASSWD manquant pour $bin.\n";
            echo "Astuce: relancez install.sh pour déployer /etc/sudoers.d/adminpanel.\n";
            exit(1);
        }
        $des = [1=>['pipe','w'],2=>['pipe','w']];
        $proc = @proc_open($cmd, $des, $pipes);
        if (!is_resource($proc)) {
            echo "[ERREUR] Impossible de lancer la commande. Vérifiez que 'sudo' autorise le script et que le binaire existe.\n";
            exit(1);
        }
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $last = microtime(true);
        while (true) {
            $out = stream_get_contents($pipes[1]);
            $err = stream_get_contents($pipes[2]);
            if ($out !== '') echo $out;
            if ($err !== '') echo $err;
            $st = proc_get_status($proc);
            if (!$st['running']) break;
            $now = microtime(true);
            if ($now - $last > 2.0 && $out === '' && $err === '') { echo '.'; $last = $now; }
            usleep(80000);
        }
        $code = proc_close($proc);
        echo "\n-- Fin (code {$code}) --\n";
        if ($code !== 0) { echo "[ERREUR] La commande s'est terminée avec un code non nul ($code).\n"; }
        else { echo "[OK] Terminé avec succès.\n"; }
        exit;
    }

    // Backward compat methods (used by SystemController)
    private function script(): string { return $this->resolveBinary(); }

    public function listJson(): array { return $this->listVersions(); }

    public function execute(string $action, string $version, bool $stream=false): string {
        $cmd = $this->buildCommand($action, $version);
        if ($stream) { $this->streamCommand($cmd); return ''; }
        return $this->runSync($cmd);
    }
}
