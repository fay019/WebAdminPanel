<?php
namespace App\Middlewares;

class FlashMiddleware {
    public static function handle(): void {
        // No-op: partials/flash.php manages display lifecycle.
        // Keep for future enhancements (e.g., auto-carry of messages across redirects).
        return;
    }
}
