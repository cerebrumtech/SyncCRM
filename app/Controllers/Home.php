<?php

namespace App\Controllers;

use App\Libraries\Auth;
use App\Models\OrganizationModel;

class Home extends BaseController
{
    public function index()
    {
        if (model(OrganizationModel::class)->countAllResults() === 0) {
            return redirect()->to('/setup');
        }
        return redirect()->to(Auth::user() ? '/dashboard' : '/login');
    }
}
