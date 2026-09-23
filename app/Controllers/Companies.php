<?php

namespace App\Controllers;

use App\Libraries\Audit;
use App\Libraries\CustomFields;
use App\Libraries\Lists;
use App\Libraries\Permissions;
use App\Libraries\Records;
use App\Libraries\Settings;
use App\Libraries\Tags;
use App\Models\ActivityModel;
use App\Models\AttachmentModel;
use App\Models\CompanyModel;
use App\Models\ContactModel;
use App\Models\DealModel;
use App\Models\NoteModel;
use App\Models\UserModel;

class Companies extends BaseController
{
    private const PER_PAGE = 25;

    public function index()
    {
        $p = $this->request->getGet();
        $page = max(1, (int) ($p['page'] ?? 1));
        $b = Lists::companies($this->orgId(), $p);
        $total = (clone $b)->countAllResults(false);
        $rows = $b->limit(self::PER_PAGE, ($page - 1) * self::PER_PAGE)->get()->getResultArray();
        foreach ($rows as &$r) {
            $r['tags'] = $r['tags'] ? json_decode($r['tags'], true) : [];
        }
        $industries = array_column(db_connect()->table('companies')->select('industry')->distinct()->where('organization_id', $this->orgId())->where('industry IS NOT NULL')->orderBy('industry')->get()->getResultArray(), 'industry');
        return $this->render('companies/index', [
            'title' => 'Companies', 'rows' => $rows, 'total' => $total, 'page' => $page, 'perPage' => self::PER_PAGE, 'p' => $p, 'industries' => $industries,
            'users' => Lists::activeUsers($this->orgId()), 'tags' => Tags::names($this->orgId()), 'defs' => CustomFields::defs($this->orgId(), 'COMPANY'),
            'views' => Lists::savedViews($this->orgId(), 'COMPANY', $this->me['id']),
        ]);
    }

    public function show(int $id)
    {
        $company = model(CompanyModel::class)->findInOrg($this->orgId(), $id);
        if (! $company) {
            throw \Sync\Exceptions\PageNotFound::forPageNotFound();
        }
        $owner = $company['owner_id'] ? model(UserModel::class)->find($company['owner_id']) : null;
        $contacts = Lists::contacts($this->orgId(), ['company' => $id, 'sort' => 'name', 'dir' => 'asc'])->get()->getResultArray();
        $deals = Lists::deals($this->orgId(), ['company' => $id])->get()->getResultArray();
        return $this->render('companies/show', [
            'title' => $company['name'], 'company' => $company, 'owner' => $owner, 'contacts' => $contacts, 'deals' => $deals,
            'users' => Lists::activeUsers($this->orgId()), 'tags' => Tags::names($this->orgId()), 'defs' => CustomFields::defs($this->orgId(), 'COMPANY'),
            'notes' => Records::notes($this->orgId(), 'company_id', $id), 'files' => Records::attachments($this->orgId(), 'company_id', $id),
            'activities' => Records::activities($this->orgId(), 'company_id', $id), 'timeline' => Records::timeline($this->orgId(), 'COMPANY', $id),
            'shares' => Records::shares($this->orgId(), 'COMPANY', $id), 'teams' => db_connect()->table('teams')->where('organization_id', $this->orgId())->orderBy('name')->get()->getResultArray(),
            'canEdit' => Permissions::canEditRecord($this->me, 'COMPANY', $company), 'canDelete' => Permissions::canDeleteRecord($this->me, $company),
        ]);
    }

