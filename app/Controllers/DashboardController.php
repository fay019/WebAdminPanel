<?php
namespace App\Controllers;
use App\Helpers\Response;
use App\Services\SysInfoService;
use App\Services\PowerService;

class DashboardController {
    public function index(): void {
        require_once __DIR__.'/../../lib/db.php';
        $sitesCount = (int)db()->query('SELECT COUNT(*) FROM sites')->fetchColumn();

        $sysSvc = new SysInfoService();
        $sysinfo = $sysSvc->snapshot();
        $php_fpm_compact = $sysSvc->phpFpmCompact($sysinfo);

        Response::view('dashboard/index', compact('sitesCount','sysinfo','php_fpm_compact'));
    }

    public function sysinfo(): void {
        header('Content-Type: application/json; charset=utf-8');
        $sysSvc = new SysInfoService();
        $sysSvc->streamRaw();
        exit;
    }

    public function power(): void {
        // Preserve legacy behavior: expects POST with action=shutdown|reboot; optional stream=1
        $action = $_POST['action'] ?? '';
        $stream = isset($_GET['stream']) ? (string)$_GET['stream'] : (isset($_POST['stream']) ? (string)$_POST['stream'] : '');
        $ajax   = ($_POST['ajax'] ?? '') === '1';
        $back   = $_POST['back'] ?? ($_SERVER['HTTP_REFERER'] ?? '/dashboard.php');
        $svc = new PowerService();
        if ($stream==='1' || $stream==='true') { $svc->execute($action, true); return; }
        $out = $svc->execute($action, false);
        if ($ajax) {
            header('Content-Type: text/plain; charset=UTF-8');
            echo $out;
            return;
        }
        // Non-AJAX: simulate legacy flash + redirect
        require_once __DIR__.'/../../partials/flash.php';
        $ok = is_string($out) && str_starts_with($out, 'OK:');
        if ($ok) {
            if ($action === 'shutdown') { flash('ok', "OK: arrêt demandé. Le système va s’éteindre."); }
            else { flash('ok', "OK: redémarrage demandé. Le système va redémarrer."); }
        } else {
            $msg = $out !== '' ? $out : 'ERREUR: aucune sortie';
            flash('err', nl2br(htmlspecialchars($msg)), true);
        }
        header("Location: $back");
        exit;
    }
}
