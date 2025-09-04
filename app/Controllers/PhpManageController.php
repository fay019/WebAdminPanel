<?php
namespace App\Controllers;

use App\Helpers\Response;
use App\Services\PhpManageService;

class PhpManageController
{
    private PhpManageService $svc;

    public function __construct()
    {
        $this->svc = new PhpManageService();
    }

    // GET /php/manage
    public function index(): void
    {
        $rows = $this->svc->listVersions();
        $commonChoices = ['7.4','8.0','8.1','8.2','8.3','8.4'];
        $installed = array_map(fn($r) => $r['ver'] ?? '', $rows);
        $choices = array_values(array_diff($commonChoices, $installed));
        Response::view('php_manage/index', compact('rows','choices'));
    }

    // POST /php/manage/action (non-stream)
    public function runAction(): void
    {
        // CSRF + Auth are enforced globally via middlewares
        $action = $_POST['action'] ?? '';
        $ver = $_POST['ver'] ?? ($_POST['version'] ?? ''); // accept legacy field name too
        $ver = is_string($ver) ? trim($ver) : '';

        if (!in_array($action, ['install','remove','restart'], true)) {
            $this->flashAndRedirect('[ERREUR] Action manquante ou invalide.');
            return;
        }
        if ($action !== 'restart' && $ver === '') {
            // install/remove require ver; restart also requires in our legacy flow
            $this->flashAndRedirect('[ERREUR] Version manquante.');
            return;
        }

        $cmd = $this->svc->buildCommand($action, $ver !== '' ? $ver : null);
        $out = $this->svc->runSync($cmd);
        $ok = is_string($out) && str_starts_with($out, 'OK:');
        $msg = nl2br(htmlspecialchars($out ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        $this->flashAndRedirect($msg, $ok ? 'ok' : 'err');
    }

    // POST /php/manage/stream (streaming text/plain; long-running)
    public function streamAction(): void
    {
        $action = $_POST['action'] ?? '';
        $ver = $_POST['ver'] ?? ($_POST['version'] ?? '');
        $ver = is_string($ver) ? trim($ver) : '';
        if (!in_array($action, ['install','remove','restart'], true)) {
            $this->sendStreamErrorAndExit("[ERREUR] Action manquante ou invalide.\n");
            return;
        }
        if ($action !== 'restart' && $ver === '') {
            $this->sendStreamErrorAndExit("[ERREUR] Version manquante.\n");
            return;
        }
        $cmd = $this->svc->buildCommand($action, $ver !== '' ? $ver : null);
        // Set streaming headers and stream via service
        header('Content-Type: text/plain; charset=UTF-8');
        header('X-Accel-Buffering: no');
        header('Cache-Control: no-store');
        $this->svc->streamCommand($cmd); // never returns
    }

    // POST /php_manage.php (legacy compat)
    public function legacyPost(): void
    {
        $ajax = ($_POST['ajax'] ?? '') === '1';
        $stream = ($_GET['stream'] ?? $_POST['stream'] ?? '') === '1';
        if ($ajax || $stream) {
            $this->streamAction();
            return;
        }
        $this->runAction();
    }

    private function flashAndRedirect(string $message, string $type='err'): void
    {
        require_once __DIR__ . '/../../partials/flash.php';
        if (!function_exists('flash')) { Response::redirect('/php/manage'); return; }
        flash($type, $message, true);
        Response::redirect('/php/manage');
    }

    private function sendStreamErrorAndExit(string $msg): void
    {
        header('Content-Type: text/plain; charset=UTF-8');
        header('X-Accel-Buffering: no');
        header('Cache-Control: no-store');
        echo $msg;
        exit(1);
    }
}
