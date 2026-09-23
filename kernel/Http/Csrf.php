<?php

namespace Sync\Http;

use Sync\Exceptions\SecurityError;

class Csrf
{
    const FIELD = 'csrf_token';

    public static function hash(): string
    {
        $s = session();
        if (! $s->has('_csrf')) {
            $s->set('_csrf', bin2hex(random_bytes(32)));
        }
        return (string) $s->get('_csrf');
    }

    public static function verify(Request $request): void
    {
        if (in_array($request->getMethod(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return;
        }
        $sent = (string) ($request->getPost(self::FIELD) ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if ($sent === '' || ! hash_equals(self::hash(), $sent)) {
            throw new SecurityError('The action you requested is not allowed.');
        }
    }
}
