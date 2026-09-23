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

class Contacts extends BaseController
{
    private const PER_PAGE = 25;

    public function index()
    {
        $p = $this->request->getGet();
        $page = max(1, (int) ($p['page'] ?? 1));
        $b = Lists::contacts($this->orgId(), $p);
        $total = (clone $b)->countAllResults(false);
        $rows = $b->limit(self::PER_PAGE, ($page - 1) * self::PER_PAGE)->get()->getResultArray();
        foreach ($rows as &$r) {
            $r['tags'] = $r['tags'] ? json_decode($r['tags'], true) : [];
        }
        $prefill = ['company_id' => $p['company'] ?? null];
        if ($prefill['company_id']) {
            $co = model(CompanyModel::class)->findInOrg($this->orgId(), (int) $prefill['company_id']);
            $prefill['company_name'] = $co['name'] ?? '';
        }
        return $this->render('contacts/index', [
            'title' => 'Contacts', 'rows' => $rows, 'total' => $total, 'page' => $page, 'perPage' => self::PER_PAGE, 'p' => $p,
            'users' => Lists::activeUsers($this->orgId()), 'tags' => Tags::names($this->orgId()), 'defs' => CustomFields::defs($this->orgId(), 'CONTACT'),
            'views' => Lists::savedViews($this->orgId(), 'CONTACT', $this->me['id']), 'prefill' => $prefill,
        ]);
    }

    public function show(int $id)
    {
        $contact = model(ContactModel::class)->findInOrg($this->orgId(), $id);
        if (! $contact) {
            throw \Sync\Exceptions\PageNotFound::forPageNotFound();
        }
        $company = $contact['company_id'] ? model(CompanyModel::class)->find($contact['company_id']) : null;
        $owner = $contact['owner_id'] ? model(UserModel::class)->find($contact['owner_id']) : null;
        $deals = Lists::deals($this->orgId(), ['contact' => $id])->get()->getResultArray();
        return $this->render('contacts/show', [
            'title' => full_name($contact), 'contact' => $contact, 'company' => $company, 'owner' => $owner, 'deals' => $deals,
            'users' => Lists::activeUsers($this->orgId()), 'tags' => Tags::names($this->orgId()), 'defs' => CustomFields::defs($this->orgId(), 'CONTACT'),
            'notes' => Records::notes($this->orgId(), 'contact_id', $id), 'files' => Records::attachments($this->orgId(), 'contact_id', $id),
            'activities' => Records::activities($this->orgId(), 'contact_id', $id), 'timeline' => Records::timeline($this->orgId(), 'CONTACT', $id),
            'shares' => Records::shares($this->orgId(), 'CONTACT', $id), 'teams' => db_connect()->table('teams')->where('organization_id', $this->orgId())->orderBy('name')->get()->getResultArray(),
            'canEdit' => Permissions::canEditRecord($this->me, 'CONTACT', $contact), 'canDelete' => Permissions::canDeleteRecord($this->me, $contact),
        ]);
    }

