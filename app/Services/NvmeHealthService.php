<?php
namespace App\Services;

class NvmeHealthService
{
    private int $ttl;
    private string $cacheFile;

    public function __construct(int $ttlSeconds = 900)
    {
        // default 15 minutes TTL for cache freshness policy
        $this->ttl = max(60, $ttlSeconds);
        // Read-only cache path produced by privileged collector (root timer)
        $this->cacheFile = '/var/cache/panel/nvme-health.json';
    }

    public function get(): array
    {
        $cacheKey = 'mwp_nvme_health_v1';
        if (function_exists('apcu_fetch')) {
            $ok = false; $data = apcu_fetch($cacheKey, $ok);
            if ($ok && is_array($data)) return $data;
        }
        $file = $this->readFileCache();
        if ($file) return $file;
        // If cache missing or stale, return NA with reason and do NOT attempt to run nvme here.
        $stale = $this->readFileCacheStaleInfo();
        $data = $this->formatNA($stale['device'] ?? null, 'stale_cache');
        if (function_exists('apcu_store')) apcu_store($cacheKey, $data, 5);
        return $data;
    }

    private function readFileCache(): ?array
    {
        $f = $this->cacheFile;
        if (!is_readable($f)) return null;
        $st = @stat($f); if (!$st) return null;
        $mtime = (int)$st['mtime'];
        $raw = @file_get_contents($f); if ($raw === false) return null;
        $j = json_decode($raw, true);
        if (!is_array($j)) return null;
        // Normalize various possible collector outputs to the API contract
        $res = $this->normalizeFromRaw($j, $mtime);
        // Enforce TTL: if stale, return NA with reason
        if ((time() - $mtime) > $this->ttl) {
            $res['ok'] = false;
            $res['status'] = 'NA';
            $res['reason'] = 'stale_cache';
        }
        return $res;
    }

    private function readFileCacheStaleInfo(): array
    {
        $f = $this->cacheFile;
        $info = ['device'=>null];
        if (is_readable($f)) {
            $raw = @file_get_contents($f);
            $j = json_decode((string)$raw, true);
            if (is_array($j)) {
                $info['device'] = $j['device'] ?? ($j['nvme'] ?? ($j['dev'] ?? null));
            }
        }
        return $info;
    }

    private function formatNA(?string $device, string $reason): array
    {
        return [
            'ok' => false,
            'status' => 'NA',
            'device' => $device,
            'metrics' => [
                'temperature_c' => null,
                'percentage_used' => null,
                'media_errors' => null,
                'power_on_hours' => null,
            ],
            'ts' => time(),
            'reason' => $reason,
        ];
    }

    private function normalizeFromRaw(array $raw, int $mtime): array
    {
        // Try to map standard nvme smart-log JSON
        $device = $raw['device'] ?? ($raw['dev'] ?? ($raw['nvme'] ?? null));
        // Some collectors might store the whole nvme smart-log payload under 'smart' key
        $smart = $raw['smart'] ?? $raw;

        $tempC = null;
        if (isset($smart['temperature'])) {
            $t = (float)$smart['temperature'];
            $tempC = ($t > 120) ? (int)round($t - 273.15) : (int)round($t);
        } elseif (isset($smart['composite_temperature'])) {
            $t = (float)$smart['composite_temperature'];
            $tempC = ($t > 120) ? (int)round($t - 273.15) : (int)round($t);
        } else {
            foreach ($smart as $k=>$v) {
                if (preg_match('/^temperature(_sensor_\d+)?$/', (string)$k)) {
                    $t = (float)$v; $tempC = ($t > 120) ? (int)round($t - 273.15) : (int)round($t); break;
                }
            }
        }
        $pctUsed = null; if (isset($smart['percentage_used'])) { $pctUsed = (int)$smart['percentage_used']; }
        $mediaErrors = null; if (isset($smart['media_errors'])) { $mediaErrors = (int)$smart['media_errors']; }
        $poh = null; if (isset($smart['power_on_hours'])) { $poh = (int)$smart['power_on_hours']; }

        // If collector already provided metrics block, prefer it
        if (isset($raw['metrics']) && is_array($raw['metrics'])) {
            $m = $raw['metrics'];
            $tempC = $m['temperature_c'] ?? $tempC;
            $pctUsed = $m['percentage_used'] ?? $pctUsed;
            $mediaErrors = $m['media_errors'] ?? $mediaErrors;
            $poh = $m['power_on_hours'] ?? $poh;
        }

        // Determine status per rules
        $status = 'NA'; $okFlag = false; $reason = null;
        if ($tempC===null && $pctUsed===null && $mediaErrors===null && $poh===null) {
            $okFlag = false; $status = 'NA'; $reason = 'smartlog_unavailable';
        } else {
            $okFlag = true;
            if (($mediaErrors !== null && $mediaErrors > 0) || ($tempC !== null && $tempC > 80)) {
                $status = 'HOT';
            } elseif (($tempC !== null && $tempC >= 70 && $tempC <= 80) || ($pctUsed !== null && $pctUsed > 5 && $pctUsed <= 10)) {
                $status = 'WARN';
            } else {
                // OK: temp < 70, wear <= 5, errors = 0
                $tempOk = ($tempC === null) ? true : ($tempC < 70);
                $wearOk = ($pctUsed === null) ? true : ($pctUsed <= 5);
                $errOk = ($mediaErrors === null) ? true : ($mediaErrors === 0);
                $status = ($tempOk && $wearOk && $errOk) ? 'OK' : 'WARN';
            }
        }

        $ts = $raw['ts'] ?? $mtime;
        $res = [
            'ok' => $okFlag,
            'status' => $status,
            'device' => $device,
            'metrics' => [
                'temperature_c' => $tempC,
                'percentage_used' => $pctUsed,
                'media_errors' => $mediaErrors,
                'power_on_hours' => $poh,
            ],
            'ts' => is_int($ts) ? $ts : $mtime,
            'reason' => $reason,
        ];
        return $res;
    }
}
