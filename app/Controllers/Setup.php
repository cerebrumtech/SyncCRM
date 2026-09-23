<?php

namespace App\Controllers;

use App\Libraries\Auth as AuthLib;
use App\Libraries\Audit;
use App\Libraries\Defaults;
use App\Models\OrganizationModel;
use App\Models\UserModel;

/** First-run: creates the organisation, the owner account and the default pipelines. */
class Setup extends BaseController
{
    public function index()
    {
        if (model(OrganizationModel::class)->countAllResults() > 0) {
            return redirect()->to('/login');
        }
        return view('auth/setup', ['title' => 'Set up your workspace']);
    }

    public function create()
    {
        if (model(OrganizationModel::class)->countAllResults() > 0) {
            return redirect()->to('/login')->with('error', 'This workspace is already set up. Please sign in.');
        }
        $orgName = $this->str('orgName', 160);
        $name = $this->str('name', 120);
        $email = strtolower((string) $this->str('email', 190));
        $password = (string) $this->request->getPost('password');
        $back = fn (string $m) => redirect()->back()->withInput()->with('error', $m);
        if (! $orgName || mb_strlen($orgName) < 2) {
            return $back('Organisation name is too short');
        }
        if (! $name || mb_strlen($name) < 2) {
            return $back('Enter your name');
        }
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $back('Enter a valid email');
        }
        if (strlen($password) < 8) {
            return $back('Password must be at least 8 characters');
        }

        $db = db_connect();
        $db->transStart();
        $orgId = model(OrganizationModel::class)->insert(['name' => $orgName, 'settings' => []]);
        $userId = model(UserModel::class)->insert(['organization_id' => $orgId, 'email' => $email, 'name' => $name, 'password_hash' => AuthLib::hash($password), 'role' => 'OWNER', 'color' => '#1B243E']);
        Defaults::createPipelines($orgId);
        $db->transComplete();

        $user = model(UserModel::class)->find($userId);
        Audit::log($user, 'setup', 'Organization', $orgId, $orgName);
        AuthLib::login($user);
        return redirect()->to('/dashboard')->with('success', 'Welcome to SyncCRM! Your workspace is ready.');
    }
}
