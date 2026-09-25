<?php

namespace App\Libraries;

use App\Models\CompanyModel;
use App\Models\ContactModel;
use App\Models\DealModel;
use App\Models\StageModel;
use App\Models\UserModel;

/**
 * Editing a record from the sheet, one cell at a time.
 *
 * The sheet is the only place in this application where a record changes without its
 * form, so the rules the form enforces have to be repeated here rather than assumed.
 * Everything a cell can do goes through FIELDS: a column not named there cannot be
 * written, whatever the browser sends.
 *
 * Two things are deliberately NOT editable from the sheet:
 *
 *  - A deal's amount when it is worked out from the products on the deal. Typing over it
 *    would silently detach the figure from the line items and nobody would know which was
 *    right. Clear the products or set the amount on the deal itself.
 *  - Anything computed or historical: ids, created dates, deal counts.
 *
 * Moving a deal between stages goes through Deals::place() so that required fields and
 * the lost-reason rule still apply. The sheet must not be a way around them.
 */
class SheetEdit
{
    /**
     * What each entity allows.
     *
     * type: text | email | phone | date | money | int | url | select | lookup
     * A select carries its own small option list; a lookup searches an endpoint, because
     * 900 companies do not belong in a dropdown rendered on every row.
     */
    public const FIELDS = [
        'CONTACT' => [
            'first_name'      => ['label' => 'First name', 'type' => 'text', 'max' => 80],
            'last_name'       => ['label' => 'Last name', 'type' => 'text', 'max' => 80],
            'job_title'       => ['label' => 'Job title', 'type' => 'text', 'max' => 120],
            'email'           => ['label' => 'Email', 'type' => 'email', 'max' => 190],
            'phone'           => ['label' => 'Phone', 'type' => 'phone', 'max' => 30],
            'whatsapp_number' => ['label' => 'WhatsApp', 'type' => 'phone', 'max' => 30],
            'alt_phone'       => ['label' => 'Alt phone', 'type' => 'phone', 'max' => 30],
            'company_id'      => ['label' => 'Company', 'type' => 'lookup', 'search' => '/api/search/companies'],
            'owner_id'        => ['label' => 'Owner', 'type' => 'select', 'options' => 'users'],
        ],
        'COMPANY' => [
            'name'           => ['label' => 'Name', 'type' => 'text', 'max' => 160, 'required' => true],
            'industry'       => ['label' => 'Industry', 'type' => 'text', 'max' => 120],
            'website'        => ['label' => 'Website', 'type' => 'url', 'max' => 190],
            'email'          => ['label' => 'Email', 'type' => 'email', 'max' => 190],
            'phone'          => ['label' => 'Phone', 'type' => 'phone', 'max' => 30],
            'alt_phone'      => ['label' => 'Alt phone', 'type' => 'phone', 'max' => 30],
            'address_line'   => ['label' => 'Address', 'type' => 'text', 'max' => 200],
            'city'           => ['label' => 'City', 'type' => 'text', 'max' => 80],
            'district'       => ['label' => 'District', 'type' => 'text', 'max' => 80],
            'state'          => ['label' => 'State', 'type' => 'text', 'max' => 80],
            'postal_code'    => ['label' => 'Postal code', 'type' => 'text', 'max' => 20],
            'branches'       => ['label' => 'Branches', 'type' => 'int', 'max' => 65535],
            'deposits_cr'    => ['label' => 'Deposits (Cr)', 'type' => 'money'],
            'loan_book_cr'   => ['label' => 'Loan book (Cr)', 'type' => 'money'],
            'loan_customers' => ['label' => 'Loan customers', 'type' => 'int', 'max' => 100000000],
            'owner_id'       => ['label' => 'Owner', 'type' => 'select', 'options' => 'users'],
        ],
        'DEAL' => [
            'title'               => ['label' => 'Deal', 'type' => 'text', 'max' => 160, 'required' => true],
            'stage_id'            => ['label' => 'Stage', 'type' => 'select', 'options' => 'stages'],
            'expected_close_date' => ['label' => 'Expected close', 'type' => 'date'],
            'amount'              => ['label' => 'Amount', 'type' => 'money'],
            'owner_id'            => ['label' => 'Owner', 'type' => 'select', 'options' => 'users'],
            'contact_id'          => ['label' => 'Contact', 'type' => 'lookup', 'search' => '/api/search/contacts'],
            'proposal_amount'     => ['label' => 'Proposal', 'type' => 'money'],
            'amount_received'     => ['label' => 'Received', 'type' => 'money'],
            'amount_pending'      => ['label' => 'Pending', 'type' => 'money'],
            'lead_source'         => ['label' => 'Lead source', 'type' => 'text', 'max' => 120],
            'company_id'          => ['label' => 'Company', 'type' => 'lookup', 'search' => '/api/search/companies'],
        ],
    ];

