<?php

namespace App\Controllers;

use App\Libraries\Auth as AuthLib;
use App\Libraries\Audit;
use App\Models\InviteModel;
use App\Models\OrganizationModel;
use App\Models\UserModel;

class Invite extends BaseController
{
    private function valid(string $token): ?array
    {
        $inv = model(InviteModel::class)->where('token', $token)->first();
        if (! $inv || $inv['status'] !== 'PENDING' || strtotime($inv['expires_at']) < time()) {
            return null;
        }
        return $inv;
    }

    public function show(string $token)
    {
        $inv = $this->valid($token);
        if (! $inv) {
            return view('auth/invite', ['title' => 'Invitation', 'invalid' => true]);
        }
        $org = model(OrganizationModel::class)->find($inv['organization_id']);
        return view('auth/invite', ['title' => 'Join ' . $org['name'], 'invite' => $inv, 'org' => $org, 'token' => $token]);
    }

    public function accept(string $token)
    {
        $inv = $this->valid($token);
        if (! $inv) {
            return redirect()->to('/login')->with('error', 'This invitation is no longer valid.');
        }
        $name = $this->str('name', 120);
        $password = (string) $this->request->getPost('password');
        if (! $name || mb_strlen($name) < 2) {
            return redirect()->back()->withInput()->with('error', 'Enter your name');
        }
        if (strlen($password) < 8) {
            return redirect()->back()->withInput()->with('error', 'Password must be at least 8 characters');
        }
        $users = model(UserModel::class);
        if ($users->where('email', $inv['email'])->first()) {
            return redirect()->to('/login')->with('error', 'An account with this email already exists. Please sign in.');
        }
        $colors = ['#0068FF', '#04A2FB', '#10B981', '#F59E0B', '#8B5CF6', '#EC4899', '#1B243E'];
        $id = $users->insert(['organization_id' => $inv['organization_id'], 'email' => $inv['email'], 'name' => $name, 'password_hash' => AuthLib::hash($password), 'role' => $inv['role'], 'color' => $colors[array_rand($colors)]]);
        model(InviteModel::class)->update($inv['id'], ['status' => 'ACCEPTED']);
        $user = $users->find($id);
        Audit::log($user, 'accept_invite', 'User', $id, $inv['email']);
        AuthLib::login($user);
        return redirect()->to('/dashboard')->with('success', 'Welcome aboard!');
    }
}
