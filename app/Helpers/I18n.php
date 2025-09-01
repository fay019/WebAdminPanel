<?php
namespace App\Helpers;

class I18n {
    private static array $cache = [];
    private static string $default = 'fr';

    public static function setLocale(string $locale): void {
        if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
        $_SESSION['locale'] = $locale;
        setcookie('locale', $locale, time()+31536000, '/');
    }

    public static function getLocale(): string {
        if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
        return $_SESSION['locale'] ?? ($_COOKIE['locale'] ?? self::$default);
    }

    public static function t(string $key, array $repl = []): string {
        $loc = self::getLocale();
        $dict = self::load($loc);
        $val = $dict[$key] ?? $key;
        foreach ($repl as $k=>$v) { $val = str_replace('{'.$k.'}', (string)$v, $val); }
        return $val;
    }

    private static function load(string $loc): array {
        if (isset(self::$cache[$loc])) return self::$cache[$loc];
        $file = __DIR__.'/../../lang/'.$loc.'.php';
        if (is_readable($file)) {
            $data = include $file;
            if (is_array($data)) return self::$cache[$loc] = $data;
        }
        return self::$cache[$loc] = [];
    }
}

if (!function_exists('__')) {
    function __(string $key, array $repl = []): string { return I18n::t($key, $repl); }
}
