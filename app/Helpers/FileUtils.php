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

    /**
     * Parse a single error.log line into a structured array.
     * Returns keys: ts_raw_app, ts_display, type, rid, message, ctx, raw.
     */
    public static function parseErrorLogLine(string $line): array {
        $raw = rtrim($line, "\r\n");
        $res = [
            'ts_raw_app' => null,
            'ts_display' => null,
            'type' => 'other',
            'rid' => null,
            'message' => trim($raw),
            'ctx' => null,
            'raw' => $raw,
        ];
        $pattern = '/^\\[(?<ts1>[^\]]+)\\]\s*(?:\\[(?<ts2>\\d{4}-\\d{2}-\\d{2} [^\]]+)\\])?\s*(?:\\[(?<type>[a-zA-Z0-9_]+)\\])?\s*(?:\\[(?<rid>[a-f0-9]{16,})\\])?\s*(?<msg>.*?)(?:\s*\\|\\s*ctx=(?<ctx>\{.*\}))?$/';
        if (preg_match($pattern, $raw, $m)) {
            $tsRaw = $m['ts2'] ?? '';
            if ($tsRaw === '') { $tsRaw = $m['ts1'] ?? ''; }
            $res['ts_raw_app'] = $tsRaw !== '' ? $tsRaw : null;
            $res['ts_display'] = self::formatDisplayTs($tsRaw ?: ($m['ts1'] ?? ''));
            $type = isset($m['type']) && $m['type'] !== '' ? strtolower($m['type']) : 'other';
            $res['type'] = in_array($type, ['php_error','php_exception','php_fatal','http_404','http_405','http_500','power_exec'], true) ? $type : $type;
            $res['rid'] = $m['rid'] ?? null;
            $res['message'] = trim($m['msg'] ?? $raw);
            $ctx = $m['ctx'] ?? '';
            if ($ctx !== '') {
                $decoded = json_decode($ctx, true);
                $res['ctx'] = is_array($decoded) ? $decoded : $ctx;
            }
        }
        return $res;
    }

    /**
     * Parse many lines at once.
     * @return array<int,array>
     */
    public static function parseErrorLogLines(array $lines): array {
        $out = [];
        foreach ($lines as $ln) { $out[] = self::parseErrorLogLine((string)$ln); }
        return $out;
    }

    private static function formatDisplayTs(string $ts): ?string {
        if ($ts === '') return null;
        // Accept formats like '2025-09-15 17:02:03' or '15-Sep-2025 17:02:03 UTC'
        $months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        // Attempt to parse as Y-m-d H:i:s
        if (preg_match('/^(\\d{4})-(\\d{2})-(\\d{2}) (\\d{2}:\\d{2}:\\d{2})$/', $ts, $m)) {
            $y=(int)$m[1]; $mo=(int)$m[2]; $d=(int)$m[3]; $hms=$m[4];
            $mon = $months[max(1,$mo)-1] ?? 'Jan';
            return sprintf('%02d %s %04d – %s (UTC)', $d, $mon, $y, $hms);
        }
        // Fallback: return as-is
        return $ts;
    }
}
