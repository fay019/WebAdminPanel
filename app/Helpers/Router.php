<?php
class Router {
    private array $routes;
    public function __construct(array $routes){ $this->routes = $routes; }

    public function dispatch(): void {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $query = $_SERVER['QUERY_STRING'] ?? '';

        // Legacy AJAX: exact match of path + query
        if ($query) {
            $ajaxKey = $uri.'?'.$query;
            if (!empty($this->routes['GET_AJAX'][$ajaxKey])) {
                $this->invoke($this->routes['GET_AJAX'][$ajaxKey]);
                return;
            }
        }

        $table = $this->routes[$method] ?? [];
        $target = $table[$uri] ?? null;
        if (is_array($target) && isset($target['redirect'])) {
            header('Location: '.$target['redirect'], true, 302);
            exit;
        }
        if (is_string($target)) { $this->invoke($target); return; }

        http_response_code(404);
        echo file_exists(__DIR__.'/../../../public/404.html') ? file_get_contents(__DIR__.'/../../../public/404.html') : 'Not Found';
    }

    private function invoke(string $controllerAction): void {
        [$ctrl, $action] = explode('@', $controllerAction, 2);
        $class = 'App\\Controllers\\'.$ctrl;
        if (!class_exists($class)) { http_response_code(500); echo "Controller $class not found"; return; }
        $obj = new $class();
        if (!method_exists($obj, $action)) { http_response_code(500); echo "Action $action not found"; return; }
        $obj->$action();
    }
}