    private const MODELS = [
        'CONTACT' => ContactModel::class,
        'COMPANY' => CompanyModel::class,
        'DEAL'    => DealModel::class,
    ];

    public static function spec(string $entity, string $field): ?array
    {
        return self::FIELDS[$entity][$field] ?? null;
    }

    /** The small option lists a select can offer, for the current organisation. */
    public static function options(string $entity, int $orgId): array
    {
        $out = ['users' => [['value' => '', 'label' => 'Unassigned']]];

        foreach (model(UserModel::class)->select('id, name')->where('organization_id', $orgId)
                     ->where('is_active', 1)->orderBy('name')->findAll() as $u) {
            $out['users'][] = ['value' => (string) $u['id'], 'label' => $u['name']];
        }

        if ($entity === 'DEAL') {
            $out['stages'] = [];
            $rows = db_connect()->table('stages s')
                ->select('s.id, s.name, p.name AS pipeline, s.pipeline_id')
                ->join('pipelines p', 'p.id = s.pipeline_id')
                ->where('p.organization_id', $orgId)
                ->orderBy('p.id')->orderBy('s.position')
                ->get()->getResultArray();
            foreach ($rows as $s) {
                $out['stages'][] = [
                    'value' => (string) $s['id'],
                    'label' => $s['pipeline'] . ' · ' . $s['name'],
                    'group' => (string) $s['pipeline_id'],
                ];
            }
        }

        return $out;
    }

    /**
     * Write one cell.
     *
     * @return array{ok:bool,display:string,value:string,error:string}
     */
    public static function apply(array $me, string $entity, int $id, string $field, string $raw): array
    {
        $spec = self::spec($entity, $field);
        if (! $spec) {
            return self::no('That column cannot be edited here.');
        }

        $orgId = (int) $me['organization_id'];
        $model = model(self::MODELS[$entity]);
        $record = $model->findInOrg($orgId, $id);

        if (! $record) {
            return self::no('That record no longer exists. Reload the page.');
        }
        if (! Permissions::canEditRecord($me, $entity, $record)) {
            return self::no('You can only edit records you own.');
        }

        $value = trim($raw);

        if (! empty($spec['required']) && $value === '') {
            return self::no($spec['label'] . ' cannot be empty.');
        }

        // Stage changes carry rules of their own and are handled separately.
        if ($entity === 'DEAL' && $field === 'stage_id') {
            return self::moveDeal($me, $record, $value, $orgId);
        }

        if ($entity === 'DEAL' && $field === 'amount' && self::amountIsDerived($record)) {
            return self::no('This amount is worked out from the products on the deal, so it cannot be typed over here. Open the deal to change the products.');
        }

        $clean = self::clean($spec, $value, $orgId, $entity);
        if (isset($clean['error'])) {
            return self::no($clean['error']);
        }

        $stored = $clean['value'];
        $before = $record;

        $data = [$field => $stored];

        // Typing an amount by hand means exactly that, and must survive a later recount.
        if ($entity === 'DEAL' && $field === 'amount') {
            $data['amount_is_manual'] = 1;
        }

        $model->update($id, $data);

        $after = $model->findInOrg($orgId, $id);

        // A model whose allowedFields does not list this column drops it without a word,
        // and the cell would report success while nothing changed - the worst kind of
        // failure, because the person walks away believing the record was updated. Read
        // it back and refuse to claim otherwise.
        if (! self::landed($after[$field] ?? null, $stored)) {
            return self::no('That column could not be saved. This is a fault in the application, not in what you typed.');
        }

        Audit::log($me, 'update', $entity, $id, self::titleOf($entity, $after), $before, $data);

        return [
            'ok'      => true,
            'display' => self::display($spec, $stored, $orgId, $entity),
            'value'   => $stored === null ? '' : (string) $stored,
            'error'   => '',
        ];
    }

