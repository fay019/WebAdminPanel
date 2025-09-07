<?php
namespace App\Services;

class StorageService {
    private int $ttl;
    private string $cacheFile;

    // Pseudo filesystems to exclude strictly (spec list)
    private const EXCLUDE_FS = [
        'tmpfs','devtmpfs','overlay','squashfs','fusectl','binfmt_misc','configfs','rootfs','proc','sysfs','pstore','debugfs','securityfs','nsfs','autofs','aufs','efivarfs','mqueue','bpf','ramfs','tracefs','selinuxfs','devpts','rpc_pipefs',
        'cgroup','cgroup2','cgroupfs'
    ];

    public function __construct(int $ttlSeconds = 10){
        $this->ttl = max(1,$ttlSeconds);
        $this->cacheFile = sys_get_temp_dir() . '/mwp_storage.json';
    }

    public function get(): array {
        $cacheKey = 'mwp_storage_v2';
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
        $mounts = $this->findmnt();
        $blk = $this->lsblkMap();
        $vols = [];
        $seenDev = [];
        foreach ($mounts as $m) {
            $mp = $m['target']; if ($mp==='') continue;
            $dev = $m['source']; $fs = strtolower($m['fstype'] ?? '');
            // Exclude pseudo-FS by fstype
            if ($this->isPseudoFs($fs)) continue;
            // Inclusion rule: mountpoint non-empty AND (lsblk TYPE in {part,lvm,crypt} OR device starts with /dev/)
            $blkType = strtolower($blk[$dev]['type'] ?? '');
            $include = ($blkType === 'part' || $blkType === 'lvm' || $blkType === 'crypt' || str_starts_with($dev, '/dev/'));
            if (!$include) continue;
            // De-duplicate bind mounts and duplicates of same device
            $devKey = ($blk[$dev]['uuid'] ?? '') . '|' . $dev . '|' . $fs;
            if (isset($seenDev[$devKey])) continue;
            if (strpos($m['opts'] ?? '', 'bind') !== false) continue;
            $seenDev[$devKey] = true;

            // StatVFS via PHP
            $total = @disk_total_space($mp);
            $free  = @disk_free_space($mp);
            if ($total === false || $free === false) continue;
            if ((int)$total === 0) continue; // ignore size_bytes == 0
            $used = max(0, $total - $free);
            $pct = $total>0 ? round($used*100/$total,1) : 0.0;

            // Label and id
            $label = $blk[$dev]['label'] ?? $this->guessLabel($dev,$mp);
            $uuid  = $blk[$dev]['uuid'] ?? '';
            $id = $uuid ?: $dev;

            $vols[] = [
                'id' => $id,
                'device' => $dev,
                'label' => $label ?: basename($dev),
                'mountpoint' => $mp,
                'fstype' => $fs,
                'size_bytes' => (int)$total,
                'used_bytes' => (int)$used,
                'free_bytes' => (int)$free,
                'used_pct' => $pct,
                'is_sd' => (bool)preg_match('/\bmmcblk/',$dev),
                'is_external' => $this->isExternal(['spec'=>$dev,'opts'=>$m['opts'] ?? '']),
                'is_nvme' => (bool)preg_match('/\bnvme\d+n\d+p?\d*$/', $dev) || str_starts_with($dev, '/dev/nvme'),
            ];
        }
        // Derive roles/badges: NVMe primary if same base device hosts '/'; SD marked as backup
        $baseHasRoot = [];
        $rootDev = null;
        foreach ($vols as $v) { if ($v['mountpoint'] === '/') { $rootDev = $v['device']; break; } }
        $baseFrom = function(string $dev): string {
            // /dev/nvme0n1p2 -> /dev/nvme0n1 ; /dev/mmcblk0p2 -> /dev/mmcblk0
            if (preg_match('#^(/dev/nvme\d+n\d+)p?\d*$#', $dev, $m)) return $m[1];
            if (preg_match('#^(/dev/mmcblk\d+)p?\d*$#', $dev, $m)) return $m[1];
            if (preg_match('#^(/dev/[a-z]+\d+)\d*$#', $dev, $m)) return $m[1];
            return $dev;
        };
        $rootBase = $rootDev ? $baseFrom($rootDev) : null;
        foreach ($vols as &$v) {
            $badges = [];
            $base = $baseFrom($v['device']);
            $isPrimary = ($rootBase && $base === $rootBase && ($v['is_nvme'] ?? false));
            if (!empty($v['is_nvme'])) { $badges[] = 'NVMe'; if ($isPrimary) $badges[] = 'primaire'; }
            if (!empty($v['is_sd'])) { $badges[] = 'SD'; $badges[] = 'secours'; }
            $v['role_primary'] = $isPrimary;
            $v['role_backup'] = !empty($v['is_sd']);
            $v['badges'] = $badges;
        }
        unset($v);

        // totals
        $totSize=0; $totUsed=0; $totFree=0;
        foreach ($vols as $v){ $totSize+=$v['size_bytes']; $totUsed+=$v['used_bytes']; $totFree+=$v['free_bytes']; }
        $totPct = $totSize>0? round($totUsed*100/$totSize,1):0.0;

        // path_stats for '/' and '/var/www'
        $root = $this->statPath('/');
        $web  = is_dir('/var/www') ? $this->statPath('/var/www') : null;

        return [
            'volumes' => $vols,
            'totals' => [
                'size_bytes'=>(int)$totSize,
                'used_bytes'=>(int)$totUsed,
                'free_bytes'=>(int)$totFree,
                'used_pct'=>$totPct,
            ],
            'path_stats' => [ 'root' => $root, 'web' => $web ],
            'ts' => time(),
        ];
    }

