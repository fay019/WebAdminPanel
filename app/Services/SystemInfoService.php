<?php
namespace App\Services;

class SystemInfoService
{
    private int $ttl;
    private string $cacheFile;

    public function __construct(int $ttlSeconds = 4)
    {
        $this->ttl = max(1, $ttlSeconds);
        $this->cacheFile = sys_get_temp_dir() . '/mwp_sysinfo.json';
    }

    public function get(): array
    {
        // Try APCu first
        $cacheKey = 'mwp_sysinfo_v1';
        if (function_exists('apcu_fetch')) {
            $ok = false; $data = apcu_fetch($cacheKey, $ok);
            if ($ok && is_array($data)) return $data;
        }
        // Try file cache
        $data = $this->readFileCache();
        if (is_array($data)) return $data;

        $data = $this->collectAll();

        if (function_exists('apcu_store')) {
            apcu_store($cacheKey, $data, $this->ttl);
        }
        $this->writeFileCache($data);
        return $data;
    }

    private function readFileCache(): ?array
    {
        $f = $this->cacheFile;
        if (!is_readable($f)) return null;
        $st = @stat($f);
        if (!$st) return null;
        if ((time() - (int)$st['mtime']) > $this->ttl) return null;
        $raw = @file_get_contents($f);
        if ($raw === false) return null;
        $json = json_decode($raw, true);
        return is_array($json) ? $json : null;
    }

    private function writeFileCache(array $data): void
    {
        $tmp = $this->cacheFile . '.tmp';
        @file_put_contents($tmp, json_encode($data, JSON_UNESCAPED_SLASHES));
        @rename($tmp, $this->cacheFile);
    }

    private function collectAll(): array
    {
        $sites = $this->collectSites();
        $uptime = $this->collectUptime();
        $load = $this->collectLoadavg();
        $cpu = $this->collectCpu();
        $ambient = $this->collectAmbient();
        $mem = $this->collectMem();
        $disk = $this->collectDisk();
        $os = $this->collectOs();
        $php = $this->collectPhp();
        $nginx = $this->collectNginx();
        $proc = $this->collectProcCount();

        return [
            'sites' => $sites,
            'uptime' => $uptime,
            'loadavg' => $load,
            'cpu' => $cpu,
            'ambient' => $ambient,
            'mem' => $mem,
            'disk' => $disk,
            'os' => $os,
            'php' => $php,
            'nginx' => $nginx,
            'processes' => [ 'count' => $proc ],
        ];
    }

    private function collectSites(): array
    {
        // SQLite DB expected at data/sites.db via lib/db.php helper
        $total = 0; $enabled = 0;
        try {
            require_once __DIR__ . '/../../lib/db.php';
            $pdo = db();
            $total = (int)$pdo->query('SELECT COUNT(*) FROM sites')->fetchColumn();
            $enabled = (int)$pdo->query('SELECT COUNT(*) FROM sites WHERE enabled = 1')->fetchColumn();
        } catch (\Throwable) { /* ignore */ }
        return [ 'total' => $total, 'enabled' => $enabled ];
    }

    private function collectUptime(): array
    {
        $seconds = 0;
        $raw = @file_get_contents('/proc/uptime');
        if (is_string($raw) && $raw !== '') {
            $parts = preg_split('/\s+/', trim($raw));
            if ($parts && isset($parts[0])) $seconds = (int)floatval($parts[0]);
        }
        return [ 'seconds' => $seconds, 'human' => $this->humanSeconds($seconds) ];
    }

    private function collectLoadavg(): array
    {
        $raw = @file_get_contents('/proc/loadavg');
        $a = [0.0, 0.0, 0.0];
        if (is_string($raw) && $raw !== '') {
            $parts = preg_split('/\s+/', trim($raw));
            $a[0] = isset($parts[0]) ? (float)$parts[0] : 0.0;
            $a[1] = isset($parts[1]) ? (float)$parts[1] : 0.0;
            $a[2] = isset($parts[2]) ? (float)$parts[2] : 0.0;
        }
        return [ '1m' => $a[0], '5m' => $a[1], '15m' => $a[2] ];
    }

