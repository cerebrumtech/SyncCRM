<?php

namespace App\Controllers;

use App\Libraries\Audit;
use App\Libraries\SheetEdit;
use App\Libraries\Visibility;
use App\Models\CompanyModel;
use App\Models\ContactModel;

/** JSON search endpoints for record pickers. */
class Api extends BaseController
{
    public function search(string $kind)
    {
        $q = trim((string) $this->request->getGet('q'));
        $db = db_connect();
        $orgId = $this->orgId();
        $rows = [];
        if ($q !== '') {
            switch ($kind) {
                case 'contacts':
                    $b = $db->table('contacts c')->select('c.id, c.first_name, c.last_name, c.email, co.name AS company_name')->join('companies co', 'co.id = c.company_id', 'left')
                        ->where('c.organization_id', $orgId)->groupStart()->like('c.first_name', $q)->orLike('c.last_name', $q)->orLike('c.email', $q)->orLike('c.phone', $q)->groupEnd();
                    Visibility::apply($b, $this->me, 'c', 'CONTACT');
                    $r = $b->orderBy('c.updated_at', 'DESC')->limit(8)->get()->getResultArray();
                    $rows = array_map(fn ($x) => ['id' => $x['id'], 'label' => full_name($x), 'sub' => $x['email'] ?? $x['company_name'] ?? '', 'company_id' => null], $r);
                    break;
                case 'companies':
                    $b = $db->table('companies')->select('id, name, city')->where('organization_id', $orgId)->like('name', $q);
                    Visibility::apply($b, $this->me, 'companies', 'COMPANY');
                    $r = $b->orderBy('updated_at', 'DESC')->limit(8)->get()->getResultArray();
                    $rows = array_map(fn ($x) => ['id' => $x['id'], 'label' => $x['name'], 'sub' => $x['city'] ?? ''], $r);
                    break;
                case 'deals':
                    $b = $db->table('deals d')->select('d.id, d.title, p.name AS pipeline_name')->join('pipelines p', 'p.id = d.pipeline_id')->where('d.organization_id', $orgId)->like('d.title', $q);
                    Visibility::apply($b, $this->me, 'd', 'DEAL');
                    $r = $b->orderBy('d.updated_at', 'DESC')->limit(8)->get()->getResultArray();
                    $rows = array_map(fn ($x) => ['id' => $x['id'], 'label' => $x['title'], 'sub' => $x['pipeline_name']], $r);
                    break;
                case 'products':
                    $r = $db->table('products')->select('id, name, sku, price, tax_rate')->where('organization_id', $orgId)->where('is_active', 1)->groupStart()->like('name', $q)->orLike('sku', $q)->groupEnd()->limit(8)->get()->getResultArray();
                    $rows = array_map(fn ($x) => ['id' => $x['id'], 'label' => $x['name'], 'sub' => $x['sku'] ?? '', 'price' => $x['price'], 'tax_rate' => $x['tax_rate']], $r);
                    break;
                default:
                    return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'error' => 'Unknown search']);
            }
        }
        return $this->response->setJSON(['ok' => true, 'data' => $rows]);
    }

    /**
     * Creates the bare minimum record from a name typed into a picker, so a deal does not
     * have to be abandoned because the company is not in the system yet.
     *
     * Only a name is taken. Everything else is filled in later on the record's own page,
     * where the real form and its validation live. An exact name that already exists is
     * returned rather than duplicated - the picker is the one place a careless second
     * Acme Ltd is easiest to create.
     */
    public function quickCreate(string $kind)
    {
        $body = $this->jsonBody();
        $name = trim((string) ($body['name'] ?? ''));
        if ($name === '') {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'error' => 'Give it a name first.']);
        }
        $name = mb_substr($name, 0, 160);
        $orgId = $this->orgId();
        $db = db_connect();

        if ($kind === 'companies') {
            $found = $db->table('companies')->select('id, name, city')->where('organization_id', $orgId)->where('name', $name)->get()->getRowArray();
            if ($found) {
                return $this->response->setJSON(['ok' => true, 'existed' => true, 'data' => ['id' => (int) $found['id'], 'label' => $found['name'], 'sub' => $found['city'] ?? '']]);
            }
            $id = model(CompanyModel::class)->insert([
                'organization_id' => $orgId, 'name' => $name, 'country' => 'India',
                'tags' => [], 'custom_fields' => [], 'owner_id' => $this->me['id'],
            ]);
            Audit::log($this->me, 'create', 'COMPANY', $id, $name, null, ['name' => $name, 'via' => 'picker']);
            return $this->response->setJSON(['ok' => true, 'data' => ['id' => (int) $id, 'label' => $name, 'sub' => 'New company']]);
        }

        if ($kind === 'contacts') {
            // "Sunita Kale" -> first "Sunita", last "Kale". One word is a first name.
            $parts = preg_split('/\s+/', $name, 2);
            $first = mb_substr($parts[0], 0, 80);
            $last = isset($parts[1]) ? mb_substr($parts[1], 0, 80) : null;
            $found = $db->table('contacts')->select('id, first_name, last_name, email')->where('organization_id', $orgId)
                ->where('first_name', $first)->where('last_name', $last)->get()->getRowArray();
            if ($found) {
                return $this->response->setJSON(['ok' => true, 'existed' => true, 'data' => ['id' => (int) $found['id'], 'label' => trim($found['first_name'] . ' ' . (string) $found['last_name']), 'sub' => $found['email'] ?? '']]);
            }
            $id = model(ContactModel::class)->insert([
                'organization_id' => $orgId, 'first_name' => $first, 'last_name' => $last,
                'tags' => [], 'custom_fields' => [], 'owner_id' => $this->me['id'],
            ]);
            Audit::log($this->me, 'create', 'CONTACT', $id, $name, null, ['name' => $name, 'via' => 'picker']);
            return $this->response->setJSON(['ok' => true, 'data' => ['id' => (int) $id, 'label' => $name, 'sub' => 'New contact']]);
        }

        return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'error' => 'Cannot create that here.']);
    }

    /**
     * Save one cell of the sheet.
     *
     * Kept thin on purpose: everything that decides what may change, and what a value is
     * allowed to be, lives in App\Libraries\SheetEdit, because the sheet is the one place
     * a record is edited without its form and those rules must not drift apart.
     */
    public function sheetUpdate(string $kind)
    {
        $entities = ['contacts' => 'CONTACT', 'companies' => 'COMPANY', 'deals' => 'DEAL'];
        $entity = $entities[$kind] ?? null;

        if ($entity === null) {
            return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'error' => 'Unknown record type.']);
        }

        $body = $this->jsonBody();
        $id = (int) ($body['id'] ?? 0);
        $field = (string) ($body['field'] ?? '');
        $value = (string) ($body['value'] ?? '');

        if ($id <= 0 || $field === '') {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'error' => 'Nothing to save.']);
        }

        $result = SheetEdit::apply($this->me, $entity, $id, $field, $value);

        if (! $result['ok']) {
            // 422 rather than 400: the request was well formed, the value was not.
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'error' => $result['error']]);
        }

        return $this->response->setJSON([
            'ok'      => true,
            'display' => $result['display'],
            'value'   => $result['value'],
        ]);
    }
}
