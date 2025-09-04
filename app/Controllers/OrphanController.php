<?php
namespace App\Controllers;

use App\Helpers\Response;

final class OrphanController
{
    private string $deployBin = '/var/www/adminpanel/bin/orphan_delete.sh';
    private string $localBin;

    public function __construct()
    {
        $this->localBin = __DIR__ . '/../../bin/orphan_delete.sh';
    }

    private function resolveBinary(): string
    {
        return is_file($this->deployBin) ? $this->deployBin : $this->localBin;
    }

    // POST /orphan/delete
    public function delete(): void
    {
        // Auth + CSRF already enforced by middlewares
        require_once __DIR__ . '/../../partials/flash.php';
        $dirParam = (string)($_GET['dir'] ?? ($_POST['dir'] ?? ''));
        $back = (string)($_POST['back'] ?? ($_SERVER['HTTP_REFERER'] ?? '/sites_list.php'));
        if ($dirParam === '') {
            flash('err', 'Chemin manquant.');
            Response::redirect($back);
        }
        $real = realpath($dirParam);
        if ($real === false || $real === null) {
            flash('err', 'Dossier introuvable.');
            Response::redirect($back);
        }
        if (strpos($real, '/var/www/') !== 0) {
            flash('err', 'Chemin non autorisé.');
            Response::redirect($back);
        }
        $base = basename($real);
        if (in_array($base, ['adminpanel','html'], true)) {
            flash('err', 'Ce dossier est protégé.');
            Response::redirect($back);
        }
        $bin = $this->resolveBinary();
        $cmd = sprintf('sudo -n %s %s 2>&1', escapeshellarg($bin), escapeshellarg($real));
        $output = shell_exec($cmd);
        if (is_dir($real)) {
            $safe = nl2br(htmlspecialchars((string)$output, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
            flash('err', "Échec de suppression du dossier.<br><pre>{$safe}</pre>", true);
        } else {
            flash('ok', 'Dossier supprimé : <code>'.htmlspecialchars($real, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</code>', true);
        }
        Response::redirect($back);
    }

    // Legacy GET/POST /orphan_delete.php (for direct compat with GET + csrf)
    public function legacyGet(): void
    {
        // legacy used GET with csrf in query
        require_once __DIR__ . '/../../lib/csrf.php';
        if (function_exists('csrf_check')) { csrf_check(); }
        $this->delete();
    }

    public function legacyPost(): void
    {
        $this->delete();
    }
}
