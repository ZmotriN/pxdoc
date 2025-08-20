<?php


class JSON5
{

    public static function decode(string $json, bool $assoc = false, int $depth = 512, int $flags = 0)
    {
        $s = self::remove_bom($json);
        $s = self::strip_comments($s);
        $s = self::convert_single_quoted_strings_to_double($s);
        $s = self::quote_unquoted_keys($s);
        $s = self::strip_trailing_commas($s);
        $s = self::normalize_numbers($s); // +123 -> 123, 0xFF -> 255
        return json_decode($s, $assoc, $depth, $flags);
    }

    /* ---------- Helpers ---------- */

    private static function remove_bom(string $s): string
    {
        return preg_replace('/^\xEF\xBB\xBF/', '', $s) ?? $s;
    }

    private static function strip_comments(string $s): string
    {
        $out = '';
        $len = strlen($s);
        $inStr = false;
        $quote = null;
        $esc = false;
        $inLine = false;
        $inBlock = false;

        for ($i = 0; $i < $len; $i++) {
            $ch = $s[$i];
            $n  = $i + 1 < $len ? $s[$i + 1] : '';

            if ($inStr) {
                $out .= $ch;
                if ($esc) {
                    $esc = false;
                    continue;
                }
                if ($ch === '\\') {
                    $esc = true;
                    continue;
                }
                if ($ch === $quote) {
                    $inStr = false;
                    $quote = null;
                }
                continue;
            }

            if ($inLine) {
                if ($ch === "\n" || $ch === "\r") {
                    $inLine = false;
                    $out .= $ch;
                }
                continue;
            }

            if ($inBlock) {
                if ($ch === '*' && $n === '/') {
                    $inBlock = false;
                    $i++;
                }
                continue;
            }

            // Not in string or comment
            if ($ch === '"' || $ch === "'") {
                $inStr = true;
                $quote = $ch;
                $out .= $ch;
                continue;
            }
            if ($ch === '/' && $n === '/') {
                $inLine = true;
                $i++;
                continue;
            }
            if ($ch === '/' && $n === '*') {
                $inBlock = true;
                $i++;
                continue;
            }

            $out .= $ch;
        }
        return $out;
    }

    private static function convert_single_quoted_strings_to_double(string $s): string
    {
        $out = '';
        $len = strlen($s);
        $inStr = false;
        $quote = null;
        $esc = false;

        for ($i = 0; $i < $len; $i++) {
            $ch = $s[$i];
            if (!$inStr && $ch === "'") {
                // Convertir cette chaîne '...' en "..." en échappant les guillemets
                $inStr = true;
                $quote = "'";
                $out .= '"';
                $i++;
                for (; $i < $len; $i++) {
                    $c = $s[$i];
                    if ($esc) {
                        // Conserver l’échappement tel quel
                        $out .= '\\' . $c;
                        $esc = false;
                        continue;
                    }
                    if ($c === '\\') {
                        $esc = true;
                        continue;
                    }
                    if ($c === '"') {
                        $out .= '\"';
                        continue;
                    }
                    if ($c === "'") {
                        $out .= '"';
                        $inStr = false;
                        $quote = null;
                        break;
                    }
                    $out .= $c;
                }
                continue;
            }

            if ($inStr) {
                $out .= $ch;
                if ($esc) {
                    $esc = false;
                    continue;
                }
                if ($ch === '\\') {
                    $esc = true;
                    continue;
                }
                if ($ch === $quote) {
                    $inStr = false;
                    $quote = null;
                }
                continue;
            }

            if ($ch === '"') {
                $inStr = true;
                $quote = '"';
            }

            $out .= $ch;
        }
        return $out;
    }

