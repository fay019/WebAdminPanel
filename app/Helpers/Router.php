<?php
use App\Helpers\Response;
class Router {
    private array $routes;
    public function __construct(array $routes){ $this->routes = $routes; }

    public function dispatch(): void {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $isHead = false;
        if ($method === 'HEAD') { $isHead = true; $method = 'GET'; }
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
        // Simple pattern matching for routes with {id}
        foreach ($table as $path => $handler) {
            if (!is_string($handler)) continue;
            if (str_contains($path, '{id}')) {
                $regex = '#^' . preg_quote($path, '#') . '$#';
                $regex = str_replace('\{id\}', '(?P<id>\\d+)', $regex);
                if (preg_match($regex, $uri, $m)) {
                    // expose id via GET for controller convenience
                    if (!isset($_GET['id']) && isset($m['id'])) { $_GET['id'] = $m['id']; }
                    $this->invoke($handler);
                    return;
                }
            }
        }

        // If URI exists for other methods -> 405
        $allowed = [];
        foreach ($this->routes as $m => $map) {
            if ($m === 'GET_AJAX') continue;
            if (!empty($map[$uri])) { $allowed[] = $m; }
        }
        if ($allowed) {
            http_response_code(405);
            header('Allow: '.implode(', ', array_unique($allowed)));
            $this->renderError(405);
            return;
        }

        http_response_code(404);
        $this->renderError(404);
    }

    private function renderError(int $code): void {
        // Prefer new views if Response exists
        if (class_exists('App\\Helpers\\Response')) {
            $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
            $wantsJson = str_contains($accept, 'application/json');
            if ($wantsJson) {
                $msg = ($code===404?'Not Found':($code===405?'Method Not Allowed':'Error'));
                Response::json(['error' => $code, 'message' => $msg], $code);
                return;
            }
            if ($code === 404 && is_file(__DIR__.'/../Views/errors/404.php')) {
                // Render MVC 404 page
                Response::view('errors/404', []);
                return;
            }
            if ($code === 405 && is_file(__DIR__.'/../Views/errors/405.php')) {
                // Render MVC 405 page
                Response::view('errors/405', []);
                return;
            }
        }
        $fallback = __DIR__.'/../../../public/'.($code===404?'404.html':'50x.html');
        if (is_readable($fallback)) { readfile($fallback); }
        else {
            echo ($code===404?'Not Found':($code===405?'Method Not Allowed':'Error'));
        }
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