    private function findmnt(): array {
        $out = @shell_exec('findmnt -J -r -o SOURCE,TARGET,FSTYPE,OPTIONS 2>/dev/null');
        if (!is_string($out) || $out==='') return $this->parseMountsFallback();
        $j = json_decode($out, true);
        $arr = [];
        if (isset($j['filesystems']) && is_array($j['filesystems'])) {
            foreach ($j['filesystems'] as $fs) {
                $src = (string)($fs['source'] ?? ''); $tgt = (string)($fs['target'] ?? ''); $fst=(string)($fs['fstype'] ?? ''); $opt=(string)($fs['options'] ?? '');
                if ($tgt==='') continue;
                $arr[] = ['source'=>$src,'target'=>$tgt,'fstype'=>$fst,'opts'=>$opt];
            }
        }
        return $arr;
    }

    private function parseMountsFallback(): array {
        $rows = [];
        $raw = @file_get_contents('/proc/self/mounts');
        if (!is_string($raw) || $raw==='') $raw = @file_get_contents('/proc/mounts');
        if (!is_string($raw) || $raw==='') return $rows;
        foreach (preg_split('/\r?\n/', trim($raw)) as $line) {
            if ($line==='') continue;
            $parts = preg_split('/\s+/', $line);
            if (count($parts) < 3) continue;
            $spec = str_replace('\\040',' ', $parts[0]);
            $file = str_replace('\\040',' ', $parts[1]);
            $vfs  = $parts[2];
            $opts = $parts[3] ?? '';
            $rows[] = ['source'=>$spec,'target'=>$file,'fstype'=>$vfs,'opts'=>$opts];
        }
        return $rows;
    }

    private function lsblkMap(): array {
        $map = [];
        $out = @shell_exec('lsblk -J -o NAME,TYPE,MOUNTPOINT,LABEL,FSTYPE,PKNAME,UUID,PATH 2>/dev/null');
        if (!is_string($out) || $out==='') return $map;
        $j = json_decode($out, true);
        $nodes = $j['blockdevices'] ?? [];
        $stack = $nodes;
        while ($stack) {
            $n = array_pop($stack);
            $path = $n['path'] ?? null; if ($path) {
                $map[$path] = [
                    'type' => strtolower((string)($n['type'] ?? '')),
                    'label'=> (string)($n['label'] ?? ''),
                    'uuid' => (string)($n['uuid'] ?? ''),
                    'fstype'=> (string)($n['fstype'] ?? ''),
                ];
            }
            if (!empty($n['children']) && is_array($n['children'])) {
                foreach ($n['children'] as $c) $stack[] = $c;
            }
        }
        return $map;
    }

    private function statPath(string $path): ?array {
        $total = @disk_total_space($path); $free = @disk_free_space($path);
        if ($total === false || $free === false) return null;
        $used = max(0, $total - $free);
        $pct = $total>0 ? round($used*100/$total,1) : 0.0;
        return [ 'size_bytes'=>(int)$total, 'used_bytes'=>(int)$used, 'free_bytes'=>(int)$free, 'used_pct'=>$pct ];
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
        // Heuristic: USB devices often appear as /dev/sdX or /dev/sdXN
        $spec = $m['spec'] ?? '';
        if (preg_match('/\b(sd[a-z]|sd[a-z]\d+)\b/', $spec)) return true;
        if (strpos($m['opts'] ?? '', 'x-gvfs-show') !== false) return true;
        return false;
    }
}
