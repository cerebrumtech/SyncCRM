<?php

namespace App\Filters;

use App\Libraries\Auth;
use App\Libraries\Permissions;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AdminFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $user = Auth::user();
        if ($user && Permissions::isAdmin($user)) {
            return null;
        }
        return redirect()->to('/dashboard')->with('error', 'Only admins can open that page.');
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
