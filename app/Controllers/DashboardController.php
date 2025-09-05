<?php
namespace App\Controllers;
use App\Helpers\Response;
use App\Services\SysInfoService;
use App\Services\PowerService;
use App\Services\SystemInfoService;

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
            $svc->execute($action, true);
            return;
        }
        $out = $svc->execute($action, false);
        $ok = is_string($out) && str_starts_with($out, 'OK:');
        if ($ok) {
            // Normalize readable message for UI
            $msg = ($action === 'shutdown') ? "Arrêt demandé. Le système va s’éteindre." : "Redémarrage demandé. Le système va redémarrer.";
            Response::json(['ok' => true, 'message' => $msg, 'raw' => $out], 200);
            return;
        }
        $msg = $out !== '' ? $out : 'Erreur: exécution power échouée';
        Response::json(['ok' => false, 'error' => 'power_failed', 'message' => $msg], 500);
    }
}
