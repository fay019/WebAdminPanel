<?php
namespace App\Controllers;
use App\Helpers\Response;
use App\Services\SysInfoService;
use App\Services\PowerService;
use App\Services\SystemInfoService;
use App\Services\StorageService;
use App\Services\NvmeHealthService;

class DashboardController {
    public function index(): void {
        require_once __DIR__.'/../../lib/db.php';
        $sitesCount = (int)db()->query('SELECT COUNT(*) FROM sites')->fetchColumn();

        $sysSvc = new SysInfoService();
        $sysinfo = $sysSvc->snapshot();
        $php_fpm_compact = $sysSvc->phpFpmCompact($sysinfo);

        Response::view('dashboard/index', compact('sitesCount','sysinfo','php_fpm_compact'));
    }

    // New normalized JSON endpoint (cached)
    public function api(): void {
        header('Content-Type: application/json; charset=UTF-8');
        $svc = new SystemInfoService(4);
        $data = $svc->get();
        echo json_encode($data, JSON_UNESCAPED_SLASHES);
        exit;
    }

    // Authenticated JSON endpoint for storage volumes
    public function storage(): void {
        header('Content-Type: application/json; charset=UTF-8');
        $svc = new StorageService(10);
        $data = $svc->get();
        echo json_encode($data, JSON_UNESCAPED_SLASHES);
        exit;
    }

    public function sysinfo(): void {
        // Legacy: keep streaming the existing bin/sysinfo.sh output
        header('Content-Type: application/json; charset=utf-8');
        $sysSvc = new SysInfoService();
        $sysSvc->streamRaw();
        exit;
    }

    // GET on power endpoints -> JSON method not allowed (no HTML)
    public function powerMethodNotAllowed(): void {
        Response::json(['ok' => false, 'error' => 'method_not_allowed'], 405);
    }

    public function power(): void {
        // JSON/POST only. Optional stream=1 for passthrough.
        $action = $_POST['action'] ?? '';
        $stream = isset($_GET['stream']) ? (string)$_GET['stream'] : (isset($_POST['stream']) ? (string)$_POST['stream'] : '');
        $svc = new PowerService();
        if ($stream === '1' || $stream === 'true') {
            // streaming passthrough for compatibility
            $res = $svc->execute($action, true);
            // In streaming mode, rely on exit code to infer success
            $code = is_array($res) ? ($res['code'] ?? 1) : 1;
            if ((int)$code === 0) {
                $msg = ($action === 'shutdown') ? "Arrêt demandé. Le système va s’éteindre." : "Redémarrage demandé. Le système va redémarrer.";
                Response::json(['ok' => true, 'action' => ($action ?: null), 'code' => 'accepted', 'message' => $msg], 200);
            }
            return;
        }
        $res = $svc->execute($action, false);
        $txt = is_array($res) ? trim((string)($res['out'] ?? '')) : trim((string)$res);
        $exit = is_array($res) ? (int)($res['code'] ?? 1) : 1;
        // Determine success: exit code 0 OR stdout contains OK marker anywhere
        $okMarker = ($action === 'shutdown') ? 'OK: shutdown triggered' : (($action === 'reboot') ? 'OK: reboot triggered' : 'OK:');
        $ok = ($exit === 0) || ($txt !== '' && (str_contains($txt, $okMarker) || str_contains($txt, 'OK:')));
        if ($ok) {
            $msg = ($action === 'shutdown') ? "Arrêt demandé. Le système va s’éteindre." : "Redémarrage demandé. Le système va redémarrer.";
            Response::json(['ok' => true, 'action' => ($action ?: null), 'code' => 'accepted', 'message' => $msg], 200);
            return;
        }
        // Map real errors before dispatch
        if (!in_array($action, ['shutdown','reboot'], true)) {
            Response::json(['ok' => false, 'error' => 'invalid_action', 'message' => 'Action invalide'], 400);
            return;
        }
        $lower = strtolower($txt);
        if ($lower === '' ) {
            Response::json(['ok' => false, 'error' => 'power_failed', 'message' => 'Aucune sortie du script'], 500);
            return;
        }
        if (str_contains($lower, 'sudo') && (str_contains($lower, 'not') || str_contains($lower,'denied') || str_contains($lower,'password'))) {
            Response::json(['ok' => false, 'error' => 'power_permission_denied', 'message' => $txt], 500);
            return;
        }
        if (str_contains($lower, 'no such file') || str_contains($lower, 'not found') || str_contains($lower, 'cannot open')) {
            Response::json(['ok' => false, 'error' => 'power_script_missing', 'message' => $txt], 500);
            return;
        }
        Response::json(['ok' => false, 'error' => 'power_failed', 'message' => $txt], 500);
    }

    // NVMe Health endpoint (10 min cache)
    public function nvmeHealth(): void {
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: public, max-age=600, s-maxage=600');
        $svc = new NvmeHealthService(600);
        $data = $svc->get();
        echo json_encode($data, JSON_UNESCAPED_SLASHES);
        exit;
    }
}
