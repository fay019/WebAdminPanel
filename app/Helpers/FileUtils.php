<?php
namespace App\Helpers;

final class FileUtils {
    /**
     * Tail last N lines from a text file without loading entire file.
     * Returns an array of lines (without trailing newlines). If file is not readable, returns a single line message.
     */
    public static function tailFile(string $file, int $lines = 500): array {
        if ($lines <= 0) { return []; }
        if (!is_readable($file)) return ["[!] Fichier non lisible: $file"];
        $f = @fopen($file, 'rb');
        if ($f === false) return ["[!] Impossible d'ouvrir: $file"];
        $buffer = '';
        $pos = -1;
        $lineCount = 0;
        $result = [];
        fseek($f, 0, SEEK_END);
        $filesize = ftell($f);
        if ($filesize === 0) { fclose($f); return []; }
        while ($lineCount < $lines && -$pos < $filesize) {
            fseek($f, $pos, SEEK_END);
            $char = fgetc($f);
            if ($char === "\n" && $buffer !== '') {
                $result[] = strrev($buffer);
                $buffer = '';
                $lineCount++;
            } else {
                $buffer .= $char;
            }
            $pos--;
        }
        if ($buffer !== '') $result[] = strrev($buffer);
        fclose($f);
        return array_reverse($result);
    }
}
