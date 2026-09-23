<?php

/**
 * Loads the SyncWorks sales hand-off into SyncCRM.
 *
 *   php schema/import-handoff.php --dir=/path/to/SyncCRM-Export --dry-run
 *   php schema/import-handoff.php --dir=/path/to/SyncCRM-Export --commit
 *
 * --dry-run does the whole import inside a transaction and rolls it back, so the
 * report is produced from a real load rather than a guess. Nothing is kept.
 * --commit does the same and keeps it.
 *
 * The demo workspace is cleared first: it is sample data, and leaving it in would
 * mix invented societies into a real pipeline.
 */

define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR);
define('ROOTPATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
define('APPPATH', ROOTPATH . 'app' . DIRECTORY_SEPARATOR);
define('KERNELPATH', ROOTPATH . 'kernel' . DIRECTORY_SEPARATOR);
define('WRITEPATH', ROOTPATH . 'writable' . DIRECTORY_SEPARATOR);

require KERNELPATH . 'bootstrap.php';

// ---------------------------------------------------------------- arguments
$opts = ['dir' => null, 'mode' => null];
foreach (array_slice($argv, 1) as $arg) {
    if (strpos($arg, '--dir=') === 0)      { $opts['dir'] = rtrim(substr($arg, 6), '/'); }
    elseif ($arg === '--dry-run')          { $opts['mode'] = 'dry'; }
    elseif ($arg === '--commit')           { $opts['mode'] = 'commit'; }
}
if (! $opts['dir'] || ! $opts['mode']) {
    fwrite(STDERR, "Usage: php schema/import-handoff.php --dir=<export folder> --dry-run|--commit\n");
    exit(2);
}
$csvDir = is_dir($opts['dir'] . '/csv') ? $opts['dir'] . '/csv' : $opts['dir'];

// ---------------------------------------------------------------- helpers
function readCsv(string $path): array
{
    if (! is_file($path)) {
        fwrite(STDERR, "Missing file: {$path}\n");
        exit(1);
    }
    $fh = fopen($path, 'r');
    // The export is UTF-8 without a BOM, but a spreadsheet round-trip can add one.
    $first = fread($fh, 3);
    if ($first !== "\xEF\xBB\xBF") {
        rewind($fh);
    }
    $head = fgetcsv($fh);
    $rows = [];
    while (($row = fgetcsv($fh)) !== false) {
        if (count($row) === 1 && trim((string) $row[0]) === '') {
            continue;
        }
        // Pad or trim so array_combine cannot fail on a ragged row.
        $row = array_slice(array_pad($row, count($head), null), 0, count($head));
        $rows[] = array_combine($head, $row);
    }
    fclose($fh);
    return $rows;
}

/** Empty string means "not known" in this export, never zero. */
function val(array $row, string $key)
{
    $v = $row[$key] ?? null;
    if ($v === null) { return null; }
    $v = trim((string) $v);
    return $v === '' ? null : $v;
}

function num(array $row, string $key)
{
    $v = val($row, $key);
    return $v === null ? null : (is_numeric($v) ? $v + 0 : null);
}

function intOrNull(array $row, string $key)
{
    $v = num($row, $key);
    return $v === null ? null : (int) $v;
}

function boolish(array $row, string $key): int
{
    $v = val($row, $key);
    return ($v === '1' || strtolower((string) $v) === 'true') ? 1 : 0;
}

/** Pipe-separated in CSV, a JSON array in the database. */
function listVal(array $row, string $key): array
{
    $v = val($row, $key);
    return $v === null ? [] : array_values(array_filter(array_map('trim', explode('|', $v)), 'strlen'));
}

/** ISO 8601 with +05:30, or a plain date, to what MySQL wants. */
function when(array $row, string $key, bool $dateOnly = false)
{
    $v = val($row, $key);
    if ($v === null) { return null; }
    try {
        $d = new DateTimeImmutable($v, new DateTimeZone('Asia/Kolkata'));
    } catch (Exception $e) {
        return null;
    }
    return $d->setTimezone(new DateTimeZone('Asia/Kolkata'))->format($dateOnly ? 'Y-m-d' : 'Y-m-d H:i:s');
}

$report = [];
$warn   = [];
function say(string $line) { global $report; $report[] = $line; echo $line . "\n"; }
function warn(string $line) { global $warn; $warn[] = $line; }

// ---------------------------------------------------------------- read everything first
say('== Reading the export');
$src = [
    'users'      => readCsv($csvDir . '/users.csv'),
    'products'   => readCsv($csvDir . '/products.csv'),
    'stages'     => readCsv($csvDir . '/pipeline_stages.csv'),
    'companies'  => readCsv($csvDir . '/companies.csv'),
    'contacts'   => readCsv($csvDir . '/contacts.csv'),
    'deals'      => readCsv($csvDir . '/deals.csv'),
    'activities' => readCsv($csvDir . '/activities.csv'),
    'notes'      => readCsv($csvDir . '/notes.csv'),
];
foreach ($src as $name => $rows) {
    say(sprintf('   %-11s %5d rows', $name, count($rows)));
}

$db = db_connect();
$db->query('SET FOREIGN_KEY_CHECKS = 1');
$db->transStart();

try {
    // ------------------------------------------------------------ organisation
    $org = $db->query('SELECT id, name FROM organizations ORDER BY id LIMIT 1')->getRowArray();
    if (! $org) {
        $db->query('INSERT INTO organizations (name, timezone, currency, created_at, updated_at)'
            . " VALUES ('SyncWorks Technologies Pvt. Ltd.', 'Asia/Kolkata', 'INR', NOW(), NOW())");
        $orgId = (int) $db->insertId();
        say('== Created the organisation');
    } else {
        $orgId = (int) $org['id'];
        say('== Using organisation: ' . $org['name']);
    }

    // ------------------------------------------------------------ clear the demo workspace
    say('== Clearing the demo workspace');
    $before = [];
    foreach (['companies', 'contacts', 'deals', 'activities', 'notes', 'products', 'pipelines'] as $t) {
        $before[$t] = (int) $db->query("SELECT COUNT(*) c FROM {$t}")->getRowArray()['c'];
    }
    // Children first; the foreign keys unlink rather than cascade now, so be explicit.
    foreach (['deal_line_items', 'notes', 'attachments', 'activities', 'record_shares',
              'deals', 'contacts', 'companies', 'products', 'saved_views', 'tags',
              'audit_logs', 'stages', 'pipelines'] as $t) {
        $db->query("DELETE FROM {$t}");
    }
    say(sprintf('   removed %d companies, %d contacts, %d deals, %d activities, %d notes',
        $before['companies'], $before['contacts'], $before['deals'], $before['activities'], $before['notes']));

    // ------------------------------------------------------------ users
    say('== Users');
    $userByEmail = [];
    $newPasswords = [];
    foreach ($src['users'] as $u) {
        $email = strtolower((string) val($u, 'email'));
        $name  = (string) val($u, 'name');
        $role  = strtoupper((string) val($u, 'role')) === 'OWNER' ? 'OWNER' : 'MEMBER';
        $color = val($u, 'colour') ?? val($u, 'color') ?? '#0068FF';
        $existing = $db->query('SELECT id FROM users WHERE organization_id = ? AND email = ?', [$orgId, $email])->getRowArray();
        if ($existing) {
            $db->query('UPDATE users SET name = ?, role = ?, color = ?, is_active = 1 WHERE id = ?',
                [$name, $role, $color, $existing['id']]);
            $userByEmail[$email] = (int) $existing['id'];
            say("   kept {$email} ({$role}) — password unchanged");
        } else {
            // The export's password column is a placeholder from the old local app and is
            // deliberately not carried across. A fresh one is generated and shown once.
            $plain = bin2hex(random_bytes(6));
            $db->query('INSERT INTO users (organization_id, email, name, password_hash, role, is_active, color, created_at, updated_at)'
                . ' VALUES (?, ?, ?, ?, ?, 1, ?, NOW(), NOW())',
                [$orgId, $email, $name, password_hash($plain, PASSWORD_BCRYPT), $role, $color]);
            $userByEmail[$email] = (int) $db->insertId();
            $newPasswords[$email] = $plain;
            say("   created {$email} ({$role})");
        }
    }
    $ownerId = null;
    foreach ($src['users'] as $u) {
        if (strtoupper((string) val($u, 'role')) === 'OWNER') {
            $ownerId = $userByEmail[strtolower((string) val($u, 'email'))];
            break;
        }
    }
    $ownerId = $ownerId ?? reset($userByEmail);
    $userId = function (?string $email) use ($userByEmail, $ownerId) {
        $e = strtolower((string) $email);
        return $userByEmail[$e] ?? $ownerId;
    };

    // ------------------------------------------------------------ products
    say('== Products');
    $productByName = [];
    foreach ($src['products'] as $p) {
        $db->query('INSERT INTO products (organization_id, name, sku, description, price, tax_rate, is_active, created_at, updated_at)'
            . ' VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())', [
            $orgId, val($p, 'name'), val($p, 'sku'), val($p, 'description'),
            num($p, 'unit_price_inr') ?? 0, num($p, 'tax_rate_pct') ?? 18, boolish($p, 'is_active'),
        ]);
        $productByName[strtolower((string) val($p, 'name'))] = (int) $db->insertId();
    }
    // SyncLOS was folded into SyncLMS; deals still name it.
    $productAlias = ['synclos' => 'synclms', 'syncenach' => 'synccollect', 'syncfinnect' => 'synccollect'];
    say('   ' . count($productByName) . ' products');

    // ------------------------------------------------------------ pipelines and stages
    say('== Pipelines and stages');
    $pipelineByName = $stageByKey = [];
    foreach ($src['stages'] as $s) {
        $pname = (string) val($s, 'pipeline');
        if (! isset($pipelineByName[$pname])) {
            $db->query('INSERT INTO pipelines (organization_id, name, position, is_default, created_at) VALUES (?, ?, ?, ?, NOW())',
                [$orgId, $pname, count($pipelineByName), boolish($s, 'is_default_pipeline')]);
            $pipelineByName[$pname] = (int) $db->insertId();
        }
        $db->query('INSERT INTO stages (pipeline_id, name, position, probability, is_won, is_lost, required_fields, color)'
            . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [
            $pipelineByName[$pname], val($s, 'stage'), intOrNull($s, 'position') ?? 0,
            intOrNull($s, 'probability_pct') ?? 0, boolish($s, 'is_won'), boolish($s, 'is_lost'),
            json_encode([]), val($s, 'colour') ?? '#0068FF',
        ]);
        $stageByKey[$pname . '|' . val($s, 'stage')] = (int) $db->insertId();
    }
    say('   ' . count($pipelineByName) . ' pipelines, ' . count($stageByKey) . ' stages');

    // ------------------------------------------------------------ companies
    say('== Companies');
    $companyByLegacy = $companyByName = [];
    $tagSeen = [];
    foreach ($src['companies'] as $c) {
        $tags = listVal($c, 'tags');
        foreach ($tags as $t) { $tagSeen[$t] = true; }
        $db->query('INSERT INTO companies (organization_id, name, industry, website, phone, alt_phone, email,'
            . ' address_line, city, district, state, postal_code, country, description,'
            . ' branches, deposits_cr, loan_book_cr, loan_customers, legacy_id,'
            . ' tags, custom_fields, owner_id, created_at, updated_at)'
            . ' VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())', [
            $orgId, val($c, 'name'), val($c, 'industry'), val($c, 'website'), val($c, 'phone'),
            val($c, 'alt_phone'), val($c, 'email'), val($c, 'address'), val($c, 'city'),
            val($c, 'district'), val($c, 'state'), null, val($c, 'country') ?? 'India',
            val($c, 'description'), intOrNull($c, 'branches'), num($c, 'deposits_cr'),
            num($c, 'loan_book_cr'), intOrNull($c, 'loan_customers'), val($c, 'id'),
            json_encode($tags), json_encode(array_filter(['source_sheets' => val($c, 'source_sheets')])),
            $userId(val($c, 'owner_email')),
            // first_seen_date is when the account first appears in the spreadsheet.
            when($c, 'first_seen_date') ?? date('Y-m-d H:i:s'),
        ]);
        $id = (int) $db->insertId();
        $companyByLegacy[(string) val($c, 'id')] = $id;
        $companyByName[strtolower((string) val($c, 'name'))] = $id;
    }
    say('   ' . count($companyByLegacy) . ' companies');

    /** A deal or contact may name a company the companies file does not carry. */
    $ensureCompany = function (?string $legacy, ?string $name) use (&$companyByLegacy, &$companyByName, $db, $orgId, $ownerId) {
        if ($legacy !== null && isset($companyByLegacy[$legacy])) { return $companyByLegacy[$legacy]; }
        if ($name === null || $name === '') { return null; }
        $key = strtolower($name);
        if (isset($companyByName[$key])) { return $companyByName[$key]; }
        $db->query('INSERT INTO companies (organization_id, name, country, legacy_id, tags, owner_id, created_at, updated_at)'
            . " VALUES (?, ?, 'India', ?, '[]', ?, NOW(), NOW())", [$orgId, $name, $legacy, $ownerId]);
        $id = (int) $db->insertId();
        if ($legacy !== null) { $companyByLegacy[$legacy] = $id; }
        $companyByName[$key] = $id;
        warn("Created a company the companies file did not carry: '{$name}'");
        return $id;
    };

    // ------------------------------------------------------------ contacts
    say('== Contacts');
    $contactByLegacy = [];
    $noPhoneKey = 0;
    foreach ($src['contacts'] as $c) {
        $tags = listVal($c, 'tags');
        foreach ($tags as $t) { $tagSeen[$t] = true; }
        $phone = val($c, 'phone');
        $intl  = val($c, 'phone_intl');
        $norm  = $intl !== null ? preg_replace('/\D/', '', $intl) : normalize_phone($phone);
        if ($phone !== null && ($norm === null || $norm === '')) { $noPhoneKey++; }
        $db->query('INSERT INTO contacts (organization_id, first_name, last_name, email, phone, phone_normalized,'
            . ' whatsapp_number, alt_phone, job_title, company_id, legacy_id, tags, custom_fields, owner_id, created_at, updated_at)'
            . ' VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())', [
            $orgId, val($c, 'first_name') ?? '(unnamed)', val($c, 'last_name'), val($c, 'email'),
            $phone, $norm ?: null, null, val($c, 'alt_phone'), val($c, 'job_title'),
            $ensureCompany(val($c, 'company_id'), val($c, 'company_name')), val($c, 'id'),
            json_encode($tags), json_encode(array_filter(['source_sheets' => val($c, 'source_sheets')])),
            $userId(val($c, 'owner_email')),
        ]);
        $contactByLegacy[(string) val($c, 'id')] = (int) $db->insertId();
    }
    say('   ' . count($contactByLegacy) . ' contacts');

    // ------------------------------------------------------------ deals
    say('== Deals');
    $dealByLegacy = [];
    $handoff = [];
    $stageMisses = $productMapped = 0;
    $position = [];
    foreach ($src['deals'] as $d) {
        $pname = (string) val($d, 'pipeline');
        $sname = (string) val($d, 'stage');
        $stageId = $stageByKey[$pname . '|' . $sname] ?? null;
        if ($stageId === null) {
            $stageMisses++;
            warn("Deal '" . val($d, 'title') . "' names an unknown stage {$pname}/{$sname}");
            continue;
        }
        $pipelineId = $pipelineByName[$pname];
        $position[$stageId] = ($position[$stageId] ?? 0) + 1;
        $tags = listVal($d, 'tags');
        foreach ($tags as $t) { $tagSeen[$t] = true; }
        $custom = array_filter([
            'referred_by'   => val($d, 'referred_by'),
            'demo_type'     => val($d, 'demo_type'),
            'teams_meeting' => val($d, 'teams_meeting'),
            'source_sheets' => val($d, 'source_sheets'),
        ]);
        $db->query('INSERT INTO deals (organization_id, title, pipeline_id, stage_id, status, amount, amount_is_manual,'
            . ' expected_close_date, closed_at, lost_reason, proposal_amount, amount_received, amount_pending,'
            . ' lead_source, original_lead_id, interest_level_pct, agreement_signed, lead_date, legacy_id,'
            . ' contact_id, company_id, owner_id, position, tags, custom_fields, created_at, updated_at)'
            . ' VALUES (?,?,?,?,?,?,1,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())', [
            $orgId, val($d, 'title'), $pipelineId, $stageId, strtoupper((string) (val($d, 'status') ?? 'OPEN')),
            num($d, 'amount_inr') ?? 0, when($d, 'expected_close_date', true), when($d, 'closed_at'),
            val($d, 'lost_reason'), num($d, 'proposal_amount_inr'), num($d, 'amount_received_inr'),
            num($d, 'amount_pending_inr'), val($d, 'lead_source'), val($d, 'original_lead_id'),
            intOrNull($d, 'interest_level_pct'), boolish($d, 'agreement_signed'), when($d, 'lead_date', true),
            val($d, 'id'),
            val($d, 'contact_id') !== null ? ($contactByLegacy[val($d, 'contact_id')] ?? null) : null,
            $ensureCompany(val($d, 'company_id'), val($d, 'company_name')),
            $userId(val($d, 'owner_email')), $position[$stageId],
            json_encode($tags), json_encode($custom),
            when($d, 'lead_date') ?? date('Y-m-d H:i:s'),
        ]);
        $dealId = (int) $db->insertId();
        $dealByLegacy[(string) val($d, 'id')] = $dealId;
        if (val($d, 'handoff_from_deal_id') !== null) {
            $handoff[$dealId] = (string) val($d, 'handoff_from_deal_id');
        }
        // products -> line items
        foreach (array_filter(array_map('trim', explode(',', (string) val($d, 'products')))) as $pn) {
            $key = strtolower($pn);
            if (isset($productAlias[$key])) { $key = $productAlias[$key]; $productMapped++; }
            if (! isset($productByName[$key])) { warn("Deal '" . val($d, 'title') . "' names unknown product '{$pn}'"); continue; }
            $db->query('INSERT INTO deal_line_items (deal_id, product_id, name, quantity, unit_price, discount_percent, tax_rate, total, position)'
                . ' VALUES (?, ?, ?, 1, 0, 0, 18, 0, 0)', [$dealId, $productByName[$key], $pn]);
        }
    }
    say('   ' . count($dealByLegacy) . ' deals, ' . $productMapped . ' product references remapped to the current catalogue');

    // second pass: the Onboarding deal points back at the Sales deal it came from
    $linked = 0;
    foreach ($handoff as $dealId => $legacy) {
        if (isset($dealByLegacy[$legacy])) {
            $db->query('UPDATE deals SET source_deal_id = ? WHERE id = ?', [$dealByLegacy[$legacy], $dealId]);
            $linked++;
        }
    }
    say('   ' . $linked . ' onboarding deals linked back to their sales deal');

    // ------------------------------------------------------------ activities
    say('== Activities');
    $acts = 0;
    foreach ($src['activities'] as $a) {
        $db->query('INSERT INTO activities (organization_id, type, title, description, status, due_at, end_at,'
            . ' all_day, location, call_direction, call_outcome, assignee_id, contact_id, company_id, deal_id,'
            . ' created_by_id, completed_at, created_at, updated_at)'
            . ' VALUES (?,?,?,?,?,?,?,0,?,?,?,?,?,?,?,?,?,NOW(),NOW())', [
            $orgId, strtoupper((string) (val($a, 'type') ?? 'TASK')), val($a, 'title'), val($a, 'description'),
            strtoupper((string) (val($a, 'status') ?? 'OPEN')), when($a, 'due_at'), when($a, 'end_at'),
            val($a, 'location'), val($a, 'call_direction'), val($a, 'call_outcome'),
            $userId(val($a, 'assignee_email')),
            val($a, 'contact_id') !== null ? ($contactByLegacy[val($a, 'contact_id')] ?? null) : null,
            val($a, 'company_id') !== null ? ($companyByLegacy[val($a, 'company_id')] ?? null) : null,
            val($a, 'deal_id') !== null ? ($dealByLegacy[val($a, 'deal_id')] ?? null) : null,
            $userId(val($a, 'assignee_email')), when($a, 'completed_at'),
        ]);
        $acts++;
    }
    say('   ' . $acts . ' activities');

    // ------------------------------------------------------------ notes
    say('== Notes');
    $noteCount = 0;
    foreach ($src['notes'] as $n) {
        $db->query('INSERT INTO notes (organization_id, body, contact_id, company_id, deal_id, author_id, created_at, updated_at)'
            . ' VALUES (?,?,?,?,?,?,?,NOW())', [
            $orgId, val($n, 'body'),
            val($n, 'contact_id') !== null ? ($contactByLegacy[val($n, 'contact_id')] ?? null) : null,
            val($n, 'company_id') !== null ? ($companyByLegacy[val($n, 'company_id')] ?? null) : null,
            val($n, 'deal_id') !== null ? ($dealByLegacy[val($n, 'deal_id')] ?? null) : null,
            $userId(val($n, 'author_email')), when($n, 'note_date') ?? date('Y-m-d H:i:s'),
        ]);
        $noteCount++;
    }
    say('   ' . $noteCount . ' notes');

    // ------------------------------------------------------------ tags
    foreach (array_keys($tagSeen) as $t) {
        $db->query('INSERT INTO tags (organization_id, name, color) VALUES (?, ?, ?)', [$orgId, $t, '#0068FF']);
    }
    say('== Tags: ' . count($tagSeen));

    // ------------------------------------------------------------ reconciliation
    say('');
    say('== Reconciliation (file -> database)');
    $checks = [
        ['companies',  count($src['companies']),  (int) $db->query('SELECT COUNT(*) c FROM companies')->getRowArray()['c']],
        ['contacts',   count($src['contacts']),   (int) $db->query('SELECT COUNT(*) c FROM contacts')->getRowArray()['c']],
        ['deals',      count($src['deals']),      (int) $db->query('SELECT COUNT(*) c FROM deals')->getRowArray()['c']],
        ['activities', count($src['activities']), (int) $db->query('SELECT COUNT(*) c FROM activities')->getRowArray()['c']],
        ['notes',      count($src['notes']),      (int) $db->query('SELECT COUNT(*) c FROM notes')->getRowArray()['c']],
    ];
    $bad = 0;
    foreach ($checks as [$name, $in, $out]) {
        $note = '';
        if ($name === 'companies' && $out > $in) { $note = ' (+' . ($out - $in) . ' created from a deal or contact)'; }
        elseif ($out !== $in) { $note = '  <-- MISMATCH'; $bad++; }
        say(sprintf('   %-11s in %5d   out %5d%s', $name, $in, $out, $note));
    }

    // Split by pipeline: the Onboarding pipeline's 'Live' stage is also a won stage, so a
    // single 'won' total counts a customer twice -- once for the sale, once for going live.
    $wonRows = $db->query("SELECT p.name pipeline, COUNT(*) c, COALESCE(SUM(d.amount),0) a,"
        . " COALESCE(SUM(d.amount_received),0) r, COALESCE(SUM(d.amount_pending),0) p"
        . " FROM deals d JOIN pipelines p ON p.id = d.pipeline_id"
        . " WHERE d.status = 'WON' GROUP BY p.name ORDER BY p.name")->getResultArray();
    foreach ($wonRows as $w) {
        say(sprintf('   won in %-12s %3d   value %14s   received %14s   pending %14s',
            $w['pipeline'], $w['c'], number_format((float) $w['a'], 2),
            number_format((float) $w['r'], 2), number_format((float) $w['p'], 2)));
    }
    $wonTotal = $db->query("SELECT COALESCE(SUM(amount),0) a, COALESCE(SUM(amount_received),0) r,"
        . " COALESCE(SUM(amount_pending),0) p, COUNT(*) c FROM deals WHERE status = 'WON'")->getRowArray();
    if (count($wonRows) > 1) {
        say('   NOTE the dashboard counts won deals across every pipeline, so the figure it shows');
        say('        will be the sum of the lines above, not the number of paying customers.');
    }
    $recvCheck = (float) $wonTotal['r'] + (float) $wonTotal['p'];
    if (abs($recvCheck - (float) $wonTotal['a']) > 0.01) {
        say(sprintf('   NOTE received + pending = %s, which does not equal the won value above',
            number_format($recvCheck, 2)));
    }

    say(sprintf('   contacts with no usable phone key: %d', $noPhoneKey));
    say(sprintf('   deals dropped for an unknown stage: %d', $stageMisses));
    $orphanDeals = (int) $db->query('SELECT COUNT(*) c FROM deals WHERE company_id IS NULL AND contact_id IS NULL')->getRowArray()['c'];
    say(sprintf('   deals with neither company nor contact: %d', $orphanDeals));

    if ($warn) {
        say('');
        say('== Notes for a human (' . count($warn) . ')');
        foreach (array_slice($warn, 0, 40) as $w) { say('   - ' . $w); }
        if (count($warn) > 40) { say('   ... and ' . (count($warn) - 40) . ' more'); }
    }

    if ($newPasswords) {
        say('');
        say('== New sign-ins (shown once)');
        foreach ($newPasswords as $email => $plain) { say(sprintf('   %-34s %s', $email, $plain)); }
        say('   Change these after the first sign-in.');
    }

    if ($bad > 0) {
        throw new RuntimeException("{$bad} table(s) did not match the file. Nothing was kept.");
    }

    if ($opts['mode'] === 'dry') {
        $db->transRollback();
        say('');
        say('DRY RUN — everything above was rolled back. Nothing was changed.');
    } else {
        $db->transComplete();
        say('');
        say('COMMITTED.');
    }
} catch (Throwable $e) {
    $db->transRollback();
    fwrite(STDERR, "\nFAILED: " . $e->getMessage() . "\nNothing was changed.\n");
    exit(1);
}

$log = WRITEPATH . 'logs/import-' . date('Ymd-His') . '.log';
@mkdir(dirname($log), 0775, true);
@file_put_contents($log, implode("\n", $report) . "\n");
echo "\nReport written to {$log}\n";
