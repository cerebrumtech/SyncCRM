<?php

namespace App\Controllers;

use App\Libraries\Activities as Act;
use App\Libraries\Deals as DealLib;
use App\Libraries\Lists;

class Dashboard extends BaseController
{
    private const RANGES = ['month' => 'This month', 'last30' => 'Last 30 days', 'quarter' => 'This quarter', 'year' => 'This year', 'all' => 'All time'];

    private function since(string $key): ?string
    {
        switch ($key) {
            case 'month':
                return date('Y-m-01 00:00:00');
            case 'last30':
                return date('Y-m-d 00:00:00', strtotime('-30 days'));
            case 'quarter':
                return date('Y-m-d 00:00:00', mktime(0, 0, 0, (int) (floor((date('n') - 1) / 3) * 3 + 1), 1, (int) date('Y')));
            case 'year':
                return date('Y-01-01 00:00:00');
            default:
                return null;
        }
    }

    public function index()
    {
        $p = $this->request->getGet();
        $orgId = $this->orgId();
        $db = db_connect();
        $range = isset(self::RANGES[$p['range'] ?? '']) ? $p['range'] : 'month';
        $owner = ! empty($p['owner']) && ctype_digit((string) $p['owner']) ? (int) $p['owner'] : null;
        $since = $this->since($range);
        $pipelines = DealLib::pipelines($orgId);
        $pipeline = null;
        foreach ($pipelines as $pl) {
            if ((string) $pl['id'] === (string) ($p['pipeline'] ?? '')) {
                $pipeline = $pl;
            }
        }
        $pipeline ??= array_values(array_filter($pipelines, fn ($pl) => $pl['is_default']))[0] ?? ($pipelines[0] ?? null);
        $users = Lists::activeUsers($orgId);
        $userMap = array_column($users, null, 'id');
        $now = now_sql();
        $today = date('Y-m-d 00:00:00');
        $tomorrow = date('Y-m-d 00:00:00', strtotime('+1 day'));

        $deals = fn () => $owner ? $db->table('deals')->where('organization_id', $orgId)->where('owner_id', $owner) : $db->table('deals')->where('organization_id', $orgId);
        $openDeals = $pipeline ? $deals()->select('stage_id, amount')->where('status', 'OPEN')->where('pipeline_id', $pipeline['id'])->get()->getResultArray() : [];
        $agg = function (string $status) use ($deals, $since) {
            $b = $deals()->select('COUNT(*) AS n, COALESCE(SUM(amount),0) AS total')->where('status', $status);
            if ($since) {
                $b->where('closed_at >=', $since);
            }
            return $b->get()->getRowArray();
        };
        $won = $agg('WON');
        $lost = $agg('LOST');
        $wonCount = (int) $won['n'];
        $lostCount = (int) $lost['n'];
        $winRate = $wonCount + $lostCount > 0 ? (int) round($wonCount / ($wonCount + $lostCount) * 100) : null;

        $openValue = array_sum(array_map(fn ($d) => (float) $d['amount'], $openDeals));
        $stageRows = [];
        foreach ($pipeline['stages'] ?? [] as $s) {
            if ($s['is_won'] || $s['is_lost']) {
                continue;
            }
            $in = array_filter($openDeals, fn ($d) => (int) $d['stage_id'] === (int) $s['id']);
            $value = array_sum(array_map(fn ($d) => (float) $d['amount'], $in));
            $stageRows[] = ['label' => $s['name'], 'value' => $value, 'display' => format_compact_inr($value), 'sub' => count($in) . ' deal' . (count($in) === 1 ? '' : 's'), 'href' => '/deals?view=list&pipeline=' . $pipeline['id'] . '&status=OPEN&stage=' . $s['id'] . ($owner ? '&owner=' . $owner : ''), 'color' => $s['color']];
        }

        $byRepQ = $deals()->select('owner_id, COUNT(*) AS n, COALESCE(SUM(amount),0) AS total')->where('status', 'WON');
        if ($since) {
            $byRepQ->where('closed_at >=', $since);
        }
        $byRep = [];
        foreach ($byRepQ->groupBy('owner_id')->orderBy('total', 'DESC')->get()->getResultArray() as $r) {
            $u = $userMap[$r['owner_id']] ?? null;
            if ($u) {
                $byRep[] = ['label' => $u['name'], 'value' => (float) $r['total'], 'display' => format_compact_inr($r['total']), 'sub' => $r['n'] . ' won', 'href' => '/deals?view=list&status=WON&owner=' . $u['id']];
            }
        }

        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $key = date('Y-m', strtotime(date('Y-m-01') . " -{$i} months"));
            $months[$key] = ['key' => $key, 'label' => date('M', strtotime($key . '-01')), 'won' => 0.0, 'lost' => 0.0, 'wonCount' => 0, 'lostCount' => 0];
        }
        $sixAgo = array_key_first($months) . '-01 00:00:00';
        foreach ($deals()->select('status, amount, closed_at')->whereIn('status', ['WON', 'LOST'])->where('closed_at >=', $sixAgo)->get()->getResultArray() as $d) {
            $k = substr($d['closed_at'], 0, 7);
            if (! isset($months[$k])) {
                continue;
            }
            if ($d['status'] === 'WON') {
                $months[$k]['won'] += (float) $d['amount'];
                $months[$k]['wonCount']++;
            } else {
                $months[$k]['lost'] += (float) $d['amount'];
                $months[$k]['lostCount']++;
            }
        }

