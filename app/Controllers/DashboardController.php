<?php
namespace App\Controllers;
use App\Helpers\Response;
use App\Services\PowerService;
use App\Services\SystemInfoService;
use App\Services\StorageService;
use App\Services\NvmeHealthService;

class DashboardController {
    private function ensureMigrate(): void { require_once __DIR__.'/../../lib/db.php'; migrate(); }

    public function index(): void {
        $this->ensureMigrate();
        $sitesCount = (int)db()->query('SELECT COUNT(*) FROM sites')->fetchColumn();

        $svc = new SystemInfoService(4);
        $data = $svc->get();
        $php_fpm_compact = $svc->phpFpmCompact($data);
        // Build a flat $sysinfo map for the legacy view (safe defaults)
        $diskWww = $data['disk']['srv_www'] ?? null;
        $topCpu = $data['cpu']['top_cpu_proc'] ?? null;
        $topMem = $data['mem']['top_mem_proc'] ?? null;
        $sysinfo = [
            'uptime' => $data['uptime']['human'] ?? 'n/a',
            'disk' => $diskWww ? ($diskWww['used_gb'] . 'G/' . $diskWww['size_gb'] . 'G (' . $diskWww['used_pct'] . '%)') : 'n/a',
            'os' => $data['os']['pretty'] ?? 'n/a',
            'kernel' => $data['os']['kernel'] ?? 'n/a',
            'processes' => $data['processes']['count'] ?? 'n/a',
            'cpu_cores' => $data['cpu']['cores'] ?? 'n/a',
            'top_cpu' => $topCpu ? ($topCpu['cmd'] . ' (' . ($topCpu['cpu'] ?? 0) . '%)') : 'n/a',
            'top_mem' => $topMem ? ($topMem['cmd'] . ' (' . ($topMem['rss_mb'] ?? 0) . ' MiB)') : 'n/a',
            'disk_www' => $diskWww ? ($diskWww['used_gb'] . 'G/' . $diskWww['size_gb'] . 'G (' . $diskWww['used_pct'] . '%)') : 'n/a',
            'php_cli' => $data['php']['cli_version'] ?? 'n/a',
            'nginx_version' => $data['nginx']['version'] ?? 'n/a',
        ];

        Response::view('dashboard/index', compact('sitesCount','php_fpm_compact','sysinfo'));
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
        $svc = new SystemInfoService(4);
        $svc->streamRaw();
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
        // Prioritize missing/invalid path classification before sudo permission checks
        if (str_contains($lower, 'no such file') || str_contains($lower, 'not found') || str_contains($lower, 'cannot open')) {
            Response::json(['ok' => false, 'error' => 'power_script_missing', 'message' => $txt], 500);
            return;
        }
        if (str_contains($lower, 'sudo') && (str_contains($lower, 'denied') || str_contains($lower,'password'))) {
            Response::json(['ok' => false, 'error' => 'power_permission_denied', 'message' => $txt], 500);
            return;
        }
        Response::json(['ok' => false, 'error' => 'power_failed', 'message' => $txt], 500);
    }

    // NVMe Health endpoint (10 min cache)
    public function nvmeHealth(): void {
        header('Content-Type: application/json; charset=UTF-8');
        // Collector updates every 10 minutes; API considers stale after 15 minutes
        header('Cache-Control: public, max-age=60, s-maxage=60');
        $svc = new NvmeHealthService(900);
        $data = $svc->get();
        echo json_encode($data, JSON_UNESCAPED_SLASHES);
        exit;
    }
}
