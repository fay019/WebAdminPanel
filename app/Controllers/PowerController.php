<?php
namespace App\Controllers;

use App\Services\PowerEventBus;

class PowerController {
    public function stream(): void {
        require_once __DIR__ . '/../../lib/auth.php';
        if (!function_exists('is_logged_in') || !is_logged_in()) {
            http_response_code(401);
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'unauthorized';
            exit;
        }
        // Release session lock to avoid blocking other requests during long stream
        if (session_status() === PHP_SESSION_ACTIVE) { @session_write_close(); }

        // SSE headers
        header('Content-Type: text/event-stream; charset=UTF-8');
        header('Cache-Control: no-cache, no-transform');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no'); // Nginx: disable buffering
        // Do not set Content-Encoding to avoid proxy confusion

        // Ensure no BOM/previous output and immediate flush
        while (ob_get_level() > 0) { @ob_end_clean(); }
        @ini_set('output_buffering', 'off');
        @ini_set('zlib.output_compression', '0');
        @ini_set('implicit_flush', '1');
        @apache_setenv('no-gzip', '1');
        @set_time_limit(0);
        @ignore_user_abort(true);

        $bus = new PowerEventBus();

        // Send a welcome comment + retry to establish stream and force headers flush
        echo ": connected\n";
        echo "retry: 5000\n\n";
        @flush();
        @ob_flush();
        flush();

        $eventsFile = '/tmp/power_events.jsonl';
        $lastId = isset($_SERVER['HTTP_LAST_EVENT_ID']) ? trim((string)$_SERVER['HTTP_LAST_EVENT_ID']) : '';
        if ($lastId === '' && isset($_GET['lastEventId'])) { $lastId = (string)$_GET['lastEventId']; }

        $fp = @fopen($eventsFile, 'rb');
        $pos = 0;
        if ($fp) {
            // Start at end by default; or scan to lastId if provided
            if ($lastId !== '') {
                $pos = 0; $found = false;
                while (!feof($fp)) {
                    $line = fgets($fp);
                    if ($line === false) break;
                    $pos = ftell($fp) ?: $pos;
                    $obj = json_decode($line, true);
                    if (is_array($obj) && ($obj['id'] ?? '') === $lastId) { $found = true; break; }
                }
                // If found, continue reading from that position (next loop will read newer ones)
                if (!$found) { $pos = filesize($eventsFile) ?: 0; }
            } else {
                $pos = filesize($eventsFile) ?: 0; // tail -f like from end
            }
            fseek($fp, $pos);
        }

        $lastHeartbeat = microtime(true);
        $start = time();
        $sessionCheckAt = $start;
        while (true) {
            // Stop if client disconnected
            if (connection_aborted()) break;

            // Heartbeat every 30 seconds
            $now = microtime(true);
            if ($now - $lastHeartbeat >= 30.0) {
                echo ": hb\n\n";
                $lastHeartbeat = $now;
                @flush();
            }

            // Check session every 60 seconds (cheap)
            if (time() - $sessionCheckAt >= 60) {
                if (!function_exists('is_logged_in') || !is_logged_in()) {
                    echo "event: error\n";
                    echo "data: {\"code\":401}\n\n";
                    @flush();
                    break;
                }
                $sessionCheckAt = time();
            }

            clearstatcache(true, $eventsFile);
            $size = @filesize($eventsFile);
            if ($size !== false && $size > $pos) {
                if (!$fp) { $fp = @fopen($eventsFile, 'rb'); }
                if ($fp) {
                    fseek($fp, $pos);
                    while (!feof($fp)) {
                        $line = fgets($fp);
                        if ($line === false) break;
                        $pos = ftell($fp) ?: $pos;
                        $obj = json_decode($line, true);
                        if (!is_array($obj)) continue;
                        $type = (string)($obj['type'] ?? '');
                        if (!in_array($type, ['reboot_started','shutdown_started','server_online'], true)) continue;
                        $eid = (string)($obj['id'] ?? '');
                        $data = json_encode(['type'=>$type,'at'=>$obj['at'] ?? null], JSON_UNESCAPED_SLASHES);
                        echo "id: {$eid}\n";
                        echo "event: {$type}\n";
                        echo "data: {$data}\n\n";
                        @flush();
                    }
                }
            }

            // Sleep lightly to avoid CPU burn (approx 1s)
            usleep(250000);
        }

        if ($fp) @fclose($fp);
        exit;
    }
}
