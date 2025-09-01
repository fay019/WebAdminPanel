<?php
namespace App\Middlewares;

class CsrfMiddleware {
    public static function handle(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { return; }
        if (function_exists('csrf_check')) { csrf_check(); }
    }
}
