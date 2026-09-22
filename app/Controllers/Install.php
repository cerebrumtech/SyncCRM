<?php

namespace App\Controllers;

use CodeIgniter\Database\Exceptions\DatabaseException;

/**
 * One-time web installer for hosts without shell access: runs the migrations
 * (and optionally the demo seed). Enabled only while app.installKey is set in .env.
 */
class Install extends BaseController
{
    private function allowed(): bool
    {
        $key = (string) config('App')->installKey;
        return $key !== '' && hash_equals($key, (string) ($this->request->getGet('key') ?? $this->request->getPost('key')));
    }

    public function index()
    {
        if (! $this->allowed()) {
            return $this->response->setStatusCode(404)->setBody('Not found');
        }
        return view('install', ['title' => 'Install SyncCRM', 'key' => $this->request->getGet('key'), 'status' => $this->status()]);
    }

    private function status(): array
    {
        try {
            $db = db_connect();
            $db->query('SELECT 1');
            $tables = $db->listTables();
            $orgs = in_array('organizations', $tables, true) ? $db->table('organizations')->countAllResults() : null;
            return ['db' => true, 'migrated' => in_array('organizations', $tables, true), 'orgs' => $orgs, 'error' => null, 'writable' => is_writable(WRITEPATH)];
        } catch (\Throwable $e) {
            return ['db' => false, 'migrated' => false, 'orgs' => null, 'error' => $e->getMessage(), 'writable' => is_writable(WRITEPATH)];
        }
    }

    public function run()
    {
        if (! $this->allowed()) {
            return $this->response->setStatusCode(404)->setBody('Not found');
        }
        $log = [];
        try {
            $migrate = service('migrations');
            $migrate->setNamespace('App');
            $migrate->latest();
            $log[] = 'Database tables are up to date.';
            if ($this->request->getPost('seed') === '1') {
                $seeder = \Config\Database::seeder();
                $seeder->setSilent(true);
                $seeder->call('DemoSeeder');
                $log[] = 'Demo data loaded (owner@syncworkstech.com / password123).';
            }
        } catch (\Throwable $e) {
            $log[] = 'Error: ' . $e->getMessage();
        }
        return view('install', ['title' => 'Install SyncCRM', 'key' => $this->request->getPost('key'), 'status' => $this->status(), 'log' => $log]);
    }
}