    private function readForm(): array
    {
        $firstName = $this->str('first_name', 80) ?? $this->fail('First name is required');
        $email = $this->str('email', 190);
        if ($email !== null) {
            $email = strtolower($email);
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->fail('Enter a valid email');
            }
        }
        $companyId = $this->intOrNull('company_id');
        if ($companyId && ! model(CompanyModel::class)->findInOrg($this->orgId(), $companyId)) {
            $this->fail('Company not found.');
        }
        $ownerId = $this->intOrNull('owner_id');
        if ($ownerId && ! model(UserModel::class)->where('organization_id', $this->orgId())->where('is_active', 1)->find($ownerId)) {
            $this->fail('Owner must be an active user.');
        }
        $phone = $this->str('phone', 30);
        $whatsapp = $this->str('whatsapp_number', 30);
        return [
            'first_name' => $firstName, 'last_name' => $this->str('last_name', 80), 'email' => $email, 'phone' => $phone, 'whatsapp_number' => $whatsapp,
            'phone_normalized' => normalize_phone($phone) ?? normalize_phone($whatsapp), 'job_title' => $this->str('job_title', 120),
            'company_id' => $companyId, 'owner_id' => $ownerId, 'tags' => Tags::parse($this->str('tags')),
            'custom_fields' => CustomFields::parse(CustomFields::defs($this->orgId(), 'CONTACT'), $this->request->getPost()),
        ];
    }

    public function create()
    {
        return $this->attempt(function () {
            $data = $this->readForm();
            $rules = Settings::dedupe($this->orgId());
            $dups = array_values(array_filter(Records::findDuplicateContacts($this->orgId(), $data['email'], $data['phone'], $data['whatsapp_number']),
                fn ($d) => $d['reason'] === 'Same email' ? $rules['contactEmail'] !== 'off' : $rules['contactPhone'] !== 'off'));
            foreach ($dups as $d) {
                if (($d['reason'] === 'Same email' ? $rules['contactEmail'] : $rules['contactPhone']) === 'block') {
                    $this->fail($d['reason'] . ' as existing contact "' . $d['name'] . '". Duplicate contacts are blocked by your workspace rules.');
                }
            }
            if ($dups && ! $this->on('force')) {
                return redirect()->back()->withInput()->with('duplicates', $dups)->with('open', 'contact-dialog');
            }
            $data['organization_id'] = $this->orgId();
            $data['owner_id'] ??= $this->me['id'];
            $id = model(ContactModel::class)->insert($data);
            Tags::ensure($this->orgId(), $data['tags']);
            Audit::log($this->me, 'create', 'CONTACT', $id, full_name($data), null, $data);
            return $this->ok('Contact created.', '/contacts/' . $id);
        }, 'contact-dialog');
    }

    public function update(int $id)
    {
        return $this->attempt(function () use ($id) {
            $existing = model(ContactModel::class)->findInOrg($this->orgId(), $id) ?? $this->fail('Contact not found.');
            Permissions::assert(Permissions::canEditRecord($this->me, 'CONTACT', $existing));
            $data = $this->readForm();
            model(ContactModel::class)->update($id, $data);
            Tags::ensure($this->orgId(), $data['tags']);
            Audit::log($this->me, 'update', 'CONTACT', $id, full_name($data), $existing, $data);
            return $this->ok('Contact updated.', '/contacts/' . $id);
        }, 'contact-dialog');
    }

    public function delete(int $id)
    {
        return $this->attempt(function () use ($id) {
            $existing = model(ContactModel::class)->findInOrg($this->orgId(), $id) ?? $this->fail('Contact not found.');
            Permissions::assert(Permissions::canDeleteRecord($this->me, $existing), 'Only the owner or an admin can delete this contact.');
            model(ContactModel::class)->delete($id);
            Audit::log($this->me, 'delete', 'CONTACT', $id, full_name($existing), $existing);
            return $this->ok('Contact deleted.', '/contacts');
        });
    }

    /** Merges another contact (source_id) into this one and deletes the source. */
    public function merge(int $id)
    {
        return $this->attempt(function () use ($id) {
            $sourceId = $this->intOrNull('source_id') ?? $this->fail('Choose the contact to merge away.');
            if ($sourceId === $id) {
                $this->fail('Pick two different contacts.');
            }
            $m = model(ContactModel::class);
            $target = $m->findInOrg($this->orgId(), $id) ?? $this->fail('Contact not found.');
            $source = $m->findInOrg($this->orgId(), $sourceId) ?? $this->fail('Contact not found.');
            Permissions::assert(Permissions::canEditRecord($this->me, 'CONTACT', $target));
            Permissions::assert(Permissions::canDeleteRecord($this->me, $source), 'You need delete rights on the contact being merged away.');
            $merged = [];
            foreach (['last_name', 'email', 'phone', 'phone_normalized', 'whatsapp_number', 'job_title', 'company_id'] as $k) {
                $merged[$k] = $target[$k] ?? $source[$k];
            }
            $merged['tags'] = array_values(array_unique(array_merge($target['tags'] ?? [], $source['tags'] ?? [])));
            $merged['custom_fields'] = array_merge($source['custom_fields'] ?? [], $target['custom_fields'] ?? []);
            $db = db_connect();
            $db->transStart();
            foreach ([DealModel::class, ActivityModel::class, NoteModel::class, AttachmentModel::class] as $cls) {
                model($cls)->where('contact_id', $sourceId)->set(['contact_id' => $id])->update();
            }
            $m->update($id, $merged);
            $m->delete($sourceId);
            $db->transComplete();
            Audit::log($this->me, 'merge', 'CONTACT', $id, full_name($target), ['mergedFrom' => full_name($source), 'sourceId' => $sourceId], $merged);
            return $this->ok('Contacts merged.', '/contacts/' . $id);
        }, 'merge-dialog');
    }
}
