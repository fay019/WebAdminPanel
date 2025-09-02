<?php
namespace App\Helpers;
class Response {
    public static function view(string $view, array $data = []): void {
        // Expose provided data to the view scope
        if (!empty($data)) { extract($data, EXTR_SKIP); }
        $base = __DIR__.'/../Views/';
        $viewFile = $base . $view . '.php';
        $layout = $base . 'layouts/layout.php';
        if (!file_exists($viewFile)) { http_response_code(500); echo 'View not found'; return; }
        // Ensure the layout can locate the resolved view file
        $GLOBALS['__view_file'] = $viewFile;
        if (file_exists($layout)) { include $layout; }
        else { include $GLOBALS['__view_file']; }
    }
    public static function json($payload, int $status=200): void {
        http_response_code($status); header('Content-Type: application/json; charset=utf-8');
        if (is_string($payload)) { echo $payload; }
        else { echo json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); }
    }
    public static function redirect(string $to, int $status=302): void { header("Location: $to", true, $status); exit; }
}