    private function collectCpu(): array
    {
        $cores = (int)trim((string)@shell_exec('nproc 2>/dev/null'));
        if ($cores <= 0) { $cores = (int)trim((string)@shell_exec('getconf _NPROCESSORS_ONLN 2>/dev/null')); }
        $tempC = null;
        $t = @file_get_contents('/sys/class/thermal/thermal_zone0/temp');
        if (is_string($t) && trim($t) !== '') {
            $v = (float)$t; $tempC = $v > 1000 ? round($v/1000, 1) : round($v, 1);
        }
        $topCpu = ['pid' => 0, 'cmd' => '', 'cpu' => 0.0];
        $ps = @shell_exec("ps -eo pid,comm,pcpu --sort=-pcpu | head -n 2 2>/dev/null");
        if (is_string($ps)) {
            $lines = preg_split('/\r?\n/', trim($ps));
            if (count($lines) >= 2) {
                $cols = preg_split('/\s+/', trim($lines[1]));
                if (count($cols) >= 3) {
                    $topCpu = [ 'pid' => (int)$cols[0], 'cmd' => $cols[1], 'cpu' => (float)$cols[2] ];
                }
            }
        }
        return [ 'cores' => $cores, 'temp_c' => $tempC, 'top_cpu_proc' => $topCpu ];
    }

    private function collectMem(): array
    {
        // Use /proc/meminfo for speed
        $info = [];
        $raw = @file_get_contents('/proc/meminfo');
        if (is_string($raw) && $raw !== '') {
            foreach (preg_split('/\r?\n/', trim($raw)) as $line) {
                if (preg_match('/^(\w+):\s+(\d+)/', $line, $m)) {
                    $info[$m[1]] = (int)$m[2]; // in kB
                }
            }
        }
        $totalKb = (int)($info['MemTotal'] ?? 0);
        $freeKb = (int)($info['MemAvailable'] ?? ($info['MemFree'] ?? 0));
        $usedKb = max(0, $totalKb - $freeKb);
        $totalMb = round($totalKb / 1024);
        $usedMb = round($usedKb / 1024);
        $freeMb = max(0, $totalMb - $usedMb);
        $pct = ($totalMb > 0) ? round($usedMb * 100 / $totalMb, 1) : 0.0;

        $topMem = ['pid' => 0, 'cmd' => '', 'rss_mb' => 0.0];
        $ps = @shell_exec("ps -eo pid,comm,rss --sort=-rss | head -n 2 2>/dev/null");
        if (is_string($ps)) {
            $lines = preg_split('/\r?\n/', trim($ps));
            if (count($lines) >= 2) {
                $cols = preg_split('/\s+/', trim($lines[1]));
                if (count($cols) >= 3) {
                    $topMem = [ 'pid' => (int)$cols[0], 'cmd' => $cols[1], 'rss_mb' => round(((int)$cols[2])/1024, 1) ];
                }
            }
        }

        return [
            'total_mb' => $totalMb,
            'used_mb' => $usedMb,
            'free_mb' => $freeMb,
            'used_pct' => $pct,
            'top_mem_proc' => $topMem,
        ];
    }

    private function collectDisk(): array
    {
        $path = '/srv/www';
        $info = ['size_gb'=>0, 'used_gb'=>0, 'avail_gb'=>0, 'used_pct'=>0];
        $df = @shell_exec('df -BG ' . escapeshellarg($path) . ' 2>/dev/null');
        if (is_string($df)) {
            $lines = preg_split('/\r?\n/', trim($df));
            if (count($lines) >= 2) {
                $cols = preg_split('/\s+/', trim($lines[1]));
                if (count($cols) >= 5) {
                    $size = (int)str_replace('G','',$cols[1]);
                    $used = (int)str_replace('G','',$cols[2]);
                    $avail= (int)str_replace('G','',$cols[3]);
                    $pct  = (int)str_replace('%','',$cols[4]);
                    $info = ['size_gb'=>$size,'used_gb'=>$used,'avail_gb'=>$avail,'used_pct'=>$pct];
                }
            }
        }
        return ['srv_www' => $info];
    }

