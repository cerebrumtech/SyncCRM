<?php

namespace App\Database\Seeds;

use App\Libraries\Auth;
use App\Libraries\Defaults;

/**
 * Demo workspace for SyncWorks Technologies. Run: php spark db:seed DemoSeeder
 * Safe to re-run: it does nothing when the demo organisation already exists.
 */
class DemoSeeder
{
    private const ORG = 'SyncWorks Technologies Pvt. Ltd.';
    private const PASSWORD = 'password123';

    public function run(): void
    {
        $db = db_connect();
        if ($db->table('organizations')->where('name', self::ORG)->countAllResults() > 0) {
            echo "Demo organisation already exists — nothing to do.\n";
            return;
        }
        $now = date('Y-m-d H:i:s');
        $day = 86400;
        $db->table('organizations')->insert(['name' => self::ORG, 'settings' => '{}', 'created_at' => $now, 'updated_at' => $now]);
        $orgId = $db->insertID();
        $hash = Auth::hash(self::PASSWORD);
        $u = [];
        foreach ([['owner', 'Hanuman Kale', 'OWNER', '#1B243E'], ['admin', 'Sneha Patil', 'ADMIN', '#0068FF'], ['rahul', 'Rahul Deshmukh', 'MEMBER', '#10B981'], ['priya', 'Priya Joshi', 'MEMBER', '#F59E0B']] as [$k, $name, $role, $color]) {
            $db->table('users')->insert(['organization_id' => $orgId, 'email' => "$k@syncworkstech.com", 'name' => $name, 'password_hash' => $hash, 'role' => $role, 'is_active' => 1, 'color' => $color, 'created_at' => $now, 'updated_at' => $now]);
            $u[$k] = $db->insertID();
        }
        $db->table('teams')->insert(['organization_id' => $orgId, 'name' => 'Pune Sales', 'created_at' => $now]);
        $teamId = $db->insertID();
        $db->table('team_members')->insertBatch([['team_id' => $teamId, 'user_id' => $u['rahul']], ['team_id' => $teamId, 'user_id' => $u['priya']]]);

        Defaults::createPipelines($orgId);
        $sales = $db->table('pipelines')->where('organization_id', $orgId)->where('name', 'Sales')->get()->getRowArray();
        $stages = [];
        foreach ($db->table('stages')->where('pipeline_id', $sales['id'])->get()->getResultArray() as $s) {
            $stages[$s['name']] = $s;
        }

        $db->table('custom_field_definitions')->insertBatch([
            ['organization_id' => $orgId, 'entity' => 'COMPANY', 'field_key' => 'gst_number', 'label' => 'GST number', 'type' => 'TEXT', 'options' => '[]', 'required' => 0, 'position' => 0],
            ['organization_id' => $orgId, 'entity' => 'COMPANY', 'field_key' => 'branches', 'label' => 'Branches', 'type' => 'NUMBER', 'options' => '[]', 'required' => 0, 'position' => 1],
            ['organization_id' => $orgId, 'entity' => 'DEAL', 'field_key' => 'licence_type', 'label' => 'Licence type', 'type' => 'SELECT', 'options' => json_encode(['Standard', 'Professional', 'Enterprise']), 'required' => 0, 'position' => 0],
            ['organization_id' => $orgId, 'entity' => 'CONTACT', 'field_key' => 'preferred_language', 'label' => 'Preferred language', 'type' => 'SELECT', 'options' => json_encode(['Marathi', 'Hindi', 'English']), 'required' => 0, 'position' => 0],
        ]);

        $products = [];
        foreach ([['SyncLMS Standard (annual)', 'LMS-STD-1Y', 120000, 'Loan management for up to 5 branches.', 1], ['SyncLMS Professional (annual)', 'LMS-PRO-1Y', 240000, 'Unlimited branches, eNACH, bureau integration.', 1], ['SyncVerify KYC pack (1,000 checks)', 'VER-1K', 15000, null, 1], ['Implementation & training', 'SVC-IMPL', 50000, null, 1], ['Legacy module (discontinued)', 'LMS-LEG', 10000, null, 0]] as [$name, $sku, $price, $desc, $active]) {
            $db->table('products')->insert(['organization_id' => $orgId, 'name' => $name, 'sku' => $sku, 'price' => $price, 'tax_rate' => 18, 'description' => $desc, 'is_active' => $active, 'created_at' => $now, 'updated_at' => $now]);
            $products[] = ['id' => $db->insertID(), 'name' => $name, 'price' => $price, 'tax' => 18];
        }

        $companies = [];
        foreach ([
            ['Shivneri Nagari Sahakari Patsanstha', 'Co-operative Credit Society', 'Pune', 'rahul', '27AAAAA0000A1Z5', 6, ['priority', 'patsanstha']],
            ['Godavari Urban Credit Society', 'Co-operative Credit Society', 'Nashik', 'priya', '27BBBBB1111B1Z2', 3, ['patsanstha']],
            ['Vidarbha Microfinance Ltd', 'Microfinance', 'Nagpur', 'rahul', '27CCCCC2222C1Z9', 14, ['mfi']],
            ['Konkan Finserv NBFC', 'NBFC', 'Ratnagiri', 'priya', '27DDDDD3333D1Z4', 2, ['nbfc', 'priority']],
            ['Marathwada Gramin Patsanstha', 'Co-operative Credit Society', 'Aurangabad', 'admin', null, 4, ['patsanstha']],
        ] as [$name, $industry, $city, $owner, $gst, $branches, $tags]) {
            $db->table('companies')->insert(['organization_id' => $orgId, 'name' => $name, 'industry' => $industry, 'city' => $city, 'state' => 'Maharashtra', 'country' => 'India', 'owner_id' => $u[$owner], 'tags' => json_encode($tags), 'custom_fields' => json_encode(['gst_number' => $gst, 'branches' => $branches]), 'created_at' => $now, 'updated_at' => $now]);
            $companies[] = $db->insertID();
        }

        $contacts = [];
        foreach ([
            ['Priya', 'Shah', 'priya.shah@shivneri.coop', '+91 98220 11111', 'Chairman', 0, 'rahul', 'Marathi'],
            ['Amol', 'Kulkarni', 'amol@shivneri.coop', '+91 98220 22222', 'CEO', 0, 'rahul', 'Marathi'],
            ['Sunita', 'Pawar', 'sunita@godavari.coop', '+91 98230 33333', 'Manager', 1, 'priya', 'Marathi'],
            ['Rakesh', 'Meshram', 'rakesh@vidarbhamf.in', '+91 98240 44444', 'Head of Operations', 2, 'rahul', 'Hindi'],
            ['Neha', 'Sawant', 'neha@konkanfinserv.in', '+91 98250 55555', 'Director', 3, 'priya', 'English'],
            ['Vikram', 'Jadhav', 'vikram@konkanfinserv.in', '+91 98250 66666', 'IT Manager', 3, 'priya', 'English'],
            ['Manisha', 'Gaikwad', 'manisha@marathwada.coop', '+91 98260 77777', 'Secretary', 4, 'admin', 'Marathi'],
            ['Sachin', 'More', null, '+91 98270 88888', 'Consultant', null, 'rahul', 'Marathi'],
        ] as [$first, $last, $email, $phone, $title, $co, $owner, $lang]) {
            $db->table('contacts')->insert(['organization_id' => $orgId, 'first_name' => $first, 'last_name' => $last, 'email' => $email, 'phone' => $phone, 'phone_normalized' => normalize_phone($phone), 'job_title' => $title, 'company_id' => $co === null ? null : $companies[$co], 'owner_id' => $u[$owner], 'tags' => json_encode($co === 0 || $co === 3 ? ['decision-maker'] : []), 'custom_fields' => json_encode(['preferred_language' => $lang]), 'created_at' => $now, 'updated_at' => $now]);
            $contacts[] = $db->insertID();
        }

        $deals = [];
        foreach ([
            ['SyncLMS Professional — Shivneri', 'Negotiation', 283200, 0, 0, 'rahul', 20, null, null, [1, 3]],
            ['SyncLMS Standard — Godavari', 'Proposal Sent', 141600, 2, 1, 'priya', 35, null, null, [0]],
            ['SyncVerify KYC — Vidarbha MF', 'Qualified', 53100, 3, 2, 'rahul', 45, null, null, [2, 2, 2]],
            ['SyncNBFC Suite — Konkan Finserv', 'Contacted', 500000, 4, 3, 'priya', 60, null, null, []],
            ['SyncLMS Standard — Marathwada', 'New Lead', 0, 6, 4, 'admin', 90, null, null, []],
            ['SyncLMS Standard — Konkan (branch 2)', 'Won', 141600, 5, 3, 'priya', -10, -10, null, [0]],
            ['SyncVerify pilot — Godavari', 'Won', 17700, 2, 1, 'priya', -40, -40, null, [2]],
            ['SyncLMS Pro — Vidarbha (2025 renewal)', 'Lost', 283200, 3, 2, 'rahul', -25, -25, 'Price too high', []],
        ] as $i => [$title, $stageName, $amount, $c, $co, $owner, $close, $closed, $lost, $items]) {
            $st = $stages[$stageName];
            $db->table('deals')->insert([
                'organization_id' => $orgId, 'title' => $title, 'pipeline_id' => $sales['id'], 'stage_id' => $st['id'], 'status' => $st['is_won'] ? 'WON' : ($st['is_lost'] ? 'LOST' : 'OPEN'),
                'amount' => $amount, 'amount_is_manual' => $items ? 0 : 1, 'expected_close_date' => date('Y-m-d', time() + $close * $day), 'closed_at' => $closed !== null ? date('Y-m-d H:i:s', time() + $closed * $day) : null,
                'lost_reason' => $lost, 'contact_id' => $contacts[$c], 'company_id' => $companies[$co], 'owner_id' => $u[$owner], 'position' => $i, 'tags' => json_encode($amount > 200000 ? ['big-ticket'] : []),
                'custom_fields' => json_encode(['licence_type' => str_contains($title, 'Pro') ? 'Professional' : 'Standard']), 'created_at' => $now, 'updated_at' => $now,
            ]);
            $dealId = $db->insertID();
            $deals[] = $dealId;
            foreach ($items as $j => $pi) {
                $p = $products[$pi];
                $db->table('deal_line_items')->insert(['deal_id' => $dealId, 'product_id' => $p['id'], 'name' => $p['name'], 'quantity' => 1, 'unit_price' => $p['price'], 'discount_percent' => 0, 'tax_rate' => $p['tax'], 'total' => round($p['price'] * 1.18, 2), 'position' => $j]);
            }
        }

        $t = fn (int $days, int $hour = 10) => date('Y-m-d H:i:s', strtotime("today +{$days} days {$hour}:00"));
        foreach ([
            ['organization_id' => $orgId, 'type' => 'TASK', 'title' => 'Send revised proposal to Shivneri', 'status' => 'OPEN', 'due_at' => $t(1), 'assignee_id' => $u['rahul'], 'created_by_id' => $u['rahul'], 'contact_id' => $contacts[0], 'company_id' => $companies[0], 'deal_id' => $deals[0], 'reminder_minutes' => 60, 'attendees' => '[]', 'recurrence' => 'NONE', 'created_at' => $now, 'updated_at' => $now],
            ['organization_id' => $orgId, 'type' => 'TASK', 'title' => 'Follow up on KYC pilot pricing', 'status' => 'OPEN', 'due_at' => $t(-2), 'assignee_id' => $u['rahul'], 'created_by_id' => $u['rahul'], 'contact_id' => $contacts[3], 'company_id' => $companies[2], 'deal_id' => $deals[2], 'attendees' => '[]', 'recurrence' => 'NONE', 'created_at' => $now, 'updated_at' => $now],
            ['organization_id' => $orgId, 'type' => 'EVENT', 'title' => 'Demo at Godavari head office', 'status' => 'OPEN', 'due_at' => $t(3, 11), 'end_at' => $t(3, 12), 'location' => 'Nashik', 'attendees' => json_encode(['sunita@godavari.coop']), 'assignee_id' => $u['priya'], 'created_by_id' => $u['priya'], 'contact_id' => $contacts[2], 'company_id' => $companies[1], 'deal_id' => $deals[1], 'reminder_minutes' => 15, 'recurrence' => 'NONE', 'created_at' => $now, 'updated_at' => $now],
            ['organization_id' => $orgId, 'type' => 'CALL', 'title' => 'Intro call with Neha', 'status' => 'COMPLETED', 'due_at' => $t(-1, 15), 'completed_at' => $t(-1, 15), 'call_direction' => 'OUTBOUND', 'call_duration_sec' => 900, 'call_outcome' => 'Interested', 'assignee_id' => $u['priya'], 'created_by_id' => $u['priya'], 'contact_id' => $contacts[4], 'company_id' => $companies[3], 'deal_id' => $deals[3], 'attendees' => '[]', 'recurrence' => 'NONE', 'created_at' => $now, 'updated_at' => $now],
            ['organization_id' => $orgId, 'type' => 'TASK', 'title' => 'Weekly pipeline review', 'status' => 'OPEN', 'due_at' => $t(2, 9), 'recurrence' => 'WEEKLY', 'assignee_id' => $u['admin'], 'created_by_id' => $u['owner'], 'attendees' => '[]', 'created_at' => $now, 'updated_at' => $now],
            ['organization_id' => $orgId, 'type' => 'CALL', 'title' => 'Call Manisha about board approval', 'status' => 'OPEN', 'due_at' => $t(1, 16), 'call_direction' => 'OUTBOUND', 'assignee_id' => $u['admin'], 'created_by_id' => $u['admin'], 'contact_id' => $contacts[6], 'company_id' => $companies[4], 'deal_id' => $deals[4], 'attendees' => '[]', 'recurrence' => 'NONE', 'created_at' => $now, 'updated_at' => $now],
        ] as $row) {
            $db->table('activities')->insert($row);
        }
        foreach ([
            ['organization_id' => $orgId, 'body' => 'Board meets on the 28th; Priya wants the proposal before then.', 'author_id' => $u['rahul'], 'contact_id' => $contacts[0], 'created_at' => $now, 'updated_at' => $now],
            ['organization_id' => $orgId, 'body' => 'Currently on Excel + Tally. 6 branches, ~9,000 members.', 'author_id' => $u['rahul'], 'company_id' => $companies[0], 'created_at' => $now, 'updated_at' => $now],
            ['organization_id' => $orgId, 'body' => 'Competitor quoted lower; emphasise eNACH savings.', 'author_id' => $u['rahul'], 'deal_id' => $deals[0], 'created_at' => $now, 'updated_at' => $now],
        ] as $row) {
            $db->table('notes')->insert($row);
        }
        $db->table('tags')->insertBatch(array_map(fn ($n) => ['organization_id' => $orgId, 'name' => $n, 'color' => '#EBF3FF'], ['priority', 'patsanstha', 'mfi', 'nbfc', 'decision-maker', 'big-ticket']));
        $db->table('saved_views')->insert(['organization_id' => $orgId, 'entity' => 'COMPANY', 'name' => 'Priority accounts', 'filters' => json_encode(['tag' => 'priority']), 'owner_id' => $u['owner'], 'is_shared' => 1, 'created_at' => $now]);
        $db->table('audit_logs')->insert(['organization_id' => $orgId, 'actor_id' => $u['owner'], 'action' => 'seed', 'entity' => 'Organization', 'entity_id' => (string) $orgId, 'entity_label' => self::ORG, 'created_at' => $now]);
        // List the accounts this seeder actually creates. Naming ones it does not
        // send whoever follows the runbook chasing a login that will never work.
        $accounts = $db->table('users')->select('email, role')->where('organization_id', $orgId)->orderBy('id')->get()->getResultArray();
        echo 'Seeded ' . self::ORG . "\nSign in with any of these, password " . self::PASSWORD . ":\n";
        foreach ($accounts as $a) {
            echo '  ' . str_pad($a['email'], 34) . strtolower($a['role']) . "\n";
        }
    }
}
