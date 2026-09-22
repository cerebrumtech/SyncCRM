<?php

namespace App\Filters;

use App\Libraries\Auth;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
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

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
