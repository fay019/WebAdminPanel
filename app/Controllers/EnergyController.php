<?php

namespace App\Controllers;

final class EnergyController
{
    private string $panelDir = '/srv/www/webadminpanel-v2';

    public function status(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $cmd = sprintf('sudo -n %s/bin/power_saver.sh status 2>&1', $this->panelDir);
        $out = trim(shell_exec($cmd) ?? '');
        echo $this->looksJson($out)
            ? $out
            : '{"hdmi":null,"wifi":"unknown","bluetooth":"unknown"}';
    }

    public function toggleHdmi(): void
    {
        $value = (($_POST['value'] ?? '') === '1') ? '1' : '0';
        $this->execAndReturn('hdmi', $value);
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
        echo $this->looksJson($statusOut)
            ? $statusOut
            : '{"hdmi":null,"wifi":"unknown","bluetooth":"unknown"}';
    }

    private function looksJson(string $s): bool
    {
        if ($s === '') return false;
        $s = ltrim($s);
        if ($s[0] !== '{' && $s[0] !== '[') return false;
        json_decode($s);
        return (json_last_error() === JSON_ERROR_NONE);
    }
}