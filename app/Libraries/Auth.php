<?php

namespace App\Libraries;

use App\Models\OrganizationModel;
use App\Models\UserModel;

/** Session-backed authentication. The user row is loaded once per request. */
class Auth
{
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
