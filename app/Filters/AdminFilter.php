<?php

namespace App\Filters;

use App\Libraries\Auth;
use App\Libraries\Permissions;
use Sync\Http\Request;

class AdminFilter 
{
    public function before(Request $request)
    {
        $user = Auth::user();
        if ($user && Permissions::isAdmin($user)) {
            return null;
        }
        return redirect()->to('/dashboard')->with('error', 'Only admins can open that page.');
    }

}
