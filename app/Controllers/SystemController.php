<?php
namespace App\Controllers;
use App\Helpers\Response;
use App\Services\PhpManageService;

class SystemController {
    public function phpManage(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            // Stream if ?stream=1 or ajax=1 like legacy
            $forceStream = (($_GET['stream'] ?? '') === '1') || !empty($_POST['ajax']);
            $action = $_POST['action'] ?? '';
            $sel = trim($_POST['version_sel'] ?? '');
            $custom = trim($_POST['version_custom'] ?? '');
            $ver = $custom !== '' ? $custom : $sel;
            if ($ver === '' && isset($_POST['version'])) { $ver = trim($_POST['version']); }

            $svc = new PhpManageService();
            if ($forceStream) {
                $svc->execute($action, $ver, true); // will stream and exit
                return;
            }
            $out = $svc->execute($action, $ver, false);
            // Non-stream fallback: flash + redirect
            require_once __DIR__ . '/../../partials/flash.php';
            $ok = is_string($out) && str_starts_with($out, 'OK:');
            flash($ok ? 'ok' : 'err', nl2br(htmlspecialchars($out ?? '')), true);
            header('Location: /php_manage');
            exit;
        }

        // GET: list versions and render view
        $svc = new PhpManageService();
        $rows = $svc->listJson();
        $commonChoices = ['7.4','8.0','8.1','8.2','8.3','8.4'];
        $installed = array_map(fn($r) => $r['ver'], $rows);
        $choices = array_values(array_diff($commonChoices, $installed));
        Response::view('system/php_manage', compact('rows','choices'));
    }
}
