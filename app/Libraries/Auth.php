<?php

namespace App\Libraries;

use App\Models\OrganizationModel;
use App\Models\UserModel;

/** Session-backed authentication. The user row is loaded once per request. */
class Auth
{
    /** Sign-in attempts allowed per email, and per client address, within the window. */
    private const MAX_PER_EMAIL = 10;
    private const MAX_PER_IP    = 25;
    private const WINDOW        = 900; // 15 minutes

    /** Where the throttle counters live. A file, so this needs no schema change. */
    private static function throttleFile(string $key): string
    {
        $dir = WRITEPATH . 'throttle';
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir . '/' . sha1($key) . '.json';
    }

    /** Timestamps of recent failures for one key, oldest entries dropped. */
    private static function recentFailures(string $key): array
    {
        $file = self::throttleFile($key);
        if (! is_file($file)) {
            return [];
        }
        $raw = @file_get_contents($file);
        $all = is_string($raw) ? json_decode($raw, true) : null;
        if (! is_array($all)) {
            return [];
        }
        $cutoff = time() - self::WINDOW;
        return array_values(array_filter($all, static fn ($t) => is_int($t) && $t >= $cutoff));
    }

    private static function clientIp(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    }

    /** True when this email or this address has failed too often recently. */
    public static function tooManyAttempts(string $email): bool
    {
        return count(self::recentFailures('email:' . $email)) >= self::MAX_PER_EMAIL
            || count(self::recentFailures('ip:' . self::clientIp())) >= self::MAX_PER_IP;
    }

    /** Record one failed sign-in against both the email and the address. */
    public static function noteFailedAttempt(string $email): void
    {
        foreach (['email:' . $email, 'ip:' . self::clientIp()] as $key) {
            $times   = self::recentFailures($key);
            $times[] = time();
            @file_put_contents(self::throttleFile($key), json_encode($times), LOCK_EX);
        }
    }

    /** A correct password clears the email's counter; the address keeps its own. */
    public static function clearAttempts(string $email): void
    {
        $file = self::throttleFile('email:' . $email);
        if (is_file($file)) {
            @unlink($file);
        }
    }

    private static ?array $user = null;
    private static bool $loaded = false;
    private static ?array $org = null;

    public static function user(): ?array
    {
        if (! self::$loaded) {
            self::$loaded = true;
            $id = session()->get('user_id');
            if ($id) {
                $u = model(UserModel::class)->find((int) $id);
                self::$user = ($u && $u['is_active']) ? $u : null;
                if (! self::$user) {
                    session()->remove('user_id');
                }
            }
        }
        return self::$user;
    }

    public static function org(): ?array
    {
        $u = self::user();
        if ($u && self::$org === null) {
            self::$org = model(OrganizationModel::class)->find($u['organization_id']);
        }
        return self::$org;
    }

    public static function login(array $user): void
    {
        session()->regenerate(true);
        session()->set('user_id', $user['id']);
        model(UserModel::class)->update($user['id'], ['last_login_at' => date('Y-m-d H:i:s')]);
        self::$user = $user;
        self::$loaded = true;
    }

    public static function logout(): void
    {
        session()->destroy();
        self::$user = null;
        self::$loaded = true;
    }

    public static function hash(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
    }

    public static function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public static function token(int $bytes = 24): string
    {
        return bin2hex(random_bytes($bytes));
    }
}
