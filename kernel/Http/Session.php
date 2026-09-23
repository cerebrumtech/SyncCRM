<?php

namespace Sync\Http;

/** Native PHP session with CodeIgniter-style flash data. */
class Session
{
    public function start(string $cookieName, int $lifetime): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        session_name($cookieName);
        $secure = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
        // Promote what the previous request flashed, then clear the outbox.
        $_SESSION['_flash_now']  = $_SESSION['_flash_next'] ?? [];
        $_SESSION['_flash_next'] = [];
    }

    /** @return mixed */
    public function get(string $key) { return $_SESSION[$key] ?? null; }

    public function set(string $key, $value): void { $_SESSION[$key] = $value; }
    public function has(string $key): bool { return isset($_SESSION[$key]); }
    public function remove(string $key): void { unset($_SESSION[$key]); }

    public function setFlashdata(string $key, $value): void
    {
        $_SESSION['_flash_next'][$key] = $value;
    }

    /** @return mixed */
    public function getFlashdata(string $key)
    {
        return $_SESSION['_flash_now'][$key] ?? null;
    }

    public function regenerate(bool $destroy = false): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id($destroy);
        }
    }

    public function destroy(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }
}
