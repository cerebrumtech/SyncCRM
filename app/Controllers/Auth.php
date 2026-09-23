<?php

namespace App\Controllers;

use App\Libraries\Auth as AuthLib;
use App\Models\OrganizationModel;
use App\Models\UserModel;

class Auth extends BaseController
{
    public function login()
    {
        if (AuthLib::user()) {
            return redirect()->to('/dashboard');
        }
        if (model(OrganizationModel::class)->countAllResults() === 0) {
            return redirect()->to('/setup');
        }
        return view('auth/login', ['title' => 'Sign in', 'next' => $this->request->getGet('next')]);
    }

    public function signIn()
    {
        $email = strtolower((string) $this->str('email', 190));
        $password = (string) $this->request->getPost('password');
        $next = (string) $this->request->getPost('next');
        $back = fn (string $msg) => redirect()->to('/login' . ($next ? '?next=' . rawurlencode($next) : ''))->withInput()->with('error', $msg);
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $back('Enter a valid email');
        }
        if ($password === '') {
            return $back('Enter your password');
        }
        // Throttle guessing. Counted per email and per client address in the session-
        // independent store so a fresh cookie does not reset the count.
        if (AuthLib::tooManyAttempts($email)) {
            return $back('Too many sign-in attempts. Wait 15 minutes and try again.');
        }
        $user = model(UserModel::class)->where('email', $email)->first();
        if (! $user || ! AuthLib::verify($password, $user['password_hash'])) {
            AuthLib::noteFailedAttempt($email);
            return $back('Incorrect email or password.');
        }
        AuthLib::clearAttempts($email);
        if (! $user['is_active']) {
            return $back('This account has been deactivated. Contact your administrator.');
        }
        AuthLib::login($user);
        return redirect()->to($next !== '' && str_starts_with($next, '/') && ! str_starts_with($next, '//') ? $next : '/dashboard');
    }

    public function logout()
    {
        AuthLib::logout();
        return redirect()->to('/login');
    }
}
