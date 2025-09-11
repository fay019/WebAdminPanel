<?php
namespace App\Services;

class PowerEventBus {
    private string $file;
    private string $bootFlag;

    public function __construct(string $file = '/tmp/power_events.jsonl'){
        $this->file = $file;
        $this->bootFlag = '/tmp/power_server_online_sent';
    }

    public function emit(string $type): string {
        // Only allow defined events
        if (!in_array($type, ['reboot_started','shutdown_started','server_online'], true)) {
            return '';
        }
        $nowMs = (int) floor(microtime(true) * 1000);
        $id = (string)$nowMs;
        $row = json_encode(['id'=>$id, 'type'=>$type, 'at'=>$nowMs], JSON_UNESCAPED_SLASHES) . "\n";
        $dir = dirname($this->file);
        if (!is_dir($dir)) { @mkdir($dir, 0777, true); }
        $fp = @fopen($this->file, 'ab');
        if ($fp) {
            // lightweight lock to avoid interleaving
            @flock($fp, LOCK_EX);
            @fwrite($fp, $row);
            @flock($fp, LOCK_UN);
            @fclose($fp);
        }
        // Touch a small pointer file to help external watchers
        @touch($this->file . '.touch');
        return $id;
    }

    public function publishServerOnlineIfNeeded(): void {
        // Try to read Linux boot_id to make this idempotent per boot
        $bootId = @trim(@file_get_contents('/proc/sys/kernel/random/boot_id')) ?: '';
        $flag = $this->bootFlag;
        $already = @trim(@file_get_contents($flag));
        if ($bootId !== '' && $already === $bootId) {
            return; // already sent for this boot
        }
        // emit event and store boot id
        $this->emit('server_online');
        if ($bootId !== '') {
            @file_put_contents($flag, $bootId, LOCK_EX);
        } else {
            // fallback: store timestamp to avoid spamming within same FPM lifecycle
            @file_put_contents($flag, (string)time(), LOCK_EX);
        }
    }
}