    /** Stage changes go through the same gate as the board, so the rules still apply. */
    private static function moveDeal(array $me, array $deal, string $stageId, int $orgId): array
    {
        $stage = model(StageModel::class)->find((int) $stageId);
        if (! $stage) {
            return self::no('That stage does not exist.');
        }

        $pipelineOk = db_connect()->table('pipelines')
            ->where('id', $stage['pipeline_id'])->where('organization_id', $orgId)
            ->countAllResults() > 0;
        if (! $pipelineOk) {
            return self::no('That stage belongs to another organisation.');
        }
        if ((int) $stage['pipeline_id'] !== (int) $deal['pipeline_id']) {
            return self::no('Moving a deal to a different pipeline has to be done on the deal itself.');
        }

        $missing = Deals::missingFields($stage, $deal, CustomFields::defs($orgId, 'DEAL'));
        if ($missing) {
            return self::no('This stage needs ' . implode(', ', $missing) . ' filled in first. Open the deal to do that.');
        }
        if (! empty($stage['is_lost']) && empty($deal['lost_reason'])) {
            return self::no('A lost deal needs a reason. Open the deal to record one.');
        }

        Deals::place($deal, $stage, null, null);
        Audit::log($me, Deals::statusFor($stage) === 'WON' ? 'won' : 'stage_change', 'DEAL',
            (int) $deal['id'], $deal['title'], ['stage' => $deal['stage_id']], ['stage' => $stage['name']]);

        return ['ok' => true, 'display' => esc($stage['name']), 'value' => (string) $stage['id'], 'error' => ''];
    }

    /** True when the figure comes from the line items rather than from a person. */
    private static function amountIsDerived(array $deal): bool
    {
        if (! empty($deal['amount_is_manual'])) {
            return false;
        }
        return db_connect()->table('deal_line_items')->where('deal_id', $deal['id'])->countAllResults() > 0;
    }

