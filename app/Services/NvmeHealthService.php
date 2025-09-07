<?php
namespace App\Services;

class NvmeHealthService
{
    private int $ttl;
    private string $cacheFile;

    public function __construct(int $ttlSeconds = 600)
    {
        // default 10 minutes
        $this->ttl = max(5, $ttlSeconds);
        $this->cacheFile = sys_get_temp_dir() . '/mwp_nvme_health.json';
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
        $data = $this->collect();
        if (function_exists('apcu_store')) apcu_store($cacheKey, $data, $this->ttl);
        $this->writeFileCache($data);
        return $data;
    }

    private function readFileCache(): ?array
    {
        $f = $this->cacheFile; if (!is_readable($f)) return null; $st=@stat($f); if(!$st) return null; if ((time()-(int)$st['mtime'])>$this->ttl) return null; $raw=@file_get_contents($f); if($raw===false) return null; $j=json_decode($raw,true); return is_array($j)?$j:null;
    }
    private function writeFileCache(array $data): void { $tmp=$this->cacheFile.'.tmp'; @file_put_contents($tmp, json_encode($data, JSON_UNESCAPED_SLASHES)); @rename($tmp, $this->cacheFile); }

    private function collect(): array
    {
        $dev = $this->detectPrimaryNvmeDevice();
        if ($dev === null) {
            return [ 'ok' => false, 'status' => 'NA', 'device' => null, 'error' => 'nvme_not_found', 'ts' => time() ];
        }
        $smart = $this->readSmartLog($dev);
        if ($smart === null) {
            return [ 'ok' => false, 'status' => 'NA', 'device' => $dev, 'error' => 'smartlog_unavailable', 'ts' => time() ];
        }
        $tempC = $this->extractTempC($smart);
        $pctUsed = $this->num($smart['percentage_used'] ?? null);
        $mediaErrors = $this->num($smart['media_errors'] ?? null);
        $poh = $this->num($smart['power_on_hours'] ?? null);
        $size = $this->detectDeviceSize($dev);
        $sizeText = $size ? $this->humanSize($size) : '';

        $status = 'OK';
        if ($tempC !== null && $tempC > 80) $status = 'HOT';
        elseif (($tempC !== null && $tempC >= 70) || ($mediaErrors !== null && $mediaErrors > 0)) $status = 'WARN';

        $tooltipParts = [];
        $name = 'NVMe' . ($sizeText?(' '.$sizeText):'');
        $tooltipParts[] = $name;
        if ($tempC !== null) $tooltipParts[] = $tempC . "°C";
        if ($pctUsed !== null) $tooltipParts[] = $pctUsed . "% wear";
        if ($mediaErrors !== null) $tooltipParts[] = $mediaErrors . " errors";
        if ($poh !== null) $tooltipParts[] = $poh . " h";
        $tooltip = implode(' — ', $tooltipParts);

        return [
            'ok' => true,
            'device' => $dev,
            'temperature_c' => $tempC,
            'percentage_used' => $pctUsed,
            'media_errors' => $mediaErrors,
            'power_on_hours' => $poh,
            'size_bytes' => $size,
            'size_text' => $sizeText,
            'status' => $status,
            'tooltip' => $tooltip,
            'ts' => time(),
        ];
    }

    private function detectPrimaryNvmeDevice(): ?string
    {
        // Try from mounted root and NVMe base
        try {
            $root = trim((string)@shell_exec("findmnt -n -o SOURCE / 2>/dev/null"));
            if ($root !== '' && preg_match('#^/dev/nvme\d+n\d+p?\d*$#', $root)) {
                if (preg_match('#^(/dev/nvme\d+n\d+)#', $root, $m)) return $m[1];
            }
        } catch (\Throwable) {}
        // lsblk fallback: choose first nvme disk (type disk) or path
        $out = @shell_exec('lsblk -J -o NAME,TYPE,PATH 2>/dev/null');
        if (is_string($out) && $out !== '') {
            $j = json_decode($out, true);
            foreach (($j['blockdevices'] ?? []) as $n) {
                $type = strtolower((string)($n['type'] ?? ''));
                $path = (string)($n['path'] ?? '');
                if ($type === 'disk' && str_starts_with($path, '/dev/nvme')) return $path;
            }
        }
        // Fallback to common default
        if (is_readable('/dev/nvme0n1')) return '/dev/nvme0n1';
        return null;
    }

    private function readSmartLog(string $device): ?array
    {
        // Prefer JSON output
        $cmd = 'nvme smart-log -o json ' . escapeshellarg($device) . ' 2>/dev/null';
        $out = @shell_exec($cmd);
        if (is_string($out) && trim($out) !== '') {
            $j = json_decode($out, true);
            if (is_array($j)) return $j;
        }
        // Non-JSON fallback (parse key: value)
        $cmd2 = 'nvme smart-log ' . escapeshellarg($device) . ' 2>/dev/null';
        $txt = @shell_exec($cmd2);
        if (!is_string($txt) || $txt === '') return null;
        $res = [];
        foreach (preg_split('/\r?\n/', trim($txt)) as $line) {
            if (preg_match('/^\s*([a-zA-Z0-9_\-]+)\s*:\s*(.+)$/', $line, $m)) {
                $key = strtolower(str_replace('-', '_', trim($m[1])));
                $val = trim($m[2]);
                if (is_numeric($val)) $val = 0 + $val;
                $res[$key] = $val;
            }
        }
        return $res ?: null;
    }

    private function extractTempC(array $smart): ?int
    {
        // JSON usually exposes temperature in Kelvin or Celsius depending on nvme-cli version
        // Common fields: temperature, temperature_sensor_1..n in Kelvin; or temp in C
        if (isset($smart['temperature'])) {
            $t = (float)$smart['temperature'];
            // Many builds provide in Kelvin
            if ($t > 120) return (int)round($t - 273.15);
            return (int)round($t);
        }
        foreach ($smart as $k=>$v) {
            if (preg_match('/^temperature(_sensor_\d+)?$/', (string)$k)) {
                $t = (float)$v; if ($t > 120) return (int)round($t - 273.15); else return (int)round($t);
            }
        }
        if (isset($smart['composite_temperature'])) { $t=(float)$smart['composite_temperature']; if ($t>120) return (int)round($t-273.15); else return (int)round($t); }
        return null;
    }

    private function detectDeviceSize(string $device): ?int
    {
        $out = @shell_exec('lsblk -b -n -o SIZE ' . escapeshellarg($device) . ' 2>/dev/null');
        if (is_string($out) && trim($out) !== '') { $n = (int)trim($out); if ($n>0) return $n; }
        return null;
    }

    private function humanSize(int $bytes): string
    {
        $units = ['B','KB','MB','GB','TB','PB']; $i=0; $v=(float)$bytes; while ($v>=1024 && $i<count($units)-1) { $v/=1024; $i++; }
        $v = ($i>=3) ? round($v,1) : round($v);
        return $v . $units[$i];
    }

    private function num($v): ?int { if ($v === null || $v === '') return null; if (is_numeric($v)) return (int)$v; $n = (int)preg_replace('/[^0-9]/','', (string)$v); return $n>=0?$n:null; }
}
