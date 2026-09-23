<?php

namespace App\Controllers\Settings;

use App\Controllers\BaseController;
use App\Libraries\Audit;
use App\Libraries\CustomFields;
use App\Models\CustomFieldDefinitionModel;

class Fields extends BaseController
{
    private const ENTITIES = ['CONTACT' => 'Contacts', 'COMPANY' => 'Companies', 'DEAL' => 'Deals'];

    public function index()
    {
        $defs = [];
        foreach (array_keys(self::ENTITIES) as $e) {
            $defs[$e] = CustomFields::defs($this->orgId(), $e);
        }
        return $this->render('settings/fields', ['title' => 'Custom fields', 'defs' => $defs, 'entities' => self::ENTITIES, 'types' => CustomFields::TYPES]);
    }

    private function options(): array
    {
        $out = [];
        foreach (preg_split('/[\n,]+/', (string) $this->str('options')) as $o) {
            $o = trim($o);
            if ($o !== '' && ! in_array($o, $out, true)) {
                $out[] = mb_substr($o, 0, 60);
            }
        }
        return $out;
    }

    public function create()
    {
        return $this->attempt(function () {
            $entity = strtoupper((string) $this->str('entity'));
            if (! isset(self::ENTITIES[$entity])) {
                $this->fail('Choose a record type.');
            }
            $label = $this->str('label', 60) ?? $this->fail('Label is required');
            $type = $this->str('type') ?? 'TEXT';
            if (! in_array($type, CustomFields::TYPES, true)) {
                $this->fail('Invalid field type.');
            }
            $options = $type === 'SELECT' ? $this->options() : [];
            if ($type === 'SELECT' && ! $options) {
                $this->fail('Add at least one option for a dropdown field.');
            }
            $key = slugify($label) ?: 'field_' . time();
            $m = model(CustomFieldDefinitionModel::class);
            if ($m->where('organization_id', $this->orgId())->where('entity', $entity)->where('field_key', $key)->first()) {
                $this->fail('A field with this name already exists for this record type.');
            }
            $count = $m->where('organization_id', $this->orgId())->where('entity', $entity)->countAllResults();
            $id = $m->insert(['organization_id' => $this->orgId(), 'entity' => $entity, 'field_key' => $key, 'label' => $label, 'type' => $type, 'options' => $options, 'required' => $this->on('required'), 'position' => $count]);
            Audit::log($this->me, 'create', 'CustomField', $id, "$entity: $label", null, ['type' => $type, 'options' => $options]);
            return $this->ok('Field added.', '/settings/fields');
        }, 'field-dialog');
    }

    public function update(int $id)
    {
        return $this->attempt(function () use ($id) {
            $m = model(CustomFieldDefinitionModel::class);
            $def = $m->findInOrg($this->orgId(), $id) ?? $this->fail('Field not found.');
            $dir = $this->str('move');
            if ($dir) {
                $siblings = $m->where('organization_id', $this->orgId())->where('entity', $def['entity'])->orderBy('position')->orderBy('id')->findAll();
                $ids = array_column($siblings, 'id');
                $i = array_search($id, $ids, true);
                $j = $i + ($dir === 'up' ? -1 : 1);
                if ($j >= 0 && $j < count($ids)) {
                    [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
                    foreach ($ids as $pos => $sid) {
                        $m->update($sid, ['position' => $pos]);
                    }
                }
                return redirect()->to('/settings/fields');
            }
            $label = $this->str('label', 60) ?? $this->fail('Label is required');
            $options = $def['type'] === 'SELECT' ? $this->options() : [];
            if ($def['type'] === 'SELECT' && ! $options) {
                $this->fail('Add at least one option for a dropdown field.');
            }
            $m->update($id, ['label' => $label, 'options' => $options, 'required' => $this->on('required')]);
            Audit::log($this->me, 'update', 'CustomField', $id, $def['entity'] . ': ' . $label, ['label' => $def['label'], 'options' => $def['options'], 'required' => $def['required']], ['label' => $label, 'options' => $options]);
            return $this->ok('Field updated.', '/settings/fields');
        }, 'field-edit-dialog');
    }

    public function delete(int $id)
    {
        return $this->attempt(function () use ($id) {
            $def = model(CustomFieldDefinitionModel::class)->findInOrg($this->orgId(), $id) ?? $this->fail('Field not found.');
            model(CustomFieldDefinitionModel::class)->delete($id);
            Audit::log($this->me, 'delete', 'CustomField', $id, $def['entity'] . ': ' . $def['label']);
            return $this->ok('Field deleted. Existing values stay stored but are no longer shown.', '/settings/fields');
        });
    }
}