    /** Turn what was typed into what should be stored, or explain why it cannot be. */
    private static function clean(array $spec, string $value, int $orgId, string $entity): array
    {
        $max = $spec['max'] ?? 190;

        switch ($spec['type']) {
            case 'email':
                if ($value === '') {
                    return ['value' => null];
                }
                if (! filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    return ['error' => 'That is not a valid email address.'];
                }
                return ['value' => mb_substr($value, 0, $max)];

            case 'phone':
                if ($value === '') {
                    return ['value' => null];
                }
                if (preg_match_all('/\d/', $value) < 6) {
                    return ['error' => 'That does not look like a phone number.'];
                }
                return ['value' => mb_substr($value, 0, $max)];

            case 'url':
                if ($value === '') {
                    return ['value' => null];
                }
                if (! preg_match('#^https?://#i', $value)) {
                    $value = 'https://' . $value;
                }
                if (! filter_var($value, FILTER_VALIDATE_URL)) {
                    return ['error' => 'That is not a valid web address.'];
                }
                return ['value' => mb_substr($value, 0, $max)];

            case 'date':
                if ($value === '') {
                    return ['value' => null];
                }
                $d = parse_date($value);
                return $d === null ? ['error' => 'Use a date like 2026-03-31.'] : ['value' => $d];

            case 'money':
                if ($value === '') {
                    return ['value' => null];
                }
                $n = money($value);
                if (! money_in_range($n)) {
                    return ['error' => 'That amount is too large. The most this field takes is ' . max_money_label() . '.'];
                }
                return ['value' => $n];

            case 'int':
                if ($value === '') {
                    return ['value' => null];
                }
                if (! is_numeric($value)) {
                    return ['error' => 'That needs to be a number.'];
                }
                $n = (int) $value;
                if ($n < 0 || $n > (int) $max) {
                    return ['error' => 'That number is out of range.'];
                }
                return ['value' => $n];

            case 'select':
                if ($value === '') {
                    return ['value' => null];
                }
                $allowed = array_column(self::options($entity, $orgId)[$spec['options']] ?? [], 'value');
                if (! in_array($value, $allowed, true)) {
                    return ['error' => 'That is not one of the choices.'];
                }
                return ['value' => (int) $value];

            case 'lookup':
                if ($value === '') {
                    return ['value' => null];
                }
                $model = strpos($spec['search'], 'companies') !== false ? CompanyModel::class : ContactModel::class;
                if (! model($model)->findInOrg($orgId, (int) $value)) {
                    return ['error' => 'That record could not be found.'];
                }
                return ['value' => (int) $value];

            default:
                return ['value' => $value === '' ? null : mb_substr($value, 0, $max)];
        }
    }

    /** What the cell should read after a successful save. Already escaped. */
    private static function display(array $spec, $stored, int $orgId, string $entity): string
    {
        $dash = '<span class="muted">—</span>';

        if ($stored === null || $stored === '') {
            return $dash;
        }

        switch ($spec['type']) {
            case 'date':
                return esc(format_date($stored));

            case 'money':
                return esc(format_inr($stored));

            case 'select':
                foreach (self::options($entity, $orgId)[$spec['options']] ?? [] as $o) {
                    if ($o['value'] === (string) $stored) {
                        // The pipeline prefix is only there to disambiguate in the dropdown.
                        $label = $o['label'];
                        $cut = strpos($label, ' · ');
                        return esc($cut === false ? $label : substr($label, $cut + 4));
                    }
                }
                return $dash;

            case 'lookup':
                $isCompany = strpos($spec['search'], 'companies') !== false;
                $row = $isCompany
                    ? model(CompanyModel::class)->findInOrg($orgId, (int) $stored)
                    : model(ContactModel::class)->findInOrg($orgId, (int) $stored);
                if (! $row) {
                    return $dash;
                }
                $href = $isCompany ? '/companies/' . (int) $stored : '/contacts/' . (int) $stored;
                $name = $isCompany ? $row['name'] : full_name($row);
                return '<a href="' . $href . '" class="hover:text-primary">' . esc($name) . '</a>';

            default:
                return esc((string) $stored);
        }
    }

    private static function titleOf(string $entity, ?array $row): string
    {
        if (! $row) {
            return '';
        }
        if ($entity === 'CONTACT') {
            return full_name($row);
        }
        return (string) ($row['name'] ?? $row['title'] ?? '');
    }

    /**
     * Did the value actually reach the database?
     *
     * Loose about type, because a decimal column reads back as "250000.00" where 250000
     * went in, and an int column as "12" rather than 12. Strict about whether anything
     * arrived at all, which is the case that matters.
     */
    private static function landed($actual, $intended): bool
    {
        if ($intended === null || $intended === '') {
            return $actual === null || $actual === '';
        }
        if (is_numeric($intended) && is_numeric($actual)) {
            return abs((float) $actual - (float) $intended) < 0.005;
        }
        return (string) $actual === (string) $intended;
    }

    private static function no(string $message): array
    {
        return ['ok' => false, 'display' => '', 'value' => '', 'error' => $message];
    }
}
