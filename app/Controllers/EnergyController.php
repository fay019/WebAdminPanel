<?php

namespace App\Controllers;

final class EnergyController
{
    private string $panelDir = '/srv/www/webadminpanel-v2';

    public function status(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $cmd = sprintf('sudo %s/bin/power_saver.sh status 2>&1', $this->panelDir);
        $out = trim(shell_exec($cmd) ?? '');
        echo $out !== '' ? $out : '{"hdmi":null,"wifi":"unknown","bluetooth":"unknown"}';
    }

    public function update(): void
    {
        $target = $_POST['target'] ?? '';
        $value  = $_POST['value'] ?? '';

        if ($target === 'bt') $target = 'bluetooth';

        $ok = match ($target) {
            'wifi', 'bluetooth' => in_array($value, ['on','off'], true),
            'hdmi'              => in_array($value, ['0','1'], true),
            default             => false,
        };

        if (!$ok) {
            http_response_code(400);
            echo json_encode(['error' => 'bad_params']);
            return;
        }

        $cmd = sprintf(
            'sudo %s/bin/power_saver.sh %s %s 2>&1',
            $this->panelDir,
            escapeshellarg($target),
            escapeshellarg($value)
        );
        $out = trim(shell_exec($cmd) ?? '');

        header('Content-Type: application/json; charset=utf-8');
        echo $out !== '' ? $out : '{"hdmi":null,"wifi":"unknown","bluetooth":"unknown"}';
    }
}