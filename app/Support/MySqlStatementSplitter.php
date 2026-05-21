<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Split MySQL dump text into executable statements (respects single-quoted strings and '' escapes).
 */
final class MySqlStatementSplitter
{
    /**
     * @return list<string>
     */
    public static function split(string $sql): array
    {
        $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
        $out = [];
        $buf = '';
        $inString = false;
        $len = strlen($sql);
        for ($i = 0; $i < $len; $i++) {
            $c = $sql[$i];
            if ($inString) {
                if ($c === "'") {
                    if ($i + 1 < $len && $sql[$i + 1] === "'") {
                        $buf .= "''";
                        $i++;

                        continue;
                    }
                    $inString = false;
                }
                $buf .= $c;

                continue;
            }
            if ($c === "'") {
                $inString = true;
                $buf .= $c;

                continue;
            }
            if ($c === ';') {
                $t = trim($buf);
                if ($t !== '') {
                    $out[] = $t;
                }
                $buf = '';

                continue;
            }
            $buf .= $c;
        }
        $t = trim($buf);
        if ($t !== '') {
            $out[] = $t;
        }

        return $out;
    }
}
