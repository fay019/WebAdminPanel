<?php
namespace App\Helpers;
final class Response {
    public static function view(string $view, array $data = []): void {
        // Expose provided data to the view scope
        if (!empty($data)) { extract($data, EXTR_SKIP); }
        $base = realpath(__DIR__ . '/../Views');
        if ($base === false) { http_response_code(500); echo 'Views base path not found'; return; }
        $relative = str_replace(['.', '\\'], ['/', '/'], $view) . '.php';
        $__view_file = $base . DIRECTORY_SEPARATOR . $relative;
        if (!is_file($__view_file)) { http_response_code(500); echo 'Vue introuvable: ' . htmlspecialchars($__view_file, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); return; }
        $layout = $base . '/layouts/layout.php';
        // Also set a global for any partials that might rely on it
        $GLOBALS['__view_file'] = $__view_file;
        if (is_file($layout)) { include $layout; }
        else { include $__view_file; }
    }
    public static function json($payload, int $status=200): void {
        http_response_code($status); header('Content-Type: application/json; charset=utf-8');
        if (is_string($payload)) { echo $payload; }
        else { echo json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); }
    }
    public static function redirect(string $to, int $status=302): void { header("Location: $to", true, $status); exit; }
}
