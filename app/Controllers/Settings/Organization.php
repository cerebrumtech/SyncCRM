<?php

namespace App\Controllers\Settings;

use App\Controllers\BaseController;
use App\Libraries\Audit;
use App\Libraries\Settings;
use App\Models\OrganizationModel;

class Organization extends BaseController
{
    public function index()
    {
        return $this->render('settings/organization', ['title' => 'Organisation', 'dedupe' => Settings::dedupe($this->orgId())]);
    }

    public function update()
    {
        return $this->attempt(function () {
            $name = $this->str('name', 160);
            if (! $name || mb_strlen($name) < 2) {
                $this->fail('Name is too short');
            }
            model(OrganizationModel::class)->update($this->orgId(), ['name' => $name]);
            Audit::log($this->me, 'update', 'Organization', $this->orgId(), $name, ['name' => $this->org['name']], ['name' => $name]);
            return $this->ok('Organisation updated.', '/settings/organization');
        });
    }

    public function dedupe()
    {
        return $this->attempt(function () {
            $rules = [];
            foreach (['contactEmail', 'contactPhone', 'companyName'] as $k) {
                $v = $this->str($k) ?? 'warn';
                if (! in_array($v, ['off', 'warn', 'block'], true)) {
                    $this->fail('Invalid duplicate rule.');
                }
                $rules[$k] = $v;
            }
            Settings::set($this->orgId(), 'dedupe', $rules);
            Audit::log($this->me, 'update_dedupe_rules', 'Organization', $this->orgId(), $this->org['name'], null, $rules);
            return $this->ok('Duplicate rules saved.', '/settings/organization');
        });
    }
}
