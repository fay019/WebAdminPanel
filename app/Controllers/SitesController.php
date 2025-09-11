<?php
namespace App\Controllers;

use App\Helpers\Response;
use App\Services\SitesService;

final class SitesController
{
    private SitesService $svc;
    public function __construct() { $this->svc = new SitesService(); }

    public function index(): void
    {
        $sites = $this->svc->list();
        // Compute orphan directories like legacy sites_list.php
        require_once __DIR__ . '/../../lib/db.php';
        $rows = db()->query('SELECT root FROM sites')->fetchAll(\PDO::FETCH_COLUMN);
        $rootsInDb = array_map(fn($r) => rtrim((string)$r, '/'), $rows ?: []);
        $orphans = [];
        foreach (glob('/var/www/*', GLOB_ONLYDIR) as $dir) {
            $base = basename($dir);
            if (in_array($base, ['adminpanel','html'], true)) { continue; }
            $d = rtrim($dir, '/');
            if (in_array($d, $rootsInDb, true)) { continue; }
            if (in_array($d . '/public', $rootsInDb, true)) { continue; }
            $orphans[] = ['name' => $base, 'path' => $dir];
        }
        usort($orphans, fn($a,$b)=>strcmp($a['name'],$b['name']));
        Response::view('sites/index', compact('sites','orphans'));
    }

    public function create(): void
    {
        Response::view('sites/create', []);
    }

    public function store(): void
    {
        $data = [
            'name' => $_POST['name'] ?? '',
            'server_names' => $_POST['server_names'] ?? '',
            'root' => $_POST['root'] ?? '',
            'php_version' => $_POST['php_version'] ?? '8.3',
            'client_max_body_size' => $_POST['client_max_body_size'] ?? 20,
            'with_logs' => isset($_POST['with_logs']) ? 1 : 0,
            'reset_root' => isset($_POST['reset_root']) ? 1 : 0,
        ];
        $res = $this->svc->create($data);
        $msg = htmlspecialchars((string)($res['output'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        flash($res['ok'] ? 'ok' : 'err', nl2br($msg) ?: ($res['ok']?'Créé.':'Erreur'), true);
        Response::redirect('/sites');
    }

    public function edit(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $site = $this->svc->find($id);
        if (!$site) { http_response_code(404); echo 'Site introuvable'; return; }
        Response::view('sites/edit', compact('site'));
    }

    public function update(): void
    {
        $id = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));
        $res = $this->svc->update($id, $_POST);
        $out = htmlspecialchars((string)($res['output'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        flash($res['ok'] ? 'ok' : 'err', nl2br($out) ?: ($res['ok']?'Enregistré.':'Erreur'), true);
        Response::redirect('/sites/'.$id.'/edit');
    }

    public function show(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $site = $this->svc->find($id);
        if (!$site) { http_response_code(404); echo 'Site introuvable'; return; }
        Response::view('sites/show', compact('site'));
    }

    public function destroy(): void
    {
        $id = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));
        $deleteRoot = (($_POST['delete_root'] ?? ($_GET['delete_root'] ?? '0')) === '1');
        $res = $this->svc->delete($id, $deleteRoot);
        $out = htmlspecialchars((string)($res['output'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        flash($res['ok'] ? 'ok' : 'err', nl2br($out) ?: ($res['ok']?'Supprimé.':'Erreur'), true);
        Response::redirect('/sites');
    }

    public function toggle(): void
    {
        $id = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));
        $enable = (($_POST['enable'] ?? ($_GET['enable'] ?? '1')) === '1');
        $res = $this->svc->toggle($id, $enable);
        $out = htmlspecialchars((string)($res['output'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        flash($res['ok'] ? 'ok' : 'err', nl2br($out) ?: ($res['ok']?'OK.':'Erreur'), true);
        $ret = '/sites';
        Response::redirect($ret);
    }
}