    private static function quote_unquoted_keys(string $s): string
    {
        $out = '';
        $len = strlen($s);
        $inStr = false;
        $quote = null;
        $esc = false;
        $stack = []; // 'obj' | 'arr'

        $i = 0;
        while ($i < $len) {
            $ch = $s[$i];
            $next = $i + 1 < $len ? $s[$i + 1] : '';

            // String state
            if ($inStr) {
                $out .= $ch;
                if ($esc) {
                    $esc = false;
                    $i++;
                    continue;
                }
                if ($ch === '\\') {
                    $esc = true;
                    $i++;
                    continue;
                }
                if ($ch === $quote) {
                    $inStr = false;
                    $quote = null;
                }
                $i++;
                continue;
            }

            if ($ch === '"' || $ch === "'") {
                $inStr = true;
                $quote = $ch;
                $out .= $ch;
                $i++;
                continue;
            }
            if ($ch === '{') {
                array_push($stack, 'obj');
                $out .= $ch;
                $i++;
                continue;
            }
            if ($ch === '[') {
                array_push($stack, 'arr');
                $out .= $ch;
                $i++;
                continue;
            }
            if ($ch === '}') {
                array_pop($stack);
                $out .= $ch;
                $i++;
                continue;
            }
            if ($ch === ']') {
                array_pop($stack);
                $out .= $ch;
                $i++;
                continue;
            }

            // Si on est dans un objet, détecter une clé non citée: { foo: ... }
            if (!empty($stack) && end($stack) === 'obj' && preg_match('/[A-Za-z_\$]/', $ch)) {
                // Only if this token is followed by ':' (en ignorant les espaces)
                $start = $i;
                $name = $ch;
                $j = $i + 1;
                while ($j < $len && preg_match('/[A-Za-z0-9_\$]/', $s[$j])) {
                    $name .= $s[$j];
                    $j++;
                }
                $k = $j;
                while ($k < $len && ctype_space($s[$k])) {
                    $k++;
                }
                if ($k < $len && $s[$k] === ':') {
                    // C’est bien une clé
                    $out .= '"' . $name . '"';
                    $i = $j; // repositionné sur l’espace (ou le :)
                    continue;
                }
            }

            $out .= $ch;
            $i++;
        }
        return $out;
    }

    private static function strip_trailing_commas(string $s): string
    {
        $out = '';
        $len = strlen($s);
        $inStr = false;
        $quote = null;
        $esc = false;

        for ($i = 0; $i < $len; $i++) {
            $ch = $s[$i];

            if ($inStr) {
                $out .= $ch;
                if ($esc) {
                    $esc = false;
                    continue;
                }
                if ($ch === '\\') {
                    $esc = true;
                    continue;
                }
                if ($ch === $quote) {
                    $inStr = false;
                    $quote = null;
                }
                continue;
            }

            if ($ch === '"' || $ch === "'") {
                $inStr = true;
                $quote = $ch;
                $out .= $ch;
                continue;
            }

            if ($ch === ',') {
                // Regarder les prochains non-espaces
                $j = $i + 1;
                while ($j < $len && ctype_space($s[$j])) $j++;
                if ($j < $len && ($s[$j] === '}' || $s[$j] === ']')) {
                    // On supprime la virgule finale
                    continue;
                }
            }

            $out .= $ch;
        }
        return $out;
    }

    private static function normalize_numbers(string $s): string
    {
        $out = '';
        $len = strlen($s);
        $inStr = false;
        $quote = null;
        $esc = false;

        for ($i = 0; $i < $len; $i++) {
            $ch = $s[$i];

            if ($inStr) {
                $out .= $ch;
                if ($esc) {
                    $esc = false;
                    continue;
                }
                if ($ch === '\\') {
                    $esc = true;
                    continue;
                }
                if ($ch === $quote) {
                    $inStr = false;
                    $quote = null;
                }
                continue;
            }

            if ($ch === '"' || $ch === "'") {
                $inStr = true;
                $quote = $ch;
                $out .= $ch;
                continue;
            }

            // +number  -> number
            if ($ch === '+') {
                $prev = rtrim(substr($out, -1), " \t\r\n");
                $next = $i + 1 < $len ? $s[$i + 1] : '';
                if ($next !== '' && (ctype_digit($next) || $next === '.')) {
                    // Contexte autorisé : début, après [, {, ,, :
                    if ($prev === '' || in_array($prev, ['[', '{', ',', ':'], true)) {
                        continue; // on saute le '+'
                    }
                }
            }

            // 0x... -> décimal
            if ($ch === '0' && $i + 1 < $len && ($s[$i + 1] === 'x' || $s[$i + 1] === 'X')) {
                $j = $i + 2;
                $hex = '';
                while ($j < $len && preg_match('/[0-9A-Fa-f]/', $s[$j])) {
                    $hex .= $s[$j];
                    $j++;
                }
                if ($hex !== '') {
                    $out .= (string) hexdec($hex);
                    $i = $j - 1;
                    continue;
                }
            }

            $out .= $ch;
        }
        return $out;
    }
    
}
