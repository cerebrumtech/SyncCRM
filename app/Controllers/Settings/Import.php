<?php

namespace App\Controllers\Settings;

use App\Controllers\BaseController;
use App\Libraries\Audit;
use App\Libraries\CustomFields;
use App\Libraries\Tags;
use App\Models\CompanyModel;
use App\Models\ContactModel;
use App\Models\UserModel;

/** Three-step CSV import: upload -> map columns -> run. The parsed file waits in writable/imports. */
class Import extends BaseController
{
    private const CONTACT_FIELDS = ['first_name' => 'First name', 'last_name' => 'Last name', 'email' => 'Email', 'phone' => 'Phone', 'whatsapp_number' => 'WhatsApp number', 'job_title' => 'Job title', 'company_name' => 'Company (by name)', 'owner_email' => 'Owner (by email)', 'tags' => 'Tags (; separated)'];
    private const COMPANY_FIELDS = ['name' => 'Company name', 'industry' => 'Industry', 'website' => 'Website', 'phone' => 'Phone', 'email' => 'Email', 'address_line' => 'Address', 'city' => 'City', 'state' => 'State', 'postal_code' => 'PIN code', 'country' => 'Country', 'description' => 'Description', 'owner_email' => 'Owner (by email)', 'tags' => 'Tags (; separated)'];
    private const MAX_ROWS = 5000;

    private function dir(): string
    {
        $d = WRITEPATH . 'imports';
        if (! is_dir($d)) {
            @mkdir($d, 0775, true);
        }
        return $d;
    }

    private function targets(string $entity): array
    {
        $t = $entity === 'CONTACT' ? self::CONTACT_FIELDS : self::COMPANY_FIELDS;
        foreach (CustomFields::defs($this->orgId(), $entity) as $d) {
            $t['cf_' . $d['field_key']] = $d['label'] . ' (custom)';
        }
        return $t;
    }

    public function index()
    {
        $pending = session()->get('import');
        $data = ['title' => 'Import CSV', 'pending' => null, 'summary' => session()->getFlashdata('import_summary')];
        if ($pending && is_file($pending['file'])) {
            $rows = json_decode(file_get_contents($pending['file']), true) ?: [];
            $data['pending'] = ['entity' => $pending['entity'], 'name' => $pending['name'], 'header' => $rows[0] ?? [], 'sample' => array_slice($rows, 1, 3), 'count' => max(0, count($rows) - 1), 'targets' => $this->targets($pending['entity']), 'guess' => $this->guess($rows[0] ?? [], $this->targets($pending['entity']))];
        }
        return $this->render('settings/import', $data);
    }

    private function guess(array $header, array $targets): array
    {
        $out = [];
        foreach ($header as $i => $h) {
            $n = slugify((string) $h);
            $alias = ['firstname' => 'first_name', 'lastname' => 'last_name', 'mobile' => 'phone', 'phone_number' => 'phone', 'whatsapp' => 'whatsapp_number', 'company' => 'company_name', 'organisation' => 'company_name', 'organization' => 'company_name', 'title' => 'job_title', 'designation' => 'job_title', 'owner' => 'owner_email', 'pin' => 'postal_code', 'pincode' => 'postal_code', 'pin_code' => 'postal_code', 'address' => 'address_line', 'company_name' => isset($targets['name']) ? 'name' : 'company_name'];
            $k = $alias[$n] ?? $n;
            if (isset($targets[$k]) && ! in_array($k, $out, true)) {
                $out[$i] = $k;
            } elseif (isset($targets['cf_' . $n]) && ! in_array('cf_' . $n, $out, true)) {
                $out[$i] = 'cf_' . $n;
            }
        }
        return $out;
    }

