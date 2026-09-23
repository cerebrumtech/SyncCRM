<?php

namespace Sync;

/** Reads a .env file in the simple "key = 'value'" form used by this app. */
class Env
{
    /** @var array<string,string> */
    private static $vars = [];

    public static function load(string $file): void
    {
        if (! is_file($file)) {
            return;
        }
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || $line[0] === ';') {
                continue;
            }
            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }
            $key = trim(substr($line, 0, $pos));
            $val = trim(substr($line, $pos + 1));
            $len = strlen($val);
            if ($len >= 2) {
                $first = $val[0];
                $last  = $val[$len - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $val = substr($val, 1, -1);
                }
            }
            self::$vars[$key] = $val;
        }
    }

    public static function get(string $key, string $default = ''): string
    {
        if (isset(self::$vars[$key])) {
            return self::$vars[$key];
        }
        $fromServer = getenv($key);
        return $fromServer === false ? $default : (string) $fromServer;
    }

    public static function has(string $key): bool
    {
        return isset(self::$vars[$key]);
    }
}
