<?php

namespace App\Controllers;

use App\Libraries\Lists;
use App\Libraries\Permissions;
use App\Models\SavedViewModel;

class Views extends BaseController
{
    public function create()
    {
        return $this->attempt(function () {
            $entity = strtoupper((string) $this->str('entity'));
            if (! isset(Lists::PATHS[$entity])) {
                $this->fail('Unknown list.');
            }
            $name = $this->str('name', 60) ?? $this->fail('Give the view a name');
            parse_str((string) $this->str('filters'), $filters);
            $filters = array_filter($filters, fn ($v, $k) => is_string($v) && $v !== '' && $k !== 'page', ARRAY_FILTER_USE_BOTH);
            model(SavedViewModel::class)->insert(['organization_id' => $this->orgId(), 'entity' => $entity, 'name' => $name, 'filters' => $filters, 'owner_id' => $this->me['id'], 'is_shared' => $this->on('is_shared')]);
            return redirect()->to(Lists::PATHS[$entity] . ($filters ? '?' . http_build_query($filters) : ''))->with('success', 'View saved.');
        }, 'save-view-dialog');
    }

    public function delete(int $id)
    {
        return $this->attempt(function () use ($id) {
            $view = model(SavedViewModel::class)->findInOrg($this->orgId(), $id) ?? $this->fail('View not found.');
            Permissions::assert($view['owner_id'] === $this->me['id'] || Permissions::isAdmin($this->me), 'You can only delete your own views.');
            model(SavedViewModel::class)->delete($id);
            return redirect()->to(Lists::PATHS[$view['entity']])->with('success', 'View deleted.');
        });
    }
}
