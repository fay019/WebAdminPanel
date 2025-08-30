<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

function flash(string $type, string $msg, bool $is_html = false): void {
    $_SESSION['flash'][] = ['t'=>$type, 'm'=>$msg, 'h'=>$is_html];
}

function show_flash(): void {
    if (empty($_SESSION['flash'])) return;
    foreach ($_SESSION['flash'] as $f) {
        $cls = $f['t'] === 'ok' ? 'ok' : ($f['t'] === 'err' ? 'err' : 'info');
        echo '<div class="flash '.$cls.'">';
        // si h=true, on considère le contenu déjà sécurisé (HTML prêt à rendre)
        if (!empty($f['h'])) {
            echo $f['m'];
        } else {
            echo htmlspecialchars($f['m'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        echo '</div>';
    }
    unset($_SESSION['flash']);
}