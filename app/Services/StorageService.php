<?php
namespace App\Services;

class StorageService {
    private int $ttl;
    private string $cacheFile;

    private const EXCLUDE_FS = [
        'tmpfs','devtmpfs','overlay','squashfs','proc','sysfs','pstore','debugfs','securityfs','nsfs','autofs','aufs','efivarfs','mqueue','bpf','ramfs','tracefs','selinuxfs',
        'cgroup','cgroup2','cgroupfs'
    ];

    public function __construct(int $ttlSeconds = 10){
        $this->ttl = max(1,$ttlSeconds);
        $this->cacheFile = sys_get_temp_dir() . '/mwp_storage.json';
    }

    public function get(): array {
        $cacheKey = 'mwp_storage_v1';
        if (function_exists('apcu_fetch')) {
            $ok=false; $d=apcu_fetch($cacheKey,$ok); if($ok && is_array($d)) return $d;
        }
        $d = $this->readFileCache(); if ($d) return $d;
        $d = $this->collect();
        if (function_exists('apcu_store')) apcu_store($cacheKey,$d,$this->ttl);
        $this->writeFileCache($d);
        return $d;
    }

    private function readFileCache(): ?array {
        $f = $this->cacheFile; if(!is_readable($f)) return null; $st=@stat($f); if(!$st) return null; if((time()- (int)$st['mtime'])>$this->ttl) return null; $raw=@file_get_contents($f); if($raw===false) return null; $j=json_decode($raw,true); return is_array($j)?$j:null;
    }
    private function writeFileCache(array $data): void { $tmp=$this->cacheFile.'.tmp'; @file_put_contents($tmp,json_encode($data,JSON_UNESCAPED_SLASHES)); @rename($tmp,$this->cacheFile); }

    private function collect(): array {
        $mounts = $this->parseMounts();
        $vols = [];
        $seenDev = [];
        foreach ($mounts as $m) {
            // Deduplicate by device id
            $devKey = $m['spec'] . '|' . $m['fs'];
            if (isset($seenDev[$devKey])) continue;
            $seenDev[$devKey] = true;
            // Use PHP disk functions
            $total = @disk_total_space($m['file']);
            $free  = @disk_free_space($m['file']);
            if ($total === false || $free === false) continue;
            $used = max(0, $total - $free);
            $pct = $total>0 ? round($used*100/$total,1) : 0.0;
            $label = $this->guessLabel($m['spec'],$m['file']);
            $vols[] = [
                'id' => $m['spec'],
                'device' => $m['spec'],
                'label' => $label,
                'mountpoint' => $m['file'],
                'fstype' => $m['fs'],
                'size_bytes' => (int)$total,
                'used_bytes' => (int)$used,
                'free_bytes' => (int)$free,
                'used_pct' => $pct,
                'is_sd' => (bool)preg_match('/\bmmcblk/',$m['spec']),
                'is_external' => $this->isExternal($m),
            ];
        }
        // totals
        $totSize=0; $totUsed=0; $totFree=0;
        foreach ($vols as $v){ $totSize+=$v['size_bytes']; $totUsed+=$v['used_bytes']; $totFree+=$v['free_bytes']; }
        $totPct = $totSize>0? round($totUsed*100/$totSize,1):0.0;
        return [
            'volumes' => $vols,
            'totals' => [
                'size_bytes'=>(int)$totSize,
                'used_bytes'=>(int)$totUsed,
                'free_bytes'=>(int)$totFree,
                'used_pct'=>$totPct,
            ],
            'ts' => time(),
        ];
    }

    private function parseMounts(): array {
        $rows = [];
        $raw = @file_get_contents('/proc/self/mounts');
        if (!is_string($raw) || $raw==='') $raw = @file_get_contents('/proc/mounts');
        if (!is_string($raw) || $raw==='') return $rows;
        foreach (preg_split('/\r?\n/', trim($raw)) as $line) {
            if ($line==='') continue;
            // Fields: spec file vfs opts freq passno (space-escaped with \040)
            $parts = preg_split('/\s+/', $line);
            if (count($parts) < 3) continue;
            $spec = str_replace('\\040',' ', $parts[0]);
            $file = str_replace('\\040',' ', $parts[1]);
            $vfs  = $parts[2];
            // Filter pseudo FS
            if ($this->isPseudoFs($vfs)) continue;
            // Skip special mountpoints if desired?
            // Avoid double counting bind mounts: detect with mount options "bind" or same spec+fstype
            $opts = $parts[3] ?? '';
            if (strpos($opts, 'bind') !== false) continue;
            // Skip read-only technical from boot (e.g., /boot/firmware as vfat is still real; keep it)
            $rows[] = ['spec'=>$spec,'file'=>$file,'fs'=>$vfs,'opts'=>$opts];
        }
        // Prefer "longest mountpoint first" to avoid nested mounts later if ever needed
        usort($rows, fn($a,$b)=>strlen($b['file'])<=>strlen($a['file']));
        // Deduplicate same device mounted multiple places → keep first occurrence
        $unique = [];
        $seen = [];
        foreach ($rows as $r){ $k=$r['spec'].'|'.$r['fs']; if(isset($seen[$k])) continue; $seen[$k]=true; $unique[]=$r; }
        return $unique;
    }

    private function isPseudoFs(string $fs): bool {
        $fs = strtolower($fs);
        if (in_array($fs, self::EXCLUDE_FS, true)) return true;
        if (str_starts_with($fs,'cgroup')) return true;
        return false;
    }

    private function guessLabel(string $spec, string $mount): string {
        // Try lsblk for pretty LABEL
        $label = '';
        $cmd = 'lsblk -no LABEL '.escapeshellarg($spec).' 2>/dev/null';
        $out = @shell_exec($cmd);
        if (is_string($out)) { $label = trim($out); }
        if ($label==='') $label = basename($spec);
        if ($label==='') $label = $mount;
        return $label;
    }

    private function isExternal(array $m): bool {
        // Heuristic: USB devices often appear as /dev/sdX or /dev/sdXN on Pi; internal NVMe as nvme*, SD as mmcblk*
        $spec = $m['spec'];
        if (preg_match('/\b(sd[a-z]|sd[a-z]\d+)\b/', $spec)) return true;
        if (strpos($m['opts'] ?? '', 'x-gvfs-show') !== false) return true;
        return false;
    }
}
