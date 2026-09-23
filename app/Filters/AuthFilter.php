<?php

namespace App\Filters;

use App\Libraries\Auth;
use Sync\Http\Request;

class AuthFilter 
{
    public function before(Request $request)
    {
        if (Auth::user()) {
            return null;
        }
        if ($request->isAJAX() || str_starts_with($request->getUri()->getPath(), 'api/')) {
            return service('response')->setStatusCode(401)->setJSON(['ok' => false, 'error' => 'Please sign in again.']);
        }
        $next = '/' . ltrim($request->getUri()->getPath(), '/');
        return redirect()->to('/login' . ($next !== '/' ? '?next=' . rawurlencode($next) : ''));
    }

}
