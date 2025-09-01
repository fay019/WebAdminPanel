<?php
namespace App\Services;

class PhpManageService {
    private string $deploy = '/var/www/adminpanel/bin/php_manage.sh';
    private string $local;
    public function __construct(){ $this->local = __DIR__ . '/../../bin/php_manage.sh'; }
    private function script(): string { return file_exists($this->deploy) ? $this->deploy : $this->local; }

    public function listJson(): array {
        $cmd = 'sudo -n ' . escapeshellarg($this->script()) . ' list --json';
        $json = shell_exec($cmd . ' 2>&1');
        $rows = json_decode((string)$json, true);
        return is_array($rows) ? $rows : [];
    }

    public function execute(string $action, string $version, bool $stream=false): string {
        if ($version !== '' && !preg_match('/^\d+\.\d+$/', $version)) {
            return '[ERREUR] Version invalide: '.$version; // keep legacy wording
        }
        $bin = $this->script();
        $cmd = null;
        if ($action === 'install' && $version !== '') {
            $cmd = 'sudo -n ' . escapeshellarg($bin) . ' install ' . escapeshellarg($version);
        } elseif ($action === 'remove' && $version !== '') {
            $cmd = 'sudo -n ' . escapeshellarg($bin) . ' remove ' . escapeshellarg($version);
        } elseif ($action === 'restart' && $version !== '') {
            $cmd = 'sudo -n ' . escapeshellarg($bin) . ' restart ' . escapeshellarg($version);
        } else {
            return '[ERREUR] Action manquante ou invalide.';
        }
        if ($stream) {
            $this->stream($cmd, $bin);
            return '';
        }
        $out = shell_exec($cmd . ' 2>&1');
        return $out === null ? '' : $out;
    }

    private function stream(string $cmd, string $bin): void {
        header('Content-Type: text/plain; charset=UTF-8');
        header('X-Accel-Buffering: no');
        header('Cache-Control: no-store');
        @ob_end_flush();
        ob_implicit_flush(true);
        ignore_user_abort(true);
        $ts = date('c');
        echo "[INFO] $ts\n";
        echo "[INFO] Exécution: $cmd\n";
        echo "[INFO] Astuce: si rien ne s'affiche, vérifiez sudoers (install.sh) et /var/log/nginx/error.log\n\n";
        // pre-check sudoers as legacy
        $check = shell_exec('sudo -n ' . escapeshellarg($bin) . ' --help 2>&1');
        if ($check !== null && (str_contains($check, 'a password is required') || str_contains($check, 'not in the sudoers'))) {
            echo "[ERREUR] sudo NOPASSWD manquant pour $bin.\n";
            echo "Astuce: relancez install.sh pour déployer /etc/sudoers.d/adminpanel.\n";
            exit(1);
        }
        $des = [1=>['pipe','w'],2=>['pipe','w']];
        $proc = @proc_open($cmd, $des, $pipes);
        if (!is_resource($proc)) { echo "[ERREUR] Impossible de lancer la commande. Vérifiez que 'sudo' autorise le script et que le binaire existe.\n"; exit(1);}        
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $lastFlush = microtime(true);
        while (true) {
            $out = stream_get_contents($pipes[1]);
            $err = stream_get_contents($pipes[2]);
            if ($out !== '') echo $out;
            if ($err !== '') echo $err;
            $status = proc_get_status($proc);
            if (!$status['running']) break;
            $now = microtime(true);
            if ($now - $lastFlush > 2.0 && $out === '' && $err === '') { echo "."; $lastFlush = $now; }
            usleep(80000);
        }
        $code = proc_close($proc);
        echo "\n-- Fin (code {$code}) --\n";
        if ($code !== 0) { echo "[ERREUR] La commande s'est terminée avec un code non nul ($code).\n"; }
        else { echo "[OK] Terminé avec succès.\n"; }
        exit;
    }
}