    public function upload()
    {
        return $this->attempt(function () {
            $entity = strtoupper((string) $this->str('entity'));
            if (! in_array($entity, ['CONTACT', 'COMPANY'], true)) {
                $this->fail('Choose what to import.');
            }
            $file = $this->request->getFile('file');
            if (! $file || ! $file->isValid() || $file->getSize() === 0) {
                $this->fail('Choose a CSV file.');
            }
            if ($file->getSize() > 10 * 1024 * 1024) {
                $this->fail('CSV must be under 10 MB.');
            }
            $h = fopen($file->getTempName(), 'r');
            $rows = [];
            $bom = fread($h, 3);
            if ($bom !== "\xEF\xBB\xBF") {
                rewind($h);
            }
            while (($r = fgetcsv($h, 0, ',', '"', '\\')) !== false) {
                if (count($r) === 1 && trim((string) $r[0]) === '') {
                    continue;
                }
                $rows[] = array_map(fn ($v) => mb_substr(trim((string) $v), 0, 2000), array_slice($r, 0, 100));
                if (count($rows) > self::MAX_ROWS + 1) {
                    fclose($h);
                    $this->fail('Import at most 5,000 rows at a time.');
                }
            }
            fclose($h);
            if (count($rows) < 2) {
                $this->fail('The file needs a header row and at least one data row.');
            }
            $path = $this->dir() . '/' . $this->orgId() . '-' . $this->me['id'] . '-' . bin2hex(random_bytes(6)) . '.json';
            file_put_contents($path, json_encode($rows));
            session()->set('import', ['entity' => $entity, 'file' => $path, 'name' => $file->getClientName()]);
            return redirect()->to('/settings/import');
        });
    }