        $actQ = fn () => $owner ? $db->table('activities')->where('organization_id', $orgId)->where('assignee_id', $owner) : $db->table('activities')->where('organization_id', $orgId);
        $typeCounts = ['TASK' => 0, 'CALL' => 0, 'EVENT' => 0];
        $q = $actQ()->select('type, COUNT(*) AS n');
        if ($since) {
            $q->where('created_at >=', $since);
        }
        foreach ($q->groupBy('type')->get()->getResultArray() as $r) {
            $typeCounts[$r['type']] = (int) $r['n'];
        }
        $activityRows = [];
        foreach (['TASK' => 'Tasks', 'CALL' => 'Calls', 'EVENT' => 'Meetings'] as $t => $label) {
            $activityRows[] = ['label' => $label, 'value' => $typeCounts[$t], 'display' => (string) $typeCounts[$t], 'href' => '/activities?range=all&assignee=' . ($owner ?? 'all') . '&type=' . $t];
        }
        $taskStatus = ['OPEN' => 0, 'COMPLETED' => 0];
        $q = $actQ()->select('status, COUNT(*) AS n')->where('type', 'TASK');
        if ($since) {
            $q->where('created_at >=', $since);
        }
        foreach ($q->groupBy('status')->get()->getResultArray() as $r) {
            $taskStatus[$r['status']] = (int) $r['n'];
        }

        $mine = fn () => $db->table('activities')->where('organization_id', $orgId)->where('assignee_id', $this->me['id'])->where('status', 'OPEN');
        $myOpen = $mine()->countAllResults();
        $myOverdue = $mine()->where('due_at <', $now)->countAllResults();
        $myToday = $mine()->where('due_at >=', $today)->where('due_at <', $tomorrow)->countAllResults();
        $upcoming = Act::query($orgId)->where('a.assignee_id', $this->me['id'])->where('a.status', 'OPEN')->groupStart()->where('a.due_at >=', $today)->orWhere('a.due_at IS NULL')->groupEnd()
            ->orderBy('a.due_at IS NULL', '', false)->orderBy('a.due_at')->limit(8)->get()->getResultArray();

        $qs = fn (array $o) => http_build_query(array_filter(['pipeline' => $pipeline['id'] ?? null, 'range' => $range, 'owner' => $owner] + $o, fn ($v) => $v !== null && $v !== ''));
        return $this->render('dashboard/index', [
            'title' => 'Dashboard', 'ranges' => self::RANGES, 'range' => $range, 'owner' => $owner, 'pipelines' => $pipelines, 'pipeline' => $pipeline, 'users' => $users, 'userMap' => $userMap,
            'openValue' => $openValue, 'openCount' => count($openDeals), 'won' => $won, 'lost' => $lost, 'winRate' => $winRate, 'stageRows' => $stageRows, 'byRep' => $byRep, 'months' => array_values($months),
            'activityRows' => $activityRows, 'taskStatus' => $taskStatus, 'myOpen' => $myOpen, 'myOverdue' => $myOverdue, 'myToday' => $myToday, 'upcoming' => $upcoming, 'qs' => $qs,
        ]);
    }
}
