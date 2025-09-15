<?php
namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\FileUtils;

final class ErrorLogController {
    private string $path = '/srv/www/webadminpanel-v2/logs/error.log';

    private function requireAdmin(): bool {
        // In this app, being logged-in equals admin rights.
        if (!function_exists('is_logged_in')) { require_once __DIR__.'/../../lib/auth.php'; }
        if (!is_logged_in()) {
            http_response_code(403);
            Response::fail(403, 'Accès refusé');
            return false;
        }
        return true;
    }

    private function tailLines(string $file, int $lines = 500): array {
        // Prefer helper if available
        if (class_exists('App\\Helpers\\FileUtils')) {
            return FileUtils::tailFile($file, $lines);
        }
        // Minimal fallback if helper file not deployed
        if ($lines <= 0) return [];
        if (!is_readable($file)) return ["[!] Fichier non lisible: $file"];
        $f = @fopen($file, 'rb');
        if ($f === false) return ["[!] Impossible d'ouvrir: $file"];
        $buffer = '';
        $pos = -1;
        $count = 0;
        $result = [];
        fseek($f, 0, SEEK_END);
        $size = ftell($f);
        if ($size === 0) { fclose($f); return []; }
        while ($count < $lines && -$pos < $size) {
            fseek($f, $pos, SEEK_END);
            $ch = fgetc($f);
            if ($ch === "\n" && $buffer !== '') {
                $result[] = strrev($buffer);
                $buffer = '';
                $count++;
            } else {
                $buffer .= $ch;
            }
            $pos--;
        }
        if ($buffer !== '') $result[] = strrev($buffer);
        fclose($f);
        return array_reverse($result);
    }

    public function index(): void {
        if (!$this->requireAdmin()) return;
        $count = isset($_GET['lines']) ? max(1, min(2000, (int)$_GET['lines'])) : 200; // default 200 lines
        $lines = $this->tailLines($this->path, $count);
        $ts = time();
        // JSON partial mode for refresh without full page reload
        if (isset($_GET['partial']) && ($_GET['partial'] === '1' || $_GET['partial'] === 'true')) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok'=>true,'ts'=>$ts,'lines'=>$lines], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            return;
        }
        Response::view('dashboard/error_log', compact('lines','ts'));
    }

    public function download(): void {
        if (!$this->requireAdmin()) return;
        $file = $this->path;
        if (!is_readable($file)) {
            http_response_code(404);
            Response::fail(404, 'Fichier de log introuvable');
            return;
        }
        // Send as attachment; avoid output buffering issues
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="error.log"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        // Attempt to set Content-Length if possible
        $size = @filesize($file);
        if (is_int($size) && $size >= 0) { header('Content-Length: '.$size); }
        @readfile($file);
    }
}
