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
        // A .env that exists but cannot be read is a permissions mistake, not a missing
        // config. Say so here rather than silently running with empty settings, which
        // surfaces much later as a confusing "Access denied for user ''@'localhost'".
        if (! is_readable($file)) {
            throw new \RuntimeException(
                $file . ' exists but is not readable by ' . self::currentUser() . ', the user this'
                . ' process runs as. Give that user read access, for example:'
                . ' chgrp www-data .env && chmod 640 .env'
            );
        }
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            throw new \RuntimeException('Could not read ' . $file . '.');
        }
        foreach ($lines as $line) {
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

    /** The OS user this process runs as, for permission error messages. */
    private static function currentUser(): string
    {
        if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
            $info = posix_getpwuid(posix_geteuid());
            if (is_array($info) && isset($info['name'])) {
                return $info['name'];
            }
        }
        $user = getenv('USER');
        return $user === false || $user === '' ? 'this process' : $user;
    }
}
