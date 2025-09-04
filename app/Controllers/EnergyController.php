<?php

namespace App\Controllers;

final class EnergyController
{
    private string $panelDir;

    public function __construct()
    {
        $env = getenv('PANEL_DIR');
        if ($env && is_dir($env)) {
            $this->panelDir = rtrim($env, '/');
        } else {
            $root = realpath(__DIR__ . '/../../');
            $this->panelDir = $root ? rtrim($root, '/') : '/srv/www/webadminpanel-v2';
        }
    }

    public function status(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $cmd = sprintf('sudo -n %s/bin/power_saver.sh status 2>&1', $this->panelDir);
        $out = trim(shell_exec($cmd) ?? '');
        if ($this->looksJson($out)) {
            echo $out; return;
        }
        $this->logError('energy.status', $cmd, $out);
        echo '{"hdmi":null,"wifi":"unknown","bluetooth":"unknown"}';
    }

    // app/Controllers/EnergyController.php (méthode toggleHdmi)
    public function toggleHdmi(): void
    {
        $value  = (($_POST['value'] ?? '') === '1') ? '1' : '0';
        $output = trim($_POST['output'] ?? ''); // <-- NEW
        header('Content-Type: application/json; charset=utf-8');

        $cmd = sprintf(
            'sudo -n %s/bin/power_saver.sh %s %s %s 2>&1',
            $this->panelDir,
            'hdmi',
            escapeshellarg($value),
            $output !== '' ? escapeshellarg($output) : ''
        );
        $out = trim(shell_exec($cmd) ?? '');
        if ($this->looksJson($out)) { echo $out; return; }
        $this->logError('energy.hdmi', $cmd, $out);

        $status = trim(shell_exec(sprintf('sudo -n %s/bin/power_saver.sh status 2>&1', $this->panelDir)) ?? '');
        if ($this->looksJson($status)) { echo $status; return; }
        $this->logError('energy.status.fallback', $cmd, $status);
        echo '{"hdmi":null,"wifi":"unknown","bluetooth":"unknown"}';
    }

    public function toggleWifi(): void
    {
        $value = (($_POST['value'] ?? '') === 'on') ? 'on' : 'off';
        $this->execAndReturn('wifi', $value);
    }

    public function toggleBt(): void
    {
        $value = (($_POST['value'] ?? '') === 'on') ? 'on' : 'off';
        $this->execAndReturn('bluetooth', $value);
    }

    private function execAndReturn(string $target, string $value): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $cmd = sprintf(
            'sudo -n %s/bin/power_saver.sh %s %s 2>&1',
            $this->panelDir,
            escapeshellarg($target),
            escapeshellarg($value)
        );
        $out = trim(shell_exec($cmd) ?? '');

        if ($this->looksJson($out)) {
            echo $out;
            return;
        }

        // Fallback: on renvoie l’état courant pour ne jamais casser le front
        $statusCmd = sprintf('sudo -n %s/bin/power_saver.sh status 2>&1', $this->panelDir);
        $statusOut = trim(shell_exec($statusCmd) ?? '');
        if ($this->looksJson($statusOut)) {
            echo $statusOut; return;
        }
        $this->logError('energy.exec', $cmd, $out . "\n-- status --\n" . $statusOut);
        echo '{"hdmi":null,"wifi":"unknown","bluetooth":"unknown"}';
    }

    private function looksJson(string $s): bool
    {
        if ($s === '') return false;
        $s = ltrim($s);
        if ($s[0] !== '{' && $s[0] !== '[') return false;
        json_decode($s);
        return (json_last_error() === JSON_ERROR_NONE);
    }

    private function logError(string $tag, string $cmd, string $out): void
    {
        $line = sprintf("[%s] %s: cmd=%s\n%s\n", date('c'), $tag, $cmd, $out);
        @file_put_contents(__DIR__ . '/../../logs/panel.log', $line, FILE_APPEND);
    }
}