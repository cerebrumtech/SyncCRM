<?php

namespace App\Controllers;

use App\Database\Seeds\DemoSeeder;
use Sync\Schema;
use Throwable;

/**
 * One-time web installer for hosts without shell access: creates the tables
 * and optionally loads the demo data. Enabled only while app.installKey is set.
 */
class Install extends BaseController
{
    private function allowed(): bool
    {
        $key = (string) config('App')->installKey;
        $sent = (string) ($this->request->getGet('key') ?? $this->request->getPost('key') ?? '');
        return $key !== '' && hash_equals($key, $sent);
    }

    public function index()
    {
        if (! $this->allowed()) {
            return $this->response->setStatusCode(404)->setBody('Not found');
        }
        return view('install', [
            'title'  => 'Install SyncCRM',
            'key'    => $this->request->getGet('key'),
            'status' => $this->status(),
        ]);
    }

    private function status(): array
    {
        try {
            $db = db_connect();
            $db->query('SELECT 1');
            $migrated = Schema::isInstalled();
            $orgs     = $migrated ? $db->table('organizations')->countAllResults() : null;
            return ['db' => true, 'migrated' => $migrated, 'orgs' => $orgs, 'error' => null, 'writable' => is_writable(WRITEPATH)];
        } catch (Throwable $e) {
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
            Schema::install();
            $log[] = 'Database tables are up to date.';
            if ($this->request->getPost('seed') === '1') {
                ob_start();
                (new DemoSeeder())->run();
                ob_end_clean();
                $log[] = 'Demo data loaded (owner@syncworkstech.com / password123).';
            }
        } catch (Throwable $e) {
            $log[] = 'Error: ' . $e->getMessage();
        }
        return view('install', [
            'title'  => 'Install SyncCRM',
            'key'    => $this->request->getPost('key'),
            'status' => $this->status(),
            'log'    => $log,
        ]);
    }
}
