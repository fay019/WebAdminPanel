<?php
namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\FileUtils;

// Defensive include in case autoloading fails in some environments
if (!class_exists('App\\Helpers\\FileUtils')) {
    require_once __DIR__ . '/../Helpers/FileUtils.php';
}

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

    public function index(): void {
        if (!$this->requireAdmin()) return;
        $lines = FileUtils::tailFile($this->path, 500);
        Response::view('dashboard/error_log', compact('lines'));
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