    public function run()
    {
        return $this->attempt(function () {
            $pending = session()->get('import');
            if ($this->request->getPost('cancel') !== null) {
                if ($pending && is_file($pending['file'])) {
                    @unlink($pending['file']);
                }
                session()->remove('import');
                return redirect()->to('/settings/import');
            }
            if (! $pending || ! is_file($pending['file'])) {
                $this->fail('Upload a CSV first.');
            }
            $entity = $pending['entity'];
            $rows = json_decode(file_get_contents($pending['file']), true) ?: [];
            array_shift($rows);
            $mapping = array_filter((array) $this->request->getPost('map'), fn ($v) => is_string($v) && $v !== '');
            $onDuplicate = $this->str('on_duplicate') ?? 'skip';
            if (! in_array($onDuplicate, ['skip', 'update', 'create'], true)) {
                $this->fail('Invalid duplicate option.');
            }
            $targets = array_values($mapping);
            if ($entity === 'CONTACT' && ! in_array('first_name', $targets, true)) {
                $this->fail('Map a column to First name.');
            }
            if ($entity === 'COMPANY' && ! in_array('name', $targets, true)) {
                $this->fail('Map a column to Company name.');
            }
            $col = array_flip($mapping); // target => column index
            $get = fn (array $row, string $key) => isset($col[$key]) ? trim((string) ($row[(int) $col[$key]] ?? '')) : '';
            $defs = CustomFields::defs($this->orgId(), $entity);
            $ownerByEmail = [];
            foreach (model(UserModel::class)->where('organization_id', $this->orgId())->where('is_active', 1)->findAll() as $u) {
                $ownerByEmail[strtolower($u['email'])] = $u['id'];
            }
            // 'noPhoneKey' counts rows whose phone could not be normalised. They import
            // fine, but carry no duplicate-detection key, so a later import of the same
            // person would not be spotted. Worth saying out loud during a migration.
            $summary = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'noPhoneKey' => 0, 'errors' => []];
            $allTags = [];
            $contacts = model(ContactModel::class);
            $companies = model(CompanyModel::class);
            $strip = fn (array $a) => array_filter($a, fn ($v) => $v !== null && $v !== '');
            foreach ($rows as $i => $row) {
                $line = $i + 2;
                try {
                    $ownerEmail = strtolower($get($row, 'owner_email'));
                    $ownerId = $ownerEmail ? ($ownerByEmail[$ownerEmail] ?? $this->me['id']) : $this->me['id'];
                    $tags = Tags::parse(str_replace(';', ',', $get($row, 'tags')));
                    $allTags = array_merge($allTags, $tags);
                    $cf = [];
                    foreach ($defs as $d) {
                        $v = $get($row, 'cf_' . $d['field_key']);
                        if ($v === '') {
                            continue;
                        }
                        $cf[$d['field_key']] = $d['type'] === 'NUMBER' ? (float) $v : ($d['type'] === 'CHECKBOX' ? (bool) preg_match('/^(yes|true|1|y)$/i', $v) : $v);
                    }
                    if ($entity === 'COMPANY') {
                        $name = $get($row, 'name');
                        if ($name === '') {
                            throw new \RuntimeException('missing company name');
                        }
                        $existing = $companies->where('organization_id', $this->orgId())->where('LOWER(name)', mb_strtolower($name))->first();
                        $fields = ['industry' => $get($row, 'industry') ?: null, 'website' => $get($row, 'website') ?: null, 'phone' => $get($row, 'phone') ?: null, 'email' => strtolower($get($row, 'email')) ?: null, 'address_line' => $get($row, 'address_line') ?: null, 'city' => $get($row, 'city') ?: null, 'state' => $get($row, 'state') ?: null, 'postal_code' => $get($row, 'postal_code') ?: null, 'country' => $get($row, 'country') ?: 'India', 'description' => $get($row, 'description') ?: null];
                        if ($existing && $onDuplicate === 'skip') {
                            $summary['skipped']++;
                            continue;
                        }
                        if ($existing && $onDuplicate === 'update') {
                            $companies->update($existing['id'], $strip($fields) + ['tags' => array_values(array_unique(array_merge($existing['tags'] ?? [], $tags))), 'custom_fields' => array_merge($existing['custom_fields'] ?? [], $cf)]);
                            $summary['updated']++;
                            continue;
                        }
                        $companies->insert(['organization_id' => $this->orgId(), 'name' => $name, 'tags' => $tags, 'custom_fields' => $cf, 'owner_id' => $ownerId] + $fields);
                        $summary['created']++;
                    } else {
                        $firstName = $get($row, 'first_name');
                        if ($firstName === '') {
                            throw new \RuntimeException('missing first name');
                        }
                        $email = strtolower($get($row, 'email')) ?: null;
                        $phone = $get($row, 'phone') ?: null;
                        $wa = $get($row, 'whatsapp_number') ?: null;
                        $pn = normalize_phone($phone) ?? normalize_phone($wa);
                        $companyId = null;
                        $companyName = $get($row, 'company_name');
                        if ($companyName !== '') {
                            $c = $companies->where('organization_id', $this->orgId())->where('LOWER(name)', mb_strtolower($companyName))->first();
                            $companyId = $c ? $c['id'] : $companies->insert(['organization_id' => $this->orgId(), 'name' => $companyName, 'owner_id' => $ownerId, 'tags' => [], 'custom_fields' => []]);
                        }
                        $existing = null;
                        if ($email || $pn) {
                            $q = $contacts->where('organization_id', $this->orgId())->groupStart();
                            if ($email) {
                                $q->where('email', $email);
                            }
                            if ($pn) {
                                $q->orWhere('phone_normalized', $pn);
                            }
                            $existing = $q->groupEnd()->first();
                        }
                        $fields = ['last_name' => $get($row, 'last_name') ?: null, 'email' => $email, 'phone' => $phone, 'phone_normalized' => $pn, 'whatsapp_number' => $wa, 'job_title' => $get($row, 'job_title') ?: null, 'company_id' => $companyId];
                    if (($phone !== null && $phone !== '' && $pn === null)) {
                        $summary['noPhoneKey']++;
                    }
                        if ($existing && $onDuplicate === 'skip') {
                            $summary['skipped']++;
                            continue;
                        }
                        if ($existing && $onDuplicate === 'update') {
                            $contacts->update($existing['id'], ['first_name' => $firstName] + $strip($fields) + ['tags' => array_values(array_unique(array_merge($existing['tags'] ?? [], $tags))), 'custom_fields' => array_merge($existing['custom_fields'] ?? [], $cf)]);
                            $summary['updated']++;
                            continue;
                        }
                        $contacts->insert(['organization_id' => $this->orgId(), 'first_name' => $firstName, 'tags' => $tags, 'custom_fields' => $cf, 'owner_id' => $ownerId] + $fields);
                        $summary['created']++;
                    }
                } catch (\Throwable $e) {
                    $summary['errors'][] = "Row {$line}: " . $e->getMessage();
                    if (count($summary['errors']) > 50) {
                        $summary['errors'][] = 'Stopped after 50 errors.';
                        break;
                    }
                }
            }
            Tags::ensure($this->orgId(), array_values(array_unique($allTags)));
            Audit::log($this->me, 'import', $entity, '*', "{$summary['created']} created, {$summary['updated']} updated, {$summary['skipped']} skipped", null, ['errors' => count($summary['errors'])]);
            @unlink($pending['file']);
            session()->remove('import');
            return redirect()->to('/settings/import')->with('import_summary', $summary + ['entity' => $entity]);
        });
    }
}