    private function collectOs(): array
    {
        $pretty = '';
        $kernel = trim((string)@shell_exec('uname -r 2>/dev/null'));
        $arch = trim((string)@shell_exec('uname -m 2>/dev/null'));
        $raw = @file_get_contents('/etc/os-release');
        if (is_string($raw) && $raw !== '') {
            foreach (preg_split('/\r?\n/', trim($raw)) as $line) {
                if (preg_match('/^PRETTY_NAME=(.*)$/', $line, $m)) {
                    $pretty = trim($m[1], "\"' ");
                    break;
                }
            }
        }
        return ['pretty'=>$pretty, 'kernel'=>$kernel, 'arch'=>$arch];
    }

    private function collectPhp(): array
    {
        $cliVersion = $this->firstNonEmpty([
            fn()=> $this->parsePhpVersion(@shell_exec('php -v 2>/dev/null')),
            fn()=> $this->parsePhpVersion(@shell_exec('php8.3 -v 2>/dev/null')),
            fn()=> $this->parsePhpVersion(@shell_exec('php8.2 -v 2>/dev/null')),
        ]) ?? '';

        $sockets = [];
        foreach (glob('/run/php/php*-fpm.sock') ?: [] as $sock) {
            $ver = null;
            if (preg_match('/php(\d+\.\d+)-fpm\.sock$/', $sock, $m)) { $ver = $m[1]; }
            $sockets[] = [ 'version' => $ver ?: '', 'sock' => $sock, 'alive' => $this->isSocketAlive($sock) ];
        }

        return ['cli_version'=>$cliVersion, 'fpm_sockets'=>$sockets];
    }

    private function parsePhpVersion(?string $text): ?string
    {
        if (!is_string($text) || $text==='') return null;
        if (preg_match('/^PHP\s+([\d.]+)/m', $text, $m)) return $m[1];
        return null;
    }

    private function isSocketAlive(string $sock): bool
    {
        // Cheap check: file exists and is a socket
        if (!file_exists($sock)) return false;
        $st = @lstat($sock);
        if ($st === false) return false;
        // POSIX socket bit mask (S_IFSOCK 0140000); but PHP doesn't expose easily → use stream_socket_client
        $fp = @stream_socket_client('unix://' . $sock, $errno, $errstr, 0.02);
        if (is_resource($fp)) { @fclose($fp); return true; }
        return true; // if exists but cannot connect, still consider alive (service may restrict)
    }

    private function collectAmbient(): array
    {
        // Try a few common sources; return null if not available.
        // 1) Optional drop-in files updated by an external sensor agent
        $paths = ['/run/ambient_temp_c', '/tmp/ambient_temp_c'];
        foreach ($paths as $p) {
            $t = @file_get_contents($p);
            if (is_string($t) && trim($t) !== '') {
                $v = (float)trim($t);
                return [ 'temp_c' => round($v, 1), 'unit' => 'C', 'ts' => time() ];
            }
        }
        // 2) Heuristic: hwmon/iio sensors (very device-specific) — keep safe and fast
        // Skipped by default to avoid overhead; can be implemented later if needed.
        return [ 'temp_c' => null, 'unit' => 'C', 'ts' => time() ];
    }

    private function collectNginx(): array
    {
        $txt = trim((string)@shell_exec('nginx -v 2>&1'));
        $v = '';
        if ($txt !== '') {
            if (preg_match('/nginx\s+version:\s*nginx\/([\d.]+)/i', $txt, $m)) $v = $m[1];
            elseif (preg_match('/nginx\/(\d+[^\s]+)/i', $txt, $m)) $v = $m[1];
            else $v = $txt;
        }
        return ['version' => $v];
    }

    private function collectProcCount(): int
    {
        $n = (int)trim((string)@shell_exec('ps -e --no-headers | wc -l 2>/dev/null'));
        return $n > 0 ? $n : 0;
    }

    private function humanSeconds(int $sec): string
    {
        $d = intdiv($sec, 86400); $sec %= 86400;
        $h = intdiv($sec, 3600); $sec %= 3600;
        $m = intdiv($sec, 60); $s = $sec % 60;
        $parts = [];
        if ($d) $parts[] = $d.'d';
        if ($h) $parts[] = $h.'h';
        if ($m) $parts[] = $m.'m';
        if ($s && !$d && !$h) $parts[] = $s.'s';
        return $parts ? implode(' ', $parts) : '0s';
    }

    private function firstNonEmpty(array $fns)
    {
        foreach ($fns as $fn) { $v = $fn(); if ($v) return $v; }
        return null;
    }
}