    private function readForm(): array
    {
        $name = $this->str('name', 160) ?? $this->fail('Company name is required');
        $email = $this->str('email', 190);
        if ($email !== null) {
            $email = strtolower($email);
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->fail('Enter a valid email');
            }
        }
        $ownerId = $this->intOrNull('owner_id');
        if ($ownerId && ! model(UserModel::class)->where('organization_id', $this->orgId())->where('is_active', 1)->find($ownerId)) {
            $this->fail('Owner must be an active user.');
        }
        return [
            'name' => $name, 'industry' => $this->str('industry', 80), 'website' => $this->str('website', 200), 'phone' => $this->str('phone', 30), 'email' => $email,
            'address_line' => $this->str('address_line', 200), 'city' => $this->str('city', 80), 'state' => $this->str('state', 80), 'postal_code' => $this->str('postal_code', 20),
            'country' => $this->str('country', 80) ?? 'India', 'description' => $this->str('description', 2000), 'owner_id' => $ownerId,
            'tags' => Tags::parse($this->str('tags')), 'custom_fields' => CustomFields::parse(CustomFields::defs($this->orgId(), 'COMPANY'), $this->request->getPost()),
        ];
    }

    public function create()
    {
        return $this->attempt(function () {
            $data = $this->readForm();
            $rule = Settings::dedupe($this->orgId())['companyName'];
            $dups = $rule === 'off' ? [] : Records::findDuplicateCompanies($this->orgId(), $data['name']);
            if ($dups && $rule === 'block') {
                $this->fail('A company named "' . $dups[0]['name'] . '" already exists. Duplicate companies are blocked by your workspace rules.');
            }
            if ($dups && ! $this->on('force')) {
                return redirect()->back()->withInput()->with('duplicates', $dups)->with('open', 'company-dialog');
            }
            $data['organization_id'] = $this->orgId();
            $data['owner_id'] ??= $this->me['id'];
            $id = model(CompanyModel::class)->insert($data);
            Tags::ensure($this->orgId(), $data['tags']);
            Audit::log($this->me, 'create', 'COMPANY', $id, $data['name'], null, $data);
            return $this->ok('Company created.', '/companies/' . $id);
        }, 'company-dialog');
    }

    public function update(int $id)
    {
        return $this->attempt(function () use ($id) {
            $existing = model(CompanyModel::class)->findInOrg($this->orgId(), $id) ?? $this->fail('Company not found.');
            Permissions::assert(Permissions::canEditRecord($this->me, 'COMPANY', $existing));
            $data = $this->readForm();
            model(CompanyModel::class)->update($id, $data);
            Tags::ensure($this->orgId(), $data['tags']);
            Audit::log($this->me, 'update', 'COMPANY', $id, $data['name'], $existing, $data);
            return $this->ok('Company updated.', '/companies/' . $id);
        }, 'company-dialog');
    }

    public function delete(int $id)
    {
        return $this->attempt(function () use ($id) {
            $existing = model(CompanyModel::class)->findInOrg($this->orgId(), $id) ?? $this->fail('Company not found.');
            Permissions::assert(Permissions::canDeleteRecord($this->me, $existing), 'Only the owner or an admin can delete this company.');
            model(CompanyModel::class)->delete($id);
            Audit::log($this->me, 'delete', 'COMPANY', $id, $existing['name'], $existing);
            return $this->ok('Company deleted.', '/companies');
        });
    }

    public function merge(int $id)
    {
        return $this->attempt(function () use ($id) {
            $sourceId = $this->intOrNull('source_id') ?? $this->fail('Choose the company to merge away.');
            if ($sourceId === $id) {
                $this->fail('Pick two different companies.');
            }
            $m = model(CompanyModel::class);
            $target = $m->findInOrg($this->orgId(), $id) ?? $this->fail('Company not found.');
            $source = $m->findInOrg($this->orgId(), $sourceId) ?? $this->fail('Company not found.');
            Permissions::assert(Permissions::canEditRecord($this->me, 'COMPANY', $target));
            Permissions::assert(Permissions::canDeleteRecord($this->me, $source), 'You need delete rights on the company being merged away.');
            $merged = [];
            foreach (['industry', 'website', 'phone', 'email', 'address_line', 'city', 'state', 'postal_code', 'description'] as $k) {
                $merged[$k] = $target[$k] ?? $source[$k];
            }
            $merged['tags'] = array_values(array_unique(array_merge($target['tags'] ?? [], $source['tags'] ?? [])));
            $merged['custom_fields'] = array_merge($source['custom_fields'] ?? [], $target['custom_fields'] ?? []);
            $db = db_connect();
            $db->transStart();
            foreach ([ContactModel::class, DealModel::class, ActivityModel::class, NoteModel::class, AttachmentModel::class] as $cls) {
                model($cls)->where('company_id', $sourceId)->set(['company_id' => $id])->update();
            }
            $m->update($id, $merged);
            $m->delete($sourceId);
            $db->transComplete();
            Audit::log($this->me, 'merge', 'COMPANY', $id, $target['name'], ['mergedFrom' => $source['name'], 'sourceId' => $sourceId], $merged);
            return $this->ok('Companies merged.', '/companies/' . $id);
        }, 'merge-dialog');
    }
}
